<?php
namespace Activitypub\Handler;

use Activitypub\Http;
use Activitypub\Activity\Activity;
use Activitypub\Collection\Users;
use Activitypub\Collection\Followers;
use Activitypub\Model\Follow_Request;

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
			'activitypub_send_follow_response',
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
		$follower_id = Followers::add_follower(
			$user_id,
			$activity['actor']
		);

		if ( \is_wp_error( $follower_id ) ) {
			// it is not even possible to send a "Reject" or "Accept" because
			// we can not get the Remote-Inbox
			return;
		}

		// save follow request by this follower
		$follow_request = Follow_Request::save( $follower_id, $user_id, $activity['id'] );

		if ( ! $user->get_manually_approves_followers() ) {
			$follow_request->approve();
		}
	}

	/**
	 * Send Follow response
	 *
	 * @param Activitypub\Model\User     $user     The Target Users ActivityPub object
	 * @param Activitypub\Model\Follower $follower The Followers ActivityPub object
	 * @param array|object               $object   The ActivityPub follow object
	 * @param string                     $type     The reponse object type: 'Accept' or 'Reject'
	 *
	 * @return void
	 */
	public static function send_follow_response( $user, $inbox, $object, $type ) {
		// send activity
		$activity = new Activity();
		$activity->set_type( $type );
		$activity->set_object( $object );
		$activity->set_actor( $user->get_id() );
		$activity->set_to( $object['actor'] );
		$activity->set_id( $user->get_id() . '#accept-' . \preg_replace( '~^https?://~', '', $object['actor'] ) . '-' . \time() );

		$activity = $activity->to_json();

		Http::post( $inbox, $activity, $user->get__id() );
	}
}
