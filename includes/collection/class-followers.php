<?php
/**
 * Followers collection file.
 *
 * @package Activitypub
 */

namespace Activitypub\Collection;

use WP_Error;
use WP_Query;
use Activitypub\Model\Follower;

use function Activitypub\is_tombstone;
use function Activitypub\get_remote_metadata_by_actor;

/**
 * ActivityPub Followers Collection.
 *
 * @author Matt Wiebe
 * @author Matthias Pfefferle
 */
class Followers {
	const POST_TYPE         = 'ap_follower';
	const CACHE_KEY_INBOXES = 'follower_inboxes_%s';

	/**
	 * Add new Follower.
	 *
	 * @param int    $user_id The ID of the WordPress User.
	 * @param string $actor   The Actor URL.
	 *
	 * @return Follower|WP_Error The Follower (WP_Post array) or an WP_Error.
	 */
	public static function add_follower( $user_id, $actor ) {
		$meta = get_remote_metadata_by_actor( $actor );

		if ( is_tombstone( $meta ) ) {
			return $meta;
		}

		if ( empty( $meta ) || ! is_array( $meta ) || is_wp_error( $meta ) ) {
			return new WP_Error( 'activitypub_invalid_follower', __( 'Invalid Follower', 'activitypub' ), array( 'status' => 400 ) );
		}

		$follower = new Follower();
		$follower->from_array( $meta );

		$id = $follower->upsert();

		if ( is_wp_error( $id ) ) {
			return $id;
		}

		$post_meta = get_post_meta( $id, '_activitypub_user_id', false );

		// phpcs:ignore WordPress.PHP.StrictInArray.MissingTrueStrict
		if ( is_array( $post_meta ) && ! in_array( $user_id, $post_meta ) ) {
			add_post_meta( $id, '_activitypub_user_id', $user_id );
			wp_cache_delete( sprintf( self::CACHE_KEY_INBOXES, $user_id ), 'activitypub' );
		}

		return $follower;
	}

	/**
	 * Remove a Follower.
	 *
	 * @param int    $user_id The ID of the WordPress User.
	 * @param string $actor   The Actor URL.
	 *
	 * @return bool True on success, false on failure.
	 */
	public static function remove_follower( $user_id, $actor ) {
		wp_cache_delete( sprintf( self::CACHE_KEY_INBOXES, $user_id ), 'activitypub' );

		$follower = self::get_follower( $user_id, $actor );

		if ( ! $follower ) {
			return false;
		}

		/**
		 * Fires before a Follower is removed.
		 *
		 * @param \Activitypub\Model\Follower $follower The Follower object.
		 * @param int                         $user_id  The ID of the WordPress User.
		 * @param string                      $actor    The Actor URL.
		 */
		do_action( 'activitypub_followers_pre_remove_follower', $follower, $user_id, $actor );

		return delete_post_meta( $follower->get__id(), '_activitypub_user_id', $user_id );
	}

	/**
	 * Get a Follower.
	 *
	 * @param int    $user_id The ID of the WordPress User.
	 * @param string $actor   The Actor URL.
	 *
	 * @return \Activitypub\Activity\Base_Object|WP_Error|null The Follower object or null
	 */
	public static function get_follower( $user_id, $actor ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$post_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT DISTINCT p.ID FROM $wpdb->posts p INNER JOIN $wpdb->postmeta pm ON p.ID = pm.post_id WHERE p.post_type = %s AND pm.meta_key = '_activitypub_user_id' AND pm.meta_value = %d AND p.guid = %s",
				array(
					esc_sql( self::POST_TYPE ),
					esc_sql( $user_id ),
					esc_sql( $actor ),
				)
			)
		);

		if ( $post_id ) {
			$post = get_post( $post_id );
			return Follower::init_from_cpt( $post );
		}

		return null;
	}

	/**
	 * Get a Follower by Actor independent of the User.
	 *
	 * @param string $actor The Actor URL.
	 *
	 * @return \Activitypub\Activity\Base_Object|WP_Error|null
	 */
	public static function get_follower_by_actor( $actor ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$post_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM $wpdb->posts WHERE guid=%s",
				esc_sql( $actor )
			)
		);

		if ( $post_id ) {
			$post = get_post( $post_id );
			return Follower::init_from_cpt( $post );
		}

		return null;
	}

	/**
	 * Get the Followers of a given user.
	 *
	 * @param int   $user_id The ID of the WordPress User.
	 * @param int   $number  Maximum number of results to return.
	 * @param int   $page    Page number.
	 * @param array $args    The WP_Query arguments.
	 * @return array List of `Follower` objects.
	 */
	public static function get_followers( $user_id, $number = -1, $page = null, $args = array() ) {
		$data = self::get_followers_with_count( $user_id, $number, $page, $args );
		return $data['followers'];
	}

	/**
	 * Get the Followers of a given user, along with a total count for pagination purposes.
	 *
	 * @param int   $user_id The ID of the WordPress User.
	 * @param int   $number  Maximum number of results to return.
	 * @param int   $page    Page number.
	 * @param array $args    The WP_Query arguments.
	 *
	 * @return array {
	 *      Data about the followers.
	 *
	 *      @type array $followers List of `Follower` objects.
	 *      @type int   $total     Total number of followers.
	 *  }
	 */
	public static function get_followers_with_count( $user_id, $number = -1, $page = null, $args = array() ) {
		$defaults = array(
			'post_type'      => self::POST_TYPE,
			'posts_per_page' => $number,
			'paged'          => $page,
			'orderby'        => 'ID',
			'order'          => 'DESC',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'meta_query'     => array(
				array(
					'key'   => '_activitypub_user_id',
					'value' => $user_id,
				),
			),
		);

		$args      = wp_parse_args( $args, $defaults );
		$query     = new WP_Query( $args );
		$total     = $query->found_posts;
		$followers = array_map(
			function ( $post ) {
				return Follower::init_from_cpt( $post );
			},
			$query->get_posts()
		);

		return compact( 'followers', 'total' );
	}

	/**
	 * Get all Followers.
	 *
	 * @return array The Term list of Followers.
	 */
	public static function get_all_followers() {
		$args = array(
			'nopaging'   => true,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'meta_query' => array(
				'relation' => 'AND',
				array(
					'key'     => '_activitypub_inbox',
					'compare' => 'EXISTS',
				),
				array(
					'key'     => '_activitypub_actor_json',
					'compare' => 'EXISTS',
				),
			),
		);
		return self::get_followers( null, null, null, $args );
	}

	/**
	 * Count the total number of followers
	 *
	 * @param int $user_id The ID of the WordPress User.
	 *
	 * @return int The number of Followers
	 */
	public static function count_followers( $user_id ) {
		$query = new WP_Query(
			array(
				'post_type'  => self::POST_TYPE,
				'fields'     => 'ids',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_query' => array(
					'relation' => 'AND',
					array(
						'key'   => '_activitypub_user_id',
						'value' => $user_id,
					),
					array(
						'key'     => '_activitypub_inbox',
						'compare' => 'EXISTS',
					),
					array(
						'key'     => '_activitypub_actor_json',
						'compare' => 'EXISTS',
					),
				),
			)
		);

		return $query->found_posts;
	}

	/**
	 * Returns all Inboxes for an Actor's Followers.
	 *
	 * @param int $user_id The ID of the WordPress User.
	 *
	 * @return array The list of Inboxes.
	 */
	public static function get_inboxes( $user_id ) {
		$cache_key = sprintf( self::CACHE_KEY_INBOXES, $user_id );
		$inboxes   = wp_cache_get( $cache_key, 'activitypub' );

		if ( $inboxes ) {
			return $inboxes;
		}

		// Get all Followers of a ID of the WordPress User.
		$posts = new WP_Query(
			array(
				'nopaging'   => true,
				'post_type'  => self::POST_TYPE,
				'fields'     => 'ids',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_query' => array(
					'relation' => 'AND',
					array(
						'key'     => '_activitypub_inbox',
						'compare' => 'EXISTS',
					),
					array(
						'key'   => '_activitypub_user_id',
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

		$posts = $posts->get_posts();

		if ( ! $posts ) {
			return array();
		}

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$results = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT meta_value FROM {$wpdb->postmeta}
				WHERE post_id IN (" . implode( ', ', array_fill( 0, count( $posts ), '%d' ) ) . ")
				AND meta_key = '_activitypub_inbox'
				AND meta_value IS NOT NULL",
				$posts
			)
		);

		$inboxes = array_filter( $results );
		wp_cache_set( $cache_key, $inboxes, 'activitypub' );

		return $inboxes;
	}

	/**
	 * Get all Followers that have not been updated for a given time.
	 *
	 * @param int $number     Optional. Limits the result. Default 50.
	 * @param int $older_than Optional. The time in seconds. Default 86400 (1 day).
	 *
	 * @return array The Term list of Followers.
	 */
	public static function get_outdated_followers( $number = 50, $older_than = 86400 ) {
		$args = array(
			'post_type'      => self::POST_TYPE,
			'posts_per_page' => $number,
			'orderby'        => 'modified',
			'order'          => 'ASC',
			'post_status'    => 'any', // 'any' includes 'trash'.
			'date_query'     => array(
				array(
					'column' => 'post_modified_gmt',
					'before' => gmdate( 'Y-m-d', \time() - $older_than ),
				),
			),
		);

		$posts = new WP_Query( $args );
		$items = array();

		foreach ( $posts->get_posts() as $follower ) {
			$items[] = Follower::init_from_cpt( $follower );
		}

		return $items;
	}

	/**
	 * Get all Followers that had errors.
	 *
	 * @param int $number Optional. The number of Followers to return. Default 20.
	 *
	 * @return array The Term list of Followers.
	 */
	public static function get_faulty_followers( $number = 20 ) {
		$args = array(
			'post_type'      => self::POST_TYPE,
			'posts_per_page' => $number,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'meta_query'     => array(
				'relation' => 'OR',
				array(
					'key'     => '_activitypub_errors',
					'compare' => 'EXISTS',
				),
				array(
					'key'     => '_activitypub_inbox',
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => '_activitypub_actor_json',
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => '_activitypub_inbox',
					'value'   => '',
					'compare' => '=',
				),
				array(
					'key'     => '_activitypub_actor_json',
					'value'   => '',
					'compare' => '=',
				),
			),
		);

		$posts = new WP_Query( $args );
		$items = array();

		foreach ( $posts->get_posts() as $follower ) {
			$items[] = Follower::init_from_cpt( $follower );
		}

		return $items;
	}

	/**
	 * This function is used to store errors that occur when
	 * sending an ActivityPub message to a Follower.
	 *
	 * The error will be stored in post meta.
	 *
	 * @param int   $post_id The ID of the WordPress Custom-Post-Type.
	 * @param mixed $error   The error message. Can be a string or a WP_Error.
	 *
	 * @return int|false The meta ID on success, false on failure.
	 */
	public static function add_error( $post_id, $error ) {
		if ( is_string( $error ) ) {
			$error_message = $error;
		} elseif ( is_wp_error( $error ) ) {
			$error_message = $error->get_error_message();
		} else {
			$error_message = __(
				'Unknown Error or misconfigured Error-Message',
				'activitypub'
			);
		}

		return add_post_meta(
			$post_id,
			'_activitypub_errors',
			$error_message
		);
	}
}
