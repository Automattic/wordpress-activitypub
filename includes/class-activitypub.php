<?php
/**
 * ActivityPub Class.
 *
 * @package Activitypub
 */

namespace Activitypub;

use Activitypub\Collection\Actors;
use Activitypub\Collection\Followers;
use Activitypub\Collection\Following;
use Activitypub\Collection\Outbox;

/**
 * ActivityPub Class.
 *
 * @author Matthias Pfefferle
 */
class Activitypub {
	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		\add_action( 'init', array( self::class, 'add_rewrite_rules' ), 11 );
		\add_action( 'init', array( self::class, 'theme_compat' ), 11 );
		\add_action( 'init', array( self::class, 'register_user_meta' ), 11 );

		\add_filter( 'template_include', array( self::class, 'render_activitypub_template' ), 99 );
		\add_action( 'template_redirect', array( self::class, 'template_redirect' ) );
		\add_filter( 'redirect_canonical', array( self::class, 'redirect_canonical' ), 10, 2 );
		\add_filter( 'redirect_canonical', array( self::class, 'no_trailing_redirect' ), 10, 2 );
		\add_filter( 'query_vars', array( self::class, 'add_query_vars' ) );
		\add_filter( 'pre_get_avatar_data', array( self::class, 'pre_get_avatar_data' ), 11, 2 );

		\add_action( 'wp_trash_post', array( self::class, 'trash_post' ), 1 );
		\add_action( 'untrash_post', array( self::class, 'untrash_post' ), 1 );

		\add_action( 'user_register', array( self::class, 'user_register' ) );

		\add_action( 'activitypub_add_user_block', array( Followers::class, 'remove_blocked_actors' ), 10, 3 );
		\add_action( 'activitypub_add_user_block', array( Following::class, 'remove_blocked_actors' ), 10, 3 );
	}

	/**
	 * Activation Hook.
	 *
	 * @param bool $network_wide Whether to activate the plugin for all sites in the network or just the current site.
	 */
	public static function activate( $network_wide ) {
		self::flush_rewrite_rules();
		Scheduler::register_schedules();

		\add_filter( 'pre_wp_update_comment_count_now', array( Comment::class, 'pre_wp_update_comment_count_now' ), 10, 3 );
		Migration::update_comment_counts();

		if ( \is_multisite() && $network_wide && ! \wp_is_large_network() ) {
			$sites = \get_sites( array( 'fields' => 'ids' ) );
			foreach ( $sites as $site ) {
				\switch_to_blog( $site );
				self::flush_rewrite_rules();
				\restore_current_blog();
			}
		}
	}

	/**
	 * Deactivation Hook.
	 *
	 * @param bool $network_wide Whether to deactivate the plugin for all sites in the network or just the current site.
	 */
	public static function deactivate( $network_wide ) {
		self::flush_rewrite_rules();
		Scheduler::deregister_schedules();

		\remove_filter( 'pre_wp_update_comment_count_now', array( Comment::class, 'pre_wp_update_comment_count_now' ) );
		Migration::update_comment_counts( 2000 );

		if ( \is_multisite() && $network_wide && ! \wp_is_large_network() ) {
			$sites = \get_sites( array( 'fields' => 'ids' ) );
			foreach ( $sites as $site ) {
				\switch_to_blog( $site );
				self::flush_rewrite_rules();
				\restore_current_blog();
			}
		}
	}

	/**
	 * Uninstall Hook.
	 */
	public static function uninstall() {
		Scheduler::deregister_schedules();

		\remove_filter( 'pre_wp_update_comment_count_now', array( Comment::class, 'pre_wp_update_comment_count_now' ) );
		Migration::update_comment_counts( 2000 );

		Options::delete();
	}

	/**
	 * Return a AS2 JSON version of an author, post or page.
	 *
	 * @param  string $template The path to the template object.
	 *
	 * @return string The new path to the JSON template.
	 */
	public static function render_activitypub_template( $template ) {
		if ( \wp_is_serving_rest_request() || \wp_doing_ajax() ) {
			return $template;
		}

		self::add_headers();

		if ( ! is_activitypub_request() || ! should_negotiate_content() ) {
			if ( \get_query_var( 'p' ) && Outbox::POST_TYPE === \get_post_type( \get_query_var( 'p' ) ) ) {
				\set_query_var( 'is_404', true );
				\status_header( 406 );
			}
			return $template;
		}

		if ( Tombstone::exists_local( Query::get_instance()->get_request_url() ) ) {
			\status_header( 410 );
			return ACTIVITYPUB_PLUGIN_DIR . 'templates/tombstone-json.php';
		}

		$activitypub_template = false;
		$activitypub_object   = Query::get_instance()->get_activitypub_object();

		if ( $activitypub_object ) {
			if ( \get_query_var( 'preview' ) ) {
				\define( 'ACTIVITYPUB_PREVIEW', true );

				/**
				 * Filter the template used for the ActivityPub preview.
				 *
				 * @param string $activitypub_template Absolute path to the template file.
				 */
				$activitypub_template = apply_filters( 'activitypub_preview_template', ACTIVITYPUB_PLUGIN_DIR . '/templates/post-preview.php' );
			} else {
				$activitypub_template = ACTIVITYPUB_PLUGIN_DIR . 'templates/activitypub-json.php';
			}
		}

		/*
		 * Check if the request is authorized.
		 *
		 * @see https://www.w3.org/wiki/SocialCG/ActivityPub/Primer/Authentication_Authorization#Authorized_fetch
		 * @see https://swicg.github.io/activitypub-http-signature/#authorized-fetch
		 */
		if ( $activitypub_template && use_authorized_fetch() ) {
			$verification = Signature::verify_http_signature( $_SERVER );
			if ( \is_wp_error( $verification ) ) {
				\status_header( 401 );

				// Fallback as template_loader can't return http headers.
				return $template;
			}
		}

		if ( $activitypub_template ) {
			\set_query_var( 'is_404', false );

			// Check if header already sent.
			if ( ! \headers_sent() ) {
				// Send 200 status header.
				\status_header( 200 );
			}

			return $activitypub_template;
		}

		return $template;
	}

	/**
	 * Add the 'self' link to the header.
	 */
	public static function add_headers() {
		$id = Query::get_instance()->get_activitypub_object_id();

		if ( ! $id ) {
			return;
		}

		if ( ! headers_sent() ) {
			\header( 'Link: <' . esc_url( $id ) . '>; title="ActivityPub (JSON)"; rel="alternate"; type="application/activity+json"', false );

			if ( \get_option( 'activitypub_vary_header', '1' ) ) {
				// Send Vary header for Accept header.
				\header( 'Vary: Accept', false );
			}
		}

		add_action(
			'wp_head',
			function () use ( $id ) {
				echo PHP_EOL . '<link rel="alternate" title="ActivityPub (JSON)" type="application/activity+json" href="' . esc_url( $id ) . '" />' . PHP_EOL;
			}
		);
	}

	/**
	 * Remove trailing slash from ActivityPub @username requests.
	 *
	 * @param string $redirect_url  The URL to redirect to.
	 * @param string $requested_url The requested URL.
	 *
	 * @return string $redirect_url The possibly-unslashed redirect URL.
	 */
	public static function no_trailing_redirect( $redirect_url, $requested_url ) {
		if ( get_query_var( 'actor' ) ) {
			return $requested_url;
		}

		return $redirect_url;
	}

	/**
	 * Add support for `p` and `author` query vars.
	 *
	 * @param string $redirect_url  The URL to redirect to.
	 * @param string $requested_url The requested URL.
	 *
	 * @return string $redirect_url
	 */
	public static function redirect_canonical( $redirect_url, $requested_url ) {
		if ( ! is_activitypub_request() ) {
			return $redirect_url;
		}

		$query = \wp_parse_url( $requested_url, PHP_URL_QUERY );

		if ( ! $query ) {
			return $redirect_url;
		}

		$query_params = \wp_parse_args( $query );
		unset( $query_params['activitypub'] );

		if ( 1 !== count( $query_params ) ) {
			return $redirect_url;
		}

		if ( isset( $query_params['p'] ) ) {
			return null;
		}

		if ( isset( $query_params['author'] ) ) {
			return null;
		}

		return $requested_url;
	}

	/**
	 * Custom redirects for ActivityPub requests.
	 *
	 * @return void
	 */
	public static function template_redirect() {
		global $wp_query;

		$comment_id = get_query_var( 'c', null );

		// Check if it seems to be a comment.
		if ( $comment_id ) {
			$comment = get_comment( $comment_id );

			// Load a 404-page if `c` is set but not valid.
			if ( ! $comment ) {
				$wp_query->set_404();
				return;
			}

			// Stop if it's not an ActivityPub comment.
			if ( is_activitypub_request() && ! is_local_comment( $comment ) ) {
				return;
			}

			wp_safe_redirect( get_comment_link( $comment ) );
			exit;
		}

		$actor = get_query_var( 'actor', null );
		if ( $actor ) {
			$actor = Actors::get_by_username( $actor );
			if ( ! $actor || \is_wp_error( $actor ) ) {
				$wp_query->set_404();
				return;
			}

			if ( is_activitypub_request() ) {
				return;
			}

			\wp_safe_redirect( $actor->get_url(), 301 );
			exit;
		}
	}

	/**
	 * Add the 'activitypub' query variable so WordPress won't mangle it.
	 *
	 * @param array $vars The query variables.
	 *
	 * @return array The query variables.
	 */
	public static function add_query_vars( $vars ) {
		$vars[] = 'activitypub';
		$vars[] = 'preview';
		$vars[] = 'author';
		$vars[] = 'actor';
		$vars[] = 'type';
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

		/**
		 * Filter allowed comment types for avatars.
		 *
		 * @param array $allowed_comment_types Array of allowed comment types.
		 */
		$allowed_comment_types = \apply_filters( 'get_avatar_comment_types', array( 'comment' ) );
		if ( ! \in_array( $id_or_email->comment_type ?: 'comment', $allowed_comment_types, true ) ) { // phpcs:ignore Universal.Operators.DisallowShortTernary
			return $args;
		}

		// Check if comment has an avatar.
		$avatar = \get_comment_meta( $id_or_email->comment_ID, 'avatar_url', true );

		if ( $avatar ) {
			if ( empty( $args['class'] ) ) {
				$args['class'] = array();
			} elseif ( \is_string( $args['class'] ) ) {
				$args['class'] = \explode( ' ', $args['class'] );
			}

			/** This filter is documented in wp-includes/link-template.php */
			$args['url']     = \apply_filters( 'get_avatar_url', $avatar, $id_or_email, $args );
			$args['class'][] = 'avatar';
			$args['class'][] = 'avatar-activitypub';
			$args['class'][] = 'avatar-' . (int) $args['size'];
			$args['class'][] = 'photo';
			$args['class'][] = 'u-photo';
			$args['class']   = \array_unique( $args['class'] );
		}

		return $args;
	}

	/**
	 * Store permalink in meta, to send delete Activity.
	 *
	 * @param string $post_id The Post ID.
	 */
	public static function trash_post( $post_id ) {
		\add_post_meta(
			$post_id,
			'_activitypub_canonical_url',
			\get_permalink( $post_id ),
			true
		);
	}

	/**
	 * Delete permalink from meta.
	 *
	 * @param string $post_id The Post ID.
	 */
	public static function untrash_post( $post_id ) {
		\delete_post_meta( $post_id, '_activitypub_canonical_url' );
	}

	/**
	 * Add rewrite rules.
	 */
	public static function add_rewrite_rules() {
		/*
		 * If another system needs to take precedence over the ActivityPub rewrite rules,
		 * they can define their own and will manually call the appropriate functions as required.
		 */
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
				'index.php?rest_route=/' . ACTIVITYPUB_REST_NAMESPACE . '/nodeinfo',
				'top'
			);
		}

		\add_rewrite_rule( '^@([\w\-\.]+)\/?$', 'index.php?actor=$matches[1]', 'top' );
		\add_rewrite_endpoint( 'activitypub', EP_AUTHORS | EP_PERMALINK | EP_PAGES );
	}

	/**
	 * Flush rewrite rules.
	 */
	public static function flush_rewrite_rules() {
		self::add_rewrite_rules();
		\flush_rewrite_rules();
	}

	/**
	 * Theme compatibility stuff.
	 */
	public static function theme_compat() {
		// We assume that you want to use Post-Formats when enabling the setting.
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
	 * Add the 'activitypub' capability to users who can publish posts.
	 *
	 * @param int $user_id User ID.
	 */
	public static function user_register( $user_id ) {
		if ( \user_can( $user_id, 'publish_posts' ) ) {
			$user = \get_user_by( 'id', $user_id );
			$user->add_cap( 'activitypub' );
		}
	}

	/**
	 * Register user meta.
	 */
	public static function register_user_meta() {
		$blog_prefix = $GLOBALS['wpdb']->get_blog_prefix();

		\register_meta(
			'user',
			$blog_prefix . 'activitypub_also_known_as',
			array(
				'type'              => 'array',
				'description'       => 'An array of URLs that the user is known by.',
				'single'            => true,
				'default'           => array(),
				'sanitize_callback' => array( Sanitize::class, 'identifier_list' ),
			)
		);

		\register_meta(
			'user',
			$blog_prefix . 'activitypub_old_host_data',
			array(
				'description' => 'Actor object for the user on the old host.',
				'single'      => true,
			)
		);

		\register_meta(
			'user',
			$blog_prefix . 'activitypub_moved_to',
			array(
				'type'              => 'string',
				'description'       => 'The new URL of the user.',
				'single'            => true,
				'sanitize_callback' => 'sanitize_url',
			)
		);

		\register_meta(
			'user',
			$blog_prefix . 'activitypub_description',
			array(
				'type'              => 'string',
				'description'       => 'The user description.',
				'single'            => true,
				'default'           => '',
				'sanitize_callback' => function ( $value ) {
					return wp_kses( $value, 'user_description' );
				},
			)
		);

		\register_meta(
			'user',
			$blog_prefix . 'activitypub_icon',
			array(
				'type'              => 'integer',
				'description'       => 'The attachment ID for user profile image.',
				'single'            => true,
				'default'           => 0,
				'sanitize_callback' => 'absint',
			)
		);

		\register_meta(
			'user',
			$blog_prefix . 'activitypub_header_image',
			array(
				'type'              => 'integer',
				'description'       => 'The attachment ID for the user header image.',
				'single'            => true,
				'default'           => 0,
				'sanitize_callback' => 'absint',
			)
		);

		\register_meta(
			'user',
			$blog_prefix . 'activitypub_mailer_new_dm',
			array(
				'type'              => 'integer',
				'description'       => 'Send a notification when someone sends this user a direct message.',
				'single'            => true,
				'sanitize_callback' => 'absint',
			)
		);
		\add_filter( 'get_user_option_activitypub_mailer_new_dm', array( self::class, 'user_options_default' ) );

		\register_meta(
			'user',
			$blog_prefix . 'activitypub_mailer_new_follower',
			array(
				'type'              => 'integer',
				'description'       => 'Send a notification when someone starts to follow this user.',
				'single'            => true,
				'sanitize_callback' => 'absint',
			)
		);
		\add_filter( 'get_user_option_activitypub_mailer_new_follower', array( self::class, 'user_options_default' ) );

		\register_meta(
			'user',
			$blog_prefix . 'activitypub_mailer_new_mention',
			array(
				'type'              => 'integer',
				'description'       => 'Send a notification when someone mentions this user.',
				'single'            => true,
				'sanitize_callback' => 'absint',
			)
		);
		\add_filter( 'get_user_option_activitypub_mailer_new_mention', array( self::class, 'user_options_default' ) );

		\register_meta(
			'user',
			'activitypub_show_welcome_tab',
			array(
				'type'              => 'integer',
				'description'       => 'Whether to show the welcome tab.',
				'single'            => true,
				'default'           => 1,
				'sanitize_callback' => 'absint',
			)
		);

		\register_meta(
			'user',
			'activitypub_show_advanced_tab',
			array(
				'type'              => 'integer',
				'description'       => 'Whether to show the advanced tab.',
				'single'            => true,
				'default'           => 0,
				'sanitize_callback' => 'absint',
			)
		);

		// Moderation user meta.
		\register_meta(
			'user',
			'activitypub_blocked_actors',
			array(
				'type'              => 'array',
				'description'       => 'User-specific blocked ActivityPub actors.',
				'single'            => true,
				'default'           => array(),
				'sanitize_callback' => array( Sanitize::class, 'identifier_list' ),
			)
		);

		\register_meta(
			'user',
			'activitypub_blocked_domains',
			array(
				'type'              => 'array',
				'description'       => 'User-specific blocked ActivityPub domains.',
				'single'            => true,
				'default'           => array(),
				'sanitize_callback' => function ( $value ) {
					return \array_unique( \array_map( array( Sanitize::class, 'host_list' ), $value ) );
				},
			)
		);

		\register_meta(
			'user',
			'activitypub_blocked_keywords',
			array(
				'type'              => 'array',
				'description'       => 'User-specific blocked ActivityPub keywords.',
				'single'            => true,
				'default'           => array(),
				'sanitize_callback' => function ( $value ) {
					return \array_map( 'sanitize_text_field', $value );
				},
			)
		);
	}

	/**
	 * Set default values for user options.
	 *
	 * @param bool|string $value  Option value.
	 * @return bool|string
	 */
	public static function user_options_default( $value ) {
		if ( false === $value ) {
			return '1';
		}

		return $value;
	}
}
