<?php
/**
 * Update handler file.
 *
 * @package Activitypub
 */

namespace Activitypub\Handler;

use Activitypub\Collection\Interactions;
use Activitypub\Collection\Remote_Actors;
use Activitypub\Collection\Remote_Posts;
use Activitypub\Http;

use function Activitypub\is_activity_reply;

/**
 * Handle Update requests.
 */
class Update {
	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		\add_action( 'activitypub_handled_inbox_update', array( self::class, 'handle_update' ), 10, 3 );
	}

	/**
	 * Handle "Update" requests.
	 *
	 * @param array                          $activity        The Activity object.
	 * @param int[]                          $user_ids        The user IDs. Always null for Update activities.
	 * @param \Activitypub\Activity\Activity $activity_object The activity object. Default null.
	 */
	public static function handle_update( $activity, $user_ids, $activity_object ) {
		$object_type = $activity['object']['type'] ?? '';

		switch ( $object_type ) {
			/*
			 * Actor Types.
			 *
			 * @see https://www.w3.org/TR/activitystreams-vocabulary/#actor-types
			 */
			case 'Person':
			case 'Group':
			case 'Organization':
			case 'Service':
			case 'Application':
				self::update_actor( $activity, $user_ids );
				break;

			/*
			 * Object and Link Types.
			 *
			 * @see https://www.w3.org/TR/activitystreams-vocabulary/#object-types
			 */
			case 'Note':
			case 'Article':
			case 'Image':
			case 'Audio':
			case 'Video':
			case 'Event':
			case 'Document':
				self::update_object( $activity, $user_ids, $activity_object );
				break;

			/*
			 * Minimal Activity.
			 *
			 * @see https://www.w3.org/TR/activitystreams-core/#example-1
			 */
			default:
				break;
		}
	}

	/**
	 * Update an Object.
	 *
	 * @param array                          $activity        The Activity object.
	 * @param int[]|null                     $user_ids        The user IDs. Always null for Update activities.
	 * @param \Activitypub\Activity\Activity $activity_object The activity object. Default null.
	 */
	public static function update_object( $activity, $user_ids, $activity_object ) {
		$result  = new \WP_Error( 'activitypub_update_failed', 'Update failed' );
		$updated = true;

		// Check for private and/or direct messages.
		if ( is_activity_reply( $activity ) ) {
			$comment_data = Interactions::update_comment( $activity );

			if ( false === $comment_data ) {
				$updated = false;
			} elseif ( ! empty( $comment_data['comment_ID'] ) ) {
				$result = \get_comment( $comment_data['comment_ID'] );
			}
		} elseif ( \get_option( 'activitypub_create_posts', false ) ) {
			$result = Remote_Posts::update( $activity, $user_ids );

			if ( \is_wp_error( $result ) && 'activitypub_post_not_found' === $result->get_error_code() ) {
				$updated = false;
			}
		}

		// There is no object to update, try to trigger create instead.
		if ( ! $updated ) {
			return Create::handle_create( $activity, $user_ids, $activity_object );
		}

		$success = ( $result && ! \is_wp_error( $result ) );

		/**
		 * Fires after an ActivityPub Update activity has been handled.
		 *
		 * @param array                          $activity The ActivityPub activity data.
		 * @param int[]|null                     $user_ids The local user IDs.
		 * @param bool                           $success  True on success, false otherwise.
		 * @param \WP_Comment|\WP_Post|\WP_Error $result   The updated post, comment, or error.
		 */
		\do_action( 'activitypub_handled_update', $activity, (array) $user_ids, $success, $result );
	}

	/**
	 * Update an Actor.
	 *
	 * @param array      $activity The Activity object.
	 * @param int[]|null $user_ids The user IDs. Always null for Update activities.
	 */
	public static function update_actor( $activity, $user_ids ) {
		/*
		 * Prefer the actor data embedded in the activity object, as it contains
		 * the fresh data sent by the remote server.
		 */
		$actor = $activity['object'] ?? null;

		/*
		 * The object may be a string IRI instead of an embedded object,
		 * in which case we need to fetch the actor data remotely.
		 * We use Http::get_remote_object() directly instead of
		 * get_remote_metadata_by_actor() because the latter returns the
		 * stale locally cached copy via fetch_by_uri().
		 */
		if ( ! \is_array( $actor ) || ! isset( $actor['id'] ) ) {
			$object = Http::get_remote_object( $activity['actor'], false );

			if ( ! \is_wp_error( $object ) && \is_array( $object ) ) {
				$actor = $object;
			}
		}

		if ( \is_array( $actor ) && isset( $actor['id'] ) ) {
			$state = Remote_Actors::upsert( $actor );
		} else {
			$state = new \WP_Error( 'activitypub_update_failed', 'Update failed: missing or invalid actor object in Update activity' );
			$actor = array();
		}

		/**
		 * Fires after an ActivityPub Update activity has been handled.
		 *
		 * @param array         $activity The ActivityPub activity data.
		 * @param int[]         $user_ids The local user IDs.
		 * @param int|\WP_Error $state    Actor post ID on success, WP_Error on failure.
		 * @param array         $actor    Remote actor meta data.
		 */
		\do_action( 'activitypub_handled_update', $activity, (array) $user_ids, $state, $actor );
	}
}
