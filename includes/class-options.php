<?php
/**
 * Options file.
 *
 * @package Activitypub
 */

namespace Activitypub;

/**
 * Options class.
 */
class Options {

	/**
	 * Initialize the options.
	 */
	public static function init() {
		\add_filter( 'pre_option_activitypub_actor_mode', array( self::class, 'pre_option_activitypub_actor_mode' ) );
		\add_filter( 'pre_option_activitypub_authorized_fetch', array( self::class, 'pre_option_activitypub_authorized_fetch' ) );
		\add_filter( 'pre_option_activitypub_shared_inbox', array( self::class, 'pre_option_activitypub_shared_inbox' ) );
		\add_filter( 'pre_option_activitypub_vary_header', array( self::class, 'pre_option_activitypub_vary_header' ) );

		\add_filter( 'pre_option_activitypub_allow_likes', array( self::class, 'maybe_disable_interactions' ) );
		\add_filter( 'pre_option_activitypub_allow_replies', array( self::class, 'maybe_disable_interactions' ) );

		\add_filter( 'default_option_activitypub_negotiate_content', array( self::class, 'default_option_activitypub_negotiate_content' ) );
		\add_filter( 'option_activitypub_max_image_attachments', array( self::class, 'default_max_image_attachments' ) );
		\add_filter( 'option_activitypub_object_type', array( self::class, 'default_object_type' ) );
	}

	/**
	 * Delete all options.
	 */
	public static function delete() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE 'activitypub_%'" );
	}

	/**
	 * Pre-get option filter for the Actor-Mode.
	 *
	 * @param string|false $pre The pre-get option value.
	 *
	 * @return string|false The actor mode or false if it should not be filtered.
	 */
	public static function pre_option_activitypub_actor_mode( $pre ) {
		if ( \defined( 'ACTIVITYPUB_SINGLE_USER_MODE' ) && ACTIVITYPUB_SINGLE_USER_MODE ) {
			return ACTIVITYPUB_BLOG_MODE;
		}

		if ( \defined( 'ACTIVITYPUB_DISABLE_USER' ) && ACTIVITYPUB_DISABLE_USER ) {
			return ACTIVITYPUB_BLOG_MODE;
		}

		if ( \defined( 'ACTIVITYPUB_DISABLE_BLOG_USER' ) && ACTIVITYPUB_DISABLE_BLOG_USER ) {
			return ACTIVITYPUB_ACTOR_MODE;
		}

		return $pre;
	}

	/**
	 * Pre-get option filter for the Authorized Fetch.
	 *
	 * @param string $pre The pre-get option value.
	 *
	 * @return string If the constant is defined, return the value, otherwise return the pre-get option value.
	 */
	public static function pre_option_activitypub_authorized_fetch( $pre ) {
		if ( ! \defined( 'ACTIVITYPUB_AUTHORIZED_FETCH' ) ) {
			return $pre;
		}

		if ( ACTIVITYPUB_AUTHORIZED_FETCH ) {
			return '1';
		}

		return '0';
	}

	/**
	 * Pre-get option filter for the Shared Inbox.
	 *
	 * @param string $pre The pre-get option value.
	 *
	 * @return string If the constant is defined, return the value, otherwise return the pre-get option value.
	 */
	public static function pre_option_activitypub_shared_inbox( $pre ) {
		if ( ! \defined( 'ACTIVITYPUB_SHARED_INBOX_FEATURE' ) ) {
			return $pre;
		}

		if ( ACTIVITYPUB_SHARED_INBOX_FEATURE ) {
			return '1';
		}

		return '0';
	}

	/**
	 * Pre-get option filter for the Vary Header.
	 *
	 * @param string $pre The pre-get option value.
	 *
	 * @return string If the constant is defined, return the value, otherwise return the pre-get option value.
	 */
	public static function pre_option_activitypub_vary_header( $pre ) {
		if ( ! \defined( 'ACTIVITYPUB_SEND_VARY_HEADER' ) ) {
			return $pre;
		}

		if ( ACTIVITYPUB_SEND_VARY_HEADER ) {
			return '1';
		}

		return '0';
	}

	/**
	 * Disallow interactions if the constant is set.
	 *
	 * @param bool $pre The value of the option.
	 *
	 * @return bool|string The value of the option.
	 */
	public static function maybe_disable_interactions( $pre ) {
		if ( ACTIVITYPUB_DISABLE_INCOMING_INTERACTIONS ) {
			return '0';
		}

		return $pre;
	}

	/**
	 * Default option filter for the Content-Negotiation.
	 *
	 * @see https://github.com/Automattic/wordpress-activitypub/wiki/Caching
	 *
	 * @param string $default_value The default value of the option.
	 *
	 * @return string The default value of the option.
	 */
	public static function default_option_activitypub_negotiate_content( $default_value ) {
		$disable_for_plugins = array(
			'wp-optimize/wp-optimize.php',
			'wp-rocket/wp-rocket.php',
			'w3-total-cache/w3-total-cache.php',
			'wp-fastest-cache/wp-fastest-cache.php',
			'sg-cachepress/sg-cachepress.php',
		);

		foreach ( $disable_for_plugins as $plugin ) {
			if ( \is_plugin_active( $plugin ) ) {
				return '0';
			}
		}

		return $default_value;
	}

	/**
	 * Default max image attachments.
	 *
	 * @param string $value The value of the option.
	 *
	 * @return string|int The value of the option.
	 */
	public static function default_max_image_attachments( $value ) {
		if ( ! \is_numeric( $value ) ) {
			$value = ACTIVITYPUB_MAX_IMAGE_ATTACHMENTS;
		}

		return $value;
	}

	/**
	 * Default object type.
	 *
	 * @param string $value The value of the option.
	 *
	 * @return string The value of the option.
	 */
	public static function default_object_type( $value ) {
		if ( ! $value ) {
			$value = ACTIVITYPUB_DEFAULT_OBJECT_TYPE;
		}

		return $value;
	}
}
