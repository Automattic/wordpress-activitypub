<?php
/**
 * Interaction REST API endpoint test file.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Rest;

use Activitypub\Tests\Test_REST_Controller_Testcase;

/**
 * Tests for Interaction REST API endpoint.
 *
 * @group rest
 * @coversDefaultClass \Activitypub\Rest\Interaction_Controller
 */
class Test_Interaction_Controller extends Test_REST_Controller_Testcase {

	/**
	 * Test route registration.
	 *
	 * @covers ::register_routes
	 */
	public function test_register_routes() {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/' . ACTIVITYPUB_REST_NAMESPACE . '/interactions', $routes );
	}

	/**
	 * Test get_item with invalid URI.
	 *
	 * @covers ::get_item
	 */
	public function test_get_item_invalid_uri() {
		$this->expectException( \WPDieException::class );

		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/interactions' );
		$request->set_param( 'uri', 'invalid-uri' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 400, $response->get_status() );
		$data = $response->get_data();
		$this->assertEquals( 'activitypub_invalid_object', $data['code'] );
	}

	/**
	 * Test get_item with Note object type.
	 *
	 * @covers ::get_item
	 */
	public function test_get_item() {
		$remote_object_filter = function () {
			return array(
				'type' => 'Note',
				'url'  => 'https://example.org/note',
			);
		};
		\add_filter( 'activitypub_pre_http_get_remote_object', $remote_object_filter, 10, 2 );

		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/interactions' );
		$request->set_param( 'uri', 'https://example.org/note' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 302, $response->get_status() );
		$this->assertArrayHasKey( 'Location', $response->get_headers() );
		$this->assertStringContainsString( 'post-new.php?in_reply_to=', $response->get_headers()['Location'] );

		\remove_filter( 'activitypub_pre_http_get_remote_object', $remote_object_filter );
	}

	/**
	 * Test get_item with custom follow URL filter.
	 *
	 * @covers ::get_item
	 */
	public function test_get_item_custom_follow_url() {
		$remote_object_filter = function () {
			return array(
				'type'  => 'Person',
				'url'   => 'https://example.org/person',
				'links' => array(
					array(
						'rel'  => 'self',
						'type' => 'application/activity+json',
						'href' => 'https://example.org/user/person',
					),
				),
			);
		};
		\add_filter( 'activitypub_pre_http_get_remote_object', $remote_object_filter, 10, 2 );

		\add_filter( 'activitypub_interactions_follow_url', array( $this, 'follow_or_reply_url' ) );

		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/interactions' );
		$request->set_param( 'uri', 'https://example.org/person' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 302, $response->get_status() );
		$this->assertArrayHasKey( 'Location', $response->get_headers() );
		$this->assertEquals( $this->follow_or_reply_url(), $response->get_headers()['Location'] );

		\remove_filter( 'activitypub_interactions_follow_url', array( $this, 'follow_or_reply_url' ) );

		// Test with Webfinger.
		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/interactions' );
		$request->set_param( 'uri', 'activitypub.blog@activitypub.blog' );

		$this->expectExceptionMessage( 'This Interaction type is not supported yet!' );

		rest_get_server()->dispatch( $request );

		\remove_filter( 'activitypub_pre_http_get_remote_object', $remote_object_filter );
	}

	/**
	 * Test get_item with custom reply URL filter.
	 *
	 * @covers ::get_item
	 */
	public function test_get_item_custom_reply_url() {
		$remote_object_filter = function () {
			return array(
				'type' => 'Note',
				'url'  => 'https://example.org/note',
			);
		};
		\add_filter( 'activitypub_pre_http_get_remote_object', $remote_object_filter, 10, 2 );

		\add_filter( 'activitypub_interactions_reply_url', array( $this, 'follow_or_reply_url' ) );

		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/interactions' );
		$request->set_param( 'uri', 'https://example.org/note' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 302, $response->get_status() );
		$this->assertArrayHasKey( 'Location', $response->get_headers() );
		$this->assertEquals( $this->follow_or_reply_url(), $response->get_headers()['Location'] );

		\remove_filter( 'activitypub_pre_http_get_remote_object', $remote_object_filter );
		\remove_filter( 'activitypub_interactions_reply_url', array( $this, 'follow_or_reply_url' ) );
	}

	/**
	 * Test get_item with WP_Error response from get_remote_object.
	 *
	 * @covers ::get_item
	 */
	public function test_get_item_wp_error() {
		$this->expectException( \WPDieException::class );

		$http_request_filter = function () {
			return new \WP_Error( 'http_request_failed', 'Connection failed.' );
		};
		\add_filter( 'pre_http_request', $http_request_filter );

		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/interactions' );
		$request->set_param( 'uri', 'https://example.org/person' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 400, $response->get_status() );
		$data = $response->get_data();
		$this->assertEquals( 'activitypub_invalid_object', $data['code'] );
		$this->assertEquals( 'The URL is not supported!', $data['message'] );

		\remove_filter( 'pre_http_request', $http_request_filter );
	}

	/**
	 * Test get_item with invalid object without type.
	 *
	 * @covers ::get_item
	 */
	public function test_get_item_invalid_object() {
		$this->expectException( \WPDieException::class );

		$http_request_filter = function () {
			return array(
				'response' => array( 'code' => 200 ),
				'body'     => wp_json_encode(
					array(
						'url' => 'https://example.org/invalid',
					)
				),
			);
		};
		\add_filter( 'pre_http_request', $http_request_filter );

		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/interactions' );
		$request->set_param( 'uri', 'https://example.org/invalid' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 400, $response->get_status() );
		$data = $response->get_data();
		$this->assertEquals( 'activitypub_invalid_object', $data['code'] );
		$this->assertEquals( 'The URL is not supported!', $data['message'] );

		\remove_filter( 'pre_http_request', $http_request_filter );
	}

	/**
	 * Test get_item_schema method.
	 *
	 * @doesNotPerformAssertions
	 */
	public function test_get_item_schema() {
		// Controller does not implement get_item_schema().
	}

	/**
	 * Returns a valid follow URL.
	 */
	public function follow_or_reply_url() {
		return 'https://custom-follow-or-reply-url.com/?a=b&c=d';
	}
}
