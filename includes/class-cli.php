<?php
namespace Activitypub;

use WP_CLI;
use WP_CLI_Command;
use Activitypub\Collection\Users;
use Activitypub\Activity\Activity;
use Activitypub\Collection\Followers;

/**
 * The ActivityPub CLI
 */
class Cli extends WP_CLI_Command {

	/**
	 * Re-Send Accept Activity as response to a Follow
	 *
	 * ## OPTIONS
	 *
	 * [--follower=<url>]
	 * : The Follower ID, this is generally a URL
	 *
	 * [--user_id=<id>]
	 * : The ActivityPub User ID
	 *
	 * ## EXAMPLES
	 *
	 *     # Re-Send Accept Activity as response to a Follow
	 *     $ wp activitypub accept --follower=https://mastodon.social/@pfefferle --user=1
	 */
	public function accept( $args, $assoc_args ) {
		$user_id = (int) $assoc_args['user_id'];
		$user    = Users::get_by_id( $user_id );

		if ( ! $user ) {
			WP_CLI::error( __( 'User is not allow to send Activities', 'activitypub' ) );
		}

		$follower = esc_url( $assoc_args['follower'] );
		$follower = Followers::get_follower( $user->get__id(), $follower );

		if ( ! $follower ) {
			WP_CLI::error( __( 'Unknown Follower', 'activitypub' ) );
		}

		// get inbox
		$inbox = $follower->get_shared_inbox();

		$object = array(
			'id'     => $follower->get_id(),
			'type'   => 'Follow',
			'actor'  => $follower->get_id(),
			'object' => $user->get_id(),
		);

		// send "Accept" activity
		$activity = new Activity();
		$activity->set_type( 'Accept' );
		$activity->set_object( $object );
		$activity->set_actor( $user->get_id() );
		$activity->set_to( $follower->get_id() );
		$activity->set_id( $user->get_id() . '#follow-' . \preg_replace( '~^https?://~', '', $follower->get_id() ) . '-' . \time() );

		$activity = $activity->to_json();

		$response = Http::post( $inbox, $activity, $user_id );

		if ( is_wp_error( $response ) ) {
			WP_CLI::error( $response->get_error_message() );
		} else {
			WP_CLI::success( __( 'Activity sent', 'activitypub' ) );
		}
	}
}
