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
	 * Add an actor block for a user.
	 *
	 * @param int    $user_id The user ID.
	 * @param string $value   The actor URI to block.
	 * @return bool True on success, false on failure.
	 */
	public static function add_block( $user_id, $value ) {
		// Find or create actor post.
		$actor_post = Remote_Actors::fetch_by_uri( $value );
		if ( \is_wp_error( $actor_post ) ) {
			return false;
		}

		$blocked = \get_post_meta( $actor_post->ID, Moderation::BLOCKED_ACTORS_META_KEY, false );
		if ( ! \in_array( (string) $user_id, $blocked, true ) ) {
			/**
			 * Fired when an actor is blocked.
			 *
			 * @param string $value   The blocked actor URI.
			 * @param string $type    The block type (actor, domain, keyword).
			 * @param int    $user_id The user ID.
			 */
			\do_action( 'activitypub_add_user_block', $value, Moderation::TYPE_ACTOR, $user_id );

			$result = (bool) \add_post_meta( $actor_post->ID, Moderation::BLOCKED_ACTORS_META_KEY, (string) $user_id );
			\clean_post_cache( $actor_post->ID );

			return $result;
		}

		return true; // Already blocked.
	}

	/**
	 * Remove an actor block for a user.
	 *
	 * @param int        $user_id The user ID.
	 * @param string|int $value   The actor URI or post ID to unblock.
	 * @return bool True on success, false on failure.
	 */
	public static function remove_block( $user_id, $value ) {
		// Handle both post ID and URI formats.
		if ( \is_numeric( $value ) ) {
			$post_id = (int) $value;
		} else {
			// Otherwise, find the actor post by actor ID.
			$actor_post = Remote_Actors::fetch_by_uri( $value );
			if ( \is_wp_error( $actor_post ) ) {
				return false;
			}
			$post_id = $actor_post->ID;
		}

		/**
		 * Fired when an actor is unblocked.
		 *
		 * @param string $value   The unblocked actor URI.
		 * @param string $type    The block type (actor, domain, keyword).
		 * @param int    $user_id The user ID.
		 */
		\do_action( 'activitypub_remove_user_block', $value, Moderation::TYPE_ACTOR, $user_id );

		$result = \delete_post_meta( $post_id, Moderation::BLOCKED_ACTORS_META_KEY, $user_id );
		\clean_post_cache( $post_id );

		return $result;
	}

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
			'post_type'      => Remote_Actors::POST_TYPE,
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
