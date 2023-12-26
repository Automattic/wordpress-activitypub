<?php
namespace Activitypub\Collection;

use WP_Error;
use WP_Query;
use Activitypub\Http;
use Activitypub\Webfinger;
use Activitypub\Activity\Base_Object;
use Activitypub\Model\Follower;

use function Activitypub\is_tombstone;
use function Activitypub\get_remote_metadata_by_actor;

/**
 * ActivityPub Follow Requests Collection
 *
 * @author André Menrath
 */
class Follow_Requests {
	/**
	 * Get a follow request together with information from the follower.
	 *
	 * @param int $user_id   The ID of the WordPress User, which may be 0 for the blog and -1 for the application user
	 * @param int $per_page  Number of items per page
	 * @param int $page_num  The current page
	 * @param int $args      May contain custom ordering or search terms.
	 *
	 * @return array Containing an array of all follow requests and the total numbers.
	 */
	public static function get_follow_requests_for_user( $user_id, $per_page, $page_num, $args ) {
		$order   = isset( $args['order'] ) && strtolower( $args['order'] ) === 'asc' ? 'ASC' : 'DESC';
		$orderby = isset( $args['orderby'] ) ? sanitize_text_field( $args['orderby'] ) : 'published';
		$search  = isset( $args['s'] ) ? sanitize_text_field( $args['s'] ) : '';

		$offset = (int) $per_page * ( (int) $page_num - 1 );

		global $wpdb;
		$follow_requests = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT SQL_CALC_FOUND_ROWS follow_request.ID AS id, follow_request.post_date AS published, follow_request.guid, follow_request.post_status AS 'status', follower.post_title AS 'post_title', follower.guid AS follower_guid, follower.id AS follower_id, follower.post_modified AS follower_modified
				 FROM {$wpdb->posts} AS follow_request
				 LEFT JOIN {$wpdb->posts} AS follower ON follow_request.post_parent = follower.ID
				 LEFT JOIN {$wpdb->postmeta} AS meta ON follow_request.ID = meta.post_id
				 WHERE follow_request.post_type = 'ap_follow_request'
				 AND (follower.post_title LIKE %s OR follower.guid LIKE %s)
				 AND meta.meta_key = 'activitypub_user_id'
				 AND meta.meta_value = %s
				 ORDER BY %s %s
				 LIMIT %d OFFSET %d",
				'%' . $wpdb->esc_like( $search ) . '%',
				'%' . $wpdb->esc_like( $search ) . '%',
				$user_id,
				$orderby,
				$order,
				$per_page,
				$offset
			)
		);
		$current_total_items = $wpdb->get_var( 'SELECT FOUND_ROWS()' );

		// Second step: Get the total rows without the LIMIT
		$total_items = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(follow_request.ID)
				 FROM {$wpdb->posts} AS follow_request
				 LEFT JOIN {$wpdb->posts} AS follower ON follow_request.post_parent = follower.ID
				 LEFT JOIN {$wpdb->postmeta} AS meta ON follow_request.ID = meta.post_id
				 WHERE follow_request.post_type = 'ap_follow_request'
				 AND meta.meta_key = 'activitypub_user_id'
				 AND meta.meta_value = %s",
				$user_id
			)
		);

		return compact( 'follow_requests', 'current_total_items', 'total_items' );
	}
}
