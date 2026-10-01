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
	 * The route answers 429 with a Retry-After header once a caller has asked too often.
	 *
	 * @covers ::register_routes
	 */
	public function test_route_is_rate_limited() {
		\wp_set_current_user( 0 );

		// The address is process state that no test case restores, so it is put back afterwards.
		$remote_addr            = $_SERVER['REMOTE_ADDR'] ?? null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Remembered only to be put back.
		$_SERVER['REMOTE_ADDR'] = '203.0.113.50';

		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/interactions' );
		$request->set_param( 'uri', 'https://example.org/note' );

		\add_filter( 'activitypub_rate_limit', '__return_zero' );

		try {
			// `rest_post_dispatch` is applied by `serve_request()`, not by `dispatch()`, so apply it here.
			$response = \apply_filters( 'rest_post_dispatch', \rest_get_server()->dispatch( $request ), \rest_get_server(), $request );
			$headers  = $response->get_headers();

			$this->assertSame( 429, $response->get_status() );
			$this->assertSame( 'activitypub_rate_limited', $response->get_data()['title'] );
			$this->assertSame( '0', $headers['RateLimit-Limit'], 'The answer states the allowance it was measured against.' );
			$this->assertSame( '0', $headers['RateLimit-Remaining'] );
			$this->assertArrayHasKey( 'Retry-After', $headers, 'The refusal says when to come back.' );

			/*
			 * The route used to be public to `Server::add_cache_headers()`, which leaves a public answer
			 * cacheable. Now that it answers per caller, the answer must not be stored by a shared cache
			 * and handed to the next caller.
			 */
			$this->assertStringContainsString( 'no-store', $headers['Cache-Control'] ?? '' );
		} finally {
			\remove_filter( 'activitypub_rate_limit', '__return_zero' );

			if ( null === $remote_addr ) {
				unset( $_SERVER['REMOTE_ADDR'] );
			} else {
				$_SERVER['REMOTE_ADDR'] = $remote_addr;
			}
		}
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

	/**
	 * Intent=quote_request redirects to the editor with quotation_of.
	 *
	 * @covers ::get_item
	 */
	public function test_get_item_quote_request_intent() {
		$remote_object_filter = function () {
			return array(
				'type' => 'Note',
				'url'  => 'https://example.org/note',
			);
		};
		\add_filter( 'activitypub_pre_http_get_remote_object', $remote_object_filter, 10, 2 );

		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/interactions' );
		$request->set_param( 'uri', 'https://example.org/note' );
		$request->set_param( 'intent', 'quote_request' );
		$response = \rest_get_server()->dispatch( $request );

		\remove_filter( 'activitypub_pre_http_get_remote_object', $remote_object_filter );

		$this->assertEquals( 302, $response->get_status() );
		$this->assertSame( \admin_url( 'post-new.php?quotation_of=' . \rawurlencode( 'https://example.org/note' ) ), $response->get_headers()['Location'] );
	}

	/**
	 * Intent=quote is a readable alias for quote_request and redirects the same way.
	 *
	 * @covers ::get_item
	 */
	public function test_get_item_quote_alias_intent() {
		$remote_object_filter = function () {
			return array(
				'type' => 'Note',
				'url'  => 'https://example.org/note',
			);
		};
		\add_filter( 'activitypub_pre_http_get_remote_object', $remote_object_filter, 10, 2 );

		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/interactions' );
		$request->set_param( 'uri', 'https://example.org/note' );
		$request->set_param( 'intent', 'quote' );
		$response = \rest_get_server()->dispatch( $request );

		\remove_filter( 'activitypub_pre_http_get_remote_object', $remote_object_filter );

		$this->assertEquals( 302, $response->get_status() );
		$this->assertSame( \admin_url( 'post-new.php?quotation_of=' . \rawurlencode( 'https://example.org/note' ) ), $response->get_headers()['Location'] );
	}

	/**
	 * An intent value outside the enum is rejected before get_item() runs.
	 *
	 * @covers ::register_routes
	 */
	public function test_get_item_unknown_intent_rejected() {
		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/interactions' );
		$request->set_param( 'uri', 'https://example.org/note' );
		$request->set_param( 'intent', 'boost' );
		$response = \rest_get_server()->dispatch( $request );

		$this->assertEquals( 400, $response->get_status() );
	}
}
