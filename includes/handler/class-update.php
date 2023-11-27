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
		\add_action( 'activitypub_inbox_update', array( self::class, 'handle_update' ), 10, 2 );
	}

	/**
	 * Handle "Update" requests
	 *
	 * @param array                $array   The activity-object
	 * @param int                  $user_id The id of the local blog-user
	 * @param Activitypub\Activity $object  The activity object
	 */
	public static function handle_update( $activity, $user_id ) {
		$object_type = isset( $activity['object']['type'] ) ? $activity['object']['type'] : '';

		switch ( $object_type ) {
			// Actor Types
			// @see https://www.w3.org/TR/activitystreams-vocabulary/#actor-types
			case 'Person':
			case 'Group':
			case 'Organization':
			case 'Service':
			case 'Application':
				self::update_actor( $activity );
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
				self::update_interaction( $activity, $user_id );
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
	public static function update_interaction( $activity, $user_id ) {
		$state    = Interactions::update_comment( $activity );
		$reaction = null;

		if ( $state && ! \is_wp_error( $reaction ) ) {
			$reaction = \get_comment( $state );
		}

		\do_action( 'activitypub_handled_update', $activity, $user_id, $state, $reaction );
	}

	/**
	 * Update an Actor
	 *
	 * @param array $activity The activity-object
	 *
	 * @return void
	 */
	public static function update_actor( $activity ) {
		if ( isset( $activity['actor'] ) ) {
			$actor = $activity['actor'];

			if ( is_array( $activity['actor'] ) ) {
				$actor = $activity['actor']['id'];
			}

			get_remote_metadata_by_actor( $actor, false );
		}
	}
}
