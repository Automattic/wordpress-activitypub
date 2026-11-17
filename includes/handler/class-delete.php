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
use Activitypub\Proxy;
use Activitypub\Signature;
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
	 * The inbox request of the current Delete of an object, to verify its signature when needed.
	 *
	 * @var \WP_REST_Request|null
	 */
	private static $request = null;

	/**
	 * The actor that signed the current request, false if it is not verified yet.
	 *
	 * @var string|false
	 */
	private static $signer = false;

	/**
	 * Initialize the class, registering WordPress hooks.
	 *
	 * @return void
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
	 *
	 * @return void
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
				if ( self::revoke_quote_authorization( $activity, $user_ids ) ) {
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

		// The request belongs to this Delete only.
		self::$request = null;
		self::$signer  = false;
	}

	/**
	 * Revoke a QuoteAuthorization stamp the quoted author deleted.
	 *
	 * @since 9.4.0
	 *
	 * @param array     $activity The Activity object.
	 * @param int[]|int $user_ids The local user IDs.
	 *
	 * @return bool True if a stamp on a local quote post was revoked.
	 */
	private static function revoke_quote_authorization( $activity, $user_ids ) {
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
				'meta_type'   => 'BINARY',
			)
		);

		if ( ! $posts ) {
			return false;
		}

		$post = $posts[0];

		// Only the quoted object's author may revoke the stamp.
		$quoted_uri = \get_post_meta( $post->ID, '_activitypub_quote_request', true );
		$quoted     = $quoted_uri ? Proxy::get( $quoted_uri ) : null;

		if ( ! $quoted || \is_wp_error( $quoted ) || empty( $quoted['attributedTo'] ) ) {
			return false;
		}

		if ( ! is_same_actor( $actor, $quoted['attributedTo'] ) ) {
			return false;
		}

		/*
		 * Signature verification is deferred for Deletes, so the body is untrusted: the
		 * revocation is honoured only if the quoted author signed it or the stamp really
		 * no longer resolves.
		 */
		if ( ! self::is_signed_by( $actor ) && ! Tombstone::exists( $stamp_uri ) ) {
			return false;
		}

		Proxy::purge( $stamp_uri );
		\delete_post_meta( $post->ID, '_activitypub_quote_authorization' );

		add_to_outbox( $post, 'Update', (int) $post->post_author );

		/** This action is documented in includes/handler/class-delete.php */
		\do_action( 'activitypub_handled_delete', $activity, (array) $user_ids, true, $post );

		return true;
	}

	/**
	 * Delete an Object.
	 *
	 * @param array     $activity The Activity object.
	 * @param int|int[] $user_ids The user ID(s).
	 *
	 * @return void
	 */
	public static function delete_object( $activity, $user_ids ) {
		$result = self::maybe_delete_interaction( $activity );
		$post   = Remote_Posts::get_by_guid( object_to_uri( $activity['object'] ) );

		// An object can be both, for example a reply of a followed actor is also in the reader.
		if ( ! $result || ! \is_wp_error( $post ) ) {
			$post_result = self::maybe_delete_post( $activity, $post );
			$result      = $result ? $result : $post_result;
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
	 *
	 * @return bool True on success, false otherwise.
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
		$actor = object_to_uri( $activity['actor'] ?? '' );

		// Confirm at the actor's URI; an inline Tombstone is only an untrusted claim.
		if ( ! \is_string( $actor ) || ! $actor || ! Tombstone::exists( $actor ) ) {
			return false;
		}

		// The actor is gone from its own host, so the cached copy goes with it.
		Proxy::purge( $actor );

		$follower = Remote_Actors::get_by_uri( $actor );

		if ( ! \is_wp_error( $follower ) ) {
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
	 *
	 * @return void
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
	 *
	 * @return void
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

		if ( ! $comments ) {
			return false;
		}

		$owner_ids = array();
		foreach ( $comments as $comment ) {
			$owner_ids[] = (int) \get_comment_meta( $comment->comment_ID, '_activitypub_remote_actor_id', true );
		}

		if ( self::is_signed_by_owner( $activity, $owner_ids ) || Tombstone::exists( $id ) ) {
			// The object is deleted, so the cached copy goes with it.
			Proxy::purge( $id );

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
	 * @since unreleased Added the `$post` parameter.
	 *
	 * @param array                   $activity The delete activity.
	 * @param \WP_Post|\WP_Error|null $post     Optional. The reader post of the object, if already looked up. Default null.
	 *
	 * @return bool|\WP_Error True on success, false or WP_Error on failure.
	 */
	public static function maybe_delete_post( $activity, $post = null ) {
		$id   = object_to_uri( $activity['object'] );
		$post = $post ?? Remote_Posts::get_by_guid( $id );

		$signed = ! \is_wp_error( $post ) && self::is_signed_by_owner( $activity, array( (int) \get_post_meta( $post->ID, '_activitypub_remote_actor_id', true ) ) );

		// The owner signed the Delete, or the object is gone from its own host.
		if ( $signed || Tombstone::exists( $id ) ) {
			// The object is deleted, so the cached copy goes with it.
			Proxy::purge( $id );

			return \is_wp_error( $post ) ? $post : Remote_Posts::delete( $post->ID );
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
	 * The request of a Delete of an object is kept, so its signature can be
	 * checked later when a local copy is deleted, see is_signed_by().
	 *
	 * @since 8.2.0 The `$force_signature` parameter is now respected.
	 * @since 9.4.0 Limited to POST deliveries to an inbox route.
	 * @since unreleased Keeps the request of a Delete of an object.
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
			self::$request = null;
			self::$signer  = false;

			/*
			 * The actor of a Delete of an object still exists, so its signature can be checked,
			 * without making it a requirement. It is checked only when a local copy is about to be
			 * deleted, see is_signed_by().
			 */
			$actor = object_to_uri( $json['actor'] ?? '' );
			if ( $actor && object_to_uri( $json['object'] ?? '' ) !== $actor ) {
				self::$request = $request;
			}

			return true;
		}

		return $defer;
	}

	/**
	 * Check whether the actor that signed the Delete owns all the objects.
	 *
	 * @since unreleased
	 *
	 * @param array $activity  The Delete activity.
	 * @param int[] $owner_ids The IDs of the remote actors that own the objects.
	 *
	 * @return bool True if the signer of the Delete owns all the objects.
	 */
	private static function is_signed_by_owner( $activity, $owner_ids ) {
		$actor = object_to_uri( $activity['actor'] ?? '' );

		if ( ! $owner_ids ) {
			return false;
		}

		foreach ( $owner_ids as $owner_id ) {
			if ( ! Remote_Actors::has_uri( $owner_id, $actor ) ) {
				return false;
			}
		}

		return self::is_signed_by( $actor );
	}

	/**
	 * Check whether the current Delete is signed by an actor.
	 *
	 * The signature is verified on the first call only, and only for the Delete of an object.
	 *
	 * @since unreleased
	 *
	 * @param string $actor The actor URI.
	 *
	 * @return bool True if the signature verified and its key belongs to the actor.
	 */
	private static function is_signed_by( $actor ) {
		if ( ! self::$request || ! $actor ) {
			return false;
		}

		if ( false === self::$signer ) {
			$signer       = Signature::verify_http_signature( self::$request );
			self::$signer = \is_string( $signer ) ? $signer : '';
		}

		return self::$signer === $actor;
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
	 *
	 * @return void
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
