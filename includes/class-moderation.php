<?php
/**
 * Moderation class file.
 *
 * @package Activitypub
 */

namespace Activitypub;

use Activitypub\Activity\Activity;
use Activitypub\Collection\Actors;
use Activitypub\Collection\Blocked_Actors;

/**
 * ActivityPub Moderation class.
 *
 * Handles user-specific blocking and site-wide moderation.
 */
class Moderation {

	/**
	 * Block type constants.
	 */
	const TYPE_ACTOR   = 'actor';
	const TYPE_DOMAIN  = 'domain';
	const TYPE_KEYWORD = 'keyword';

	/**
	 * Post meta key for blocked actors.
	 */
	const BLOCKED_ACTORS_META_KEY = '_activitypub_blocked_by';

	/**
	 * User meta key for blocked keywords.
	 */
	const USER_META_KEYS = array(
		self::TYPE_DOMAIN  => 'activitypub_blocked_domains',
		self::TYPE_KEYWORD => 'activitypub_blocked_keywords',
	);

	/**
	 * Option key for site-wide blocked keywords.
	 */
	const OPTION_KEYS = array(
		self::TYPE_DOMAIN  => 'activitypub_site_blocked_domains',
		self::TYPE_KEYWORD => 'activitypub_site_blocked_keywords',
	);

	/**
	 * Check if an activity should be blocked for a specific user.
	 *
	 * @param Activity $activity The activity.
	 * @param int|null $user_id  The user ID to check blocks for.
	 * @return bool True if blocked, false otherwise.
	 */
	public static function activity_is_blocked( $activity, $user_id = null ) {
		if ( ! $activity instanceof Activity ) {
			return false;
		}

		// First check site-wide blocks (admin moderation).
		if ( self::activity_is_blocked_site_wide( $activity ) ) {
			return true;
		}

		// Then check user-specific blocks.
		if ( $user_id && self::activity_is_blocked_for_user( $activity, $user_id ) ) {
			return true;
		}

		$remote_addr = \sanitize_text_field( \wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );
		$user_agent  = \sanitize_text_field( \wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' ) );

		// Fall back to WordPress comment disallowed list.
		return \wp_check_comment_disallowed_list( $activity->to_json( false ), '', '', $activity->get_content(), $remote_addr, $user_agent );
	}

	/**
	 * Check if an activity is blocked site-wide.
	 *
	 * @param Activity $activity The activity.
	 * @return bool True if blocked, false otherwise.
	 */
	public static function activity_is_blocked_site_wide( $activity ) {
		$blocks = self::get_site_blocks();

		return self::check_activity_against_blocks( $activity, $blocks['actors'], $blocks['domains'], $blocks['keywords'] );
	}

	/**
	 * Check if an activity is blocked for a specific user.
	 *
	 * @param Activity $activity The activity.
	 * @param int      $user_id  The user ID.
	 * @return bool True if blocked, false otherwise.
	 */
	public static function activity_is_blocked_for_user( $activity, $user_id ) {
		$blocks = self::get_user_blocks( $user_id );

		return self::check_activity_against_blocks( $activity, $blocks['actors'], $blocks['domains'], $blocks['keywords'] );
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
			case self::TYPE_ACTOR:
				return Blocked_Actors::add_block( $user_id, $value );

			case self::TYPE_DOMAIN:
			case self::TYPE_KEYWORD:
				$blocks = \get_user_meta( $user_id, self::USER_META_KEYS[ $type ], true ) ?: array(); // phpcs:ignore Universal.Operators.DisallowShortTernary.Found

				if ( ! \in_array( $value, $blocks, true ) ) {
					/**
					 * Fired when a domain or keyword is blocked.
					 *
					 * @param string $value   The blocked domain or keyword.
					 * @param string $type    The block type (actor, domain, keyword).
					 * @param int    $user_id The user ID.
					 */
					\do_action( 'activitypub_add_user_block', $value, $type, $user_id );

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
			case self::TYPE_ACTOR:
				return Blocked_Actors::remove_block( $user_id, $value );

			case self::TYPE_DOMAIN:
			case self::TYPE_KEYWORD:
				$blocks = \get_user_meta( $user_id, self::USER_META_KEYS[ $type ], true ) ?: array(); // phpcs:ignore Universal.Operators.DisallowShortTernary.Found
				$key    = \array_search( $value, $blocks, true );

				if ( false !== $key ) {
					/**
					 * Fired when a domain or keyword is unblocked.
					 *
					 * @param string $value   The unblocked domain or keyword.
					 * @param string $type    The block type (actor, domain, keyword).
					 * @param int    $user_id The user ID.
					 */
					\do_action( 'activitypub_remove_user_block', $value, $type, $user_id );

					unset( $blocks[ $key ] );
					return \update_user_meta( $user_id, self::USER_META_KEYS[ $type ], \array_values( $blocks ) );
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
			'actors'   => \wp_list_pluck( Blocked_Actors::get_blocked_actors( $user_id ), 'guid' ),
			'domains'  => \get_user_meta( $user_id, self::USER_META_KEYS[ self::TYPE_DOMAIN ], true ) ?: array(), // phpcs:ignore Universal.Operators.DisallowShortTernary.Found
			'keywords' => \get_user_meta( $user_id, self::USER_META_KEYS[ self::TYPE_KEYWORD ], true ) ?: array(), // phpcs:ignore Universal.Operators.DisallowShortTernary.Found
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
			case self::TYPE_ACTOR:
				// Site-wide actor blocking uses the BLOG_USER_ID.
				return self::add_user_block( Actors::BLOG_USER_ID, self::TYPE_ACTOR, $value );

			case self::TYPE_DOMAIN:
			case self::TYPE_KEYWORD:
				$blocks = \get_option( self::OPTION_KEYS[ $type ], array() );

				if ( ! \in_array( $value, $blocks, true ) ) {
					/**
					 * Fired when a domain or keyword is blocked site-wide.
					 *
					 * @param string $value The blocked domain or keyword.
					 * @param string $type  The block type (actor, domain, keyword).
					 */
					\do_action( 'activitypub_add_site_block', $value, $type );

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
			case self::TYPE_ACTOR:
				// Site-wide actor unblocking uses the BLOG_USER_ID.
				return self::remove_user_block( Actors::BLOG_USER_ID, self::TYPE_ACTOR, $value );

			case self::TYPE_DOMAIN:
			case self::TYPE_KEYWORD:
				$blocks = \get_option( self::OPTION_KEYS[ $type ], array() );
				$key    = \array_search( $value, $blocks, true );

				if ( false !== $key ) {
					/**
					 * Fired when a domain or keyword is unblocked site-wide.
					 *
					 * @param string $value The unblocked domain or keyword.
					 * @param string $type  The block type (actor, domain, keyword).
					 */
					\do_action( 'activitypub_remove_site_block', $value, $type );

					unset( $blocks[ $key ] );
					return \update_option( self::OPTION_KEYS[ $type ], \array_values( $blocks ) );
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
			'actors'   => \wp_list_pluck( Blocked_Actors::get_blocked_actors( Actors::BLOG_USER_ID ), 'guid' ),
			'domains'  => \get_option( self::OPTION_KEYS[ self::TYPE_DOMAIN ], array() ),
			'keywords' => \get_option( self::OPTION_KEYS[ self::TYPE_KEYWORD ], array() ),
		);
	}

	/**
	 * Check if an actor is blocked by user or site-wide.
	 *
	 * @param string $actor_uri Actor URI to check.
	 * @param int    $user_id   Optional. User ID to check user blocks for. Defaults to 0 (site-wide only).
	 * @return bool True if blocked, false otherwise.
	 */
	public static function is_actor_blocked( $actor_uri, $user_id = 0 ) {
		if ( ! $actor_uri ) {
			return false;
		}

		// Check site-wide blocks.
		$site_blocks = self::get_site_blocks();
		if ( \in_array( $actor_uri, $site_blocks['actors'], true ) ) {
			return true;
		}

		// Check site-wide domain blocks.
		$actor_domain = \wp_parse_url( $actor_uri, PHP_URL_HOST );
		if ( $actor_domain && \in_array( $actor_domain, $site_blocks['domains'], true ) ) {
			return true;
		}

		// Check user-specific blocks if user_id is provided.
		if ( $user_id > 0 ) {
			$user_blocks = self::get_user_blocks( $user_id );
			if ( \in_array( $actor_uri, $user_blocks['actors'], true ) ) {
				return true;
			}

			// Check user-specific domain blocks.
			if ( $actor_domain && \in_array( $actor_domain, $user_blocks['domains'], true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check activity against blocklists.
	 *
	 * @param Activity $activity         The activity.
	 * @param array    $blocked_actors   List of blocked actors.
	 * @param array    $blocked_domains  List of blocked domains.
	 * @param array    $blocked_keywords List of blocked keywords.
	 * @return bool True if blocked, false otherwise.
	 */
	private static function check_activity_against_blocks( $activity, $blocked_actors, $blocked_domains, $blocked_keywords ) {
		$has_object = \is_object( $activity->get_object() );

		// Extract actor information.
		$actor_id = object_to_uri( $activity->get_actor() );

		// Check blocked actors.
		if ( $actor_id ) {
			// If actor_id is not a URL, resolve it via webfinger.
			if ( ! \str_starts_with( $actor_id, 'http' ) ) {
				$resolved_url = Webfinger::resolve( $actor_id );
				if ( ! \is_wp_error( $resolved_url ) ) {
					$actor_id = $resolved_url;
				}
			}

			if ( \in_array( $actor_id, $blocked_actors, true ) ) {
				return true;
			}
		}

		// Check blocked domains.
		$urls = array(
			\wp_parse_url( $actor_id, PHP_URL_HOST ),
			\wp_parse_url( $activity->get_id(), PHP_URL_HOST ),
			\wp_parse_url( object_to_uri( $activity->get_object() ) ?? '', PHP_URL_HOST ),
		);
		foreach ( $blocked_domains as $domain ) {
			if ( \in_array( $domain, $urls, true ) ) {
				return true;
			}
		}

		// Check blocked keywords in activity content.
		if ( $has_object ) {
			$object        = $activity->get_object();
			$content_map   = array();
			$content_map[] = $object->get_content();
			$content_map[] = $object->get_summary();
			$content_map[] = $object->get_name();

			if ( is_actor( $object ) ) {
				$content_map[] = $object->get_preferred_username();
			}

			if ( \is_array( $object->get_content_map() ) ) {
				$content_map = \array_merge( $content_map, \array_values( $object->get_content_map() ) );
			}

			if ( \is_array( $object->get_summary_map() ) ) {
				$content_map = \array_merge( $content_map, \array_values( $object->get_summary_map() ) );
			}

			if ( \is_array( $object->get_name_map() ) ) {
				$content_map = \array_merge( $content_map, \array_values( $object->get_name_map() ) );
			}

			$content_map = \array_filter( $content_map );
			$content     = \implode( ' ', $content_map );

			foreach ( $blocked_keywords as $keyword ) {
				if ( \stripos( $content, $keyword ) !== false ) {
					return true;
				}
			}
		}

		return false;
	}
}
