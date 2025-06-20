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
	 * Meta key for pending followers user ID.
	 *
	 * @var string
	 */
	const PENDING_META_KEY = '_activitypub_followed_by_pending';

	/**
	 * Follow a user.
	 *
	 * @param int $remote_actor_id The ID of the remote Actor.
	 * @param int $user_id         The ID of the WordPress User.
	 *
	 * @return int|\WP_Error The ID of the Actor or a WP_Error.
	 */
	public static function follow( $remote_actor_id, $user_id ) {
		$remote_actor = Actors::get_remote_by_uri( $remote_actor_id );

		if ( \is_wp_error( $remote_actor ) ) {
			return $remote_actor;
		}

		$following = \get_post_meta( $remote_actor->ID, self::FOLLOWING_META_KEY, false );

		if ( ! \is_array( $following ) ) {
			$following = array();
		}

		if ( ! \in_array( $user_id, $following, true ) ) {
			\update_post_meta( $remote_actor->ID, self::FOLLOWING_META_KEY, $user_id );
		}

		return $remote_actor_id;
	}

	/**
	 * Accept a follow request.
	 *
	 * @param int $remote_actor_id The ID of the remote Actor.
	 * @param int $user_id         The ID of the WordPress User.
	 *
	 * @return int|\WP_Error The ID of the Actor or a WP_Error.
	 */
	public static function accept( $remote_actor_id, $user_id ) {
		$remote_actor = Actors::get_remote_by_uri( $remote_actor_id );

		if ( \is_wp_error( $remote_actor ) ) {
			return $remote_actor;
		}

		$following = \get_post_meta( $remote_actor->ID, self::PENDING_META_KEY, false );

		if ( ! \is_array( $following ) || ! \in_array( $user_id, $following, true ) ) {
			return new \WP_Error( 'activitypub_following_not_found', 'Follow request not found' );
		}

		\add_post_meta( $remote_actor->ID, self::FOLLOWING_META_KEY, $user_id );
		\delete_post_meta( $remote_actor->ID, self::PENDING_META_KEY, $user_id );

		return $remote_actor_id;
	}
}
