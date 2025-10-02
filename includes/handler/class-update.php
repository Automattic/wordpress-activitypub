<?php
/**
 * Update handler file.
 *
 * @package Activitypub
 */

namespace Activitypub\Handler;

use Activitypub\Collection\Interactions;
use Activitypub\Collection\Remote_Actors;

use function Activitypub\get_remote_metadata_by_actor;

/**
 * Handle Update requests.
 */
class Update {
	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		\add_action( 'activitypub_inbox_update', array( self::class, 'handle_update' ), 10, 2 );
	}

	/**
	 * Handle "Update" requests.
	 *
	 * @param array $activity The Activity object.
	 * @param int   $user_id  The user ID. Always null for Update activities.
	 */
	public static function handle_update( $activity, $user_id ) {
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
				self::update_actor( $activity, $user_id );
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
				self::update_interaction( $activity, $user_id );
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
	 * Update an Interaction.
	 *
	 * @param array $activity The Activity object.
	 * @param int   $user_id  The user ID. Always null for Update activities.
	 */
	public static function update_interaction( $activity, $user_id ) {
		$comment_data = Interactions::update_comment( $activity );
		$success      = false;

		if ( ! empty( $comment_data['comment_ID'] ) ) {
			$success = true;
			$result  = \get_comment( $comment_data['comment_ID'] );
		} else {
			$result = $comment_data;
		}

		/**
		 * Fires after an ActivityPub Update activity has been handled.
		 *
		 * @param array                            $activity The ActivityPub activity data.
		 * @param int                              $user_id  The local user ID.
		 * @param bool                             $success  True on success, false otherwise.
		 * @param array|string|int|\WP_Error|false $result   The updated comment, or null if update failed.
		 */
		\do_action( 'activitypub_handled_update', $activity, $user_id, $success, $result );
	}

	/**
	 * Update an Actor.
	 *
	 * @param array $activity The Activity object.
	 * @param int   $user_id  The user ID. Always null for Update activities.
	 */
	public static function update_actor( $activity, $user_id ) {
		// Update cache.
		$actor = get_remote_metadata_by_actor( $activity['actor'], false );

		if ( ! $actor || \is_wp_error( $actor ) || ! isset( $actor['id'] ) ) {
			return;
		}

		$state = Remote_Actors::upsert( $actor );

		/**
		 * Fires after an ActivityPub Update activity has been handled.
		 *
		 * @param array         $activity The ActivityPub activity data.
		 * @param int.          $user_id  The local user ID.
		 * @param int|\WP_Error $state    Actor post ID on success, WP_Error on failure.
		 * @param array         $actor    Remote actor meta data.
		 */
		\do_action( 'activitypub_handled_update', $activity, $user_id, $state, $actor );
	}
}
