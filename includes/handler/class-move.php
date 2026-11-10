<?php
/**
 * Move handler file.
 *
 * @package Activitypub
 */

namespace Activitypub\Handler;

use Activitypub\Collection\Blocked_Actors;
use Activitypub\Collection\Followers;
use Activitypub\Collection\Following;
use Activitypub\Collection\Inbox;
use Activitypub\Collection\Remote_Actors;
use Activitypub\Moderation;
use Activitypub\Proxy;

use function Activitypub\get_id_hash;
use function Activitypub\get_stored_id_forms;
use function Activitypub\is_actor;
use function Activitypub\is_same_domain;
use function Activitypub\object_to_uri;

/**
 * Handle Move requests.
 *
 * @see https://www.w3.org/TR/activitystreams-vocabulary/#dfn-move
 * @see https://docs.joinmastodon.org/user/moving/
 * @see https://docs.joinmastodon.org/spec/activitypub/#Move
 */
class Move {
	/**
	 * Initialize the class, registering WordPress hooks.
	 *
	 * @return void
	 */
	public static function init() {
		\add_action( 'activitypub_inbox_move', array( self::class, 'handle_move' ), 10, 4 );
		\add_action( 'activitypub_inbox_shared_move', array( self::class, 'handle_move' ), 10, 4 );
	}

	/**
	 * Handle Move requests.
	 *
	 * @param array                               $activity        The JSON "Move" Activity.
	 * @param int|int[]                           $user_ids        The user ID(s).
	 * @param \Activitypub\Activity\Activity|null $activity_object Optional. The activity object. Default null.
	 * @param string|null                         $context         Optional. The inbox context. Default null.
	 *
	 * @return void
	 */
	public static function handle_move( $activity, $user_ids, $activity_object = null, $context = null ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		// The shared inbox invokes this once per resolved recipient and once on the shared hook;
		// handle that path only on the shared hook, so one Move is handled once.
		if ( Inbox::CONTEXT_SHARED_INBOX === $context && 'activitypub_inbox_shared_move' !== \current_filter() ) {
			return;
		}

		$target_uri = self::extract_target( $activity );
		$origin_uri = self::extract_origin( $activity );

		if ( ! $target_uri || ! $origin_uri ) {
			return;
		}

		// Only the old or the new actor may send the Move; checked before anything is fetched.
		if ( ! \in_array( object_to_uri( $activity['actor'] ?? null ), array( $origin_uri, $target_uri ), true ) ) {
			return;
		}

		// Only a stored old actor has relationships to move, so nothing else needs a fetch.
		$origin_object = Remote_Actors::get_by_uri( $origin_uri );
		if ( \is_wp_error( $origin_object ) ) {
			return;
		}

		// No request to our own site or to a blocked one; the confirmed id is checked again below.
		if ( is_same_domain( $target_uri ) || Moderation::is_actor_blocked( $target_uri ) ) {
			return;
		}

		// Both are fetched fresh: a cached copy may predate the movedTo or the alsoKnownAs.
		$target_json = Proxy::get( $target_uri, array( 'cached' => false ) );
		$origin_json = Proxy::get( $origin_uri, array( 'cached' => false ) );

		$verified = self::verify_move( $activity, $target_json, $origin_json );

		if ( ! $verified ) {
			return;
		}

		// The old actor must be the one we store, not one its URL redirects to.
		$stored_origin = Remote_Actors::get_by_uri( $origin_json['id'] );
		if ( \is_wp_error( $stored_origin ) || $stored_origin->ID !== $origin_object->ID ) {
			return;
		}

		$target_id = $target_json['id'];

		// A remote actor can not move to one of ours, nor to an actor the site blocks.
		if ( is_same_domain( $target_id ) || Moderation::is_actor_blocked( $target_id ) ) {
			return;
		}

		$target_object = Remote_Actors::get_by_uri( $target_id );

		// Our users who follow the old actor follow the new one (FEP-7628), unless they already do.
		$refollow = self::get_following_user_ids( $origin_object->ID );
		if ( ! \is_wp_error( $target_object ) ) {
			$refollow = \array_diff( $refollow, self::get_following_user_ids( $target_object->ID ) );
		}

		global $wpdb;

		// If only the origin is stored, it becomes the target.
		if ( \is_wp_error( $target_object ) ) {
			/*
			 * Proxy::get() already self-confirmed the target. Store it first, so a target that can
			 * not be stored (no inbox) leaves the origin as it was. wp_update_post() keeps the guid.
			 */
			$result = Remote_Actors::update( $origin_object->ID, $target_json );
			if ( ! $result || \is_wp_error( $result ) ) {
				return;
			}

			// Written in the form an insert stores, which every guid lookup matches.
			list( $guid ) = get_stored_id_forms( $target_id );
			$data         = array( 'guid' => $guid );
			$hash         = get_id_hash( $target_id );
			if ( $hash ) {
				$data['post_name'] = $hash;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->update( $wpdb->posts, $data, array( 'ID' => (int) $origin_object->ID ) );
			\clean_post_cache( $origin_object->ID );

			$target_post_id = $origin_object->ID;
		} else {
			// If both the target and origin are stored, merge them.
			// The new Follows go to the inbox of the fresh copy, not to a stale stored one.
			$result = Remote_Actors::update( $target_object->ID, $target_json );
			if ( ! $result || \is_wp_error( $result ) ) {
				return;
			}

			// Followers and blocks of the origin carry over; our own follows are sent again below.
			$origin_users = \get_post_meta( $origin_object->ID, Followers::FOLLOWER_META_KEY, false );
			$target_users = \get_post_meta( $target_object->ID, Followers::FOLLOWER_META_KEY, false );
			foreach ( \array_diff( $origin_users, $target_users ) as $user_id ) {
				\add_post_meta( $target_object->ID, Followers::FOLLOWER_META_KEY, $user_id );
			}

			// Through Blocked_Actors::add(), so the block hooks run and clean up the relationships.
			foreach ( \get_post_meta( $origin_object->ID, Moderation::BLOCKED_ACTORS_META_KEY, false ) as $user_id ) {
				Blocked_Actors::add( (int) $user_id, $target_id );
			}

			// Comments and Reader posts of the origin belong to the target now.
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			$where = array(
				'meta_key'   => '_activitypub_remote_actor_id',
				'meta_value' => (string) $origin_object->ID,
			);

			// The meta type and the cache group of its queries, which core names differently.
			$types = array(
				'comment' => 'comment',
				'post'    => 'posts',
			);

			foreach ( $types as $type => $cache_group ) {
				$table = $wpdb->{ \sprintf( '%smeta', $type ) };
				$ids   = $wpdb->get_col( $wpdb->prepare( 'SELECT %i FROM %i WHERE meta_key = %s AND meta_value = %s', \sprintf( '%s_id', $type ), $table, $where['meta_key'], $where['meta_value'] ) );

				if ( $ids ) {
					$wpdb->update( $table, array( 'meta_value' => (string) $target_object->ID ), $where );
					\wp_cache_delete_multiple( $ids, \sprintf( '%s_meta', $type ) );
					\wp_cache_set_last_changed( $cache_group );
				}
			}
			// phpcs:enable

			$target_post_id = $target_object->ID;
			$result         = Remote_Actors::delete( $origin_object->ID );
		}

		// A user who blocks the new actor neither follows it nor keeps it as a follower.
		foreach ( \get_post_meta( $target_post_id, Followers::FOLLOWER_META_KEY, false ) as $user_id ) {
			if ( Moderation::is_actor_blocked( $target_id, (int) $user_id ) ) {
				Followers::remove( $target_post_id, (int) $user_id );
			}
		}

		foreach ( $refollow as $user_id ) {
			// After a swap the post still says the user follows; the new server does not know that yet.
			Following::reject( $target_post_id, (int) $user_id );

			// A user who blocks the new actor does not follow it; follow() refuses users who can no longer follow.
			if ( ! Moderation::is_actor_blocked( $target_id, (int) $user_id ) ) {
				Following::follow( $target_post_id, (int) $user_id );
			}
		}

		/**
		 * Fires after an ActivityPub Move activity has been handled.
		 *
		 * @param array $activity The ActivityPub activity data.
		 * @param int[] $user_ids The local user IDs.
		 * @param bool  $success  Always true; a Move that is not handled returns before this action.
		 * @param mixed $result   The moved actor's post ID, or the result of deleting the merged old actor.
		 */
		\do_action( 'activitypub_handled_move', $activity, (array) $user_ids, true, $result );
	}

	/**
	 * Extract the target from the activity.
	 *
	 * FEP-7628 and Mastodon send the new actor as `target`. Without a `target`,
	 * the `object` is read as the new actor, the way older senders sent it.
	 *
	 * @param array $activity The JSON "Move" Activity.
	 *
	 * @return string|null The target URI or null if not found.
	 */
	private static function extract_target( $activity ) {
		if ( ! empty( $activity['target'] ) ) {
			return object_to_uri( $activity['target'] );
		}

		if ( ! empty( $activity['object'] ) ) {
			return object_to_uri( $activity['object'] );
		}

		return null;
	}

	/**
	 * Extract the origin from the activity.
	 *
	 * FEP-7628 and Mastodon send the old actor as `object` and the new one as `target`; the
	 * `actor` is whoever sends the Move, the old actor (push) or the new one (pull). Without a
	 * `target`, the `object` is the target, so the origin is the `origin` or the `actor`.
	 *
	 * @param array $activity The JSON "Move" Activity.
	 *
	 * @return string|null The origin URI or null if not found.
	 */
	private static function extract_origin( $activity ) {
		if ( ! empty( $activity['target'] ) && ! empty( $activity['object'] ) ) {
			return object_to_uri( $activity['object'] );
		}

		if ( ! empty( $activity['origin'] ) ) {
			return object_to_uri( $activity['origin'] );
		}

		if ( ! empty( $activity['actor'] ) ) {
			return object_to_uri( $activity['actor'] );
		}

		return null;
	}

	/**
	 * Verify the move.
	 *
	 * Both actors have to agree, each in its own document: the target lists the origin in
	 * `alsoKnownAs`, and the origin points at the target with `movedTo`. A `copiedTo` origin
	 * stays active (FEP-7628), so it is not merged away.
	 *
	 * @param array           $activity      The JSON "Move" Activity.
	 * @param array|\WP_Error $target_object The target object.
	 * @param array|\WP_Error $origin_object The origin object.
	 *
	 * @return bool True if the move is verified, false otherwise.
	 */
	private static function verify_move( $activity, $target_object, $origin_object ) {
		// Check if both objects are actors.
		if ( ! \is_array( $target_object ) || ! \is_array( $origin_object ) || ! is_actor( $target_object ) || ! is_actor( $origin_object ) ) {
			return false;
		}

		if ( ! \is_string( $target_object['id'] ?? null ) || ! \is_string( $origin_object['id'] ?? null ) ) {
			return false;
		}

		// Check if the target and origin are not the same.
		if ( $target_object['id'] === $origin_object['id'] ) {
			return false;
		}

		// Only one of the two actors may send the Move.
		if ( ! \in_array( object_to_uri( $activity['actor'] ?? null ), array( $origin_object['id'], $target_object['id'] ), true ) ) {
			return false;
		}

		// Normalize alsoKnownAs to a list of ids (some JSON-LD payloads use a string or objects).
		$also_known_as = \array_map( '\Activitypub\object_to_uri', (array) ( $target_object['alsoKnownAs'] ?? array() ) );
		if ( empty( $also_known_as ) ) {
			return false;
		}

		/*
		 * The target must list the origin by its id, which the fetch confirmed. The origin's url and
		 * webfinger are its own claims: matching them would let an actor claim an alias of the
		 * account it pretends to move to. Mastodon compares the id only as well.
		 */
		if ( ! \in_array( $origin_object['id'], $also_known_as, true ) ) {
			return false;
		}

		// Check if the origin points at the target.
		return object_to_uri( $origin_object['movedTo'] ?? null ) === $target_object['id'];
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
}
