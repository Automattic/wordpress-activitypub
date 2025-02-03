<?php
/**
 * Following REST API endpoint test file.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Rest;

/**
 * Tests for Following REST API endpoint.
 *
 * @coversDefaultClass \Activitypub\Rest\Following_Controller
 */
class Test_Following_Controller extends \Activitypub\Tests\Test_REST_Controller_Testcase {

	/**
	 * Test route registration.
	 *
	 * @covers ::register_routes
	 */
	public function test_register_routes() {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/' . ACTIVITYPUB_REST_NAMESPACE . '/(?:users|actors)/(?P<user_id>[\w\-\.]+)/following', $routes );
	}

	/**
	 * Test schema.
	 * @todo
	 * @covers ::get_item_schema
	 */
	public function test_get_item_schema() {
		$request  = new \WP_REST_Request( 'OPTIONS', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/0/following' );
		$response = rest_get_server()->dispatch( $request )->get_data();

		$this->assertArrayHasKey( 'schema', $response );
		$schema = $response['schema'];

		// Test specific property types.
		$this->assertEquals( 'array', $schema['properties']['@context']['type'] );
		$this->assertEquals( 'string', $schema['properties']['id']['type'] );
		$this->assertEquals( 'uri', $schema['properties']['id']['format'] );
		$this->assertEquals( array( 'Following' ), $schema['properties']['type']['enum'] );
		$this->assertEquals( 'object', $schema['properties']['icon']['type'] );
		$this->assertEquals( 'date-time', $schema['properties']['published']['format'] );
	}

	/**
	 * Test get_item response.
	 * @todo
	 * @covers ::get_item
	 */
	public function test_get_item() {
		$request  = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/0/following' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertStringContainsString( 'application/activity+json', $response->get_headers()['Content-Type'] );

		$data = $response->get_data();

		// Test required properties.
		$this->assertArrayHasKey( '@context', $data );
		$this->assertArrayHasKey( 'id', $data );
		$this->assertArrayHasKey( 'type', $data );
		$this->assertArrayHasKey( 'name', $data );
		$this->assertArrayHasKey( 'inbox', $data );
		$this->assertArrayHasKey( 'outbox', $data );

		// Test property values.
		$this->assertEquals( 'Following', $data['type'] );
		$this->assertStringContainsString( '/activitypub/1.0/application', $data['id'] );
		$this->assertStringContainsString( '/activitypub/1.0/actors/-1/inbox', $data['inbox'] );
		$this->assertStringContainsString( '/activitypub/1.0/actors/-1/outbox', $data['outbox'] );
	}

	/**
	 * Test that the Following response matches its schema.
	 *
	 * @covers ::get_item
	 * @covers ::get_item_schema
	 */
	public function test_response_matches_schema() {
		$request  = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/0/following' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$schema   = ( new \Activitypub\Rest\Following_Controller() )->get_item_schema();

		$valid = \rest_validate_value_from_schema( $data, $schema );
		$this->assertNotWPError( $valid, 'Response failed schema validation: ' . ( \is_wp_error( $valid ) ? $valid->get_error_message() : '' ) );
	}
}
