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
}
