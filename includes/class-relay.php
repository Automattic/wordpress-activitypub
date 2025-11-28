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
	 * Check if an activity should be relayed.
	 *
	 * @param Activity $activity  The activity to check.
	 * @param array    $user_ids  The user IDs that are recipients.
	 *
	 * @return bool True if should relay, false otherwise.
	 */
	public static function should_relay( $activity, $user_ids ) {
		// Check relay mode enabled.
		if ( ! get_option( 'activitypub_relay_mode', false ) ) {
			return false;
		}

		// Check Blog actor is recipient.
		if ( ! in_array( Actors::BLOG_USER_ID, (array) $user_ids, true ) ) {
			return false;
		}

		// Check activity is public.
		$to       = $activity->get_to();
		$cc       = $activity->get_cc();
		$audience = array_merge( (array) $to, (array) $cc );

		if ( ! in_array( 'https://www.w3.org/ns/activitystreams#Public', $audience, true ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Forward an activity to all followers except the sender.
	 *
	 * Wraps the activity in an Announce and sends it to all Blog actor followers.
	 *
	 * @param Activity $activity The activity to forward.
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
		$announce->set_object( $activity->to_array() );
		$announce->set_to( array( 'https://www.w3.org/ns/activitystreams#Public' ) );
		$announce->set_published( gmdate( ACTIVITYPUB_DATE_TIME_RFC3339 ) );

		// Generate unique ID for the Announce.
		$announce->set_id(
			add_query_arg(
				array(
					'p'      => 'relay',
					'time'   => time(),
					'object' => rawurlencode( $activity->get_id() ),
				),
				home_url( '/' )
			)
		);

		// Add to outbox for distribution.
		// The existing dispatcher will handle:
		// - Batching
		// - Domain blocklist filtering
		// - Delivery to follower inboxes
		// - Retry logic
		Outbox::add( $announce, Actors::BLOG_USER_ID );
	}
}
