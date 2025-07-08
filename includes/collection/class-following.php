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
	 * Meta key for the following user ID.
	 *
	 * @var string
	 */
	const FOLLOWING_META_KEY = '_activitypub_followed_by';

	/**
	 * Meta key for pending following user ID.
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

	/**
	 * Remove a follow request.
	 *
	 * @param \WP_Post|int $post    The ID of the remote Actor.
	 * @param int          $user_id The ID of the WordPress User.
	 *
	 * @return \WP_Post|\WP_Error The Actor post or a WP_Error.
	 */
	public static function unfollow( $post, $user_id ) {
		$post = \get_post( $post );

		if ( ! $post ) {
			return new \WP_Error( 'activitypub_remote_actor_not_found', __( 'Remote actor not found', 'activitypub' ) );
		}

		$actor_type = Actors::get_type_by_id( $user_id );

		\delete_post_meta( $post->ID, self::FOLLOWING_META_KEY, $user_id );
		\delete_post_meta( $post->ID, self::PENDING_META_KEY, $user_id );

		// Get Post-ID of the Follow Outbox Activity.
		$post_id_query = new \WP_Query(
			array(
				'post_type'      => Outbox::POST_TYPE,
				'nopaging'       => true,
				'posts_per_page' => 1,
				'author'         => \max( $user_id, 0 ),
				'fields'         => 'ids',
				'number'         => 1,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_query'     => array(
					array(
						'key'   => '_activitypub_object_id',
						'value' => $post->guid,
					),
					array(
						'key'   => '_activitypub_activity_type',
						'value' => 'Follow',
					),
					array(
						'key'   => '_activitypub_activity_actor',
						'value' => $actor_type,
					),
				),
			)
		);

		$post_ids = $post_id_query->get_posts();

		if ( $post_ids ) {
			Outbox::undo( $post_ids[0] );
		}

		return $post;
	}

	/**
	 * Get the Followings of a given user, along with a total count for pagination purposes.
	 *
	 * @param int|null $user_id The ID of the WordPress User.
	 * @param int      $number  Maximum number of results to return.
	 * @param int      $page    Page number.
	 * @param array    $args    The WP_Query arguments.
	 *
	 * @return array {
	 *      Data about the followings.
	 *
	 *      @type \WP_Post[] $followings List of `Following` objects.
	 *      @type int        $total      Total number of followings.
	 *  }
	 */
	public static function get_following_with_count( $user_id, $number = -1, $page = null, $args = array() ) {
		$defaults = array(
			'post_type'      => Actors::POST_TYPE,
			'posts_per_page' => $number,
			'paged'          => $page,
			'orderby'        => 'ID',
			'order'          => 'DESC',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'meta_query'     => array(
				array(
					'key'   => self::FOLLOWING_META_KEY,
					'value' => $user_id,
				),
			),
		);

		$args      = \wp_parse_args( $args, $defaults );
		$query     = new \WP_Query( $args );
		$total     = $query->found_posts;
		$following = \array_filter( $query->get_posts() );

		return \compact( 'following', 'total' );
	}

	/**
	 * Get the Followings of a given user.
	 *
	 * @param int|null $user_id The ID of the WordPress User.
	 * @param int      $number  Maximum number of results to return.
	 * @param int      $page    Page number.
	 * @param array    $args    The WP_Query arguments.
	 *
	 * @return \WP_Post[] List of `Following` objects.
	 */
	public static function get_following( $user_id, $number = -1, $page = null, $args = array() ) {
		$data = self::get_following_with_count( $user_id, $number, $page, $args );

		return $data['following'];
	}

	/**
	 * Get the total number of followings of a given user.
	 *
	 * @param int|null $user_id The ID of the WordPress User.
	 *
	 * @return int The total number of followings.
	 */
	public static function count_following( $user_id ) {
		return self::get_following_with_count( $user_id )['total'];
	}
}
