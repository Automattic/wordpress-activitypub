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
		return Actors::get_by_id(  $this->item->ID );
	}

	/**
	 * Get the Actor ID.
	 *
	 * @return string The Actor ID.
	 */
	public function to_id() {
		return Actors::get_by_id( $this->item->ID )->get_id();
	}
}
