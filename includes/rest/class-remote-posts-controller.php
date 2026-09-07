<?php
/**
 * Remote_Posts_Controller file.
 *
 * @package Activitypub
 */

namespace Activitypub\Rest;

/**
 * Remote_Posts_Controller class.
 *
 * Restricts the WordPress REST route that core generates for the `ap_post` post
 * type. The route holds remote posts cached for a specific local user, so it is
 * limited to users who can use ActivityPub, and to their own feed.
 *
 * @since 9.3.0
 */
class Remote_Posts_Controller extends \WP_REST_Posts_Controller {
	use Reader_Permission;

	/**
	 * Check whether a request has read access to a single cached post.
	 *
	 * @since 9.3.0
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return true|\WP_Error True if the request has read access, WP_Error otherwise.
	 */
	public function get_item_permissions_check( $request ) {
		$permission = $this->check_reader_capability();

		if ( \is_wp_error( $permission ) ) {
			return $permission;
		}

		return parent::get_item_permissions_check( $request );
	}

	/**
	 * Check whether a post can be read.
	 *
	 * This is the shared predicate: core's own `get_item_permissions_check()` calls it, and so
	 * does `WP_REST_Comments_Controller`, which serves the remote replies cached on these posts.
	 * Scoping here covers both routes; scoping only the route callbacks leaves the comment route
	 * reading the same records.
	 *
	 * @since 9.3.0
	 *
	 * @param \WP_Post $post Post object.
	 * @return bool True if the post can be read, false otherwise.
	 */
	public function check_read_permission( $post ) {
		if ( ! parent::check_read_permission( $post ) ) {
			return false;
		}

		return $this->can_read_feed_of( \get_post_meta( $post->ID, '_activitypub_user_id', false ) );
	}
}
