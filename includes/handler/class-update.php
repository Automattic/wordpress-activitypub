<?php
namespace Activitypub\Handler;

use WP_Error;
use Activitypub\Collection\Interactions;

use function Activitypub\get_remote_metadata_by_actor;

/**
 * Handle Update requests.
 */
class Update {
	/**
	 * Initialize the class, registering WordPress hooks
	 */
	public static function init() {
		\add_action(
			'activitypub_inbox_update',
			array( self::class, 'handle_update' )
		);
	}

	/**
	 * Handle "Update" requests
	 *
	 * @param array                $array   The activity-object
	 * @param int                  $user_id The id of the local blog-user
	 */
	public static function handle_update( $array ) {
		$object_type = isset( $array['object']['type'] ) ? $array['object']['type'] : '';

		switch ( $object_type ) {
			// Actor Types
			// @see https://www.w3.org/TR/activitystreams-vocabulary/#actor-types
			case 'Person':
			case 'Group':
			case 'Organization':
			case 'Service':
			case 'Application':
				self::update_actor( $array );
				break;
			// Object and Link Types
			// @see https://www.w3.org/TR/activitystreams-vocabulary/#object-types
			case 'Note':
			case 'Article':
			case 'Image':
			case 'Audio':
			case 'Video':
			case 'Event':
			case 'Document':
				self::update_interaction( $array );
				break;
			// Minimal Activity
			// @see https://www.w3.org/TR/activitystreams-core/#example-1
			default:
				break;
		}
	}

	/**
	 * Update an Interaction
	 *
	 * @param array $activity The activity-object
	 * @param int   $user_id  The id of the local blog-user
	 *
	 * @return void
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

		\do_action( 'activitypub_handled_update', $activity, null, $state, $reaction );
	}

	/**
	 * Update an Actor
	 *
	 * @param array $activity The activity-object
	 *
	 * @return void
	 */
	public static function update_actor( $activity ) {
		// update cache
		get_remote_metadata_by_actor( $activity['actor'], false );

		// @todo maybe also update all interactions
	}
}
