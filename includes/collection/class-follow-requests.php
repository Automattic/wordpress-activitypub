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
	 * @param int $user_id      The ID of the WordPress User
	 * @param int $follower_id  The follower ID
	 *
	 * @return \Activitypub\Model\Follow|null The Follower object or null
	 */
	public static function get_follow_requests_for_user( $user_id, $per_page, $page, $args ) {
		$order   = isset($args['order']) && strtolower($args['order']) === 'asc' ? 'ASC' : 'DESC';
		$orderby = isset($args['orderby']) ? sanitize_text_field($args['orderby']) : 'published';

		global $wpdb;
		$follow_requests = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT SQL_CALC_FOUND_ROWS follow_request.ID AS id, follow_request.post_date AS published, follow_request.guid, follow_request.post_status AS 'status', follower.post_title AS 'post_title', follower.guid AS follower_guid, follower.id AS follower_id, follower.post_modified AS follower_modified
				 FROM {$wpdb->posts} AS follow_request
				 LEFT JOIN {$wpdb->posts} AS follower ON follow_request.post_parent = follower.ID
				 LEFT JOIN {$wpdb->postmeta} AS meta ON follow_request.ID = meta.post_id
				 WHERE follow_request.post_type = 'ap_follow_request'
				 AND meta.meta_key = 'activitypub_user_id'
				 AND meta.meta_value = %s
				 ORDER BY {$orderby} {$order}
				 LIMIT %d OFFSET %d",
				$user_id,
				$per_page,
				0
			)
		);
		$total_items = $wpdb->get_var("SELECT FOUND_ROWS()");

		return compact( 'follow_requests', 'total_items' );
	}
}
