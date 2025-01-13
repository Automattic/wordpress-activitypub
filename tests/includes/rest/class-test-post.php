<?php
/**
 * Test Post REST Endpoints.
 *
 * @package ActivityPub
 */

namespace Activitypub\Tests\Rest;

use Activitypub\Rest\Post;
use WP_REST_Request;
use WP_REST_Server;
use WP_UnitTestCase;
use WP_REST_Response;

/**
 * Test Post REST Endpoints.
 *
 * @coversDefaultClass \Activitypub\Rest\Post
 */
class Test_Post extends WP_UnitTestCase {
	/**
	 * REST Server.
	 *
	 * @var WP_REST_Server
	 */
	protected $server;

	/**
	 * Set up.
	 */
	public function set_up() {
		parent::set_up();

		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		$this->server   = $wp_rest_server;

		do_action( 'rest_api_init' );
	}

	/**
	 * Test initialization of hooks.
	 *
	 * @covers ::init
	 */
	public function test_init() {
		$routes = $this->server->get_routes();
		$this->assertArrayHasKey( '/' . ACTIVITYPUB_REST_NAMESPACE . '/posts/(?P<id>\d+)/reactions', $routes );
	}

	/**
	 * Test getting reactions for a non-existent post.
	 *
	 * @covers ::get_reactions
	 */
	public function test_get_reactions_non_existent_post() {
		$request  = new WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/posts/999999/reactions' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 404, $response->get_status() );
		$this->assertEquals( 'post_not_found', $response->get_data()['code'] );
	}

	/**
	 * Test getting reactions for a post with no reactions.
	 *
	 * @covers ::get_reactions
	 */
	public function test_get_reactions_no_reactions() {
		$post_id  = self::factory()->post->create();
		$request  = new WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/posts/' . $post_id . '/reactions' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEmpty( $response->get_data() );
	}

	/**
	 * Test getting reactions for a post with reactions.
	 *
	 * @covers ::get_reactions
	 */
	public function test_get_reactions_with_reactions() {
		$post_id = self::factory()->post->create();

		// Create a "like" reaction.
		$comment_data = array(
			'comment_post_ID'      => $post_id,
			'comment_author'       => 'Test User',
			'comment_author_url'   => 'https://example.com/user',
			'comment_author_email' => '',
			'comment_content'      => '',
			'comment_type'         => 'like',
			'comment_parent'       => 0,
			'user_id'              => 0,
			'comment_approved'     => 1,
		);
		$comment_id   = wp_insert_comment( $comment_data );
		update_comment_meta( $comment_id, 'avatar_url', 'https://example.com/avatar.jpg' );

		$request  = new WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/posts/' . $post_id . '/reactions' );
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertArrayHasKey( 'likes', $data );
		$this->assertEquals( '1 like', $data['likes']['label'] );
		$this->assertCount( 1, $data['likes']['items'] );

		$item = $data['likes']['items'][0];
		$this->assertEquals( 'Test User', $item['name'] );
		$this->assertEquals( 'https://example.com/user', $item['url'] );
		$this->assertEquals( 'https://example.com/avatar.jpg', $item['avatar'] );
	}

	/**
	 * Test getting reactions for a post with multiple reaction types.
	 *
	 * @covers ::get_reactions
	 */
	public function test_get_reactions_multiple_types() {
		$post_id = self::factory()->post->create();

		// Create reactions of different types.
		$reaction_types = array(
			array(
				'type'   => 'like',
				'author' => 'Like User',
				'url'    => 'https://example.com/like-user',
			),
			array(
				'type'   => 'repost',
				'author' => 'Announce User',
				'url'    => 'https://example.com/announce-user',
			),
		);

		foreach ( $reaction_types as $reaction ) {
			$comment_data = array(
				'comment_post_ID'      => $post_id,
				'comment_author'       => $reaction['author'],
				'comment_author_url'   => $reaction['url'],
				'comment_author_email' => '',
				'comment_content'      => '',
				'comment_type'         => $reaction['type'],
				'comment_parent'       => 0,
				'user_id'              => 0,
				'comment_approved'     => 1,
			);
			wp_insert_comment( $comment_data );
		}

		$request  = new WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/posts/' . $post_id . '/reactions' );
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertArrayHasKey( 'likes', $data );
		$this->assertArrayHasKey( 'reposts', $data );
		$this->assertEquals( '1 like', $data['likes']['label'] );
		$this->assertEquals( '1 repost', $data['reposts']['label'] );
	}

	/**
	 * Test getting reactions respects comment approval status.
	 *
	 * @covers ::get_reactions
	 */
	public function test_get_reactions_respects_approval() {
		$post_id = self::factory()->post->create();

		// Create an unapproved reaction.
		$comment_data = array(
			'comment_post_ID'      => $post_id,
			'comment_author'       => 'Test User',
			'comment_author_url'   => 'https://example.com/user',
			'comment_author_email' => '',
			'comment_content'      => '',
			'comment_type'         => 'like',
			'comment_parent'       => 0,
			'user_id'              => 0,
			'comment_approved'     => 0,
		);
		wp_insert_comment( $comment_data );

		$request  = new WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/posts/' . $post_id . '/reactions' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEmpty( $response->get_data() );
	}
}
