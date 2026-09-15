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

		// Show Mastodon apps the language WPML assigned to a post.
		\add_filter( 'mastodon_api_status_language', array( self::class, 'get_wpml_post_locale' ), 10, 2 );
		// Store the language a Mastodon app sets with WPML.
		\add_filter( 'mastodon_api_pre_save_status_language', array( self::class, 'save_status_language' ), 10, 3 );
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

		$language_details = \apply_filters( 'wpml_post_language_details', null, $post->ID );

		if ( \is_array( $language_details ) && isset( $language_details['language_code'] ) ) {
			$lang = $language_details['language_code'];
		}

		return $lang;
	}

	/**
	 * Store the language a Mastodon app set for a post with WPML.
	 *
	 * A language WPML does not know is left to Enable Mastodon Apps, which stores it as post meta.
	 *
	 * @param bool   $stored   Whether the language has been stored elsewhere.
	 * @param int    $post_id  The post ID.
	 * @param string $language The language code the app submitted.
	 *
	 * @return bool True when WPML stored the language.
	 */
	public static function save_status_language( $stored, $post_id, $language ) {
		$languages = \apply_filters( 'wpml_active_languages', null );

		if ( ! isset( $languages[ $language ] ) ) {
			return $stored;
		}

		\do_action(
			'wpml_set_element_language_details',
			array(
				'element_id'    => $post_id,
				'element_type'  => 'post_' . \get_post_type( $post_id ),
				'language_code' => $language,
			)
		);

		return true;
	}
}
