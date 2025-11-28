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
		if ( \get_option( 'activitypub_relay_mode', false ) ) {
			\add_action( 'activitypub_handled_create', array( self::class, 'handle_activity' ), 10, 3 );
			\add_action( 'activitypub_handled_update', array( self::class, 'handle_activity' ), 10, 3 );
			\add_action( 'activitypub_handled_delete', array( self::class, 'handle_activity' ), 10, 3 );
			\add_action( 'activitypub_handled_announce', array( self::class, 'handle_activity' ), 10, 3 );
		}
	}

	/**
	 * Handle incoming activity and relay if needed.
	 *
	 * @param array $activity The activity data.
	 * @param array $user_ids The user IDs that are recipients.
	 * @param bool  $success  Whether the activity was handled successfully.
	 */
	public static function handle_activity( $activity, $user_ids, $success ) {
		// Only relay if: successfully handled, Blog actor is recipient, activity is public, and in single-user mode.
		if (
			! $success ||
			! in_array( Actors::BLOG_USER_ID, (array) $user_ids, true ) ||
			! is_activity_public( $activity ) ||
			! is_single_user()
		) {
			return;
		}

		// Create Announce wrapper.
		$announce = new Activity();
		$announce->set_type( 'Announce' );
		$announce->set_actor( Actors::BLOG_USER_ID );
		$announce->set_object( $activity );
		$announce->set_published( gmdate( ACTIVITYPUB_DATE_TIME_RFC3339 ) );

		// Add to outbox for distribution. The outbox will generate the ID.
		Outbox::add( $announce, Actors::BLOG_USER_ID );
	}
}
