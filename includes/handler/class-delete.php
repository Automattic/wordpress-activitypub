<?php
namespace Activitypub\Handler;

use WP_Error;
use WP_REST_Request;
use Activitypub\Http;
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
		// defer signature verification for `Delete` requests.
		\add_filter( 'activitypub_defer_signature_verification', array( self::class, 'defer_signature_verification' ), 10, 2 );
	}

	/**
	 * Handles "Delete" requests.
	 *
	 * @param array $activity The delete activity.
	 * @param int   $user_id  The ID of the user performing the delete activity.
	 */
	public static function handle_delete( $activity, $user_id ) {
		$object_type = isset( $activity['object']['type'] ) ? $activity['object']['type'] : '';

		switch ( $object_type ) {
			// Actor Types
			// @see https://www.w3.org/TR/activitystreams-vocabulary/#actor-types
			case 'Person':
			case 'Group':
			case 'Organization':
			case 'Service':
			case 'Application':
				self::maybe_delete_follower( $user_id, $activity );
				break;
			// Tombstone Type
			// @see: https://www.w3.org/TR/activitystreams-vocabulary/#dfn-tombstone
			case 'Tombstone':
				// Handle tombstone.
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
				self::maybe_delete_reaction( $user_id, $activity );
				break;
			// Minimal Activity
			// @see https://www.w3.org/TR/activitystreams-core/#example-1
			default:
				// ignore non Minimal Activities.
				if ( ! is_string( $activity['object'] ) ) {
					return;
				}

				// check if Object is an Actor.
				if ( $activity['actor'] === $activity['object'] ) {
					self::maybe_delete_follower( $user_id, $activity );
				} else { // assume a reaction otherwise.
					self::maybe_delete_reaction( $user_id, $activity );
				}
				// maybe handle Delete Activity for other Object Types.
				break;
		}
	}

	/**
	 * Delete a Follower if Actor-URL is a Tombstone.
	 *
	 * @param int   $user_id  The ID of the user receiving the delete activity.
	 * @param array $activity The delete activity.
	 */
	public static function maybe_delete_follower( $user_id, $activity ) {
		$follower = Followers::get_follower( $user_id, $activity['actor'] );

		// if no matching follower, nothing to do.
		if ( ! $follower ) {
			return;
		}

		// verify if Actor is deleted.
		if ( Http::is_tombstone( $activity['actor'] ) ) {
			$follower->delete();
		}
	}

	/**
	 * Defer signature verification for `Delete` requests.
	 *
	 * @param bool            $defer   Whether to defer signature verification.
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return bool Whether to defer signature verification.
	 */
	public static function defer_signature_verification( $defer, $request ) {
		$json = $request->get_json_params();

		if ( 'Delete' === $json['type'] ) {
			return true;
		}

		return false;
	}
}
