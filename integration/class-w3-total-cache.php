<?php
/**
 * W3 Total Cache extension for ActivityPub requests.
 */

namespace Activitypub\Integration;

use function Activitypub\is_activitypub_request;

/**
 * W3 Total Cache extension for ActivityPub requests.
 */
class W3_Total_Cache {

	/**
	 * Initialize the W3 Total Cache integration.
	 */
	public static function init() {
		// Register the pagecache_page_key filter.
		\w3tc_add_action( 'pagecache_page_key', array( self::class, 'pagecache_page_key' ) );
		// Register additional filters to handle query strings and cache rules.
		\w3tc_add_action( 'pagecache_extract_accept_qs', array( self::class, 'pagecache_extract_accept_qs' ) );
		\w3tc_add_action( 'w3tc_pagecache_rules_apache_accept_qs', array( self::class, 'w3tc_pagecache_rules_accept_qs' ) );
		\w3tc_add_action( 'w3tc_pagecache_rules_nginx_accept_qs', array( self::class, 'w3tc_pagecache_rules_accept_qs' ) );
		\w3tc_add_action( 'w3tc_pagecache_rules_apache_accept_qs_rules', array( self::class, 'w3tc_pagecache_rules_apache_accept_qs_rules' ), 10, 2 );
		\w3tc_add_action( 'w3tc_pagecache_rules_nginx_accept_qs_rules', array( self::class, 'w3tc_pagecache_rules_nginx_accept_qs_rules' ), 10, 2 );
		\w3tc_add_action( 'w3tc_pagecache_rules_apache_uri_prefix', array( self::class, 'w3tc_pagecache_rules_apache_uri_prefix' ) );
		\w3tc_add_action( 'w3tc_pagecache_rules_nginx_uri_prefix', array( self::class, 'w3tc_pagecache_rules_nginx_uri_prefix' ) );
	}

	/**
	 * Modify the page cache key if this is an ActivityPub request.
	 *
	 * @param array $page_key The page cache key.
	 *
	 * @return array The modified page cache key.
	 */
	public static function pagecache_page_key( $page_key ) {
		// Always check for ActivityPub request.
		if ( is_activitypub_request() ) {
			// Make the ActivityPub key more distinct by adding a prefix.
			$page_key[0]  = 'activitypub_' . $page_key[0];
			$page_key[1] .= '_activitypub';
		}

		return $page_key;
	}

	/**
	 * Extract query strings that should be accepted for caching.
	 *
	 * @param array $query_strings The list of query strings to accept.
	 * @return array The modified list of query strings.
	 */
	public static function pagecache_extract_accept_qs( $query_strings ) {
		// Add ActivityPub-specific query parameters that should be considered for caching.
		$query_strings[] = 'activitypub';
		$query_strings[] = 'format';

		return $query_strings;
	}

	/**
	 * Add ActivityPub query strings to the accepted query strings for caching rules.
	 *
	 * @param array $query_strings The list of query strings to accept.
	 * @return array The modified list of query strings.
	 */
	public static function w3tc_pagecache_rules_accept_qs( $query_strings ) {
		$query_strings[] = 'activitypub';
		$query_strings[] = 'format';

		return $query_strings;
	}

	/**
	 * Modify Apache caching rules for ActivityPub query strings.
	 *
	 * @param array  $query_rules The query rules.
	 * @param string $query The query string.
	 *
	 * @return array The modified query rules.
	 */
	public static function w3tc_pagecache_rules_apache_accept_qs_rules( $query_rules, $query ) {
		if ( 'activitypub' === $query || 'format' === $query ) {
			$query_rules[1] = str_replace( '[E=', '[E=W3TC_ACTIVITYPUB:_activitypub,E=', $query_rules[1] );
		}

		return $query_rules;
	}

	/**
	 * Modify the Apache URI prefix for ActivityPub requests.
	 *
	 * @param string $uri_prefix The URI prefix.
	 * @return string The modified URI prefix.
	 */
	public static function w3tc_pagecache_rules_apache_uri_prefix( $uri_prefix ) {
		return $uri_prefix . '%{ENV:W3TC_ACTIVITYPUB}';
	}

	/**
	 * Modify Nginx caching rules for ActivityPub query strings.
	 *
	 * @param array $query_rules The query rules.
	 * @param string $query The query string.
	 * @return array The modified query rules.
	 */
	public static function w3tc_pagecache_rules_nginx_accept_qs_rules( $query_rules, $query ) {
		if ( $query === 'activitypub' || $query === 'format' ) {
			array_splice( $query_rules, 1, 0, '    set $w3tc_activitypub "_activitypub";' );
			array_unshift( $query_rules, 'set $w3tc_activitypub "";' );
		}

		return $query_rules;
	}

	/**
	 * Modify the Nginx URI prefix for ActivityPub requests.
	 *
	 * @param string $uri_prefix The URI prefix.
	 * @return string The modified URI prefix.
	 */
	public static function w3tc_pagecache_rules_nginx_uri_prefix( $uri_prefix ) {
		return $uri_prefix . '$w3tc_activitypub';
	}
}
