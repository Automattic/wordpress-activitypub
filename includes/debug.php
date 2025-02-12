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

/**
 * Debug the outbox post type.
 *
 * @param array  $args      The arguments for the post type.
 * @param string $post_type The post type.
 *
 * @return array The arguments for the post type.
 */
function debug_outbox_post_type( $args, $post_type ) {
	if ( 'ap_outbox' !== $post_type ) {
		return $args;
	}

	$args['show_ui']   = true;
	$args['menu_icon'] = 'dashicons-upload';

	return $args;
}
add_filter( 'register_post_type_args', '\Activitypub\debug_outbox_post_type', 10, 2 );
