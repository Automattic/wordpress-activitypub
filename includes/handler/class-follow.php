<?php
namespace Activitypub\Handler;

use Activitypub\Http;
use Activitypub\Notification;
use Activitypub\Activity\Activity;
use Activitypub\Collection\Users;
use Activitypub\Collection\Followers;

/**
 * Handle Follow requests
 */
class Follow {
	/**
	 * Initialize the class, registering WordPress hooks
	 */
	public static function init() {
		\add_action(
			'activitypub_inbox_follow',
			array( self::class, 'handle_follow' )
		);

		\add_action(
			'activitypub_followers_post_follow',
			array( self::class, 'send_follow_response' ),
			10,
			4
		);
	}

	/**
	 * Handle "Follow" requests
	 *
	 * @param array $activity The activity object
	 * @param int   $user_id  The user ID
	 */
	public static function handle_follow( $activity ) {
		$user = Users::get_by_resource( $activity['object'] );

		if ( ! $user || is_wp_error( $user ) ) {
			// If we can not find a user,
			// we can not initiate a follow process
			return;
		}

		$user_id = $user->get__id();

		// save follower
		$follower = Followers::add_follower(
			$user_id,
			$activity['actor']
		);

		do_action(
			'activitypub_followers_post_follow',
			$activity['actor'],
			$activity,
			$user_id,
			$follower
		);

		// send notification
		$notification = new Notification(
			'follow',
			$activity['actor'],
			$activity,
			$user_id
		);
		$notification->send();
	}

	/**
	 * Send Accept response
	 *
	 * @param string                     $actor    The Actor URL
	 * @param array                      $object   The Activity object
	 * @param int                        $user_id  The ID of the WordPress User
	 * @param Activitypub\Model\Follower $follower The Follower object
	 *
	 * @return void
	 */
	public static function send_follow_response( $actor, $object, $user_id, $follower ) {
		if ( \is_wp_error( $follower ) ) {
			// it is not even possible to send a "Reject" because
			// we can not get the Remote-Inbox
			return;
		}

		// only send minimal data
		$object = array_intersect_key(
			$object,
			array_flip(
				array(
					'id',
					'type',
					'actor',
					'object',
				)
			)
		);

		$user = Users::get_by_id( $user_id );

		// get inbox
		$inbox = $follower->get_shared_inbox();

		// send "Accept" activity
		$activity = new Activity();
		$activity->set_type( 'Accept' );
		$activity->set_object( $object );
		$activity->set_actor( $user->get_id() );
		$activity->set_to( $actor );
		$activity->set_id( $user->get_id() . '#follow-' . \preg_replace( '~^https?://~', '', $actor ) . '-' . \time() );

		$activity = $activity->to_json();

		Http::post( $inbox, $activity, $user_id );
	}
}
