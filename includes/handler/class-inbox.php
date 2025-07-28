<?php
/**
 * Inbox handler file.
 *
 * @package Activitypub
 */

namespace Activitypub\Handler;

use Activitypub\Activity\Activity;
use Activitypub\Collection\Inbox as Inbox_Collection;

/**
 * Handle Inbox requests.
 */
class Inbox {
	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		// Check if inbox collection persistence is enabled.
		if ( \get_option( 'activitypub_persist_inbox', '0' ) ) {
			\add_action(
				'activitypub_inbox',
				array( self::class, 'handle_inbox_requests' ),
				10,
				4
			);
		}
	}

	/**
	 * Handles "Inbox" requests.
	 *
	 * @param array              $data     The data array.
	 * @param int                $user_id  The id of the local blog-user.
	 * @param string             $type     The type of the activity.
	 * @param Activity|\WP_Error $activity The Activity object.
	 */
	public static function handle_inbox_requests( $data, $user_id, $type, $activity ) {
		// Start with only storing Create and Update activities.
		if ( ! in_array( strtolower( $type ), array( 'create', 'update' ), true ) ) {
			return;
		}

		Inbox_Collection::add( $activity, $user_id );
	}
}
