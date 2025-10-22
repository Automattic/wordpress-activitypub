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
	 * Track which activities have been processed by the shared handler
	 * to prevent double-processing in the legacy handler.
	 *
	 * @var array
	 */
	private static $processed_activities = array();

	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		// Check if inbox collection persistence is enabled.
		if ( \get_option( 'activitypub_persist_inbox', '0' ) ) {
			// NEW: Shared inbox handler (processes once for all recipients).
			\add_action( 'activitypub_inbox_shared', array( self::class, 'handle_inbox_requests_shared' ), 10, 4 );

			// LEGACY: Per-user inbox handler (for backward compatibility).
			\add_action( 'activitypub_inbox', array( self::class, 'handle_inbox_requests' ), 10, 4 );
		}
	}

	/**
	 * Handles shared inbox requests with multiple recipients.
	 *
	 * This is the preferred handler that processes activities once for all recipients.
	 *
	 * @since unreleased
	 *
	 * @param array              $data       The data array.
	 * @param array              $recipients Array of user IDs.
	 * @param string             $type       The type of the activity.
	 * @param Activity|\WP_Error $activity   The Activity object.
	 */
	public static function handle_inbox_requests_shared( $data, $recipients, $type, $activity ) {
		$success = true;

		/**
		 * Filters the activity types to persist in the inbox.
		 *
		 * @param array $activity_types The activity types to persist in the inbox.
		 */
		$activity_types = \apply_filters( 'activitypub_persist_inbox_activity_types', array( 'Create', 'Update', 'Follow', 'Like', 'Announce' ) );
		$activity_types = \array_map( 'Activitypub\camel_to_snake_case', $activity_types );

		if ( ! \in_array( \strtolower( $type ), $activity_types, true ) ) {
			$success = false;
			$id      = new \WP_Error( 'activitypub_inbox_ignored', 'Activity type not configured to be persisted in inbox.' );
		}

		if ( $success ) {
			/**
			 * Filters the object types to persist in the inbox.
			 *
			 * @param array $object_types The object types to persist in the inbox.
			 */
			$object_types = \apply_filters( 'activitypub_persist_inbox_object_types', Base_Object::TYPES );
			$object_types = \array_map( 'Activitypub\camel_to_snake_case', $object_types );

			if ( is_array( $data['object'] ) && ( empty( $data['object']['type'] ) || ! \in_array( \strtolower( $data['object']['type'] ), $object_types, true ) ) ) {
				$success = false;
				$id      = new \WP_Error( 'activitypub_inbox_ignored', 'Activity type not configured to be persisted in inbox.' );
			}
		}

		if ( $success ) {
			// Add with array of recipients (deduplicated storage).
			$id = Inbox_Collection::add( $activity, $recipients );

			// Mark as processed to prevent double-processing in legacy handler.
			if ( $activity && ! \is_wp_error( $activity ) ) {
				$activity_id = $activity->get_id();
				if ( $activity_id ) {
					self::$processed_activities[ $activity_id ] = true;
				}
			}
		}

		/**
		 * Fires after an ActivityPub shared inbox activity has been handled.
		 *
		 * @since unreleased
		 *
		 * @param array         $data       The ActivityPub activity data.
		 * @param array         $recipients Array of user IDs.
		 * @param bool          $success    True on success, false otherwise.
		 * @param \WP_Error|int $id         The ID of the inbox item that was created, or WP_Error if failed.
		 */
		\do_action( 'activitypub_handled_inbox_shared', $data, $recipients, $success, $id );
	}

	/**
	 * Handles "Inbox" requests (legacy per-user handler).
	 *
	 * @deprecated Use activitypub_inbox_shared hook handler instead.
	 *
	 * @param array              $data     The data array.
	 * @param int                $user_id  The id of the local blog-user.
	 * @param string             $type     The type of the activity.
	 * @param Activity|\WP_Error $activity The Activity object.
	 */
	public static function handle_inbox_requests( $data, $user_id, $type, $activity ) {
		// Check if this activity was already processed by the shared handler.
		if ( $activity && ! \is_wp_error( $activity ) ) {
			$activity_id = $activity->get_id();
			if ( $activity_id && isset( self::$processed_activities[ $activity_id ] ) ) {
				// Already processed by shared handler, skip to avoid duplication.
				return;
			}
		}

		$success = true;

		/**
		 * Filters the activity types to persist in the inbox.
		 *
		 * @param array $activity_types The activity types to persist in the inbox.
		 */
		$activity_types = \apply_filters( 'activitypub_persist_inbox_activity_types', array( 'Create', 'Update', 'Follow', 'Like', 'Announce' ) );
		$activity_types = \array_map( 'Activitypub\camel_to_snake_case', $activity_types );

		if ( ! \in_array( \strtolower( $type ), $activity_types, true ) ) {
			$success = false;
			$id      = new \WP_Error( 'activitypub_inbox_ignored', 'Activity type not configured to be persisted in inbox.' );
		}

		if ( $success ) {
			/**
			 * Filters the object types to persist in the inbox.
			 *
			 * @param array $object_types The object types to persist in the inbox.
			 */
			$object_types = \apply_filters( 'activitypub_persist_inbox_object_types', Base_Object::TYPES );
			$object_types = \array_map( 'Activitypub\camel_to_snake_case', $object_types );

			if ( is_array( $data['object'] ) && ( empty( $data['object']['type'] ) || ! \in_array( \strtolower( $data['object']['type'] ), $object_types, true ) ) ) {
				$success = false;
				$id      = new \WP_Error( 'activitypub_inbox_ignored', 'Activity type not configured to be persisted in inbox.' );
			}
		}

		if ( $success ) {
			$id = Inbox_Collection::add( $activity, $user_id );
		}

		/**
		 * Fires after an ActivityPub Inbox activity has been handled.
		 *
		 * @param array         $data    The ActivityPub activity data.
		 * @param int           $user_id The local user ID.
		 * @param bool          $success True on success, false otherwise.
		 * @param \WP_Error|int $id      The ID of the inbox item that was created, or WP_Error if failed.
		 */
		\do_action( 'activitypub_handled_inbox', $data, $user_id, $success, $id );
	}
}
