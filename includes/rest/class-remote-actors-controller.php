<?php
/**
 * Remote_Actors_Controller file.
 *
 * @package Activitypub
 */

namespace Activitypub\Rest;

use Activitypub\Collection\Followers;
use Activitypub\Collection\Following;

/**
 * Remote_Actors_Controller class.
 *
 * Restricts the WordPress REST route that core generates for the `ap_actor` post
 * type. The route holds the cached remote actor documents and the follower graph,
 * which the plugin's own collection endpoints only expose when the site owner has
 * chosen to publish the social graph.
 *
 * @since unreleased
 */
class Remote_Actors_Controller extends \WP_REST_Posts_Controller {
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
	 * Check whether a request has read access to a single cached actor.
	 *
	 * The collection is scoped by a query filter, which single item requests never
	 * run, so the relationship is checked here to keep another actor's followers
	 * from being read by ID.
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

		$related_user_ids = \array_merge(
			\get_post_meta( $post->ID, Followers::FOLLOWER_META_KEY, false ),
			\get_post_meta( $post->ID, Following::FOLLOWING_META_KEY, false ),
			\get_post_meta( $post->ID, Following::PENDING_META_KEY, false )
		);

		if ( ! $this->can_read_feed_of( $related_user_ids ) ) {
			return new \WP_Error(
				'activitypub_rest_forbidden',
				\__( 'Sorry, you are not allowed to read this actor.', 'activitypub' ),
				array( 'status' => \rest_authorization_required_code() )
			);
		}

		return true;
	}
}
