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
		// Late priority so this owns the final endpoint lists: a filter that re-adds the actor tree
		// earlier is overridden, and our deny rules are not dropped by another filter's list.
		\add_filter( 'wp_rest_cache/allowed_endpoints', array( self::class, 'add_activitypub_endpoints' ), \PHP_INT_MAX );
		\add_filter( 'wp_rest_cache/disallowed_endpoints', array( self::class, 'add_disallowed_endpoints' ), \PHP_INT_MAX );
		\add_filter( 'wp_rest_cache/determine_object_type', array( self::class, 'set_object_type' ), 10, 4 );
		\add_filter( 'wp_rest_cache/is_single_item', array( self::class, 'set_is_single_item' ), 10, 3 );
		\add_action( 'transition_post_status', array( self::class, 'transition_post_status' ), 10, 3 );
		\add_action( 'transition_comment_status', array( self::class, 'transition_comment_status' ), 10, 3 );
		\add_action( 'activitypub_migrate', array( self::class, 'purge_on_migrate' ) );
	}

	/**
	 * Purge actor pages cached before caching was restricted to public routes.
	 *
	 * Runs on the migration event, which fires once per upgrade, rather than on every request. Purges
	 * only when upgrading from a version that predates the restriction, so a routine update does not
	 * needlessly cold-start the cache this integration exists to keep warm. Entries stored under the
	 * old rules could otherwise be served from the store and leak an owner-only response.
	 *
	 * @since unreleased
	 *
	 * @param string $version_from_db The version being upgraded from.
	 */
	public static function purge_on_migrate( $version_from_db ) {
		if ( \version_compare( $version_from_db, 'unreleased', '<' ) ) {
			Caching::get_instance()->delete_object_type_caches( 'ActivityPub' );
		}
	}

	/**
	 * Add ActivityPub endpoints to the list of allowed endpoints.
	 *
	 * Only routes that answer every caller identically belong here. Cache entries are
	 * keyed by request URI alone, and an entry is matched as a plain substring of that
	 * URI, so listing a prefix opts in every route below it.
	 *
	 * The actor tree is deliberately absent. It carries owner-only reads, such as the
	 * inbox collection, under the same prefix as its public routes, and the actor ID
	 * sits between the prefix and the route, so no entry can name the public routes
	 * without also naming the private ones.
	 *
	 * This replaces any existing entries under the ActivityPub namespace rather than merging, and runs
	 * at a late priority (see init()), so a caller-varying actor prefix added by another filter is
	 * overridden rather than left to let WP REST Cache store an owner-only response and replay it by
	 * URL. The disallowed list denies the whole actor tree as a further backstop.
	 *
	 * @since unreleased Actor routes are no longer cached.
	 *
	 * @param array $endpoints List of allowed endpoints.
	 *
	 * @return array Filtered list of allowed endpoints.
	 */
	public static function add_activitypub_endpoints( $endpoints ) {
		$endpoints[ ACTIVITYPUB_REST_NAMESPACE ] = array( 'collections/moderators', 'comments', 'nodeinfo', 'posts' );

		return $endpoints;
	}

	/**
	 * Never cache any actor-tree route, whose response can depend on the caller.
	 *
	 * The cache keys entries by request URI, so a response produced for one caller can be served to
	 * another. The actor tree is already kept out of the allowed list, so this is defence in depth for
	 * the case where another filter re-adds the `actors`/`users` prefix: the whole tree, owner-only
	 * inbox, event stream, peer-only `followers/sync`, and the caller-varying followers/following/
	 * outbox collections alike, stays uncacheable.
	 *
	 * The entries are literal prefixes, not per-route regexes, on purpose. WP REST Cache matches this
	 * list with a substring check against a percent-encoded URI for plain-permalink (`rest_route`)
	 * requests, and its regex fallback did not exist before v2026.3.0. A literal `users`/`actors`
	 * prefix matches the whole tree on both permalink styles and every supported cache-plugin version;
	 * a per-route regex matches neither the encoded form nor older versions. Verified against
	 * WP REST Cache 2026.3.1.
	 *
	 * @since unreleased
	 *
	 * @param array $endpoints List of disallowed endpoints.
	 *
	 * @return array Filtered list of disallowed endpoints.
	 */
	public static function add_disallowed_endpoints( $endpoints ) {
		$existing = isset( $endpoints[ ACTIVITYPUB_REST_NAMESPACE ] ) ? (array) $endpoints[ ACTIVITYPUB_REST_NAMESPACE ] : array();

		/*
		 * Merge so deny rules added by an administrator or another filter are preserved, and
		 * deduplicate: WP REST Cache passes the persisted option back through this filter on every
		 * request, so a plain merge would append another copy each time and grow the option without
		 * bound.
		 */
		$endpoints[ ACTIVITYPUB_REST_NAMESPACE ] = \array_values( \array_unique( \array_merge( $existing, array( 'users', 'actors' ) ) ) );

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

		$post_types   = \get_option( 'activitypub_support_post_types', array() );
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

		if ( ! \in_array( $comment->comment_type ?: 'comment', $comment_types, true ) ) {
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
