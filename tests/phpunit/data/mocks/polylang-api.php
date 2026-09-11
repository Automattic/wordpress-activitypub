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
