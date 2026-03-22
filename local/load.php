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

/*
 * Defer signature verification on local development to better test API requests.
 *
 * @see includes/rest/trait-verification.php :: verify_signature()
 */
\add_filter( 'activitypub_defer_signature_verification', '__return_true', 20 );

/**
 * Allow unsafe HTTP request URLs (localhost, private IPs, etc.) in local development.
 *
 * Disables WordPress SSRF protection (`reject_unsafe_urls`) so that
 * HTTP requests to localhost, private IPs, etc. are permitted during
 * local development. Must NOT run in production.
 *
 * @param array $parsed_args HTTP request arguments.
 *
 * @return array Filtered HTTP request arguments.
 *
 * @see wp_http_validate_url()
 */
function allow_unsafe_http_request_urls( $parsed_args ) {
	$parsed_args['reject_unsafe_urls'] = false;

	return $parsed_args;
}
\add_filter( 'http_request_args', __NAMESPACE__ . '\allow_unsafe_http_request_urls' );

/*
 * Allow http redirect URIs for OAuth clients in local development.
 * In production, only https and custom URI schemes are accepted.
 *
 * @see includes/oauth/class-client.php :: validate_uri_format()
 */
\add_filter( 'activitypub_oauth_allow_http_redirect_uri', '__return_true' );

/*
 * Enable incoming post creation from federated sources without requiring
 * the setting to be toggled in Settings → ActivityPub → General.
 *
 * @see includes/class-activitypub.php :: init()
 */
\add_filter( 'option_activitypub_create_posts', '__return_true' );
