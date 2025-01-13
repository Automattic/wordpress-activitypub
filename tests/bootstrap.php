<?php
/**
 * Bootstrap file for ActivityPub.
 *
 * @package Activitypub
 */

\define( 'ACTIVITYPUB_DISABLE_INCOMING_INTERACTIONS', false );

\define( 'AP_TESTS_DIR', __DIR__ );
$_tests_dir = \getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = \rtrim( \sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! \file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo "Could not find $_tests_dir/includes/functions.php, have you run bin/install-wp-tests.sh ?" . \PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit( 1 );
}

// Give access to tests_add_filter() function.
require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin() {
	require \dirname( __DIR__ ) . '/activitypub.php';
	$enable_mastodon_apps_plugin = dirname( dirname( __DIR__ ) ) . '/enable-mastodon-apps/enable-mastodon-apps.php'; // phpcs:ignore
	if ( file_exists( $enable_mastodon_apps_plugin ) ) {
		require $enable_mastodon_apps_plugin;
	}
}
\tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

/**
 * Disable HTTP requests.
 *
 * @param mixed  $response The value to return instead of making a HTTP request.
 * @param array  $args     Request arguments.
 * @param string $url      The request URL.
 * @return mixed|false|WP_Error
 */
function http_disable_request( $response, $args, $url ) {
	if ( false !== $response ) {
		// Another filter has already overridden this request.
		return $response;
	}

	/**
	 * Allow HTTP requests to be made.
	 *
	 * @param bool  $allow Whether to allow the HTTP request.
	 * @param array $args  Request arguments.
	 * @param string $url  The request URL.
	 */
	if ( apply_filters( 'tests_allow_http_request', false, $args, $url ) ) {
		// This request has been specifically permitted.
		return false;
	}

	$backtrace = array_reverse( debug_backtrace() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace,PHPCompatibility.FunctionUse.ArgumentFunctionsReportCurrentValue.NeedsInspection
	$trace_str = '';
	foreach ( $backtrace as $frame ) {
		if (
			( isset( $frame['file'] ) && strpos( $frame['file'], 'phpunit.php' ) !== false ) ||
			( isset( $frame['file'] ) && strpos( $frame['file'], 'wp-includes/http.php' ) !== false ) ||
			( isset( $frame['file'] ) && strpos( $frame['file'], 'wp-includes/class-wp-hook.php' ) !== false ) ||
			( isset( $frame['function'] ) && __FUNCTION__ === $frame['function'] ) ||
			( isset( $frame['function'] ) && 'apply_filters' === $frame['function'] )
		) {
			continue;
		}

		if ( $trace_str ) {
			$trace_str .= ', ';
		}

		if ( ! empty( $frame['file'] ) && ! empty( $frame['line'] ) ) {
			$trace_str .= basename( $frame['file'] ) . ':' . $frame['line'];
			if ( ! empty( $frame['function'] ) ) {
				$trace_str .= ' ';
			}
		}

		if ( ! empty( $frame['function'] ) ) {
			if ( ! empty( $frame['class'] ) ) {
				$trace_str .= $frame['class'] . '::';
			}
			$trace_str .= $frame['function'] . '()';
		}
	}

	return new WP_Error( 'cancelled', 'Live HTTP request cancelled by bootstrap.php' );
}
\tests_add_filter( 'pre_http_request', 'http_disable_request', 99, 3 );

// Start up the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php';
require __DIR__ . '/class-activitypub-testcase-cache-http.php';
require __DIR__ . '/class-test-rest-controller-testcase.php';

\Activitypub\Migration::add_default_settings();
