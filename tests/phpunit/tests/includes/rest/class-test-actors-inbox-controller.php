<?php
/**
 * Test file for Actors_Inbox_Controller.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Rest;

use Activitypub\Collection\Actors;
use Activitypub\Collection\Inbox as Inbox_Collection;
use Activitypub\Rest\Server;

/**
 * Test class for Actors_Inbox_Controller.
 *
 * @group rest
 * @coversDefaultClass \Activitypub\Rest\Actors_Inbox_Controller
 */
class Test_Actors_Inbox_Controller extends \Activitypub\Tests\Test_REST_Controller_Testcase {
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
	 */
	public static function set_up_before_class() {
		self::$user_id = self::factory()->user->create( array( 'role' => 'author' ) );
		self::$post_id = self::factory()->post->create(
			array(
				'post_author'  => self::$user_id,
				'post_title'   => 'Test Post',
				'post_content' => 'Test Content',
				'post_status'  => 'publish',
			)
		);
	}

	/**
	 * Set up the test.
	 */
	public function set_up() {
		\add_option( 'permalink_structure', '/%postname%/' );

		Server::init();
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

		$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/users/1/inbox' );
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

		$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/users/1/inbox' );
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

		$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/users/1/inbox' );
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

		$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/users/1/inbox' );
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
		$request        = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/users/1/inbox' );
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

		$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/users/1/inbox' );
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

		$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/users/1/inbox' );
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

		$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/users/1/inbox' );
		$request->set_header( 'Content-Type', 'application/activity+json' );
		$request->set_body( \wp_json_encode( $json ) );

		// Dispatch the request.
		$response = \rest_do_request( $request );
		$this->assertEquals( 202, $response->get_status() );

		\remove_filter( 'activitypub_defer_signature_verification', '__return_true' );
	}

	/**
	 * Test create_item verification.
	 *
	 * @covers ::create_item
	 */
	public function test_user_inbox_post_verification() {
		\add_filter(
			'activitypub_pre_http_get_remote_object',
			function ( $json, $actor ) {
				$public_key = Actors::get_public_key( self::$user_id );

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
		$actor    = Actors::get_by_id( self::$user_id );
		$object   = \Activitypub\Transformer\Post::transform( $post )->to_object();
		$activity = new \Activitypub\Activity\Activity();
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

		$signature = new \Activitypub\Signature\Http_Signature_Draft();
		$args      = $signature->sign(
			array(
				'method'      => 'POST',
				'body'        => $activity->to_json(),
				'key_id'      => $actor->get_id() . '#main-key',
				'private_key' => Actors::get_private_key( self::$user_id ),
				'headers'     => array(
					'Content-Type' => 'application/activity+json',
					'Date'         => \gmdate( 'D, d M Y H:i:s T' ),
					'Host'         => \wp_parse_url( $actor->get_inbox(), PHP_URL_HOST ),
				),
			),
			$actor->get_inbox()
		);

		$this->assertMatchesRegularExpression(
			'/keyId="' . preg_quote( $actor->get_id(), '/' ) . '#main-key",algorithm="rsa-sha256",headers="\(request-target\) host date digest",signature="[^"]*"/',
			$args['headers']['Signature']
		);

		// Signed headers.
		$route = \wp_parse_url( $actor->get_inbox(), PHP_URL_PATH );

		$request = new \WP_REST_Request( 'POST', str_replace( '/wp-json', '', $route ) );
		$request->set_body( $args['body'] );
		$request->set_headers( $args['headers'] );

		$response = \rest_do_request( $request );
		$this->assertEquals( 202, $response->get_status() );

		\remove_all_filters( 'activitypub_pre_http_get_remote_object' );
	}

	/**
	 * Test schema.
	 *
	 * @covers ::get_item_schema
	 */
	public function test_get_item_schema() {
		$request  = new \WP_REST_Request( 'OPTIONS', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/1/inbox' );
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
		add_filter(
			'activitypub_rest_inbox_array',
			function ( $inbox ) {
				$inbox['totalItems'] = 1;

				return $inbox;
			}
		);

		$request  = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/users/1/inbox' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$schema   = ( new \Activitypub\Rest\Actors_Inbox_Controller() )->get_item_schema();

		$valid = \rest_validate_value_from_schema( $data, $schema );
		$this->assertNotWPError( $valid, 'Response failed schema validation: ' . ( \is_wp_error( $valid ) ? $valid->get_error_message() : '' ) );
	}

	/**
	 * Test disallow list block.
	 *
	 * @covers ::create_item
	 */
	public function test_disallow_list_block() {
		\add_filter( 'activitypub_defer_signature_verification', '__return_true' );

		// Add a keyword that will be in our test content.
		\update_option( 'disallowed_keys', 'https://remote.example/@test' );

		// Set up mock action.
		$inbox_action = new \MockAction();
		\add_action( 'activitypub_inbox', array( $inbox_action, 'action' ) );

		// Create a valid request with content that contains the disallowed keyword.
		$json = array(
			'id'     => 'https://remote.example/@id',
			'type'   => 'Create',
			'actor'  => 'https://remote.example/@test',
			'object' => array(
				'id'        => 'https://remote.example/post/test',
				'type'      => 'Note',
				'content'   => 'Hello, World!',
				'inReplyTo' => 'https://local.example/post/test',
				'published' => '2020-01-01T00:00:00Z',
			),
		);

		$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/' . self::$user_id . '/inbox' );
		$request->set_header( 'Content-Type', 'application/activity+json' );
		$request->set_body( \wp_json_encode( $json ) );

		// Dispatch the request.
		$response = \rest_do_request( $request );

		$this->assertEquals( 202, $response->get_status() );

		// Verify that the hooks were not called.
		$this->assertEquals( 0, $inbox_action->get_call_count(), 'activitypub_inbox hook should not be called when content is disallowed' );

		// Clean up.
		\delete_option( 'disallowed_keys' );
		\remove_filter( 'activitypub_defer_signature_verification', '__return_true' );
		\remove_action( 'activitypub_inbox', array( $inbox_action, 'action' ) );
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
	 * Test context parameter is passed correctly to inbox action hook.
	 *
	 * @covers ::create_item
	 */
	public function test_inbox_context_parameter() {
		\add_filter( 'activitypub_defer_signature_verification', '__return_true' );

		$captured_context = null;

		\add_action(
			'activitypub_inbox',
			function ( $data, $user_id, $type, $activity, $context ) use ( &$captured_context ) {
				$captured_context = $context;
			},
			10,
			5
		);

		$json = array(
			'id'     => 'https://remote.example/@id-context',
			'type'   => 'Create',
			'actor'  => 'https://remote.example/@test',
			'object' => array(
				'id'        => 'https://remote.example/post/context',
				'type'      => 'Note',
				'content'   => 'Testing context parameter',
				'published' => '2020-01-01T00:00:00Z',
			),
		);

		$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/users/' . self::$user_id . '/inbox' );
		$request->set_header( 'Content-Type', 'application/activity+json' );
		$request->set_body( \wp_json_encode( $json ) );

		$response = \rest_do_request( $request );
		$this->assertEquals( 202, $response->get_status() );

		// Verify context parameter was passed correctly as inbox context.
		$this->assertEquals( Inbox_Collection::CONTEXT_INBOX, $captured_context );

		\remove_filter( 'activitypub_defer_signature_verification', '__return_true' );
		\remove_all_actions( 'activitypub_inbox' );
	}
}
