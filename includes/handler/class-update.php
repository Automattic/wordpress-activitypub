<?php
/**
 * Update handler file.
 *
 * @package Activitypub
 */

namespace Activitypub\Handler;

use Activitypub\Collection\Interactions;
use Activitypub\Collection\Remote_Actors;
use Activitypub\Collection\Remote_Posts;
use Activitypub\Proxy;

use function Activitypub\is_activity_reply;
use function Activitypub\is_quote_activity;
use function Activitypub\object_to_id;

/**
 * Handle Update requests.
 */
class Update {
	/**
	 * Initialize the class, registering WordPress hooks.
	 *
	 * @return void
	 */
	public static function init() {
		\add_action( 'activitypub_handled_inbox_update', array( self::class, 'handle_update' ), 10, 3 );
	}

	/**
	 * Handle "Update" requests.
	 *
	 * @param array                          $activity        The Activity object.
	 * @param int[]                          $user_ids        Local recipient user IDs (followers and addressed local actors); may be empty.
	 * @param \Activitypub\Activity\Activity $activity_object The activity object. Default null.
	 *
	 * @return void
	 */
	public static function handle_update( $activity, $user_ids, $activity_object ) {
		// The proxy only lets an actor on the object's own host retire the cached copy.
		Proxy::delete( $activity['object'] ?? null, $activity['actor'] ?? '' );

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
				self::update_actor( $activity, $user_ids );
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
				self::update_object( $activity, $user_ids, $activity_object );
				break;

			/*
			 * Minimal Activity.
			 *
			 * @see https://www.w3.org/TR/activitystreams-core/#example-1
			 */
			default:
				break;
		}
	}

	/**
	 * Update an Object.
	 *
	 * @param array                          $activity        The Activity object.
	 * @param int[]|null                     $user_ids        Local recipient user IDs (followers and addressed local actors); may be empty.
	 * @param \Activitypub\Activity\Activity $activity_object The activity object. Default null.
	 *
	 * @return void
	 */
	public static function update_object( $activity, $user_ids, $activity_object ) {
		$result  = new \WP_Error( 'activitypub_update_failed', 'Update failed' );
		$updated = true;

		// Replies and quotes are stored as comments, as Create decides.
		if ( is_activity_reply( $activity ) || is_quote_activity( $activity ) ) {
			$comment_data = Interactions::update_comment( $activity );

			if ( false === $comment_data ) {
				$updated = false;
			} elseif ( \is_wp_error( $comment_data ) ) {
				// Handled but rejected (e.g. a foreign actor): keep the failure so the
				// success flag stays false and the Create fallback is not triggered.
				$result = $comment_data;
			} elseif ( ! empty( $comment_data['comment_ID'] ) ) {
				$result = \get_comment( $comment_data['comment_ID'] );
			}
		} elseif ( \get_option( 'activitypub_create_posts', false ) ) {
			$result = Remote_Posts::update( $activity, $user_ids );

			if ( \is_wp_error( $result ) && 'activitypub_post_not_found' === $result->get_error_code() ) {
				$updated = false;
			}
		}

		// There is no object to update, try to trigger create instead.
		if ( ! $updated ) {
			Create::handle_create( $activity, $user_ids, $activity_object );
			return;
		}

		$success = ( $result && ! \is_wp_error( $result ) );

		/**
		 * Fires after an ActivityPub Update activity has been handled.
		 *
		 * @param array                          $activity The ActivityPub activity data.
		 * @param int[]|null                     $user_ids The local user IDs.
		 * @param bool                           $success  True on success, false otherwise.
		 * @param \WP_Comment|\WP_Post|\WP_Error $result   The updated post, comment, or error.
		 */
		\do_action( 'activitypub_handled_update', $activity, (array) $user_ids, $success, $result );
	}

	/**
	 * Update an Actor.
	 *
	 * @param array      $activity The Activity object.
	 * @param int[]|null $user_ids Local recipient user IDs (followers and addressed local actors); may be empty.
	 *
	 * @return void
	 */
	public static function update_actor( $activity, $user_ids ) {
		$signer = object_to_id( $activity['actor'] );
		$actor  = $activity['object'] ?? null;

		/*
		 * An embedded actor is the fresh data sent by the remote server. It goes through the Proxy like
		 * a fetch: only the signer vouches for itself, and it gets the actor schema. An actor that is
		 * only named is fetched; Proxy::get() instead of get_remote_metadata_by_actor(), which returns
		 * the stale locally cached copy via fetch_by_uri().
		 */
		$actor = \is_array( $actor ) ? Proxy::put( $actor, $signer ) : Proxy::get( $signer, array( 'cached' => false ) );

		/*
		 * An actor may only update itself. Bind the updated object to the activity
		 * actor (the same constraint the Delete handler enforces) so a remote server
		 * cannot overwrite another host's cached actor by sending an Update whose
		 * object.id points at a victim actor.
		 */
		if ( \is_array( $actor ) && object_to_id( $actor ) === $signer ) {
			$state = Remote_Actors::upsert( $actor );
		} else {
			$state = new \WP_Error( 'activitypub_update_failed', \__( 'Update failed: missing, invalid, or unauthorized actor object in Update activity.', 'activitypub' ) );
			$actor = array();
		}

		/**
		 * Fires after an ActivityPub Update activity has been handled.
		 *
		 * @param array         $activity The ActivityPub activity data.
		 * @param int[]         $user_ids The local user IDs.
		 * @param int|\WP_Error $state    Actor post ID on success, WP_Error on failure.
		 * @param array         $actor    Remote actor meta data.
		 */
		\do_action( 'activitypub_handled_update', $activity, (array) $user_ids, $state, $actor );
	}
}
