<?php
/**
 * Outbox Undo handler file.
 *
 * @package Activitypub
 */

namespace Activitypub\Handler\Outbox;

use Activitypub\Collection\Following;
use Activitypub\Collection\Remote_Actors;

/**
 * Handle outgoing Undo activities.
 */
class Undo {
	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		\add_filter( 'activitypub_outbox_undo', array( self::class, 'handle_undo' ), 10, 2 );
	}

	/**
	 * Handle outgoing "Undo" activities from local actors.
	 *
	 * Handles Undo Follow (unfollow) activities.
	 *
	 * @param array $data    The activity data array.
	 * @param int   $user_id The user ID.
	 */
	public static function handle_undo( $data, $user_id = null ) {
		$object = $data['object'] ?? array();

		if ( ! \is_array( $object ) ) {
			return;
		}

		$type = $object['type'] ?? '';

		// Only handle Undo Follow for now.
		if ( 'Follow' !== $type ) {
			return;
		}

		// Get the target actor from the original Follow activity.
		$target = $object['object'] ?? '';

		if ( empty( $target ) || ! \is_string( $target ) ) {
			return;
		}

		// Get the remote actor.
		$remote_actor = Remote_Actors::get_by_uri( $target );

		if ( \is_wp_error( $remote_actor ) ) {
			return;
		}

		// Remove following relationship.
		\delete_post_meta( $remote_actor->ID, Following::FOLLOWING_META_KEY, $user_id );
		\delete_post_meta( $remote_actor->ID, Following::PENDING_META_KEY, $user_id );

		/**
		 * Fires after an outgoing Undo Follow activity has been processed.
		 *
		 * @param int   $remote_actor_id The remote actor post ID.
		 * @param array $data            The activity data.
		 * @param int   $user_id         The user ID.
		 */
		\do_action( 'activitypub_outbox_undo_follow_sent', $remote_actor->ID, $data, $user_id );
	}
}
