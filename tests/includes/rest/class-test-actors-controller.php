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
	 * Actors Controller instance.
	 *
	 * @var Actors_Controller
	 */
	protected $controller;

	/**
	 * Create fake data before our tests run.
	 */
	public static function set_up_before_class() {
		self::$user_id = self::factory()->user->create( array( 'role' => 'author' ) );

		parent::set_up_before_class();
	}

	/**
	 * Test route registration.
	 *
	 * @covers ::register_routes
	 */
	public function test_register_routes() {
		$routes = $this->server->get_routes();
		$this->assertArrayHasKey( '/activitypub/1.0/users/(?P<user_id>[\w\-\.]+)', $routes );
		$this->assertArrayHasKey( '/activitypub/1.0/users/(?P<user_id>[\w\-\.]+)/remote-follow', $routes );
	}

	/**
	 * Test getting a single actor.
	 *
	 * @covers ::get_item
	 */
	public function test_get_item() {
		wp_set_current_user( self::$user_id );

		$request  = new \WP_REST_Request( 'GET', '/activitypub/1.0/users/' . self::$user_id );
		$response = $this->server->dispatch( $request );

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
		$request  = new \WP_REST_Request( 'GET', '/activitypub/1.0/users/999999' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 404, $response->get_status() );
	}

	/**
	 * Test getting remote follow endpoint.
	 *
	 * @covers ::get_remote_follow_item
	 */
	public function test_get_remote_follow_item() {
		wp_set_current_user( self::$user_id );

		$request = new \WP_REST_Request( 'GET', '/activitypub/1.0/users/' . self::$user_id . '/remote-follow' );
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

		$response = $this->server->dispatch( $request );

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
		wp_set_current_user( self::$user_id );

		$request = new \WP_REST_Request( 'GET', '/activitypub/1.0/users/' . self::$user_id . '/remote-follow' );
		$request->set_param( 'resource', 'invalid-url' );

		$response = $this->server->dispatch( $request );

		$this->assertEquals( 400, $response->get_status() );
	}

	/**
	 * Test schema.
	 *
	 * @covers ::get_item_schema
	 */
	public function test_get_item_schema() {
		$schema = $this->controller->get_item_schema();

		$this->assertIsArray( $schema );
		$this->assertEquals( 'actor', $schema['title'] );
		$this->assertArrayHasKey( 'properties', $schema );

		$properties = $schema['properties'];
		$this->assertArrayHasKey( '@context', $properties );
		$this->assertArrayHasKey( 'id', $properties );
		$this->assertArrayHasKey( 'type', $properties );
		$this->assertArrayHasKey( 'attachment', $properties );
	}
}
