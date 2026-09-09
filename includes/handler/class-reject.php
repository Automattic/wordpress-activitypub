<?php
/**
 * Reject handler file.
 *
 * @package Activitypub
 */

namespace Activitypub\Handler;

use Activitypub\Collection\Following;
use Activitypub\Collection\Outbox;
use Activitypub\Collection\Remote_Actors;

use function Activitypub\object_to_uri;

/**
 * Handle "Reject" requests.
 */
class Reject {
	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		\add_action( 'activitypub_inbox_reject', array( self::class, 'handle_reject' ), 10, 2 );
		\add_filter( 'activitypub_validate_object', array( self::class, 'validate_object' ), 10, 3 );
	}

	/**
	 * Handles "Reject" requests.
	 *
	 * @param array     $reject   The activity-object.
	 * @param int|int[] $user_ids The user ID(s).
	 */
	public static function handle_reject( $reject, $user_ids ) {
		// Validate that there is a preceding Activity.
		$outbox_post = Outbox::get_by_guid( $reject['object']['id'] );

		if ( \is_wp_error( $outbox_post ) ) {
			return;
		}

		// We currently only support reject for Follow activities. But we will support more in the future.
		switch ( \get_post_meta( $outbox_post->ID, '_activitypub_activity_type', true ) ) {
			case 'Follow':
				self::reject_follow( $reject, $user_ids );
				break;
			default:
				break;
		}
	}

	/**
	 * Reject a "Follow" request.
	 *
	 * @param array     $reject   The activity-object.
	 * @param int|int[] $user_ids The user ID(s).
	 */
	private static function reject_follow( $reject, $user_ids ) {
		/*
		 * For a Follow Reject, the sender must be the actor that was followed.
		 * Without this, a signed Reject from one actor could cancel a Follow that
		 * targeted another actor by referencing that pending Follow's outbox GUID.
		 */
		$reject_actor   = object_to_uri( $reject['actor'] ?? '' );
		$followed_actor = object_to_uri( $reject['object']['object'] ?? '' );
		if ( ! $reject_actor || ! $followed_actor || $reject_actor !== $followed_actor ) {
			return;
		}

		$actor_post = Remote_Actors::get_by_uri( $followed_actor );

		if ( \is_wp_error( $actor_post ) ) {
			return;
		}

		$user_id = \is_array( $user_ids ) ? \reset( $user_ids ) : $user_ids;
		$result  = Following::reject( $actor_post, $user_id );
		$success = ! \is_wp_error( $result );

		/**
		 * Fires after an ActivityPub Reject activity has been handled.
		 *
		 * @param array              $reject   The ActivityPub activity data.
		 * @param int[]              $user_ids The local user IDs.
		 * @param bool               $success  True on success, false otherwise.
		 * @param \WP_Post|\WP_Error $result   Actor post on success, WP_Error on failure.
		 */
		\do_action( 'activitypub_handled_reject', $reject, (array) $user_ids, $success, $result );
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

		if ( 'Reject' !== $activity['type'] ) {
			return $valid;
		}

		if ( ! isset( $activity['actor'], $activity['object'] ) ) {
			return false;
		}

		if ( ! \is_array( $activity['object'] ) ) {
			return false;
		}

		if ( ! isset( $activity['object']['id'], $activity['object']['type'], $activity['object']['actor'], $activity['object']['object'] ) ) {
			return false;
		}

		return $valid;
	}
}
