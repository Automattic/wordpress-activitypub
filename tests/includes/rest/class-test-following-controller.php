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
	 * Set up.
	 */
	public function set_up() {
		parent::set_up();

		add_filter(
			'activitypub_rest_following',
			function ( $follow_list ) {
				$users = \Activitypub\Collection\Actors::get_collection();

				foreach ( $users as $user ) {
					$follow_list[] = $user->get_id();
				}

				return $follow_list;
			}
		);
	}

	/**
	 * Tear down.
	 */
	public function tear_down() {
		remove_all_filters( 'activitypub_rest_following' );

		parent::tear_down();
	}
	/**
	 * Test route registration.
	 *
	 * @covers ::register_routes
	 */
	public function test_register_routes() {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/' . ACTIVITYPUB_REST_NAMESPACE . '/(?:users|actors)\/(?P<user_id>[\w\-\.]+)/following', $routes );
	}

	/**
	 * Test schema.
	 *
	 * @covers ::get_item_schema
	 */
	public function test_get_item_schema() {
		$request  = new \WP_REST_Request( 'OPTIONS', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/0/following' );
		$response = rest_get_server()->dispatch( $request )->get_data();

		$this->assertArrayHasKey( 'schema', $response );
		$schema = $response['schema'];

		// Test specific property types.
		$this->assertEquals( array( 'array', 'object' ), $schema['properties']['@context']['type'] );
		$this->assertEquals( 'string', $schema['properties']['id']['type'] );
		$this->assertEquals( 'uri', $schema['properties']['id']['format'] );
		$this->assertEquals( array( 'OrderedCollectionPage' ), $schema['properties']['type']['enum'] );
		$this->assertEquals( 'array', $schema['properties']['orderedItems']['type'] );
		$this->assertEquals( 'string', $schema['properties']['orderedItems']['items']['type'] );
		$this->assertEquals( 'string', $schema['properties']['generator']['type'] );
		$this->assertEquals( 'uri', $schema['properties']['generator']['format'] );
	}

	/**
	 * Test get_items response.
	 *
	 * @covers ::get_items
	 */
	public function test_get_items() {
		$request  = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/0/following' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertStringContainsString( 'application/activity+json', $response->get_headers()['Content-Type'] );

		$data = $response->get_data();

		// Test required properties.
		$this->assertArrayHasKey( '@context', $data );
		$this->assertArrayHasKey( 'id', $data );
		$this->assertArrayHasKey( 'type', $data );
		$this->assertArrayHasKey( 'generator', $data );
		$this->assertArrayHasKey( 'actor', $data );
		$this->assertArrayHasKey( 'totalItems', $data );
		$this->assertArrayHasKey( 'orderedItems', $data );
		$this->assertArrayHasKey( 'partOf', $data );
		$this->assertArrayHasKey( 'first', $data );

		// Test property values.
		$this->assertEquals( 'OrderedCollectionPage', $data['type'] );
		$this->assertStringContainsString( 'wordpress.org', $data['generator'] );
		$this->assertEquals( $data['partOf'], $data['first'] );
		$this->assertIsArray( $data['orderedItems'] );
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

	/**
	 * Test get_item method.
	 *
	 * @doesNotPerformAssertions
	 */
	public function test_get_item() {
		// Controller does not implement get_item().
	}
}
