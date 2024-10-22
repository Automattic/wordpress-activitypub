<?php
/**
 * Delete handler file.
 *
 * @package Activitypub
 */

namespace Activitypub\Handler;

use WP_REST_Request;
use Activitypub\Http;
use Activitypub\Collection\Followers;
use Activitypub\Collection\Interactions;

/**
 * Handles Delete requests.
 */
class Delete {
	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		\add_action(
			'activitypub_inbox_delete',
			array( self::class, 'handle_delete' )
		);

		// Defer signature verification for `Delete` requests.
		\add_filter(
			'activitypub_defer_signature_verification',
			array( self::class, 'defer_signature_verification' ),
			10,
			2
		);

		// Side effect.
		\add_action(
			'activitypub_delete_actor_interactions',
			array( self::class, 'delete_interactions' )
		);
	}

	/**
	 * Handles "Delete" requests.
	 *
	 * @param array $activity The delete activity.
	 */
	public static function handle_delete( $activity ) {
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
				self::maybe_delete_follower( $activity );
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
				self::maybe_delete_interaction( $activity );
				break;

			/*
			 * Tombstone Type.
			 *
			 * @see: https://www.w3.org/TR/activitystreams-vocabulary/#dfn-tombstone
			 */
			case 'Tombstone':
				self::maybe_delete_interaction( $activity );
				break;

			/*
			 * Minimal Activity.
			 *
			 * @see https://www.w3.org/TR/activitystreams-core/#example-1
			 */
			default:
				// Ignore non Minimal Activities.
				if ( ! is_string( $activity['object'] ) ) {
					return;
				}

				// Check if Object is an Actor.
				if ( $activity['actor'] === $activity['object'] ) {
					self::maybe_delete_follower( $activity );
				} else { // Assume an interaction otherwise.
					self::maybe_delete_interaction( $activity );
				}
				// Maybe handle Delete Activity for other Object Types.
				break;
		}
	}

	/**
	 * Delete a Follower if Actor-URL is a Tombstone.
	 *
	 * @param array $activity The delete activity.
	 */
	public static function maybe_delete_follower( $activity ) {
		$follower = Followers::get_follower_by_actor( $activity['actor'] );

		// Verify that Actor is deleted.
		if ( $follower && Http::is_tombstone( $activity['actor'] ) ) {
			$follower->delete();
			self::maybe_delete_interactions( $activity );
		}
	}

	/**
	 * Delete Reactions if Actor-URL is a Tombstone.
	 *
	 * @param array $activity The delete activity.
	 */
	public static function maybe_delete_interactions( $activity ) {
		// Verify that Actor is deleted.
		if ( Http::is_tombstone( $activity['actor'] ) ) {
			\wp_schedule_single_event(
				\time(),
				'activitypub_delete_actor_interactions',
				array( $activity['actor'] )
			);
		}
	}

	/**
	 * Delete comments from an Actor.
	 *
	 * @param array $actor The actor whose comments to delete.
	 */
	public static function delete_interactions( $actor ) {
		$comments = Interactions::get_interactions_by_actor( $actor );

		if ( is_array( $comments ) ) {
			foreach ( $comments as $comment ) {
				wp_delete_comment( $comment->comment_ID, true );
			}
		}
	}

	/**
	 * Delete a Reaction if URL is a Tombstone.
	 *
	 * @param array $activity The delete activity.
	 */
	public static function maybe_delete_interaction( $activity ) {
		if ( is_array( $activity['object'] ) ) {
			$id = $activity['object']['id'];
		} else {
			$id = $activity['object'];
		}

		$comments = Interactions::get_interaction_by_id( $id );

		if ( $comments && Http::is_tombstone( $id ) ) {
			foreach ( $comments as $comment ) {
				wp_delete_comment( $comment->comment_ID, true );
			}
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

		if ( isset( $json['type'] ) && 'Delete' === $json['type'] ) {
			return true;
		}

		return false;
	}
}
