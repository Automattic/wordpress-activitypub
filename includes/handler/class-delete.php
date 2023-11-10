<?php
namespace Activitypub\Handler;

use Activitypub\Collection\Followers;

/**
 * Handles Delete requests.
 */
class Delete {
	/**
	 * Initialize the class, registering WordPress hooks
	 */
	public static function init() {
		\add_action( 'activitypub_inbox_delete', array( self::class, 'handle_delete' ), 10, 2 );
	}

	/**
	 * Handles "Delete" requests.
	 *
	 * @param array $activity The delete activity.
	 * @param int   $user_id  The ID of the user performing the delete activity.
	 */
	public function handle_delete( $activity, $user_id ) {
		if (
			! isset( $activity['object'] ) ||
			! is_array( $activity['object'] ) ||
			! isset( $activity['object']['id'] )
		) {
			return;
		}

		$object_type = isset( $activity['object']['type'] ) ? $activity['object']['type'] : '';

		switch ( $object_type ) {
			case 'Person':
			case 'Group':
			case 'Organization':
			case 'Service':
			case 'Application':
				$follower = Followers::get_follower( $user_id, $activity['actor'] );
				if ( $follower ) {
					$follower->delete();
				}

				break;
			case 'Tombstone':
				// Handle tombstone.
				break;
			case 'Note':
			case 'Article':
			case 'Image':
			case 'Audio':
			case 'Video':
			case 'Event':
			case 'Document':
			default:
				// Handle delete activity for other object types.
				break;
		}
	}
}
