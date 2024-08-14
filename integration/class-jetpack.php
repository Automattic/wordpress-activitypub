<?php
namespace Activitypub\Integration;

class Jetpack {

	public static function init() {
		\add_filter( 'jetpack_sync_post_meta_whitelist', array( self::class, 'add_sync_meta' ) );
	}

	public static function add_sync_meta( $whitelist ) {
		if ( ! is_array( $whitelist ) ) {
			return $whitelist;
		}
		$activitypub_meta_keys = [
			'activitypub_user_id',
			'activitypub_inbox',
			'activitypub_actor_json',
		];
		return \array_merge( $whitelist, $activitypub_meta_keys );
	}
}
