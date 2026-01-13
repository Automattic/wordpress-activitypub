<?php
/**
 * Load the ActivityPub development tools.
 *
 * @package Activitypub
 */

namespace Activitypub\Development;

\Activitypub\Autoloader::register_path( __NAMESPACE__, __DIR__ );

// Initialize local development tools below.

/*
 * Enables Jetpack development/debug mode.
 *
 * Setting JETPACK_DEV_DEBUG to true allows Jetpack features to run in a local development environment,
 * bypassing certain production checks and enabling debugging tools. This should only be enabled for local development.
 */
if ( ! defined( 'JETPACK_DEV_DEBUG' ) ) {
	define( 'JETPACK_DEV_DEBUG', true );
}

// Load development WP-CLI commands.
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	\WP_CLI::add_command(
		'activitypub',
		'\Activitypub\Development\Cli',
		array(
			'shortdesc' => 'ActivityPub related commands to manage plugin functionality and the federation of posts and comments.',
		)
	);
}

// Defer signature verification on local development to better test API requests.
\add_filter( 'activitypub_defer_signature_verification', '__return_true', 20 );

\add_filter( 'option_activitypub_create_posts', '__return_true' );
