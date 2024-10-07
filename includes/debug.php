<?php
/**
 * Debugging functions.
 *
 * @package Activitypub
 */

namespace Activitypub;

/**
 * Allow localhost URLs if WP_DEBUG is true.
 *
 * @param array $parsed_args An array of HTTP request arguments.
 *
 * @return array Array or string of HTTP request arguments.
 */
function allow_localhost( $parsed_args ) {
	$parsed_args['reject_unsafe_urls'] = false;

	return $parsed_args;
}
add_filter( 'http_request_args', '\Activitypub\allow_localhost' );
