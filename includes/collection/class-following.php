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
	 * @param int    $user_id The ID of the WordPress User.
	 * @param string $actor   The Actor URL.
	 *
	 * @return int|WP_Error The ID of the Actor or a WP_Error.
	 */
	public static function follow( $user_id, $actor ) {
		$actor = Http::get_remote_object( $actor );

		if ( is_wp_error( $actor ) ) {
			return $actor;
		}

		$id = Actors::upsert( $actor );

		if ( is_wp_error( $id ) ) {
			return $id;
		}

		$following = \get_user_meta( $user_id, self::FOLLOWING_META_KEY, false );

		if ( ! is_array( $following ) ) {
			$following = array();
		}

		if ( ! in_array( $id, $following, true ) ) {
			\update_user_meta( $user_id, self::FOLLOWING_META_KEY, $id );
		}

		return $id;
	}
}
