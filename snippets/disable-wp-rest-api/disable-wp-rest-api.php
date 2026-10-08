<?php
/**
 * Plugin Name:       Allow ActivityPub through Disable WP REST API
 * Plugin URI:        https://github.com/Automattic/wordpress-activitypub
 * Description:       Keeps ActivityPub endpoints available while Disable WP REST API restricts other REST API requests.
 * Version:           unreleased
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Automattic
 * Author URI:        https://automattic.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Requires Plugins:  activitypub, disable-wp-rest-api
 * Update URI:        https://github.com/Automattic/wordpress-activitypub
 *
 * @package Activitypub
 */

namespace Activitypub\Snippets;

if ( ! \defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Allow the current ActivityPub request through Disable WP REST API.
 *
 * @since 9.4.0
 *
 * @param false|string|string[] $allowed Existing allowed request URIs.
 * @return false|string|string[] Allowed request URIs.
 */
function allow_activitypub_rest_requests( $allowed ) {
	global $wp;

	if ( ! \defined( 'ACTIVITYPUB_REST_NAMESPACE' ) || ! isset( $_SERVER['REQUEST_URI'] ) ) {
		return $allowed;
	}

	// Use WordPress's resolved route for both pretty and plain permalinks.
	$route = $wp->query_vars['rest_route'] ?? '';
	if ( ! \is_string( $route ) ) {
		return $allowed;
	}
	$route = \ltrim( $route, '/' );
	if ( ACTIVITYPUB_REST_NAMESPACE !== $route && ! \str_starts_with( $route, ACTIVITYPUB_REST_NAMESPACE . '/' ) ) {
		return $allowed;
	}

	$allowed   = $allowed ? (array) $allowed : array();
	$allowed[] = $_SERVER['REQUEST_URI']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- The other plugin compares this exact string; it is never output.

	return $allowed;
}

\add_filter( 'disable_wp_rest_api_server_var', __NAMESPACE__ . '\allow_activitypub_rest_requests' );
