<?php
/**
 * Test file for Activitypub Rest Inbox.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Rest;

use Activitypub\Collection\Actors;
use Activitypub\Collection\Inbox as Inbox_Collection;

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

		// Verify the action was triggered for the blog user.
		// With shared inbox support, activitypub_inbox fires for each recipient.
		$this->assertGreaterThanOrEqual( 1, $inbox_action->get_call_count() );

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

		// Verify the action was triggered for each recipient.
		// With shared inbox support, activitypub_inbox fires once per recipient.
		$this->assertGreaterThanOrEqual( 2, $inbox_action->get_call_count() );

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

		// Verify the action was triggered for each valid recipient.
		// With shared inbox support, activitypub_inbox fires once per recipient.
		$this->assertGreaterThanOrEqual( 2, $inbox_action->get_call_count() );

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

		// Verify the action was triggered for active recipient only.
		// With shared inbox support, activitypub_inbox fires once per recipient.
		$this->assertGreaterThanOrEqual( 1, $inbox_action->get_call_count() );

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

			// Verify the action was triggered for the recipient.
			// With shared inbox support, activitypub_inbox fires once per recipient.
			$this->assertGreaterThanOrEqual( 1, $inbox_action->get_call_count(), "Failed for activity type: {$type}" );
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

	/**
	 * Test get_local_recipients with public activity.
	 *
	 * @covers ::get_local_recipients
	 */
	public function test_get_local_recipients_public_activity() {
		// Enable actor mode to allow user actors.
		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_MODE );

		// Create additional test users (authors have activitypub capability by default).
		$user_id_1 = self::factory()->user->create( array( 'role' => 'author' ) );
		$user_id_2 = self::factory()->user->create( array( 'role' => 'author' ) );
		$user_id_3 = self::factory()->user->create( array( 'role' => 'editor' ) );

		// Create a remote actor and make our users follow them.
		$remote_actor_url = 'https://example.com/actor/1';

		// Mock the remote actor fetch.
		\add_filter(
			'activitypub_pre_http_get_remote_object',
			function ( $pre, $url ) use ( $remote_actor_url ) {
				if ( $url === $remote_actor_url ) {
					return array(
						'@context'          => 'https://www.w3.org/ns/activitystreams',
						'id'                => $remote_actor_url,
						'type'              => 'Person',
						'preferredUsername' => 'testactor',
						'name'              => 'Test Actor',
						'inbox'             => 'https://example.com/actor/1/inbox',
					);
				}
				return $pre;
			},
			10,
			2
		);

		$remote_actor = \Activitypub\Collection\Remote_Actors::fetch_by_uri( $remote_actor_url );

		// Make users follow the remote actor.
		\add_post_meta( $remote_actor->ID, '_activitypub_followers', self::$user_id );
		\add_post_meta( $remote_actor->ID, '_activitypub_followers', $user_id_1 );
		\add_post_meta( $remote_actor->ID, '_activitypub_followers', $user_id_2 );
		\add_post_meta( $remote_actor->ID, '_activitypub_followers', $user_id_3 );

		// Public activity with "to" containing the public collection.
		$activity = array(
			'type'  => 'Create',
			'actor' => $remote_actor_url,
			'to'    => array( 'https://www.w3.org/ns/activitystreams#Public' ),
			'cc'    => array( 'https://external.example.com/followers' ),
		);

		// Use reflection to test the private method.
		$reflection = new \ReflectionClass( $this->inbox_controller );
		$method     = $reflection->getMethod( 'get_local_recipients' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->inbox_controller, $activity );

		// Should return users who follow the remote actor.
		$this->assertNotEmpty( $result, 'Should return users for public activity' );
		$this->assertContains( self::$user_id, $result, 'Should contain test user' );
		$this->assertContains( $user_id_1, $result, 'Should contain user 1' );
		$this->assertContains( $user_id_2, $result, 'Should contain user 2' );
		$this->assertContains( $user_id_3, $result, 'Should contain user 3' );

		// Verify it returns exactly the followers we added.
		// Note: May include blog user (0) if blog mode is enabled.
		$this->assertGreaterThanOrEqual( 4, count( $result ), 'Should return at least 4 followers' );
		$this->assertLessThanOrEqual( 5, count( $result ), 'Should return at most 5 followers (4 users + optional blog)' );

		// Clean up.
		\wp_delete_post( $remote_actor->ID, true );
		\wp_delete_user( $user_id_1 );
		\wp_delete_user( $user_id_2 );
		\wp_delete_user( $user_id_3 );
		\delete_option( 'activitypub_actor_mode' );
		\remove_all_filters( 'activitypub_pre_http_get_remote_object' );
	}

	/**
	 * Test get_local_recipients with public activity using "cc" field.
	 *
	 * @covers ::get_local_recipients
	 */
	public function test_get_local_recipients_public_activity_in_cc() {
		// Enable actor mode to allow user actors.
		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_MODE );

		// Create a test user (authors have activitypub capability by default).
		$user_id = self::factory()->user->create( array( 'role' => 'author' ) );

		// Create a remote actor and make our users follow them.
		$remote_actor_url = 'https://example.com/actor/1';

		// Mock the remote actor fetch.
		\add_filter(
			'activitypub_pre_http_get_remote_object',
			function ( $pre, $url ) use ( $remote_actor_url ) {
				if ( $url === $remote_actor_url ) {
					return array(
						'@context'          => 'https://www.w3.org/ns/activitystreams',
						'id'                => $remote_actor_url,
						'type'              => 'Person',
						'preferredUsername' => 'testactor',
						'name'              => 'Test Actor',
						'inbox'             => 'https://example.com/actor/1/inbox',
					);
				}
				return $pre;
			},
			10,
			2
		);

		$remote_actor = \Activitypub\Collection\Remote_Actors::fetch_by_uri( $remote_actor_url );

		// Make users follow the remote actor.
		\add_post_meta( $remote_actor->ID, '_activitypub_followers', self::$user_id );
		\add_post_meta( $remote_actor->ID, '_activitypub_followers', $user_id );

		// Public activity with "cc" containing the public collection.
		$activity = array(
			'type'  => 'Create',
			'actor' => $remote_actor_url,
			'to'    => array( 'https://external.example.com/user/specific' ),
			'cc'    => array( 'https://www.w3.org/ns/activitystreams#Public' ),
		);

		// Use reflection to test the private method.
		$reflection = new \ReflectionClass( $this->inbox_controller );
		$method     = $reflection->getMethod( 'get_local_recipients' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->inbox_controller, $activity );

		// Should return users who follow the remote actor because activity is public.
		$this->assertNotEmpty( $result, 'Should return users for public activity in cc' );
		$this->assertContains( self::$user_id, $result, 'Should contain original test user' );
		$this->assertContains( $user_id, $result, 'Should contain new test user' );

		// Verify it returns exactly the followers we added.
		// Note: May include blog user (0) if blog mode is enabled.
		$this->assertGreaterThanOrEqual( 2, count( $result ), 'Should return at least 2 followers' );
		$this->assertLessThanOrEqual( 3, count( $result ), 'Should return at most 3 followers (2 users + optional blog)' );

		// Clean up.
		\wp_delete_post( $remote_actor->ID, true );
		\wp_delete_user( $user_id );
		\delete_option( 'activitypub_actor_mode' );
		\remove_all_filters( 'activitypub_pre_http_get_remote_object' );
	}

	/**
	 * Test context parameter is passed to action hooks for shared inbox.
	 *
	 * @covers ::create_item
	 */
	public function test_shared_inbox_context_parameter() {
		\add_filter( 'activitypub_defer_signature_verification', '__return_true' );
		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_AND_BLOG_MODE );

		$user_actor = \Activitypub\Collection\Actors::get_by_id( self::$user_id );
		$blog_actor = \Activitypub\Collection\Actors::get_by_id( \Activitypub\Collection\Actors::BLOG_USER_ID );

		$captured_context = null;

		\add_action(
			'activitypub_inbox_shared',
			function ( $data, $user_ids, $type, $activity, $context ) use ( &$captured_context ) {
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

		// Verify context parameter was passed correctly.
		$this->assertEquals( Inbox_Collection::CONTEXT_SHARED_INBOX, $captured_context );

		\remove_filter( 'activitypub_defer_signature_verification', '__return_true' );
		\remove_all_actions( 'activitypub_inbox_shared' );
		\delete_option( 'activitypub_actor_mode' );
	}

	/**
	 * Test shared inbox action hook fires with multiple recipients.
	 *
	 * @covers ::create_item
	 */
	public function test_shared_inbox_action_hook_fires() {
		\add_filter( 'activitypub_defer_signature_verification', '__return_true' );
		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_AND_BLOG_MODE );

		$user_actor = \Activitypub\Collection\Actors::get_by_id( self::$user_id );
		$blog_actor = \Activitypub\Collection\Actors::get_by_id( \Activitypub\Collection\Actors::BLOG_USER_ID );

		$shared_inbox_fired  = false;
		$captured_recipients = null;

		\add_action(
			'activitypub_inbox_shared',
			function ( $data, $user_ids ) use ( &$shared_inbox_fired, &$captured_recipients ) {
				$shared_inbox_fired  = true;
				$captured_recipients = $user_ids;
			},
			10,
			2
		);

		$json = array(
			'id'     => 'https://remote.example/@id-shared',
			'type'   => 'Create',
			'actor'  => 'https://remote.example/@test',
			'object' => array(
				'id'        => 'https://remote.example/post/shared',
				'type'      => 'Note',
				'content'   => 'Testing shared inbox',
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

		// Verify shared inbox action fired.
		$this->assertTrue( $shared_inbox_fired, 'Shared inbox action should fire' );
		$this->assertIsArray( $captured_recipients, 'Recipients should be an array' );
		$this->assertCount( 2, $captured_recipients, 'Should have 2 recipients' );
		$this->assertContains( self::$user_id, $captured_recipients );
		$this->assertContains( Actors::BLOG_USER_ID, $captured_recipients );

		\remove_filter( 'activitypub_defer_signature_verification', '__return_true' );
		\remove_all_actions( 'activitypub_inbox_shared' );
		\delete_option( 'activitypub_actor_mode' );
	}

	/**
	 * Test inbox persistence with shared inbox.
	 *
	 * @covers ::create_item
	 */
	public function test_inbox_persistence_with_shared_inbox() {
		\add_filter( 'activitypub_defer_signature_verification', '__return_true' );
		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_AND_BLOG_MODE );

		$user_actor = \Activitypub\Collection\Actors::get_by_id( self::$user_id );
		$blog_actor = \Activitypub\Collection\Actors::get_by_id( \Activitypub\Collection\Actors::BLOG_USER_ID );

		$inbox_id = null;

		\add_action(
			'activitypub_handled_inbox',
			function ( $data, $user_ids, $type, $activity, $item_id ) use ( &$inbox_id ) {
				$inbox_id = $item_id;
			},
			10,
			5
		);

		$json = array(
			'id'     => 'https://remote.example/@id-persist',
			'type'   => 'Create',
			'actor'  => 'https://remote.example/@test',
			'object' => array(
				'id'        => 'https://remote.example/post/persist',
				'type'      => 'Note',
				'content'   => 'Testing inbox persistence',
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

		// Verify inbox item was created.
		$this->assertIsInt( $inbox_id, 'Inbox item should be created' );
		$this->assertGreaterThan( 0, $inbox_id, 'Inbox item ID should be positive' );

		// Verify both recipients are stored.
		$recipients = Inbox_Collection::get_recipients( $inbox_id );
		$this->assertCount( 2, $recipients, 'Should have 2 recipients' );
		$this->assertContains( self::$user_id, $recipients );
		$this->assertContains( Actors::BLOG_USER_ID, $recipients );

		\remove_filter( 'activitypub_defer_signature_verification', '__return_true' );
		\remove_all_actions( 'activitypub_handled_inbox' );
		\remove_all_actions( 'activitypub_inbox' );
		\remove_all_actions( 'activitypub_inbox_shared' );
		\delete_option( 'activitypub_actor_mode' );
	}

	/**
	 * Test regular inbox action hook fires with shared inbox context on fallback.
	 *
	 * @covers ::create_item
	 */
	public function test_regular_inbox_action_with_shared_inbox_context() {
		\add_filter( 'activitypub_defer_signature_verification', '__return_true' );
		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_AND_BLOG_MODE );

		$user_actor = \Activitypub\Collection\Actors::get_by_id( self::$user_id );
		$blog_actor = \Activitypub\Collection\Actors::get_by_id( \Activitypub\Collection\Actors::BLOG_USER_ID );

		$inbox_contexts = array();

		\add_action(
			'activitypub_inbox',
			function ( $data, $user_id, $type, $activity, $context ) use ( &$inbox_contexts ) {
				$inbox_contexts[] = $context;
			},
			10,
			5
		);

		$json = array(
			'id'     => 'https://remote.example/@id-fallback',
			'type'   => 'Create',
			'actor'  => 'https://remote.example/@test',
			'object' => array(
				'id'        => 'https://remote.example/post/fallback',
				'type'      => 'Note',
				'content'   => 'Testing fallback context',
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

		// Verify inbox action was called for each recipient with shared_inbox context.
		$this->assertGreaterThanOrEqual( 2, count( $inbox_contexts ), 'Should fire inbox action for each recipient' );
		foreach ( $inbox_contexts as $context ) {
			$this->assertEquals( Inbox_Collection::CONTEXT_SHARED_INBOX, $context, 'Context should be shared_inbox' );
		}

		\remove_filter( 'activitypub_defer_signature_verification', '__return_true' );
		\remove_all_actions( 'activitypub_inbox' );
		\remove_all_actions( 'activitypub_inbox_shared' );
		\delete_option( 'activitypub_actor_mode' );
	}
}
