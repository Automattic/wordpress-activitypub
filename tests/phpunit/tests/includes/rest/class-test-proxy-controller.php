<?php
/**
 * Test Proxy Controller.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Rest;

use Activitypub\Rest\Proxy_Controller;

/**
 * Test class for Proxy_Controller.
 *
 * @coversDefaultClass \Activitypub\Rest\Proxy_Controller
 */
class Test_Proxy_Controller extends \WP_UnitTestCase {

	/**
	 * REST Server instance.
	 *
	 * @var \WP_REST_Server
	 */
	protected $server;

	/**
	 * User ID for testing.
	 *
	 * @var int
	 */
	protected static $user_id;

	/**
	 * Set up test resources.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		self::$user_id = self::factory()->user->create( array( 'role' => 'author' ) );
		\get_user_by( 'id', self::$user_id )->add_cap( 'activitypub' );
	}

	/**
	 * Set up test fixtures.
	 */
	public function set_up() {
		parent::set_up();

		global $wp_rest_server;
		$wp_rest_server = new \WP_REST_Server();
		$this->server   = $wp_rest_server;

		\do_action( 'rest_api_init' );
	}

	/**
	 * Clean up test resources.
	 */
	public static function tear_down_after_class() {
		\wp_delete_user( self::$user_id );
		parent::tear_down_after_class();
	}

	/**
	 * Test that the proxy route is registered.
	 *
	 * @covers ::register_routes
	 */
	public function test_route_registered() {
		$routes = $this->server->get_routes();
		$this->assertArrayHasKey( '/' . ACTIVITYPUB_REST_NAMESPACE . '/proxy', $routes );
	}

	/**
	 * Test that proxy rejects non-HTTPS URLs.
	 *
	 * @covers ::validate_url
	 */
	public function test_http_url_rejected() {
		// Mock OAuth authentication.
		$this->mock_oauth_auth();

		$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/proxy' );
		$request->set_body_params( array( 'id' => 'http://example.com/users/test' ) );

		$response = $this->server->dispatch( $request );

		$this->assertEquals( 400, $response->get_status() );
		$this->assertEquals( 'rest_invalid_param', $response->get_data()['code'] );

		$this->unmock_oauth_auth();
	}

	/**
	 * Test that proxy rejects private network URLs.
	 *
	 * Uses wp_http_validate_url() which blocks private IP ranges.
	 *
	 * @covers ::validate_url
	 */
	public function test_private_network_rejected() {
		// Mock OAuth authentication.
		$this->mock_oauth_auth();

		$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/proxy' );
		$request->set_body_params( array( 'id' => 'https://192.168.1.1/users/test' ) );

		$response = $this->server->dispatch( $request );

		$this->assertEquals( 400, $response->get_status() );
		$this->assertEquals( 'rest_invalid_param', $response->get_data()['code'] );

		$this->unmock_oauth_auth();
	}

	/**
	 * Test proxy requires authentication.
	 *
	 * @covers ::verify_authentication
	 */
	public function test_requires_authentication() {
		$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/proxy' );
		$request->set_body_params( array( 'id' => 'https://example.com/users/test' ) );

		$response = $this->server->dispatch( $request );

		// Should fail with 401 or similar since no OAuth token is provided.
		$this->assertNotEquals( 200, $response->get_status() );
	}

	/**
	 * Test successful proxy fetch of an actor.
	 *
	 * @covers ::create_item
	 */
	public function test_successful_actor_fetch() {
		// Mock OAuth authentication.
		$this->mock_oauth_auth();

		// Mock the HTTP response.
		$actor_data = array(
			'@context'          => 'https://www.w3.org/ns/activitystreams',
			'type'              => 'Person',
			'id'                => 'https://example.com/users/test',
			'inbox'             => 'https://example.com/users/test/inbox',
			'preferredUsername' => 'test',
			'name'              => 'Test User',
		);

		\add_filter(
			'pre_http_request',
			function () use ( $actor_data ) {
				return array(
					'response' => array( 'code' => 200 ),
					'body'     => \wp_json_encode( $actor_data ),
					'headers'  => array( 'content-type' => 'application/activity+json' ),
				);
			}
		);

		$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/proxy' );
		$request->set_body_params( array( 'id' => 'https://example.com/users/test' ) );

		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertEquals( 'Person', $data['type'] );
		$this->assertEquals( 'https://example.com/users/test', $data['id'] );

		$this->unmock_oauth_auth();
	}

	/**
	 * Test that proxy accepts a WebFinger handle (`user@host`) and resolves it.
	 *
	 * @covers ::validate_url
	 * @covers ::sanitize_url
	 * @covers ::create_item
	 */
	public function test_successful_actor_fetch_by_handle() {
		$this->mock_oauth_auth();

		$actor_data = array(
			'@context'          => 'https://www.w3.org/ns/activitystreams',
			'type'              => 'Person',
			'id'                => 'https://example.com/users/test',
			'inbox'             => 'https://example.com/users/test/inbox',
			'preferredUsername' => 'test',
			'name'              => 'Test User',
		);

		$filter = function ( $preempt, $args, $url ) use ( $actor_data ) {
			if ( false !== \strpos( $url, '/.well-known/webfinger' ) ) {
				return array(
					'response' => array( 'code' => 200 ),
					'body'     => \wp_json_encode(
						array(
							'subject' => 'acct:test@example.com',
							'links'   => array(
								array(
									'rel'  => 'self',
									'type' => 'application/activity+json',
									'href' => $actor_data['id'],
								),
							),
						)
					),
					'headers'  => array( 'content-type' => 'application/jrd+json' ),
				);
			}

			return array(
				'response' => array( 'code' => 200 ),
				'body'     => \wp_json_encode( $actor_data ),
				'headers'  => array( 'content-type' => 'application/activity+json' ),
			);
		};
		\add_filter( 'pre_http_request', $filter, 10, 3 );

		$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/proxy' );
		$request->set_body_params( array( 'id' => 'test@example.com' ) );

		$response = $this->server->dispatch( $request );

		\remove_filter( 'pre_http_request', $filter, 10 );
		$this->unmock_oauth_auth();

		$this->assertEquals( 200, $response->get_status(), \wp_json_encode( $response->get_data() ) );
		$data = $response->get_data();
		$this->assertEquals( 'Person', $data['type'] );
		$this->assertEquals( 'https://example.com/users/test', $data['id'] );
	}

	/**
	 * Test that proxy accepts an `acct:` URI and resolves it via WebFinger.
	 *
	 * @covers ::validate_url
	 * @covers ::sanitize_url
	 * @covers ::create_item
	 */
	public function test_successful_actor_fetch_by_acct_uri() {
		$this->mock_oauth_auth();

		$actor_data = array(
			'@context'          => 'https://www.w3.org/ns/activitystreams',
			'type'              => 'Person',
			'id'                => 'https://example.com/users/test',
			'inbox'             => 'https://example.com/users/test/inbox',
			'preferredUsername' => 'test',
			'name'              => 'Test User',
		);

		$filter = function ( $preempt, $args, $url ) use ( $actor_data ) {
			if ( false !== \strpos( $url, '/.well-known/webfinger' ) ) {
				return array(
					'response' => array( 'code' => 200 ),
					'body'     => \wp_json_encode(
						array(
							'subject' => 'acct:test@example.com',
							'links'   => array(
								array(
									'rel'  => 'self',
									'type' => 'application/activity+json',
									'href' => $actor_data['id'],
								),
							),
						)
					),
					'headers'  => array( 'content-type' => 'application/jrd+json' ),
				);
			}

			return array(
				'response' => array( 'code' => 200 ),
				'body'     => \wp_json_encode( $actor_data ),
				'headers'  => array( 'content-type' => 'application/activity+json' ),
			);
		};
		\add_filter( 'pre_http_request', $filter, 10, 3 );

		$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/proxy' );
		$request->set_body_params( array( 'id' => 'acct:test@example.com' ) );

		$response = $this->server->dispatch( $request );

		\remove_filter( 'pre_http_request', $filter, 10 );
		$this->unmock_oauth_auth();

		$this->assertEquals( 200, $response->get_status(), \wp_json_encode( $response->get_data() ) );
		$data = $response->get_data();
		$this->assertEquals( 'Person', $data['type'] );
		$this->assertEquals( 'https://example.com/users/test', $data['id'] );
	}

	/**
	 * Test that proxy accepts a WebFinger handle with leading `@` and resolves it.
	 *
	 * @covers ::validate_url
	 * @covers ::sanitize_url
	 * @covers ::create_item
	 */
	public function test_successful_actor_fetch_by_handle_with_leading_at() {
		$this->mock_oauth_auth();

		$actor_data = array(
			'@context'          => 'https://www.w3.org/ns/activitystreams',
			'type'              => 'Person',
			'id'                => 'https://example.com/users/test',
			'inbox'             => 'https://example.com/users/test/inbox',
			'preferredUsername' => 'test',
			'name'              => 'Test User',
		);

		$filter = function ( $preempt, $args, $url ) use ( $actor_data ) {
			if ( false !== \strpos( $url, '/.well-known/webfinger' ) ) {
				return array(
					'response' => array( 'code' => 200 ),
					'body'     => \wp_json_encode(
						array(
							'subject' => 'acct:test@example.com',
							'links'   => array(
								array(
									'rel'  => 'self',
									'type' => 'application/activity+json',
									'href' => $actor_data['id'],
								),
							),
						)
					),
					'headers'  => array( 'content-type' => 'application/jrd+json' ),
				);
			}

			return array(
				'response' => array( 'code' => 200 ),
				'body'     => \wp_json_encode( $actor_data ),
				'headers'  => array( 'content-type' => 'application/activity+json' ),
			);
		};
		\add_filter( 'pre_http_request', $filter, 10, 3 );

		$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/proxy' );
		$request->set_body_params( array( 'id' => '@test@example.com' ) );

		$response = $this->server->dispatch( $request );

		\remove_filter( 'pre_http_request', $filter, 10 );
		$this->unmock_oauth_auth();

		$this->assertEquals( 200, $response->get_status(), \wp_json_encode( $response->get_data() ) );
		$data = $response->get_data();
		$this->assertEquals( 'Person', $data['type'] );
		$this->assertEquals( 'https://example.com/users/test', $data['id'] );
	}

	/**
	 * Test that the stream sub-route also accepts acct identifiers.
	 *
	 * Guards against the `format => 'uri'` schema constraint that previously
	 * rejected handles before the validate_callback ran.
	 *
	 * @covers ::register_routes
	 * @covers ::get_stream
	 */
	public function test_stream_accepts_acct_identifier() {
		$this->mock_oauth_auth();

		$filter = function ( $preempt, $args, $url ) {
			if ( false !== \strpos( $url, '/.well-known/webfinger' ) ) {
				return array(
					'response' => array( 'code' => 200 ),
					'body'     => \wp_json_encode(
						array(
							'subject' => 'acct:test@example.com',
							'links'   => array(
								array(
									'rel'  => 'self',
									'type' => 'application/activity+json',
									'href' => 'https://example.com/users/test',
								),
							),
						)
					),
					'headers'  => array( 'content-type' => 'application/jrd+json' ),
				);
			}

			return array(
				'response' => array( 'code' => 200 ),
				'body'     => \wp_json_encode(
					array(
						'type' => 'Person',
						'id'   => 'https://example.com/users/test',
					)
				),
				'headers'  => array( 'content-type' => 'application/activity+json' ),
			);
		};
		\add_filter( 'pre_http_request', $filter, 10, 3 );

		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/proxy/stream' );
		$request->set_query_params( array( 'id' => 'test@example.com' ) );

		$response = $this->server->dispatch( $request );

		\remove_filter( 'pre_http_request', $filter, 10 );
		$this->unmock_oauth_auth();

		// The route accepts the handle, the webfinger lookup resolves, and
		// get_stream() reaches the "no eventStream" branch — returning 404
		// rather than the schema-level `rest_invalid_param` 400 it would
		// have produced with the previous `format => 'uri'` constraint.
		$this->assertEquals( 404, $response->get_status() );
		$this->assertEquals( 'activitypub_no_event_stream', $response->get_data()['code'] );
	}

	/**
	 * Test that proxy still rejects garbage input that is neither URL nor handle.
	 *
	 * @covers ::validate_url
	 */
	public function test_malformed_input_rejected() {
		$this->mock_oauth_auth();

		$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/proxy' );
		$request->set_body_params( array( 'id' => 'not-a-url-or-handle' ) );

		$response = $this->server->dispatch( $request );

		$this->unmock_oauth_auth();

		$this->assertEquals( 400, $response->get_status() );
		$this->assertEquals( 'rest_invalid_param', $response->get_data()['code'] );
	}

	/**
	 * Mock OAuth authentication for testing.
	 */
	private function mock_oauth_auth() {
		\add_filter( 'activitypub_oauth_check_permission', '__return_true' );
	}

	/**
	 * Remove OAuth mock.
	 */
	private function unmock_oauth_auth() {
		\remove_filter( 'activitypub_oauth_check_permission', '__return_true' );
	}
}
