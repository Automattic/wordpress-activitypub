<?php
/**
 * Remote_Actors_Controller file.
 *
 * @package Activitypub
 */

namespace Activitypub\Rest;

use Activitypub\Collection\Followers;
use Activitypub\Collection\Following;
use Activitypub\Collection\Remote_Posts;

/**
 * Remote_Actors_Controller class.
 *
 * Restricts the WordPress REST route that core generates for the `ap_actor` post
 * type. The route holds the cached remote actor documents and the follower graph,
 * which the plugin's own collection endpoints only expose when the site owner has
 * chosen to publish the social graph.
 *
 * @since 9.3.0
 */
class Remote_Actors_Controller extends \WP_REST_Posts_Controller {
	use Reader_Permission;

	/**
	 * Check whether a request has read access to a single cached actor.
	 *
	 * The collection is scoped by a query filter, which single item requests never
	 * run, so the relationship is checked here to keep another actor's followers
	 * from being read by ID.
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

		if ( $this->can_read_feed_of( $related_user_ids ) ) {
			return true;
		}

		/*
		 * Following is not the only way an actor gets cached: authoring a post that was boosted
		 * or replied to into someone's feed does it too, and those records carry no relationship
		 * meta. The reader already renders that actor's name and avatar next to the post, so the
		 * record is readable by whoever the feed belongs to.
		 */
		if ( self::authored_a_post_in_feed_of( $post->ID, \get_current_user_id() ) ) {
			return true;
		}

		return new \WP_Error(
			'activitypub_rest_forbidden',
			\__( 'Sorry, you are not allowed to read this actor.', 'activitypub' ),
			array( 'status' => \rest_authorization_required_code() )
		);
	}

	/**
	 * Check whether an actor authored a cached post in a given user's feed.
	 *
	 * @since 9.3.0
	 *
	 * @param int $actor_post_id The `ap_actor` post ID.
	 * @param int $user_id       The local user whose feed to look in.
	 * @return bool True if the actor authored at least one post in that feed.
	 */
	private static function authored_a_post_in_feed_of( $actor_post_id, $user_id ) {
		if ( ! $user_id ) {
			return false;
		}

		$posts = \get_posts(
			array(
				'post_type'      => Remote_Posts::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_query'     => array(
					array(
						'key'   => '_activitypub_remote_actor_id',
						'value' => $actor_post_id,
					),
					array(
						'key'   => '_activitypub_user_id',
						'value' => $user_id,
					),
				),
			)
		);

		return ! empty( $posts );
	}
}
