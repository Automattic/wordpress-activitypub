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
	 * Transforms the WP_User object to an Actor.
	 *
	 * @see \Activitypub\Activity\Actor
	 *
	 * @return \Activitypub\Activity\Base_Object|\WP_Error The Actor or WP_Error on failure.
	 */
	public function to_object() {
		return $this->transform_object_properties( Actors::get_by_id( $this->item->ID ) );
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
