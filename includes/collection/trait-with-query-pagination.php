<?php
/**
 * Query Pagination Trait file.
 *
 * @package Activitypub
 */

namespace Activitypub\Collection;

/**
 * Query Pagination Trait.
 *
 * Shared `WP_Query` plumbing for the actor-relationship collections (Followers,
 * Following, Blocked_Actors) that all page over `Remote_Actors` posts filtered by
 * a per-user meta key. Callers supply the `meta_query` and rename the result key.
 */
trait With_Query_Pagination {

	/**
	 * Run a paginated query over the remote-actor posts.
	 *
	 * @param int   $number     Maximum number of results to return.
	 * @param int   $page       Page number.
	 * @param array $args       Additional WP_Query arguments to merge over the defaults.
	 * @param array $meta_query The meta query that scopes the collection to a user.
	 *
	 * @return array {
	 *     The queried posts and the total match count.
	 *
	 *     @type \WP_Post[] $posts The (non-empty) posts on this page.
	 *     @type int        $total Total number of matching posts.
	 * }
	 */
	protected static function query_posts( $number, $page, $args, $meta_query ) {
		$defaults = array(
			'post_type'      => Remote_Actors::POST_TYPE,
			'posts_per_page' => $number,
			'paged'          => $page,
			'orderby'        => 'ID',
			'order'          => 'DESC',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'meta_query'     => $meta_query,
		);

		$args  = \wp_parse_args( $args, $defaults );
		$query = new \WP_Query( $args );

		return array(
			'posts' => \array_filter( $query->posts ),
			'total' => $query->found_posts,
		);
	}

	/**
	 * Get remote-actor posts for a user whose inbox shares a URI authority.
	 *
	 * Used for FEP-8fcf collection synchronization.
	 *
	 * @param int    $user_id   The user ID whose collection to filter.
	 * @param string $authority The URI authority (scheme + host) to filter by.
	 * @param string $meta_key  The relationship meta key that scopes the collection.
	 *
	 * @return \WP_Post[] Array of WP_Post objects.
	 */
	protected static function query_by_authority( $user_id, $authority, $meta_key ) {
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
						'key'   => $meta_key,
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
}
