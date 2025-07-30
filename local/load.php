<?php
/**
 * Load the ActivityPub development tools.
 *
 * @package Activitypub
 */

namespace Activitypub\Development;

\Activitypub\Autoloader::register_path( __NAMESPACE__, __DIR__ );

// Initialize local development tools below.

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
