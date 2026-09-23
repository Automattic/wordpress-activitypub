<?php
/**
 * Delete handler file.
 *
 * @package Activitypub
 */

namespace Activitypub\Handler;

use Activitypub\Collection\Inbox;
use Activitypub\Collection\Interactions;
use Activitypub\Collection\Remote_Actors;
use Activitypub\Collection\Remote_Posts;
use Activitypub\Http;
use Activitypub\Tombstone;

use function Activitypub\add_to_outbox;
use function Activitypub\is_same_actor;
use function Activitypub\is_same_host;
use function Activitypub\object_to_uri;

/**
 * Handles Delete requests.
 */
class Delete {
	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		\add_action( 'activitypub_inbox_delete', array( self::class, 'handle_delete' ), 10, 4 );
		\add_action( 'activitypub_inbox_shared_delete', array( self::class, 'handle_delete' ), 10, 4 );
		\add_filter( 'activitypub_skip_inbox_storage', array( self::class, 'skip_inbox_storage' ), 10, 2 );
		\add_filter( 'activitypub_defer_signature_verification', array( self::class, 'defer_signature_verification' ), 10, 3 );
		\add_action( 'activitypub_delete_remote_actor_interactions', array( self::class, 'delete_interactions' ) );
		\add_action( 'activitypub_delete_remote_actor_posts', array( self::class, 'delete_posts' ) );

		\add_filter( 'activitypub_get_outbox_activity', array( self::class, 'outbox_activity' ) );
		\add_action( 'post_activitypub_add_to_outbox', array( self::class, 'maybe_bury' ), 10, 2 );
	}

	/**
	 * Handles "Delete" requests.
	 *
	 * @param array                               $activity        The delete activity.
	 * @param int|int[]                           $user_ids        The local user ID(s).
	 * @param \Activitypub\Activity\Activity|null $activity_object Optional. The activity object. Default null.
	 * @param string|null                         $context         Optional. The inbox context. Default null.
	 */
	public static function handle_delete( $activity, $user_ids, $activity_object = null, $context = null ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		// The shared inbox invokes this once per resolved recipient and once on the shared hook;
		// handle that path only on the shared hook, so it runs once with the full recipient list.
		if ( Inbox::CONTEXT_SHARED_INBOX === $context && 'activitypub_inbox_shared_delete' !== \current_filter() ) {
			return;
		}

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
				// A bare URI may be a QuoteAuthorization stamp the quoted author revoked.
				if ( self::revoke_quote_authorization( $activity ) ) {
					break;
				}

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
	 * Revoke a QuoteAuthorization stamp the quoted author deleted.
	 *
	 * @since unreleased
	 *
	 * @param array $activity The Activity object.
	 *
	 * @return bool True if a stamp on a local quote post was revoked.
	 */
	private static function revoke_quote_authorization( $activity ) {
		$stamp_uri = object_to_uri( $activity['object'] ?? '' );
		$actor     = object_to_uri( $activity['actor'] ?? '' );

		// An actor deleting itself is never a stamp, and a stamp always lives on its issuer's host.
		if ( ! $stamp_uri || $stamp_uri === $actor || ! is_same_host( $stamp_uri, $actor ) ) {
			return false;
		}

		$posts = \get_posts(
			array(
				'post_type'   => \get_post_types_by_support( 'activitypub' ),
				'post_status' => 'any',
				'numberposts' => 1,
				'meta_key'    => '_activitypub_quote_authorization', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'  => $stamp_uri, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);

		if ( ! $posts ) {
			return false;
		}

		$post = $posts[0];

		// Only the quoted object's author may revoke the stamp.
		$quoted_uri = \get_post_meta( $post->ID, '_activitypub_quote_request', true );
		$quoted     = $quoted_uri ? Http::get_remote_object( $quoted_uri ) : null;

		if ( ! $quoted || \is_wp_error( $quoted ) || empty( $quoted['attributedTo'] ) ) {
			return false;
		}

		if ( ! is_same_actor( $actor, $quoted['attributedTo'] ) ) {
			return false;
		}

		/*
		 * Signature verification is deferred for Deletes, so the body is untrusted: the
		 * revocation is honoured only if the stamp really no longer resolves.
		 */
		if ( ! Tombstone::exists( $stamp_uri ) ) {
			return false;
		}

		\delete_post_meta( $post->ID, '_activitypub_quote_authorization' );

		add_to_outbox( $post, 'Update', $post->post_author );

		return true;
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
		if ( ! \is_wp_error( $follower ) && Tombstone::exists( $activity['actor'] ) ) {
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
		$posts = Remote_Posts::get_by_remote_actor_id( $id );

		foreach ( $posts as $post ) {
			Remote_Posts::delete( $post->ID );
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
				\wp_delete_comment( $comment->comment_ID, true );
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
			return Remote_Posts::delete_by_guid( $id );
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
	 * Endpoints that opt in to mandatory signing by calling
	 * `verify_signature( $request, true )` must not be overridden — the
	 * Delete carve-out is only for the default inbox path where the
	 * remote actor's keys may legitimately be gone before the Delete
	 * arrives.
	 *
	 * @since 8.2.0 The `$force_signature` parameter is now respected.
	 * @since unreleased Limited to POST deliveries to an inbox route.
	 *
	 * @param bool             $defer           Whether to defer signature verification.
	 * @param \WP_REST_Request $request         The request object.
	 * @param bool             $force_signature Whether the caller has forced signature verification.
	 *
	 * @return bool Whether to defer signature verification.
	 */
	public static function defer_signature_verification( $defer, $request, $force_signature = false ) {
		if ( $force_signature ) {
			return $defer;
		}

		// Deliveries are POSTs; on any other method the body is not an activity we accept, so it must not waive verification.
		if ( 'POST' !== $request->get_method() ) {
			return $defer;
		}

		// Lowercased because routes match case-insensitively, so `/Inbox` reaches the same handler.
		$route = \strtolower( $request->get_route() );

		/* The carve-out is for inbox deliveries only: both the shared inbox and the per-actor inboxes end in `/inbox`. */
		if ( ! \str_starts_with( $route, '/' . ACTIVITYPUB_REST_NAMESPACE . '/' ) || ! \str_ends_with( $route, '/inbox' ) ) {
			return $defer;
		}

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
}
