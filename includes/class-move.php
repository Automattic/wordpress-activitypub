<?php
/**
 * Move class file.
 *
 * @package Activitypub
 */

namespace Activitypub;

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
}
