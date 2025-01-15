<?php
/**
 * Outbox REST API endpoint test file.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Rest;

use Activitypub\Rest\Outbox_Controller;

/**
 * Tests for Outbox REST API endpoint.
 *
 * @coversDefaultClass Outbox_Controller
 */
class Test_Outbox_Controller extends \Activitypub\Tests\Test_REST_Controller_Testcase {
	/**
	 * Test post IDs.
	 *
	 * @var int[]
	 */
	public static $post_ids;

	/**
	 * Set up class test fixtures.
	 *
	 * @param \WP_UnitTest_Factory $factory WordPress unit test factory.
	 */
	public static function wpSetUpBeforeClass( $factory ) {
		self::$post_ids = $factory->post->create_many( 10 );
	}

	/**
	 * Clean up test fixtures.
	 */
	public static function wpTearDownAfterClass() {
		foreach ( self::$post_ids as $post_id ) {
			\wp_delete_post( $post_id, true );
		}
	}

	/**
	 * Test route registration.
	 *
	 * @covers ::register_routes
	 */
	public function test_register_routes() {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/' . ACTIVITYPUB_REST_NAMESPACE . '/(?:users|actors)/(?P<user_id>[\w\-\.]+)/outbox', $routes );
	}

	/**
	 * Test getting items.
	 *
	 * @covers ::get_items
	 */
	public function test_get_item() {
		$request  = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/1/outbox' );
		$response = \rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
	}

	/**
	 * Test schema.
	 *
	 * @covers ::get_collection_schema
	 */
	public function test_get_collection_schema() {
		$request  = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/0/outbox' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$schema   = ( new Outbox_Controller() )->get_collection_schema();

		$valid = \rest_validate_value_from_schema( $data, $schema );
		$this->assertNotWPError( $valid, 'Response failed schema validation: ' . ( \is_wp_error( $valid ) ? $valid->get_error_message() : '' ) );
	}

	/**
	 * Test user ID validation.
	 *
	 * @covers ::validate_user_id
	 */
	public function test_validate_user_id() {
		$controller = new Outbox_Controller();
		$this->assertTrue( $controller->validate_user_id( 0 ) );
		$this->assertTrue( $controller->validate_user_id( '1' ) );
		$this->assertWPError( $controller->validate_user_id( 'user-1' ) );
	}

	/**
	 * Test get_item_schema method.
	 *
	 * @doesNotPerformAssertions
	 */
	public function test_get_item_schema() {
		// Controller does not implement get_item_schema().
	}
}
