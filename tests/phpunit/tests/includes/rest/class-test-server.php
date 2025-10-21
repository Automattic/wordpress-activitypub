<?php
/**
 * Test file for Server.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Rest;

use Activitypub\Rest\Server;

/**
 * Test class for Server.
 *
 * @group rest
 * @coversDefaultClass \Activitypub\Rest\Server
 */
class Test_Server extends \WP_Test_REST_TestCase {

	/**
	 * Test init method.
	 *
	 * @covers ::init
	 */
	public function test_init() {
		// Ensure hooks are not already added.
		$this->assertFalse( \has_filter( 'rest_request_before_callbacks', array( Server::class, 'validate_requests' ) ) );
		$this->assertFalse( \has_filter( 'rest_request_parameter_order', array( Server::class, 'request_parameter_order' ) ) );
		$this->assertFalse( \has_filter( 'rest_post_dispatch', array( Server::class, 'filter_output' ) ) );

		Server::init();

		// Verify hooks are added.
		$this->assertEquals( 9, \has_filter( 'rest_request_before_callbacks', array( Server::class, 'validate_requests' ) ) );
		$this->assertEquals( 10, \has_filter( 'rest_request_parameter_order', array( Server::class, 'request_parameter_order' ) ) );
		$this->assertEquals( 10, \has_filter( 'rest_post_dispatch', array( Server::class, 'filter_output' ) ) );
	}

	/**
	 * Test verify_signature method with HEAD request.
	 *
	 * @covers ::verify_signature
	 */
	public function test_verify_signature_head_request() {
		$request = new \WP_REST_Request( 'HEAD', '/' . ACTIVITYPUB_REST_NAMESPACE . '/inbox' );
		$this->assertTrue( Server::verify_signature( $request ) );
	}

	/**
	 * Test verify_signature method with deferred verification.
	 *
	 * @covers ::verify_signature
	 */
	public function test_verify_signature_deferred() {
		\add_filter( 'activitypub_defer_signature_verification', '__return_true' );

		$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/inbox' );
		$this->assertTrue( Server::verify_signature( $request ) );

		\remove_filter( 'activitypub_defer_signature_verification', '__return_true' );
	}

	/**
	 * Data provider for HTTP methods that require signature verification.
	 *
	 * @return array
	 */
	public function signature_required_methods_provider() {
		return array(
			'POST request'   => array( 'POST', true ),
			'PUT request'    => array( 'PUT', false ),
			'PATCH request'  => array( 'PATCH', false ),
			'DELETE request' => array( 'DELETE', false ),
		);
	}

	/**
	 * Test verify_signature method with requests requiring signature.
	 *
	 * @dataProvider signature_required_methods_provider
	 * @covers ::verify_signature
	 *
	 * @param string $method HTTP method.
	 * @param bool   $expect_status Whether to expect status in error data.
	 */
	public function test_verify_signature_methods_requiring_signature( $method, $expect_status ) {
		$request = new \WP_REST_Request( $method, '/' . ACTIVITYPUB_REST_NAMESPACE . '/inbox' );
		$result  = Server::verify_signature( $request );
		$this->assertInstanceOf( '\WP_Error', $result );
		$this->assertEquals( 'activitypub_signature_verification', $result->get_error_code() );

		if ( $expect_status ) {
			$this->assertEquals( 401, $result->get_error_data()['status'] );
		}
	}

	/**
	 * Test verify_signature method with GET request and authorized fetch enabled.
	 *
	 * @covers ::verify_signature
	 */
	public function test_verify_signature_get_request_authorized_fetch() {
		\add_filter( 'activitypub_use_authorized_fetch', '__return_true' );

		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/inbox' );
		$result  = Server::verify_signature( $request );
		$this->assertInstanceOf( '\WP_Error', $result );
		$this->assertEquals( 'activitypub_signature_verification', $result->get_error_code() );

		\remove_filter( 'activitypub_use_authorized_fetch', '__return_true' );
	}

	/**
	 * Test verify_signature method with GET request and authorized fetch disabled.
	 *
	 * @covers ::verify_signature
	 */
	public function test_verify_signature_get_request_no_authorized_fetch() {
		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/inbox' );
		$this->assertTrue( Server::verify_signature( $request ) );
	}

	/**
	 * Test verify_signature method with custom filter callback.
	 *
	 * @covers ::verify_signature
	 */
	public function test_verify_signature_with_custom_filter() {
		$filter_called = false;
		$test_filter   = function ( $defer, $request ) use ( &$filter_called ) {
			$filter_called = true;
			$this->assertFalse( $defer );
			$this->assertInstanceOf( '\WP_REST_Request', $request );
			return false;
		};

		\add_filter( 'activitypub_defer_signature_verification', $test_filter, 10, 2 );

		$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/inbox' );
		$result  = Server::verify_signature( $request );

		$this->assertTrue( $filter_called );
		$this->assertInstanceOf( '\WP_Error', $result );

		\remove_filter( 'activitypub_defer_signature_verification', $test_filter );
	}

	/**
	 * Test verify_signature method with filter that returns different values.
	 *
	 * @covers ::verify_signature
	 */
	public function test_verify_signature_filter_context() {
		$defer_filter = function ( $defer, $request ) {
			// Test that filter receives correct parameters.
			if ( $request->get_method() === 'PUT' ) {
				return true; // Defer for PUT.
			}
			return $defer; // Don't defer for others.
		};

		\add_filter( 'activitypub_defer_signature_verification', $defer_filter, 10, 2 );

		// Test PUT request (should be deferred).
		$put_request = new \WP_REST_Request( 'PUT', '/' . ACTIVITYPUB_REST_NAMESPACE . '/inbox' );
		$this->assertTrue( Server::verify_signature( $put_request ) );

		// Test POST request (should not be deferred).
		$post_request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/inbox' );
		$result       = Server::verify_signature( $post_request );
		$this->assertInstanceOf( '\WP_Error', $result );

		\remove_filter( 'activitypub_defer_signature_verification', $defer_filter );
	}

	/**
	 * Test verify_signature method.
	 *
	 * @covers ::verify_signature
	 */
	public function test_verify_signature() {
		// HEAD requests are always bypassed.
		$request = new \WP_REST_Request( 'HEAD', '/' . ACTIVITYPUB_REST_NAMESPACE . '/inbox' );
		$this->assertTrue( Server::verify_signature( $request ) );

		// POST requests require a signature.
		$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/inbox' );
		$this->assertErrorResponse( 'activitypub_signature_verification', Server::verify_signature( $request ) );

		// GET requests with secure mode enabled require a signature.
		\add_filter( 'activitypub_use_authorized_fetch', '__return_true' );

		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/inbox' );
		$this->assertErrorResponse( 'activitypub_signature_verification', Server::verify_signature( $request ) );

		// GET requests with secure mode disabled are bypassed.
		\remove_filter( 'activitypub_use_authorized_fetch', '__return_true' );
		$this->assertTrue( Server::verify_signature( $request ) );
	}

	/**
	 * Data provider for validate_requests scenarios that return response unchanged.
	 *
	 * @return array[]
	 */
	public function validate_requests_passthrough_provider() {
		return array(
			'HEAD request'          => array(
				'HEAD',
				'/' . ACTIVITYPUB_REST_NAMESPACE . '/inbox',
				'test_response',
				null,
				null,
			),
			'WP_Error response'     => array(
				'POST',
				'/' . ACTIVITYPUB_REST_NAMESPACE . '/inbox',
				new \WP_Error( 'test_error', 'Test error message' ),
				null,
				null,
			),
			'non-ActivityPub route' => array(
				'POST',
				'/wp/v2/posts',
				'test_response',
				null,
				null,
			),
			'no type parameter'     => array(
				'POST',
				'/' . ACTIVITYPUB_REST_NAMESPACE . '/inbox',
				'test_response',
				'application/json',
				array( 'actor' => 'test' ),
			),
			'allowed activity type' => array(
				'POST',
				'/' . ACTIVITYPUB_REST_NAMESPACE . '/inbox',
				'test_response',
				'application/json',
				array( 'type' => 'Follow' ),
			),
		);
	}

	/**
	 * Test validate_requests method scenarios that return response unchanged.
	 *
	 * @dataProvider validate_requests_passthrough_provider
	 * @covers ::validate_requests
	 *
	 * @param string      $method HTTP method.
	 * @param string      $route Request route.
	 * @param mixed       $response Response to validate.
	 * @param string|null $content_type Content type header.
	 * @param array|null  $body_data Request body data.
	 */
	public function test_validate_requests_passthrough_scenarios( $method, $route, $response, $content_type, $body_data ) {
		$handler = array();
		$request = new \WP_REST_Request( $method, $route );

		if ( $content_type ) {
			$request->set_header( 'content-type', $content_type );
		}

		if ( $body_data ) {
			$request->set_body( wp_json_encode( $body_data ) );
		}

		$result = Server::validate_requests( $response, $handler, $request );
		$this->assertEquals( $response, $result );
	}

	/**
	 * Data provider for request_parameter_order scenarios.
	 *
	 * @return array
	 */
	public function request_parameter_order_provider() {
		$default_order = array( 'URL', 'JSON', 'POST', 'defaults' );
		$reordered     = array( 'JSON', 'POST', 'URL', 'defaults' );

		return array(
			'non-ActivityPub route' => array(
				'POST',
				'/wp/v2/posts',
				$default_order,
				$default_order,
			),
			'non-CREATABLE method'  => array(
				'GET',
				'/' . ACTIVITYPUB_REST_NAMESPACE . '/inbox',
				$default_order,
				$default_order,
			),
			'ActivityPub CREATABLE' => array(
				'POST',
				'/' . ACTIVITYPUB_REST_NAMESPACE . '/inbox',
				$default_order,
				$reordered,
			),
		);
	}

	/**
	 * Test request_parameter_order method with different scenarios.
	 *
	 * @dataProvider request_parameter_order_provider
	 * @covers ::request_parameter_order
	 *
	 * @param string $method HTTP method.
	 * @param string $route Request route.
	 * @param array  $input_order Input parameter order.
	 * @param array  $expected_order Expected output order.
	 */
	public function test_request_parameter_order( $method, $route, $input_order, $expected_order ) {
		$request = new \WP_REST_Request( $method, $route );
		$result  = Server::request_parameter_order( $input_order, $request );
		$this->assertEquals( $expected_order, $result );
	}

	/**
	 * Test filter_output method with non-ActivityPub route.
	 *
	 * @covers ::filter_output
	 */
	public function test_filter_output_non_activitypub_route() {
		$response = new \WP_REST_Response( array( 'test' => 'data' ), 200 );
		$server   = new \WP_REST_Server();
		$request  = new \WP_REST_Request( 'GET', '/wp/v2/posts' );

		$result = Server::filter_output( $response, $server, $request );
		$this->assertEquals( $response, $result );
	}

	/**
	 * Test filter_output method with success status code.
	 *
	 * @covers ::filter_output
	 */
	public function test_filter_output_success_status() {
		$response = new \WP_REST_Response( array( 'test' => 'data' ), 200 );
		$server   = new \WP_REST_Server();
		$request  = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/inbox' );

		$result = Server::filter_output( $response, $server, $request );
		$this->assertEquals( $response, $result );
	}

	/**
	 * Test filter_output method with error status code.
	 *
	 * @covers ::filter_output
	 */
	public function test_filter_output_error_status() {
		$response = new \WP_REST_Response(
			array(
				'code'    => 'test_error',
				'message' => 'Test error message',
				'data'    => array( 'status' => 400 ),
			),
			400
		);
		$server   = new \WP_REST_Server();
		$request  = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/inbox' );

		$result = Server::filter_output( $response, $server, $request );

		$expected_data = array(
			'type'     => 'about:blank',
			'title'    => 'test_error',
			'detail'   => 'Test error message',
			'status'   => 400,
			'metadata' => array(
				'code'    => 'test_error',
				'message' => 'Test error message',
				'data'    => array( 'status' => 400 ),
			),
		);

		$this->assertEquals( $expected_data, $result->get_data() );
		$this->assertEquals( 400, $result->get_status() );
	}

	/**
	 * Test filter_output method with WP_Error in response data.
	 *
	 * @covers ::filter_output
	 */
	public function test_filter_output_wp_error_data() {
		$wp_error = new \WP_Error( 'test_error', 'Test error message', array( 'status' => 500 ) );
		$response = new \WP_REST_Response( $wp_error, 500 );
		$server   = new \WP_REST_Server();
		$request  = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/inbox' );

		$result = Server::filter_output( $response, $server, $request );

		$data = $result->get_data();
		$this->assertEquals( 'about:blank', $data['type'] );
		$this->assertEquals( 'test_error', $data['title'] );
		$this->assertEquals( 'Test error message', $data['detail'] );
		$this->assertEquals( 500, $data['status'] );
		$this->assertArrayHasKey( 'metadata', $data );
		$this->assertEquals( 500, $result->get_status() );
	}

	/**
	 * Data provider for missing error data scenarios.
	 *
	 * @return array
	 */
	public function missing_error_data_provider() {
		return array(
			'missing code'    => array(
				array( 'message' => 'Test error message' ),
				'',
				'Test error message',
			),
			'missing message' => array(
				array( 'code' => 'test_error' ),
				'test_error',
				'',
			),
		);
	}

	/**
	 * Test filter_output method with missing error data.
	 *
	 * @dataProvider missing_error_data_provider
	 * @covers ::filter_output
	 *
	 * @param array  $response_data The response data.
	 * @param string $expected_title Expected title value.
	 * @param string $expected_detail Expected detail value.
	 */
	public function test_filter_output_missing_error_data( $response_data, $expected_title, $expected_detail ) {
		$response = new \WP_REST_Response( $response_data, 400 );
		$server   = new \WP_REST_Server();
		$request  = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/inbox' );

		$result = Server::filter_output( $response, $server, $request );

		$data = $result->get_data();
		$this->assertEquals( $expected_title, $data['title'] );
		$this->assertEquals( $expected_detail, $data['detail'] );
	}
}
