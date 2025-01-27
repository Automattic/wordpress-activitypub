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
}
