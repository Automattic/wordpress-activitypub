<?php
/**
 * Undo handler file.
 *
 * @package Activitypub
 */

namespace Activitypub\Handler;

use Activitypub\Collection\Inbox as Inbox_Collection;

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
		\add_action( 'activitypub_validate_object', array( self::class, 'validate_object' ), 10, 3 );
	}

	/**
	 * Handle "Unfollow" requests.
	 *
	 * @param array    $activity The JSON "Undo" Activity.
	 * @param int|null $user_id  The ID of the user who initiated the "Undo" activity.
	 */
	public static function handle_undo( $activity, $user_id ) {
		$success = false;
		$result  = Inbox_Collection::undo( object_to_uri( $activity['object'] ) );

		if ( $result && ! \is_wp_error( $result ) ) {
			$success = true;
		}

		/**
		 * Fires after an ActivityPub Undo activity has been handled.
		 *
		 * @param array              $activity The ActivityPub activity data.
		 * @param int                $user_id  The local user ID.
		 * @param bool               $success  True on success, false on failure.
		 * @param \WP_Comment|string $result   The target, based on the activity that is being undone.
		 */
		\do_action( 'activitypub_handled_undo', $activity, $user_id, $success, $result );
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
}
