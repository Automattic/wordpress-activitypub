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
use Activitypub\Post_Types;

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
	 * Test ap_actor REST endpoint returns followers when social graph is hidden.
	 *
	 * The REST endpoint should still return followers regardless of the
	 * activitypub_hide_social_graph setting, as this setting only affects
	 * the ActivityPub protocol endpoint, not the WordPress REST API.
	 *
	 * @covers ::register_ap_actor_rest_query_params
	 * @covers ::filter_ap_actor_rest_query
	 */
	public function test_ap_actor_rest_endpoint_returns_followers_with_hidden_social_graph() {
		$user_id = self::factory()->user->create( array( 'role' => 'author' ) );

		// Hide social graph.
		\update_user_option( $user_id, 'activitypub_hide_social_graph', '1' );

		// Create test follower.
		$follower_id = self::factory()->post->create(
			array(
				'post_type'   => Remote_Actors::POST_TYPE,
				'post_title'  => 'Hidden Graph Follower',
				'post_status' => 'publish',
				'guid'        => 'https://example.com/users/hidden',
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

		// Clean up.
		\delete_user_option( $user_id, 'activitypub_hide_social_graph' );
	}
}
