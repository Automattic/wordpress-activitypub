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
		add_filter( 'oembed_dataparse', array( __CLASS__, 'handle_filtered_oembed_result' ), 11, 3 );
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

	/**
	 * Handle cases where WordPress has filtered out the oEmbed result for security reasons,
	 * but we can provide a safe ActivityPub-specific markup.
	 *
	 * This runs after wp_filter_oembed_result has potentially nullified the result.
	 *
	 * @param string|false $html The returned oEmbed HTML.
	 * @param object       $data A data object result from an oEmbed provider.
	 * @param string       $url  The URL of the content to be embedded.
	 * @return string|false      The filtered oEmbed HTML or our ActivityPub embed.
	 */
	public static function handle_filtered_oembed_result( $html, $data, $url ) {
		// If we already have valid HTML, return it.
		if ( $html ) {
			return $html;
		}

		// If this isn't a rich or video type, we can't help.
		if ( ! isset( $data->type ) || ! in_array( $data->type, array( 'rich', 'video' ), true ) ) {
			return $html;
		}

		// If there's no HTML in the data, we can't help.
		if ( empty( $data->html ) || ! is_string( $data->html ) ) {
			return $html;
		}

		// Try to get ActivityPub representation.
		$activitypub_html = get_embed_html( $url );
		if ( ! $activitypub_html ) {
			return $html;
		}

		// Return our safer ActivityPub embed HTML.
		return $activitypub_html;
	}
}
