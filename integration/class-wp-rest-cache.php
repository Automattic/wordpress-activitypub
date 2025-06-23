<?php
/**
 * WP REST Cache integration file.
 *
 * This file contains code for caching ActivityPub REST requests.
 *
 * Copyright (C) 2025 Epiphyt
 * Original code: https://epiph.yt/en/blog/2025/accidental-ddos-through-activitypub-plugin/
 *
 * Portions of this code are adapted from GPL v2 licensed code.
 * As such, you may also redistribute and/or modify those portions under the terms of
 * the GNU General Public License as published by the Free Software Foundation.
 *
 * https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package Activitypub
 */

namespace Activitypub\Integration;

use Activitypub\Collection\Outbox;
use Activitypub\Comment;
use WP_Rest_Cache_Plugin\Includes\Caching\Caching;

/**
 * Compatibility with the WP REST Cache plugin.
 *
 * @see https://wordpress.org/plugins/wp-rest-cache/
 */
class WP_Rest_Cache {
	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		\add_filter( 'wp_rest_cache/allowed_endpoints', array( self::class, 'add_activitypub_endpoints' ) );
		\add_filter( 'wp_rest_cache/determine_object_type', array( self::class, 'set_object_type' ), 10, 4 );
		\add_filter( 'wp_rest_cache/is_single_item', array( self::class, 'set_is_single_item' ), 10, 3 );
		\add_action( 'transition_post_status', array( self::class, 'transition_post_status' ), 10, 3 );
		\add_action( 'transition_comment_status', array( self::class, 'transition_comment_status' ), 10, 3 );
	}

	/**
	 * Add ActivityPub endpoints to the list of allowed endpoints.
	 *
	 * @param array $endpoints List of allowed endpoints.
	 *
	 * @return array Filtered list of allowed endpoints.
	 */
	public static function add_activitypub_endpoints( $endpoints ) {
		$endpoints[ ACTIVITYPUB_REST_NAMESPACE ] = array( 'actors', 'collections', 'comments', 'interactions', 'nodeinfo', 'posts', 'users' );

		return $endpoints;
	}

	/**
	 * Set whether the cache represents a single item.
	 *
	 * Always return false for ActivityPub endpoints, since cache entries cannot be flushed otherwise.
	 *
	 * @param bool   $is_single Whether the current cache represents a single item.
	 * @param mixed  $data      Data to cache.
	 * @param string $uri       Request URI.
	 *
	 * @return bool Whether the cache represents a single item.
	 */
	public static function set_is_single_item( $is_single, $data, $uri ) {
		if ( self::is_activitypub_endpoint( $uri ) ) {
			return false;
		}

		return $is_single;
	}

	/**
	 * Set object type for ActivityPub.
	 *
	 * @param string $object_type Object type.
	 * @param string $cache_key   Object key.
	 * @param mixed  $data        Data to cache.
	 * @param string $uri         Request URI.
	 *
	 * @return string Updated object type.
	 */
	public static function set_object_type( $object_type, $cache_key, $data, $uri ) {
		if ( self::is_activitypub_endpoint( $uri ) ) {
			return 'ActivityPub';
		}

		return $object_type;
	}

	/**
	 * Reset cache by transition post status.
	 *
	 * @param string   $new_status New post status.
	 * @param string   $old_status Old post status.
	 * @param \WP_Post $post Post object.
	 */
	public static function transition_post_status( $new_status, $old_status, $post ) {
		if ( 'publish' !== $new_status && 'publish' !== $old_status ) {
			return;
		}

		$post_types   = (array) \get_option( 'activitypub_support_post_types', array() );
		$post_types[] = Outbox::POST_TYPE;

		if ( ! \in_array( $post->post_type, $post_types, true ) ) {
			return;
		}

		Caching::get_instance()->delete_object_type_caches( 'ActivityPub' );
	}

	/**
	 * Reset cache by transition comment status.
	 *
	 * @param string      $new_status The new comment status.
	 * @param string      $old_status The old comment status.
	 * @param \WP_Comment $comment    Comment object.
	 */
	public static function transition_comment_status( $new_status, $old_status, $comment ) {
		if ( 'approved' !== $new_status && 'approved' !== $old_status ) {
			return;
		}

		$comment_types   = Comment::get_comment_type_slugs();
		$comment_types[] = 'comment';

		if ( ! \in_array( $comment->comment_type ?: 'comment', $comment_types, true ) ) { // phpcs:ignore Universal.Operators.DisallowShortTernary
			return;
		}

		Caching::get_instance()->delete_object_type_caches( 'ActivityPub' );
	}

	/**
	 * Test, whether the current endpoint is an ActivityPub endpoint.
	 *
	 * @param string $uri URI to test.
	 *
	 * @return bool Whether the current endpoint is an ActivityPub endpoint.
	 */
	private static function is_activitypub_endpoint( $uri ) {
		$search = '/' . ACTIVITYPUB_REST_NAMESPACE . '/';

		return \str_contains( $uri, $search ) || \str_contains( $uri, 'rest_route=' . \rawurlencode( $search ) );
	}
}
