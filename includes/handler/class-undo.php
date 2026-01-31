<?php
/**
 * Undo handler file.
 *
 * @package Activitypub
 */

namespace Activitypub\Handler;

use Activitypub\Collection\Following;
use Activitypub\Collection\Inbox as Inbox_Collection;
use Activitypub\Collection\Remote_Actors;

use function Activitypub\object_to_uri;

/**
 * Handle Undo requests.
 */
class Undo {
	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		\add_action( 'activitypub_inbox_undo', array( self::class, 'handle_undo' ), 10, 2 );
		\add_action( 'activitypub_handled_outbox_undo', array( self::class, 'handle_outbox_undo' ), 10, 4 );
		\add_action( 'activitypub_validate_object', array( self::class, 'validate_object' ), 10, 3 );
	}

	/**
	 * Handle "Unfollow" requests.
	 *
	 * @param array          $activity The JSON "Undo" Activity.
	 * @param int|int[]|null $user_ids The user ID(s).
	 */
	public static function handle_undo( $activity, $user_ids ) {
		$success = false;
		$result  = Inbox_Collection::undo( object_to_uri( $activity['object'] ) );

		if ( $result && ! \is_wp_error( $result ) ) {
			$success = true;
		}

		/**
		 * Fires after an ActivityPub Undo activity has been handled.
		 *
		 * @param array              $activity The ActivityPub activity data.
		 * @param int[]              $user_ids The local user IDs.
		 * @param bool               $success  True on success, false on failure.
		 * @param \WP_Comment|string $result   The target, based on the activity that is being undone.
		 */
		\do_action( 'activitypub_handled_undo', $activity, (array) $user_ids, $success, $result );
	}

	/**
	 * Validate the object.
	 *
	 * @param bool             $valid   The validation state.
	 * @param string           $param   The object parameter.
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @return bool The validation state: true if valid, false if not.
	 */
	public static function validate_object( $valid, $param, $request ) {
		$activity = $request->get_json_params();

		if ( empty( $activity['type'] ) ) {
			return false;
		}

		if ( 'Undo' !== $activity['type'] ) {
			return $valid;
		}

		if ( ! isset( $activity['actor'], $activity['object'] ) ) {
			return false;
		}

		if ( ! \is_array( $activity['object'] ) && ! \is_string( $activity['object'] ) ) {
			return false;
		}

		if ( \is_array( $activity['object'] ) && ! isset( $activity['object']['id'] ) ) {
			return false;
		}

		return $valid;
	}

	/**
	 * Handle outbox "Undo" activities (C2S).
	 *
	 * Handles Undo Follow (unfollow) activities.
	 *
	 * @param array                          $data       The activity data array.
	 * @param int                            $user_id    The user ID.
	 * @param \Activitypub\Activity\Activity $activity   The Activity object.
	 * @param int                            $outbox_id  The outbox post ID.
	 */
	public static function handle_outbox_undo( $data, $user_id, $activity, $outbox_id ) {
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
		 * Fires after an Undo Follow activity has been sent via C2S.
		 *
		 * @param int   $remote_actor_id The remote actor post ID.
		 * @param array $data            The activity data.
		 * @param int   $user_id         The user ID.
		 * @param int   $outbox_id       The outbox post ID.
		 */
		\do_action( 'activitypub_outbox_undo_follow_sent', $remote_actor->ID, $data, $user_id, $outbox_id );
	}
}
