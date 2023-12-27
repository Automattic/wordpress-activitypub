<?php
namespace Activitypub\Collection;

use WP_Error;
use WP_Query;
use Activitypub\Http;
use Activitypub\Webfinger;
use Activitypub\Model\Follower;

use function Activitypub\is_tombstone;
use function Activitypub\get_remote_metadata_by_actor;

/**
 * ActivityPub Followers Collection
 *
 * @author Matt Wiebe
 * @author Matthias Pfefferle
 */
class Followers {
	const POST_TYPE = 'ap_follower';
	const CACHE_KEY_INBOXES = 'follower_inboxes_%s';

	/**
	 * Add new Follower.
	 *
	 * This does not add the follow relationship. 
	 * 
	 * The follow relationship can be added later with add_follow_relationship which is usually done when the Accept respone is sent.
	 *
	 * @param string $actor   The Actor URL
	 *
	 * @return array|WP_Error The Follower (WP_Post array) or an WP_Error
	 */
	public static function add_follower( $actor ) {
		$meta = get_remote_metadata_by_actor( $actor );

		if ( is_tombstone( $meta ) ) {
			return $meta;
		}

		if ( empty( $meta ) || ! is_array( $meta ) || is_wp_error( $meta ) ) {
			return new WP_Error( 'activitypub_invalid_follower', __( 'Invalid Follower', 'activitypub' ), array( 'status' => 400 ) );
		}

		$follower = new Follower();
		$follower->from_array( $meta );

		// Save the follower to the internal post type or update if it's ActivityPub ID is already known.
		$follower_id = $follower->upsert();

		if ( is_wp_error( $follower_id ) ) {
			return $follower_id;
		}

		return $follower;
	}

	/**
	 * Add follow relationship between follower actor identified by internal post id and target actor identified by WordPress user id.
	 *
	 * @param int|string $user_id     The internal id of the target WordPress user that gets followed.
	 * @param int|string $follower_id The internal id of the follower actor.
	 */
	public static function add_follow_relationship( $user_id, $follower_id ) {
		$post_meta = get_post_meta( $follower_id, 'activitypub_user_id' );

		// phpcs:ignore WordPress.PHP.StrictInArray.MissingTrueStrict
		// Check if the follow relationship is already present.
		if ( is_array( $post_meta ) && ! in_array( $user_id, $post_meta ) ) {
			// Actually save the follow relationship
			add_post_meta( $follower_id, 'activitypub_user_id', $user_id );
			// Reset the cached inboxes for the followed user
			wp_cache_delete( sprintf( self::CACHE_KEY_INBOXES, $user_id ), 'activitypub' );
		}
	}

	/**
	 * Remove a Follower
	 *
	 * @param int    $user_id The ID of the WordPress User
	 * @param string $actor   The Actor URL
	 *
	 * @return bool|WP_Error True on success, false or WP_Error on failure.
	 */
	public static function remove_follow_relationship( $user_id, $actor ) {
		wp_cache_delete( sprintf( self::CACHE_KEY_INBOXES, $user_id ), 'activitypub' );

		$follower = self::get_follower( $user_id, $actor );

		if ( ! $follower ) {
			return false;
		}

		return delete_post_meta( $follower->get__id(), 'activitypub_user_id', $user_id );
	}

	/**
	 * Get a Follower.
	 *
	 * @param int    $user_id The ID of the WordPress User
	 * @param string $actor   The Actor URL
	 *
	 * @return \Activitypub\Model\Follower|null The Follower object or null
	 */
	public static function get_follower( $user_id, $actor ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$post_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT DISTINCT p.ID FROM $wpdb->posts p INNER JOIN $wpdb->postmeta pm ON p.ID = pm.post_id WHERE p.post_type = %s AND pm.meta_key = 'activitypub_user_id' AND pm.meta_value = %d AND p.guid = %s",
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
	 * Get a Follower by the internal ID.
	 *
	 * @param int $user_id The post ID of the WordPress Follower custom post type post.
	 *
	 * @return \Activitypub\Model\Follower|null The Follower object or null
	 */
	public static function get_follower_by_id( $follower_id ) {
		$post = get_post( $follower_id );
		return Follower::init_from_cpt( $post );
	}

	/**
	 * Get a Follower by Actor indepenent from the User.
	 *
	 * @param string $actor The Actor URL.
	 *
	 * @return \Activitypub\Model\Follower|null The Follower object or null
	 */
	public static function get_follower_by_actor( $actor ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
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
	 * Get the Followers of a given user
	 *
	 * @param int    $user_id The ID of the WordPress User.
	 * @param int    $number  Maximum number of results to return.
	 * @param int    $page    Page number.
	 * @param array  $args    The WP_Query arguments.
	 * @return array List of `Follower` objects.
	 */
	public static function get_followers( $user_id, $number = -1, $page = null, $args = array() ) {
		$data = self::get_followers_with_count( $user_id, $number, $page, $args );
		return $data['followers'];
	}

	/**
	 * Get the Followers of a given user, along with a total count for pagination purposes.
	 *
	 * @param int    $user_id The ID of the WordPress User.
	 * @param int    $number  Maximum number of results to return.
	 * @param int    $page    Page number.
	 * @param array  $args    The WP_Query arguments.
	 *
	 * @return array
	 *               followers List of `Follower` objects.
	 *               total     Total number of followers.
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
					'key'   => 'activitypub_user_id',
					'value' => $user_id,
				),
			),
		);
		// TODO: handle follower with empty post_content or inbox which is saved in post_content_filtered
		$args = wp_parse_args( $args, $defaults );
		$query = new WP_Query( $args );
		$total = $query->found_posts;
		$followers = array_map(
			function ( $post ) {
				return Follower::init_from_cpt( $post );
			},
			$query->get_posts()
		);
		return compact( 'followers', 'total' );
	}

	/**
	 * Get all Followers
	 *
	 * @param array $args The WP_Query arguments.
	 *
	 * @return array The Term list of Followers.
	 */
	public static function get_all_followers() {
		$args = array(
			'nopaging'   => true,
		);
		return self::get_followers( null, null, null, $args );
	}

	/**
	 * Count the total number of followers
	 *
	 * @param int $user_id The ID of the WordPress User
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
						'key'   => 'activitypub_user_id',
						'value' => $user_id,
					),
				),
			)
		);
		// TODO: handle follower with empty post_content or inbox which is saved in post_content_filtered
		return $query->found_posts;
	}

	/**
	 * Returns all Inboxes fo a Users Followers
	 *
	 * @param int $user_id The ID of the WordPress User
	 *
	 * @return array The list of Inboxes
	 */
	public static function get_inboxes( $user_id ) {
		$cache_key = sprintf( self::CACHE_KEY_INBOXES, $user_id );
		// TODO: enable caching of the inboxes: this is only for debugging purpose.
		// $inboxes = wp_cache_get( $cache_key, 'activitypub' );

		// if ( $inboxes ) {
		//  return $inboxes;
		// }

		// get all Followers of a ID of the WordPress User
		$follower_query = new WP_Query(
			array(
				'nopaging'   => true,
				'post_type'  => self::POST_TYPE,
				'fields'     => 'ids',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_query' => array(
					'relation' => 'AND',
					array(
						'key'   => 'activitypub_user_id',
						'value' => $user_id,
					),
				),
			)
		);

		$follower_ids = $follower_query->get_posts();

		if ( ! $follower_ids ) {
			return array();
		}

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$inboxes = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT post_content_filtered FROM {$wpdb->posts}
				 WHERE ID IN (" . implode( ', ', array_fill( 0, count( $follower_ids ), '%d' ) ) . ')',
				$follower_ids
			)
		);

		wp_cache_set( $cache_key, $inboxes, 'activitypub' );

		return $inboxes;
	}

	/**
	 * Get all Followers that have not been updated for a given time
	 *
	 * @param enum $output     The output format, supported ARRAY_N, OBJECT and ACTIVITYPUB_OBJECT.
	 * @param int  $number     Limits the result.
	 * @param int  $older_than The time in seconds.
	 *
	 * @return mixed The Term list of Followers, the format depends on $output.
	 */
	public static function get_outdated_followers( $number = 50, $older_than = 86400 ) {
		$args = array(
			'post_type'      => self::POST_TYPE,
			'posts_per_page' => $number,
			'orderby'        => 'modified',
			'order'          => 'ASC',
			'post_status'    => 'any', // 'any' includes 'trash
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
			$items[] = Follower::init_from_cpt( $follower ); // phpcs:ignore
		}

		return $items;
	}

	/**
	 * Get all Followers that had errors
	 *
	 * @param enum    $output The output format, supported ARRAY_N, OBJECT and ACTIVITYPUB_OBJECT
	 * @param integer $number The number of Followers to return.
	 *
	 * @return mixed The Term list of Followers, the format depends on $output.
	 */
	public static function get_faulty_followers( $number = 20 ) {
		$args = array(
			'post_type'      => self::POST_TYPE,
			'posts_per_page' => $number,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'meta_query'     => array(
				'relation' => 'OR',
				array(
					'key'     => 'activitypub_errors',
					'compare' => 'EXISTS',
				),
				array(
					'key'     => 'activitypub_inbox',
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => 'activitypub_actor_json',
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => 'activitypub_inbox',
					'value'   => '',
					'compare' => '=',
				),
				array(
					'key'     => 'activitypub_actor_json',
					'value'   => '',
					'compare' => '=',
				),
			),
		);

		$posts = new WP_Query( $args );
		$items = array();

		foreach ( $posts->get_posts() as $follower ) {
			$items[] = Follower::init_from_cpt( $follower ); // phpcs:ignore
		}

		return $items;
	}

	/**
	 * This function is used to store errors that occur when
	 * sending an ActivityPub message to a Follower.
	 *
	 * The error will be stored in the
	 * post meta.
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
			'activitypub_errors',
			$error_message
		);
	}
}
