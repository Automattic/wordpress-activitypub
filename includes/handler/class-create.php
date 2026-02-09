<?php
/**
 * Create handler file.
 *
 * @package Activitypub
 */

namespace Activitypub\Handler;

use Activitypub\Collection\Interactions;
use Activitypub\Collection\Posts;
use Activitypub\Tombstone;

use function Activitypub\get_activity_visibility;
use function Activitypub\is_activity_reply;
use function Activitypub\is_quote_activity;
use function Activitypub\is_self_ping;
use function Activitypub\object_id_to_comment;

/**
 * Handle Create requests.
 */
class Create {
	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		\add_action( 'activitypub_handled_inbox_create', array( self::class, 'handle_create' ), 10, 3 );
		\add_filter( 'activitypub_validate_object', array( self::class, 'validate_object' ), 10, 3 );
		\add_action( 'post_activitypub_add_to_outbox', array( self::class, 'maybe_unbury' ), 10, 2 );
	}

	/**
	 * Handles "Create" requests.
	 *
	 * @param array                          $activity        The activity-object.
	 * @param int|int[]                      $user_ids        The id(s) of the local blog-user(s).
	 * @param \Activitypub\Activity\Activity $activity_object Optional. The activity object. Default null.
	 */
	public static function handle_create( $activity, $user_ids, $activity_object = null ) {
		// Check for private and/or direct messages.
		if ( ACTIVITYPUB_CONTENT_VISIBILITY_PRIVATE === get_activity_visibility( $activity ) ) {
			$result = false;
		} elseif ( is_activity_reply( $activity ) || is_quote_activity( $activity ) ) { // Check for replies and quotes.
			$result = self::create_interaction( $activity, $user_ids, $activity_object );
		} else { // Handle non-interaction objects.
			$result = self::create_post( $activity, $user_ids, $activity_object );
		}

		if ( false === $result ) {
			return;
		}

		$success = ! \is_wp_error( $result );

		/**
		 * Fires after an ActivityPub Create activity has been handled.
		 *
		 * @param array                          $activity The ActivityPub activity data.
		 * @param int[]                          $user_ids The local user IDs.
		 * @param bool                           $success  True on success, false otherwise.
		 * @param \WP_Comment|\WP_Post|\WP_Error $result   The WP_Comment object of the created comment, or null if creation failed.
		 */
		\do_action( 'activitypub_handled_create', $activity, (array) $user_ids, $success, $result );
	}

	/**
	 * Handle interactions like replies.
	 *
	 * @param array                          $activity        The activity-object.
	 * @param int[]                          $user_ids        The ids of the local blog-users.
	 * @param \Activitypub\Activity\Activity $activity_object Optional. The activity object. Default null.
	 *
	 * @return \WP_Comment|\WP_Error|false The created comment, WP_Error on failure, false if already exists or not processed.
	 */
	public static function create_interaction( $activity, $user_ids, $activity_object = null ) {
		$existing_comment = object_id_to_comment( $activity['object']['id'] );

		// If comment exists, call update action.
		if ( $existing_comment ) {
			Update::handle_update( $activity, (array) $user_ids, $activity_object );

			return false;
		}

		if ( is_self_ping( $activity['object']['id'] ) ) {
			return false;
		}

		$result = Interactions::add_comment( $activity );

		if ( ! $result || \is_wp_error( $result ) ) {
			return $result;
		}

		return \get_comment( $result );
	}

	/**
	 * Handle non-interaction posts like posts.
	 *
	 * @param array                          $activity        The activity-object.
	 * @param int[]                          $user_ids        The ids of the local blog-users.
	 * @param \Activitypub\Activity\Activity $activity_object Optional. The activity object. Default null.
	 *
	 * @return \WP_Post|\WP_Error|false The post on success, WP_Error on failure, false if already exists.
	 */
	public static function create_post( $activity, $user_ids, $activity_object = null ) {
		if ( ! \get_option( 'activitypub_create_posts', false ) ) {
			return false;
		}

		$existing_post = Posts::get_by_guid( $activity['object']['id'] );

		// If post exists, call update action.
		if ( $existing_post instanceof \WP_Post ) {
			Update::handle_update( $activity, (array) $user_ids, $activity_object );

			return false;
		}

		return Posts::add( $activity, $user_ids );
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

		if ( 'Create' !== $activity['type'] ) {
			return $valid;
		}

		if ( ! isset( $activity['object'] ) || ! \is_array( $activity['object'] ) ) {
			return false;
		}

		if ( ! isset( $activity['object']['id'], $activity['object']['content'] ) ) {
			return false;
		}

		return $valid;
	}

	/**
	 * Remove a URL from the tombstone registry when a Create or Update activity is sent.
	 *
	 * This handles the case where a post was soft-deleted (visibility changed to local/private)
	 * and then later changed back to public. The Create/Update activity indicates the post is being
	 * re-federated, so we remove it from the tombstone registry.
	 *
	 * @param int                            $outbox_id The ID of the outbox activity.
	 * @param \Activitypub\Activity\Activity $activity  The Activity object.
	 */
	public static function maybe_unbury( $outbox_id, $activity ) {
		if ( ! in_array( $activity->get_type(), array( 'Create', 'Update' ), true ) ) {
			return;
		}

		$object = $activity->get_object();

		if ( $object ) {
			Tombstone::remove( $object->get_id(), $object->get_url() );
		}
	}
}
