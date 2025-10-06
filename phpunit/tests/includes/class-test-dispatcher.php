<?php
/**
 * Test Dispatcher Class.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

use Activitypub\Activity\Activity;
use Activitypub\Collection\Actors;
use Activitypub\Collection\Followers;
use Activitypub\Collection\Outbox;
use Activitypub\Dispatcher;

/**
 * Test class for Activitypub Dispatcher.
 *
 * @coversDefaultClass \Activitypub\Dispatcher
 */
class Test_Dispatcher extends ActivityPub_Outbox_TestCase {

	/**
	 * Set up the test case.
	 */
	public function set_up() {
		parent::set_up();

		add_filter( 'pre_get_remote_metadata_by_actor', array( $this, 'add_follower' ), 10, 2 );
	}

	/**
	 * Tear down the test case.
	 */
	public function tear_down() {
		\delete_option( 'activitypub_actor_mode' );
		remove_filter( 'pre_get_remote_metadata_by_actor', array( $this, 'add_follower' ) );

		parent::tear_down();
	}

	/**
	 * Tests send_to_followers.
	 *
	 * @covers ::send_to_followers
	 */
	public function test_send_to_followers() {
		$post_id     = self::factory()->post->create( array( 'post_author' => self::$user_id ) );
		$outbox_item = $this->get_latest_outbox_item( \add_query_arg( 'p', $post_id, \home_url( '/' ) ) );

		Followers::add_follower( self::$user_id, 'https://example.org/users/username' );
		Followers::add_follower( self::$user_id, 'https://example.com/users/username' );

		$result = Dispatcher::send_to_followers( $outbox_item->ID, 1 );

		$this->assertEquals( array( $outbox_item->ID, 1, 1 ), $result );
	}

	/**
	 * Test process_outbox.
	 *
	 * @covers ::process_outbox
	 */
	public function test_process_outbox() {
		$post_id = self::factory()->post->create( array( 'post_author' => self::$user_id ) );

		$test_callback = function ( $send, $activity ) {
			$this->assertInstanceOf( Activity::class, $activity );
			$this->assertEquals( 'Create', $activity->get_type() );

			return $send;
		};
		add_filter( 'activitypub_send_activity_to_followers', $test_callback, 10, 2 );

		$outbox_item = $this->get_latest_outbox_item( \add_query_arg( 'p', $post_id, \home_url( '/' ) ) );

		Dispatcher::process_outbox( $outbox_item->ID );

		$this->assertEquals( 'publish', \get_post( $outbox_item->ID )->post_status );

		remove_filter( 'activitypub_send_activity_to_followers', $test_callback );
	}

	/**
	 * Data provider for test_send_to_inboxes.
	 *
	 * @return array
	 */
	public function data_provider_send_to_inboxes() {
		$inboxes = array( 'https://example.com/inbox1', 'https://example.com/inbox2' );

		return array(
			array( 503, 'Service Unavailable', $inboxes, $inboxes ),
			array( 404, 'Not Found', $inboxes, array() ),
		);
	}

	/**
	 * Test send_to_inboxes schedules retry for failed requests.
	 *
	 * @dataProvider data_provider_send_to_inboxes
	 * @covers ::send_to_inboxes
	 *
	 * @param int    $code HTTP response code.
	 * @param string $message HTTP response message.
	 * @param array  $inboxes Inboxes to send to.
	 * @param array  $expected Expected inboxes to be scheduled for retry.
	 *
	 * @throws \ReflectionException If the method does not exist.
	 */
	public function test_send_to_inboxes_schedules_retry( $code, $message, $inboxes, $expected ) {
		$post_id     = self::factory()->post->create( array( 'post_author' => self::$user_id ) );
		$outbox_item = $this->get_latest_outbox_item( \add_query_arg( 'p', $post_id, \home_url( '/' ) ) );

		// Mock safe_remote_post to simulate a failed request.
		add_filter(
			'pre_http_request',
			function () use ( $code, $message ) {
				return new \WP_Error( $code, $message );
			}
		);

		$send_to_inboxes = new \ReflectionMethod( Dispatcher::class, 'send_to_inboxes' );
		$send_to_inboxes->setAccessible( true );

		// Invoke the method.
		$retries = $send_to_inboxes->invoke( null, $inboxes, $outbox_item ); // null for static methods.

		$this->assertSame( $expected, $retries, 'Expected all inboxes to be scheduled for retry' );

		remove_all_filters( 'pre_http_request' );
	}

	/**
	 * Test send_to_additional_inboxes.
	 *
	 * @covers ::send_to_additional_inboxes
	 */
	public function test_send_to_relays() {
		global $wp_actions;

		$post_id      = self::factory()->post->create( array( 'post_author' => self::$user_id ) );
		$outbox_item  = $this->get_latest_outbox_item( \add_query_arg( 'p', $post_id, \home_url( '/' ) ) );
		$fake_request = function () {
			return new \WP_Error( 'test', 'test' );
		};

		add_filter( 'pre_http_request', $fake_request, 10, 3 );

		// Make `Dispatcher::send_to_additional_inboxes` a public method.
		$send_to_additional_inboxes = new \ReflectionMethod( Dispatcher::class, 'send_to_additional_inboxes' );
		$send_to_additional_inboxes->setAccessible( true );

		$send_to_additional_inboxes->invoke( null, $this->get_activity_mock(), Actors::get_by_id( self::$user_id ), $outbox_item );

		// Test how often the request was sent.
		$this->assertEquals( 0, did_action( 'activitypub_sent_to_inbox' ) );

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$wp_actions = null;

		// Add a relay.
		$relays = array( 'https://relay1.example.com/inbox' );
		update_option( 'activitypub_relays', $relays );

		$send_to_additional_inboxes->invoke( null, $this->get_activity_mock(), Actors::get_by_id( self::$user_id ), $outbox_item );

		// Test how often the request was sent.
		$this->assertEquals( 1, did_action( 'activitypub_sent_to_inbox' ) );

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$wp_actions = null;

		// Add a relay.
		$relays = array( 'https://relay1.example.com/inbox', 'https://relay2.example.com/inbox' );
		update_option( 'activitypub_relays', $relays );

		$send_to_additional_inboxes->invoke( null, $this->get_activity_mock(), Actors::get_by_id( self::$user_id ), $outbox_item );

		// Test how often the request was sent.
		$this->assertEquals( 2, did_action( 'activitypub_sent_to_inbox' ) );

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$wp_actions = null;

		$private_activity = Outbox::get_activity( $outbox_item->ID );
		$private_activity->set_to( null );
		$private_activity->set_cc( null );

		// Clone object.
		$private_activity = clone $private_activity;

		$send_to_additional_inboxes->invoke( null, $private_activity, Actors::get_by_id( self::$user_id ), $outbox_item );

		// Test how often the request was sent.
		$this->assertEquals( 0, did_action( 'activitypub_sent_to_inbox' ) );

		\remove_filter( 'pre_http_request', $fake_request, 10 );

		\delete_option( 'activitypub_relays' );
		\wp_delete_post( $post_id );
		\wp_delete_post( $outbox_item->ID );
	}

	/**
	 * Test whether an activity should be sent to followers.
	 *
	 * @covers ::should_send_to_followers
	 */
	public function test_should_send_to_followers() {
		$post_id     = self::factory()->post->create( array( 'post_author' => self::$user_id ) );
		$outbox_item = $this->get_latest_outbox_item( \add_query_arg( 'p', $post_id, \home_url( '/' ) ) );
		$activity    = \Activitypub\Collection\Outbox::get_activity( $outbox_item );

		$should_send = new \ReflectionMethod( Dispatcher::class, 'should_send_to_followers' );
		$should_send->setAccessible( true );

		// No followers, so should not send.
		$this->assertFalse( $should_send->invoke( null, $activity, Actors::get_by_id( self::$user_id ), $outbox_item ) );

		// Add a follower.
		Followers::add_follower( self::$user_id, 'https://example.org/users/username' );

		$this->assertTrue( $should_send->invoke( null, $activity, Actors::get_by_id( self::$user_id ), $outbox_item ) );
	}

	/**
	 * Returns a mock of an Activity object.
	 *
	 * @return Activity
	 */
	private function get_activity_mock() {
		$activity = $this->createMock( Activity::class, array( '__call' ) );

		// Mock the static method using reflection.
		$activity->expects( $this->any() )
			->method( '__call' )
			->willReturnCallback(
				function ( $name ) {
					if ( 'get_to' === $name ) {
						return array( 'https://www.w3.org/ns/activitystreams#Public' );
					}

					if ( 'get_cc' === $name ) {
						return array();
					}

					if ( 'get_type' === $name ) {
						return 'Create';
					}

					return null;
				}
			);

		return $activity;
	}

	/**
	 * Add a follower for testing.
	 *
	 * @param array  $pre   The pre metadata.
	 * @param string $actor The actor ID.
	 * @return array
	 */
	public function add_follower( $pre, $actor ) {
		return array(
			'id'                => $actor,
			'url'               => $actor,
			'inbox'             => $actor . '/inbox',
			'name'              => 'username',
			'preferredUsername' => 'username',
			'endpoints'         => array( 'sharedInbox' => 'https://example.org/sharedInbox' ),
		);
	}

	/**
	 * Test that in_reply_to URLs from the same domain are ignored.
	 *
	 * @covers ::add_inboxes_of_replied_urls
	 */
	public function test_ignore_same_domain_in_reply_to() {
		// Create a test activity with in_reply_to pointing to same domain.
		$activity = new Activity();
		$activity->set_type( 'Create' );
		$activity->set_id( 'https://example.com/test-id' );
		$activity->set_in_reply_to( 'https://example.com/post/123' );

		// Create a test actor.
		$actor_id = self::$user_id;

		// Get inboxes for the activity.
		$inboxes = Dispatcher::add_inboxes_of_replied_urls( array(), $actor_id, $activity );

		// Verify that no inboxes were added since the in_reply_to is from same domain.
		$this->assertEmpty( $inboxes, 'Inboxes should be empty for same domain in_reply_to URLs' );
	}

	/**
	 * Test that in_reply_to URLs from different domains are processed.
	 *
	 * @covers ::add_inboxes_of_replied_urls
	 */
	public function test_process_different_domain_in_reply_to() {
		// Create a test activity with in_reply_to pointing to different domain.
		$activity = new Activity();
		$activity->set_type( 'Create' );
		$activity->set_id( 'https://example.com/test-id' );
		$activity->set_in_reply_to( 'https://mastodon.social/@user/123456789' );

		// Create a test actor.
		$actor_id = self::$user_id;

		$callback = function ( $pre, $parsed_args, $url ) {
			if ( 'https://mastodon.social/@user/123456789' === $url ) {
				return array(
					'response' => array( 'code' => 200 ),
					'body'     => \wp_json_encode(
						array(
							'type'         => 'Note',
							'id'           => 'https://mastodon.social/@user/123456789',
							'attributedTo' => 'https://mastodon.social/@user',
						)
					),
				);
			}

			if ( 'https://mastodon.social/@user' === $url ) {
				return array(
					'response' => array( 'code' => 200 ),
					'body'     => \wp_json_encode(
						array(
							'type'  => 'Person',
							'id'    => 'https://mastodon.social/@user',
							'inbox' => 'https://mastodon.social/inbox',
						)
					),
				);
			}

			return $pre;
		};

		// Mock the HTTP response for the remote object.
		add_filter( 'pre_http_request', $callback, 10, 3 );

		// Get inboxes for the activity.
		$inboxes = Dispatcher::add_inboxes_of_replied_urls( array(), $actor_id, $activity );

		// Verify that the inbox was added.
		$this->assertContains( 'https://mastodon.social/inbox', $inboxes, 'Inbox should be added for different domain in_reply_to URLs' );

		// Clean up.
		remove_filter( 'pre_http_request', $callback );
	}
}
