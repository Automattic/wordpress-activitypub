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
 * @since unreleased
 */
class Remote_Posts_Controller extends \WP_REST_Posts_Controller {
	use Reader_Permission;

	/**
	 * Check whether a request has read access to the collection.
	 *
	 * @since unreleased
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return true|\WP_Error True if the request has read access, WP_Error otherwise.
	 */
	public function get_items_permissions_check( $request ) {
		$permission = $this->check_reader_permission();

		if ( \is_wp_error( $permission ) ) {
			return $permission;
		}

		return parent::get_items_permissions_check( $request );
	}

	/**
	 * Check whether a request has read access to a single cached post.
	 *
	 * The collection is scoped by a query filter, which single item requests never
	 * run, so ownership is checked here to keep the feed from being read by ID.
	 *
	 * @since unreleased
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return true|\WP_Error True if the request has read access, WP_Error otherwise.
	 */
	public function get_item_permissions_check( $request ) {
		$permission = $this->check_reader_permission();

		if ( \is_wp_error( $permission ) ) {
			return $permission;
		}

		$permission = parent::get_item_permissions_check( $request );

		if ( \is_wp_error( $permission ) || ! $permission ) {
			return $permission;
		}

		$post = $this->get_post( $request['id'] );

		if ( \is_wp_error( $post ) ) {
			return $post;
		}

		if ( ! $this->can_read_feed_of( \get_post_meta( $post->ID, '_activitypub_user_id', false ) ) ) {
			return new \WP_Error(
				'activitypub_rest_forbidden',
				\__( 'Sorry, you are not allowed to read this post.', 'activitypub' ),
				array( 'status' => \rest_authorization_required_code() )
			);
		}

		return true;
	}
}
