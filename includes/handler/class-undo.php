<?php
namespace Activitypub\Handler;

use Activitypub\Collection\Followers;

/**
 * Handle Undo requests
 */
class Undo {
	/**
	 * Initialize the class, registering WordPress hooks
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
	 * Handle "Unfollow" requests
	 *
	 * @param array $activity The JSON "Undo" Activity
	 * @param int   $user_id  The ID of the ID of the WordPress User
	 */
	public static function handle_undo( $activity, $user_id ) {
		if (
			isset( $activity['object']['type'] ) &&
			'Follow' === $activity['object']['type']
		) {
			// If user ID is not set, try to get it from the activity
			if (
				! $user_id &&
				isset( $activity['object']['object'] ) &&
				filter_var( $activity['object']['object'], FILTER_VALIDATE_URL )
			) {
				$user    = Users::get_by_resource( $activity['object']['object'] );
				$user_id = $user->get__id();
			}

			if ( ! $user_id ) {
				// If we can not find a user,
				// we can not initiate an undo follow process
				return;
			}

			Followers::remove_follower( $user_id, $activity['actor'] );
		}
	}
}
