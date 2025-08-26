<?php
/**
 * Blocked Actors collection file.
 *
 * @package Activitypub
 */

namespace Activitypub\Collection;

use Activitypub\Moderation;

/**
 * ActivityPub Blocked Actors Collection.
 */
class Blocked_Actors {
	/**
	 * Get the blocked actors of a given user, along with a total count for pagination purposes.
	 *
	 * @param int|null $user_id The ID of the WordPress User.
	 * @param int      $number  Maximum number of results to return.
	 * @param int      $page    Page number.
	 * @param array    $args    The WP_Query arguments.
	 *
	 * @return array {
	 *      Data about the blocked actors.
	 *
	 *      @type \WP_Post[] $blocked_actors List of blocked Actor WP_Post objects.
	 *      @type int        $total         Total number of blocked actors.
	 *  }
	 */
	public static function get_blocked_actors_with_count( $user_id, $number = -1, $page = null, $args = array() ) {
		$defaults = array(
			'post_type'      => Actors::POST_TYPE,
			'posts_per_page' => $number,
			'paged'          => $page,
			'orderby'        => 'ID',
			'order'          => 'DESC',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'meta_query'     => array(
				array(
					'key'   => Moderation::BLOCKED_ACTORS_META_KEY,
					'value' => $user_id,
				),
			),
		);

		$args           = \wp_parse_args( $args, $defaults );
		$query          = new \WP_Query( $args );
		$total          = $query->found_posts;
		$blocked_actors = \array_filter( $query->posts );

		return \compact( 'blocked_actors', 'total' );
	}

	/**
	 * Get the blocked actors of a given user.
	 *
	 * @param int|null $user_id The ID of the WordPress User.
	 * @param int      $number  Maximum number of results to return.
	 * @param int      $page    Page number.
	 * @param array    $args    The WP_Query arguments.
	 *
	 * @return \WP_Post[] List of blocked Actors.
	 */
	public static function get_blocked_actors( $user_id, $number = -1, $page = null, $args = array() ) {
		return self::get_blocked_actors_with_count( $user_id, $number, $page, $args )['blocked_actors'];
	}
}
