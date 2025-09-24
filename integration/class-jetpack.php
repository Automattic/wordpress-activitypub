<?php
/**
 * Jetpack integration file.
 *
 * @package Activitypub
 */

namespace Activitypub\Integration;

use Activitypub\Collection\Followers;
use Activitypub\Collection\Following;
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
		$allow_list[] = Followers::FOLLOWER_META_KEY;
		$allow_list[] = Following::FOLLOWING_META_KEY;

		return $allow_list;
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
