<?php
/**
 * Actors REST API endpoint test file.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Rest;

use Activitypub\Rest\Actors_Controller;

/**
 * Tests for Actors REST API endpoint.
 *
 * @group rest
 * @coversDefaultClass \Activitypub\Rest\Actors_Controller
 */
class Test_Actors_Controller extends \Activitypub\Tests\Test_REST_Controller_Testcase {
	/**
	 * Test user ID.
	 *
	 * @var int
	 */
	protected static $user_id;

	/**
	 * Original server global.
	 *
	 * @var array
	 */
	protected $original_server;

	/**
	 * Create fake data before our tests run.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		self::$user_id = self::factory()->user->create( array( 'role' => 'author' ) );
		\get_user_by( 'id', self::$user_id )->add_cap( 'activitypub' );
	}

	/**
	 * Set up the test case.
	 */
	public function set_up() {
		parent::set_up();
		$this->original_server = $_SERVER;

		$_SERVER['HTTP_ACCEPT'] = 'application/activity+json';
	}

	/**
	 * Reset the server global after each test.
	 */
	public function tear_down() {
		$_SERVER = $this->original_server;
		parent::tear_down();
	}

	/**
	 * Test getting a single actor.
	 *
	 * @covers ::get_item
	 */
	public function test_get_item() {
		$request  = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/users/' . self::$user_id );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();

		$this->assertIsArray( $data );
		$this->assertArrayHasKey( '@context', $data );
		$this->assertArrayHasKey( 'id', $data );
		$this->assertArrayHasKey( 'type', $data );
		$this->assertEquals( 'Person', $data['type'] );
	}

	/**
	 * Test getting a non-existent actor.
	 *
	 * @covers ::get_item
	 */
	public function test_get_non_existent_item() {
		$request  = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/users/999999' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 400, $response->get_status() );
	}

	/**
	 * Test getting remote follow endpoint.
	 *
	 * @covers ::get_remote_follow_item
	 */
	public function test_get_remote_follow_item() {
		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/users/' . self::$user_id . '/remote-follow' );
		$request->set_param( 'resource', 'https://example.com/user' );

		// Mock Webfinger::get_remote_follow_endpoint.
		add_filter(
			'pre_http_request',
			function () {
				return array(
					'response' => array( 'code' => 200 ),
					'body'     => wp_json_encode(
						array(
							'links' => array(
								array(
									'rel'      => 'http://ostatus.org/schema/1.0/subscribe',
									'template' => 'https://example.com/follow?uri={uri}',
								),
							),
						)
					),
				);
			}
		);

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();

		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'url', $data );
		$this->assertArrayHasKey( 'template', $data );
	}

	/**
	 * Test getting remote follow endpoint with invalid resource.
	 *
	 * @covers ::get_remote_follow_item
	 */
	public function test_get_remote_follow_item_invalid_resource() {
		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/users/' . self::$user_id . '/remote-follow' );
		$request->set_param( 'resource', 'invalid-url' );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 400, $response->get_status() );
	}

	/**
	 * Test that the unauthenticated remote-follow endpoint is rate limited per IP.
	 *
	 * Regression: the endpoint triggers an outbound WebFinger request to a user-supplied
	 * host, so it must be throttled to limit its use as a blind SSRF / amplification vector.
	 *
	 * @covers ::get_remote_follow_item
	 */
	public function test_get_remote_follow_item_rate_limited() {
		// Dedicated IP and a clean slate so this test does not affect (or depend on) others.
		$_SERVER['REMOTE_ADDR'] = '203.0.113.10';
		\delete_transient( 'ap_remote_follow_' . \md5( '203.0.113.10' ) );

		$http_mock = function () {
			return array(
				'response' => array( 'code' => 200 ),
				'body'     => wp_json_encode(
					array(
						'links' => array(
							array(
								'rel'      => 'http://ostatus.org/schema/1.0/subscribe',
								'template' => 'https://example.com/follow?uri={uri}',
							),
						),
					)
				),
			);
		};
		add_filter( 'pre_http_request', $http_mock );

		$dispatch = function () {
			$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/users/' . self::$user_id . '/remote-follow' );
			$request->set_param( 'resource', 'https://example.com/user' );
			return rest_get_server()->dispatch( $request );
		};

		try {
			// The first ten requests from the same IP are allowed.
			for ( $i = 1; $i <= 10; $i++ ) {
				$this->assertEquals( 200, $dispatch()->get_status(), "Request {$i} should be allowed." );
			}

			// The eleventh request is rate limited, with a Retry-After header for back-off.
			$response = $dispatch();
			$this->assertEquals( 429, $response->get_status() );
			$this->assertEquals( 'activitypub_rate_limited', $response->get_data()['code'] );
			$this->assertSame( (string) MINUTE_IN_SECONDS, $response->get_headers()['Retry-After'] ?? null );
		} finally {
			remove_filter( 'pre_http_request', $http_mock );
			\delete_transient( 'ap_remote_follow_' . \md5( '203.0.113.10' ) );
		}
	}

	/**
	 * Test that the remote-follow endpoint fails closed when no client IP is available.
	 *
	 * @covers ::get_remote_follow_item
	 */
	public function test_get_remote_follow_item_fails_closed_without_ip() {
		$no_ip = '__return_empty_string';
		add_filter( 'activitypub_client_ip', $no_ip );

		try {
			$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/users/' . self::$user_id . '/remote-follow' );
			$request->set_param( 'resource', 'https://example.com/user' );
			$response = rest_get_server()->dispatch( $request );

			$this->assertEquals( 429, $response->get_status(), 'Without a determinable IP the endpoint must fail closed.' );
			$this->assertEquals( 'activitypub_rate_limited', $response->get_data()['code'] );
			$this->assertSame( (string) MINUTE_IN_SECONDS, $response->get_headers()['Retry-After'] ?? null, 'The fail-closed response must also carry Retry-After.' );
		} finally {
			remove_filter( 'activitypub_client_ip', $no_ip );
		}
	}

	/**
	 * Test that a template with a disallowed scheme is not returned by the endpoint.
	 *
	 * The gate itself is covered in `Test_Webfinger`; this pins that the controller returns what
	 * the gate produced instead of re-deriving the URL from the response.
	 *
	 * @covers ::get_remote_follow_item
	 */
	public function test_get_remote_follow_item_rejects_javascript_template() {
		$_SERVER['REMOTE_ADDR'] = '203.0.113.11';
		\delete_transient( 'ap_remote_follow_' . \md5( '203.0.113.11' ) );

		$http_mock = function () {
			return array(
				'response' => array( 'code' => 200 ),
				'body'     => wp_json_encode(
					array(
						'subject' => 'acct:someone@remote.example',
						'links'   => array(
							array(
								'rel'      => 'http://ostatus.org/schema/1.0/subscribe',
								'template' => 'javascript:alert(1)//{uri}',
							),
						),
					)
				),
			);
		};
		add_filter( 'pre_http_request', $http_mock );

		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/users/' . self::$user_id . '/remote-follow' );
		$request->set_param( 'resource', 'https://remote.example/user' );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		remove_filter( 'pre_http_request', $http_mock );
		\delete_transient( 'ap_remote_follow_' . \md5( '203.0.113.11' ) );

		// The unsafe template is dropped, so the safe last-resort URL is returned instead.
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'https://remote.example/authorize_interaction?uri={uri}', $data['template'] );
		$this->assertStringStartsWith( 'https://remote.example/authorize_interaction?uri=', $data['url'] );
	}

	/**
	 * Test schema.
	 *
	 * @covers ::get_item_schema
	 */
	public function test_get_item_schema() {
		$schema = ( new Actors_Controller() )->get_item_schema();

		$this->assertIsArray( $schema );
		$this->assertEquals( 'actor', $schema['title'] );
		$this->assertArrayHasKey( 'properties', $schema );

		$properties = $schema['properties'];
		$this->assertArrayHasKey( '@context', $properties );
		$this->assertArrayHasKey( 'id', $properties );
		$this->assertArrayHasKey( 'type', $properties );
		$this->assertArrayHasKey( 'attachment', $properties );
	}

	/**
	 * Test that the Actors response matches its schema.
	 *
	 * @covers ::get_item
	 * @covers ::get_item_schema
	 */
	public function test_response_matches_schema() {
		$request  = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/users/' . self::$user_id );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$schema   = ( new Actors_Controller() )->get_item_schema();

		$valid = \rest_validate_value_from_schema( $data, $schema );
		$this->assertNotWPError( $valid, 'Response failed schema validation: ' . ( \is_wp_error( $valid ) ? $valid->get_error_message() : '' ) );
	}
}
