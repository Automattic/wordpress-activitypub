<?php
/**
 * Jetpack integration file.
 *
 * @package Activitypub
 */

namespace Activitypub\Integration;

use Activitypub\Comment;

/**
 * Jetpack integration class.
 */
class Jetpack {

	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		\add_filter( 'jetpack_sync_post_meta_whitelist', array( self::class, 'add_sync_meta' ) );
		\add_filter( 'jetpack_json_api_comment_types', array( self::class, 'add_comment_types' ) );
		\add_filter( 'jetpack_api_include_comment_types_count', array( self::class, 'add_comment_types' ) );
	}

	/**
	 * Add ActivityPub meta keys to the Jetpack sync allow list.
	 *
	 * @param array $allow_list The Jetpack sync allow list.
	 *
	 * @return array The Jetpack sync allow list with ActivityPub meta keys.
	 */
	public static function add_sync_meta( $allow_list ) {
		if ( ! is_array( $allow_list ) ) {
			return $allow_list;
		}
		$activitypub_meta_keys = array(
			'_activitypub_user_id',
			'_activitypub_inbox',
			'_activitypub_actor_json',
		);
		return \array_merge( $allow_list, $activitypub_meta_keys );
	}

	/**
	 * Add custom comment types to the list of comment types.
	 *
	 * @param array $comment_types Default comment types.
	 * @return array
	 */
	public static function add_comment_types( $comment_types ) {
		return array_unique( \array_merge( $comment_types, Comment::get_comment_type_slugs() ) );
	}
}
