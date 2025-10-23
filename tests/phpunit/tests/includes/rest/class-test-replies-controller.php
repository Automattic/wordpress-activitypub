<?php
/**
 * Test Replies REST Endpoint.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Rest;

/**
 * Test Replies REST Endpoint.
 *
 * @group rest
 * @coversDefaultClass \Activitypub\Rest\Replies_Controller
 */
class Test_Replies_Controller extends \Activitypub\Tests\Test_REST_Controller_Testcase {

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
	 * Test comment IDs.
	 *
	 * @var array
	 */
	protected static $comment_ids;

	/**
	 * Create fake data before our tests run.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		self::$user_id = self::factory()->user->create( array( 'role' => 'author' ) );
		\get_user_by( 'id', self::$user_id )->add_cap( 'activitypub' );

		self::$post_id = self::factory()->post->create(
			array(
				'post_author' => self::$user_id,
				'post_status' => 'publish',
			)
		);

		// Create a hierarchy of comments.
		$comment_1 = self::factory()->comment->create(
			array(
				'comment_post_ID' => self::$post_id,
				'user_id'         => self::$user_id,
			)
		);

		$comment_2 = self::factory()->comment->create(
			array(
				'comment_post_ID' => self::$post_id,
				'user_id'         => self::$user_id,
				'comment_parent'  => $comment_1,
			)
		);

		self::$comment_ids = array( $comment_1, $comment_2 );
	}

	/**
	 * Test registration of routes.
	 *
	 * @covers ::register_routes
	 */
	public function test_register_routes() {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/' . ACTIVITYPUB_REST_NAMESPACE . '/(?P<object_type>[\w\-\.]+)s/(?P<id>[\w\-\.]+)/(?P<type>[\w\-\.]+)', $routes );
	}

	/**
	 * Test getting replies for a post.
	 *
	 * @covers ::get_items
	 */
	public function test_get_post_replies() {
		$request  = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/posts/' . self::$post_id . '/replies' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();

		$this->assertIsArray( $data );
		$this->assertArrayHasKey( '@context', $data );
		$this->assertArrayHasKey( 'type', $data );
		$this->assertContains( $data['type'], array( 'Collection', 'OrderedCollection', 'CollectionPage', 'OrderedCollectionPage' ) );
		$this->assertArrayHasKey( 'first', $data );
	}

	/**
	 * Test getting replies for a comment.
	 *
	 * @covers ::get_items
	 */
	public function test_get_comment_replies() {
		$request  = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/comments/' . self::$comment_ids[0] . '/replies' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();

		$this->assertIsArray( $data );
		$this->assertArrayHasKey( '@context', $data );
		$this->assertArrayHasKey( 'type', $data );
		$this->assertContains( $data['type'], array( 'Collection', 'OrderedCollection', 'CollectionPage', 'OrderedCollectionPage' ) );
		$this->assertArrayHasKey( 'first', $data );
	}

	/**
	 * Test getting replies with pagination.
	 *
	 * @covers ::get_items
	 */
	public function test_get_replies_pagination() {
		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/posts/' . self::$post_id . '/replies' );
		$request->set_param( 'page', 2 );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();

		$this->assertIsArray( $data );
		$this->assertArrayHasKey( '@context', $data );
		$this->assertArrayHasKey( 'type', $data );
		$this->assertContains( $data['type'], array( 'Collection', 'CollectionPage', 'OrderedCollectionPage' ) );
	}

	/**
	 * Test getting replies for non-existent post.
	 *
	 * @covers ::get_items
	 */
	public function test_get_replies_non_existent_post() {
		$request  = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/posts/99999/replies' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'activitypub_replies_collection_does_not_exist', $response, 404 );
	}

	/**
	 * Test getting replies for non-existent comment.
	 *
	 * @covers ::get_items
	 */
	public function test_get_replies_non_existent_comment() {
		$request  = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/comments/99999/replies' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'activitypub_replies_collection_does_not_exist', $response, 404 );
	}

	/**
	 * Test getting likes for a post.
	 *
	 * @covers ::get_items
	 */
	public function test_get_post_likes() {
		$request  = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/posts/' . self::$post_id . '/likes' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();

		$this->assertIsArray( $data );
		$this->assertArrayHasKey( '@context', $data );
		$this->assertArrayHasKey( 'type', $data );
		$this->assertContains( $data['type'], array( 'Collection', 'OrderedCollection', 'CollectionPage', 'OrderedCollectionPage' ) );
		$this->assertArrayHasKey( 'totalItems', $data );
	}

	/**
	 * Test getting shares for a post.
	 *
	 * @covers ::get_items
	 */
	public function test_get_post_shares() {
		$request  = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/posts/' . self::$post_id . '/shares' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();

		$this->assertIsArray( $data );
		$this->assertArrayHasKey( '@context', $data );
		$this->assertArrayHasKey( 'type', $data );
		$this->assertContains( $data['type'], array( 'Collection', 'OrderedCollection', 'CollectionPage', 'OrderedCollectionPage' ) );
		$this->assertArrayHasKey( 'totalItems', $data );
	}

	/**
	 * Test getting shares for a comment.
	 *
	 * @covers ::get_items
	 */
	public function test_get_comment_shares() {
		$request  = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/comments/' . self::$comment_ids[0] . '/shares' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();

		$this->assertIsArray( $data );
		$this->assertArrayHasKey( '@context', $data );
		$this->assertArrayHasKey( 'type', $data );
		$this->assertContains( $data['type'], array( 'Collection', 'OrderedCollection', 'CollectionPage', 'OrderedCollectionPage' ) );
		$this->assertArrayHasKey( 'totalItems', $data );
		$this->assertEquals( 0, $data['totalItems'] );
	}

	/**
	 * Test getting likes for a comment.
	 *
	 * @covers ::get_items
	 */
	public function test_get_comment_likes() {
		$request  = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/comments/' . self::$comment_ids[0] . '/likes' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();

		$this->assertIsArray( $data );
		$this->assertArrayHasKey( '@context', $data );
		$this->assertArrayHasKey( 'type', $data );
		$this->assertContains( $data['type'], array( 'Collection', 'OrderedCollection', 'CollectionPage', 'OrderedCollectionPage' ) );
		$this->assertArrayHasKey( 'totalItems', $data );
		$this->assertEquals( 0, $data['totalItems'] );
	}

	/**
	 * Test getting shares for a non-existent post.
	 *
	 * @covers ::get_items
	 */
	public function test_get_post_shares_non_existent() {
		$request  = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/posts/99999/shares' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'activitypub_replies_collection_does_not_exist', $response, 404 );
	}

	/**
	 * Test the get item schema.
	 *
	 * @covers ::get_item_schema
	 */
	public function test_get_item_schema() {
		$request    = new \WP_REST_Request( 'OPTIONS', '/' . ACTIVITYPUB_REST_NAMESPACE . '/posts/' . self::$post_id . '/replies' );
		$response   = rest_get_server()->dispatch( $request );
		$data       = $response->get_data();
		$properties = $data['schema']['properties'];

		$this->assertArrayHasKey( '@context', $properties );
		$this->assertArrayHasKey( 'id', $properties );
		$this->assertArrayHasKey( 'type', $properties );
		$this->assertArrayHasKey( 'first', $properties );
		$this->assertArrayHasKey( 'last', $properties );
		$this->assertArrayHasKey( 'items', $properties );
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
