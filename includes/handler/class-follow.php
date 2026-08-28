<?php
/**
 * Follow handler file.
 *
 * @package Activitypub
 */

namespace Activitypub\Handler;

use Activitypub\Activity\Activity;
use Activitypub\Application;
use Activitypub\Collection\Actors;
use Activitypub\Collection\Followers;
use Activitypub\Collection\Remote_Actors;
use Activitypub\Http;

use function Activitypub\add_to_outbox;
use function Activitypub\object_to_uri;

/**
 * Handle Follow requests.
 */
class Follow {
	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		\add_action( 'activitypub_inbox_follow', array( self::class, 'handle_follow' ), 10, 2 );
		\add_action( 'activitypub_handled_follow', array( self::class, 'queue_accept' ), 10, 4 );

		// The Application actor cannot be followed; explicitly reject such Follows so they don't sit "pending" on the remote instance forever.
		\add_action( 'activitypub_inbox_shared_follow', array( self::class, 'reject_application_follow' ), 10, 2 );
	}

	/**
	 * Handle "Follow" requests.
	 *
	 * @param array     $activity The activity object.
	 * @param int|int[] $user_ids The user ID(s).
	 */
	public static function handle_follow( $activity, $user_ids ) {
		// Extract the user ID (follow requests are always for a single user).
		$user_id = \is_array( $user_ids ) ? \reset( $user_ids ) : $user_ids;

		// Check if the actor already follows the user.
		$already_following = false;
		$remote_actor      = Remote_Actors::get_by_uri( $activity['actor'] );
		if ( ! \is_wp_error( $remote_actor ) ) {
			$already_following = Followers::follows( $remote_actor->ID, $user_id );
		}

		// Save follower if not already following.
		if ( $already_following ) {
			$success = false;
		} else {
			$remote_actor = Followers::add( $user_id, $activity['actor'] );
			$success      = ! \is_wp_error( $remote_actor );

			if ( $success ) {
				$remote_actor = \get_post( $remote_actor );
			}
		}

		/**
		 * Fires after a new follower has been added.
		 *
		 * @deprecated 7.5.0 Use "activitypub_handled_follow" instead.
		 *
		 * @param string             $actor        The URL of the actor (follower) who initiated the follow.
		 * @param array              $activity     The complete activity data of the follow request.
		 * @param int                $user_id      The ID of the WordPress user being followed.
		 * @param \WP_Post|\WP_Error $remote_actor The Actor object containing the new follower's data.
		 */
		\do_action_deprecated( 'activitypub_followers_post_follow', array( $activity['actor'], $activity, $user_id, $remote_actor ), '7.5.0', 'activitypub_handled_follow' );

		/**
		 * Fires after a Follow activity has been handled.
		 *
		 * @param array              $activity     The ActivityPub activity data.
		 * @param int[]              $user_ids     The local user IDs.
		 * @param bool               $success      True on success, false otherwise.
		 * @param \WP_Post|\WP_Error $remote_actor The remote actor/follower, or WP_Error if failed.
		 */
		\do_action( 'activitypub_handled_follow', $activity, (array) $user_ids, $success, $remote_actor );
	}

	/**
	 * Send Accept response.
	 *
	 * @param array              $activity_object The ActivityPub activity data.
	 * @param int|int[]          $user_ids        The local user IDs.
	 * @param bool               $success         True on success, false otherwise.
	 * @param \WP_Post|\WP_Error $remote_actor    The remote actor/follower, or WP_Error if failed.
	 */
	public static function queue_accept( $activity_object, $user_ids, $success, $remote_actor ) {
		if ( \is_wp_error( $remote_actor ) ) {
			// Impossible to send a "Reject" because we can not get the Remote-Inbox.
			return;
		}

		// Extract the user ID from the array (follow requests are always for a single user).
		$user_id = \is_array( $user_ids ) ? \reset( $user_ids ) : $user_ids;

		$actor = $activity_object['actor'];

		// Only send minimal data.
		$activity_object = \array_intersect_key(
			$activity_object,
			array(
				'id'     => 1,
				'type'   => 1,
				'actor'  => 1,
				'object' => 1,
			)
		);

		$activity = new Activity();
		$activity->set_type( 'Accept' );
		$activity->set_actor( Actors::get_by_id( $user_id )->get_id() );
		$activity->set_object( $activity_object );
		$activity->set_to( array( $actor ) );

		add_to_outbox( $activity, null, $user_id, ACTIVITYPUB_CONTENT_VISIBILITY_PRIVATE );
	}

	/**
	 * Reject Follow requests aimed at the Application actor.
	 *
	 * The Application advertises `manuallyApprovesFollowers` but is not followable,
	 * so an explicit Reject is the only way a remote follow request gets resolved.
	 * The Reject is sent directly instead of through the Outbox, which only
	 * dispatches for real actors, and is signed with the Application key.
	 *
	 * @since 9.1.0
	 *
	 * @param array $activity The Follow activity data.
	 * @param int[] $user_ids The local recipient IDs the inbox resolved.
	 */
	public static function reject_application_follow( $activity, $user_ids ) {
		// A resolved recipient means the Follow targets a real actor, not the Application.
		if ( ! empty( $user_ids ) ) {
			return;
		}

		if ( empty( $activity['object'] ) || ! Application::is_application_resource( object_to_uri( $activity['object'] ) ) ) {
			return;
		}

		$actor        = object_to_uri( $activity['actor'] );
		$remote_actor = Remote_Actors::fetch_by_uri( $actor );

		if ( \is_wp_error( $remote_actor ) ) {
			return;
		}

		$inbox = \get_post_meta( $remote_actor->ID, '_activitypub_inbox', true );

		if ( ! $inbox ) {
			return;
		}

		// Only send minimal data.
		$origin_activity = \array_intersect_key(
			$activity,
			array(
				'id'     => 1,
				'type'   => 1,
				'actor'  => 1,
				'object' => 1,
			)
		);

		$reject = new Activity();
		$reject->set_type( 'Reject' );
		$reject->set_id( Application::get_id() . '#reject-' . \md5( \wp_json_encode( $origin_activity ) ) );
		$reject->set_actor( Application::get_id() );
		$reject->set_object( $origin_activity );
		$reject->set_to( array( $actor ) );

		Http::post( $inbox, $reject->to_json(), null );
	}
}
