<?php
/**
 * Move class file.
 *
 * @package Activitypub
 */

namespace Activitypub;

use Activitypub\Activity\Activity;
use Activitypub\Activity\Actor;
use Activitypub\Collection\Actors;
use Activitypub\Collection\Blocked_Actors;
use Activitypub\Collection\Followers;
use Activitypub\Collection\Following;
use Activitypub\Collection\Remote_Actors;
use Activitypub\Model\Blog;
use Activitypub\Model\User;
use Activitypub\Scheduler\Actor as Actor_Scheduler;

use function Activitypub\is_actor;
use function Activitypub\unescape_guid;

/**
 * ActivityPub (Account) Move Class
 *
 * @author Matthias Pfefferle
 */
class Move {

	/**
	 * Initialize the Move class.
	 *
	 * @return void
	 */
	public static function init() {
		/**
		 * Filter to enable automatically moving Fediverse accounts when the domain changes.
		 *
		 * @param bool $domain_moves_enabled Whether domain moves are enabled.
		 */
		$domain_moves_enabled = \apply_filters( 'activitypub_enable_primary_domain_moves', false );

		if ( $domain_moves_enabled ) {
			// Add the filter to change the domain.
			\add_filter( 'update_option_home', array( self::class, 'change_domain' ), 10, 2 );

			if ( \get_option( 'activitypub_old_host' ) ) {
				\add_action( 'activitypub_construct_model_actor', array( self::class, 'maybe_initiate_old_user' ) );
				\add_action( 'activitypub_pre_send_to_inboxes', array( self::class, 'pre_send_to_inboxes' ) );

				if ( ! is_user_type_disabled( 'blog' ) ) {
					\add_filter( 'activitypub_pre_get_by_username', array( self::class, 'old_blog_username' ), 10, 2 );
				}
			}
		}
	}

	/**
	 * Move an ActivityPub account from one location to another.
	 *
	 * @param string $from The current account URL.
	 * @param string $to   The new account URL.
	 *
	 * @return int|bool|\WP_Error The ID of the outbox item or false or WP_Error on failure.
	 */
	public static function account( $from, $to ) {
		if ( is_same_domain( $from ) && is_same_domain( $to ) ) {
			return self::internally( $from, $to );
		}

		return self::externally( $from, $to );
	}

	/**
	 * Move an ActivityPub Actor from one location (internal) to another (external).
	 *
	 * This helps with migrating local profiles to a new external profile:
	 *
	 * `Move::externally( 'https://example.com/?author=123', 'https://mastodon.example/users/foo' );`
	 *
	 * @param string $from The current account URL.
	 * @param string $to   The new account URL.
	 *
	 * @return int|bool|\WP_Error The ID of the outbox item or false or WP_Error on failure.
	 */
	public static function externally( $from, $to ) {
		$user = Actors::get_by_various( $from );

		if ( \is_wp_error( $user ) ) {
			return $user;
		}

		$response = Proxy::get( $to, array( 'cached' => false ) );

		if ( \is_wp_error( $response ) ) {
			return $response;
		}

		$target_actor = new Actor();
		$target_actor->from_array( $response );

		/*
		 * Receivers accept the Move only when the id sent as its `object` is listed in the target's
		 * `alsoKnownAs`, and only an actor with a canonical id can be federated.
		 */
		$target_id     = $target_actor->get_id();
		$also_known_as = (array) $target_actor->get_also_known_as();
		if ( ! is_actor( $response ) || ! \is_string( $target_id ) || '' === $target_id || $target_id === $user->get_id() || ! \in_array( $user->get_id(), $also_known_as, true ) ) {
			return new \WP_Error( 'invalid_target', \__( 'Invalid target', 'activitypub' ) );
		}

		$activity = new Activity();
		$activity->set_type( 'Move' );
		$activity->set_actor( $user->get_id() );
		$activity->set_origin( $user->get_id() );
		$activity->set_object( $user->get_id() );
		$activity->set_target( $target_id );
		// The Move goes to the old actor's followers (FEP-7628); the transformer only adds the public audience.
		$activity->set_cc( array( $user->get_followers() ) );

		$outbox_id = add_to_outbox( $activity, null, $user->get__id(), ACTIVITYPUB_CONTENT_VISIBILITY_PUBLIC );

		// The move takes effect only once the Move is queued; the profile Update then carries the new `movedTo`.
		if ( $outbox_id && ! \is_wp_error( $outbox_id ) ) {
			// Store the canonical id: receivers compare the advertised `movedTo` against the Move's `target`.
			if ( $user->get__id() > 0 ) {
				\update_user_option( $user->get__id(), 'activitypub_moved_to', $target_id );
			} else {
				\update_option( 'activitypub_blog_user_moved_to', $target_id );
			}

			Actor_Scheduler::schedule_profile_update( $user->get__id() );
		}

		return $outbox_id;
	}

	/**
	 * Internal Move.
	 *
	 * Move an ActivityPub Actor from one location (internal) to another (internal).
	 *
	 * This helps with migrating abandoned profiles to `Move` to other profiles:
	 *
	 * `Move::internally( 'https://example.com/?author=123', 'https://example.com/?author=321' );`
	 *
	 * ... or to change Actor-IDs like:
	 *
	 * `Move::internally( 'https://example.com/author/foo', 'https://example.com/?author=123' );`
	 *
	 * @param string $from The current account URL.
	 * @param string $to   The new account URL.
	 *
	 * @return int|bool|\WP_Error The ID of the outbox item or false or WP_Error on failure.
	 */
	public static function internally( $from, $to ) {
		$user = Actors::get_by_various( $from );

		if ( \is_wp_error( $user ) ) {
			return $user;
		}

		// Resolving through the model rejects a user without the ActivityPub capability.
		$target = Actors::get_by_various( $to );

		if ( \is_wp_error( $target ) ) {
			return $target;
		}

		$target_id = $target->get__id();

		// Advertise and federate the target's canonical id, whatever form the move was requested with.
		$to = $target->get_id();

		// check if `$from` is a URL or an ID.
		if ( \filter_var( $from, FILTER_VALIDATE_URL ) ) {
			$actor = $from;
		} else {
			$actor = $user->get_id();
		}

		$activity = new Activity();
		$activity->set_type( 'Move' );
		$activity->set_actor( $actor );
		$activity->set_origin( $actor );
		$activity->set_object( $actor );
		$activity->set_target( $to );
		// The Move goes to the old actor's followers (FEP-7628); the transformer only adds the public audience.
		$activity->set_to( array( $user->get_followers() ) );

		$outbox_id = add_to_outbox( $activity, null, $user->get__id(), ACTIVITYPUB_CONTENT_VISIBILITY_QUIET_PUBLIC );

		// The move takes effect only once the Move is queued; the profile Updates then carry the new links.
		if ( $outbox_id && ! \is_wp_error( $outbox_id ) ) {
			// Point the old actor at the new one.
			if ( $user->get__id() > 0 ) {
				\update_user_option( $user->get__id(), 'activitypub_moved_to', $to );
			} else {
				\update_option( 'activitypub_blog_user_moved_to', $to );
			}

			/*
			 * The old actor id goes into the target's `alsoKnownAs`, since receivers accept the Move
			 * only when the new actor links back. For a domain change both resolve to the same actor.
			 */
			if ( $target_id > 0 ) {
				self::update_user_also_known_as( $target_id, $actor );
			} else {
				self::update_blog_also_known_as( $actor );
			}

			Actor_Scheduler::schedule_profile_update( $user->get__id() );

			if ( $target_id !== $user->get__id() ) {
				Actor_Scheduler::schedule_profile_update( $target_id );
			}
		}

		return $outbox_id;
	}

	/**
	 * Move a remote actor to its new id.
	 *
	 * Our users who follow the old actor follow the new one (FEP-7628), and their blocks carry over.
	 *
	 * @since unreleased
	 *
	 * @param \WP_Post $origin The stored old actor.
	 * @param array    $actor  The new actor, fetched and verified.
	 *
	 * @return int|\WP_Error The post ID of the new actor, or WP_Error if it can not be stored.
	 */
	public static function remote( $origin, $actor ) {
		// Read before the merge, which deletes the old actor.
		$refollow   = self::get_following_user_ids( $origin->ID );
		$blocked_by = \get_post_meta( $origin->ID, Moderation::BLOCKED_ACTORS_META_KEY, false );
		$origin_uri = unescape_guid( $origin->guid );
		$old_actor  = $blocked_by ? Remote_Actors::get_actor( $origin ) : null;

		// Stored first, so a new actor that can not be stored (no inbox) leaves the old one as it was.
		$post_id = Remote_Actors::upsert( $actor );

		if ( \is_wp_error( $post_id ) ) {
			return $post_id;
		}

		// A merge into the old actor itself would delete it.
		if ( ! $post_id || $post_id === $origin->ID ) {
			return new \WP_Error( 'activitypub_actor_not_moved', \__( 'The actor could not be moved.', 'activitypub' ) );
		}

		// Users who already follow the new actor keep that follow.
		$refollow = \array_diff( $refollow, self::get_following_user_ids( $post_id ) );

		Remote_Actors::merge( $origin->ID, $post_id );

		// The old account stays reachable, so it keeps its blocks. Stored again from our copy, without a fetch.
		if ( $old_actor && ! \is_wp_error( $old_actor ) ) {
			Remote_Actors::upsert( $old_actor );
		}

		// Through Blocked_Actors::add(), so the block hooks run and clean up the relationships.
		foreach ( $blocked_by as $user_id ) {
			Blocked_Actors::add( (int) $user_id, $actor['id'] );
			Blocked_Actors::add( (int) $user_id, $origin_uri );
		}

		// A user who blocks the new actor neither follows it nor keeps it as a follower.
		foreach ( \get_post_meta( $post_id, Followers::FOLLOWER_META_KEY, false ) as $user_id ) {
			if ( Moderation::is_actor_blocked( $actor['id'], (int) $user_id ) ) {
				Followers::remove( $post_id, (int) $user_id );
			}
		}

		// A user who blocks the new actor does not follow it; follow() refuses users who can no longer follow.
		foreach ( $refollow as $user_id ) {
			if ( ! Moderation::is_actor_blocked( $actor['id'], (int) $user_id ) ) {
				Following::follow( $post_id, (int) $user_id );
			}
		}

		return $post_id;
	}

	/**
	 * Get the local users that follow or asked to follow a remote actor.
	 *
	 * @param int $post_id The remote actor post ID.
	 *
	 * @return string[] The user IDs.
	 */
	private static function get_following_user_ids( $post_id ) {
		return \array_unique(
			\array_merge(
				\get_post_meta( $post_id, Following::FOLLOWING_META_KEY, false ),
				\get_post_meta( $post_id, Following::PENDING_META_KEY, false )
			)
		);
	}

	/**
	 * Update the alsoKnownAs property of a user.
	 *
	 * @param int    $user_id The user ID.
	 * @param string $from    The current account URL.
	 *
	 * @return void
	 */
	private static function update_user_also_known_as( $user_id, $from ) {
		$also_known_as = \get_user_option( 'activitypub_also_known_as', $user_id ) ?: array();

		if ( \in_array( $from, $also_known_as, true ) ) {
			return;
		}

		$also_known_as[] = $from;

		\update_user_option( $user_id, 'activitypub_also_known_as', $also_known_as );
	}

	/**
	 * Update the alsoKnownAs property of the blog.
	 *
	 * @param string $from The current account URL.
	 *
	 * @return void
	 */
	private static function update_blog_also_known_as( $from ) {
		$also_known_as = \get_option( 'activitypub_blog_user_also_known_as', array() );

		if ( \in_array( $from, $also_known_as, true ) ) {
			return;
		}

		$also_known_as[] = $from;

		\update_option( 'activitypub_blog_user_also_known_as', $also_known_as );
	}

	/**
	 * Change domain for all ActivityPub Actors.
	 *
	 * This method handles domain migration according to the ActivityPub Data Portability spec.
	 * It stores the old host and calls Move::internally for each available profile.
	 * It also caches the JSON representation of the old Actor for future lookups.
	 *
	 * @param string $from The old domain.
	 * @param string $to   The new domain.
	 *
	 * @return array Array of results from Move::internally calls.
	 */
	public static function change_domain( $from, $to ) {
		// Get all actors that need to be migrated.
		$actors = Actors::get_all();

		$results   = array();
		$to_host   = \wp_parse_url( $to, \PHP_URL_HOST );
		$from_host = \wp_parse_url( $from, \PHP_URL_HOST );

		// Store the old host for future reference.
		\update_option( 'activitypub_old_host', $from_host );

		// Process each actor.
		foreach ( $actors as $actor ) {
			$actor_id = $actor->get_id();

			// Replace the new host with the old host in the actor ID.
			$old_actor_id = \str_replace( $to_host, $from_host, $actor_id );

			// Call Move::internally for this actor.
			$result = self::internally( $old_actor_id, $actor_id );

			if ( \is_wp_error( $result ) ) {
				/**
				 * Fires when an actor move fails during domain change.
				 *
				 * @since 8.1.0
				 *
				 * @param \WP_Error $result   The error that occurred.
				 * @param string    $actor_id The actor ID that failed to move.
				 */
				\do_action( 'activitypub_move_failed', $result, $actor_id );
				continue;
			}

			$json = \str_replace( $to_host, $from_host, $actor->to_json() );

			// Save the current actor data after migration.
			if ( $actor instanceof Blog ) {
				\update_option( 'activitypub_blog_user_old_host_data', $json, false );
			} else {
				\update_user_option( $actor->get__id(), 'activitypub_old_host_data', $json );
			}

			$results[] = array(
				'actor'  => $actor_id,
				'result' => $result,
			);
		}

		return $results;
	}

	/**
	 * Maybe initiate old user.
	 *
	 * This method checks if the current request domain matches the old host.
	 * If it does, it retrieves the cached data for the user and populates the instance.
	 *
	 * @param Blog|User $instance The Blog or User instance to populate.
	 *
	 * @return void
	 */
	public static function maybe_initiate_old_user( $instance ) {
		if ( ! Query::get_instance()->is_old_host_request() ) {
			return;
		}

		if ( $instance instanceof Blog ) {
			$cached_data = \get_option( 'activitypub_blog_user_old_host_data' );
		} elseif ( $instance instanceof User ) {
			$cached_data = \get_user_option( 'activitypub_old_host_data', $instance->get__id() );
		}

		if ( ! empty( $cached_data ) ) {
			$instance->from_json( $cached_data );
		}
	}

	/**
	 * Pre-send to inboxes.
	 *
	 * @param string $json The ActivityPub Activity JSON.
	 *
	 * @return void
	 */
	public static function pre_send_to_inboxes( $json ) {
		$json = \json_decode( $json, true );

		if ( 'Move' !== $json['type'] ) {
			return;
		}

		if ( is_same_domain( $json['object'] ) ) {
			return;
		}

		Query::get_instance()->set_old_host_request();
	}

	/**
	 * Filter to return the old blog username.
	 *
	 * @param null   $pre      The pre-existing value.
	 * @param string $username The username to check.
	 *
	 * @return Blog|null The old blog instance or null.
	 */
	public static function old_blog_username( $pre, $username ) {
		$old_host = \get_option( 'activitypub_old_host' );

		// Special case for Blog Actor on old host.
		if ( $old_host === $username && Query::get_instance()->is_old_host_request() ) {
			// Return a new Blog instance which will load the cached data in its constructor.
			$pre = new Blog();
		}

		return $pre;
	}
}
