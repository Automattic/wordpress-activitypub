<?php
/**
 * Test Proxy Controller.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

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

		( new Proxy_Controller() )->register_routes();
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
	 * @covers ::get_item_permissions_check
	 */
	public function test_http_url_rejected() {
		// Mock OAuth authentication.
		$this->mock_oauth_auth();

		$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/proxy' );
		$request->set_body_params( array( 'id' => 'http://example.com/users/test' ) );

		$response = $this->server->dispatch( $request );

		$this->assertEquals( 400, $response->get_status() );
		$this->assertEquals( 'activitypub_invalid_url', $response->get_data()['code'] );

		$this->unmock_oauth_auth();
	}

	/**
	 * Test that proxy rejects localhost URLs.
	 *
	 * @covers ::get_item_permissions_check
	 */
	public function test_localhost_rejected() {
		// Mock OAuth authentication.
		$this->mock_oauth_auth();

		$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/proxy' );
		$request->set_body_params( array( 'id' => 'https://localhost/users/test' ) );

		$response = $this->server->dispatch( $request );

		$this->assertEquals( 400, $response->get_status() );
		$this->assertEquals( 'activitypub_invalid_url', $response->get_data()['code'] );

		$this->unmock_oauth_auth();
	}

	/**
	 * Test proxy requires OAuth authentication.
	 *
	 * @covers ::get_item_permissions_check
	 */
	public function test_requires_oauth() {
		$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/proxy' );
		$request->set_body_params( array( 'id' => 'https://example.com/users/test' ) );

		$response = $this->server->dispatch( $request );

		// Should fail with 401 or similar since no OAuth token is provided.
		$this->assertNotEquals( 200, $response->get_status() );
	}

	/**
	 * Test successful proxy fetch of an actor.
	 *
	 * @covers ::get_item
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
