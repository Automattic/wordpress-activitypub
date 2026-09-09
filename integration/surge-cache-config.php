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
	 * For a given URL, the html and ActivityPub representations must land in different cache buckets,
	 * and this boundary must match how the plugin negotiates that same URL, or one representation could
	 * be replayed for the other. Reuse the plugin's single source of truth:
	 * Activitypub\accept_prefers_activitypub(), which buckets a request as JSON when its highest-priority
	 * (by `q`, then order) media type is ActivityPub. That keeps an ActivityPub-preferring client such
	 * as Mastodon (which also accepts `text/html;q=0.1`) in the JSON bucket instead of the html one.
	 *
	 * This mirrors only the Accept-header branch of Query::is_activitypub_request(), which is the sole
	 * branch that varies a single URL. The `?activitypub` override also forces JSON, but it changes
	 * the URL and Surge already keys on the full URL, so those requests are segregated on their own and
	 * never share a bucket with the negotiated ones.
	 *
	 * Do NOT call Activitypub\is_activitypub_request() here. Surge runs this config from its
	 * advanced-cache.php drop-in, on the cache-serve path, before the plugin, the main $wp_query, and
	 * the plugin's autoloader exist, so that function (which needs all three) would fatal. Only the
	 * dependency-free accept_prefers_activitypub() is safe on this path. functions-request.php is
	 * side-effect-free and the plugin loads it with require_once, so including it here neither runs
	 * code nor risks a redeclare.
	 */
	require_once __DIR__ . '/../includes/functions-request.php';

	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	if ( \Activitypub\accept_prefers_activitypub( $_SERVER['HTTP_ACCEPT'] ) ) {
		$representation = 'json';
	}
}

// phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UndefinedVariable
$config['variants']['representation'] = $representation;
unset( $representation );

// phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UndefinedVariable
return $config;
