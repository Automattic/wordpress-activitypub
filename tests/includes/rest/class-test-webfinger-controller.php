<?php
/**
 * WebFinger REST API endpoint test file.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Rest;

/**
 * Tests for WebFinger REST API endpoint.
 *
 * @coversDefaultClass \Activitypub\Rest\Webfinger_Controller
 */
class Test_Webfinger_Controller extends \Activitypub\Tests\Test_REST_Controller_Testcase {

	/**
	 * Test user.
	 *
	 * @var \WP_User
	 */
	protected static $user;

	/**
	 * Set up class test fixtures.
	 *
	 * @param \WP_UnitTest_Factory $factory WordPress unit test factory.
	 */
	public static function wpSetUpBeforeClass( \WP_UnitTest_Factory $factory ) {
		self::$user = $factory->user->create_and_get(
			array(
				'user_login' => 'test_user',
				'user_email' => 'user@example.org',
			)
		);
		self::$user->add_cap( 'activitypub' );
	}

	/**
	 * Clean up test fixtures.
	 */
	public static function wpTearDownAfterClass() {
		self::delete_user( self::$user->ID );
	}

	/**
	 * Create test environment.
	 */
	public function set_up() {
		parent::set_up();

		\add_filter( 'webfinger_data', array( '\Activitypub\Integration\Webfinger', 'add_pseudo_user_discovery' ), 1, 2 );
	}

	/**
	 * Test route registration.
	 *
	 * @covers ::register_routes
	 */
	public function test_register_routes() {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/' . ACTIVITYPUB_REST_NAMESPACE . '/webfinger', $routes );
	}

	/**
	 * Test schema.
	 *
	 * @covers ::get_item_schema
	 */
	public function test_get_item_schema() {
		$request  = new \WP_REST_Request( 'OPTIONS', '/' . ACTIVITYPUB_REST_NAMESPACE . '/webfinger' );
		$response = rest_get_server()->dispatch( $request )->get_data();

		$this->assertArrayHasKey( 'schema', $response );
		$schema = $response['schema'];

		$this->assertIsArray( $schema );
		$this->assertArrayHasKey( 'properties', $schema );
		$this->assertArrayHasKey( 'subject', $schema['properties'] );
		$this->assertArrayHasKey( 'aliases', $schema['properties'] );
		$this->assertArrayHasKey( 'links', $schema['properties'] );
	}

	/**
	 * Test get_item with valid resource.
	 *
	 * @covers ::get_item
	 */
	public function test_get_item() {
		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/webfinger' );
		$request->set_param( 'resource', 'acct:test_user@example.org' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertStringContainsString( 'application/jrd+json', $response->get_headers()['Content-Type'] );
		$this->assertEquals( '*', $response->get_headers()['Access-Control-Allow-Origin'] );
	}

	/**
	 * Test get_item with invalid resource.
	 *
	 * @covers ::get_item
	 */
	public function test_get_item_with_invalid_resource() {
		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/webfinger' );
		$request->set_param( 'resource', 'invalid-resource' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 400, $response->get_status() );
	}

	/**
	 * Test get_item with missing resource.
	 *
	 * @covers ::get_item
	 */
	public function test_get_item_with_missing_resource() {
		$request  = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/webfinger' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 400, $response->get_status() );
	}

	/**
	 * Test webfinger_data filter.
	 *
	 * @covers ::get_profile
	 */
	public function test_webfinger_data_filter() {
		$test_data = array(
			'subject' => 'acct:test_user@example.org',
			'aliases' => array( 'https://example.org/@test_user' ),
			'links'   => array(
				array(
					'rel'  => 'self',
					'type' => 'application/activity+json',
					'href' => 'https://example.org/author/test_user',
				),
			),
		);

		\add_filter(
			'webfinger_data',
			function ( $data, $webfinger ) use ( $test_data ) {
				$this->assertEquals( 'acct:test_user@example.org', $webfinger );
				return $test_data;
			},
			10,
			2
		);

		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/webfinger' );
		$request->set_param( 'resource', 'acct:test_user@example.org' );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( $test_data, $data );
	}

	/**
	 * Test get_item with author URL resource.
	 *
	 * @covers ::get_item
	 */
	public function test_get_item_with_author_url() {
		$author_url = \get_author_posts_url( self::$user->ID );
		$request    = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/webfinger' );
		$request->set_param( 'resource', $author_url );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertStringContainsString( 'application/jrd+json', $response->get_headers()['Content-Type'] );
		$this->assertContains( $author_url, $data['aliases'] );
		$this->assertArrayHasKey( 'links', $data );
	}

	/**
	 * Test that the Webfinger response matches its schema.
	 *
	 * @covers ::get_item
	 * @covers ::get_item_schema
	 */
	public function test_response_matches_schema() {
		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/webfinger' );
		$request->set_param( 'resource', 'acct:test_user@example.org' );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$schema   = ( new \Activitypub\Rest\Webfinger_Controller() )->get_item_schema();

		$valid = \rest_validate_value_from_schema( $data, $schema );
		$this->assertNotWPError( $valid, 'Response failed schema validation: ' . ( \is_wp_error( $valid ) ? $valid->get_error_message() : '' ) );
	}
}
