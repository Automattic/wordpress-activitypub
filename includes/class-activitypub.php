<?php
namespace Activitypub;

use Exception;
use Activitypub\Signature;
use Activitypub\Collection\Users;
use Activitypub\Collection\Followers;
use Activitypub\Collection\Extra_Fields;

use function Activitypub\is_comment;
use function Activitypub\sanitize_url;
use function Activitypub\is_local_comment;
use function Activitypub\site_supports_blocks;
use function Activitypub\is_user_type_disabled;
use function Activitypub\is_activitypub_request;
use function Activitypub\should_comment_be_federated;

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

		// Add support for ActivityPub to custom post types
		$post_types = \get_option( 'activitypub_support_post_types', array( 'post' ) ) ? \get_option( 'activitypub_support_post_types', array( 'post' ) ) : array();

		foreach ( $post_types as $post_type ) {
			\add_post_type_support( $post_type, 'activitypub' );
		}

		\add_action( 'wp_trash_post', array( self::class, 'trash_post' ), 1 );
		\add_action( 'untrash_post', array( self::class, 'untrash_post' ), 1 );

		\add_action( 'init', array( self::class, 'add_rewrite_rules' ), 11 );
		\add_action( 'init', array( self::class, 'theme_compat' ), 11 );

		\add_action( 'user_register', array( self::class, 'user_register' ) );

		\add_action( 'in_plugin_update_message-' . ACTIVITYPUB_PLUGIN_BASENAME, array( self::class, 'plugin_update_message' ) );

		if ( site_supports_blocks() ) {
			\add_action( 'tool_box', array( self::class, 'tool_box' ) );
		}

		\add_filter( 'activitypub_get_actor_extra_fields', array( Extra_Fields::class, 'default_actor_extra_fields' ), 10, 2 );

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

		if ( \is_author() && ! is_user_disabled( \get_the_author_meta( 'ID' ) ) ) {
			$json_template = ACTIVITYPUB_PLUGIN_DIR . '/templates/user-json.php';
		} elseif ( is_comment() ) {
			$json_template = ACTIVITYPUB_PLUGIN_DIR . '/templates/comment-json.php';
		} elseif ( \is_singular() ) {
			$json_template = ACTIVITYPUB_PLUGIN_DIR . '/templates/post-json.php';
		} elseif ( \is_home() && ! is_user_type_disabled( 'blog' ) ) {
			$json_template = ACTIVITYPUB_PLUGIN_DIR . '/templates/blog-json.php';
		}

		/*
		 * Check if the request is authorized.
		 *
		 * @see https://www.w3.org/wiki/SocialCG/ActivityPub/Primer/Authentication_Authorization#Authorized_fetch
		 * @see https://swicg.github.io/activitypub-http-signature/#authorized-fetch
		 */
		if ( $json_template && ACTIVITYPUB_AUTHORIZED_FETCH ) {
			$verification = Signature::verify_http_signature( $_SERVER );
			if ( \is_wp_error( $verification ) ) {
				header( 'HTTP/1.1 401 Unauthorized' );

				// fallback as template_loader can't return http headers
				return $template;
			}
		}

		if ( $json_template ) {
			return $json_template;
		}

		return $template;
	}

	/**
	 * Add the 'self' link to the header.
	 *
	 * @see
	 *
	 * @return void
	 */
	public static function add_headers() {
		// phpcs:ignore
		$request_uri = $_SERVER['REQUEST_URI'];

		if ( ! $request_uri ) {
			return;
		}

		// only add self link to author pages...
		if ( is_author() ) {
			if ( is_user_disabled( get_queried_object_id() ) ) {
				return;
			}
		} elseif ( is_singular() ) { // or posts/pages/custom-post-types...
			if ( ! \post_type_supports( \get_post_type(), 'activitypub' ) ) {
				return;
			}
		} else { // otherwise return
			return;
		}

		// add self link to html and http header
		$host      = wp_parse_url( home_url() );
		$self_link = esc_url(
			apply_filters(
				'self_link',
				set_url_scheme(
					// phpcs:ignore
					'http://' . $host['host'] . wp_unslash( $request_uri )
				)
			)
		);

		if ( ! headers_sent() ) {
			header( 'Link: <' . $self_link . '>; rel="alternate"; type="application/activity+json"' );
		}

		add_action(
			'wp_head',
			function () use ( $self_link ) {
				echo PHP_EOL . '<link rel="alternate" type="application/activity+json" href="' . esc_url( $self_link ) . '" />' . PHP_EOL;
			}
		);
	}

	/**
	 * Custom redirects for ActivityPub requests.
	 *
	 * @return void
	 */
	public static function template_redirect() {
		self::add_headers();

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
		if ( is_activitypub_request() && ! is_local_comment( $comment ) ) {
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
			if ( empty( $args['class'] ) ) {
				$args['class'] = array();
			} elseif ( \is_string( $args['class'] ) ) {
				$args['class'] = \explode( ' ', $args['class'] );
			}

			$args['url']     = $avatar;
			$args['class'][] = 'avatar-activitypub';
			$args['class'][] = 'u-photo';
			$args['class']   = \array_unique( $args['class'] );
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
			'index.php?rest_route=/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/$matches[1]',
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
	 * Adds metabox on wp-admin/tools.php
	 *
	 * @return void
	 */
	public static function tool_box() {
		if ( \current_user_can( 'edit_posts' ) ) {
			\load_template( ACTIVITYPUB_PLUGIN_DIR . 'templates/toolbox.php' );
		}
	}

	/**
	 * Theme compatibility stuff
	 *
	 * @return void
	 */
	public static function theme_compat() {
		// We assume that you want to use Post-Formats when enabling the setting
		if ( 'wordpress-post-format' === \get_option( 'activitypub_object_type', ACTIVITYPUB_DEFAULT_OBJECT_TYPE ) ) {
			if ( ! get_theme_support( 'post-formats' ) ) {
				// Add support for the Aside, Gallery Post Formats...
				add_theme_support(
					'post-formats',
					array(
						'gallery',
						'status',
						'image',
						'video',
						'audio',
					)
				);
			}
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

		// Both User and Blog Extra Fields types have the same args.
		$args = array(
			'labels'           => array(
				'name'          => _x( 'Extra fields', 'post_type plural name', 'activitypub' ),
				'singular_name' => _x( 'Extra field', 'post_type single name', 'activitypub' ),
				'add_new'       => __( 'Add new', 'activitypub' ),
				'add_new_item'  => __( 'Add new extra field', 'activitypub' ),
				'new_item'      => __( 'New extra field', 'activitypub' ),
				'edit_item'     => __( 'Edit extra field', 'activitypub' ),
				'view_item'     => __( 'View extra field', 'activitypub' ),
				'all_items'     => __( 'All extra fields', 'activitypub' ),
			),
			'public'              => false,
			'hierarchical'        => false,
			'query_var'           => false,
			'has_archive'         => false,
			'publicly_queryable'  => false,
			'show_in_menu'        => false,
			'delete_with_user'    => true,
			'can_export'          => true,
			'exclude_from_search' => true,
			'show_in_rest'        => true,
			'map_meta_cap'        => true,
			'show_ui'             => true,
			'supports'            => array( 'title', 'editor', 'page-attributes' ),
		);

		\register_post_type( Extra_Fields::USER_POST_TYPE, $args );
		\register_post_type( Extra_Fields::BLOG_POST_TYPE, $args );

		\do_action( 'activitypub_after_register_post_type' );
	}

	/**
	 * Add the 'activitypub' capability to users who can publish posts.
	 *
	 * @param int   $user_id  User ID.
	 *
	 * @param array $userdata The raw array of data passed to wp_insert_user().
	 */
	public static function user_register( $user_id ) {
		if ( \user_can( $user_id, 'publish_posts' ) ) {
			$user = \get_user_by( 'id', $user_id );
			$user->add_cap( 'activitypub' );
		}
	}
}
