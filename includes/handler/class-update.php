<?php
/**
 * Update handler file.
 *
 * @package Activitypub
 */

namespace Activitypub\Handler;

use Activitypub\Collection\Interactions;

use function Activitypub\get_remote_metadata_by_actor;

/**
 * Handle Update requests.
 */
class Update {
	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		\add_action(
			'activitypub_inbox_update',
			array( self::class, 'handle_update' )
		);
	}

	/**
	 * Handle "Update" requests.
	 *
	 * @param array $activity The Activity object.
	 */
	public static function handle_update( $activity ) {
		$object_type = isset( $activity['object']['type'] ) ? $activity['object']['type'] : '';

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
				self::update_actor( $activity );
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
				self::update_interaction( $activity );
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
	 */
	public static function update_interaction( $activity ) {
		$commentdata = Interactions::update_comment( $activity );
		$reaction    = null;

		if ( ! empty( $commentdata['comment_ID'] ) ) {
			$state    = 1;
			$reaction = \get_comment( $commentdata['comment_ID'] );
		} else {
			$state = $commentdata;
		}

		/**
		 * Fires after an Update activity has been handled.
		 *
		 * @param array            $activity The complete Update activity data.
		 * @param null             $user     Always null for Update activities.
		 * @param int|array        $state    1 if comment was updated successfully, error data otherwise.
		 * @param \WP_Comment|null $reaction The updated comment object if successful, null otherwise.
		 */
		\do_action( 'activitypub_handled_update', $activity, null, $state, $reaction );
	}

	/**
	 * Update an Actor.
	 *
	 * @param array $activity The Activity object.
	 */
	public static function update_actor( $activity ) {
		// Update cache.
		get_remote_metadata_by_actor( $activity['actor'], false );

		// @todo maybe also update all interactions.
	}
}
