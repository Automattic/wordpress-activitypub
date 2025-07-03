<?php
/**
 * W3 Total Cache extension for ActivityPub requests.
 *
 * @package activitypub
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
		\w3tc_add_action( 'pagecache_page_key', array( self::class, 'pagecache_page_key' ) );
		\add_filter( 'w3tc_pgcache_rules_apache_last', array( self::class, 'w3tc_pgcache_rules_apache_last' ) );
	}

	/**
	 * Filter: Allow adding additional rules at the end of the PGCACHE_CORE block, before the last rule.
	 *
	 * @since 2.7.1
	 *
	 * @param string $key The page key.
	 *
	 * @return string The modified page key.
	 */
	public static function pagecache_page_key( $key ) {
		// Check if this is an ActivityPub request.
		if ( is_activitypub_request() ) {
			$key['key'][1] .= '_activitypub';
		}

		return $key;
	}

	/**
	 * Modify the Apache caching rules for ActivityPub requests.
	 *
	 * @param array $rules The existing Apache rules.
	 *
	 * @return array The modified Apache rules.
	 */
	public static function w3tc_pgcache_rules_apache_last( $rules ) {
		// Add check for ActivityPub Accept header (Content Negotiation).
		$rules .= 'RewriteCond %{HTTP:Accept} (application/(ld\+json|activity\+json|json)) [NC]' . PHP_EOL;
		$rules .= 'RewriteRule .* - [E=W3TC_ACTIVITYPUB:_activitypub]' . PHP_EOL;

		// Also set the environment variable if activitypub parameter is explicitly set.
		$rules .= 'RewriteCond %{QUERY_STRING} (?:^|&)activitypub(?:=|&|$) [NC]' . PHP_EOL;
		$rules .= 'RewriteRule .* - [E=W3TC_ACTIVITYPUB:_activitypub]' . PHP_EOL;

		return $rules;
	}
}
