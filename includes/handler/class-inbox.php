<?php
/**
 * Inbox handler file.
 *
 * @package Activitypub
 */

namespace Activitypub\Handler;

use Activitypub\Activity\Activity;
use Activitypub\Activity\Base_Object;
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
		/**
		 * Filters the activity types to persist in the inbox.
		 *
		 * @param array $activity_types The activity types to persist in the inbox.
		 */
		$activity_types = \apply_filters( 'activitypub_persist_inbox_activity_types', array( 'Create', 'Update' ) );

		if ( ! in_array( $type, $activity_types, true ) ) {
			return;
		}

		/**
		 * Filters the object types to persist in the inbox.
		 *
		 * @param array $object_types The object types to persist in the inbox.
		 */
		$object_types = \apply_filters( 'activitypub_persist_inbox_object_types', Base_Object::TYPES );

		if ( isset( $data['object']['type'] ) && ! in_array( $data['object']['type'], $object_types, true ) ) {
			return;
		}

		$id = Inbox_Collection::add( $activity, $user_id );

		/**
		 * Fires after a new follower has been added.
		 *
		 * @param array              $data     The data array.
		 * @param int                $user_id  The ID of the local blog user.
		 * @param \WP_Error|int      $id       The ID of the inbox item.
		 * @param Activity|\WP_Error $activity The Activity object.
		 */
		\do_action( 'activitypub_handled_inbox', $data, $user_id, $id, $activity );
	}
}
