<?php
/**
 * Following collection file.
 *
 * @package Activitypub
 */

namespace Activitypub\Collection;

use Activitypub\Activity\Activity;

use function Activitypub\add_to_outbox;

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
	 * Pending Status.
	 *
	 * @var string
	 */
	const PENDING = 'pending';

	/**
	 * Accepted Status.
	 *
	 * @var string
	 */
	const ACCEPTED = 'accepted';

	/**
	 * All Status.
	 *
	 * @var string
	 */
	const ALL = 'all';

	/**
	 * Follow a user.
	 *
	 * Please do not use this method directly, use `\Activitypub\follow` instead.
	 *
	 * @see \Activitypub\follow
	 *
	 * @param \WP_Post|int $post    The ID of the remote Actor.
	 * @param int          $user_id The ID of the WordPress User.
	 *
	 * @return int|false|\WP_Post|\WP_Error The Outbox ID or false on failure, the Actor post or a WP_Error.
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
			$actor = Actors::get_by_id( $user_id );

			if ( \is_wp_error( $actor ) ) {
				return $actor;
			}

			\add_post_meta( $post->ID, self::PENDING_META_KEY, (string) $user_id );

			$follow = new Activity();
			$follow->set_type( 'Follow' );
			$follow->set_actor( $actor->get_id() );
			$follow->set_object( $post->guid );
			$follow->set_to( array( $post->guid ) );

			return add_to_outbox( $follow, null, $user_id, ACTIVITYPUB_CONTENT_VISIBILITY_PRIVATE );
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
	 * Please do not use this method directly, use `\Activitypub\unfollow` instead.
	 *
	 * @see \Activitypub\unfollow
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

		if ( $post_id_query->posts ) {
			Outbox::undo( $post_id_query->posts[0] );
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
			'post_type'      => Remote_Actors::POST_TYPE,
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
		$following = \array_filter( $query->posts );

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
		return self::get_following_with_count( $user_id, 1 )['total'];
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
	public static function get_pending_with_count( $user_id, $number = -1, $page = null, $args = array() ) {
		$defaults = array(
			'post_type'      => Remote_Actors::POST_TYPE,
			'posts_per_page' => $number,
			'paged'          => $page,
			'orderby'        => 'ID',
			'order'          => 'DESC',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'meta_query'     => array(
				array(
					'key'   => self::PENDING_META_KEY,
					'value' => $user_id,
				),
			),
		);

		$args      = \wp_parse_args( $args, $defaults );
		$query     = new \WP_Query( $args );
		$total     = $query->found_posts;
		$following = \array_filter( $query->posts );

		return \compact( 'following', 'total' );
	}

	/**
	 * Get the pending followings of a given user.
	 *
	 * @param int|null $user_id The ID of the WordPress User.
	 * @param int      $number  Maximum number of results to return.
	 * @param int      $page    Page number.
	 * @param array    $args    The WP_Query arguments.
	 *
	 * @return \WP_Post[] List of `Following` objects.
	 */
	public static function get_pending( $user_id, $number = -1, $page = null, $args = array() ) {
		$data = self::get_pending_with_count( $user_id, $number, $page, $args );

		return $data['following'];
	}

	/**
	 * Get the total number of pending followings of a given user.
	 *
	 * @param int|null $user_id The ID of the WordPress User.
	 *
	 * @return int The total number of pending followings.
	 */
	public static function count_pending( $user_id ) {
		return self::get_pending_with_count( $user_id, 1 )['total'];
	}

	/**
	 * Get all followings of a given user.
	 *
	 * @param int|null $user_id The ID of the WordPress User.
	 * @param int      $number  Maximum number of results to return.
	 * @param int      $page    Page number.
	 * @param array    $args    The WP_Query arguments.
	 *
	 * @return array {
	 *      Data about the followings.
	 *
	 *      @type \WP_Post[] $followers List of `Follower` objects.
	 *      @type int $total Total number of followers.
	 * }
	 */
	public static function get_all_with_count( $user_id, $number = -1, $page = null, $args = array() ) {
		$defaults = array(
			'post_type'      => Remote_Actors::POST_TYPE,
			'posts_per_page' => $number,
			'paged'          => $page,
			'orderby'        => 'ID',
			'order'          => 'DESC',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'meta_query'     => array(
				'relation' => 'OR',
				array(
					'key'   => self::FOLLOWING_META_KEY,
					'value' => $user_id,
				),
				array(
					'key'   => self::PENDING_META_KEY,
					'value' => $user_id,
				),
			),
		);

		$args      = \wp_parse_args( $args, $defaults );
		$query     = new \WP_Query( $args );
		$total     = $query->found_posts;
		$following = \array_filter( $query->posts );

		return \compact( 'following', 'total' );
	}

	/**
	 * Get all followings of a given user.
	 *
	 * @param int|null $user_id The ID of the WordPress User.
	 *
	 * @return \WP_Post[] List of `Following` objects.
	 */
	public static function get_all( $user_id ) {
		return self::get_all_with_count( $user_id )['following'];
	}

	/**
	 * Get the total number of all followings of a given user.
	 *
	 * @param int|null $user_id The ID of the WordPress User.
	 *
	 * @return int The total number of all followings.
	 */
	public static function count_all( $user_id ) {
		return self::get_all_with_count( $user_id, 1 )['total'];
	}

	/**
	 * Get the total number of followings of a given user.
	 *
	 * @param int|null $user_id The ID of the WordPress User.
	 *
	 * @return array Total number of followings and pending followings.
	 */
	public static function count( $user_id ) {
		return array(
			self::ALL      => self::get_all_with_count( $user_id, 1 )['total'],
			self::ACCEPTED => self::get_following_with_count( $user_id, 1 )['total'],
			self::PENDING  => self::get_pending_with_count( $user_id, 1 )['total'],
		);
	}

	/**
	 * Check the status of a given following.
	 *
	 * @param int $user_id The ID of the WordPress User.
	 * @param int $post_id The ID of the Post.
	 *
	 * @return string|false The status of the following.
	 */
	public static function check_status( $user_id, $post_id ) {
		$all_meta  = get_post_meta( $post_id );
		$following = $all_meta[ self::FOLLOWING_META_KEY ] ?? array();
		$pending   = $all_meta[ self::PENDING_META_KEY ] ?? array();

		if ( \in_array( (string) $user_id, $following, true ) ) {
			return self::ACCEPTED;
		}

		if ( \in_array( (string) $user_id, $pending, true ) ) {
			return self::PENDING;
		}

		return false;
	}

	/**
	 * Remove blocked actors from following list.
	 *
	 * @see \Activitypub\Activitypub::init()
	 *
	 * @param string $value   The blocked actor URI or domain/keyword.
	 * @param string $type    The block type (actor, domain, keyword).
	 * @param int    $user_id The user ID.
	 */
	public static function remove_blocked_actors( $value, $type, $user_id ) {
		if ( 'actor' !== $type ) {
			return;
		}

		$actor_id = Actors::get_id_by_various( $value );
		if ( \is_wp_error( $actor_id ) ) {
			return;
		}

		self::unfollow( $actor_id, $user_id );
	}
}
