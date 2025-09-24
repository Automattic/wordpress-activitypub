<?php
/**
 * Undo handler file.
 *
 * @package Activitypub
 */

namespace Activitypub\Handler;

use Activitypub\Collection\Actors;
use Activitypub\Collection\Followers;
use Activitypub\Comment;

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
	}

	/**
	 * Handle "Unfollow" requests.
	 *
	 * @param array    $activity The JSON "Undo" Activity.
	 * @param int|null $user_id  The ID of the user who initiated the "Undo" activity.
	 */
	public static function handle_undo( $activity, $user_id ) {
		if (
			! isset( $activity['object']['type'] ) ||
			! isset( $activity['object']['object'] )
		) {
			return;
		}

		$type    = $activity['object']['type'];
		$success = false;
		$result  = null;

		// Handle "Unfollow" requests.
		if ( 'Follow' === $type ) {
			$user_id = Actors::get_id_by_resource( object_to_uri( $activity['object']['object'] ) );

			if ( \is_wp_error( $user_id ) ) {
				// If we can not find a user, we can not initiate a follow process.
				return;
			}

			$result  = object_to_uri( $activity['actor'] );
			$success = Followers::remove_follower( $user_id, $result );
		}

		// Handle "Undo" requests for "Like" and "Create" activities.
		if ( in_array( $type, array( 'Like', 'Create', 'Announce' ), true ) ) {
			if ( ACTIVITYPUB_DISABLE_INCOMING_INTERACTIONS ) {
				return;
			}

			$object_id = object_to_uri( $activity['object'] );
			$result    = Comment::object_id_to_comment( esc_url_raw( $object_id ) );

			if ( empty( $result ) ) {
				return;
			}

			$success = \wp_delete_comment( $result, true );
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
}
