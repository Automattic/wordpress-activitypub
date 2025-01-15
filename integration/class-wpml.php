<?php
/**
 * WPML integration.
 *
 * @package Activitypub
 */

namespace Activitypub\Integration;

/**
 * Compatibility with the WPML Multilingual CMS plugin.
 *
 * @see https://wpml.org/
 */
class WPML {
	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		\add_filter( 'activitypub_post_locale', array( self::class, 'get_wpml_post_locale' ), 10, 3 );
	}

	/**
	 * Fetch the post locale from the WPML post data.
	 *
	 * @param string          $lang    The language code.
	 * @param int             $post_id The post ID.
	 * @param WP_Post         $post    The post object
	 *
	 * @return string The modified language code.
	 */
	public static function get_wpml_post_locale( $lang, $post_id, $post ) {
		$language_details = apply_filters( 'wpml_post_language_details', NULL, $post_id );

		if ( is_array( $language_details ) && isset( $language_details['language_code'] ) ) {
			$lang = $language_details['language_code'];
		}

		return $lang;
	}
}
