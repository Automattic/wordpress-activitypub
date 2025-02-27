<?php
/**
 * Move class file.
 *
 * @package Activitypub
 */

namespace Activitypub;

use Activitypub\Activity\Actor;
use Activitypub\Collection\Actors;

/**
 * ActivityPub (Account) Move Class
 *
 * @author Matthias Pfefferle
 */
class Move {
	/**
	 * Move an ActivityPub account from one location to another.
	 *
	 * @param string $from The current account URL.
	 * @param string $to   The new account URL.
	 *
	 * @return int|bool|\WP_Error The ID of the outbox item or false or WP_Error on failure.
	 */
	public static function account( $from, $to ) {
		$user = Actors::get_by_resource( $from );

		if ( \is_wp_error( $user ) ) {
			return $user;
		}

		// Update the movedTo property.
		if ( $user->get__id() > 0 ) {
			\update_user_option( $user->get__id(), 'activitypub_move_to', $to );
		} else {
			\update_option( 'activitypub_blog_user_moved_to', $to );
		}

		// Add the old account URL to alsoKnownAs.
		$also_known_as   = (array) \get_user_option( 'activitypub_also_known_as', $user->get__id() );
		$also_known_as[] = $from;
		$also_known_as   = \array_unique( $also_known_as );

		if ( $user->get__id() > 0 ) {
			\update_user_option( $user->get__id(), 'activitypub_also_known_as', $also_known_as );
		} else {
			\update_option( 'activitypub_blog_user_also_known_as', $also_known_as );
		}

		$response = Http::get_remote_object( $to );

		if ( \is_wp_error( $response ) ) {
			return $response;
		}

		$actor = new Actor();
		$actor->from_array( $response );

		// Check if the `Move` Activity is valid.
		$also_known_as = $actor->get_also_known_as();
		if ( ! in_array( $from, $also_known_as, true ) ) {
			return new \WP_Error( 'invalid_target', __( 'Invalid target', 'activitypub' ) );
		}

		// Add to outbox.
		return add_to_outbox( $actor, 'Move', $user->get__id(), ACTIVITYPUB_CONTENT_VISIBILITY_PUBLIC );
	}
}
