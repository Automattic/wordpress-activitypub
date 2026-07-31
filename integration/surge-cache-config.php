<?php
/**
 * Content negotiation fix for Surge.
 *
 * @see https://dominikschilling.de/notes/http-accept-header-wordpress-cache-activitypub/
 *
 * @package Activitypub
 */

$representation = 'html';

if ( isset( $_SERVER['HTTP_ACCEPT'] ) ) {
	/*
	 * The html and JSON representations of a URL must land in different cache buckets, and this
	 * bucket boundary must match exactly what the plugin decides to serve, or a page that was served
	 * as one representation could be replayed as the other.
	 *
	 * Reuse the plugin's single source of truth for that boundary: Activitypub\is_json_only_accept().
	 * Do NOT call Activitypub\is_activitypub_request() here. Surge runs this config from its
	 * advanced-cache.php drop-in, on the cache-serve path, before the plugin, the main $wp_query, and
	 * the plugin's autoloader exist, so that function (which needs all three) would fatal. Only the
	 * dependency-free is_json_only_accept() is safe on this path.
	 *
	 * functions-request.php is side-effect-free and the plugin loads it with require_once, so
	 * including it here neither runs code nor risks a redeclare.
	 */
	require_once __DIR__ . '/../includes/functions-request.php';

	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	if ( \Activitypub\is_json_only_accept( $_SERVER['HTTP_ACCEPT'] ) ) {
		$representation = 'json';
	}
}

// phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UndefinedVariable
$config['variants']['representation'] = $representation;
unset( $representation );

// phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UndefinedVariable
return $config;
