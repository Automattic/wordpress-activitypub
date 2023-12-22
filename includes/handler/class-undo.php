<?php
namespace Activitypub\Handler;

use Activitypub\Collection\Users;
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
			array( self::class, 'handle_undo' )
		);
	}

	/**
	 * Handle "Unfollow" requests
	 *
	 * @param array $activity The JSON "Undo" Activity
	 * @param int   $user_id  The ID of the ID of the WordPress User
	 */
	public static function handle_undo( $activity ) {
		if (
			isset( $activity['object']['type'] ) &&
			'Follow' === $activity['object']['type'] &&
			isset( $activity['object']['object'] ) &&
			filter_var( $activity['object']['object'], FILTER_VALIDATE_URL )
		) {
			$user = Users::get_by_resource( $activity['object']['object'] );

			if ( ! $user || is_wp_error( $user ) ) {
				// If we can not find a user,
				// we can not initiate a follow process
				return;
			}

			$user_id = $user->get__id();

			Followers::remove_follower( $user_id, $activity['actor'] );
		}
	}
}
