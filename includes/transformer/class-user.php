<?php
/**
 * User Transformer Class file.
 *
 * @package Activitypub
 */

namespace Activitypub\Transformer;

use Activitypub\Collection\Actors;

/**
 * User Transformer Class.
 */
class User extends Base {
	/**
	 * Transforms the WP_User object to an ActivityPub Object
	 *
	 * @see \Activitypub\Activity\Base_Object
	 *
	 * @return \Activitypub\Activity\Base_Object The ActivityPub Object
	 */
	public function to_object() {
		$user  = $this->wp_object;
		$actor = Actors::get_by_id( $user->ID );

		return $actor;
	}

	/**
	 * Get the User ID.
	 *
	 * @return int The User ID.
	 */
	public function get_id() {
		// TODO: Will be removed with the new Outbox implementation.
		return $this->wp_object->ID;
	}

	/**
	 * Change the User ID.
	 *
	 * @param int $user_id The new user ID.
	 *
	 * @return User The User Object.
	 */
	public function change_wp_user_id( $user_id ) {
		// TODO: Will be removed with the new Outbox implementation.
		$this->wp_object->ID = $user_id;

		return $this;
	}

	/**
	 * Get the WP_User ID.
	 *
	 * @return int The WP_User ID.
	 */
	public function get_wp_user_id() {
		// TODO: Will be removed with the new Outbox implementation.
		return $this->wp_object->ID;
	}
}
