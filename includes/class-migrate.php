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

	// public static function get_posts_with_comments( $args = null ) {
	// 	$page =  ( \get_query_var( 'page' ) ? \get_query_var( 'page' ) : 1 );
	//     if ( is_null( $args ) ) {
	//         $args = array(
	//             'number'  => '10',
	//             'offset'  => $page,
	//             'type'    => 'activitypub',
	//             'order'   => 'ASC',
	//         );
	//     }
	//     $comments = \get_comments( $args );
	//     $compat = array();
	//     foreach ( $comments as $comment ) {
	// 		$post_id = $comment->comment_post_ID;
	// 		if ( \get_post_meta( $post_id, '_activitypub_permalink_compat', true ) ) {
	// 			// if has url needs migration
	// 			$compat[] = $post_id;
	// 		}
	//     }
	//     $posts_migrate = \get_posts( \array_unique( $compat ) );
	// 	return $posts_migrate;
	// }

	public static function count_posts() {
		$posts = self::get_posts();
		return \count( $posts );
	}

	/**
	 * migrate_post
	 * first send Delete (obj)
	 * then send Announce (obj)
	 *
	 * @param int $post_id
	 * @param str $url
	 */
	// public static function migrate_post( $post_id, $url ) {
	// 	self::delete_url( $post_id, $url );
	// 	$post = \get_post( $post_id );
	// 	$activitypub_post = new \Activitypub\Model\Post( $post );
	// 	\wp_schedule_single_event( \time() + 1, 'send_announce_activity', array( $activitypub_post ) );
	// 	//return \count( $posts );
	// }

	/**
	 * delete_url
	 * Send a Delete activity to the Fediverse
	 *
	 * @param str $activitypub_permalink_compat
	 */
	// public static function delete_url( $post_id, $url ) {
	// 	$post = \get_post( $post_id );
	// 	$activitypub_post = new \Activitypub\Model\Post( $post );
	// 	\wp_schedule_single_event( \time(), 'activitypub_send_delete_activity', array( $activitypub_post, $url ) );
	// 	\wp_schedule_single_event( \time() + 1, 'delete_post_meta', array( $post_id, '_activitypub_permalink_compat' ) );
	// 	// return admin_notice?;
	// }
}
