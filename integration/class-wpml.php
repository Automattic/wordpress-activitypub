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
		\add_filter( 'activitypub_locale', array( self::class, 'get_wpml_post_locale' ), 10, 2 );
	}

	/**
	 * Fetch the post locale from the WPML post data.
	 *
	 * @param string $lang The language code.
	 * @param mixed  $post The post object.
	 *
	 * @return string The modified language code.
	 */
	public static function get_wpml_post_locale( $lang, $post ) {
		if ( ! $post instanceof \WP_Post ) {
			return $lang;
		}

		$language_details = apply_filters( 'wpml_post_language_details', null, $post->ID );

		if ( is_array( $language_details ) && isset( $language_details['language_code'] ) ) {
			$lang = $language_details['language_code'];
		}

		return $lang;
	}
}
