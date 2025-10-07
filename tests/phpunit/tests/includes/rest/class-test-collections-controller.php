<?php
/**
 * Test Collections REST Endpoint.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Rest;

use Activitypub\Activity\Base_Object;

/**
 * Test Collections REST Endpoint.
 *
 * @group rest
 * @coversDefaultClass \Activitypub\Rest\Collections_Controller
 */
class Test_Collections_Controller extends \Activitypub\Tests\Test_REST_Controller_Testcase {

	/**
	 * Test user ID.
	 *
	 * @var int
	 */
	protected static $user_id;

	/**
	 * Test post ID.
	 *
	 * @var int
	 */
	protected static $post_id;

	/**
	 * Test tag IDs.
	 *
	 * @var array
	 */
	protected static $tag_ids;

	/**
	 * Create fake data before our tests run.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		self::$user_id = self::factory()->user->create( array( 'role' => 'author' ) );
		\get_user_by( 'id', self::$user_id )->add_cap( 'activitypub' );

		self::$post_id = self::factory()->post->create( array( 'post_author' => self::$user_id ) );
		self::$tag_ids = self::factory()->tag->create_many( 12, array( '' ) );
		self::factory()->term->add_post_terms( self::$post_id, self::$tag_ids, 'post_tag' );
	}

	/**
	 * Test registration of routes.
	 *
	 * @covers ::register_routes
	 */
	public function test_register_routes() {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/' . ACTIVITYPUB_REST_NAMESPACE . '/(?:users|actors)\/(?P<user_id>[-]?\d+)/collections/(?P<type>[\w\-\.]+)', $routes );
	}

	/**
	 * Test getting items with invalid user.
	 *
	 * @covers ::get_items
	 */
	public function test_get_items_invalid_user() {
		$request  = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/9999999/collections/tags' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	/**
	 * Test getting items with invalid collection type.
	 *
	 * @covers ::get_items
	 */
	public function test_get_items_invalid_type() {
		$request  = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/' . self::$user_id . '/collections/invalid' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	/**
	 * Test getting tags collection.
	 *
	 * @covers ::get_tags
	 */
	public function test_get_tags() {
		$request  = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/' . self::$user_id . '/collections/tags' );
		$response = rest_get_server()->dispatch( $request )->get_data();

		$this->assertIsArray( $response );
		$this->assertEquals( Base_Object::JSON_LD_CONTEXT, $response['@context'] );
		$this->assertEquals( 'Collection', $response['type'] );
		$this->assertIsArray( $response['items'] );
	}

	/**
	 * Test getting featured collection.
	 *
	 * @covers ::get_featured
	 */
	public function test_get_featured() {
		stick_post( self::$post_id );

		$request  = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/' . self::$user_id . '/collections/featured' );
		$response = rest_get_server()->dispatch( $request )->get_data();

		$this->assertIsArray( $response );
		$this->assertEquals( Base_Object::JSON_LD_CONTEXT, $response['@context'] );
		$this->assertEquals( 'OrderedCollection', $response['type'] );
		$this->assertIsArray( $response['orderedItems'] );
		$this->assertEquals( 1, $response['totalItems'] );
	}

	/**
	 * Test schema.
	 *
	 * @covers ::get_item_schema
	 */
	public function test_get_item_schema() {
		$request  = new \WP_REST_Request( 'OPTIONS', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/' . self::$user_id . '/collections/featured' );
		$response = rest_get_server()->dispatch( $request );
		$schema   = $response->get_data()['schema'];

		$this->assertIsArray( $schema );
		$this->assertEquals( 'object', $schema['type'] );
		$this->assertArrayHasKey( 'properties', $schema );
	}

	/**
	 * Test that the Followers response matches its schema.
	 *
	 * @covers ::get_items
	 * @covers ::get_item_schema
	 */
	public function test_response_matches_schema() {
		$request  = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/users/' . self::$user_id . '/collections/tags' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$schema   = ( new \Activitypub\Rest\Collections_Controller() )->get_item_schema();

		$valid = \rest_validate_value_from_schema( $data, $schema );
		$this->assertNotWPError( $valid, 'Response failed schema validation: ' . ( \is_wp_error( $valid ) ? $valid->get_error_message() : '' ) );
	}

	/**
	 * Test get_item_schema method.
	 *
	 * @doesNotPerformAssertions
	 */
	public function test_get_item() {
		// Controller does not implement get_item().
	}
}
