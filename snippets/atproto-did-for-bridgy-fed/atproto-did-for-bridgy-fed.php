<?php
/**
 * Plugin Name: ATproto DID for Bridgy Fed
 * Description: Allows you to serve an ATproto DID from your blog's `.well-known` directory to allow Bridgy Fed to use your blog's hostname as its Bluesky handle
 * Version: 0.0.1
 * Author: Julian Fietkau
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: atproto-did-for-bridgy-fed
 * License: Creative Commons Zero 1.0 Universal
 * License URI: https://creativecommons.org/publicdomain/zero/1.0/
 * Requires Plugins: activitypub
 *
 * @package Activitypub
 */

namespace Activitypub\Snippets;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register rewrite rule for the ATproto DID endpoint.
 */
function atproto_did_register_rule() {
	\add_rewrite_rule(
		'^\.well-known/atproto-did$',
		'index.php?rest_route=/atproto_did/atproto-did',
		'top'
	);
}

/**
 * Flush rewrite rules on plugin activation.
 */
function atproto_did_activate() {
	atproto_did_register_rule();
	\flush_rewrite_rules();
}

/**
 * Register the REST API route for the ATproto DID.
 */
function atproto_did_rest_init() {
	\register_rest_route(
		'atproto_did',
		'atproto-did',
		array(
			'methods'             => 'GET',
			'callback'            => __NAMESPACE__ . '\atproto_did_rest_endpoint',
			'permission_callback' => '__return_true',
		)
	);
}

/**
 * Handle the ATproto DID REST endpoint request.
 *
 * @return \WP_REST_Response The response containing the ATproto DID.
 */
function atproto_did_rest_endpoint() {
	// Insert your DID here.
	$body    = 'did:plc:________________________';
	$status  = 200;
	$headers = array(
		'Content-Type' => 'text/plain',
	);

	return new \WP_REST_Response( $body, $status, $headers );
}

\add_action( 'init', __NAMESPACE__ . '\atproto_did_register_rule' );
\add_action( 'rest_api_init', __NAMESPACE__ . '\atproto_did_rest_init' );
\register_activation_hook( __FILE__, __NAMESPACE__ . '\atproto_did_activate' );
