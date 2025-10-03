<?php
/**
 * Test file for Activitypub Rest Inbox.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Rest;

use Activitypub\Collection\Actors;

/**
 * Test class for Activitypub Rest Inbox.
 *
 * @group rest
 * @coversDefaultClass \Activitypub\Rest\Inbox_Controller
 */
class Test_Inbox_Controller extends \Activitypub\Tests\Test_REST_Controller_Testcase {
	/**
	 * User ID.
	 *
	 * @var int
	 */
	protected static $user_id;

	/**
	 * Inbox Controller instance for testing.
	 *
	 * @var \Activitypub\Rest\Inbox_Controller
	 */
	private $inbox_controller;

	/**
	 * Create fake data before tests run.
	 */
	public static function set_up_before_class() {
		self::$user_id = self::factory()->user->create( array( 'role' => 'author' ) );
	}

	/**
	 * Set up the test.
	 */
	public function set_up() {
		parent::set_up();

		$this->inbox_controller = new \Activitypub\Rest\Inbox_Controller();
	}

	/**
	 * Delete fake data after tests run.
	 */
	public static function tear_down_after_class() {
		\wp_delete_user( self::$user_id );
	}

	/**
	 * Test follow request global inbox.
	 *
	 * @covers ::get_items
	 */
	public function test_get_items() {
		\add_filter( 'activitypub_defer_signature_verification', '__return_true' );

		$json = array(
			'id'     => 'https://remote.example/@id',
			'type'   => 'Follow',
			'actor'  => 'https://remote.example/@test',
			'object' => 'https://local.example/@test',
		);

		$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/inbox' );
		$request->set_header( 'Content-Type', 'application/activity+json' );
		$request->set_body( \wp_json_encode( $json ) );

		// Dispatch the request.
		$response = \rest_do_request( $request );
		$this->assertEquals( 202, $response->get_status() );

		\remove_filter( 'activitypub_defer_signature_verification', '__return_true' );
	}

	/**
	 * Test create request global inbox.
	 *
	 * @covers ::create_item
	 */
	public function test_create_item() {
		\add_filter( 'activitypub_defer_signature_verification', '__return_true' );

		// Invalid request, because of an invalid object.
		$json = array(
			'id'     => 'https://remote.example/@id',
			'type'   => 'Create',
			'actor'  => 'https://remote.example/@test',
			'object' => 'https://local.example/@test',
		);

		$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/inbox' );
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
		$request        = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/inbox' );
		$request->set_header( 'Content-Type', 'application/activity+json' );
		$request->set_body( \wp_json_encode( $json ) );

		// Dispatch the request.
		$response = \rest_do_request( $request );
		$this->assertEquals( 202, $response->get_status() );

		\remove_filter( 'activitypub_defer_signature_verification', '__return_true' );
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

		$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/inbox' );
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
	 * Test get_item_schema method.
	 *
	 * @doesNotPerformAssertions
	 */
	public function test_get_item_schema() {
		// Controller does not implement get_item_schema().
	}

	/**
	 * Test creating an inbox item with blog user context.
	 *
	 * @covers ::create_item
	 */
	public function test_create_item_with_blog_user() {
		\add_filter( 'activitypub_defer_signature_verification', '__return_true' );

		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_BLOG_MODE );

		$blog_actor = \Activitypub\Collection\Actors::get_by_id( \Activitypub\Collection\Actors::BLOG_USER_ID );

		// Set up mock action.
		$inbox_action = new \MockAction();
		\add_action( 'activitypub_inbox', array( $inbox_action, 'action' ) );

		$json = array(
			'id'     => 'https://remote.example/@id',
			'type'   => 'Create',
			'actor'  => 'https://remote.example/@test',
			'object' => array(
				'id'        => 'https://remote.example/post/test',
				'type'      => 'Note',
				'content'   => 'Hello, World!',
				'to'        => array( $blog_actor->get_id() ),
				'published' => '2020-01-01T00:00:00Z',
			),
			'to'     => array( $blog_actor->get_id() ),
		);

		$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/inbox' );
		$request->set_header( 'Content-Type', 'application/activity+json' );
		$request->set_body( \wp_json_encode( $json ) );

		$response = \rest_do_request( $request );
		$this->assertEquals( 202, $response->get_status() );

		// Verify the action was triggered exactly once for a single recipient.
		$this->assertEquals( 1, $inbox_action->get_call_count() );

		\remove_filter( 'activitypub_defer_signature_verification', '__return_true' );
		\delete_option( 'activitypub_actor_mode' );
	}

	/**
	 * Test creating an inbox item with multiple recipients.
	 *
	 * @covers ::create_item
	 */
	public function test_create_item_with_multiple_recipients() {
		\add_filter( 'activitypub_defer_signature_verification', '__return_true' );

		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_AND_BLOG_MODE );

		$user_actor = \Activitypub\Collection\Actors::get_by_id( self::$user_id );
		$blog_actor = \Activitypub\Collection\Actors::get_by_id( \Activitypub\Collection\Actors::BLOG_USER_ID );

		// Set up mock action.
		$inbox_action = new \MockAction();
		\add_action( 'activitypub_inbox', array( $inbox_action, 'action' ) );

		$json = array(
			'id'     => 'https://remote.example/@id',
			'type'   => 'Create',
			'actor'  => 'https://remote.example/@test',
			'object' => array(
				'id'        => 'https://remote.example/post/test',
				'type'      => 'Note',
				'content'   => 'Hello, World!',
				'to'        => array( $user_actor->get_id(), $blog_actor->get_id() ),
				'published' => '2020-01-01T00:00:00Z',
			),
			'to'     => array( $user_actor->get_id(), $blog_actor->get_id() ),
		);

		$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/inbox' );
		$request->set_header( 'Content-Type', 'application/activity+json' );
		$request->set_body( \wp_json_encode( $json ) );

		$response = \rest_do_request( $request );
		$this->assertEquals( 202, $response->get_status() );

		// Verify the action was triggered exactly once for each recipient.
		$this->assertEquals( 2, $inbox_action->get_call_count() );

		\remove_filter( 'activitypub_defer_signature_verification', '__return_true' );
		\delete_option( 'activitypub_actor_mode' );
	}

	/**
	 * Test creating an inbox item with multiple recipients and invalid recipient.
	 *
	 * @covers ::create_item
	 */
	public function test_create_item_with_multiple_recipients_and_invalid_recipient() {
		\add_filter( 'activitypub_defer_signature_verification', '__return_true' );

		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_AND_BLOG_MODE );

		$user_actor = \Activitypub\Collection\Actors::get_by_id( self::$user_id );
		$blog_actor = \Activitypub\Collection\Actors::get_by_id( \Activitypub\Collection\Actors::BLOG_USER_ID );

		// Set up mock action.
		$inbox_action = new \MockAction();
		\add_action( 'activitypub_inbox', array( $inbox_action, 'action' ) );

		$json = array(
			'id'     => 'https://remote.example/@id',
			'type'   => 'Create',
			'actor'  => 'https://remote.example/@test',
			'object' => array(
				'id'        => 'https://remote.example/post/test',
				'type'      => 'Note',
				'content'   => 'Hello, World!',
				'to'        => array( $user_actor->get_id(), $blog_actor->get_id() ),
				'published' => '2020-01-01T00:00:00Z',
			),
			'to'     => array( $user_actor->get_id(), $blog_actor->get_id(), 'https://invalid.example/@test' ),
		);

		$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/inbox' );
		$request->set_header( 'Content-Type', 'application/activity+json' );
		$request->set_body( \wp_json_encode( $json ) );

		$response = \rest_do_request( $request );
		$this->assertEquals( 202, $response->get_status() );

		// Verify the action was triggered exactly once for each recipient.
		$this->assertEquals( 2, $inbox_action->get_call_count() );

		\remove_filter( 'activitypub_defer_signature_verification', '__return_true' );
		\delete_option( 'activitypub_actor_mode' );
	}

	/**
	 * Test creating an inbox item with multiple recipients and inactive recipient.
	 *
	 * @covers ::create_item
	 */
	public function test_create_item_with_multiple_recipients_and_inactive_recipient() {
		\add_filter( 'activitypub_defer_signature_verification', '__return_true' );

		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_AND_BLOG_MODE );

		$user_actor = \Activitypub\Collection\Actors::get_by_id( self::$user_id );
		$blog_actor = \Activitypub\Collection\Actors::get_by_id( \Activitypub\Collection\Actors::BLOG_USER_ID );

		// Set up mock action.
		$inbox_action = new \MockAction();
		\add_action( 'activitypub_inbox', array( $inbox_action, 'action' ) );

		$json = array(
			'id'     => 'https://remote.example/@id',
			'type'   => 'Create',
			'actor'  => 'https://remote.example/@test',
			'object' => array(
				'id'        => 'https://remote.example/post/test',
				'type'      => 'Note',
				'content'   => 'Hello, World!',
				'to'        => array( $user_actor->get_id(), $blog_actor->get_id() ),
				'published' => '2020-01-01T00:00:00Z',
			),
			'to'     => array( $user_actor->get_id(), $blog_actor->get_id() ),
		);

		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_MODE );

		$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/inbox' );
		$request->set_header( 'Content-Type', 'application/activity+json' );
		$request->set_body( \wp_json_encode( $json ) );

		$response = \rest_do_request( $request );
		$this->assertEquals( 202, $response->get_status() );

		// Verify the action was triggered exactly once for each recipient.
		$this->assertEquals( 1, $inbox_action->get_call_count() );

		\remove_filter( 'activitypub_defer_signature_verification', '__return_true' );
		\delete_option( 'activitypub_actor_mode' );
	}

	/**
	 * Test creating an inbox item with different activity types.
	 *
	 * @covers ::create_item
	 */
	public function test_create_item_with_different_activity_types() {
		\add_filter( 'activitypub_defer_signature_verification', '__return_true' );

		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_MODE );

		$user_actor     = \Activitypub\Collection\Actors::get_by_id( self::$user_id );
		$activity_types = array( 'Update', 'Delete', 'Follow', 'Accept', 'Reject', 'Announce', 'Like' );

		foreach ( $activity_types as $type ) {
			// Set up mock action.
			$inbox_action = new \MockAction();
			\add_action( 'activitypub_inbox', array( $inbox_action, 'action' ) );

			$json = array(
				'id'     => 'https://remote.example/@id',
				'type'   => $type,
				'actor'  => 'https://remote.example/@test',
				'object' => array(
					'id'        => 'https://remote.example/post/test',
					'type'      => 'Note',
					'content'   => 'Hello, World!',
					'to'        => array( $user_actor->get_id() ),
					'published' => '2020-01-01T00:00:00Z',
				),
				'to'     => array( $user_actor->get_id() ),
			);

			// `Accept` needs an `object` with `actor` and `object`.
			if ( 'Accept' === $type ) {
				$json['object']['actor']  = 'https://remote.example/@test';
				$json['object']['object'] = 'https://remote.example/post/test';
			}

			$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/inbox' );
			$request->set_header( 'Content-Type', 'application/activity+json' );
			$request->set_body( \wp_json_encode( $json ) );

			$response = \rest_do_request( $request );
			$this->assertEquals( 202, $response->get_status(), "Failed for activity type: {$type}" );

			// Verify the action was triggered exactly once for a single recipient.
			$this->assertEquals( 1, $inbox_action->get_call_count() );
		}

		\remove_filter( 'activitypub_defer_signature_verification', '__return_true' );
		\delete_option( 'activitypub_actor_mode' );
	}

	/**
	 * Test creating an inbox item with invalid request.
	 *
	 * @covers ::create_item
	 */
	public function test_create_item_with_invalid_request() {
		\add_filter( 'activitypub_defer_signature_verification', '__return_true' );

		// Test with missing required fields.
		$json = array(
			'type' => 'Create',
		);

		$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/inbox' );
		$request->set_header( 'Content-Type', 'application/activity+json' );
		$request->set_body( \wp_json_encode( $json ) );

		$response = \rest_do_request( $request );
		$this->assertEquals( 400, $response->get_status() );

		// Test with invalid content type.
		$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/inbox' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( \wp_json_encode( $json ) );

		$response = \rest_do_request( $request );
		$this->assertEquals( 400, $response->get_status() );

		\remove_filter( 'activitypub_defer_signature_verification', '__return_true' );
	}

	/**
	 * Test get_local_recipients method with no recipients.
	 *
	 * @covers ::get_local_recipients
	 */
	public function test_get_local_recipients_no_recipients() {
		$activity = array(
			'type' => 'Create',
		);

		// Use reflection to test the private method.
		$reflection = new \ReflectionClass( $this->inbox_controller );
		$method     = $reflection->getMethod( 'get_local_recipients' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->inbox_controller, $activity );
		$this->assertEmpty( $result, 'Should return empty array when no recipients' );
	}

	/**
	 * Test get_local_recipients with external recipients only.
	 *
	 * @covers ::get_local_recipients
	 */
	public function test_get_local_recipients_external_only() {
		$activity = array(
			'type' => 'Create',
			'to'   => array( 'https://external.example.com/user/123' ),
			'cc'   => array( 'https://another.example.com/user/456' ),
		);

		// Use reflection to test the private method.
		$reflection = new \ReflectionClass( $this->inbox_controller );
		$method     = $reflection->getMethod( 'get_local_recipients' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->inbox_controller, $activity );
		$this->assertEmpty( $result, 'Should return empty array for external recipients only' );
	}

	/**
	 * Test get_local_recipients with actual local actor.
	 *
	 * @covers ::get_local_recipients
	 */
	public function test_get_local_recipients_with_local_actor() {
		// Get the actual actor ID for the user.
		$actor    = Actors::get_by_id( self::$user_id );
		$actor_id = $actor->get_id();

		$activity = array(
			'type' => 'Create',
			'to'   => array( $actor_id ),
			'cc'   => array( 'https://external.example.com/user/123' ),
		);

		// Use reflection to test the private method.
		$reflection = new \ReflectionClass( $this->inbox_controller );
		$method     = $reflection->getMethod( 'get_local_recipients' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->inbox_controller, $activity );
		$this->assertContains( self::$user_id, $result, 'Should contain local user ID' );
		$this->assertCount( 1, $result, 'Should contain exactly one recipient' );
	}

	/**
	 * Test get_local_recipients handles malformed actor URLs.
	 *
	 * @covers ::get_local_recipients
	 */
	public function test_get_local_recipients_with_malformed_urls() {
		$activity = array(
			'type' => 'Create',
			'to'   => array(
				'not-a-valid-url',
				get_home_url() . '/invalid-actor-path',
			),
			'cc'   => array(),
		);

		// Use reflection to test the private method.
		$reflection = new \ReflectionClass( $this->inbox_controller );
		$method     = $reflection->getMethod( 'get_local_recipients' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->inbox_controller, $activity );
		$this->assertEmpty( $result, 'Should handle malformed URLs gracefully' );
	}
}
