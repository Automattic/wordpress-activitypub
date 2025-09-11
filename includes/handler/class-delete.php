<?php
/**
 * Delete handler file.
 *
 * @package Activitypub
 */

namespace Activitypub\Handler;

use Activitypub\Collection\Interactions;
use Activitypub\Collection\Remote_Actors;
use Activitypub\Tombstone;

use function Activitypub\object_to_uri;

/**
 * Handles Delete requests.
 */
class Delete {
	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		\add_action( 'activitypub_inbox_delete', array( self::class, 'handle_delete' ) );
		\add_filter( 'activitypub_defer_signature_verification', array( self::class, 'defer_signature_verification' ), 10, 2 );
		\add_action( 'activitypub_delete_actor_interactions', array( self::class, 'delete_interactions' ) );

		\add_filter( 'activitypub_get_outbox_activity', array( self::class, 'outbox_activity' ) );
		\add_action( 'post_activitypub_add_to_outbox', array( self::class, 'post_add_to_outbox' ), 10, 2 );
	}

	/**
	 * Handles "Delete" requests.
	 *
	 * @param array $activity The delete activity.
	 */
	public static function handle_delete( $activity ) {
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
		$follower = Remote_Actors::get_by_uri( $activity['actor'] );

		// Verify that Actor is deleted.
		if ( ! is_wp_error( $follower ) && Tombstone::exists( $activity['actor'] ) ) {
			Remote_Actors::delete( $follower->ID );
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
		if ( Tombstone::exists( $activity['actor'] ) ) {
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
	 * @param string $actor The URL of the actor whose comments to delete.
	 */
	public static function delete_interactions( $actor ) {
		$comments = Interactions::get_interactions_by_actor( $actor );

		foreach ( $comments as $comment ) {
			wp_delete_comment( $comment, true );
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

		if ( $comments && Tombstone::exists( $id ) ) {
			foreach ( $comments as $comment ) {
				wp_delete_comment( $comment->comment_ID, true );
			}
		}
	}

	/**
	 * Defer signature verification for `Delete` requests.
	 *
	 * @param bool             $defer   Whether to defer signature verification.
	 * @param \WP_REST_Request $request The request object.
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

	/**
	 * Set the object to the object ID.
	 *
	 * @param \Activitypub\Activity\Activity $activity The Activity object.
	 *
	 * @return \Activitypub\Activity\Activity The filtered Activity object.
	 */
	public static function outbox_activity( $activity ) {
		if ( 'Delete' === $activity->get_type() ) {
			$activity->set_object( object_to_uri( $activity->get_object() ) );
		}

		return $activity;
	}

	/**
	 * Add the activity to the outbox.
	 *
	 * @param int                            $outbox_id The ID of the outbox activity.
	 * @param \Activitypub\Activity\Activity $activity  The Activity object.
	 */
	public static function post_add_to_outbox( $outbox_id, $activity ) {
		// Set Tombstones for deleted objects.
		if ( 'Delete' === $activity->get_type() ) {
			Tombstone::bury( object_to_uri( $activity->get_object() ) );
		}
	}
}
