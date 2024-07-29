<?php
namespace Activitypub;

/**
 * Allow localhost URLs if WP_DEBUG is true.
 *
 * @param array  $r   Array of HTTP request args.
 * @param string $url The request URL.
 *
 * @return array Array or string of HTTP request arguments.
 */
function allow_localhost( $r, $url ) { // phpcs:ignore
	$r['reject_unsafe_urls'] = false;

	return $r;
}
add_filter( 'http_request_args', '\Activitypub\allow_localhost', 10, 2 );
