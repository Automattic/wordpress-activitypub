<?php
/**
 * Following collection file.
 *
 * @package Activitypub
 */

namespace Activitypub\Collection;

use Activitypub\Http;

/**
 * ActivityPub Following Collection.
 */
class Following {
	/**
	 * Meta key for the followers user ID.
	 *
	 * @var string
	 */
	const FOLLOWING_META_KEY = '_activitypub_followed_by';

	/**
	 * Follow a user.
	 *
	 * @param int $remote_actor_id The ID of the remote Actor.
	 * @param int $user_id         The ID of the WordPress User.
	 *
	 * @return int|\WP_Error The ID of the Actor or a WP_Error.
	 */
	public static function follow( $remote_actor_id, $user_id ) {
		$following = \get_user_meta( $user_id, self::FOLLOWING_META_KEY, false );

		if ( ! is_array( $following ) ) {
			$following = array();
		}

		if ( ! in_array( $remote_actor_id, $following, true ) ) {
			\update_user_meta( $user_id, self::FOLLOWING_META_KEY, $remote_actor_id );
		}

		return $remote_actor_id;
	}
}
