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
		\add_action( 'activitypub_inbox_undo', array( self::class, 'handle_undo' ), 10, 2 );
	}

	/**
	 * Handle "Unfollow" requests
	 *
	 * @param array $activity The JSON "Undo" Activity
	 * @param int   $user_id  The ID of the ID of the WordPress User
	 */
	public static function handle_undo( $activity, $user_id ) {
		if (
			isset( $activity['object'] ) &&
			isset( $activity['actor'] ) &&
			isset( $activity['object']['type'] ) &&
			'Follow' === $activity['object']['type']
		) {
			Followers::remove_follower( $user_id, $activity['actor'] );
		}
	}
}
