<?php
/**
 * REST Controller Testcase file.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

/**
 * REST Controller Testcase.
 */
abstract class Test_REST_Controller_Testcase extends \WP_Test_REST_TestCase {

	/**
	 * The REST server.
	 *
	 * @var \WP_REST_Server
	 */
	protected $server;

	/**
	 * Set up the test.
	 */
	public function set_up() {
		parent::set_up();
		add_filter( 'rest_url', array( $this, 'filter_rest_url_for_leading_slash' ), 10, 2 );

		global $wp_rest_server;
		$wp_rest_server = new \Spy_REST_Server();
		do_action( 'rest_api_init', $wp_rest_server );
	}

	/**
	 * Tear down the test.
	 */
	public function tear_down() {
		remove_filter( 'rest_url', array( $this, 'test_rest_url_for_leading_slash' ) );

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
			$this->fail(
				sprintf(
					'REST API URL "%s" should have a leading slash.',
					$path
				)
			);
		}

		return $url;
	}
}
