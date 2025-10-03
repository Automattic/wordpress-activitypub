<?php
/**
 * Test file for Activitypub Cache HTTP.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

/**
 * Test class for Activitypub Cache HTTP.
 */
class ActivityPub_TestCase_Cache_HTTP extends \WP_UnitTestCase {
	/**
	 * The REST server.
	 *
	 * @var \Spy_REST_Server
	 */
	public $server;

	/**
	 * Set up the test.
	 */
	public function set_up() {
		parent::set_up();

		// Manually activate the REST server.
		global $wp_rest_server;
		$wp_rest_server = new \Spy_REST_Server();
		$this->server   = $wp_rest_server;
		do_action( 'rest_api_init' );

		add_filter(
			'rest_url',
			function () {
				return get_option( 'home' ) . '/wp-json/';
			}
		);

		add_filter( 'pre_http_request', array( get_called_class(), 'pre_http_request' ), 10, 3 );
		add_filter( 'http_response', array( get_called_class(), 'http_response' ), 10, 3 );
	}

	/**
	 * Tear down the test.
	 */
	public function tear_down() {
		remove_filter( 'pre_http_request', array( get_called_class(), 'pre_http_request' ) );
		remove_filter( 'http_response', array( get_called_class(), 'http_response' ) );
		parent::tear_down();
	}

	/**
	 * Filters the return value of an HTTP request.
	 *
	 * @param bool   $preempt Whether to preempt an HTTP request's return value.
	 * @param array  $request {
	 *      Array of HTTP request arguments.
	 *
	 *      @type string $method Request method.
	 *      @type string $body   Request body.
	 * }
	 * @param string $url The request URL.
	 * @return array|bool|\WP_Error Array containing 'headers', 'body', 'response', 'cookies', 'filename'. A WP_Error instance. A boolean false value.
	 */
	public static function pre_http_request( $preempt, $request, $url ) {
		$p     = wp_parse_url( $url );
		$cache = __DIR__ . '/fixtures/' . sanitize_title( $p['host'] . '-' . $p['path'] ) . '.json';
		if ( file_exists( $cache ) ) {
			return apply_filters(
				'fake_http_response',
				json_decode( file_get_contents( $cache ), true ), // phpcs:ignore WordPress.WP.AlternativeFunctions
				$p['scheme'] . '://' . $p['host'],
				$url,
				$request
			);
		}

		$home_url = home_url();

		// Pretend the url now is the requested one.
		update_option( 'home', $p['scheme'] . '://' . $p['host'] );
		$rest_prefix = home_url() . '/wp-json';

		if ( false === strpos( $url, $rest_prefix ) ) {
			// Restore the old home_url.
			update_option( 'home', $home_url );
			return $preempt;
		}

		$url = substr( $url, strlen( $rest_prefix ) );
		$r   = new \WP_REST_Request( $request['method'], $url );
		if ( ! empty( $request['body'] ) ) {
			foreach ( $request['body'] as $key => $value ) {
				$r->set_param( $key, $value );
			}
		}
		global $wp_rest_server;
		$response = $wp_rest_server->dispatch( $r );
		// Restore the old url.
		update_option( 'home', $home_url );

		/**
		 * Filters the return value of an HTTP request.
		 *
		 * @param array  $response Array containing 'headers', 'body', 'response'.
		 * @param string $url      The request URL.
		 * @param array  $request  Array of HTTP request arguments.
		 */
		return apply_filters(
			'fake_http_response',
			array(
				'headers'  => array(
					'content-type' => 'text/json',
				),
				'body'     => wp_json_encode( $response->data ),
				'response' => array(
					'code' => $response->status,
				),
			),
			$p['scheme'] . '://' . $p['host'],
			$url,
			$request
		);
	}

	/**
	 * Filters the HTTP response.
	 *
	 * @param array  $response HTTP response.
	 * @param array  $args     HTTP request arguments.
	 * @param string $url      The request URL.
	 * @return array HTTP response.
	 */
	public static function http_response( $response, $args, $url ) {
		$p     = wp_parse_url( $url );
		$cache = __DIR__ . '/fixtures/' . sanitize_title( $p['host'] . '-' . $p['path'] ) . '.json';
		if ( ! file_exists( $cache ) ) {
			$headers = wp_remote_retrieve_headers( $response );
			file_put_contents( // phpcs:ignore WordPress.WP.AlternativeFunctions
				$cache,
				wp_json_encode(
					array(
						'headers'  => $headers->getAll(),
						'body'     => wp_remote_retrieve_body( $response ),
						'response' => array(
							'code' => wp_remote_retrieve_response_code( $response ),
						),
					)
				)
			);
		}
		return $response;
	}
}
