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
		$activity_object = $this->transform_object_properties( Actors::get_by_id( $this->item->ID ) );

		if ( \is_wp_error( $activity_object ) ) {
			return $activity_object;
		}

		$activity_object = $this->set_audience( $activity_object );

		return $activity_object;
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
