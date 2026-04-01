<?php
/**
 * Test Liked REST Endpoint.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Rest;

use Activitypub\Collection\Outbox;

/**
 * Test Liked REST Endpoint.
 *
 * @group rest
 * @group liked
 * @coversDefaultClass \Activitypub\Rest\Liked_Controller
 */
class Test_Liked_Controller extends \Activitypub\Tests\Test_REST_Controller_Testcase {

	/**
	 * Test user ID.
	 *
	 * @var int
	 */
	protected static $user_id;

	/**
	 * Create fake data before our tests run.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		self::$user_id = self::factory()->user->create( array( 'role' => 'author' ) );
		\get_user_by( 'id', self::$user_id )->add_cap( 'activitypub' );
	}

	/**
	 * Test registration of routes.
	 *
	 * @covers ::register_routes
	 */
	public function test_register_routes() {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/' . ACTIVITYPUB_REST_NAMESPACE . '/(?:users|actors)\/(?P<user_id>[-]?\d+)/liked', $routes );
	}

	/**
	 * Test getting an empty liked collection.
	 *
	 * @covers ::get_items
	 */
	public function test_get_empty_liked_collection() {
		$request  = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/' . self::$user_id . '/liked' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();

		$this->assertArrayHasKey( '@context', $data );
		$this->assertEquals( 'OrderedCollection', $data['type'] );
		$this->assertEquals( 0, $data['totalItems'] );
	}

	/**
	 * Test liked collection with items.
	 *
	 * @covers ::get_items
	 */
	public function test_get_liked_collection_with_items() {
		$this->create_like_outbox_item( self::$user_id, 'https://example.com/post/1' );
		$this->create_like_outbox_item( self::$user_id, 'https://example.com/post/2' );

		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/' . self::$user_id . '/liked' );
		$request->set_param( 'page', 1 );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();

		$this->assertEquals( 'OrderedCollectionPage', $data['type'] );
		$this->assertEquals( 2, $data['totalItems'] );
		$this->assertContains( 'https://example.com/post/1', $data['orderedItems'] );
		$this->assertContains( 'https://example.com/post/2', $data['orderedItems'] );
	}

	/**
	 * Test that undone likes are excluded.
	 *
	 * @covers ::get_items
	 */
	public function test_undone_likes_excluded() {
		$this->create_like_outbox_item( self::$user_id, 'https://example.com/post/3' );
		$this->create_like_outbox_item( self::$user_id, 'https://example.com/post/4' );

		// Undo the like for post/3.
		$this->create_undo_outbox_item( self::$user_id, 'https://example.com/post/3' );

		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/' . self::$user_id . '/liked' );
		$request->set_param( 'page', 1 );
		$response = rest_get_server()->dispatch( $request );

		$data = $response->get_data();

		$this->assertNotContains( 'https://example.com/post/3', $data['orderedItems'] );
		$this->assertContains( 'https://example.com/post/4', $data['orderedItems'] );
	}

	/**
	 * Test that re-likes appear after undo.
	 *
	 * @covers ::get_items
	 */
	public function test_relike_after_undo() {
		$this->create_like_outbox_item( self::$user_id, 'https://example.com/post/5' );
		$this->create_undo_outbox_item( self::$user_id, 'https://example.com/post/5' );

		// Re-like: sleep 1 second to ensure different post_date.
		sleep( 1 );
		$this->create_like_outbox_item( self::$user_id, 'https://example.com/post/5' );

		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/' . self::$user_id . '/liked' );
		$request->set_param( 'page', 1 );
		$response = rest_get_server()->dispatch( $request );

		$data = $response->get_data();

		$this->assertContains( 'https://example.com/post/5', $data['orderedItems'] );
	}

	/**
	 * Test the Collection vs CollectionPage distinction.
	 *
	 * @covers ::get_items
	 */
	public function test_collection_vs_page() {
		$this->create_like_outbox_item( self::$user_id, 'https://example.com/post/6' );

		// Without page param: returns Collection with first/last links but no items.
		$request  = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/' . self::$user_id . '/liked' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 'OrderedCollection', $data['type'] );
		$this->assertArrayHasKey( 'first', $data );
		$this->assertArrayNotHasKey( 'orderedItems', $data );
	}

	/**
	 * Test the liked collection for an invalid user.
	 *
	 * @covers ::get_items
	 */
	public function test_invalid_user() {
		$request  = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/99999/liked' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertTrue( $response->get_status() >= 400 );
	}

	/**
	 * Test the schema.
	 *
	 * @covers ::get_item_schema
	 */
	public function test_get_item_schema() {
		$request    = new \WP_REST_Request( 'OPTIONS', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/' . self::$user_id . '/liked' );
		$response   = rest_get_server()->dispatch( $request );
		$data       = $response->get_data();
		$properties = $data['schema']['properties'];

		$this->assertArrayHasKey( '@context', $properties );
		$this->assertArrayHasKey( 'id', $properties );
		$this->assertArrayHasKey( 'type', $properties );
		$this->assertArrayHasKey( 'totalItems', $properties );
		$this->assertArrayHasKey( 'orderedItems', $properties );
		$this->assertArrayHasKey( 'actor', $properties );
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
	 * Create a Like outbox item for testing.
	 *
	 * @param int    $user_id   The user ID.
	 * @param string $object_id The liked object URL.
	 * @return int The post ID.
	 */
	private function create_like_outbox_item( $user_id, $object_id ) {
		$activity = array(
			'type'   => 'Like',
			'actor'  => 'https://example.com/user/' . $user_id,
			'object' => $object_id,
		);

		return \wp_insert_post(
			array(
				'post_type'    => Outbox::POST_TYPE,
				'post_status'  => 'publish',
				'post_author'  => $user_id,
				'post_title'   => '[Like] ' . $object_id,
				'post_content' => \wp_json_encode( $activity ),
				'meta_input'   => array(
					'_activitypub_object_id'      => $object_id,
					'_activitypub_activity_type'  => 'Like',
					'_activitypub_activity_actor' => 'user',
				),
			)
		);
	}

	/**
	 * Create an Undo outbox item for testing.
	 *
	 * @param int    $user_id   The user ID.
	 * @param string $object_id The object URL to undo.
	 * @return int The post ID.
	 */
	private function create_undo_outbox_item( $user_id, $object_id ) {
		$activity = array(
			'type'   => 'Undo',
			'actor'  => 'https://example.com/user/' . $user_id,
			'object' => array(
				'type'   => 'Like',
				'object' => $object_id,
			),
		);

		return \wp_insert_post(
			array(
				'post_type'    => Outbox::POST_TYPE,
				'post_status'  => 'publish',
				'post_author'  => $user_id,
				'post_title'   => '[Undo] ' . $object_id,
				'post_content' => \wp_json_encode( $activity ),
				'meta_input'   => array(
					'_activitypub_object_id'      => $object_id,
					'_activitypub_activity_type'  => 'Undo',
					'_activitypub_activity_actor' => 'user',
				),
			)
		);
	}
}
