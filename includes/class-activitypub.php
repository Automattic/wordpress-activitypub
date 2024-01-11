<?php
namespace Activitypub;

use Exception;
use Activitypub\Signature;
use Activitypub\Collection\Users;
use Activitypub\Collection\Followers;

use function Activitypub\sanitize_url;

use function Activitypub\is_comment;
use function Activitypub\is_activitypub_request;

/**
 * ActivityPub Class
 *
 * @author Matthias Pfefferle
 */
class Activitypub {
	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		\add_filter( 'template_include', array( self::class, 'render_json_template' ), 99 );
		\add_action( 'template_redirect', array( self::class, 'template_redirect' ) );
		\add_filter( 'query_vars', array( self::class, 'add_query_vars' ) );
		\add_filter( 'pre_get_avatar_data', array( self::class, 'pre_get_avatar_data' ), 11, 2 );
		\add_filter( 'get_comment_link', array( self::class, 'remote_comment_link' ), 11, 3 );

		// Add support for ActivityPub to custom post types
		$post_types = \get_option( 'activitypub_support_post_types', array( 'post', 'page' ) ) ? \get_option( 'activitypub_support_post_types', array( 'post', 'page' ) ) : array();

		foreach ( $post_types as $post_type ) {
			\add_post_type_support( $post_type, 'activitypub' );
		}

		\add_action( 'wp_trash_post', array( self::class, 'trash_post' ), 1 );
		\add_action( 'untrash_post', array( self::class, 'untrash_post' ), 1 );

		\add_action( 'init', array( self::class, 'add_rewrite_rules' ), 11 );

		\add_action( 'after_setup_theme', array( self::class, 'theme_compat' ), 99 );

		\add_action( 'in_plugin_update_message-' . ACTIVITYPUB_PLUGIN_BASENAME, array( self::class, 'plugin_update_message' ) );

		\add_filter( 'comment_class', array( self::class, 'comment_class' ), 10, 3 );

		// register several post_types
		self::register_post_types();
	}

	/**
	 * Activation Hook
	 *
	 * @return void
	 */
	public static function activate() {
		self::flush_rewrite_rules();

		Scheduler::register_schedules();
	}

	/**
	 * Deactivation Hook
	 *
	 * @return void
	 */
	public static function deactivate() {
		self::flush_rewrite_rules();
		Scheduler::deregister_schedules();
	}

	/**
	 * Uninstall Hook
	 *
	 * @return void
	 */
	public static function uninstall() {
		Scheduler::deregister_schedules();
	}

	/**
	 * Return a AS2 JSON version of an author, post or page.
	 *
	 * @param  string $template The path to the template object.
	 *
	 * @return string The new path to the JSON template.
	 */
	public static function render_json_template( $template ) {
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return $template;
		}

		if ( ! is_activitypub_request() ) {
			return $template;
		}

		$json_template = false;

		// check if user can publish posts
		if ( \is_author() && is_wp_error( Users::get_by_id( \get_the_author_meta( 'ID' ) ) ) ) {
			return $template;
		}

		if ( \is_author() ) {
			$json_template = ACTIVITYPUB_PLUGIN_DIR . '/templates/author-json.php';
		} elseif ( is_comment() ) {
			$json_template = ACTIVITYPUB_PLUGIN_DIR . '/templates/comment-json.php';
		} elseif ( \is_singular() ) {
			$json_template = ACTIVITYPUB_PLUGIN_DIR . '/templates/post-json.php';
		} elseif ( \is_home() ) {
			$json_template = ACTIVITYPUB_PLUGIN_DIR . '/templates/blog-json.php';
		}

		if ( ACTIVITYPUB_AUTHORIZED_FETCH ) {
			$verification = Signature::verify_http_signature( $_SERVER );
			if ( \is_wp_error( $verification ) ) {
				// fallback as template_loader can't return http headers
				return $template;
			}
		}

		return $json_template;
	}

	/**
	 * Custom redirects for ActivityPub requests.
	 *
	 * @return void
	 */
	public static function template_redirect() {
		$comment_id = get_query_var( 'c', null );

		// check if it seems to be a comment
		if ( ! $comment_id ) {
			return;
		}

		$comment = get_comment( $comment_id );

		// load a 404 page if `c` is set but not valid
		if ( ! $comment ) {
			global $wp_query;
			$wp_query->set_404();
			return;
		}

		// stop if it's not an ActivityPub comment
		if ( is_activitypub_request() && $comment->user_id ) {
			return;
		}

		wp_safe_redirect( get_comment_link( $comment ) );
		exit;
	}

	/**
	 * Add the 'activitypub' query variable so WordPress won't mangle it.
	 */
	public static function add_query_vars( $vars ) {
		$vars[] = 'activitypub';
		$vars[] = 'c';
		$vars[] = 'p';

		return $vars;
	}

	/**
	 * Replaces the default avatar.
	 *
	 * @param array             $args        Arguments passed to get_avatar_data(), after processing.
	 * @param int|string|object $id_or_email A user ID, email address, or comment object.
	 *
	 * @return array $args
	 */
	public static function pre_get_avatar_data( $args, $id_or_email ) {
		if (
			! $id_or_email instanceof \WP_Comment ||
			! isset( $id_or_email->comment_type ) ||
			$id_or_email->user_id
		) {
			return $args;
		}

		$allowed_comment_types = \apply_filters( 'get_avatar_comment_types', array( 'comment' ) );
		if (
			! empty( $id_or_email->comment_type ) &&
			! \in_array(
				$id_or_email->comment_type,
				(array) $allowed_comment_types,
				true
			)
		) {
			$args['url'] = false;
			/** This filter is documented in wp-includes/link-template.php */
			return \apply_filters( 'get_avatar_data', $args, $id_or_email );
		}

		// Check if comment has an avatar.
		$avatar = self::get_avatar_url( $id_or_email->comment_ID );

		if ( $avatar ) {
			if ( ! isset( $args['class'] ) || ! \is_array( $args['class'] ) ) {
				$args['class'] = array( 'u-photo' );
			} else {
				$args['class'][] = 'u-photo';
				$args['class']   = \array_unique( $args['class'] );
			}
			$args['url']     = $avatar;
			$args['class'][] = 'avatar-activitypub';
		}

		return $args;
	}

	/**
	 * Function to retrieve Avatar URL if stored in meta.
	 *
	 * @param int|WP_Comment $comment
	 *
	 * @return string $url
	 */
	public static function get_avatar_url( $comment ) {
		if ( \is_numeric( $comment ) ) {
			$comment = \get_comment( $comment );
		}
		return \get_comment_meta( $comment->comment_ID, 'avatar_url', true );
	}

	/**
	 * Link remote comments to source url.
	 *
	 * @param string $comment_link
	 * @param object|WP_Comment $comment
	 *
	 * @return string $url
	 */
	public static function remote_comment_link( $comment_link, $comment ) {
		if ( ! $comment || is_admin() ) {
			return $comment_link;
		}

		$comment_meta = \get_comment_meta( $comment->comment_ID );

		if ( ! empty( $comment_meta['source_url'][0] ) ) {
			return $comment_meta['source_url'][0];
		} elseif ( ! empty( $comment_meta['source_id'][0] ) ) {
			return $comment_meta['source_id'][0];
		}

		return $comment_link;
	}

	/**
	 * Store permalink in meta, to send delete Activity.
	 *
	 * @param string $post_id The Post ID.
	 *
	 * @return void
	 */
	public static function trash_post( $post_id ) {
		\add_post_meta(
			$post_id,
			'activitypub_canonical_url',
			\get_permalink( $post_id ),
			true
		);
	}

	/**
	 * Delete permalink from meta
	 *
	 * @param string $post_id The Post ID
	 *
	 * @return void
	 */
	public static function untrash_post( $post_id ) {
		\delete_post_meta( $post_id, 'activitypub_canonical_url' );
	}

	/**
	 * Add rewrite rules
	 */
	public static function add_rewrite_rules() {
		// If another system needs to take precedence over the ActivityPub rewrite rules,
		// they can define their own and will manually call the appropriate functions as required.
		if ( ACTIVITYPUB_DISABLE_REWRITES ) {
			return;
		}

		if ( ! \class_exists( 'Webfinger' ) ) {
			\add_rewrite_rule(
				'^.well-known/webfinger',
				'index.php?rest_route=/' . ACTIVITYPUB_REST_NAMESPACE . '/webfinger',
				'top'
			);
		}

		if ( ! \class_exists( 'Nodeinfo_Endpoint' ) && true === (bool) \get_option( 'blog_public', 1 ) ) {
			\add_rewrite_rule(
				'^.well-known/nodeinfo',
				'index.php?rest_route=/' . ACTIVITYPUB_REST_NAMESPACE . '/nodeinfo/discovery',
				'top'
			);
			\add_rewrite_rule(
				'^.well-known/x-nodeinfo2',
				'index.php?rest_route=/' . ACTIVITYPUB_REST_NAMESPACE . '/nodeinfo2',
				'top'
			);
		}

		\add_rewrite_rule(
			'^@([\w\-\.]+)',
			'index.php?rest_route=/' . ACTIVITYPUB_REST_NAMESPACE . '/users/$matches[1]',
			'top'
		);

		\add_rewrite_endpoint( 'activitypub', EP_AUTHORS | EP_PERMALINK | EP_PAGES );
	}

	/**
	 * Flush rewrite rules;
	 */
	public static function flush_rewrite_rules() {
		self::add_rewrite_rules();
		\flush_rewrite_rules();
	}

	/**
	 * Theme compatibility stuff
	 *
	 * @return void
	 */
	public static function theme_compat() {
		$site_icon = get_theme_support( 'custom-logo' );

		if ( ! $site_icon ) {
			// custom logo support
			add_theme_support(
				'custom-logo',
				array(
					'height' => 80,
					'width'  => 80,
				)
			);
		}

		$custom_header = get_theme_support( 'custom-header' );

		if ( ! $custom_header ) {
			// This theme supports a custom header
			$custom_header_args = array(
				'width'       => 1250,
				'height'      => 600,
				'header-text' => true,
			);
			add_theme_support( 'custom-header', $custom_header_args );
		}
	}

	/**
	 * Display plugin upgrade notice to users
	 *
	 * @param array $data The plugin data
	 *
	 * @return void
	 */
	public static function plugin_update_message( $data ) {
		if ( ! isset( $data['upgrade_notice'] ) ) {
			return;
		}

		printf(
			'<div class="update-message">%s</div>',
			wp_kses(
				wpautop( $data['upgrade_notice '] ),
				array(
					'p'      => array(),
					'a'      => array( 'href', 'title' ),
					'strong' => array(),
					'em'     => array(),
				)
			)
		);
	}

	/**
	 * Register the "Followers" Taxonomy
	 *
	 * @return void
	 */
	private static function register_post_types() {
		\register_post_type(
			Followers::POST_TYPE,
			array(
				'labels'           => array(
					'name'          => _x( 'Followers', 'post_type plural name', 'activitypub' ),
					'singular_name' => _x( 'Follower', 'post_type single name', 'activitypub' ),
				),
				'public'           => false,
				'hierarchical'     => false,
				'rewrite'          => false,
				'query_var'        => false,
				'delete_with_user' => false,
				'can_export'       => true,
				'supports'         => array(),
			)
		);

		\register_post_meta(
			Followers::POST_TYPE,
			'activitypub_inbox',
			array(
				'type'              => 'string',
				'single'            => true,
				'sanitize_callback' => 'sanitize_url',
			)
		);

		\register_post_meta(
			Followers::POST_TYPE,
			'activitypub_errors',
			array(
				'type'              => 'string',
				'single'            => false,
				'sanitize_callback' => function ( $value ) {
					if ( ! is_string( $value ) ) {
						throw new Exception( 'Error message is no valid string' );
					}

					return esc_sql( $value );
				},
			)
		);

		\register_post_meta(
			Followers::POST_TYPE,
			'activitypub_user_id',
			array(
				'type'              => 'string',
				'single'            => false,
				'sanitize_callback' => function ( $value ) {
					return esc_sql( $value );
				},
			)
		);

		\register_post_meta(
			Followers::POST_TYPE,
			'activitypub_actor_json',
			array(
				'type'              => 'string',
				'single'            => true,
				'sanitize_callback' => function ( $value ) {
					return sanitize_text_field( $value );
				},
			)
		);

		\do_action( 'activitypub_after_register_post_type' );
	}

	/**
	 * Filters the CSS classes to add an ActivityPub class.
	 *
	 * @param string[] $classes    An array of comment classes.
	 * @param string[] $css_class  An array of additional classes added to the list.
	 * @param string   $comment_id The comment ID as a numeric string.
	 *
	 * @return string[] An array of classes.
	 */
	public static function comment_class( $classes, $css_class, $comment_id ) {
		// check if ActivityPub comment
		if ( 'activitypub' === get_comment_meta( $comment_id, 'protocol', true ) ) {
			$classes[] = 'activitypub-comment';
		}

		return $classes;
	}
}
