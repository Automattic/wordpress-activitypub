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
	 * @param \WP_Post|int $post    The ID of the remote Actor.
	 * @param int          $user_id The ID of the WordPress User.
	 *
	 * @return \WP_Post|\WP_Error The ID of the Actor or a WP_Error.
	 */
	public static function follow( $post, $user_id ) {
		$post = \get_post( $post );

		if ( ! $post ) {
			return new \WP_Error( 'activitypub_remote_actor_not_found', 'Remote actor not found' );
		}

		$all_meta  = get_post_meta( $post->ID );
		$following = $all_meta[ self::FOLLOWING_META_KEY ] ?? array();
		$pending   = $all_meta[ self::PENDING_META_KEY ] ?? array();

		if ( ! \in_array( (string) $user_id, $following, true ) && ! \in_array( (string) $user_id, $pending, true ) ) {
			\add_post_meta( $post->ID, self::PENDING_META_KEY, (string) $user_id );
		}

		return $post;
	}

	/**
	 * Accept a follow request.
	 *
	 * @param \WP_Post|int $post    The ID of the remote Actor.
	 * @param int          $user_id The ID of the WordPress User.
	 *
	 * @return \WP_Post|\WP_Error The ID of the Actor or a WP_Error.
	 */
	public static function accept( $post, $user_id ) {
		$post = \get_post( $post );

		if ( ! $post ) {
			return new \WP_Error( 'activitypub_remote_actor_not_found', 'Remote actor not found' );
		}

		$following = \get_post_meta( $post->ID, self::PENDING_META_KEY, false );

		if ( ! \is_array( $following ) || ! \in_array( (string) $user_id, $following, true ) ) {
			return new \WP_Error( 'activitypub_following_not_found', 'Follow request not found' );
		}

		\add_post_meta( $post->ID, self::FOLLOWING_META_KEY, $user_id );
		\delete_post_meta( $post->ID, self::PENDING_META_KEY, $user_id );

		return $post;
	}

	/**
	 * Reject a follow request.
	 *
	 * @param \WP_Post|int $post    The ID of the remote Actor.
	 * @param int          $user_id The ID of the WordPress User.
	 *
	 * @return \WP_Post|\WP_Error The ID of the Actor or a WP_Error.
	 */
	public static function reject( $post, $user_id ) {
		$post = \get_post( $post );

		if ( ! $post ) {
			return new \WP_Error( 'activitypub_remote_actor_not_found', 'Remote actor not found' );
		}

		\delete_post_meta( $post->ID, self::PENDING_META_KEY, $user_id );
		\delete_post_meta( $post->ID, self::FOLLOWING_META_KEY, $user_id );

		return $post;
	}
}
