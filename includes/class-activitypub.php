<?php
/**
 * ActivityPub Class.
 *
 * @package Activitypub
 */

namespace Activitypub;

use Activitypub\Collection\Followers;
use Activitypub\Collection\Following;

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
		\add_action( 'init', array( self::class, 'theme_compat' ), 11 );
		\add_action( 'init', array( self::class, 'register_user_meta' ), 11 );

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
	 * Flush rewrite rules.
	 */
	public static function flush_rewrite_rules() {
		Router::add_rewrite_rules();
		\flush_rewrite_rules();
	}

	/**
	 * Add rewrite rules.
	 *
	 * @deprecated 7.5.0 Use {@see Router::add_rewrite_rules()}.
	 */
	public static function add_rewrite_rules() {
		_deprecated_function( __FUNCTION__, '7.5.0', '\Activitypub\Router::add_rewrite_rules()' );

		Router::add_rewrite_rules();
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
