<?php
/**
 * Outbox Follow handler file.
 *
 * @package Activitypub
 */

namespace Activitypub\Handler\Outbox;

use Activitypub\Collection\Following;
use Activitypub\Collection\Remote_Actors;

/**
 * Handle outgoing Follow activities.
 */
class Follow {
	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		\add_filter( 'activitypub_outbox_follow', array( self::class, 'handle_follow' ), 10, 2 );
	}

	/**
	 * Handle outgoing "Follow" activities from local actors.
	 *
	 * Adds the target actor to the user's following list (pending until accepted).
	 *
	 * @param array $data    The activity data array.
	 * @param int   $user_id The user ID.
	 */
	public static function handle_follow( $data, $user_id = null ) {
		$object = $data['object'] ?? '';

		// The object should be the actor URL to follow.
		if ( empty( $object ) || ! \is_string( $object ) ) {
			return;
		}

		// Fetch or create the remote actor.
		$remote_actor = Remote_Actors::fetch_by_uri( $object );

		if ( \is_wp_error( $remote_actor ) ) {
			return;
		}

		// Check if already following.
		$all_meta  = \get_post_meta( $remote_actor->ID );
		$following = $all_meta[ Following::FOLLOWING_META_KEY ] ?? array();
		$pending   = $all_meta[ Following::PENDING_META_KEY ] ?? array();

		if ( \in_array( (string) $user_id, $following, true ) || \in_array( (string) $user_id, $pending, true ) ) {
			return;
		}

		// Add to pending following.
		\add_post_meta( $remote_actor->ID, Following::PENDING_META_KEY, (string) $user_id );

		/**
		 * Fires after an outgoing Follow activity has been processed.
		 *
		 * @param int   $remote_actor_id The remote actor post ID.
		 * @param array $data            The activity data.
		 * @param int   $user_id         The user ID.
		 */
		\do_action( 'activitypub_outbox_follow_sent', $remote_actor->ID, $data, $user_id );
	}
}
