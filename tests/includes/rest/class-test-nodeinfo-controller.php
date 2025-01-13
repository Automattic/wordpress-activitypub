<?php
/**
 * NodeInfo REST API endpoint test file.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Rest;

/**
 * Tests for NodeInfo REST API endpoint.
 *
 * @coversDefaultClass \Activitypub\Rest\Nodeinfo_Controller
 */
class Test_Nodeinfo_Controller extends \Activitypub\Tests\Test_REST_Controller_Testcase {

	/**
	 * Test route registration.
	 *
	 * @covers ::register_routes
	 */
	public function test_register_routes() {
		$routes = \rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/' . ACTIVITYPUB_REST_NAMESPACE . '/nodeinfo', $routes );
		$this->assertArrayHasKey( '/' . ACTIVITYPUB_REST_NAMESPACE . '/nodeinfo/(?P<version>\d\.\d)', $routes );
	}

	/**
	 * Test get_items method.
	 *
	 * @covers ::get_items
	 */
	public function test_get_items() {
		$request  = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/nodeinfo' );
		$response = \rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertArrayHasKey( 'links', $data );
		$this->assertCount( 2, $data['links'] );

		// Test first link.
		$this->assertEquals( 'https://nodeinfo.diaspora.software/ns/schema/2.0', $data['links'][0]['rel'] );
		$this->assertStringEndsWith( '/nodeinfo/2.0', $data['links'][0]['href'] );

		// Test second link.
		$this->assertEquals( 'https://www.w3.org/ns/activitystreams#Application', $data['links'][1]['rel'] );
		$this->assertStringEndsWith( '/application', $data['links'][1]['href'] );

		// Make sure the links work.
		$request  = new \WP_REST_Request( 'GET', str_replace( \get_rest_url(), '/', $data['links'][0]['href'] ) );
		$response = \rest_get_server()->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );

		$request  = new \WP_REST_Request( 'GET', str_replace( \get_rest_url(), '/', $data['links'][1]['href'] ) );
		$response = \rest_get_server()->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
	}

	/**
	 * Test get_item method with valid version.
	 *
	 * @covers ::get_item
	 * @covers ::get_version_2_0
	 */
	public function test_get_item() {
		$request  = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/nodeinfo/2.0' );
		$response = \rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );

		// Test required fields.
		$this->assertEquals( '2.0', $data['version'] );
		$this->assertArrayHasKey( 'software', $data );
		$this->assertEquals( array( 'activitypub' ), $data['protocols'] );
		$this->assertArrayHasKey( 'services', $data );
		$this->assertEquals( (bool) \get_option( 'users_can_register' ), $data['openRegistrations'] );
		$this->assertArrayHasKey( 'usage', $data );
		$this->assertArrayHasKey( 'metadata', $data );
	}

	/**
	 * Test get_item method with invalid version.
	 *
	 * @covers ::get_item
	 */
	public function test_get_item_invalid_version() {
		$request  = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/nodeinfo/1.0' );
		$response = \rest_get_server()->dispatch( $request );

		$this->assertEquals( 405, $response->get_status() );

		$request  = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/nodeinfo/invalid' );
		$response = \rest_get_server()->dispatch( $request );

		$this->assertEquals( 404, $response->get_status() );
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
