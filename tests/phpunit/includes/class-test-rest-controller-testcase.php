<?php
/**
 * REST Controller Testcase file.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

use function Activitypub\object_to_uri;

/**
 * REST Controller Testcase.
 */
abstract class Test_REST_Controller_Testcase extends \WP_Test_REST_TestCase {

	/**
	 * Set up the test.
	 */
	public function set_up() {
		parent::set_up();

		\add_filter( 'rest_url', array( $this, 'filter_rest_url_for_leading_slash' ), 10, 2 );
		\add_filter( 'activitypub_pre_http_get_remote_object', array( get_called_class(), 'bypass_url_validation' ), 10, 2 );

		global $wp_rest_server;
		$wp_rest_server = new \Spy_REST_Server();
		\do_action( 'rest_api_init', $wp_rest_server );
	}

	/**
	 * Tear down the test.
	 */
	public function tear_down() {
		\remove_filter( 'rest_url', array( $this, 'filter_rest_url_for_leading_slash' ) );
		\remove_filter( 'activitypub_pre_http_get_remote_object', array( get_called_class(), 'bypass_url_validation' ) );

		global $wp_rest_server;
		$wp_rest_server = null;

		parent::tear_down();
	}

	/**
	 * Test get_item.
	 */
	abstract public function test_get_item();

	/**
	 * Test register_routes.
	 */
	abstract public function test_get_item_schema();

	/**
	 * Filter REST URL for leading slash.
	 *
	 * @param string $url  URL.
	 * @param string $path Path.
	 * @return string
	 */
	public function filter_rest_url_for_leading_slash( $url, $path ) {
		if ( is_multisite() || get_option( 'permalink_structure' ) ) {
			return $url;
		}

		// Make sure path for rest_url has a leading slash for proper resolution.
		if ( 0 !== strpos( $path, '/' ) ) {
			$this->fail( \sprintf( 'REST API URL "%s" should have a leading slash.', $path ) );
		}

		return $url;
	}

	/**
	 * Bypass URL validation by returning fixture data.
	 *
	 * This method is used to bypass wp_http_validate_url() which calls gethostbyname()
	 * and fails when running tests offline. By returning fixture data early, we can
	 * avoid the DNS lookup entirely.
	 *
	 * @param mixed        $pre           The preempted value.
	 * @param array|string $url_or_object The URL or object.
	 * @return mixed
	 */
	public static function bypass_url_validation( $pre, $url_or_object ) {
		$url = object_to_uri( $url_or_object );
		if ( ! $url ) {
			return $pre;
		}

		// Check if fixture exists and return it to bypass wp_http_validate_url().
		$p = \wp_parse_url( $url );
		if ( ! $p || ! isset( $p['host'] ) || ! isset( $p['path'] ) ) {
			return $pre;  // Invalid URL, let normal flow handle it.
		}

		$cache = AP_TESTS_DIR . '/data/fixtures/' . \sanitize_title( $p['host'] . '-' . $p['path'] ) . '.json';
		if ( \file_exists( $cache ) ) {
			$data = \json_decode( \file_get_contents( $cache ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			if ( isset( $data['body'] ) ) {
				return \json_decode( $data['body'], true );
			}
		}

		return $pre;
	}
}
