<?php
/**
 * Interaction REST API endpoint test file.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Rest;

/**
 * Tests for Interaction REST API endpoint.
 *
 * @coversDefaultClass \Activitypub\Rest\Interaction_Controller
 */
class Test_Interaction_Controller extends \Activitypub\Tests\Test_REST_Controller_Testcase {

	/**
	 * Tear down.
	 */
	public function tear_down() {
		\remove_all_filters( 'activitypub_interactions_follow_url' );
		\remove_all_filters( 'activitypub_interactions_reply_url' );

		parent::tear_down();
	}

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
		\add_filter(
			'pre_http_request',
			function () {
				return array(
					'response' => array( 'code' => 200 ),
					'body'     => wp_json_encode(
						array(
							'type' => 'Note',
							'url'  => 'https://example.org/note',
						)
					),
				);
			}
		);

		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/interactions' );
		$request->set_param( 'uri', 'https://example.org/note' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 302, $response->get_status() );
		$this->assertArrayHasKey( 'Location', $response->get_headers() );
		$this->assertStringContainsString( 'post-new.php?in_reply_to=', $response->get_headers()['Location'] );
	}

	/**
	 * Test get_item with custom follow URL filter.
	 *
	 * @covers ::get_item
	 */
	public function test_get_item_custom_follow_url() {
		\add_filter(
			'pre_http_request',
			function () {
				return array(
					'response' => array( 'code' => 200 ),
					'body'     => wp_json_encode(
						array(
							'type' => 'Person',
							'url'  => 'https://example.org/person',
						)
					),
				);
			}
		);

		\add_filter( 'activitypub_interactions_follow_url', array( $this, 'follow_or_reply_url' ) );

		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/interactions' );
		$request->set_param( 'uri', 'https://example.org/person' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 302, $response->get_status() );
		$this->assertArrayHasKey( 'Location', $response->get_headers() );
		$this->assertEquals( 'https://custom-follow-or-reply-url.com', $response->get_headers()['Location'] );
	}

	/**
	 * Test get_item with custom reply URL filter.
	 *
	 * @covers ::get_item
	 */
	public function test_get_item_custom_reply_url() {
		\add_filter(
			'pre_http_request',
			function () {
				return array(
					'response' => array( 'code' => 200 ),
					'body'     => wp_json_encode(
						array(
							'type' => 'Note',
							'url'  => 'https://example.org/note',
						)
					),
				);
			}
		);

		\add_filter( 'activitypub_interactions_reply_url', array( $this, 'follow_or_reply_url' ) );

		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/interactions' );
		$request->set_param( 'uri', 'https://example.org/note' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 302, $response->get_status() );
		$this->assertArrayHasKey( 'Location', $response->get_headers() );
		$this->assertEquals( 'https://custom-follow-or-reply-url.com', $response->get_headers()['Location'] );
	}

	/**
	 * Test get_item with WP_Error response from get_remote_object.
	 *
	 * @covers ::get_item
	 */
	public function test_get_item_wp_error() {
		$this->expectException( \WPDieException::class );

		\add_filter(
			'pre_http_request',
			function () {
				return new \WP_Error( 'http_request_failed', 'Connection failed.' );
			}
		);

		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/interactions' );
		$request->set_param( 'uri', 'https://example.org/person' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 400, $response->get_status() );
		$data = $response->get_data();
		$this->assertEquals( 'activitypub_invalid_object', $data['code'] );
		$this->assertEquals( 'The URL is not supported!', $data['message'] );
	}

	/**
	 * Test get_item with invalid object without type.
	 *
	 * @covers ::get_item
	 */
	public function test_get_item_invalid_object() {
		$this->expectException( \WPDieException::class );

		\add_filter(
			'pre_http_request',
			function () {
				return array(
					'response' => array( 'code' => 200 ),
					'body'     => wp_json_encode(
						array(
							'url' => 'https://example.org/invalid',
						)
					),
				);
			}
		);

		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/interactions' );
		$request->set_param( 'uri', 'https://example.org/invalid' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 400, $response->get_status() );
		$data = $response->get_data();
		$this->assertEquals( 'activitypub_invalid_object', $data['code'] );
		$this->assertEquals( 'The URL is not supported!', $data['message'] );
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
		return 'https://custom-follow-or-reply-url.com';
	}
}
