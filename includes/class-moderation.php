<?php
/**
 * Moderation class file.
 *
 * @package Activitypub
 */

namespace Activitypub;

use Activitypub\Activity\Activity;
use Activitypub\Activity\Actor;
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
				return Blocked_Actors::add( $user_id, $value );

			case self::TYPE_DOMAIN:
			case self::TYPE_KEYWORD:
				$blocks = \get_user_meta( $user_id, self::USER_META_KEYS[ $type ], true ) ?: array();

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
				return Blocked_Actors::remove( $user_id, $value );

			case self::TYPE_DOMAIN:
			case self::TYPE_KEYWORD:
				$blocks = \get_user_meta( $user_id, self::USER_META_KEYS[ $type ], true ) ?: array();
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
			'actors'   => \wp_list_pluck( Blocked_Actors::get_many( $user_id ), 'guid' ),
			'domains'  => \get_user_meta( $user_id, self::USER_META_KEYS[ self::TYPE_DOMAIN ], true ) ?: array(),
			'keywords' => \get_user_meta( $user_id, self::USER_META_KEYS[ self::TYPE_KEYWORD ], true ) ?: array(),
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
	 * Add multiple site-wide blocks at once.
	 *
	 * More efficient than calling add_site_block() in a loop as it
	 * performs a single database update.
	 *
	 * @param string $type   The block type (domain or keyword only).
	 * @param array  $values Array of values to block.
	 */
	public static function add_site_blocks( $type, $values ) {
		if ( ! \in_array( $type, array( self::TYPE_DOMAIN, self::TYPE_KEYWORD ), true ) ) {
			return;
		}

		if ( empty( $values ) ) {
			return;
		}

		foreach ( $values as $value ) {
			/**
			 * Fired when a domain or keyword is blocked site-wide.
			 *
			 * @param string $value The blocked domain or keyword.
			 * @param string $type  The block type (actor, domain, keyword).
			 */
			\do_action( 'activitypub_add_site_block', $value, $type );
		}

		$existing = \get_option( self::OPTION_KEYS[ $type ], array() );
		\update_option( self::OPTION_KEYS[ $type ], \array_unique( \array_merge( $existing, $values ) ) );
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
			'actors'   => \wp_list_pluck( Blocked_Actors::get_many( Actors::BLOG_USER_ID ), 'guid' ),
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

		$normalized = normalize_actor_uri( $actor_uri );

		// Check site-wide blocks.
		$site_blocks = self::get_site_blocks();
		if ( \in_array( $normalized, \array_map( __NAMESPACE__ . '\normalize_actor_uri', $site_blocks['actors'] ), true ) ) {
			return true;
		}

		// Check site-wide domain blocks. Hosts are case-insensitive, so both sides are folded.
		$actor_domain = \strtolower( (string) \wp_parse_url( $actor_uri, PHP_URL_HOST ) );
		if ( $actor_domain && \in_array( $actor_domain, \array_map( 'strtolower', $site_blocks['domains'] ), true ) ) {
			return true;
		}

		// Check user-specific blocks if user_id is provided.
		if ( $user_id > 0 ) {
			$user_blocks = self::get_user_blocks( $user_id );
			if ( \in_array( $normalized, \array_map( __NAMESPACE__ . '\normalize_actor_uri', $user_blocks['actors'] ), true ) ) {
				return true;
			}

			// Check user-specific domain blocks.
			if ( $actor_domain && \in_array( $actor_domain, \array_map( 'strtolower', $user_blocks['domains'] ), true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check a delivered actor URI against the blocked actors.
	 *
	 * The delivered `actor` is an untrusted string, and the signature only binds it to a host:
	 * `Verification::verify_key_id()` requires the keyId and the actor to share a host, not to be
	 * the same string. A blocked actor can therefore keep their real account and key and vary only
	 * the spelling of the URI they send, which `Http::get_remote_object()` then resolves back to the
	 * blocked identity when a handler stores it.
	 *
	 * Matching happens in two steps. Normalized comparison catches the spellings that are the same
	 * URI, and costs nothing. Anything else is only a match if it resolves to a blocked id, which
	 * takes a fetch, so it is limited to deliveries from a host that has a blocked actor on it:
	 * no other host can produce a match, and the signature check has already contacted this one.
	 *
	 * @param string   $actor_id       The actor URI from the delivered activity.
	 * @param string[] $blocked_actors The blocked actor URIs.
	 *
	 * @return bool True if the actor is blocked, false otherwise.
	 */
	private static function actor_matches_blocklist( $actor_id, $blocked_actors ) {
		if ( empty( $blocked_actors ) ) {
			return false;
		}

		$normalized_blocks = \array_map( __NAMESPACE__ . '\normalize_actor_uri', $blocked_actors );

		if ( \in_array( normalize_actor_uri( $actor_id ), $normalized_blocks, true ) ) {
			return true;
		}

		$host = \strtolower( (string) \wp_parse_url( $actor_id, PHP_URL_HOST ) );
		if ( '' === $host ) {
			return false;
		}

		$blocked_hosts = \array_map(
			static function ( $blocked ) {
				return \strtolower( (string) \wp_parse_url( (string) $blocked, PHP_URL_HOST ) );
			},
			$blocked_actors
		);

		if ( ! \in_array( $host, $blocked_hosts, true ) ) {
			return false;
		}

		$object = Http::get_remote_object( $actor_id );
		if ( \is_wp_error( $object ) || ! isset( $object['id'] ) || ! \is_string( $object['id'] ) ) {
			return false;
		}

		return \in_array( normalize_actor_uri( $object['id'] ), $normalized_blocks, true );
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

			if ( self::actor_matches_blocklist( $actor_id, $blocked_actors ) ) {
				return true;
			}
		}

		// Check blocked domains. Hosts are case-insensitive, so both sides are folded.
		$urls = \array_map(
			static function ( $url ) {
				return \strtolower( (string) \wp_parse_url( (string) $url, PHP_URL_HOST ) );
			},
			array( $actor_id, $activity->get_id(), object_to_uri( $activity->get_object() ) ?? '' )
		);
		foreach ( $blocked_domains as $domain ) {
			if ( \in_array( \strtolower( (string) $domain ), $urls, true ) ) {
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
				/* @var Actor $object Actor object */
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
