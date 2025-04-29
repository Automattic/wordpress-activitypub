<?php
/**
 * WP REST Cache integration file.
 *
 * @package Activitypub
 */

namespace Activitypub\Integration;

use Activitypub\Collection\Actors;

/**
 * Compatibility with the WP REST Cache plugin
 *
 * @see https://wordpress.org/plugins/wp-rest-cache/
 */
class WP_Rest_Cache {
	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		\add_filter( 'wp_rest_cache/allowed_endpoints', array( self::class, 'add_activitypub_endpoints' ) );
	}

	/**
	 * Add ActivityPub endpoints to the list of allowed endpoints.
	 *
	 * @param array $endpoints List of allowed endpoints.
	 *
	 * @return array
	 */
	public static function add_activitypub_endpoints( $endpoints ) {
		$endpoints[ ACTIVITYPUB_REST_NAMESPACE ] = array( 'actors', 'users', 'posts' );

		return $endpoints;
	}
}
