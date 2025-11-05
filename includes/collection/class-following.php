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
	 * @return int|\WP_Error The Outbox ID on success or a WP_Error on failure.
	 */
	public static function follow( $post, $user_id ) {
		$post = \get_post( $post );

		if ( ! $post ) {
			return new \WP_Error( 'activitypub_remote_actor_not_found', 'Remote actor not found' );
		}

		$all_meta  = get_post_meta( $post->ID );
		$following = $all_meta[ self::FOLLOWING_META_KEY ] ?? array();
		$pending   = $all_meta[ self::PENDING_META_KEY ] ?? array();

		if ( \in_array( (string) $user_id, $following, true ) || \in_array( (string) $user_id, $pending, true ) ) {
			$post_id_query = new \WP_Query(
				array(
					'post_type'      => Outbox::POST_TYPE,
					'post_status'    => 'any',
					'posts_per_page' => 1,
					'no_found_rows'  => true,
					'author'         => \max( $user_id, 0 ),
					'fields'         => 'ids',
					'order'          => 'DESC',
					'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						array(
							'key'   => '_activitypub_object_id',
							'value' => $post->guid,
						),
						array(
							'key'   => '_activitypub_activity_type',
							'value' => 'Follow',
						),
					),
				)
			);

			if ( $post_id_query->posts ) {
				return $post_id_query->posts[0];
			}

			return new \WP_Error( 'activitypub_already_following', 'User is already following this actor but outbox activity not found.' );
		}

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

		$result = add_to_outbox( $follow, null, $user_id, ACTIVITYPUB_CONTENT_VISIBILITY_PRIVATE );

		if ( ! $result ) {
			return new \WP_Error( 'activitypub_follow_failed', 'Failed to add follow activity to outbox.' );
		}

		return $result;
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
	 * Query followings of a given user, with pagination info.
	 *
	 * @param int|null $user_id The ID of the WordPress User.
	 * @param int      $number  Maximum number of results to return.
	 * @param int      $page    Page number.
	 * @param array    $args    The WP_Query arguments.
	 *
	 * @return array {
	 *      Data about the followings.
	 *
	 *      @type \WP_Post[] $following List of `Following` objects.
	 *      @type int        $total     Total number of followings.
	 *  }
	 */
	public static function query( $user_id, $number = -1, $page = null, $args = array() ) {
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
	 * Get many followings of a given user.
	 *
	 * @param int|null $user_id The ID of the WordPress User.
	 * @param int      $number  Maximum number of results to return.
	 * @param int      $page    Page number.
	 * @param array    $args    The WP_Query arguments.
	 *
	 * @return \WP_Post[] List of `Following` objects.
	 */
	public static function get_many( $user_id, $number = -1, $page = null, $args = array() ) {
		$data = self::query( $user_id, $number, $page, $args );

		return $data['following'];
	}

	/**
	 * Query pending followings of a given user, with pagination info.
	 *
	 * @param int|null $user_id The ID of the WordPress User.
	 * @param int      $number  Maximum number of results to return.
	 * @param int      $page    Page number.
	 * @param array    $args    The WP_Query arguments.
	 *
	 * @return array {
	 *      Data about the pending followings.
	 *
	 *      @type \WP_Post[] $following List of `Following` objects.
	 *      @type int        $total     Total number of pending followings.
	 *  }
	 */
	public static function query_pending( $user_id, $number = -1, $page = null, $args = array() ) {
		$defaults = array(
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'meta_query' => array(
				array(
					'key'   => self::PENDING_META_KEY,
					'value' => $user_id,
				),
			),
		);

		$args = \wp_parse_args( $args, $defaults );

		return self::query( $user_id, $number, $page, $args );
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
		return self::query_pending( $user_id, $number, $page, $args )['following'];
	}

	/**
	 * Get the total number of pending followings of a given user.
	 *
	 * @param int|null $user_id The ID of the WordPress User.
	 *
	 * @return int The total number of pending followings.
	 */
	public static function count_pending( $user_id ) {
		return self::query_pending( $user_id, 1 )['total'];
	}

	/**
	 * Query all followings of a given user (both accepted and pending), with pagination info.
	 *
	 * @param int|null $user_id The ID of the WordPress User.
	 * @param int      $number  Maximum number of results to return.
	 * @param int      $page    Page number.
	 * @param array    $args    The WP_Query arguments.
	 *
	 * @return array {
	 *      Data about all followings.
	 *
	 *      @type \WP_Post[] $following List of `Following` objects.
	 *      @type int        $total     Total number of all followings.
	 * }
	 */
	public static function query_all( $user_id, $number = -1, $page = null, $args = array() ) {
		$defaults = array(
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'meta_query' => array(
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

		$args = \wp_parse_args( $args, $defaults );

		return self::query( $user_id, $number, $page, $args );
	}

	/**
	 * Get partial followers collection for a specific instance.
	 *
	 * Returns only followers whose ID shares the specified URI authority.
	 * Used for FEP-8fcf synchronization.
	 *
	 * @param int    $user_id   The user ID whose followers to get.
	 * @param string $authority The URI authority (scheme + host) to filter by.
	 * @param string $state     The following state to filter by (accepted or pending). Default is accepted.
	 *
	 * @return array Array of follower URLs.
	 */
	public static function get_by_authority( $user_id, $authority, $state = self::FOLLOWING_META_KEY ) {
		$posts = new \WP_Query(
			array(
				'post_type'      => Remote_Actors::POST_TYPE,
				'posts_per_page' => -1,
				'orderby'        => 'ID',
				'order'          => 'DESC',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_query'     => array(
					'relation' => 'AND',
					array(
						'key'   => $state,
						'value' => $user_id,
					),
					array(
						'key'     => '_activitypub_inbox',
						'compare' => 'LIKE',
						'value'   => $authority,
					),
				),
			)
		);

		return $posts->posts ?? array();
	}

	/**
	 * Get all followings of a given user.
	 *
	 * @param int|null $user_id The ID of the WordPress User.
	 *
	 * @return \WP_Post[] List of `Following` objects.
	 */
	public static function get_all( $user_id ) {
		return self::query_all( $user_id, -1 )['following'];
	}

	/**
	 * Get the total number of all followings of a given user.
	 *
	 * @param int|null $user_id The ID of the WordPress User.
	 *
	 * @return int The total number of all followings.
	 */
	public static function count_all( $user_id ) {
		return self::query_all( $user_id, 1 )['total'];
	}

	/**
	 * Count the total number of followings.
	 *
	 * @param int $user_id The ID of the WordPress User.
	 *
	 * @return int The number of Followings
	 */
	public static function count( $user_id ) {
		return self::query( $user_id, 1 )['total'];
	}

	/**
	 * Get the total number of followings of a given user by status.
	 *
	 * @param int|null $user_id The ID of the WordPress User.
	 *
	 * @return array Total number of followings and pending followings.
	 */
	public static function count_by_status( $user_id ) {
		return array(
			self::ALL      => self::count_all( $user_id ),
			self::ACCEPTED => self::count( $user_id ),
			self::PENDING  => self::count_pending( $user_id ),
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
	 * Get local user IDs following a given remote actor.
	 *
	 * @param string $actor_url The actor URL.
	 *
	 * @return int[] List of local user IDs following the actor.
	 */
	public static function get_follower_ids( $actor_url ) {
		$actor = Remote_Actors::get_by_uri( $actor_url );
		if ( \is_wp_error( $actor ) ) {
			return array();
		}

		$user_ids = \get_post_meta( $actor->ID, self::FOLLOWING_META_KEY, false );
		if ( ! is_array( $user_ids ) || empty( $user_ids ) ) {
			return array();
		}

		return array_map( 'intval', $user_ids );
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

	/**
	 * Get the Followings of a given user, along with a total count for pagination purposes.
	 *
	 * @deprecated unreleased Use {@see Following::query()}.
	 *
	 * @param int|null $user_id The ID of the WordPress User.
	 * @param int      $number  Maximum number of results to return.
	 * @param int      $page    Page number.
	 * @param array    $args    The WP_Query arguments.
	 *
	 * @return array {
	 *      Data about the followings.
	 *
	 *      @type \WP_Post[] $following List of `Following` objects.
	 *      @type int        $total     Total number of followings.
	 *  }
	 */
	public static function get_following_with_count( $user_id, $number = -1, $page = null, $args = array() ) {
		\_deprecated_function( __METHOD__, 'unreleased', 'Activitypub\Collection\Following::query' );

		return self::query( $user_id, $number, $page, $args );
	}

	/**
	 * Get pending followings of a given user, along with a total count for pagination purposes.
	 *
	 * @deprecated unreleased Use {@see Following::query_pending()}.
	 *
	 * @param int|null $user_id The ID of the WordPress User.
	 * @param int      $number  Maximum number of results to return.
	 * @param int      $page    Page number.
	 * @param array    $args    The WP_Query arguments.
	 *
	 * @return array {
	 *      Data about the pending followings.
	 *
	 *      @type \WP_Post[] $following List of `Following` objects.
	 *      @type int        $total     Total number of pending followings.
	 *  }
	 */
	public static function get_pending_with_count( $user_id, $number = -1, $page = null, $args = array() ) {
		\_deprecated_function( __METHOD__, 'unreleased', 'Activitypub\Collection\Following::query_pending' );

		return self::query_pending( $user_id, $number, $page, $args );
	}

	/**
	 * Get all followings of a given user (both accepted and pending), along with a total count for pagination purposes.
	 *
	 * @deprecated unreleased Use {@see Following::query_all()}.
	 *
	 * @param int|null $user_id The ID of the WordPress User.
	 * @param int      $number  Maximum number of results to return.
	 * @param int      $page    Page number.
	 * @param array    $args    The WP_Query arguments.
	 *
	 * @return array {
	 *      Data about all followings.
	 *
	 *      @type \WP_Post[] $following List of `Following` objects.
	 *      @type int        $total     Total number of all followings.
	 *  }
	 */
	public static function get_all_with_count( $user_id, $number = -1, $page = null, $args = array() ) {
		\_deprecated_function( __METHOD__, 'unreleased', 'Activitypub\Collection\Following::query_all' );

		return self::query_all( $user_id, $number, $page, $args );
	}

	/**
	 * Get the Followings of a given user.
	 *
	 * @deprecated unreleased Use {@see Following::get_many()}.
	 *
	 * @param int|null $user_id The ID of the WordPress User.
	 * @param int      $number  Maximum number of results to return.
	 * @param int      $page    Page number.
	 * @param array    $args    The WP_Query arguments.
	 *
	 * @return \WP_Post[] List of `Following` objects.
	 */
	public static function get_following( $user_id, $number = -1, $page = null, $args = array() ) {
		\_deprecated_function( __METHOD__, 'unreleased', 'Activitypub\Collection\Following::get_many' );

		return self::get_many( $user_id, $number, $page, $args );
	}
}
