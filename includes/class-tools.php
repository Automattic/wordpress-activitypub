<?php
namespace Activitypub\Tools;

/**
 * ActivityPub Migrate DB-Class
 *
 * @author Django Doucet
 */
class Posts {

	/**
	 * Marks ActivityPub Posts for backwards compatibility
	 */
	public static function mark_posts_to_migrate() {
		$post_types = \get_option( 'activitypub_support_post_types', array( 'post', 'page' ) );
		$args = array(
			'numberposts'  => -1,
			'post_type' => $post_types,
			'order'   => 'ASC',
		);
		$posts_to_migrate = \get_posts( $args );
		foreach ( $posts_to_migrate as $post ) {
			$permalink = \get_permalink( $post->ID );
			\update_post_meta( $post->ID, '_activitypub_permalink_compat', $permalink );
		}
	}

	public static function get_posts_to_migrate() {
		$post_types = \get_option( 'activitypub_support_post_types', array( 'post', 'page' ) );
		$args = array(
			'numberposts'  => -1,
			'post_type' => $post_types,
			'meta_key' => '_activitypub_permalink_compat',
			'order'   => 'ASC',
		);
		return \get_posts( $args );
	}

	public static function get_posts_with_activitypub_comments() {
		$args = array(
			'comment_type' => 'activitypub',
			'status' => 'all',
		);
		$activitypub_comments = \get_comments( $args );
		$activitypub_comments_posts = array();
		foreach ( $activitypub_comments as $comment ) {
			$meta = \get_post_meta( $comment->comment_post_ID, '_activitypub_permalink_compat', true );
			if ( ! $meta ) {
				continue;
			}
			$activitypub_comments_posts[] = $comment->comment_post_ID;
		}
		return ( empty( \array_unique( $activitypub_comments_posts ) ) ? $activitypub_comments_posts : \get_posts( \array_unique( $activitypub_comments_posts ) ) );
	}

	public static function count_posts_to_migrate() {
		$posts = self::get_posts_to_migrate();
		return \count( $posts );
	}

	public static function count_posts_with_comments_to_migrate() {
		$posts = self::get_posts_with_activitypub_comments();
		return \count( $posts );
	}

	/**
	 * migrate_post
	 * Federate Delete
	 * Federate Announce
	 *
	 * @param str post_url (_activitypub_permalink_compat)
	 * @param str post_author (user_id)
	 */
	public static function migrate_post( $post_url, $post_author ) {
		self::delete_url( $post_url, $post_author );
		self::announce_url( $post_url, $post_author );
	}

	/**
	 * announce_url
	 * send Announce (obj)
	 *
	 * @param str post_url (post_id)
	 * @param int user_id
	 */
	public static function announce_url( $post_url, $user_id ) {
		\wp_schedule_single_event( \time(), 'activitypub_send_announce_activity', array( $post_url, $user_id ) );
	}

	/**
	 * delete_url
	 * Send a Delete activity to the Fediverse
	 *
	 * @param str post_url (_activitypub_permalink_compat)
	 * @param str post_author (user_id)
	 */
	public static function delete_url( $post_url, $post_author ) {
		\wp_schedule_single_event( \time(), 'activitypub_send_delete_url_activity', array( $post_url, $post_author ) );
	}
}
