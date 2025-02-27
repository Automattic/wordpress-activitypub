<?php
/**
 * ActivityPub Embed Handler.
 *
 * @package Activitypub
 */

namespace Activitypub;

use WP_REST_Response;

/**
 * Class to handle embedding ActivityPub content
 */
class Embed {

	/**
	 * Initialize the embed handler
	 */
	public static function init() {
		add_filter( 'pre_oembed_result', array( __CLASS__, 'maybe_use_activitypub_embed' ), 10, 3 );
	}

	/**
	 * Check if a real oEmbed result exists for the given URL.
	 *
	 * @param string $url The URL to check.
	 * @param array  $args Additional arguments passed to wp_oembed_get().
	 * @return bool True if a real oEmbed result exists, false otherwise.
	 */
	public static function has_real_oembed( $url, $args = array() ) {
		// Temporarily remove our filter to avoid infinite loops.
		remove_filter( 'pre_oembed_result', array( __CLASS__, 'maybe_use_activitypub_embed' ), 10, 3 );

		// Try to get a "real" oEmbed result. If found, it'll be cached to avoid unnecessary HTTP requests in `wp_oembed_get`.
		$oembed_result = wp_oembed_get( $url, $args );

		// Add our filter back.
		add_filter( 'pre_oembed_result', array( __CLASS__, 'maybe_use_activitypub_embed' ), 10, 3 );

		return false !== $oembed_result;
	}

	/**
	 * Filter the oembed result to handle ActivityPub content when no oEmbed is found.
	 * Implementation is a bit weird because there's no way to filter on a false result, we have to use `pre_oembed_result`.
	 *
	 * @param null|string $result The UNSANITIZED (and potentially unsafe) HTML that should be used to embed.
	 * @param string      $url    The URL to the content that should be attempted to be embedded.
	 * @param array       $args   Additional arguments passed to wp_oembed_get().
	 * @return null|string         Return null to allow normal oEmbed processing, or string for ActivityPub embed.
	 */
	public static function maybe_use_activitypub_embed( $result, $url, $args ) {
		// If we already have a result, return it.
		if ( null !== $result ) {
			return $result;
		}

		// If we found a real oEmbed, return null to allow normal processing.
		if ( self::has_real_oembed( $url, $args ) ) {
			return null;
		}

		// No oEmbed found, try to get ActivityPub representation.
		$html = get_embed_html( $url );

		// If we couldn't get an ActivityPub embed either, return null to allow normal processing.
		if ( ! $html ) {
			return null;
		}

		// Return the ActivityPub embed HTML.
		return $html;
	}
}
