<?php
/**
 * Test file for Activitypub Rest Inbox.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Rest;

/**
 * Test class for Activitypub Rest Inbox.
 *
 * @coversDefaultClass \Activitypub\Rest\Inbox
 */
class Test_Inbox extends \WP_UnitTestCase {
	/**
	 * Test user ID.
	 *
	 * @var int
	 */
	protected static $user_id;

	/**
	 * Post ID.
	 *
	 * @var int
	 */
	protected static $post_id;

	/**
	 * Create fake data before tests run.
	 *
	 * @param WP_UnitTest_Factory $factory Helper that creates fake data.
	 */
	public static function wpSetUpBeforeClass( $factory ) {
		self::$user_id = $factory->user->create(
			array(
				'role' => 'author',
			)
		);

		self::$post_id = $factory->post->create(
			array(
				'post_author'  => self::$user_id,
				'post_title'   => 'Test Post',
				'post_content' => 'Test Content',
				'post_status'  => 'publish',
			)
		);
	}

	/**
	 * Clean up after tests.
	 */
	public static function wpTearDownAfterClass() {
		wp_delete_user( self::$user_id );
	}

	/**
	 * Set up the test.
	 */
	public function set_up() {
		\add_option( 'permalink_structure', '/%postname%/' );

		\Activitypub\Rest\Server::add_hooks();
	}

	/**
	 * Tear down the test.
	 */
	public function tear_down() {
		\delete_option( 'permalink_structure' );
	}

	/**
	 * Test the inbox signature issue.
	 */
	public function test_inbox_signature_issue() {
		\add_filter( 'activitypub_defer_signature_verification', '__return_false' );

		$json = array(
			'id'     => 'https://remote.example/@id',
			'type'   => 'Follow',
			'actor'  => 'https://remote.example/@test',
			'object' => 'https://local.example/@test',
		);

		$request = new \WP_REST_Request( 'POST', '/activitypub/1.0/users/1/inbox' );
		$request->set_header( 'Content-Type', 'application/activity+json' );
		$request->set_body( \wp_json_encode( $json ) );

		$response = \rest_do_request( $request );

		$this->assertEquals( 401, $response->get_status() );
		$this->assertEquals( 'activitypub_signature_verification', $response->get_data()['code'] );
	}

	/**
	 * Test missing attribute.
	 */
	public function test_missing_attribute() {
		\add_filter( 'activitypub_defer_signature_verification', '__return_true' );

		$json = array(
			'id'    => 'https://remote.example/@id',
			'type'  => 'Follow',
			'actor' => 'https://remote.example/@test',
		);

		$request = new \WP_REST_Request( 'POST', '/activitypub/1.0/users/1/inbox' );
		$request->set_header( 'Content-Type', 'application/activity+json' );
		$request->set_body( \wp_json_encode( $json ) );

		$response = \rest_do_request( $request );

		$this->assertEquals( 400, $response->get_status() );
		$this->assertEquals( 'rest_missing_callback_param', $response->get_data()['code'] );
		$this->assertEquals( 'object', $response->get_data()['data']['params'][0] );

		\remove_filter( 'activitypub_defer_signature_verification', '__return_true' );
	}

	/**
	 * Test follow request.
	 */
	public function test_follow_request() {
		\add_filter( 'activitypub_defer_signature_verification', '__return_true' );

		$json = array(
			'id'     => 'https://remote.example/@id',
			'type'   => 'Follow',
			'actor'  => 'https://remote.example/@test',
			'object' => 'https://local.example/@test',
		);

		$request = new \WP_REST_Request( 'POST', '/activitypub/1.0/users/1/inbox' );
		$request->set_header( 'Content-Type', 'application/activity+json' );
		$request->set_body( \wp_json_encode( $json ) );

		// Dispatch the request.
		$response = \rest_do_request( $request );
		$this->assertEquals( 202, $response->get_status() );

		\remove_filter( 'activitypub_defer_signature_verification', '__return_true' );
	}

	/**
	 * Test follow request global inbox.
	 */
	public function test_follow_request_global_inbox() {
		\add_filter( 'activitypub_defer_signature_verification', '__return_true' );

		$json = array(
			'id'     => 'https://remote.example/@id',
			'type'   => 'Follow',
			'actor'  => 'https://remote.example/@test',
			'object' => 'https://local.example/@test',
		);

		$request = new \WP_REST_Request( 'POST', '/activitypub/1.0/inbox' );
		$request->set_header( 'Content-Type', 'application/activity+json' );
		$request->set_body( \wp_json_encode( $json ) );

		// Dispatch the request.
		$response = \rest_do_request( $request );
		$this->assertEquals( 202, $response->get_status() );

		\remove_filter( 'activitypub_defer_signature_verification', '__return_true' );
	}

	/**
	 * Test create request with a remote actor.
	 */
	public function test_create_request() {
		\add_filter( 'activitypub_defer_signature_verification', '__return_true' );

		// Invalid request, because of an invalid object.
		$json = array(
			'id'     => 'https://remote.example/@id',
			'type'   => 'Create',
			'actor'  => 'https://remote.example/@test',
			'object' => 'https://local.example/@test',
		);

		$request = new \WP_REST_Request( 'POST', '/activitypub/1.0/users/1/inbox' );
		$request->set_header( 'Content-Type', 'application/activity+json' );
		$request->set_body( \wp_json_encode( $json ) );

		// Dispatch the request.
		$response = \rest_do_request( $request );
		$this->assertEquals( 400, $response->get_status() );
		$this->assertEquals( 'rest_invalid_param', $response->get_data()['code'] );

		// Valid request, because of a valid object.
		$json['object'] = array(
			'id'        => 'https://remote.example/post/test',
			'type'      => 'Note',
			'content'   => 'Hello, World!',
			'inReplyTo' => 'https://local.example/post/test',
			'published' => '2020-01-01T00:00:00Z',
		);
		$request        = new \WP_REST_Request( 'POST', '/activitypub/1.0/users/1/inbox' );
		$request->set_header( 'Content-Type', 'application/activity+json' );
		$request->set_body( \wp_json_encode( $json ) );

		// Dispatch the request.
		$response = \rest_do_request( $request );
		$this->assertEquals( 202, $response->get_status() );

		\remove_filter( 'activitypub_defer_signature_verification', '__return_true' );
	}

	/**
	 * Test create request global inbox.
	 */
	public function test_create_request_global_inbox() {
		\add_filter( 'activitypub_defer_signature_verification', '__return_true' );

		// Invalid request, because of an invalid object.
		$json = array(
			'id'     => 'https://remote.example/@id',
			'type'   => 'Create',
			'actor'  => 'https://remote.example/@test',
			'object' => 'https://local.example/@test',
		);

		$request = new \WP_REST_Request( 'POST', '/activitypub/1.0/inbox' );
		$request->set_header( 'Content-Type', 'application/activity+json' );
		$request->set_body( \wp_json_encode( $json ) );

		// Dispatch the request.
		$response = \rest_do_request( $request );
		$this->assertEquals( 400, $response->get_status() );
		$this->assertEquals( 'rest_invalid_param', $response->get_data()['code'] );

		// Valid request, because of a valid object.
		$json['object'] = array(
			'id'        => 'https://remote.example/post/test',
			'type'      => 'Note',
			'content'   => 'Hello, World!',
			'inReplyTo' => 'https://local.example/post/test',
			'published' => '2020-01-01T00:00:00Z',
		);
		$request        = new \WP_REST_Request( 'POST', '/activitypub/1.0/inbox' );
		$request->set_header( 'Content-Type', 'application/activity+json' );
		$request->set_body( \wp_json_encode( $json ) );

		// Dispatch the request.
		$response = \rest_do_request( $request );
		$this->assertEquals( 202, $response->get_status() );

		\remove_filter( 'activitypub_defer_signature_verification', '__return_true' );
	}

	/**
	 * Test update request.
	 */
	public function test_update_request() {
		\add_filter( 'activitypub_defer_signature_verification', '__return_true' );

		$json = array(
			'id'     => 'https://remote.example/@id',
			'type'   => 'Update',
			'actor'  => 'https://remote.example/@test',
			'object' => array(
				'id'        => 'https://remote.example/post/test',
				'type'      => 'Note',
				'content'   => 'Hello, World!',
				'inReplyTo' => 'https://local.example/post/test',
				'published' => '2020-01-01T00:00:00Z',
			),
		);

		$request = new \WP_REST_Request( 'POST', '/activitypub/1.0/users/1/inbox' );
		$request->set_header( 'Content-Type', 'application/activity+json' );
		$request->set_body( \wp_json_encode( $json ) );

		// Dispatch the request.
		$response = \rest_do_request( $request );
		$this->assertEquals( 202, $response->get_status() );

		\remove_filter( 'activitypub_defer_signature_verification', '__return_true' );
	}

	/**
	 * Test like request.
	 */
	public function test_like_request() {
		\add_filter( 'activitypub_defer_signature_verification', '__return_true' );

		$json = array(
			'id'     => 'https://remote.example/@id',
			'type'   => 'Like',
			'actor'  => 'https://remote.example/@test',
			'object' => 'https://local.example/post/test',
		);

		$request = new \WP_REST_Request( 'POST', '/activitypub/1.0/users/1/inbox' );
		$request->set_header( 'Content-Type', 'application/activity+json' );
		$request->set_body( \wp_json_encode( $json ) );

		// Dispatch the request.
		$response = \rest_do_request( $request );
		$this->assertEquals( 202, $response->get_status() );

		\remove_filter( 'activitypub_defer_signature_verification', '__return_true' );
	}

	/**
	 * Test announce request.
	 */
	public function test_announce_request() {
		\add_filter( 'activitypub_defer_signature_verification', '__return_true' );

		$json = array(
			'id'     => 'https://remote.example/@id',
			'type'   => 'Announce',
			'actor'  => 'https://remote.example/@test',
			'object' => 'https://local.example/post/test',
		);

		$request = new \WP_REST_Request( 'POST', '/activitypub/1.0/users/1/inbox' );
		$request->set_header( 'Content-Type', 'application/activity+json' );
		$request->set_body( \wp_json_encode( $json ) );

		// Dispatch the request.
		$response = \rest_do_request( $request );
		$this->assertEquals( 202, $response->get_status() );

		\remove_filter( 'activitypub_defer_signature_verification', '__return_true' );
	}

	/**
	 * Test whether an activity is public.
	 *
	 * @dataProvider the_data_provider
	 *
	 * @param array $data  The data.
	 * @param bool  $check The check.
	 */
	public function test_is_activity_public( $data, $check ) {
		$this->assertEquals( $check, \Activitypub\is_activity_public( $data ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function the_data_provider() {
		return array(
			array(
				array(
					'cc'     => array(
						'https://example.org/@test',
						'https://example.com/@test2',
					),
					'to'     => 'https://www.w3.org/ns/activitystreams#Public',
					'object' => array(),
				),
				true,
			),
			array(
				array(
					'cc'     => array(
						'https://example.org/@test',
						'https://example.com/@test2',
					),
					'to'     => array(
						'https://www.w3.org/ns/activitystreams#Public',
					),
					'object' => array(),
				),
				true,
			),
			array(
				array(
					'cc'     => array(
						'https://example.org/@test',
						'https://example.com/@test2',
					),
					'object' => array(),
				),
				false,
			),
			array(
				array(
					'cc'     => array(
						'https://example.org/@test',
						'https://example.com/@test2',
					),
					'object' => array(
						'to' => 'https://www.w3.org/ns/activitystreams#Public',
					),
				),
				true,
			),
			array(
				array(
					'cc'     => array(
						'https://example.org/@test',
						'https://example.com/@test2',
					),
					'object' => array(
						'to' => array(
							'https://www.w3.org/ns/activitystreams#Public',
						),
					),
				),
				true,
			),
		);
	}

	/**
	 * Test user_inbox_post verification.
	 *
	 * @covers ::user_inbox_post
	 */
	public function test_user_inbox_post_verification() {
		add_filter(
			'pre_get_remote_metadata_by_actor',
			function ( $json, $actor ) {
				$user       = \Activitypub\Collection\Actors::get_by_id( self::$user_id );
				$public_key = \Activitypub\Signature::get_public_key_for( $user->get__id() );

				// Return ActivityPub Profile with signature.
				return array(
					'id'        => $actor,
					'type'      => 'Person',
					'publicKey' => array(
						'id'           => $actor . '#main-key',
						'owner'        => $actor,
						'publicKeyPem' => $public_key,
					),
				);
			},
			10,
			2
		);

		// Get the post object.
		$post = get_post( self::$post_id );

		// Test valid request.
		$actor    = \Activitypub\Collection\Actors::get_by_id( self::$user_id );
		$object   = \Activitypub\Transformer\Post::transform( $post )->to_object();
		$activity = new \Activitypub\Activity\Activity( 'Like' );
		$activity->from_array(
			array(
				'id'     => 'https://example.com/activity/1',
				'type'   => 'Like',
				'actor'  => 'https://example.com/actor',
				'object' => $object->get_id(),
			)
		);

		// Mock remote actor URL.
		$activity->add_cc( $actor->get_id() );
		$activity = $activity->to_json();

		// Generate_digest & generate_signature.
		$digest    = \Activitypub\Signature::generate_digest( $activity );
		$date      = gmdate( 'D, d M Y H:i:s T' );
		$signature = \Activitypub\Signature::generate_signature( self::$user_id, 'POST', $actor->get_inbox(), $date, $digest );

		$this->assertMatchesRegularExpression(
			'/keyId="' . preg_quote( $actor->get_id(), '/' ) . '#main-key",algorithm="rsa-sha256",headers="\(request-target\) host date digest",signature="[^"]*"/',
			$signature
		);

		// Signed headers.
		$url_parts = wp_parse_url( $actor->get_inbox() );
		$route     = $url_parts['path'];
		$host      = $url_parts['host'];

		$request = new \WP_REST_Request( 'POST', str_replace( '/wp-json', '', $route ) );
		$request->set_header( 'content-type', 'application/activity+json' );
		$request->set_header( 'digest', $digest );
		$request->set_header( 'signature', $signature );
		$request->set_header( 'date', $date );
		$request->set_header( 'host', $host );
		$request->set_body( $activity );

		$response = \rest_do_request( $request );
		$this->assertEquals( 202, $response->get_status() );

		remove_filter( 'pre_get_remote_metadata_by_actor', '__return_true' );
	}
}
