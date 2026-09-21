<?php
/**
 * WordPress login page function stubs.
 *
 * The OAuth templates render through the `wp-login.php` chrome, but the test
 * suite never loads `wp-login.php`, so `login_header()` and `login_footer()` are
 * undefined there. These stand-ins let the templates be rendered and inspected.
 *
 * @package Activitypub
 */

if ( ! \function_exists( 'login_header' ) ) {
	/**
	 * Stub for the `wp-login.php` header.
	 *
	 * @param string   $title         The page title.
	 * @param string   $message       Optional message to display.
	 * @param WP_Error $wp_error      Optional error object. Unused.
	 * @param bool     $deprecated    Deprecated argument. Unused.
	 * @param string   $deprecated_2  Deprecated argument. Unused.
	 */
	function login_header( $title = 'Log In', $message = '', $wp_error = null, $deprecated = '', $deprecated_2 = '' ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed, VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- Signature must match wp-login.php.
		echo '<div id="login">';
		if ( $title ) {
			echo '<h1>' . \esc_html( $title ) . '</h1>';
		}
	}
}

if ( ! \function_exists( 'login_footer' ) ) {
	/**
	 * Stub for the `wp-login.php` footer.
	 *
	 * @param string $deprecated Deprecated argument. Unused.
	 */
	function login_footer( $deprecated = '' ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found, VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- Signature must match wp-login.php.
		echo '</div>';
	}
}
