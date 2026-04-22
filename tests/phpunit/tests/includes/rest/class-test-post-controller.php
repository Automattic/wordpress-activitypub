<?php
/**
 * Test Post REST Endpoints.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Rest;

use Activitypub\Rest\Post;
use WP_REST_Request;
use WP_REST_Server;
use WP_UnitTestCase;

/**
 * Test Post REST Endpoints.
 *
 * @group rest
 * @coversDefaultClass \Activitypub\Rest\Post_Controller
 */
class Test_Post_Controller extends WP_UnitTestCase {
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
	 * @covers ::register_routes
	 */
	public function test_init() {
		$routes = $this->server->get_routes();
		$this->assertArrayHasKey( '/' . ACTIVITYPUB_REST_NAMESPACE . '/posts/(?P<id>[\d]+)/reactions', $routes );
	}

	/**
	 * Test getting reactions for a non-existent post.
	 *
	 * @covers ::get_reactions
	 */
	public function test_get_reactions_non_existent_post() {
		$request  = new WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/posts/999999/reactions' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 400, $response->get_status() );
		$this->assertEquals( 'rest_invalid_param', $response->get_data()['code'] );
	}

	/**
	 * Non-public posts (private, draft, trashed, password-protected) must not
	 * leak reaction metadata via the public reactions route.
	 *
	 * @covers ::get_reactions
	 * @dataProvider data_non_public_post_states
	 *
	 * @param array $overrides wp_insert_post overrides describing the non-public state.
	 */
	public function test_get_reactions_non_public_post( $overrides ) {
		$post_id = self::factory()->post->create(
			array_merge( array( 'post_status' => 'publish' ), $overrides )
		);

		wp_insert_comment(
			array(
				'comment_post_ID'      => $post_id,
				'comment_author'       => 'Remote User',
				'comment_author_url'   => 'https://mastodon.social/users/remoteuser',
				'comment_author_email' => '',
				'comment_content'      => '',
				'comment_type'         => 'like',
				'comment_parent'       => 0,
				'user_id'              => 0,
				'comment_approved'     => 1,
			)
		);

		$request  = new WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/posts/' . $post_id . '/reactions' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 400, $response->get_status() );
		$this->assertEquals( 'rest_invalid_param', $response->get_data()['code'] );
	}

	/**
	 * A previously-federated post that has since been made non-public must not
	 * expose reactions. Regression test for the `is_post_disabled()` escape hatch
	 * that kept the gate open for posts in a `federated` lifecycle state.
	 *
	 * @covers ::get_reactions
	 */
	public function test_get_reactions_previously_federated_post_made_private() {
		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$post    = \get_post( $post_id );

		/* Simulate federation having already happened. */
		\Activitypub\set_wp_object_state( $post, ACTIVITYPUB_OBJECT_STATE_FEDERATED );

		/* Post is later made private. */
		\wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'private',
			)
		);

		$request  = new WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/posts/' . $post_id . '/reactions' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 400, $response->get_status() );
		$this->assertEquals( 'rest_invalid_param', $response->get_data()['code'] );
	}

	/**
	 * Data provider covering the post states that should not expose reactions.
	 *
	 * @return array[] Test cases.
	 */
	public function data_non_public_post_states() {
		return array(
			'private post'       => array( array( 'post_status' => 'private' ) ),
			'draft post'         => array( array( 'post_status' => 'draft' ) ),
			'pending post'       => array( array( 'post_status' => 'pending' ) ),
			'trashed post'       => array( array( 'post_status' => 'trash' ) ),
			'password protected' => array( array( 'post_password' => 'secret' ) ),
			'local visibility'   => array(
				array(
					'meta_input' => array( 'activitypub_content_visibility' => ACTIVITYPUB_CONTENT_VISIBILITY_LOCAL ),
				),
			),
			'private visibility' => array(
				array(
					'meta_input' => array( 'activitypub_content_visibility' => ACTIVITYPUB_CONTENT_VISIBILITY_PRIVATE ),
				),
			),
		);
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

	/**
	 * Reactions response sanitizes author name and URL defensively.
	 *
	 * Remote actor metadata is trusted only so far. Stored author names must
	 * not introduce markup into the JSON response, and stored URLs must not
	 * carry non-HTTP(S) schemes even if a storage-time bug let them through.
	 *
	 * @covers ::get_reactions
	 */
	public function test_get_reactions_sanitizes_remote_actor_metadata() {
		$post_id = self::factory()->post->create();

		wp_insert_comment(
			array(
				'comment_post_ID'      => $post_id,
				'comment_author'       => '<img src=x onerror=alert(1)>&amp;friends',
				'comment_author_url'   => 'javascript:alert(1)',
				'comment_author_email' => '',
				'comment_content'      => '',
				'comment_type'         => 'like',
				'comment_parent'       => 0,
				'user_id'              => 0,
				'comment_approved'     => 1,
			)
		);

		$request  = new WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/posts/' . $post_id . '/reactions' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertArrayHasKey( 'likes', $data );
		$this->assertCount( 1, $data['likes']['items'] );

		$item = $data['likes']['items'][0];

		/* Author name must have no HTML tags and should have entities decoded. */
		$this->assertStringNotContainsString( '<', $item['name'], 'Tags must be stripped from author name' );
		$this->assertStringNotContainsString( '>', $item['name'], 'Tags must be stripped from author name' );
		$this->assertStringContainsString( '&friends', $item['name'], 'Entity-encoded ampersand should be decoded' );

		/* URL must reject non-HTTP(S) schemes — esc_url() returns an empty string. */
		$this->assertSame( '', $item['url'], 'javascript: scheme must be stripped' );
	}

	/**
	 * Reactions URL is locked to http/https — other schemes esc_url() allows
	 * by default (mailto:, tel:, etc.) must be rejected.
	 *
	 * @covers ::get_reactions
	 */
	public function test_get_reactions_rejects_non_http_schemes() {
		$post_id = self::factory()->post->create();

		wp_insert_comment(
			array(
				'comment_post_ID'      => $post_id,
				'comment_author'       => 'Mailer Daemon',
				'comment_author_url'   => 'mailto:evil@example.com',
				'comment_author_email' => '',
				'comment_content'      => '',
				'comment_type'         => 'like',
				'comment_parent'       => 0,
				'user_id'              => 0,
				'comment_approved'     => 1,
			)
		);

		$request  = new WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/posts/' . $post_id . '/reactions' );
		$response = $this->server->dispatch( $request );
		$item     = $response->get_data()['likes']['items'][0];

		$this->assertSame( '', $item['url'], 'mailto: scheme must be stripped' );
	}

	/**
	 * Test remote-intent route is registered.
	 *
	 * @covers ::register_routes
	 */
	public function test_remote_intent_route_registered() {
		$routes = $this->server->get_routes();
		$this->assertArrayHasKey( '/' . ACTIVITYPUB_REST_NAMESPACE . '/posts/(?P<id>[\d]+)/remote-intent', $routes );
	}

	/**
	 * Test remote-intent rejects a non-existent post at arg validation.
	 *
	 * @covers ::get_remote_intent_template
	 */
	public function test_remote_intent_non_existent_post() {
		$request = new WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/posts/999999/remote-intent' );
		$request->set_param( 'resource', 'user@example.com' );
		$request->set_param( 'intent', 'like' );

		$response = $this->server->dispatch( $request );

		$this->assertEquals( 400, $response->get_status() );
		$this->assertEquals( 'rest_invalid_param', $response->get_data()['code'] );
	}

	/**
	 * Test remote-intent rejects a draft post at arg validation.
	 *
	 * @covers ::get_remote_intent_template
	 */
	public function test_remote_intent_draft_post() {
		$post_id = self::factory()->post->create( array( 'post_status' => 'draft' ) );
		$request = new WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/posts/' . $post_id . '/remote-intent' );
		$request->set_param( 'resource', 'user@example.com' );
		$request->set_param( 'intent', 'like' );

		$response = $this->server->dispatch( $request );

		$this->assertEquals( 400, $response->get_status() );
		$this->assertEquals( 'rest_invalid_param', $response->get_data()['code'] );
	}

	/**
	 * Test remote-intent rejects invalid intent values via enum validation.
	 *
	 * @covers ::register_routes
	 */
	public function test_remote_intent_invalid_intent() {
		$routes    = $this->server->get_routes();
		$route_key = '/' . ACTIVITYPUB_REST_NAMESPACE . '/posts/(?P<id>[\d]+)/remote-intent';

		$this->assertArrayHasKey( $route_key, $routes );

		// Verify the intent parameter has enum validation.
		$route_data  = $routes[ $route_key ];
		$handler     = $route_data[0];
		$intent_args = $handler['args']['intent'];

		$this->assertArrayHasKey( 'enum', $intent_args );
		$this->assertContains( 'like', $intent_args['enum'] );
		$this->assertContains( 'announce', $intent_args['enum'] );
		$this->assertContains( 'create', $intent_args['enum'] );
		$this->assertNotContains( 'invalid_intent', $intent_args['enum'] );
	}

	/**
	 * Test remote-intent returns url and template for a valid request.
	 *
	 * @covers ::get_remote_intent_template
	 */
	public function test_remote_intent_returns_url_and_template() {
		$post_id = self::factory()->post->create();

		// Mock the WebFinger response with an OStatus subscribe template.
		$filter = function () {
			return array(
				'response' => array( 'code' => 200 ),
				'body'     => \wp_json_encode(
					array(
						'subject' => 'acct:user@example.com',
						'links'   => array(
							array(
								'rel'      => 'http://ostatus.org/schema/1.0/subscribe',
								'template' => 'https://example.com/authorize_interaction?uri={uri}',
							),
						),
					)
				),
			);
		};
		\add_filter( 'pre_http_request', $filter );

		$request = new WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/posts/' . $post_id . '/remote-intent' );
		$request->set_param( 'resource', 'user@example.com' );
		$request->set_param( 'intent', 'like' );

		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertArrayHasKey( 'url', $data );
		$this->assertArrayHasKey( 'template', $data );
		$this->assertStringContainsString( 'authorize_interaction', $data['url'] );

		\remove_filter( 'pre_http_request', $filter );
	}

	/**
	 * Test getting reactions respects comment approval status.
	 *
	 * @covers ::get_reactions
	 */
	public function test_get_reactions_skips_comment_reactions() {
		$post_id    = self::factory()->post->create();
		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID' => $post_id,
				'comment_type'    => 'comment',
				'comment_content' => 'Test Comment',
			)
		);
		self::factory()->comment->create(
			array(
				'comment_post_ID'      => $post_id,
				'comment_author'       => 'Test User',
				'comment_author_url'   => 'https://example.com/user',
				'comment_author_email' => '',
				'comment_content'      => '',
				'comment_type'         => 'like',
				'comment_parent'       => $comment_id,
				'user_id'              => 0,
				'comment_approved'     => 1,
			)
		);

		$request  = new WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/posts/' . $post_id . '/reactions' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEmpty( $response->get_data() );
	}
}
