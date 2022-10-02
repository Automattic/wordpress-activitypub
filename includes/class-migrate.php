<?php
namespace Activitypub\Migrate;

/**
 * ActivityPub Migrate DB-Class
 *
 * @author Django Doucet
 */
class Posts {

	/**
	 * Updates ActivityPub Posts for backwards compatibility
	 */
	public static function backcompat_posts() {
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

	public static function get_posts() {
		$post_types = \get_option( 'activitypub_support_post_types', array( 'post', 'page' ) );
		$args = array(
			'numberposts'  => -1,
			'post_type' => $post_types,
			'meta_key' => '_activitypub_permalink_compat',
			'order'   => 'ASC',
		);
		$posts_to_migrate = \get_posts( $args );
		return $posts_to_migrate;
	}

	public static function count_posts() {
		$posts = self::get_posts();
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
		self::announce_url( $post_url );
	}

	/**
	 * announce_url
	 * send Announce (obj)
	 *
	 * @param str post_url (post_id)
	 */
	public static function announce_url( $post_url ) {
		$post_id = \url_to_postid( $post_url );
		$activitypub_post = new \Activitypub\Model\Post( \get_post( $post_id ) );
		\wp_schedule_single_event( \time() + 1, 'activitypub_send_announce_activity', array( $activitypub_post ) );
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
