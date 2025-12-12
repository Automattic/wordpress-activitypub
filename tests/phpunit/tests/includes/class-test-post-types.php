<?php
/**
 * Test file for Post Types.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

use Activitypub\Activitypub;
use Activitypub\Collection\Followers;
use Activitypub\Collection\Remote_Actors;

/**
 * Test class for Post Types.
 *
 * @coversDefaultClass \Activitypub\Post_Types
 */
class Test_Post_Types extends \WP_UnitTestCase {

	/**
	 * REST Server.
	 *
	 * @var \WP_REST_Server
	 */
	protected $server;

	/**
	 * Set up test environment.
	 */
	public function set_up(): void {
		parent::set_up();
		Activitypub::init();

		global $wp_rest_server;
		$wp_rest_server = new \Spy_REST_Server();
		$this->server   = $wp_rest_server;
		\do_action( 'rest_api_init', $wp_rest_server );
	}

	/**
	 * Tear down test environment.
	 */
	public function tear_down(): void {
		global $wp_rest_server;
		$wp_rest_server = null;

		parent::tear_down();
	}

	/**
	 * Test prevent_empty_post_meta method.
	 *
	 * @covers ::prevent_empty_post_meta
	 */
	public function test_prevent_empty_post_meta() {
		$post_id = self::factory()->post->create( array( 'post_author' => 1 ) );

		\update_post_meta( $post_id, 'activitypub_max_image_attachments', ACTIVITYPUB_MAX_IMAGE_ATTACHMENTS );
		$this->assertEmpty( \get_post_meta( $post_id, 'activitypub_max_image_attachments', true ) );
		\delete_post_meta( $post_id, 'activitypub_max_image_attachments' );

		\update_post_meta( $post_id, 'activitypub_max_image_attachments', ACTIVITYPUB_MAX_IMAGE_ATTACHMENTS + 3 );
		$this->assertEquals( ACTIVITYPUB_MAX_IMAGE_ATTACHMENTS + 3, \get_post_meta( $post_id, 'activitypub_max_image_attachments', true ) );
		\delete_post_meta( $post_id, 'activitypub_max_image_attachments' );
	}

	/**
	 * Test ap_actor REST endpoint returns followers when social graph is visible.
	 *
	 * @covers ::register_ap_actor_rest_query_params
	 * @covers ::filter_ap_actor_rest_query
	 */
	public function test_ap_actor_rest_endpoint_returns_followers_with_visible_social_graph() {
		$user_id = self::factory()->user->create( array( 'role' => 'author' ) );

		// Ensure social graph is visible (default).
		\delete_user_option( $user_id, 'activitypub_hide_social_graph' );

		// Create test follower.
		$follower_id = self::factory()->post->create(
			array(
				'post_type'   => Remote_Actors::POST_TYPE,
				'post_title'  => 'Test Follower',
				'post_status' => 'publish',
				'guid'        => 'https://example.com/users/test',
			)
		);
		\add_post_meta( $follower_id, Followers::FOLLOWER_META_KEY, $user_id );

		// Query the REST API.
		$request = new \WP_REST_Request( 'GET', '/wp/v2/ap_actor' );
		$request->set_param( 'activitypub_following', $user_id );

		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertCount( 1, $data );
		$this->assertEquals( $follower_id, $data[0]['id'] );
	}

	/**
	 * Test ap_actor REST endpoint respects social graph visibility setting.
	 *
	 * @covers ::register_ap_actor_rest_query_params
	 * @covers ::filter_ap_actor_rest_query
	 */
	public function test_ap_actor_rest_endpoint_respects_social_graph_visibility() {
		$user_id = self::factory()->user->create( array( 'role' => 'author' ) );

		// Enable blog user for this test.
		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_AND_BLOG_MODE );

		// Create test follower for user.
		$follower_id = self::factory()->post->create(
			array(
				'post_type'   => Remote_Actors::POST_TYPE,
				'post_title'  => 'User Follower',
				'post_status' => 'publish',
				'guid'        => 'https://example.com/users/follower',
			)
		);
		\add_post_meta( $follower_id, Followers::FOLLOWER_META_KEY, $user_id );

		// Create test follower for blog user (ID 0).
		$blog_follower_id = self::factory()->post->create(
			array(
				'post_type'   => Remote_Actors::POST_TYPE,
				'post_title'  => 'Blog Follower',
				'post_status' => 'publish',
				'guid'        => 'https://example.com/users/blog-follower',
			)
		);
		\add_post_meta( $blog_follower_id, Followers::FOLLOWER_META_KEY, 0 );

		// Verify followers are returned when social graph is visible.
		$request = new \WP_REST_Request( 'GET', '/wp/v2/ap_actor' );
		$request->set_param( 'activitypub_following', $user_id );
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
		$this->assertCount( 1, $response->get_data(), 'User followers should be returned when social graph is visible.' );

		$request = new \WP_REST_Request( 'GET', '/wp/v2/ap_actor' );
		$request->set_param( 'activitypub_following', 0 );
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
		$this->assertCount( 1, $response->get_data(), 'Blog followers should be returned when social graph is visible.' );

		// Hide social graph for user.
		\update_user_option( $user_id, 'activitypub_hide_social_graph', '1' );

		// Verify no followers are returned for user with hidden social graph.
		$request = new \WP_REST_Request( 'GET', '/wp/v2/ap_actor' );
		$request->set_param( 'activitypub_following', $user_id );
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
		$this->assertCount( 0, $response->get_data(), 'User followers should not be returned when social graph is hidden.' );

		// Hide social graph for blog.
		\update_option( 'activitypub_hide_social_graph', '1' );

		// Verify no followers are returned for blog with hidden social graph.
		$request = new \WP_REST_Request( 'GET', '/wp/v2/ap_actor' );
		$request->set_param( 'activitypub_following', 0 );
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
		$this->assertCount( 0, $response->get_data(), 'Blog followers should not be returned when social graph is hidden.' );

		// Clean up.
		\delete_user_option( $user_id, 'activitypub_hide_social_graph' );
		\delete_option( 'activitypub_hide_social_graph' );
		\delete_option( 'activitypub_actor_mode' );
	}
}
