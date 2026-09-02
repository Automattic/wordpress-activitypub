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

		if ( ! self::has_blocks( $blocks ) ) {
			return false;
		}

		return self::check_activity_against_blocks( $activity, $blocks );
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

		if ( ! self::has_blocks( $blocks ) ) {
			return false;
		}

		return self::check_activity_against_blocks( $activity, $blocks );
	}

	/**
	 * Check whether a set of blocks has anything in it.
	 *
	 * Worth asking before the checks run: they parse the activity and can go to the network for a
	 * handle, and this runs once per local recipient of every delivery. Most sites block nothing.
	 *
	 * @since 9.3.0
	 *
	 * @param array $blocks Blocks organized by type, as returned by get_site_blocks().
	 *
	 * @return bool True if any list has an entry, false otherwise.
	 */
	public static function has_blocks( $blocks ) {
		return self::has_actor_blocks( $blocks ) || self::has_domain_blocks( $blocks ) || self::has_keyword_blocks( $blocks );
	}

	/**
	 * Check whether a set of blocks names any actor.
	 *
	 * @since 9.3.0
	 *
	 * @param array $blocks Blocks organized by type.
	 *
	 * @return bool True if an actor is blocked, false otherwise.
	 */
	public static function has_actor_blocks( $blocks ) {
		return ! empty( $blocks['actors'] );
	}

	/**
	 * Check whether a set of blocks names any domain.
	 *
	 * @since 9.3.0
	 *
	 * @param array $blocks Blocks organized by type.
	 *
	 * @return bool True if a domain is blocked, false otherwise.
	 */
	public static function has_domain_blocks( $blocks ) {
		return ! empty( $blocks['domains'] );
	}

	/**
	 * Check whether a set of blocks names any keyword.
	 *
	 * @since 9.3.0
	 *
	 * @param array $blocks Blocks organized by type.
	 *
	 * @return bool True if a keyword is blocked, false otherwise.
	 */
	public static function has_keyword_blocks( $blocks ) {
		return ! empty( $blocks['keywords'] );
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

		$hosts = array( Webfinger::get_host( $actor_uri ) );

		// Check site-wide blocks.
		$site_blocks = self::get_site_blocks();
		if ( self::uri_matches_actors( $actor_uri, $site_blocks['actors'] ) ) {
			return true;
		}

		// Check site-wide domain blocks.
		if ( self::hosts_are_blocked( $hosts, $site_blocks['domains'] ) ) {
			return true;
		}

		// Check user-specific blocks if user_id is provided.
		if ( $user_id > 0 ) {
			$user_blocks = self::get_user_blocks( $user_id );
			if ( self::uri_matches_actors( $actor_uri, $user_blocks['actors'] ) ) {
				return true;
			}

			// Check user-specific domain blocks.
			if ( self::hosts_are_blocked( $hosts, $user_blocks['domains'] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check a set of hosts against the blocked domains.
	 *
	 * @param string[] $hosts           The folded hosts to check.
	 * @param array    $blocked_domains The blocked domains.
	 *
	 * @return bool True if any host is blocked, false otherwise.
	 */
	private static function hosts_are_blocked( $hosts, $blocked_domains ) {
		/*
		 * Cast because a stored list is not guaranteed to be one: array_map() fatals on a scalar
		 * where the foreach this replaced only warned, and this runs on every delivery.
		 * array_filter drops the empty hosts, so an empty stored entry can never match one.
		 */
		return (bool) \array_intersect( \array_filter( $hosts ), \array_map( __NAMESPACE__ . '\\fold_host', (array) $blocked_domains ) );
	}

	/**
	 * Check an actor URI against a list of blocked actors.
	 *
	 * Compared entry by entry rather than by normalizing the whole list first, so a match
	 * returns without touching the rest of it. This runs on every delivery, and a site can
	 * block a lot of accounts.
	 *
	 * @param string   $uri            The actor URI to look for, in any spelling.
	 * @param string[] $blocked_actors The blocked actor URIs.
	 *
	 * @return bool True if the URI is blocked, false otherwise.
	 */
	private static function uri_matches_actors( $uri, $blocked_actors ) {
		$normalized = normalize_actor_uri( $uri );

		if ( '' === $normalized ) {
			return false;
		}

		foreach ( $blocked_actors as $blocked ) {
			if ( normalize_actor_uri( $blocked ) === $normalized ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check a delivered actor URI against the blocked actors.
	 *
	 * The delivered `actor` is only bound to a host by the signature, not to an exact string, so a
	 * spelling that normalizes differently still has to be resolved to be ruled out.
	 *
	 * Two steps. The delivered string is compared normalized, which settles the ordinary case
	 * without leaving the site. A delivery that gets past that is resolved over the network, so a
	 * spelling the normalizer cannot fold still gets ruled out. A failed fetch leaves the delivery
	 * unblocked, as before: a host that will not answer cannot be confirmed as blocked, and the
	 * host owns the actor being claimed, so refusing to answer is a way out of a block. The
	 * domain list is the tool for a host behaving that way.
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

		/*
		 * Narrow by host first, so only a delivery that could plausibly be blocked pays for a
		 * resolution. Most deliveries reach a list with nothing on their host and leave here. The
		 * host comes from the delivered actor only: taking it from the key id would let a forged
		 * header choose which entries get compared.
		 *
		 * This is a cost gate, not a completeness one. An actor whose own host carries no entry is
		 * never resolved, so a document served elsewhere that declares a blocked id is not caught
		 * here even though the resolved comparison below would match it. Resolving every delivery
		 * to close that would mean an outbound request per delivery whenever any actor is blocked.
		 * Block the host to cover it.
		 */
		$host = Webfinger::get_host( $actor_id );

		if ( '' === $host ) {
			return false;
		}

		$on_host = \array_filter(
			$blocked_actors,
			static function ( $blocked ) use ( $host ) {
				return Webfinger::get_host( $blocked ) === $host;
			}
		);

		if ( empty( $on_host ) ) {
			return false;
		}

		if ( self::uri_matches_actors( $actor_id, $on_host ) ) {
			return true;
		}

		/*
		 * Resolved rather than read from a locally stored actor: a `guid` is the id the actor
		 * declared, and `Update` stores an embedded actor object bound only to the sender's
		 * host, so a remote server can store itself under any same-host id it likes.
		 */
		$object = Http::get_remote_object( $actor_id );

		/*
		 * Compared against the whole list, not the host-narrowed one: that narrowing decides
		 * whether resolving is worth a request, but the id it resolves to can be on another host
		 * and still be blocked.
		 */
		return ! \is_wp_error( $object )
			&& isset( $object['id'] )
			&& \is_string( $object['id'] )
			&& self::uri_matches_actors( $object['id'], $blocked_actors );
	}

	/**
	 * Check activity against blocklists.
	 *
	 * @param Activity $activity The activity.
	 * @param array    $blocks   Blocks organized by type, as returned by get_site_blocks().
	 * @return bool True if blocked, false otherwise.
	 */
	private static function check_activity_against_blocks( $activity, $blocks ) {
		// Extract actor information.
		$actor_id = object_to_uri( $activity->get_actor() );

		/*
		 * Domains are checked before anything that goes to the network: the webfinger lookup
		 * below and the actor check both issue requests, and a blocked domain must not be
		 * contacted to find out that it is blocked.
		 */
		if ( self::has_domain_blocks( $blocks ) ) {
			$hosts = array(
				Webfinger::get_host( $actor_id ),
				Webfinger::get_host( $activity->get_id() ),
				Webfinger::get_host( object_to_uri( $activity->get_object() ) ),
			);

			if ( self::hosts_are_blocked( $hosts, $blocks['domains'] ) ) {
				return true;
			}
		}

		/*
		 * `is_acct()` is too strict to decide this. Its host grammar rejects a trailing-dot FQDN
		 * and any non-ASCII host, both of which `Webfinger::resolve()` resolves fine, and a
		 * delivery that skips resolution over a spelling is a delivery that skips the block. All
		 * that matters here is whether this is a handle rather than a URL already worth comparing.
		 */
		$is_handle = false === \strpos( $actor_id, '://' ) && false !== \strpos( $actor_id, '@' );

		/*
		 * Resolve a handle to its URL, but only for a list that could match it: a keyword-only
		 * blocklist has no use for the actor's URL and should not pay a lookup for one. Its own
		 * host is not blocked, or we would have returned above.
		 */

		if ( ( self::has_domain_blocks( $blocks ) || self::has_actor_blocks( $blocks ) ) && $is_handle ) {
			$resolved_url = Webfinger::resolve( $actor_id );
			$actor_id     = \is_wp_error( $resolved_url ) ? $actor_id : $resolved_url;

			/*
			 * Checked again: webfinger returns whatever `href` the remote document names, and
			 * that can be on a different host than the handle it was looked up under.
			 */
			if ( self::hosts_are_blocked( array( Webfinger::get_host( $actor_id ) ), $blocks['domains'] ) ) {
				return true;
			}
		}

		// Check blocked actors.
		if ( self::actor_matches_blocklist( $actor_id, $blocks['actors'] ) ) {
			return true;
		}

		// Check blocked keywords in activity content.
		if ( self::has_keyword_blocks( $blocks ) && \is_object( $activity->get_object() ) ) {
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

			foreach ( (array) $blocks['keywords'] as $keyword ) {
				if ( \stripos( $content, $keyword ) !== false ) {
					return true;
				}
			}
		}

		return false;
	}
}
