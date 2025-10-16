<?php
/**
 * Debugging functions.
 *
 * @package Activitypub
 */

namespace Activitypub;

use Activitypub\Collection\Inbox;
use Activitypub\Collection\Outbox;
use Activitypub\Collection\Posts;

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
\add_filter( 'http_request_args', '\Activitypub\allow_localhost' );

/**
 * Debug the outbox post type.
 *
 * @param array  $args      The arguments for the post type.
 * @param string $post_type The post type.
 *
 * @return array The arguments for the post type.
 */
function debug_post_type( $args, $post_type ) {
	if ( ! \in_array( $post_type, array( Outbox::POST_TYPE, Inbox::POST_TYPE, Posts::POST_TYPE ), true ) ) {
		return $args;
	}

	$args['show_ui'] = true;

	if ( Outbox::POST_TYPE === $post_type ) {
		$args['menu_icon'] = 'dashicons-upload';
	} elseif ( Inbox::POST_TYPE === $post_type ) {
		$args['menu_icon'] = 'dashicons-download';
	} elseif ( Posts::POST_TYPE === $post_type ) {
		$args['menu_icon'] = 'dashicons-media-document';
	}

	return $args;
}
\add_filter( 'register_post_type_args', '\Activitypub\debug_post_type', 10, 2 );

/**
 * Debug the object type taxonomy.
 *
 * @param array  $args     The arguments for the taxonomy.
 * @param string $taxonomy The taxonomy.
 *
 * @return array The arguments for the taxonomy.
 */
function debug_taxonomy( $args, $taxonomy ) {
	if ( ! in_array( $taxonomy, array( 'ap_object_type', 'ap_tag' ), true ) ) {
		return $args;
	}

	$args['show_ui']      = true;
	$args['show_in_menu'] = true;

	return $args;
}
\add_filter( 'register_taxonomy_args', '\Activitypub\debug_taxonomy', 10, 2 );

/**
 * Debug the outbox post type column.
 *
 * @param array  $columns   The columns.
 * @param string $post_type The post type.
 *
 * @return array The updated columns.
 */
function debug_outbox_post_type_column( $columns, $post_type ) {
	if ( ! \in_array( $post_type, array( Outbox::POST_TYPE, Inbox::POST_TYPE ), true ) ) {
		return $columns;
	}

	$columns['ap_meta'] = 'Meta';

	return $columns;
}
\add_filter( 'manage_posts_columns', '\Activitypub\debug_outbox_post_type_column', 10, 2 );

/**
 * Debug the outbox post type meta.
 *
 * @param string $column_name The column name.
 * @param int    $post_id     The post ID.
 *
 * @return void
 */
function manage_posts_custom_column( $column_name, $post_id ) {
	if ( 'ap_meta' === $column_name ) {
		$meta = \get_post_meta( $post_id );
		foreach ( $meta as $key => $value ) {
			echo \esc_attr( $key ) . ': ' . \esc_html( $value[0] ) . '<br>';
		}
	}
}
\add_action( 'manage_posts_custom_column', '\Activitypub\manage_posts_custom_column', 10, 2 );
