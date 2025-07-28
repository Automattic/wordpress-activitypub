<?php
/**
 * Moderation class file.
 *
 * @package Activitypub
 */

namespace Activitypub;

/**
 * ActivityPub Moderation class.
 *
 * Handles user-specific blocking and site-wide moderation.
 */
class Moderation {

	/**
	 * User meta key for blocked actors.
	 */
	const USER_BLOCKED_ACTORS_META = 'activitypub_blocked_actors';

	/**
	 * User meta key for blocked domains.
	 */
	const USER_BLOCKED_DOMAINS_META = 'activitypub_blocked_domains';

	/**
	 * User meta key for blocked keywords.
	 */
	const USER_BLOCKED_KEYWORDS_META = 'activitypub_blocked_keywords';

	/**
	 * Option key for site-wide blocked actors.
	 */
	const SITE_BLOCKED_ACTORS_OPTION = 'activitypub_site_blocked_actors';

	/**
	 * Option key for site-wide blocked domains.
	 */
	const SITE_BLOCKED_DOMAINS_OPTION = 'activitypub_site_blocked_domains';

	/**
	 * Option key for site-wide blocked keywords.
	 */
	const SITE_BLOCKED_KEYWORDS_OPTION = 'activitypub_site_blocked_keywords';

	/**
	 * Option key for site-wide moderation settings.
	 */
	const SITE_MODERATION_SETTINGS_OPTION = 'activitypub_moderation_settings';

	/**
	 * Initialize the moderation system.
	 */
	public static function init() {
		\add_action( 'init', array( __CLASS__, 'register_hooks' ) );
	}

	/**
	 * Register hooks.
	 */
	public static function register_hooks() {
		// Hook can be added here for future extensions
	}

	/**
	 * Check if an activity should be blocked for a specific user.
	 *
	 * @param array    $activity_data The activity data.
	 * @param int|null $user_id       The user ID to check blocks for.
	 * @return bool True if blocked, false otherwise.
	 */
	public static function is_activity_blocked( $activity_data, $user_id = null ) {
		// First check site-wide blocks (admin moderation)
		if ( self::is_activity_blocked_site_wide( $activity_data ) ) {
			return true;
		}

		// Then check user-specific blocks
		if ( $user_id && self::is_activity_blocked_for_user( $activity_data, $user_id ) ) {
			return true;
		}

		// Fall back to WordPress comment disallowed list
		$activity_json = is_array( $activity_data ) ? \wp_json_encode( $activity_data ) : $activity_data;
		return \wp_check_comment_disallowed_list( $activity_json, '', '', '', $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '' );
	}

	/**
	 * Check if an activity is blocked site-wide.
	 *
	 * @param array $activity_data The activity data.
	 * @return bool True if blocked, false otherwise.
	 */
	public static function is_activity_blocked_site_wide( $activity_data ) {
		$blocked_actors   = \get_option( self::SITE_BLOCKED_ACTORS_OPTION, array() );
		$blocked_domains  = \get_option( self::SITE_BLOCKED_DOMAINS_OPTION, array() );
		$blocked_keywords = \get_option( self::SITE_BLOCKED_KEYWORDS_OPTION, array() );

		// Extract actor information.
		$actor_id = '';
		if ( isset( $activity_data['actor'] ) ) {
			$actor_id = is_string( $activity_data['actor'] ) ? $activity_data['actor'] : ( $activity_data['actor']['id'] ?? '' );
		}

		// Check blocked actors.
		if ( $actor_id && in_array( $actor_id, $blocked_actors, true ) ) {
			return true;
		}

		// Check blocked domains.
		if ( $actor_id ) {
			$domain = \wp_parse_url( $actor_id, PHP_URL_HOST );
			if ( $domain && in_array( $domain, $blocked_domains, true ) ) {
				return true;
			}
		}

		// Check blocked keywords in activity content.
		$activity_json = \wp_json_encode( $activity_data );
		foreach ( $blocked_keywords as $keyword ) {
			if ( stripos( $activity_json, $keyword ) !== false ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if an activity is blocked for a specific user.
	 *
	 * @param array $activity_data The activity data.
	 * @param int   $user_id       The user ID.
	 * @return bool True if blocked, false otherwise.
	 */
	public static function is_activity_blocked_for_user( $activity_data, $user_id ) {
		$blocked_actors   = \get_user_meta( $user_id, self::USER_BLOCKED_ACTORS_META, true ) ?: array();
		$blocked_domains  = \get_user_meta( $user_id, self::USER_BLOCKED_DOMAINS_META, true ) ?: array();
		$blocked_keywords = \get_user_meta( $user_id, self::USER_BLOCKED_KEYWORDS_META, true ) ?: array();

		// Extract actor information.
		$actor_id = '';
		if ( isset( $activity_data['actor'] ) ) {
			$actor_id = is_string( $activity_data['actor'] ) ? $activity_data['actor'] : ( $activity_data['actor']['id'] ?? '' );
		}

		// Check blocked actors.
		if ( $actor_id && in_array( $actor_id, $blocked_actors, true ) ) {
			return true;
		}

		// Check blocked domains.
		if ( $actor_id ) {
			$domain = \wp_parse_url( $actor_id, PHP_URL_HOST );
			if ( $domain && in_array( $domain, $blocked_domains, true ) ) {
				return true;
			}
		}

		// Check blocked keywords in activity content.
		$activity_json = \wp_json_encode( $activity_data );
		foreach ( $blocked_keywords as $keyword ) {
			if ( stripos( $activity_json, $keyword ) !== false ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Add a block for a user.
	 *
	 * @param int    $user_id The user ID.
	 * @param string $type    The block type (actor, domain, keyword).
	 * @param string $value   The value to block.
	 * @return bool True on success, false on failure.
	 */
	public static function add_user_block( $user_id, $type, $value ) {
		$meta_key = self::get_user_meta_key_for_type( $type );
		if ( ! $meta_key ) {
			return false;
		}

		$blocks = \get_user_meta( $user_id, $meta_key, true ) ?: array();

		if ( ! in_array( $value, $blocks, true ) ) {
			$blocks[] = $value;
			return \update_user_meta( $user_id, $meta_key, $blocks );
		}

		return true; // Already blocked.
	}

	/**
	 * Remove a block for a user.
	 *
	 * @param int    $user_id The user ID.
	 * @param string $type    The block type (actor, domain, keyword).
	 * @param string $value   The value to unblock.
	 * @return bool True on success, false on failure.
	 */
	public static function remove_user_block( $user_id, $type, $value ) {
		$meta_key = self::get_user_meta_key_for_type( $type );
		if ( ! $meta_key ) {
			return false;
		}

		$blocks = \get_user_meta( $user_id, $meta_key, true ) ?: array();
		$key    = array_search( $value, $blocks, true );

		if ( $key !== false ) {
			unset( $blocks[ $key ] );
			$blocks = array_values( $blocks ); // Re-index array.
			return \update_user_meta( $user_id, $meta_key, $blocks );
		}

		return true; // Not blocked anyway.
	}

	/**
	 * Get all blocks for a user.
	 *
	 * @param int $user_id The user ID.
	 * @return array Array of blocks organized by type.
	 */
	public static function get_user_blocks( $user_id ) {
		return array(
			'actors'   => \get_user_meta( $user_id, self::USER_BLOCKED_ACTORS_META, true ) ?: array(),
			'domains'  => \get_user_meta( $user_id, self::USER_BLOCKED_DOMAINS_META, true ) ?: array(),
			'keywords' => \get_user_meta( $user_id, self::USER_BLOCKED_KEYWORDS_META, true ) ?: array(),
		);
	}

	/**
	 * Add a site-wide block.
	 *
	 * @param string $type  The block type (actor, domain, keyword).
	 * @param string $value The value to block.
	 * @return bool True on success, false on failure.
	 */
	public static function add_site_block( $type, $value ) {
		$option_key = self::get_site_option_key_for_type( $type );
		if ( ! $option_key ) {
			return false;
		}

		$blocks = \get_option( $option_key, array() );

		if ( ! in_array( $value, $blocks, true ) ) {
			$blocks[] = $value;
			return \update_option( $option_key, $blocks );
		}

		return true; // Already blocked.
	}

	/**
	 * Remove a site-wide block.
	 *
	 * @param string $type  The block type (actor, domain, keyword).
	 * @param string $value The value to unblock.
	 * @return bool True on success, false on failure.
	 */
	public static function remove_site_block( $type, $value ) {
		$option_key = self::get_site_option_key_for_type( $type );
		if ( ! $option_key ) {
			return false;
		}

		$blocks = \get_option( $option_key, array() );
		$key    = array_search( $value, $blocks, true );

		if ( $key !== false ) {
			unset( $blocks[ $key ] );
			$blocks = array_values( $blocks ); // Re-index array.
			return \update_option( $option_key, $blocks );
		}

		return true; // Not blocked anyway.
	}

	/**
	 * Get all site-wide blocks.
	 *
	 * @return array Array of blocks organized by type.
	 */
	public static function get_site_blocks() {
		return array(
			'actors'   => \get_option( self::SITE_BLOCKED_ACTORS_OPTION, array() ),
			'domains'  => \get_option( self::SITE_BLOCKED_DOMAINS_OPTION, array() ),
			'keywords' => \get_option( self::SITE_BLOCKED_KEYWORDS_OPTION, array() ),
		);
	}

	/**
	 * Get user meta key for block type.
	 *
	 * @param string $type The block type.
	 * @return string|false The meta key or false if invalid type.
	 */
	private static function get_user_meta_key_for_type( $type ) {
		switch ( $type ) {
			case 'actor':
				return self::USER_BLOCKED_ACTORS_META;
			case 'domain':
				return self::USER_BLOCKED_DOMAINS_META;
			case 'keyword':
				return self::USER_BLOCKED_KEYWORDS_META;
			default:
				return false;
		}
	}

	/**
	 * Get site option key for block type.
	 *
	 * @param string $type The block type.
	 * @return string|false The option key or false if invalid type.
	 */
	private static function get_site_option_key_for_type( $type ) {
		switch ( $type ) {
			case 'actor':
				return self::SITE_BLOCKED_ACTORS_OPTION;
			case 'domain':
				return self::SITE_BLOCKED_DOMAINS_OPTION;
			case 'keyword':
				return self::SITE_BLOCKED_KEYWORDS_OPTION;
			default:
				return false;
		}
	}
}