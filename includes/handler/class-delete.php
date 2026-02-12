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

use function Activitypub\object_to_uri;

/**
 * Handles Delete requests.
 */
class Delete {
	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		\add_action( 'activitypub_inbox_delete', array( self::class, 'incoming' ), 10, 2 );
		\add_action( 'activitypub_handled_outbox_delete', array( self::class, 'outgoing' ), 10, 4 );
		\add_filter( 'activitypub_skip_inbox_storage', array( self::class, 'skip_inbox_storage' ), 10, 2 );
		\add_filter( 'activitypub_defer_signature_verification', array( self::class, 'defer_signature_verification' ), 10, 2 );
		\add_action( 'activitypub_delete_remote_actor_interactions', array( self::class, 'delete_interactions' ) );
		\add_action( 'activitypub_delete_remote_actor_posts', array( self::class, 'delete_posts' ) );

		\add_filter( 'activitypub_get_outbox_activity', array( self::class, 'outbox_activity' ) );
		\add_action( 'post_activitypub_add_to_outbox', array( self::class, 'maybe_bury' ), 10, 2 );
	}

	/**
	 * Handle incoming "Delete" requests from remote actors.
	 *
	 * @param array     $activity The delete activity.
	 * @param int|int[] $user_ids The local user ID(s).
	 */
	public static function incoming( $activity, $user_ids ) {
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
				self::delete_remote_actor( $activity, $user_ids );
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
				self::delete_object( $activity, $user_ids );
				break;

			/*
			 * Tombstone Type.
			 *
			 * @see: https://www.w3.org/TR/activitystreams-vocabulary/#dfn-tombstone
			 */
			case 'Tombstone':
				self::delete_object( $activity, $user_ids );
				break;

			/*
			 * Minimal Activity.
			 *
			 * @see https://www.w3.org/TR/activitystreams-core/#example-1
			 */
			default:
				// Check if Object is an Actor.
				if ( object_to_uri( $activity['object'] ) === $activity['actor'] ) {
					self::delete_remote_actor( $activity, $user_ids );
				} else { // Assume an object otherwise.
					self::delete_object( $activity, $user_ids );
				}
				// Maybe handle Delete Activity for other Object Types.
				break;
		}
	}

	/**
	 * Delete an Object.
	 *
	 * @param array     $activity The Activity object.
	 * @param int|int[] $user_ids The user ID(s).
	 */
	public static function delete_object( $activity, $user_ids ) {
		$result = self::maybe_delete_interaction( $activity );

		if ( ! $result ) {
			$result = self::maybe_delete_post( $activity );
		}

		$success = ( $result && ! \is_wp_error( $result ) );

		/**
		 * Fires after an ActivityPub Delete activity has been handled.
		 *
		 * @param array      $activity The ActivityPub activity data.
		 * @param int[]      $user_ids The local user IDs.
		 * @param bool       $success  True on success, false otherwise.
		 * @param mixed|null $result   The result of the delete operation.
		 */
		\do_action( 'activitypub_handled_delete', $activity, (array) $user_ids, $success, $result );
	}

	/**
	 * Delete an Actor.
	 *
	 * @param array     $activity The Activity object.
	 * @param int|int[] $user_ids The user ID(s).
	 */
	public static function delete_remote_actor( $activity, $user_ids ) {
		$result  = self::maybe_delete_follower( $activity );
		$success = ( $result && ! \is_wp_error( $result ) );

		/**
		 * Fires after an ActivityPub Delete activity has been handled.
		 *
		 * @param array      $activity The ActivityPub activity data.
		 * @param int[]      $user_ids The local user IDs.
		 * @param bool       $success  True on success, false otherwise.
		 * @param mixed|null $result   The result of the delete operation.
		 */
		\do_action( 'activitypub_handled_delete', $activity, (array) $user_ids, $success, $result );

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
			self::maybe_delete_interactions( $follower->ID );
			self::maybe_delete_posts( $follower->ID );
			$state = Remote_Actors::delete( $follower->ID );
		}

		return $state ?? false;
	}

	/**
	 * Schedule Deletion of Interactions of a Remote Actor.
	 *
	 * @param int $id The remote actor ID.
	 */
	public static function maybe_delete_interactions( $id ) {
		\wp_schedule_single_event(
			\time(),
			'activitypub_delete_remote_actor_interactions',
			array( $id )
		);
	}

	/**
	 * Schedule Deletion of Reader Items of a Remote Actor.
	 *
	 * @param int $id The remote actor ID.
	 */
	public static function maybe_delete_posts( $id ) {
		\wp_schedule_single_event(
			\time(),
			'activitypub_delete_remote_actor_posts',
			array( $id )
		);
	}

	/**
	 * Delete Interactions from a Remote Actor.
	 *
	 * @param int $id The ID of the actor whose comments to delete.
	 *
	 * @return bool True on success, false otherwise.
	 */
	public static function delete_interactions( $id ) {
		$comments = Interactions::get_by_remote_actor_id( $id );

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
	 * Delete Reader Items from an Actor.
	 *
	 * @param int $id The ID of the actor whose comments to delete.
	 *
	 * @return bool True on success, false otherwise.
	 */
	public static function delete_posts( $id ) {
		$posts = Posts::get_by_remote_actor_id( $id );

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
	 * Note: When comments are deleted, WordPress automatically deletes all associated
	 * comment meta including _activitypub_remote_actor_id. The remote actor post itself
	 * is not deleted, as it may be referenced by other comments or may be needed for
	 * future interactions.
	 *
	 * @param array $activity The delete activity.
	 *
	 * @return bool True on success, false otherwise.
	 */
	public static function maybe_delete_interaction( $activity ) {
		$id       = object_to_uri( $activity['object'] );
		$comments = Interactions::get_by_id( $id );

		if ( $comments && Tombstone::exists( $id ) ) {
			foreach ( $comments as $comment ) {
				// WordPress will automatically delete all comment meta including _activitypub_remote_actor_id.
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
	 * Add a URL to the tombstone registry when a Delete activity is sent.
	 *
	 * @param int                            $outbox_id The ID of the outbox activity.
	 * @param \Activitypub\Activity\Activity $activity  The Activity object.
	 */
	public static function maybe_bury( $outbox_id, $activity ) {
		if ( 'Delete' !== $activity->get_type() ) {
			return;
		}

		$object = $activity->get_object();

		if ( ! $object ) {
			return;
		}

		Tombstone::bury( object_to_uri( $object ) );

		if ( \is_object( $object ) ) {
			Tombstone::bury( $object->get_id(), $object->get_url() );
		}
	}

	/**
	 * Handle outgoing "Delete" activities from local actors.
	 *
	 * Deletes a WordPress post.
	 *
	 * @param array                          $data       The activity data array.
	 * @param int                            $user_id    The user ID.
	 * @param \Activitypub\Activity\Activity $activity   The Activity object.
	 * @param int                            $outbox_id  The outbox post ID.
	 */
	public static function outgoing( $data, $user_id, $activity, $outbox_id ) {
		$object = $data['object'] ?? '';

		// Get the object ID (can be a string URL or an object with an id).
		$object_id = object_to_uri( $object );

		if ( empty( $object_id ) ) {
			return;
		}

		/*
		 * Find the post by its ActivityPub ID.
		 * First try to find a local post by permalink (for C2S-created posts).
		 */
		$post_id = \url_to_postid( $object_id );
		$post    = $post_id ? \get_post( $post_id ) : null;

		// Fall back to Posts collection for remote posts (ap_post type).
		if ( ! $post instanceof \WP_Post ) {
			$post = Posts::get_by_guid( $object_id );
		}

		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		/*
		 * Verify the user owns this post.
		 * The blog actor ($user_id === 0) can delete any post since it
		 * represents the site itself.
		 */
		if ( (int) $post->post_author !== $user_id && $user_id > 0 ) {
			return;
		}

		// Trash the post (use wp_delete_post with false to move to trash).
		$result = \wp_trash_post( $post->ID );

		if ( ! $result ) {
			return;
		}

		/**
		 * Fires after a post has been deleted from an outgoing Delete activity.
		 *
		 * @param int   $post_id    The deleted post ID.
		 * @param array $data       The activity data.
		 * @param int   $user_id    The user ID.
		 * @param int   $outbox_id  The outbox post ID.
		 */
		\do_action( 'activitypub_outbox_deleted_post', $post->ID, $data, $user_id, $outbox_id );
	}

	/**
	 * Handle "Delete" requests.
	 *
	 * @deprecated unreleased Use Delete::incoming() instead.
	 *
	 * @param array     $activity The delete activity.
	 * @param int|int[] $user_ids The local user ID(s).
	 */
	public static function handle_delete( $activity, $user_ids ) {
		\_deprecated_function( __METHOD__, 'unreleased', 'Delete::incoming()' );

		return self::incoming( $activity, $user_ids );
	}

	/**
	 * Handle outbox "Delete" activities.
	 *
	 * @deprecated unreleased Use Delete::outgoing() instead.
	 *
	 * @param array                          $data       The activity data array.
	 * @param int                            $user_id    The user ID.
	 * @param \Activitypub\Activity\Activity $activity   The Activity object.
	 * @param int                            $outbox_id  The outbox post ID.
	 */
	public static function handle_outbox_delete( $data, $user_id, $activity, $outbox_id ) {
		\_deprecated_function( __METHOD__, 'unreleased', 'Delete::outgoing()' );

		return self::outgoing( $data, $user_id, $activity, $outbox_id );
	}
}
