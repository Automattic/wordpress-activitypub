<?php
/**
 * Plugin Name:       Inspect Internal Storage
 * Plugin URI:        https://github.com/Automattic/wordpress-activitypub
 * Description:       Makes the ActivityPub plugin's internal Inbox, Outbox, and remote post (ap_post) storage visible in the WordPress admin, so you can inspect the raw activities the plugin sends and receives.
 * Version:           1.0.0
 * Requires at least: 5.9
 * Requires PHP:      7.4
 * Author:            Automattic
 * Author URI:        https://automattic.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       activitypub-inspect-internal-storage
 * Requires Plugins:  activitypub
 *
 * @package Activitypub
 */

namespace Activitypub\Snippets;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The ActivityPub internal post types to expose, mapped to a dashicon.
 *
 * These collections are normally hidden (`show_ui` is false). The slugs are the
 * plugin's Inbox (received activities), Outbox (sent activities), and remote
 * post / `ap_post` (cached remote objects) storage.
 *
 * @return array<string, string> Post type slug => dashicon class.
 */
function inspect_internal_storage_post_types() {
	return array(
		'ap_inbox'  => 'dashicons-download',
		'ap_outbox' => 'dashicons-upload',
		'ap_post'   => 'dashicons-media-document',
	);
}

/**
 * Make the internal post types visible in the admin UI.
 *
 * @param array  $args      The post type arguments.
 * @param string $post_type The post type slug.
 *
 * @return array The filtered post type arguments.
 */
function inspect_internal_storage_post_type( $args, $post_type ) {
	$post_types = inspect_internal_storage_post_types();

	if ( ! isset( $post_types[ $post_type ] ) ) {
		return $args;
	}

	$args['show_ui']      = true;
	$args['show_in_menu'] = true;
	$args['menu_icon']    = $post_types[ $post_type ];

	return $args;
}
\add_filter( 'register_post_type_args', __NAMESPACE__ . '\inspect_internal_storage_post_type', 10, 2 );

/**
 * Make the ap_post taxonomies (object type and tags) visible in the admin UI.
 *
 * @param array  $args     The taxonomy arguments.
 * @param string $taxonomy The taxonomy slug.
 *
 * @return array The filtered taxonomy arguments.
 */
function inspect_internal_storage_taxonomy( $args, $taxonomy ) {
	if ( ! \in_array( $taxonomy, array( 'ap_object_type', 'ap_tag' ), true ) ) {
		return $args;
	}

	$args['show_ui']      = true;
	$args['show_in_menu'] = true;

	return $args;
}
\add_filter( 'register_taxonomy_args', __NAMESPACE__ . '\inspect_internal_storage_taxonomy', 10, 2 );

/**
 * Add a "Meta" column to the Inbox and Outbox list tables.
 *
 * @param array  $columns   The list table columns.
 * @param string $post_type The post type slug.
 *
 * @return array The filtered columns.
 */
function inspect_internal_storage_columns( $columns, $post_type ) {
	if ( ! \in_array( $post_type, array( 'ap_inbox', 'ap_outbox' ), true ) ) {
		return $columns;
	}

	$columns['ap_meta'] = 'Meta';

	return $columns;
}
\add_filter( 'manage_posts_columns', __NAMESPACE__ . '\inspect_internal_storage_columns', 10, 2 );

/**
 * Render the "Meta" column by listing each stored meta key and value.
 *
 * @param string $column_name The current column name.
 * @param int    $post_id     The current post ID.
 */
function inspect_internal_storage_column_content( $column_name, $post_id ) {
	if ( 'ap_meta' !== $column_name ) {
		return;
	}

	foreach ( \get_post_meta( $post_id ) as $key => $value ) {
		echo \esc_html( $key ) . ': ' . \esc_html( $value[0] ) . '<br>';
	}
}
\add_action( 'manage_posts_custom_column', __NAMESPACE__ . '\inspect_internal_storage_column_content', 10, 2 );
