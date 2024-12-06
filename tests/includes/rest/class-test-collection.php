<?php
/**
 * Test Moderators REST Endpoint.
 *
 * @package ActivityPub
 */

namespace Activitypub\Tests\Rest;

use Activitypub\Activity\Actor;
use Activitypub\Rest\Collection;

/**
 * Test Moderators REST Endpoint.
 *
 * @coversDefaultClass \Activitypub\Rest\Collection
 */
class Test_Collection extends \WP_UnitTestCase {
	/**
	 * The REST Server.
	 *
	 * @var \WP_REST_Server
	 */
	protected $server;

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
		self::$user_with_cap = $factory->user->create_and_get(
			array(
				'role' => 'administrator',
			)
		);
		self::$user_with_cap->add_cap( 'activitypub' );

		self::$user_without_cap = $factory->user->create_and_get(
			array(
				'role' => 'subscriber',
			)
		);
	}

	/**
	 * Clean up after tests.
	 */
	public static function wpTearDownAfterClass() {
		self::delete_user( self::$user_with_cap->ID );
		self::delete_user( self::$user_without_cap->ID );
	}

	/**
	 * Set up before each test.
	 */
	public function set_up() {
		parent::set_up();

		global $wp_rest_server;

		$wp_rest_server = new \WP_REST_Server();
		$this->server   = $wp_rest_server;

		do_action( 'rest_api_init' );
	}

	/**
	 * Test moderators endpoint response structure.
	 */
	public function test_moderators_get() {
		new \WP_REST_Request( 'GET', '/activitypub/1.0/collections/moderators' );
		$response = Collection::moderators_get();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( 'application/activity+json; charset=' . get_option( 'blog_charset' ), $response->get_headers()['Content-Type'] );

		$data = $response->get_data();

		// Test response structure.
		$this->assertArrayHasKey( '@context', $data );
		$this->assertEquals( Actor::JSON_LD_CONTEXT, $data['@context'] );
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
}
