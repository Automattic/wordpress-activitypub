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
		// Inbox handler with middleware to filter shared inbox requests.
		\add_action( 'activitypub_inbox', array( self::class, 'maybe_handle_inbox_request' ), 10, 5 );

		// Shared inbox handler (processes directly without filtering).
		\add_action( 'activitypub_inbox_shared', array( self::class, 'handle_inbox_requests' ), 10, 5 );
	}

	/**
	 * Maybe handle inbox request based on context.
	 *
	 * Only processes requests with 'inbox' context to prevent duplicate processing
	 * when shared inbox fires both `activitypub_inbox_shared` and `activitypub_inbox`.
	 *
	 * @param array              $data     The data array.
	 * @param int|array          $user_ids The id of the local blog-user, or array of user IDs.
	 * @param string             $type     The type of the activity.
	 * @param Activity|\WP_Error $activity The Activity object.
	 * @param string             $context  The context of the request.
	 */
	public static function maybe_handle_inbox_request( $data, $user_ids, $type, $activity, $context = Inbox_Collection::CONTEXT_INBOX ) {
		// Ignore shared inbox requests to prevent duplicate processing.
		if ( Inbox_Collection::CONTEXT_SHARED_INBOX === $context ) {
			return;
		}

		// Process inbox requests.
		self::handle_inbox_requests( $data, $user_ids, $type, $activity, $context );
	}

	/**
	 * Handles "Inbox" requests.
	 *
	 * Supports both single user_id (int) and multiple user_ids (array).
	 *
	 * @param array              $data         The data array.
	 * @param int|array          $user_ids     The id of the local blog-user, or array of user IDs.
	 * @param string             $type         The type of the activity.
	 * @param Activity|\WP_Error $activity     The Activity object.
	 * @param string             $context      The context of the request (Inbox_Collection::CONTEXT_INBOX or Inbox_Collection::CONTEXT_SHARED_INBOX).
	 */
	public static function handle_inbox_requests( $data, $user_ids, $type, $activity, $context = Inbox_Collection::CONTEXT_INBOX ) {
		/**
		 * Filter to skip inbox storage.
		 *
		 * Skip inbox storage for debugging purposes or to reduce load for
		 * certain Activity-Types, like "Delete".
		 *
		 * @param bool  $skip Whether to skip inbox storage.
		 * @param array $data  The activity data array.
		 *
		 * @return bool Whether to skip inbox storage.
		 */
		$skip = \apply_filters( 'activitypub_skip_inbox_storage', false, $data );

		if ( $skip ) {
			return;
		}

		$result = Inbox_Collection::add( $activity, $user_ids );

		// Normalize user_id to array for action hooks.
		$user_ids = (array) $user_ids;

		/**
		 * Fires after an ActivityPub Inbox activity has been handled.
		 *
		 * @param array              $data     The data array.
		 * @param array              $user_ids The user IDs.
		 * @param string             $type     The type of the activity.
		 * @param Activity|\WP_Error $activity The Activity object.
		 * @param \WP_Error|int      $result   The ID of the inbox item that was created, or WP_Error if failed.
		 * @param string             $context  The context of the request ('inbox' or 'shared_inbox').
		 */
		\do_action( 'activitypub_handled_inbox', $data, $user_ids, $type, $activity, $result, $context );

		/**
		 * Fires after an ActivityPub Inbox activity has been handled.
		 *
		 * @param array              $data     The data array.
		 * @param array              $user_ids The user IDs.
		 * @param Activity|\WP_Error $activity The Activity object.
		 * @param \WP_Error|int      $result   The ID of the inbox item that was created, or WP_Error if failed.
		 * @param string             $context  The context of the request ('inbox' or 'shared_inbox').
		 */
		\do_action( 'activitypub_handled_inbox_' . $type, $data, $user_ids, $activity, $result, $context );
	}
}
