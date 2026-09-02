<?php
/**
 * Plugin Name: Comment Type Core Shim (test only)
 * Description: Pretends WordPress core shipped register_comment_type() and wp_get_default_excluded_comment_types(). Never install on a real site.
 *
 * Defines the core API from WordPress/wordpress-develop#12311 and #12310 as an mu-plugin, so
 * every function_exists() guard in the plugin's polyfill takes the "core has it" branch. The
 * definitions are the polyfill's own, loaded under the core name: what is being tested is not
 * the shim, it is that the plugin behaves identically whichever side of the guard is live.
 *
 * @package Activitypub
 */

// The plugin's polyfill IS the reference implementation; load it first, under the core names.
$activitypub_dir = WP_PLUGIN_DIR . '/activitypub/includes/polyfill/';

require_once $activitypub_dir . 'class-wp-comment-type.php';
require_once $activitypub_dir . 'comment-types.php';

// Core registers its built-in types on init at the highest priority, before any plugin.
if ( ! function_exists( 'create_initial_comment_types' ) ) {
	/**
	 * Registers the built-in comment types, as core will.
	 */
	function create_initial_comment_types() {
		register_comment_type(
			'comment',
			array(
				'labels'   => array(
					'name'          => 'Comments',
					'singular_name' => 'Comment',
				),
				'_builtin' => true,
			)
		);
		register_comment_type(
			'pingback',
			array(
				'labels'   => array(
					'name'          => 'Pingbacks',
					'singular_name' => 'Pingback',
				),
				'_builtin' => true,
			)
		);
		register_comment_type(
			'trackback',
			array(
				'labels'   => array(
					'name'          => 'Trackbacks',
					'singular_name' => 'Trackback',
				),
				'_builtin' => true,
			)
		);
		register_comment_type(
			'note',
			array(
				'labels'   => array(
					'name'          => 'Notes',
					'singular_name' => 'Note',
				),
				'public'   => false,
				'internal' => true,
				'_builtin' => true,
			)
		);
	}
	add_action( 'init', 'create_initial_comment_types', 0 );
}

// Marker the tests can read to know which side of the guard is live.
define( 'ACTIVITYPUB_COMMENT_TYPE_CORE_SHIM', true );
