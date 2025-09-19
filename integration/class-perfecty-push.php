<?php
/**
 * Perfecty Push integration file.
 *
 * @package Activitypub
 */

namespace Activitypub\Integration;

use Activitypub\Collection\Remote_Actors;

use function Activitypub\object_to_uri;

/**
 * Perfecty Push integration.
 *
 * This class handles the compatibility with the Perfecty Push plugin
 * for sending web push notifications on ActivityPub events.
 *
 * @see https://wordpress.org/plugins/perfecty-push-notifications/
 */
class Perfecty_Push {

	/**
	 * Initialize the Perfecty Push integration.
	 */
	public static function init() {
		// Hook into ActivityPub handler events.
		\add_action( 'activitypub_handled_like', array( self::class, 'handle_like' ), 10, 4 );
		\add_action( 'activitypub_handled_announce', array( self::class, 'handle_announce' ), 10, 4 );
		\add_action( 'activitypub_handled_create', array( self::class, 'handle_create' ), 10, 4 );
		\add_action( 'activitypub_followers_post_follow', array( self::class, 'handle_follow' ), 10, 4 );

		// Register settings.
		\add_action( 'load-profile.php', array( self::class, 'register_user_settings' ) );
		\add_action( 'load-settings_page_activitypub', array( self::class, 'register_blog_settings' ) );
		\add_action( 'admin_init', array( self::class, 'register_setting_field' ) );
	}

	/**
	 * Handle like notifications.
	 *
	 * @param object      $activity The activity object.
	 * @param int         $user_id  The user ID.
	 * @param string      $state    The state.
	 * @param \WP_Comment $comment  The comment object.
	 */
	public static function handle_like( $activity, $user_id, $state, $comment ) {
		if ( ! self::is_notification_enabled( 'like', $user_id ) ) {
			return;
		}

		$actor = Remote_Actors::fetch_by_uri( $activity['actor'] );
		if ( is_wp_error( $actor ) ) {
			return;
		}

		$actor = Remote_Actors::get_actor( $actor );

		$actor_name  = $actor->get_name() ?? $actor->get_preferred_username() ?? \__( 'Someone', 'activitypub' );
		$actor_image = object_to_uri( $actor->get_icon() ?? ACTIVITYPUB_PLUGIN_URL . 'assets/img/mp.jpg' );

		$title   = \__( 'New like on your post', 'activitypub' );
		$message = sprintf(
			/* translators: 1: Actor name; 2: Post title */
			\__( '%1$s liked %2$s', 'activitypub' ),
			$actor_name,
			get_the_title( $comment->comment_post_ID )
		);

		self::send_notification( $user_id, $message, $title, $actor_image );
	}

	/**
	 * Handle announce (repost/boost) notifications.
	 *
	 * @param object      $activity The activity object.
	 * @param int         $user_id  The user ID.
	 * @param string      $state    The state.
	 * @param \WP_Comment $comment  The comment object.
	 */
	public static function handle_announce( $activity, $user_id, $state, $comment ) {
		if ( ! self::is_notification_enabled( 'announce', $user_id ) ) {
			return;
		}

		$actor = Remote_Actors::fetch_by_uri( $activity['actor'] );
		if ( is_wp_error( $actor ) ) {
			return;
		}

		$actor = Remote_Actors::get_actor( $actor );

		$actor_name  = $actor->get_name() ?? $actor->get_preferred_username() ?? \__( 'Someone', 'activitypub' );
		$actor_image = object_to_uri( $actor->get_icon() ?? ACTIVITYPUB_PLUGIN_URL . 'assets/img/mp.jpg' );

		$title   = \__( 'Your post was shared', 'activitypub' );
		$message = sprintf(
		/* translators: 1: Actor name; 2: Post title */
			\__( '%1$s shared %2$s', 'activitypub' ),
			$actor_name,
			get_the_title( $comment->comment_post_ID )
		);

		self::send_notification( $user_id, $message, $title, $actor_image );
	}

	/**
	 * Handle create (comment) notifications.
	 *
	 * @param object      $activity The activity object.
	 * @param int         $user_id  The user ID.
	 * @param string      $state    The state.
	 * @param \WP_Comment $comment  The comment object.
	 */
	public static function handle_create( $activity, $user_id, $state, $comment ) {
		if ( ! self::is_notification_enabled( 'create', $user_id ) ) {
			return;
		}

		$actor = Remote_Actors::fetch_by_uri( $activity['actor'] );
		if ( is_wp_error( $actor ) ) {
			return;
		}

		$actor = Remote_Actors::get_actor( $actor );

		$actor_name  = $actor->get_name() ?? $actor->get_preferred_username() ?? \__( 'Someone', 'activitypub' );
		$actor_image = object_to_uri( $actor->get_icon() ?? ACTIVITYPUB_PLUGIN_URL . 'assets/img/mp.jpg' );

		/* translators: %s: Actor name */
		$title   = sprintf( \__( '%s commented on your post', 'activitypub' ), $actor_name );
		$message = \html_entity_decode( \get_comment_excerpt( $comment ), ENT_QUOTES, 'UTF-8' );

		self::send_notification( $user_id, $message, $title, $actor_image, get_comment_link( $comment ) );
	}

	/**
	 * Handle follow notifications.
	 *
	 * @param string   $actor_url    The actor URL.
	 * @param array    $activity     The activity object.
	 * @param int      $user_id      The user ID.
	 * @param \WP_Post $remote_actor The remote actor object.
	 */
	public static function handle_follow( $actor_url, $activity, $user_id, $remote_actor ) {
		if ( ! self::is_notification_enabled( 'follow', $user_id ) ) {
			return;
		}

		$remote_actor = Remote_Actors::get_actor( $remote_actor );
		$actor_name   = $remote_actor->get_name() ?? $remote_actor->get_preferred_username();
		$actor_image  = object_to_uri( $remote_actor->get_icon() ?? ACTIVITYPUB_PLUGIN_URL . 'assets/img/mp.jpg' );

		$title = \__( 'New follower', 'activitypub' );
		/* translators: %s: Actor name */
		$message = sprintf( \__( '%s is now following you', 'activitypub' ), $actor_name );

		self::send_notification( $user_id, $message, $title, $actor_image );
	}

	/**
	 * Send a push notification to a user.
	 *
	 * @param int    $user_id The WordPress user ID.
	 * @param string $message The notification message.
	 * @param string $title   The notification title.
	 * @param string $image_url Optional image URL.
	 * @param string $url_to_open Optional URL to open when clicked.
	 */
	private static function send_notification( $user_id, $message, $title = '', $image_url = '', $url_to_open = '' ) {
		try {
			// Check if Perfecty Push Integration class exists and is properly loaded.
			if ( ! class_exists( 'Perfecty_Push_Integration' ) ) {
				// Attempt to load the integration file manually.
				$integration_file = WP_PLUGIN_DIR . '/' . dirname( PERFECTY_PUSH_BASENAME ) . '/integration/class-perfecty-push-integration.php';
				if ( file_exists( $integration_file ) ) {
					include_once $integration_file;
				}
			}

			if ( class_exists( 'Perfecty_Push_Integration' ) ) {
				( new \Perfecty_Push_Integration() )->notify( $user_id, $message, $title, $image_url, $url_to_open );
			}
		} catch ( \Exception $e ) {
			error_log( 'ActivityPub Perfecty Push notification failed: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}

	/**
	 * Check if a specific notification type is enabled for a user.
	 *
	 * @param string $type The notification type (like, announce, create, follow).
	 * @param int    $user_id The user ID to check settings for.
	 * @return bool True if enabled, false otherwise.
	 */
	private static function is_notification_enabled( $type, $user_id ) {
		$default_enabled = array( 'like', 'announce', 'create', 'follow' );

		// Check user-specific settings first.
		$user_settings = \get_user_meta( $user_id, 'activitypub_perfecty_push_notifications', true );
		if ( ! empty( $user_settings ) && is_array( $user_settings ) ) {
			return in_array( $type, $user_settings, true );
		}

		// Fall back to blog settings for blog actor (user_id 0).
		if ( 0 === $user_id ) {
			$blog_settings = \get_option( 'activitypub_perfecty_push_notifications', $default_enabled );
			return in_array( $type, $blog_settings, true );
		}

		// Default to enabled for all types.
		return in_array( $type, $default_enabled, true );
	}

	/**
	 * Register user settings.
	 */
	public static function register_user_settings() {
		\add_settings_field(
			'activitypub_perfecty_push_notifications',
			\esc_html__( 'Push Notifications', 'activitypub' ),
			array( self::class, 'render_user_notification_field' ),
			'activitypub_user_settings',
			'activitypub_user_profile'
		);
	}

	/**
	 * Register blog settings.
	 */
	public static function register_blog_settings() {
		// Check if we're on the blog profile tab.
		if ( isset( $_GET['tab'] ) && 'blog-profile' === \sanitize_key( $_GET['tab'] ) ) {
			\add_settings_field(
				'activitypub_perfecty_push_notifications',
				\esc_html__( 'Push Notifications', 'activitypub' ),
				array( self::class, 'render_blog_notification_field' ),
				'activitypub_blog_settings',
				'activitypub_blog_profile'
			);
		}
	}

	/**
	 * Register the setting field.
	 */
	public static function register_setting_field() {
		\register_setting(
			'activitypub_settings',
			'activitypub_perfecty_push_notifications',
			array(
				'type'              => 'array',
				'description'       => \__( 'ActivityPub Perfecty Push notification types', 'activitypub' ),
				'sanitize_callback' => array( self::class, 'sanitize_notification_types' ),
				'default'           => array( 'like', 'announce', 'create', 'follow' ),
			)
		);
	}

	/**
	 * Sanitize notification types setting.
	 *
	 * @param array $input The input value.
	 * @return array The sanitized value.
	 */
	public static function sanitize_notification_types( $input ) {
		$valid_types = array( 'like', 'announce', 'create', 'follow' );

		if ( ! is_array( $input ) ) {
			return array();
		}

		return array_intersect( $input, $valid_types );
	}

	/**
	 * Render the user notification settings field.
	 */
	public static function render_user_notification_field() {
		$user_id       = \get_current_user_id();
		$enabled_types = \get_user_meta( $user_id, 'activitypub_perfecty_push_notifications', true );

		if ( ! is_array( $enabled_types ) ) {
			$enabled_types = array( 'like', 'announce', 'create', 'follow' );
		}

		$notification_types = array(
			'like'     => \__( 'Likes', 'activitypub' ),
			'announce' => \__( 'Reposts/Boosts', 'activitypub' ),
			'create'   => \__( 'Comments', 'activitypub' ),
			'follow'   => \__( 'New Followers', 'activitypub' ),
		);

		echo '<fieldset>';
		foreach ( $notification_types as $type => $label ) {
			printf(
				'<label><input type="checkbox" name="activitypub_perfecty_push_notifications[]" value="%s" %s /> %s</label><br />',
				\esc_attr( $type ),
				\checked( in_array( $type, $enabled_types, true ), true, false ),
				\esc_html( $label )
			);
		}
		echo '</fieldset>';
		echo '<p class="description">' . \esc_html__( 'Select which ActivityPub events should trigger push notifications for your account.', 'activitypub' ) . '</p>';
	}

	/**
	 * Render the blog notification settings field.
	 */
	public static function render_blog_notification_field() {
		$enabled_types      = \get_option( 'activitypub_perfecty_push_notifications', array( 'like', 'announce', 'create', 'follow' ) );
		$notification_types = array(
			'like'     => \__( 'Likes', 'activitypub' ),
			'announce' => \__( 'Reposts/Boosts', 'activitypub' ),
			'create'   => \__( 'Comments', 'activitypub' ),
			'follow'   => \__( 'New Followers', 'activitypub' ),
		);

		echo '<fieldset>';
		foreach ( $notification_types as $type => $label ) {
			printf(
				'<label><input type="checkbox" name="activitypub_perfecty_push_notifications[]" value="%s" %s /> %s</label><br />',
				\esc_attr( $type ),
				\checked( in_array( $type, $enabled_types, true ), true, false ),
				\esc_html( $label )
			);
		}
		echo '</fieldset>';
		echo '<p class="description">' . \esc_html__( 'Select which ActivityPub events should trigger push notifications for the blog actor.', 'activitypub' ) . '</p>';
	}
}
