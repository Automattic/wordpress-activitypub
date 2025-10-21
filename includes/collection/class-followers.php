<?php
/**
 * Followers collection file.
 *
 * @package Activitypub
 */

namespace Activitypub\Collection;

use Activitypub\Signature;
use Activitypub\Tombstone;

use function Activitypub\get_remote_metadata_by_actor;
use function Activitypub\get_rest_url_by_path;

/**
 * ActivityPub Followers Collection.
 *
 * @author Matt Wiebe
 * @author Matthias Pfefferle
 */
class Followers {
	/**
	 * Cache key for the followers inbox.
	 *
	 * @var string
	 */
	const CACHE_KEY_INBOXES = 'follower_inboxes_%s';

	/**
	 * Meta key for the followers user ID.
	 *
	 * @var string
	 */
	const FOLLOWER_META_KEY = '_activitypub_following';

	/**
	 * Add new Follower.
	 *
	 * @param int    $user_id The ID of the WordPress User.
	 * @param string $actor   The Actor URL.
	 *
	 * @return int|\WP_Error The Follower ID or an WP_Error.
	 */
	public static function add( $user_id, $actor ) {
		$meta = get_remote_metadata_by_actor( $actor );

		if ( Tombstone::exists( $meta ) ) {
			return $meta;
		}

		if ( empty( $meta ) || ! \is_array( $meta ) || \is_wp_error( $meta ) ) {
			return new \WP_Error( 'activitypub_invalid_follower', __( 'Invalid Follower', 'activitypub' ), array( 'status' => 400 ) );
		}

		$post_id = Remote_Actors::upsert( $meta );
		if ( \is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$post_meta = \get_post_meta( $post_id, self::FOLLOWER_META_KEY, false );
		if ( \is_array( $post_meta ) && ! \in_array( (string) $user_id, $post_meta, true ) ) {
			\add_post_meta( $post_id, self::FOLLOWER_META_KEY, $user_id );
			\wp_cache_delete( \sprintf( self::CACHE_KEY_INBOXES, $user_id ), 'activitypub' );
			\wp_cache_delete( Remote_Actors::CACHE_KEY_INBOXES, 'activitypub' );
		}

		return $post_id;
	}

	/**
	 * Add new Follower.
	 *
	 * @deprecated unreleased Use {@see Followers::add()}.
	 *
	 * @param int    $user_id The ID of the WordPress User.
	 * @param string $actor   The Actor URL.
	 *
	 * @return int|\WP_Error The Follower ID or an WP_Error.
	 */
	public static function add_follower( $user_id, $actor ) {
		\_deprecated_function( __METHOD__, 'unreleased', 'Activitypub\Collection\Followers::add' );

		return self::add( $user_id, $actor );
	}

	/**
	 * Remove a Follower.
	 *
	 * @param \WP_Post|int $post_or_id The ID of the remote Actor.
	 * @param int          $user_id    The ID of the WordPress User.
	 *
	 * @return bool True on success, false on failure.
	 */
	public static function remove( $post_or_id, $user_id ) {
		$post = \get_post( $post_or_id );

		if ( ! $post ) {
			return false;
		}

		\wp_cache_delete( \sprintf( self::CACHE_KEY_INBOXES, $user_id ), 'activitypub' );
		\wp_cache_delete( Remote_Actors::CACHE_KEY_INBOXES, 'activitypub' );

		/**
		 * Fires before a Follower is removed.
		 *
		 * @param \WP_Post                    $post    The remote Actor object.
		 * @param int                         $user_id The ID of the WordPress User.
		 * @param \Activitypub\Activity\Actor $actor   The remote Actor object.
		 */
		\do_action( 'activitypub_followers_pre_remove_follower', $post, $user_id, Remote_Actors::get_actor( $post ) );

		return \delete_post_meta( $post->ID, self::FOLLOWER_META_KEY, $user_id );
	}

	/**
	 * Remove a Follower.
	 *
	 * @deprecated 7.1.0 Use {@see Followers::remove()}.
	 *
	 * @param int    $user_id The ID of the WordPress User.
	 * @param string $actor   The Actor URL.
	 *
	 * @return bool True on success, false on failure.
	 */
	public static function remove_follower( $user_id, $actor ) {
		\_deprecated_function( __METHOD__, '7.1.0', 'Activitypub\Collection\Followers::remove' );

		$remote_actor = self::get_by_uri( $user_id, $actor );

		if ( \is_wp_error( $remote_actor ) ) {
			return false;
		}

		return self::remove( $remote_actor->ID, $user_id );
	}

	/**
	 * Get a Follower by URI.
	 *
	 * @param int    $user_id The ID of the WordPress User.
	 * @param string $actor   The Actor URL.
	 *
	 * @return \WP_Post|\WP_Error The Follower object or WP_Error on failure.
	 */
	public static function get_by_uri( $user_id, $actor ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT DISTINCT p.ID FROM $wpdb->posts p INNER JOIN $wpdb->postmeta pm ON p.ID = pm.post_id WHERE p.post_type = %s AND pm.meta_key = %s AND pm.meta_value = %d AND p.guid = %s",
				array(
					\esc_sql( Remote_Actors::POST_TYPE ),
					\esc_sql( self::FOLLOWER_META_KEY ),
					\esc_sql( $user_id ),
					\esc_sql( $actor ),
				)
			)
		);

		if ( ! $id ) {
			return new \WP_Error(
				'activitypub_follower_not_found',
				\__( 'Follower not found', 'activitypub' ),
				array( 'status' => 404 )
			);
		}

		return \get_post( $id );
	}

	/**
	 * Get a Follower.
	 *
	 * @deprecated unreleased Use {@see Followers::get_by_uri()}
	 *
	 * @param int    $user_id The ID of the WordPress User.
	 * @param string $actor   The Actor URL.
	 *
	 * @return \WP_Post|\WP_Error The Follower object or WP_Error on failure.
	 */
	public static function get_follower( $user_id, $actor ) {
		_deprecated_function( __METHOD__, 'unreleased', 'Activitypub\Collection\Followers::get_by_uri' );
		return self::get_by_uri( $user_id, $actor );
	}

	/**
	 * Get a Follower by Actor independent of the User.
	 *
	 * @deprecated 7.4.0 Use {@see Remote_Actors::get_by_uri()}.
	 *
	 * @param string $actor The Actor URL.
	 *
	 * @return \WP_Post|\WP_Error The Follower object or WP_Error on failure.
	 */
	public static function get_follower_by_actor( $actor ) {
		\_deprecated_function( __METHOD__, '7.4.0', 'Activitypub\Collection\Remote_Actors::get_by_uri' );

		return Remote_Actors::get_by_uri( $actor );
	}

	/**
	 * Get many followers.
	 *
	 * @param int|null $user_id The ID of the WordPress User.
	 * @param int      $number  Maximum number of results to return.
	 * @param int      $page    Page number.
	 * @param array    $args    The WP_Query arguments.
	 *
	 * @return \WP_Post[] List of `Follower` objects.
	 */
	public static function get_many( $user_id, $number = -1, $page = null, $args = array() ) {
		$data = self::query( $user_id, $number, $page, $args );

		return $data['followers'];
	}

	/**
	 * Get the Followers of a given user.
	 *
	 * @deprecated unreleased Use {@see Followers::get_many()}
	 *
	 * @param int|null $user_id The ID of the WordPress User.
	 * @param int      $number  Maximum number of results to return.
	 * @param int      $page    Page number.
	 * @param array    $args    The WP_Query arguments.
	 *
	 * @return \WP_Post[] List of `Follower` objects.
	 */
	public static function get_followers( $user_id, $number = -1, $page = null, $args = array() ) {
		_deprecated_function( __METHOD__, 'unreleased', 'Activitypub\Collection\Followers::get_many' );
		return self::get_many( $user_id, $number, $page, $args );
	}

	/**
	 * Get the Followers of a given user, along with a total count for pagination purposes.
	 *
	 * @deprecated unreleased Use {@see Followers::query()}.
	 *
	 * @param int|null $user_id The ID of the WordPress User.
	 * @param int      $number  Maximum number of results to return.
	 * @param int      $page    Page number.
	 * @param array    $args    The WP_Query arguments.
	 *
	 * @return array {
	 *      Data about the followers.
	 *
	 *      @type \WP_Post[] $followers List of `Follower` objects.
	 *      @type int        $total     Total number of followers.
	 *  }
	 */
	public static function get_followers_with_count( $user_id, $number = -1, $page = null, $args = array() ) {
		\_deprecated_function( __METHOD__, 'unreleased', 'Activitypub\Collection\Followers::query' );

		return self::query( $user_id, $number, $page, $args );
	}

	/**
	 * Query followers with pagination info.
	 *
	 * @param int|null $user_id The ID of the WordPress User.
	 * @param int      $number  Maximum number of results to return.
	 * @param int      $page    Page number.
	 * @param array    $args    The WP_Query arguments.
	 *
	 * @return array {
	 *      Data about the followers.
	 *
	 *      @type \WP_Post[] $followers List of `Follower` objects.
	 *      @type int        $total     Total number of followers.
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
				'relation' => 'OR',
				array(
					'key'   => self::FOLLOWER_META_KEY,
					'value' => $user_id,
				),
				// for backwards compatibility.
				array(
					'key'   => '_activitypub_user_id',
					'value' => $user_id,
				),
			),
		);

		$args      = \wp_parse_args( $args, $defaults );
		$query     = new \WP_Query( $args );
		$total     = $query->found_posts;
		$followers = \array_filter( $query->posts );

		return \compact( 'followers', 'total' );
	}

	/**
	 * Count the total number of followers.
	 *
	 * @param int $user_id The ID of the WordPress User.
	 *
	 * @return int The number of Followers
	 */
	public static function count( $user_id ) {
		return self::query( $user_id, 1 )['total'];
	}

	/**
	 * Count the total number of followers.
	 *
	 * @deprecated unreleased Use {@see Followers::count()}.
	 *
	 * @param int $user_id The ID of the WordPress User.
	 *
	 * @return int The number of Followers
	 */
	public static function count_followers( $user_id ) {
		\_deprecated_function( __METHOD__, 'unreleased', 'Activitypub\Collection\Followers::count' );

		return self::count( $user_id );
	}

	/**
	 * Returns all Inboxes for an Actor's Followers.
	 *
	 * @param int $user_id The ID of the WordPress User.
	 *
	 * @return array The list of Inboxes.
	 */
	public static function get_inboxes( $user_id ) {
		$cache_key = \sprintf( self::CACHE_KEY_INBOXES, $user_id );
		$inboxes   = \wp_cache_get( $cache_key, 'activitypub' );

		if ( $inboxes ) {
			return $inboxes;
		}

		// Get all Followers of an ID of the WordPress User.
		$posts = new \WP_Query(
			array(
				'nopaging'   => true,
				'post_type'  => Remote_Actors::POST_TYPE,
				'fields'     => 'ids',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_query' => array(
					'relation' => 'AND',
					array(
						'key'     => '_activitypub_inbox',
						'compare' => 'EXISTS',
					),
					array(
						'key'   => self::FOLLOWER_META_KEY,
						'value' => $user_id,
					),
					array(
						'key'     => '_activitypub_inbox',
						'value'   => '',
						'compare' => '!=',
					),
				),
			)
		);

		if ( ! $posts->posts ) {
			return array();
		}

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$results = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT meta_value FROM {$wpdb->postmeta}
				WHERE post_id IN (" . \implode( ', ', \array_fill( 0, \absint( $posts->post_count ), '%d' ) ) . ")
				AND meta_key = '_activitypub_inbox'
				AND meta_value IS NOT NULL",
				$posts->posts
			)
		);

		$inboxes = \array_filter( $results );
		\wp_cache_set( $cache_key, $inboxes, 'activitypub' );

		return $inboxes;
	}

	/**
	 * Get all Inboxes for a given Activity.
	 *
	 * @param string $json       The ActivityPub Activity JSON.
	 * @param int    $actor_id   The WordPress Actor ID.
	 * @param int    $batch_size Optional. The batch size. Default 50.
	 * @param int    $offset     Optional. The offset. Default 0.
	 *
	 * @return array The list of Inboxes.
	 */
	public static function get_inboxes_for_activity( $json, $actor_id, $batch_size = 50, $offset = 0 ) {
		$activity = \json_decode( $json, true );
		// Only if this is a Delete. Create handles its own "Announce" in dual user mode.
		if ( 'Delete' === ( $activity['type'] ?? null ) ) {
			$inboxes = Remote_Actors::get_inboxes();
		} else {
			$inboxes = self::get_inboxes( $actor_id );
		}

		return \array_slice( $inboxes, $offset, $batch_size );
	}

	/**
	 * Maybe add Inboxes of the Blog User.
	 *
	 * @deprecated 7.3.0
	 *
	 * @param string $json     The ActivityPub Activity JSON.
	 * @param int    $actor_id The WordPress Actor ID.
	 *
	 * @return bool True if the Inboxes of the Blog User should be added, false otherwise.
	 */
	public static function maybe_add_inboxes_of_blog_user( $json, $actor_id ) {
		\_deprecated_function( __METHOD__, '7.3.0' );

		// Only if we're in both Blog and User modes.
		if ( ACTIVITYPUB_ACTOR_AND_BLOG_MODE !== \get_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_MODE ) ) {
			return false;
		}
		// Only if this isn't the Blog Actor.
		if ( Actors::BLOG_USER_ID === $actor_id ) {
			return false;
		}

		$activity = \json_decode( $json, true );
		// Only if this is an Update or Delete. Create handles its own "Announce" in dual user mode.
		if ( ! \in_array( $activity['type'] ?? null, array( 'Update', 'Delete' ), true ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Get all Followers.
	 *
	 * @deprecated 7.1.0 Use {@see Actors::get_all()}.
	 *
	 * @return \WP_Post[] The list of Followers.
	 */
	public static function get_all_followers() {
		_deprecated_function( __METHOD__, '7.1.0', 'Activitypub\Collection\Actors::get_all' );

		$args = array(
			'nopaging'   => true,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'meta_query' => array(
				'relation' => 'AND',
				array(
					'key'     => '_activitypub_inbox',
					'compare' => 'EXISTS',
				),
			),
		);
		return self::get_many( null, null, null, $args );
	}

	/**
	 * Get all Followers that have not been updated for a given time.
	 *
	 * @deprecated 7.0.0 Use {@see Remote_Actors::get_outdated()}.
	 *
	 * @param int $number     Optional. Limits the result. Default 50.
	 * @param int $older_than Optional. The time in seconds. Default 86400 (1 day).
	 *
	 * @return \WP_Post[] The list of Actors.
	 */
	public static function get_outdated_followers( $number = 50, $older_than = 86400 ) {
		_deprecated_function( __METHOD__, '7.0.0', 'Activitypub\Collection\Remote_Actors::get_outdated' );

		return Remote_Actors::get_outdated( $number, $older_than );
	}

	/**
	 * Get all Followers that had errors.
	 *
	 * @deprecated 7.0.0 Use {@see Remote_Actors::get_faulty()}.
	 *
	 * @param int $number Optional. The number of Followers to return. Default 20.
	 *
	 * @return \WP_Post[] The list of Actors.
	 */
	public static function get_faulty_followers( $number = 20 ) {
		_deprecated_function( __METHOD__, '7.0.0', 'Activitypub\Collection\Remote_Actors::get_faulty' );

		return Remote_Actors::get_faulty( $number );
	}

	/**
	 * This function is used to store errors that occur when
	 * sending an ActivityPub message to a Follower.
	 *
	 * The error will be stored in post meta.
	 *
	 * @deprecated 7.0.0 Use {@see Remote_Actors::add_error()}.
	 *
	 * @param int   $post_id The ID of the WordPress Custom-Post-Type.
	 * @param mixed $error   The error message. Can be a string or a WP_Error.
	 *
	 * @return int|false The meta ID on success, false on failure.
	 */
	public static function add_error( $post_id, $error ) {
		\_deprecated_function( __METHOD__, '7.0.0', 'Activitypub\Collection\Remote_Actors::add_error' );

		return Remote_Actors::add_error( $post_id, $error );
	}

	/**
	 * Clear the errors for a Follower.
	 *
	 * @deprecated 7.0.0 Use {@see Remote_Actors::clear_errors()}.
	 *
	 * @param int $post_id The ID of the WordPress Custom-Post-Type.
	 *
	 * @return bool True on success, false on failure.
	 */
	public static function clear_errors( $post_id ) {
		\_deprecated_function( __METHOD__, '7.0.0', 'Activitypub\Collection\Remote_Actors::clear_errors' );

		return Remote_Actors::clear_errors( $post_id );
	}

	/**
	 * Check the status of a given following.
	 *
	 * @param int $post_id The ID of the Post.
	 * @param int $user_id The ID of the WordPress User.
	 *
	 * @return bool The status of the following.
	 */
	public static function follows( $post_id, $user_id ) {
		$all_meta  = \get_post_meta( $post_id );
		$following = $all_meta[ self::FOLLOWER_META_KEY ] ?? array();

		return \in_array( (string) $user_id, $following, true );
	}

	/**
	 * Remove blocked actors from follower lists.
	 *
	 * Called via activitypub_add_user_block hook.
	 *
	 * @param string $value   The blocked actor URI.
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

		self::remove( $actor_id, $user_id );
	}

	/**
	 * Compute the partial follower collection digest for a specific instance.
	 *
	 * Implements FEP-8fcf: Followers collection synchronization.
	 * This is a convenience wrapper that filters followers by authority and then
	 * computes the digest using the standard FEP-8fcf algorithm.
	 *
	 * The digest is created by XORing together the individual SHA256 digests
	 * of each follower's ID.
	 *
	 * @see https://codeberg.org/fediverse/fep/src/branch/main/fep/8fcf/fep-8fcf.md
	 * @see Signature::get_collection_digest() for the core digest algorithm
	 *
	 * @param int    $user_id   The user ID whose followers to compute.
	 * @param string $authority The URI authority (scheme + host) to filter by.
	 *
	 * @return string|false The hex-encoded digest, or false if no followers.
	 */
	public static function compute_partial_digest( $user_id, $authority ) {
		// Get followers filtered by authority.
		$followers    = self::get_by_authority( $user_id, $authority );
		$follower_ids = \wp_list_pluck( $followers, 'guid' );

		// Delegate to the core digest computation algorithm.
		return Signature::get_collection_digest( $follower_ids );
	}

	/**
	 * Get partial followers collection for a specific instance.
	 *
	 * Returns only followers whose ID shares the specified URI authority.
	 * Used for FEP-8fcf synchronization.
	 *
	 * @param int    $user_id   The user ID whose followers to get.
	 * @param string $authority The URI authority (scheme + host) to filter by.
	 *
	 * @return \WP_Post[] Array of WP_Post objects.
	 */
	public static function get_by_authority( $user_id, $authority ) {
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
						'key'   => self::FOLLOWER_META_KEY,
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
	 * Generate the Collection-Synchronization header value for FEP-8fcf.
	 *
	 * @param int    $user_id   The user ID whose followers collection to sync.
	 * @param string $authority The authority of the receiving instance.
	 *
	 * @return string|false The header value, or false if cannot generate.
	 */
	public static function generate_sync_header( $user_id, $authority ) {
		$followers = self::get_by_authority( $user_id, $authority );
		$followers = \wp_list_pluck( $followers, 'guid' );

		// Compute the digest for this specific authority.
		$digest = Signature::get_collection_digest( $followers );

		if ( ! $digest ) {
			return false;
		}

		// Build the collection ID (followers collection URL).
		$collection_id = get_rest_url_by_path( sprintf( 'actors/%d/followers', $user_id ) );

		// Build the partial followers URL.
		$url = get_rest_url_by_path(
			sprintf(
				'actors/%d/followers/sync?authority=%s',
				$user_id,
				rawurlencode( $authority )
			)
		);

		// Format as per FEP-8fcf (similar to HTTP Signatures format).
		return sprintf(
			'collectionId="%s", url="%s", digest="%s"',
			$collection_id,
			$url,
			$digest
		);
	}
}
