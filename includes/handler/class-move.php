<?php
/**
 * Move handler file.
 *
 * @package Activitypub
 */

namespace Activitypub\Handler;

use Activitypub\Collection\Inbox;
use Activitypub\Collection\Remote_Actors;
use Activitypub\Moderation;
use Activitypub\Move as Account_Move;
use Activitypub\Proxy;

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

		// A remote actor can not move to one of ours, nor to an actor the site blocks.
		if ( is_same_domain( $target_json['id'] ) || Moderation::is_actor_blocked( $target_json['id'] ) ) {
			return;
		}

		$result = Account_Move::remote( $origin_object, $target_json );

		if ( \is_wp_error( $result ) ) {
			return;
		}

		/**
		 * Fires after an ActivityPub Move activity has been handled.
		 *
		 * @param array $activity The ActivityPub activity data.
		 * @param int[] $user_ids The local user IDs.
		 * @param bool  $success  Always true; a Move that is not handled returns before this action.
		 * @param int   $result   The post ID of the new actor.
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
}
