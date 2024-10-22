<?php
/**
 * Jetpack integration file.
 *
 * @package Activitypub
 */

namespace Activitypub\Integration;

/**
 * Jetpack integration class.
 */
class Jetpack {

	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		\add_filter( 'jetpack_sync_post_meta_whitelist', array( self::class, 'add_sync_meta' ) );
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
			'activitypub_user_id',
			'activitypub_inbox',
			'activitypub_actor_json',
		);
		return \array_merge( $allow_list, $activitypub_meta_keys );
	}
}
