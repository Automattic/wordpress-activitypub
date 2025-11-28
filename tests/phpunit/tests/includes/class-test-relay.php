<?php
/**
 * Test file for ActivityPub Relay.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

use Activitypub\Activity\Activity;
use Activitypub\Collection\Actors;
use Activitypub\Relay;

/**
 * Test class for ActivityPub Relay.
 *
 * @coversDefaultClass \Activitypub\Relay
 */
class Test_Relay extends \WP_UnitTestCase {

	/**
	 * Set up the test.
	 */
	public function set_up() {
		parent::set_up();

		// Store original relay mode setting.
		$this->original_relay_mode = \get_option( 'activitypub_relay_mode', false );

		// Ensure relay mode is disabled at start.
		\update_option( 'activitypub_relay_mode', false );
	}

	/**
	 * Tear down the test.
	 */
	public function tear_down() {
		// Restore original relay mode setting.
		\update_option( 'activitypub_relay_mode', $this->original_relay_mode );

		parent::tear_down();
	}

	/**
	 * Test should_relay returns false when relay mode is disabled.
	 *
	 * @covers ::should_relay
	 */
	public function test_should_relay_disabled() {
		\update_option( 'activitypub_relay_mode', false );

		$activity = new Activity();
		$activity->set_to( array( 'https://www.w3.org/ns/activitystreams#Public' ) );

		$this->assertFalse( Relay::should_relay( $activity, array( Actors::BLOG_USER_ID ) ) );
	}

	/**
	 * Test should_relay returns false when blog user is not recipient.
	 *
	 * @covers ::should_relay
	 */
	public function test_should_relay_not_blog_user() {
		\update_option( 'activitypub_relay_mode', true );

		$activity = new Activity();
		$activity->set_to( array( 'https://www.w3.org/ns/activitystreams#Public' ) );

		// Test with different user ID.
		$this->assertFalse( Relay::should_relay( $activity, array( 1 ) ) );
	}

	/**
	 * Test should_relay returns false when activity is not public.
	 *
	 * @covers ::should_relay
	 */
	public function test_should_relay_not_public() {
		\update_option( 'activitypub_relay_mode', true );

		$activity = new Activity();
		$activity->set_to( array( 'https://example.com/followers' ) );
		$activity->set_cc( array() );

		$this->assertFalse( Relay::should_relay( $activity, array( Actors::BLOG_USER_ID ) ) );
	}

	/**
	 * Test should_relay returns true for public activity to blog user.
	 *
	 * @covers ::should_relay
	 */
	public function test_should_relay_public_to_blog() {
		\update_option( 'activitypub_relay_mode', true );

		$activity = new Activity();
		$activity->set_to( array( 'https://www.w3.org/ns/activitystreams#Public' ) );

		$this->assertTrue( Relay::should_relay( $activity, array( Actors::BLOG_USER_ID ) ) );
	}

	/**
	 * Test should_relay returns true when public is in CC.
	 *
	 * @covers ::should_relay
	 */
	public function test_should_relay_public_in_cc() {
		\update_option( 'activitypub_relay_mode', true );

		$activity = new Activity();
		$activity->set_to( array( 'https://example.com/followers' ) );
		$activity->set_cc( array( 'https://www.w3.org/ns/activitystreams#Public' ) );

		$this->assertTrue( Relay::should_relay( $activity, array( Actors::BLOG_USER_ID ) ) );
	}

	/**
	 * Test forward_activity creates an Announce and adds to outbox.
	 *
	 * @covers ::forward_activity
	 */
	public function test_forward_activity() {
		\update_option( 'activitypub_relay_mode', true );

		// Create a test activity.
		$activity = new Activity();
		$activity->set_type( 'Create' );
		$activity->set_id( 'https://example.com/activity/123' );
		$activity->set_actor( 'https://example.com/users/test' );
		$activity->set_to( array( 'https://www.w3.org/ns/activitystreams#Public' ) );

		// Track outbox additions.
		$outbox_added = array();
		\add_action(
			'activitypub_outbox_pre',
			function ( $activity_obj, $user_id ) use ( &$outbox_added ) {
				$outbox_added[] = array(
					'activity' => $activity_obj,
					'user_id'  => $user_id,
				);
			},
			10,
			2
		);

		// Forward the activity.
		Relay::forward_activity( $activity );

		// Verify an activity was added to outbox.
		$this->assertCount( 1, $outbox_added );
		$this->assertEquals( Actors::BLOG_USER_ID, $outbox_added[0]['user_id'] );

		// Verify the activity is an Announce.
		$announce = $outbox_added[0]['activity'];
		$this->assertInstanceOf( Activity::class, $announce );
		$this->assertEquals( 'Announce', $announce->get_type() );

		// Verify the announce wraps the original activity.
		$object = $announce->get_object();
		$this->assertIsArray( $object );
		$this->assertEquals( 'Create', $object['type'] );
		$this->assertEquals( 'https://example.com/activity/123', $object['id'] );
	}

	/**
	 * Test forward_activity sets correct announce properties.
	 *
	 * @covers ::forward_activity
	 */
	public function test_forward_activity_properties() {
		\update_option( 'activitypub_relay_mode', true );

		$activity = new Activity();
		$activity->set_type( 'Create' );
		$activity->set_id( 'https://example.com/activity/456' );
		$activity->set_to( array( 'https://www.w3.org/ns/activitystreams#Public' ) );

		// Track outbox additions.
		$announce = null;
		\add_action(
			'activitypub_outbox_pre',
			function ( $activity_obj ) use ( &$announce ) {
				$announce = $activity_obj;
			},
			10
		);

		Relay::forward_activity( $activity );

		// Verify announce properties.
		$this->assertEquals( 'Announce', $announce->get_type() );
		$this->assertEquals( array( 'https://www.w3.org/ns/activitystreams#Public' ), $announce->get_to() );
		$this->assertNotEmpty( $announce->get_published() );
		$this->assertNotEmpty( $announce->get_id() );

		// Verify announce ID contains relay identifier.
		$announce_id = $announce->get_id();
		$this->assertStringContainsString( 'p=relay', $announce_id );
		$this->assertStringContainsString( rawurlencode( 'https://example.com/activity/456' ), $announce_id );
	}
}
