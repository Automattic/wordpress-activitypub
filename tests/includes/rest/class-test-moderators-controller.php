<?php
/**
 * Test Moderators REST Endpoint.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Rest;

use function Activitypub\get_context;

/**
 * Test Moderators REST Endpoint.
 *
 * @group rest
 * @coversDefaultClass \Activitypub\Rest\Moderators_Controller
 */
class Test_Moderators_Controller extends \Activitypub\Tests\Test_REST_Controller_Testcase {
	/**
	 * A user with activitypub capability.
	 *
	 * @var \WP_User
	 */
	protected static $user_with_cap;

	/**
	 * A user without activitypub capability.
	 *
	 * @var \WP_User
	 */
	protected static $user_without_cap;

	/**
	 * Create fake data before tests run.
	 *
	 * @param \WP_UnitTest_Factory $factory Helper that creates fake data.
	 */
	public static function wpSetUpBeforeClass( $factory ) {
		self::$user_with_cap = $factory->user->create_and_get( array( 'role' => 'administrator' ) );
		self::$user_with_cap->add_cap( 'activitypub' );

		self::$user_without_cap = $factory->user->create_and_get( array( 'role' => 'subscriber' ) );
	}

	/**
	 * Clean up after tests.
	 */
	public static function wpTearDownAfterClass() {
		self::delete_user( self::$user_with_cap->ID );
		self::delete_user( self::$user_without_cap->ID );
	}

	/**
	 * Test moderators endpoint response structure.
	 */
	public function test_get_items() {
		$request  = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/collections/moderators' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( 'application/activity+json; charset=' . get_option( 'blog_charset' ), $response->get_headers()['Content-Type'] );

		$data = $response->get_data();

		// Test response structure.
		$this->assertArrayHasKey( '@context', $data );
		$this->assertEquals( get_context(), $data['@context'] );
		$this->assertArrayHasKey( 'id', $data );
		$this->assertArrayHasKey( 'type', $data );
		$this->assertEquals( 'OrderedCollection', $data['type'] );
		$this->assertArrayHasKey( 'orderedItems', $data );
		$this->assertIsArray( $data['orderedItems'] );

		// Test that user with cap is in the list.
		$user_id = home_url( '?author=' . self::$user_with_cap->ID );
		$this->assertContains( $user_id, $data['orderedItems'] );

		// Test that user without cap is not in the list.
		$user_id = home_url( '?author=' . self::$user_without_cap->ID );
		$this->assertNotContains( $user_id, $data['orderedItems'] );
	}

	/**
	 * Test that the Followers response matches its schema.
	 *
	 * @covers ::get_items
	 * @covers ::get_item_schema
	 */
	public function test_response_matches_schema() {
		$request  = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/collections/moderators' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$schema   = ( new \Activitypub\Rest\Moderators_Controller() )->get_item_schema();

		$valid = \rest_validate_value_from_schema( $data, $schema );
		$this->assertNotWPError( $valid, 'Response failed schema validation: ' . ( \is_wp_error( $valid ) ? $valid->get_error_message() : '' ) );
	}

	/**
	 * Test the get item schema.
	 *
	 * @covers ::get_item_schema
	 */
	public function test_get_item_schema() {
		$request    = new \WP_REST_Request( 'OPTIONS', '/' . ACTIVITYPUB_REST_NAMESPACE . '/collections/moderators' );
		$response   = rest_get_server()->dispatch( $request );
		$data       = $response->get_data();
		$properties = $data['schema']['properties'];

		$this->assertArrayHasKey( '@context', $properties );
		$this->assertArrayHasKey( 'id', $properties );
		$this->assertArrayHasKey( 'type', $properties );
		$this->assertArrayHasKey( 'orderedItems', $properties );
	}

	/**
	 * Test get_item method.
	 *
	 * @doesNotPerformAssertions
	 */
	public function test_get_item() {
		// Controller does not implement get_item().
	}
}
