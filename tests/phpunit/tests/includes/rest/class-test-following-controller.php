<?php
/**
 * Following REST API endpoint test file.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Rest;

use Activitypub\Collection\Following;
use Activitypub\Collection\Remote_Actors;

/**
 * Tests for Following REST API endpoint.
 *
 * @group rest
 * @coversDefaultClass \Activitypub\Rest\Following_Controller
 */
class Test_Following_Controller extends \Activitypub\Tests\Test_REST_Controller_Testcase {

	/**
	 * Set up before class.
	 */
	public static function set_up_before_class() {
		self::factory()->post->create_many(
			25,
			array(
				'post_type'    => Remote_Actors::POST_TYPE,
				'post_content' => \wp_slash(
					\wp_json_encode(
						array(
							'id'                => 'https://example.org/actor/1',
							'type'              => 'Person',
							'preferredUsername' => 'user1',
							'name'              => 'User 1',
						)
					)
				),
				'meta_input'   => array(
					Following::FOLLOWING_META_KEY => '0',
				),
			)
		);
		self::factory()->post->create_many(
			3,
			array(
				'post_type'    => Remote_Actors::POST_TYPE,
				'post_content' => \wp_slash(
					\wp_json_encode(
						array(
							'id'                => 'https://example.org/actor/1',
							'type'              => 'Person',
							'preferredUsername' => 'user1',
							'name'              => 'User 1',
						)
					)
				),
				'meta_input'   => array(
					Following::PENDING_META_KEY => '1',
				),
			)
		);
	}

	/**
	 * Test route registration.
	 *
	 * @covers ::register_routes
	 */
	public function test_register_routes() {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/' . ACTIVITYPUB_REST_NAMESPACE . '/(?:users|actors)\/(?P<user_id>[-]?\d+)/following', $routes );
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
		$this->assertContains( 'array', (array) $schema['properties']['@context']['type'] );
		$this->assertContains( 'object', (array) $schema['properties']['@context']['type'] );
		$this->assertEquals( 'string', $schema['properties']['id']['type'] );
		$this->assertEquals( 'uri', $schema['properties']['id']['format'] );
		$this->assertEquals( 'string', $schema['properties']['generator']['type'] );
		$this->assertEquals( 'uri', $schema['properties']['generator']['format'] );
		$this->assertEquals( 'string', $schema['properties']['actor']['type'] );
		$this->assertEquals( 'uri', $schema['properties']['actor']['format'] );
		$this->assertEquals( 'integer', $schema['properties']['totalItems']['type'] );
		$this->assertEquals( 'string', $schema['properties']['partOf']['type'] );
		$this->assertEquals( 'uri', $schema['properties']['partOf']['format'] );
		$this->assertEquals( 'array', $schema['properties']['orderedItems']['type'] );
	}

	/**
	 * Test get_items response.
	 *
	 * @covers ::get_items
	 */
	public function test_get_items() {
		$actor_mode = \get_option( 'activitypub_actor_mode' );
		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_BLOG_MODE );

		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/0/following' );
		$request->set_param( 'page', 1 );
		$request->set_param( 'context', 'simple' );
		$response = rest_get_server()->dispatch( $request );

		\update_option( 'activitypub_actor_mode', $actor_mode );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertStringContainsString( 'application/activity+json', $response->get_headers()['Content-Type'] );

		$data = $response->get_data();

		// Test required properties.
		$this->assertArrayHasKey( '@context', $data );
		$this->assertArrayHasKey( 'id', $data );
		$this->assertArrayHasKey( 'type', $data );
		$this->assertArrayHasKey( 'generator', $data );
		$this->assertArrayHasKey( 'totalItems', $data );

		// Test property values.
		$this->assertEquals( 'OrderedCollectionPage', $data['type'] );
		$this->assertStringContainsString( 'wordpress.org', $data['generator'] );
		$this->assertNotEmpty( $data['orderedItems'] );
		$this->assertEquals( 25, $data['totalItems'] );
	}

	/**
	 * Test that a hidden social graph does not leak the following count.
	 *
	 * Regression: totalItems was emitted unconditionally, so a non-owner could read
	 * the following total even when the social graph was hidden.
	 *
	 * @covers ::get_items
	 */
	public function test_get_items_hides_total_when_social_graph_hidden() {
		$actor_mode = \get_option( 'activitypub_actor_mode' );
		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_BLOG_MODE );
		\update_option( 'activitypub_hide_social_graph', '1' );
		\wp_set_current_user( 0 );

		try {
			$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/0/following' );
			$request->set_param( 'page', 1 );
			$request->set_param( 'context', 'simple' );
			$response = rest_get_server()->dispatch( $request );

			$this->assertEquals( 200, $response->get_status() );
			$data = $response->get_data();
			$this->assertSame( 0, $data['totalItems'], 'A hidden social graph must not leak the following count.' );
			$this->assertEmpty( $data['orderedItems'] ?? array(), 'A hidden social graph must not expose the following list.' );
		} finally {
			\delete_option( 'activitypub_hide_social_graph' );
			\update_option( 'activitypub_actor_mode', $actor_mode );
		}
	}

	/**
	 * Test that the deprecated activitypub_rest_following filter cannot re-leak a hidden count.
	 *
	 * @covers ::get_items
	 */
	public function test_get_items_deprecated_filter_respects_hidden_social_graph() {
		$this->setExpectedDeprecated( 'activitypub_rest_following' );

		$actor_mode = \get_option( 'activitypub_actor_mode' );
		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_BLOG_MODE );
		\update_option( 'activitypub_hide_social_graph', '1' );
		\wp_set_current_user( 0 );

		$inject = static function () {
			return array( 'https://example.org/actor/leak' );
		};
		\add_filter( 'activitypub_rest_following', $inject );

		try {
			$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/0/following' );
			$request->set_param( 'page', 1 );
			$request->set_param( 'context', 'simple' );
			$response = rest_get_server()->dispatch( $request );

			$data = $response->get_data();
			$this->assertSame( 0, $data['totalItems'], 'The deprecated filter must not re-leak the count when the graph is hidden.' );
			$this->assertEmpty( $data['orderedItems'] ?? array(), 'The deprecated filter must not re-expose the list when the graph is hidden.' );
		} finally {
			\remove_filter( 'activitypub_rest_following', $inject );
			\delete_option( 'activitypub_hide_social_graph' );
			\update_option( 'activitypub_actor_mode', $actor_mode );
		}
	}

	/**
	 * Test get_items response with full context.
	 *
	 * @covers ::get_items
	 */
	public function test_get_items_full_context() {
		$actor_mode = \get_option( 'activitypub_actor_mode' );
		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_BLOG_MODE );

		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/0/following' );
		$request->set_param( 'page', 1 );
		$request->set_param( 'context', 'full' );
		$response = rest_get_server()->dispatch( $request );

		\update_option( 'activitypub_actor_mode', $actor_mode );

		$data = $response->get_data();
		$this->assertIsArray( $data['orderedItems'] );

		// In full context, orderedItems should contain full actor objects.
		foreach ( $data['orderedItems'] as $item ) {
			$this->assertIsArray( $item );
		}
	}

	/**
	 * Test get_items with pagination.
	 *
	 * @covers ::get_items
	 */
	public function test_get_items_pagination() {
		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_BLOG_MODE );

		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/0/following' );
		$request->set_param( 'page', 2 );
		$request->set_param( 'per_page', 10 );
		$response = rest_get_server()->dispatch( $request );

		$data = $response->get_data();

		// Test pagination properties.
		$this->assertArrayHasKey( 'first', $data );
		$this->assertArrayHasKey( 'last', $data );
		$this->assertStringContainsString( 'page=1', $data['first'] );
		$this->assertIsString( $data['last'] );

		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/0/following' );
		$request->set_param( 'page', 100 );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_post_invalid_page_number', $response, 400 );

		\delete_option( 'activitypub_actor_mode' );
	}

	/**
	 * Test get_items with invalid user.
	 *
	 * @covers ::get_items
	 */
	public function test_get_items_invalid_user() {
		$request  = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/999999/following' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	/**
	 * Test that the Following response matches its schema.
	 *
	 * @covers ::get_items
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
