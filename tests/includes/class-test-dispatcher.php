<?php
/**
 * Test Dispatcher Class.
 *
 * @package ActivityPub
 */

use Activitypub\Activity\Activity;
use Activitypub\Collection\Actors;
use Activitypub\Collection\Outbox;
use Activitypub\Collection\Followers;
use Activitypub\Dispatcher;

/**
 * Test class for Activitypub Dispatcher.
 *
 * @coversDefaultClass Activitypub\Dispatcher
 */
class Test_Dispatcher extends \Activitypub\Tests\ActivityPub_Outbox_TestCase {
	/**
	 * Tear down the test case.
	 */
	public function tear_down() {
		\delete_option( 'activitypub_actor_mode' );

		parent::tear_down();
	}

	/**
	 * Test maybe_add_inboxes_of_blog_user when actor mode is not ACTIVITYPUB_ACTOR_AND_BLOG_MODE
	 *
	 * @covers ::maybe_add_inboxes_of_blog_user
	 * @expectedDeprecated Activitypub\Dispatcher::maybe_add_inboxes_of_blog_user
	 */
	public function test_maybe_add_inboxes_of_blog_user_wrong_mode() {
		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_BLOG_MODE );

		$inboxes  = array( 'https://example.com/inbox' );
		$activity = $this->createMock( Activity::class );

		$result = Dispatcher::maybe_add_inboxes_of_blog_user( $inboxes, 1, $activity );
		$this->assertEquals( $inboxes, $result );
	}

	/**
	 * Test maybe_add_inboxes_of_blog_user when actor is blog user
	 *
	 * @covers ::maybe_add_inboxes_of_blog_user
	 * @expectedDeprecated Activitypub\Dispatcher::maybe_add_inboxes_of_blog_user
	 */
	public function test_maybe_add_inboxes_of_blog_user_is_blog_user() {
		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_AND_BLOG_MODE );

		$inboxes  = array( 'https://example.com/inbox' );
		$activity = $this->createMock( Activity::class );

		$result = Dispatcher::maybe_add_inboxes_of_blog_user( $inboxes, Actors::BLOG_USER_ID, $activity );
		$this->assertEquals( $inboxes, $result );
	}

	/**
	 * Test maybe_add_inboxes_of_blog_user when activity type is not Update
	 *
	 * @covers ::maybe_add_inboxes_of_blog_user
	 * @expectedDeprecated Activitypub\Dispatcher::maybe_add_inboxes_of_blog_user
	 */
	public function test_maybe_add_inboxes_of_blog_user_not_update() {
		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_AND_BLOG_MODE );

		$inboxes  = array( 'https://example.com/inbox' );
		$activity = $this->get_activity_mock();

		$result = Dispatcher::maybe_add_inboxes_of_blog_user( $inboxes, 1, $activity );
		$this->assertEquals( $inboxes, $result );
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
	 * Test that the deprecated filter activitypub_send_to_inboxes is still working.
	 * This test can be removed when the filter is removed.
	 *
	 * @covers ::maybe_add_inboxes_of_blog_user
	 * @expectedDeprecated activitypub_interactees_inboxes
	 */
	public function test_deprecated_filter() {
		add_filter(
			'activitypub_interactees_inboxes',
			function ( $inboxes ) {
				$inboxes[] = 'https://example.com/inbox';

				return $inboxes;
			}
		);

		$inboxes = apply_filters( 'activitypub_additional_inboxes', array(), 1, $this->get_activity_mock() );
		$this->assertContains( 'https://example.com/inbox', $inboxes );

		remove_all_filters( 'activitypub_interactees_inboxes' );
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
	 * @throws ReflectionException If the method does not exist.
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

		$send_to_inboxes = new ReflectionMethod( Dispatcher::class, 'send_to_inboxes' );
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
		$send_to_additional_inboxes = new ReflectionMethod( Dispatcher::class, 'send_to_additional_inboxes' );
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

		$should_send = new ReflectionMethod( Dispatcher::class, 'should_send_to_followers' );
		$should_send->setAccessible( true );

		// No followers, so should not send.
		$this->assertFalse( $should_send->invoke( null, $activity, Actors::get_by_id( self::$user_id ), $outbox_item ) );

		// Add a follower.
		add_filter(
			'pre_get_remote_metadata_by_actor',
			function () {
				return array(
					'id'                => 'https://example.org/users/username',
					'url'               => 'https://example.org/users/username',
					'inbox'             => 'https://example.org/users/username/inbox',
					'name'              => 'username',
					'preferredUsername' => 'username',
					'endpoints'         => array( 'sharedInbox' => 'https://example.org/sharedInbox' ),
				);
			}
		);
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
}
