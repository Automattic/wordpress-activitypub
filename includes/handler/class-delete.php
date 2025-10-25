<?php
/**
 * Delete handler file.
 *
 * @package Activitypub
 */

namespace Activitypub\Handler;

use Activitypub\Collection\Interactions;
use Activitypub\Collection\Posts;
use Activitypub\Collection\Remote_Actors;
use Activitypub\Tombstone;

use function Activitypub\is_activity_reply;
use function Activitypub\object_to_uri;

/**
 * Handles Delete requests.
 */
class Delete {
	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		\add_action( 'activitypub_inbox_delete', array( self::class, 'handle_delete' ), 10, 2 );
		\add_filter( 'activitypub_skip_inbox_storage', array( self::class, 'skip_inbox_storage' ), 10, 2 );
		\add_filter( 'activitypub_defer_signature_verification', array( self::class, 'defer_signature_verification' ), 10, 2 );
		\add_action( 'activitypub_delete_remote_actor_interactions', array( self::class, 'delete_interactions' ) );
		\add_action( 'activitypub_delete_remote_actor_posts', array( self::class, 'delete_posts' ) );

		\add_filter( 'activitypub_get_outbox_activity', array( self::class, 'outbox_activity' ) );
		\add_action( 'post_activitypub_add_to_outbox', array( self::class, 'post_add_to_outbox' ), 10, 2 );
	}

	/**
	 * Handles "Delete" requests.
	 *
	 * @param array $activity The delete activity.
	 * @param int   $user_id  The local user ID.
	 */
	public static function handle_delete( $activity, $user_id ) {
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
				self::delete_remote_actor( $activity, $user_id );
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
				self::delete_object( $activity, $user_id );
				break;

			/*
			 * Tombstone Type.
			 *
			 * @see: https://www.w3.org/TR/activitystreams-vocabulary/#dfn-tombstone
			 */
			case 'Tombstone':
				self::delete_object( $activity, $user_id );
				break;

			/*
			 * Minimal Activity.
			 *
			 * @see https://www.w3.org/TR/activitystreams-core/#example-1
			 */
			default:
				// Check if Object is an Actor.
				if ( object_to_uri( $activity['object'] ) === $activity['actor'] ) {
					self::delete_remote_actor( $activity, $user_id );
				} else { // Assume an object otherwise.
					self::delete_object( $activity, $user_id );
				}
				// Maybe handle Delete Activity for other Object Types.
				break;
		}
	}

	/**
	 * Delete an Object.
	 *
	 * @param array $activity The Activity object.
	 * @param int   $user_id  The user ID.
	 */
	public static function delete_object( $activity, $user_id ) {
		// Check for private and/or direct messages.
		if ( is_activity_reply( $activity ) ) {
			$result = self::maybe_delete_interaction( $activity );
		} else {
			$result = self::maybe_delete_post( $activity );
		}

		$success = ( $result && ! \is_wp_error( $result ) );

		/**
		 * Fires after an ActivityPub Delete activity has been handled.
		 *
		 * @param array      $activity The ActivityPub activity data.
		 * @param int        $user_id  The local user ID.
		 * @param bool       $success  True on success, false otherwise.
		 * @param mixed|null $result   The result of the delete operation.
		 */
		\do_action( 'activitypub_handled_delete', $activity, $user_id, $success, $result );
	}

	/**
	 * Delete an Actor.
	 *
	 * @param array $activity The Activity object.
	 * @param int   $user_id  The user ID.
	 */
	public static function delete_remote_actor( $activity, $user_id ) {
		$result  = self::maybe_delete_follower( $activity );
		$success = ( $result && ! \is_wp_error( $result ) );

		/**
		 * Fires after an ActivityPub Delete activity has been handled.
		 *
		 * @param array      $activity The ActivityPub activity data.
		 * @param int        $user_id  The local user ID.
		 * @param bool       $success  True on success, false otherwise.
		 * @param mixed|null $result   The result of the delete operation.
		 */
		\do_action( 'activitypub_handled_delete', $activity, $user_id, $success, $result );

		return $result;
	}

	/**
	 * Delete a Follower if Actor-URL is a Tombstone.
	 *
	 * @param array $activity The delete activity.
	 *
	 * @return bool True on success, false otherwise.
	 */
	public static function maybe_delete_follower( $activity ) {
		$follower = Remote_Actors::get_by_uri( $activity['actor'] );

		// Verify that Actor is deleted.
		if ( ! is_wp_error( $follower ) && Tombstone::exists( $activity['actor'] ) ) {
			$state = Remote_Actors::delete( $follower->ID );
			self::maybe_delete_interactions( $activity );
			self::maybe_delete_posts( $activity );
		}

		return $state ?? false;
	}

	/**
	 * Delete Reactions if Actor-URL is a Tombstone.
	 *
	 * @param array $activity The delete activity.
	 *
	 * @return bool True on success, false otherwise.
	 */
	public static function maybe_delete_interactions( $activity ) {
		// Verify that Actor is deleted.
		if ( Tombstone::exists( $activity['actor'] ) ) {
			\wp_schedule_single_event(
				\time(),
				'activitypub_delete_remote_actor_interactions',
				array( $activity['actor'] )
			);

			return true;
		}

		return false;
	}

	/**
	 * Delete Reactions if Actor-URL is a Tombstone.
	 *
	 * @param array $activity The delete activity.
	 *
	 * @return bool True on success, false otherwise.
	 */
	public static function maybe_delete_posts( $activity ) {
		// Verify that Actor is deleted.
		if ( Tombstone::exists( $activity['actor'] ) ) {
			\wp_schedule_single_event(
				\time(),
				'activitypub_delete_remote_actor_posts',
				array( $activity['actor'] )
			);

			return true;
		}

		return false;
	}

	/**
	 * Delete comments from an Actor.
	 *
	 * @param string $actor The URL of the actor whose comments to delete.
	 *
	 * @return bool True on success, false otherwise.
	 */
	public static function delete_interactions( $actor ) {
		$comments = Interactions::get_by_actor( $actor );

		foreach ( $comments as $comment ) {
			\wp_delete_comment( $comment, true );
		}

		if ( $comments ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Delete comments from an Actor.
	 *
	 * @param string $actor The URL of the actor whose comments to delete.
	 *
	 * @return bool True on success, false otherwise.
	 */
	public static function delete_posts( $actor ) {
		$posts = Posts::get_by_remote_actor( $actor );

		foreach ( $posts as $post ) {
			Posts::delete( $post->ID );
		}

		if ( $posts ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Delete a Reaction if URL is a Tombstone.
	 *
	 * @param array $activity The delete activity.
	 *
	 * @return bool True on success, false otherwise.
	 */
	public static function maybe_delete_interaction( $activity ) {
		if ( is_array( $activity['object'] ) ) {
			$id = $activity['object']['id'];
		} else {
			$id = $activity['object'];
		}

		$comments = Interactions::get_by_id( $id );

		if ( $comments && Tombstone::exists( $id ) ) {
			foreach ( $comments as $comment ) {
				wp_delete_comment( $comment->comment_ID, true );
			}

			return true;
		}

		return false;
	}

	/**
	 * Delete a post from the Posts collection.
	 *
	 * @param array $activity The delete activity.
	 *
	 * @return bool|\WP_Error True on success, false or WP_Error on failure.
	 */
	public static function maybe_delete_post( $activity ) {
		$id = object_to_uri( $activity['object'] );

		// Check if the object exists and is a tombstone.
		if ( Tombstone::exists( $id ) ) {
			return Posts::delete_by_guid( $id );
		}

		return false;
	}

	/**
	 * Skip inbox storage for `Delete` requests.
	 *
	 * @param bool  $skip Whether to skip inbox storage.
	 * @param array $data The activity data array.
	 *
	 * @return bool Whether to skip inbox storage.
	 */
	public static function skip_inbox_storage( $skip, $data ) {
		if ( isset( $data['type'] ) && 'Delete' === $data['type'] ) {
			return true;
		}

		return $skip;
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

		return $defer;
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
