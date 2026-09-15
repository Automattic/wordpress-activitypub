<?php
/**
 * Polylang integration.
 *
 * @package Activitypub
 */

namespace Activitypub\Integration;

/**
 * Compatibility with the Polylang plugin.
 *
 * @see https://polylang.pro/
 */
class Polylang {
	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		\add_filter( 'activitypub_locale', array( self::class, 'get_post_locale' ), 10, 2 );

		// Show Mastodon apps the language Polylang assigned to a post.
		\add_filter( 'mastodon_api_status_language', array( self::class, 'get_post_locale' ), 10, 2 );
		// Store the language a Mastodon app sets with Polylang.
		\add_filter( 'mastodon_api_pre_save_status_language', array( self::class, 'save_status_language' ), 10, 3 );
	}

	/**
	 * Use the language Polylang assigned to the post as the locale of the ActivityPub object.
	 *
	 * A comment takes the language of the post it belongs to.
	 *
	 * @param string $lang The language code.
	 * @param mixed  $item The transformed object.
	 *
	 * @return string The language code.
	 */
	public static function get_post_locale( $lang, $item ) {
		if ( $item instanceof \WP_Comment ) {
			$item = \get_post( $item->comment_post_ID );
		}

		if ( ! $item instanceof \WP_Post || ! \function_exists( 'pll_get_post_language' ) ) {
			return $lang;
		}

		$post_lang = \pll_get_post_language( $item->ID, 'slug' );

		return $post_lang ? $post_lang : $lang;
	}

	/**
	 * Store the language a Mastodon app set for a post with Polylang.
	 *
	 * A language Polylang does not know is left to Enable Mastodon Apps, which stores it as post meta.
	 *
	 * @param bool   $stored   Whether the language has been stored elsewhere.
	 * @param int    $post_id  The post ID.
	 * @param string $language The language code the app submitted.
	 *
	 * @return bool True when Polylang stored the language.
	 */
	public static function save_status_language( $stored, $post_id, $language ) {
		if ( ! \function_exists( 'pll_set_post_language' ) || ! \in_array( $language, \pll_languages_list(), true ) ) {
			return $stored;
		}

		\pll_set_post_language( $post_id, $language );

		return true;
	}
}
