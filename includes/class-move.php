<?php
/**
 * Move class file.
 *
 * @package Activitypub
 */

namespace Activitypub;

use Activitypub\Collection\Actors;

/**
 * ActivityPub (Account) Move Class
 *
 * @author Matthias Pfefferle
 */
class Move {
	/**
	 * Initialize the class, registering WordPress hooks
	 */
	public static function init() {
		\add_filter( 'activitypub_activity_user_object_array', array( self::class, 'extend_actor_profiles' ), 10, 3 );
	}

	/**
	 * Extend the actor profiles and add the "movedTo" and "alsoKnownAs" properties.
	 *
	 * @param array $actor The Actor-Profile.
	 * @param int   $id    The Activity-ID.
	 * @param mixed $user  The WordPress-User.
	 *
	 * @return array the extended actor profile
	 */
	public static function extend_actor_profiles( $actor, $id, $user ) {
		// Check if the user is a valid user object.
		if ( ! $user instanceof \Activitypub\Model\User ) {
			return $actor;
		}

		$move_to = \get_user_option( 'activitypub_move_to', $user->get__id() );

		if ( $move_to ) {
			$actor['movedTo'] = $move_to;
		}

		$also_known_as = \get_user_option( 'activitypub_also_known_as', $user->get__id() );

		if ( $also_known_as ) {
			$actor['alsoKnownAs'] = (array) $also_known_as;
		}

		return $actor;
	}

	/**
	 * Move an ActivityPub account from one location to another.
	 *
	 * @param string $from The current account URL.
	 * @param string $to   The new account URL.
	 * @return int|bool The ID of the outbox item or false on failure.
	 */
	public static function account( $from, $to ) {
		$user = Actors::get_by_resource( $from );

		if ( ! $user ) {
			return false;
		}

		// Update the movedTo property.
		\update_user_option( $user->get__id(), 'activitypub_move_to', $to );

		// Add the old account URL to alsoKnownAs.
		$also_known_as   = (array) \get_user_option( 'activitypub_also_known_as', $user->get__id() );
		$also_known_as[] = $from;
		$also_known_as   = array_unique( $also_known_as );
		\update_user_option( $user->get__id(), 'activitypub_also_known_as', $also_known_as );

		// Create Move activity.
		$activity = array(
			'@context' => get_context(),
			'type'     => 'Move',
			'actor'    => $from,
			'object'   => $from,
			'target'   => $to,
		);

		// Add to outbox.
		return add_to_outbox( $activity, 'Move', $user->get__id() );
	}
}
