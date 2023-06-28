<?php
namespace Activitypub;

use Activitypub\Signature;

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
		\add_filter( 'query_vars', array( self::class, 'add_query_vars' ) );
		\add_filter( 'pre_get_avatar_data', array( self::class, 'pre_get_avatar_data' ), 11, 2 );

		// Add support for ActivityPub to custom post types
		$post_types = \get_option( 'activitypub_support_post_types', array( 'post', 'page' ) ) ? \get_option( 'activitypub_support_post_types', array( 'post', 'page' ) ) : array();

		foreach ( $post_types as $post_type ) {
			\add_post_type_support( $post_type, 'activitypub' );
		}

		\add_action( 'wp_trash_post', array( self::class, 'trash_post' ), 1 );
		\add_action( 'untrash_post', array( self::class, 'untrash_post' ), 1 );

		\add_action( 'init', array( self::class, 'add_rewrite_rules' ) );

		\add_action( 'after_setup_theme', array( self::class, 'theme_compat' ), 99 );
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
		if ( \is_author() && ! User_Factory::get_by_id( \get_the_author_meta( 'ID' ) ) ) {
			return $template;
		}

		if ( \is_author() ) {
			$json_template = ACTIVITYPUB_PLUGIN_DIR . '/templates/author-json.php';
		} elseif ( \is_singular() ) {
			$json_template = ACTIVITYPUB_PLUGIN_DIR . '/templates/post-json.php';
		} elseif ( \is_home() ) {
			$json_template = ACTIVITYPUB_PLUGIN_DIR . '/templates/blog-json.php';
		}

		if ( ACTIVITYPUB_SECURE_MODE ) {
			$verification = Signature::verify_http_signature( $_SERVER );
			if ( \is_wp_error( $verification ) ) {
				// fallback as template_loader can't return http headers
				return $template;
			}
		}

		return $json_template;
	}

	/**
	 * Add the 'activitypub' query variable so WordPress won't mangle it.
	 */
	public static function add_query_vars( $vars ) {
		$vars[] = 'activitypub';

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
	 * Store permalink in meta, to send delete Activity
	 *
	 * @param string $post_id The Post ID
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
		if ( ! \class_exists( 'Webfinger' ) ) {
			\add_rewrite_rule(
				'^.well-known/webfinger',
				'index.php?rest_route=/' . ACTIVITYPUB_REST_NAMESPACE . '/webfinger',
				'top'
			);
		}

		if ( ! \class_exists( 'Nodeinfo' ) && true === (bool) \get_option( 'blog_public', 1 ) ) {
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
			\add_rewrite_rule(
				'^@([\w\-\.]+)',
				'index.php?rest_route=/' . ACTIVITYPUB_REST_NAMESPACE . '/users/$matches[1]',
				'top'
			);
		}

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
}
