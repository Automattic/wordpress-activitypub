<?php
/**
 * Remote Posts collection file.
 *
 * @package Activitypub
 */

namespace Activitypub\Collection;

/**
 * Default Remote Posts collection.
 *
 * The collection for remote ActivityPub posts (articles, notes, media, etc.) received via
 * Server-to-Server (S2S) federation, cached as the `ap_post` post type. The behavior lives in
 * {@see Remote_Posts}; this class binds it to this default post type.
 */
class Default_Remote_Posts extends Remote_Posts {
	/**
	 * The 'ap_post' post type exclusively stores remote cached ActivityPub objects.
	 *
	 * @return boolean
	 */
	public static function is_exclusive() {
		return true;
	}

	/**
	 * Get the post type.
	 *
	 * @return string
	 */
	public static function get_post_type() {
		return self::POST_TYPE;
	}

	/**
	 * Get the ActivityPub object type. This is the default type.
	 *
	 * @return string
	 */
	public static function get_activitypub_object_type() {
		return '*';
	}
}
