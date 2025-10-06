<?php
/**
 * Test Comments REST Endpoint.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Rest;

use Activitypub\Comment;

/**
 * Test Comments REST Endpoint.
 *
 * @group rest
 * @coversDefaultClass \Activitypub\Rest\Comments_Controller
 */
class Test_Comments_Controller extends \Activitypub\Tests\Test_REST_Controller_Testcase {

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
	protected static $comment_ids;

	/**
	 * Create fake data before our tests run.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		self::$user_id = self::factory()->user->create( array( 'role' => 'author' ) );
		\get_user_by( 'id', self::$user_id )->add_cap( 'activitypub' );

		self::$post_id     = self::factory()->post->create( array( 'post_author' => self::$user_id ) );
		self::$comment_ids = self::factory()->comment->create_post_comments( self::$post_id, 2, array( 'comment_approved' => '1' ) );
		add_comment_meta( self::$comment_ids[0], 'protocol', 'activitypub', true );
	}

	/**
	 * Set up the test case.
	 */
	public function set_up() {
		parent::set_up();

		// Mock Webfinger::get_remote_follow_endpoint.
		add_filter( 'pre_http_request', array( $this, 'mock_remote_follow_endpoint' ) );
	}

	/**
	 * Tear down.
	 */
	public function tear_down() {
		remove_filter( 'pre_http_request', array( $this, 'mock_remote_follow_endpoint' ) );

		parent::tear_down();
	}

	/**
	 * Test registration of routes.
	 *
	 * @covers ::register_routes
	 */
	public function test_register_routes() {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/' . ACTIVITYPUB_REST_NAMESPACE . '/comments/(?P<comment_id>[-]?\d+)/remote-reply', $routes );
	}

	/**
	 * Test getting a single comment reply response.
	 *
	 * @covers ::get_item
	 */
	public function test_get_item() {
		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/comments/' . self::$comment_ids[0] . '/remote-reply' );
		$request->set_param( 'resource', 'https://example.com/user' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();

		$this->assertIsArray( $data );
		$this->assertSame( 'https://example.com/follow?uri=' . Comment::generate_id( self::$comment_ids[0] ), $data['url'] );
	}

	/**
	 * Test getting reply response for local comment
	 *
	 * @covers ::get_item
	 */
	public function test_get_item_local_comment() {
		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/comments/' . self::$comment_ids[1] . '/remote-reply' );
		$request->set_param( 'resource', 'https://example.com/user' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_invalid_param', $response );
	}

	/**
	 * Test schema.
	 *
	 * @covers ::get_item_schema
	 */
	public function test_get_item_schema() {
		$request  = new \WP_REST_Request( 'OPTIONS', '/' . ACTIVITYPUB_REST_NAMESPACE . '/comments/' . self::$comment_ids[0] . '/remote-reply' );
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
		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/comments/' . self::$comment_ids[0] . '/remote-reply' );
		$request->set_param( 'resource', 'https://example.com/user' );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$schema   = ( new \Activitypub\Rest\Comments_Controller() )->get_item_schema();

		$valid = \rest_validate_value_from_schema( $data, $schema );
		$this->assertNotWPError( $valid, 'Response failed schema validation: ' . ( \is_wp_error( $valid ) ? $valid->get_error_message() : '' ) );
	}

	/**
	 * Mock Webfinger::get_remote_follow_endpoint.
	 *
	 * @return array
	 */
	public function mock_remote_follow_endpoint() {
		return array(
			'response' => array( 'code' => 200 ),
			'body'     => wp_json_encode(
				array(
					'links' => array(
						array(
							'rel'      => 'http://ostatus.org/schema/1.0/subscribe',
							'template' => 'https://example.com/follow?uri={uri}',
						),
					),
				)
			),
		);
	}
}
