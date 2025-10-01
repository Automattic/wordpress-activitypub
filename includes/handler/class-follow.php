<?php
/**
 * Follow handler file.
 *
 * @package Activitypub
 */

namespace Activitypub\Handler;

use Activitypub\Activity\Activity;
use Activitypub\Collection\Actors;
use Activitypub\Collection\Followers;

use function Activitypub\add_to_outbox;

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
	}

	/**
	 * Handle "Follow" requests.
	 *
	 * @param array $activity The activity object.
	 * @param int   $user_id  The user ID.
	 */
	public static function handle_follow( $activity, $user_id ) {
		if ( Actors::APPLICATION_USER_ID === $user_id ) {
			self::queue_reject( $activity, $user_id );
			return;
		}

		// Save follower.
		$remote_actor = Followers::add_follower(
			$user_id,
			$activity['actor']
		);

		$success = ! \is_wp_error( $remote_actor );

		if ( ! \is_wp_error( $remote_actor ) ) {
			$remote_actor = \get_post( $remote_actor );
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
		 * @param int                $user_id      The local user ID.
		 * @param bool               $success      True on success, false otherwise.
		 * @param \WP_Post|\WP_Error $remote_actor The remote actor/follower, or WP_Error if failed.
		 */
		\do_action( 'activitypub_handled_follow', $activity, $user_id, $success, $remote_actor );
	}

	/**
	 * Send Accept response.
	 *
	 * @param array              $activity_object The ActivityPub activity data.
	 * @param int                $user_id         The local user ID.
	 * @param bool               $success         True on success, false otherwise.
	 * @param \WP_Post|\WP_Error $remote_actor    The remote actor/follower, or WP_Error if failed.
	 */
	public static function queue_accept( $activity_object, $user_id, $success, $remote_actor ) {
		if ( \is_wp_error( $remote_actor ) ) {
			// Impossible to send a "Reject" because we can not get the Remote-Inbox.
			return;
		}

		$actor = $activity_object['actor'];

		// Only send minimal data.
		$activity_object = array_intersect_key(
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
	 * Send Reject response.
	 *
	 * @param array $activity The Activity array.
	 * @param int   $user_id  The ID of the WordPress User.
	 */
	public static function queue_reject( $activity, $user_id ) {
		// Only send minimal data.
		$origin_activity = array_intersect_key(
			$activity,
			array(
				'id'     => 1,
				'type'   => 1,
				'actor'  => 1,
				'object' => 1,
			)
		);

		$activity = new Activity();
		$activity->set_type( 'Reject' );
		$activity->set_actor( Actors::get_by_id( $user_id )->get_id() );
		$activity->set_object( $origin_activity );
		$activity->set_to( array( $origin_activity['actor'] ) );

		add_to_outbox( $activity, null, $user_id, ACTIVITYPUB_CONTENT_VISIBILITY_PRIVATE );
	}
}
