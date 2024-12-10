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
		\add_action(
			'activitypub_inbox_undo',
			array( self::class, 'handle_undo' ),
			10,
			2
		);
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

		$type  = $activity['object']['type'];
		$state = false;

		// Handle "Unfollow" requests.
		if ( 'Follow' === $type ) {
			$id   = object_to_uri( $activity['object']['object'] );
			$user = Actors::get_by_resource( $id );

			if ( ! $user || is_wp_error( $user ) ) {
				// If we can not find a user, we can not initiate a follow process.
				return;
			}

			$user_id = $user->get__id();
			$actor   = object_to_uri( $activity['actor'] );

			$state = Followers::remove_follower( $user_id, $actor );
		}

		// Handle "Undo" requests for "Like" and "Create" activities.
		if ( in_array( $type, array( 'Like', 'Create', 'Announce' ), true ) ) {
			if ( ACTIVITYPUB_DISABLE_INCOMING_INTERACTIONS ) {
				return;
			}

			$object_id = object_to_uri( $activity['object'] );
			$comment   = Comment::object_id_to_comment( esc_url_raw( $object_id ) );

			if ( empty( $comment ) ) {
				return;
			}

			$state = wp_trash_comment( $comment );
		}

		/**
		 * Fires after an "Undo" activity has been handled.
		 *
		 * @param array    $activity The JSON "Undo" Activity.
		 * @param int|null $user_id  The ID of the user who initiated the "Undo" activity otherwise null.
		 * @param mixed    $state    The state of the "Undo" activity.
		 */
		do_action( 'activitypub_handled_undo', $activity, $user_id, $state );
	}
}
