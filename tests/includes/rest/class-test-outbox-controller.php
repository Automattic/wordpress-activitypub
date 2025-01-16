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
 * @coversDefaultClass \Activitypub\Rest\Outbox_Controller
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

		\remove_filter( 'activitypub_defer_signature_verification', '__return_true' );
	}

	/**
	 * Set up test environment.
	 */
	public function set_up() {
		parent::set_up();
		\add_filter( 'activitypub_defer_signature_verification', '__return_true' );
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
	 * Test getting items.
	 *
	 * @covers ::get_items
	 */
	public function test_get_items() {
		$request  = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/0/outbox' );
		$response = \rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
	}

	/**
	 * Test creating items.
	 *
	 * @covers ::create_item
	 */
	public function test_create_item() {
		\add_filter( 'activitypub_defer_signature_verification', '__return_true' );
		$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/1/outbox' );
		$request->set_header( 'Content-Type', 'application/ld+json; profile="https://www.w3.org/ns/activitystreams"' );
		$request->set_body(
			wp_json_encode(
				array(
					'@context' => array(
						'https://www.w3.org/ns/activitystreams',
						array( '@language' => 'en' ),
					),
					'type'     => 'Like',
					'actor'    => 'https://dustycloud.org/chris/',
					'name'     => "Chris liked 'Minimal ActivityPub update client'",
					'object'   => 'https://rhiaro.co.uk/2016/05/minimal-activitypub',
					'to'       => array(
						'https://rhiaro.co.uk/#amy',
						'https://dustycloud.org/followers',
						'https://rhiaro.co.uk/followers/',
					),
					'cc'       => 'https://e14n.com/evan',

				)
			)
		);
		$response = \rest_get_server()->dispatch( $request );

		$headers = $response->get_headers();
		$this->assertArrayHasKey( 'Location', $headers );

		$post_id = \url_to_postid( \html_entity_decode( $headers['Location'] ) );
		$this->assertInstanceOf( \WP_Post::class, get_post( $post_id ) );

		$this->assertEquals( 201, $response->get_status() );
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
	 * Test getting items with pagination.
	 *
	 * @covers ::get_items
	 */
	public function test_get_items_pagination() {
		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/0/outbox' );
		$request->set_param( 'page', 2 );
		$request->set_param( 'per_page', 3 );
		$response = \rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertArrayHasKey( 'prev', $data );
		$this->assertArrayHasKey( 'next', $data );
		$this->assertStringContainsString( 'page=1', $data['prev'] );
		$this->assertStringContainsString( 'page=3', $data['next'] );
	}

	/**
	 * Test getting items response structure.
	 *
	 * @covers ::get_items
	 */
	public function test_get_items_response_structure() {
		$request  = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/0/outbox' );
		$response = \rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertArrayHasKey( '@context', $data );
		$this->assertArrayHasKey( 'id', $data );
		$this->assertArrayHasKey( 'type', $data );
		$this->assertArrayHasKey( 'totalItems', $data );
		$this->assertArrayHasKey( 'orderedItems', $data );
		$this->assertEquals( 'OrderedCollectionPage', $data['type'] );
		$this->assertIsArray( $data['orderedItems'] );

		$headers = $response->get_headers();
		$this->assertEquals( 'application/activity+json; charset=' . \get_option( 'blog_charset' ), $headers['Content-Type'] );
	}

	/**
	 * Test getting items for specific user.
	 *
	 * @covers ::get_items
	 */
	public function test_get_items_specific_user() {
		$user_id = self::factory()->user->create( array( 'role' => 'author' ) );
		$post_id = self::factory()->post->create(
			array(
				'post_author'  => $user_id,
				'post_type'    => 'ap_outbox',
				'post_status'  => 'draft',
				'post_title'   => 'https://example.org/activity/1',
				'post_content' => wp_json_encode(
					array(
						'@context' => array( 'https://www.w3.org/ns/activitystreams' ),
						'id'       => 'https://example.org/activity/1',
						'type'     => 'Create',
						'actor'    => 'https://example.org/user/' . $user_id,
						'object'   => array(
							'id'      => 'https://example.org/note/1',
							'type'    => 'Note',
							'content' => 'Test content',
						),
					)
				),
				'meta_input'   => array(
					'_activitypub_activity_type'  => 'Create',
					'_activitypub_activity_actor' => 'user',
				),
			)
		);

		$request  = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/' . $user_id . '/outbox' );
		$response = \rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertSame( 1, (int) $data['totalItems'] );
		$this->assertStringContainsString( (string) $user_id, $data['actor'] );

		\wp_delete_post( $post_id, true );
		\wp_delete_user( $user_id );
	}

	/**
	 * Test outbox filters.
	 *
	 * @covers ::get_items
	 */
	public function test_get_items_filters() {
		$filter_called = false;
		$pre_called    = false;
		$post_called   = false;

		\add_filter(
			'activitypub_rest_outbox_array',
			function ( $response ) use ( &$filter_called ) {
				$filter_called = true;
				return $response;
			}
		);

		\add_action(
			'activitypub_rest_outbox_pre',
			function () use ( &$pre_called ) {
				$pre_called = true;
			}
		);

		\add_action(
			'activitypub_outbox_post',
			function () use ( &$post_called ) {
				$post_called = true;
			}
		);

		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/0/outbox' );
		\rest_get_server()->dispatch( $request );

		$this->assertTrue( $filter_called, 'activitypub_rest_outbox_array filter was not called.' );
		$this->assertTrue( $pre_called, 'activitypub_rest_outbox_pre action was not called.' );
		$this->assertTrue( $post_called, 'activitypub_outbox_post action was not called.' );

		\remove_all_filters( 'activitypub_rest_outbox_array' );
		\remove_all_actions( 'activitypub_rest_outbox_pre' );
		\remove_all_actions( 'activitypub_outbox_post' );
	}

	/**
	 * Test getting items with minimum per_page.
	 *
	 * @covers ::get_items
	 */
	public function test_get_items_minimum_per_page() {
		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/0/outbox' );
		$request->set_param( 'per_page', 1 );
		$response = \rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertCount( 1, $data['orderedItems'] );
	}

	/**
	 * Test getting items with maximum per_page.
	 *
	 * @covers ::get_items
	 */
	public function test_get_items_maximum_per_page() {
		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/0/outbox' );
		$request->set_param( 'per_page', 100 );
		$response = \rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
	}

	/**
	 * Test get_item method.
	 *
	 * @doesNotPerformAssertions
	 */
	public function test_get_item() {
		// Controller does not implement get_item().
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
