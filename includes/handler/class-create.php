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
		\add_action( 'activitypub_handled_inbox_create', array( self::class, 'handle_create' ), 10, 2 );

		\add_filter( 'activitypub_validate_object', array( self::class, 'validate_object' ), 10, 3 );
		\add_action( 'post_activitypub_add_to_outbox', array( self::class, 'maybe_unbury' ), 10, 2 );
	}

	/**
	 * Handle incoming "Create" activities from remote actors.
	 *
	 * @param array $activity The activity data.
	 * @param int[] $user_ids The local user IDs targeted.
	 *
	 * @return \WP_Post|\WP_Comment|\WP_Error|false The created content or error.
	 */
	public static function handle_create( $activity, $user_ids = null ) {
		// Check for private and/or direct messages.
		if ( ACTIVITYPUB_CONTENT_VISIBILITY_PRIVATE === get_activity_visibility( $activity ) ) {
			return false;
		}

		// Route to appropriate handler based on content type.
		if ( is_activity_reply( $activity ) || is_quote_activity( $activity ) ) {
			$result = self::create_interaction( $activity, $user_ids );
		} else {
			$result = self::create_post( $activity, $user_ids );
		}

		if ( false === $result ) {
			return $result;
		}

		$success = ! \is_wp_error( $result );

		/**
		 * Fires after an incoming ActivityPub Create activity has been handled.
		 *
		 * @param array                          $activity The ActivityPub activity data.
		 * @param int[]                          $user_ids The local user IDs.
		 * @param bool                           $success  True on success, false otherwise.
		 * @param \WP_Comment|\WP_Post|\WP_Error $result   The created content or error.
		 */
		\do_action( 'activitypub_handled_create', $activity, (array) $user_ids, $success, $result );

		return $result;
	}

	/**
	 * Handle incoming interaction (reply/quote) from remote actor.
	 *
	 * @param array $activity The activity data.
	 * @param int[] $user_ids The local user IDs targeted.
	 *
	 * @return \WP_Comment|\WP_Error|false Comment, WP_Error, or false.
	 */
	public static function create_interaction( $activity, $user_ids ) {
		$existing_comment = object_id_to_comment( $activity['object']['id'] );

		// If comment exists, call update action.
		if ( $existing_comment ) {
			Update::handle_update( $activity, (array) $user_ids, null );

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
	 * Handle incoming post from remote actor.
	 *
	 * @param array $activity The activity data.
	 * @param int[] $user_ids The local user IDs targeted.
	 *
	 * @return \WP_Post|\WP_Error|false Post, WP_Error, or false.
	 */
	public static function create_post( $activity, $user_ids ) {
		if ( ! \get_option( 'activitypub_create_posts', false ) ) {
			return false;
		}

		$existing_post = Posts::get_by_guid( $activity['object']['id'] );

		// If post exists, call update action.
		if ( $existing_post instanceof \WP_Post ) {
			Update::handle_update( $activity, (array) $user_ids, null );

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

		// Only content is required; ID is optional for outbox activities (assigned by the server).
		if ( ! isset( $activity['object']['content'] ) ) {
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
