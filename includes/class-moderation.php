<?php
/**
 * Moderation class file.
 *
 * @package Activitypub
 */

namespace Activitypub;

use Activitypub\Activity\Activity;

/**
 * ActivityPub Moderation class.
 *
 * Handles user-specific blocking and site-wide moderation.
 */
class Moderation {
	/**
	 * User meta key for blocked keywords.
	 */
	const USER_META_KEYS = array(
		'domain'  => 'activitypub_blocked_domains',
		'keyword' => 'activitypub_blocked_keywords',
	);

	/**
	 * Option key for site-wide blocked keywords.
	 */
	const OPTION_KEYS = array(
		'domain'  => 'activitypub_site_blocked_domains',
		'keyword' => 'activitypub_site_blocked_keywords',
	);

	/**
	 * Check if an activity should be blocked for a specific user.
	 *
	 * @param array    $activity_data The activity data.
	 * @param int|null $user_id       The user ID to check blocks for.
	 * @return bool True if blocked, false otherwise.
	 */
	public static function activity_is_blocked( $activity_data, $user_id = null ) {
		// First check site-wide blocks (admin moderation).
		if ( self::activity_is_blocked_site_wide( $activity_data ) ) {
			return true;
		}

		// Then check user-specific blocks.
		if ( $user_id && self::activity_is_blocked_for_user( $activity_data, $user_id ) ) {
			return true;
		}

		// Convert to Activity object and get JSON like the original implementation.
		if ( is_array( $activity_data ) ) {
			$activity_data = Activity::init_from_array( $activity_data )->to_json( false );
		}

		$remote_addr = \sanitize_text_field( \wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );
		$user_agent  = \sanitize_text_field( \wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' ) );

		// Fall back to WordPress comment disallowed list.
		return \wp_check_comment_disallowed_list( $activity_data, '', '', '', $remote_addr, $user_agent );
	}

	/**
	 * Check if an activity is blocked site-wide.
	 *
	 * @param array $activity_data The activity data.
	 * @return bool True if blocked, false otherwise.
	 */
	public static function activity_is_blocked_site_wide( $activity_data ) {
		$blocks = self::get_site_blocks();

		return self::check_activity_against_blocks( $activity_data, $blocks['actors'], $blocks['domains'], $blocks['keywords'] );
	}

	/**
	 * Check if an activity is blocked for a specific user.
	 *
	 * @param array $activity_data The activity data.
	 * @param int   $user_id       The user ID.
	 * @return bool True if blocked, false otherwise.
	 */
	public static function activity_is_blocked_for_user( $activity_data, $user_id ) {
		$blocks = self::get_user_blocks( $user_id );

		return self::check_activity_against_blocks( $activity_data, $blocks['actors'], $blocks['domains'], $blocks['keywords'] );
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
		switch ( $type ) {
			case 'domain':
			case 'keyword':
				$blocks = \get_user_meta( $user_id, self::USER_META_KEYS[ $type ], true ) ?: array(); // phpcs:ignore Universal.Operators.DisallowShortTernary.Found

				if ( ! in_array( $value, $blocks, true ) ) {
					$blocks[] = $value;
					return (bool) \update_user_meta( $user_id, self::USER_META_KEYS[ $type ], $blocks );
				}
				break;
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
		switch ( $type ) {
			case 'domain':
			case 'keyword':
				$blocks = \get_user_meta( $user_id, self::USER_META_KEYS[ $type ], true ) ?: array(); // phpcs:ignore Universal.Operators.DisallowShortTernary.Found
				$key    = array_search( $value, $blocks, true );

				if ( false !== $key ) {
					unset( $blocks[ $key ] );
					return \update_user_meta( $user_id, self::USER_META_KEYS[ $type ], array_values( $blocks ) );
				}
				break;
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
			'actors'   => array(),
			'domains'  => \get_user_meta( $user_id, self::USER_META_KEYS['domain'], true ) ?: array(), // phpcs:ignore Universal.Operators.DisallowShortTernary.Found
			'keywords' => \get_user_meta( $user_id, self::USER_META_KEYS['keyword'], true ) ?: array(), // phpcs:ignore Universal.Operators.DisallowShortTernary.Found
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
		switch ( $type ) {
			case 'domain':
			case 'keyword':
				$blocks = \get_option( self::OPTION_KEYS[ $type ], array() );

				if ( ! in_array( $value, $blocks, true ) ) {
					$blocks[] = $value;
					return \update_option( self::OPTION_KEYS[ $type ], $blocks );
				}
				break;
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
		switch ( $type ) {
			case 'domain':
			case 'keyword':
				$blocks = \get_option( self::OPTION_KEYS[ $type ], array() );
				$key    = array_search( $value, $blocks, true );

				if ( false !== $key ) {
					unset( $blocks[ $key ] );
					return \update_option( self::OPTION_KEYS[ $type ], array_values( $blocks ) );
				}
				break;
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
			'actors'   => array(),
			'domains'  => \get_option( self::OPTION_KEYS['domain'], array() ),
			'keywords' => \get_option( self::OPTION_KEYS['keyword'], array() ),
		);
	}

	/**
	 * Check activity against blocklists.
	 *
	 * @param array $activity_data    The activity data.
	 * @param array $blocked_actors   List of blocked actors.
	 * @param array $blocked_domains  List of blocked domains.
	 * @param array $blocked_keywords List of blocked keywords.
	 * @return bool True if blocked, false otherwise.
	 */
	private static function check_activity_against_blocks( $activity_data, $blocked_actors, $blocked_domains, $blocked_keywords ) {
		// Extract actor information.
		$actor_id = '';
		if ( isset( $activity_data['actor'] ) ) {
			$actor_id = object_to_uri( $activity_data['actor'] );
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
}
