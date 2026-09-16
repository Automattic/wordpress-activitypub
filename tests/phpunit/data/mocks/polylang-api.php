<?php
/**
 * Mock of Polylang's API for tests.
 *
 * @package Activitypub
 */

if ( ! function_exists( 'pll_get_post_language' ) ) {
	/**
	 * Minimal stand-in for Polylang's `pll_get_post_language()`.
	 *
	 * The real function reads the language term of the post; the mock reads the
	 * `_test_pll_language` post meta a test sets.
	 *
	 * @param int    $post_id The post ID.
	 * @param string $field   The language field. Only `slug` is supported.
	 *
	 * @return string|false The language slug, or false when the post has none.
	 */
	function pll_get_post_language( $post_id, $field = 'slug' ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed, VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		$lang = \get_post_meta( $post_id, '_test_pll_language', true );

		return $lang ? $lang : false;
	}
}

if ( ! function_exists( 'pll_set_post_language' ) ) {
	/**
	 * Minimal stand-in for Polylang's `pll_set_post_language()`.
	 *
	 * The real function assigns the language term; the mock stores the slug in the
	 * `_test_pll_language` post meta `pll_get_post_language()` reads.
	 *
	 * @param int    $post_id The post ID.
	 * @param string $lang    The language slug.
	 */
	function pll_set_post_language( $post_id, $lang ) {
		\update_post_meta( $post_id, '_test_pll_language', $lang );
	}
}

if ( ! function_exists( 'pll_languages_list' ) ) {
	/**
	 * Minimal stand-in for Polylang's `pll_languages_list()`.
	 *
	 * @param array $args Unused. The real function accepts `fields` and `hide_empty`.
	 *
	 * @return string[] The slugs of the site's languages.
	 */
	function pll_languages_list( $args = array() ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found, VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		return array( 'en', 'de', 'fr' );
	}
}
