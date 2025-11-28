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
	 * Test handle_activity does not relay when relay mode is disabled.
	 *
	 * @covers ::handle_activity
	 */
	public function test_handle_activity_relay_mode_disabled() {
		\update_option( 'activitypub_relay_mode', false );

		$activity = array(
			'type' => 'Create',
			'id'   => 'https://example.com/activity/123',
			'to'   => array( 'https://www.w3.org/ns/activitystreams#Public' ),
		);

		// Track outbox additions.
		$outbox_added = array();
		\add_action(
			'activitypub_outbox_pre',
			function ( $activity_obj ) use ( &$outbox_added ) {
				$outbox_added[] = $activity_obj;
			}
		);

		Relay::handle_activity( $activity, array( Actors::BLOG_USER_ID ), true, null );

		// Verify nothing was added to outbox.
		$this->assertEmpty( $outbox_added );
	}

	/**
	 * Test handle_activity does not relay when activity failed.
	 *
	 * @covers ::handle_activity
	 */
	public function test_handle_activity_not_successful() {
		\update_option( 'activitypub_relay_mode', true );

		$activity = array(
			'type' => 'Create',
			'id'   => 'https://example.com/activity/123',
			'to'   => array( 'https://www.w3.org/ns/activitystreams#Public' ),
		);

		// Track outbox additions.
		$outbox_added = array();
		\add_action(
			'activitypub_outbox_pre',
			function ( $activity_obj ) use ( &$outbox_added ) {
				$outbox_added[] = $activity_obj;
			}
		);

		Relay::handle_activity( $activity, array( Actors::BLOG_USER_ID ), false, null );

		// Verify nothing was added to outbox.
		$this->assertEmpty( $outbox_added );
	}

	/**
	 * Test handle_activity does not relay when blog user is not recipient.
	 *
	 * @covers ::handle_activity
	 */
	public function test_handle_activity_not_blog_user() {
		\update_option( 'activitypub_relay_mode', true );

		$activity = array(
			'type' => 'Create',
			'id'   => 'https://example.com/activity/123',
			'to'   => array( 'https://www.w3.org/ns/activitystreams#Public' ),
		);

		// Track outbox additions.
		$outbox_added = array();
		\add_action(
			'activitypub_outbox_pre',
			function ( $activity_obj ) use ( &$outbox_added ) {
				$outbox_added[] = $activity_obj;
			}
		);

		// Test with different user ID.
		Relay::handle_activity( $activity, array( 1 ), true, null );

		// Verify nothing was added to outbox.
		$this->assertEmpty( $outbox_added );
	}

	/**
	 * Test handle_activity does not relay when activity is not public.
	 *
	 * @covers ::handle_activity
	 */
	public function test_handle_activity_not_public() {
		\update_option( 'activitypub_relay_mode', true );

		$activity = array(
			'type' => 'Create',
			'id'   => 'https://example.com/activity/123',
			'to'   => array( 'https://example.com/followers' ),
		);

		// Track outbox additions.
		$outbox_added = array();
		\add_action(
			'activitypub_outbox_pre',
			function ( $activity_obj ) use ( &$outbox_added ) {
				$outbox_added[] = $activity_obj;
			}
		);

		Relay::handle_activity( $activity, array( Actors::BLOG_USER_ID ), true, null );

		// Verify nothing was added to outbox.
		$this->assertEmpty( $outbox_added );
	}

	/**
	 * Test handle_activity relays public activity to blog user.
	 *
	 * @covers ::handle_activity
	 * @covers ::forward_activity
	 */
	public function test_handle_activity_relays_public() {
		\update_option( 'activitypub_relay_mode', true );

		$activity = array(
			'type'  => 'Create',
			'id'    => 'https://example.com/activity/123',
			'actor' => 'https://example.com/users/test',
			'to'    => array( 'https://www.w3.org/ns/activitystreams#Public' ),
		);

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

		Relay::handle_activity( $activity, array( Actors::BLOG_USER_ID ), true, null );

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
