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
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$accept = strtolower( wp_unslash( $_SERVER['HTTP_ACCEPT'] ) );

	if ( str_contains( $accept, 'text/html' ) ) {
		$representation = 'html';
	} elseif (
		str_contains( $accept, 'application/json' ) ||
		str_contains( $accept, 'application/activity+json' ) ||
		str_contains( $accept, 'application/ld+json' )
	) {
		$representation = 'json';
	}
}

// phpcs:ignore
$config['variants']['representation'] = $representation;
unset( $accept, $representation );

// phpcs:ignore
return $config;
