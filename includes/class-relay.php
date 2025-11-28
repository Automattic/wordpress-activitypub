<?php
/**
 * ActivityPub Relay Class
 *
 * Handles forwarding of activities when relay mode is enabled.
 *
 * @package Activitypub
 */

namespace Activitypub;

use Activitypub\Activity\Activity;
use Activitypub\Collection\Actors;
use Activitypub\Collection\Outbox;

/**
 * ActivityPub Relay Class
 *
 * Provides relay functionality to forward public activities to all followers.
 */
class Relay {
	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		// Only add hooks if relay mode is enabled.
		if ( ! \get_option( 'activitypub_relay_mode', false ) ) {
			return;
		}

		\add_action( 'activitypub_handled_create', array( self::class, 'handle_activity' ), 10, 4 );
		\add_action( 'activitypub_handled_update', array( self::class, 'handle_activity' ), 10, 4 );
		\add_action( 'activitypub_handled_delete', array( self::class, 'handle_activity' ), 10, 4 );
		\add_action( 'activitypub_handled_announce', array( self::class, 'handle_activity' ), 10, 4 );
	}

	/**
	 * Handle incoming activity and relay if needed.
	 *
	 * @param array $activity The activity data.
	 * @param array $user_ids The user IDs that are recipients.
	 * @param bool  $success  Whether the activity was handled successfully.
	 * @param mixed $result   The result of the activity handling.
	 */
	public static function handle_activity( $activity, $user_ids, $success, $result ) {
		// Only relay successfully handled activities.
		if ( ! $success ) {
			return;
		}

		// Check if Blog actor is recipient.
		if ( ! in_array( Actors::BLOG_USER_ID, (array) $user_ids, true ) ) {
			return;
		}

		// Check if activity is public.
		if ( ! \Activitypub\is_activity_public( $activity ) ) {
			return;
		}

		self::forward_activity( $activity );
	}

	/**
	 * Forward an activity to all followers except the sender.
	 *
	 * Wraps the activity in an Announce and sends it to all Blog actor followers.
	 *
	 * @param array $activity The activity data array.
	 */
	public static function forward_activity( $activity ) {
		$blog_actor = Actors::get_by_id( Actors::BLOG_USER_ID );

		if ( is_wp_error( $blog_actor ) ) {
			return;
		}

		// Create Announce wrapper.
		$announce = new Activity();
		$announce->set_type( 'Announce' );
		$announce->set_actor( $blog_actor->get_id() );
		$announce->set_object( $activity );
		$announce->set_to( array( 'https://www.w3.org/ns/activitystreams#Public' ) );
		$announce->set_published( gmdate( ACTIVITYPUB_DATE_TIME_RFC3339 ) );

		// Add to outbox for distribution. The outbox will generate the ID.
		Outbox::add( $announce, Actors::BLOG_USER_ID );
	}
}
