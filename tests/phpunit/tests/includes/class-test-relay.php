<?php
/**
 * Test file for ActivityPub Relay.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

use Activitypub\Collection\Actors;
use Activitypub\Relay;

/**
 * Test class for ActivityPub Relay.
 *
 * @coversDefaultClass \Activitypub\Relay
 */
class Test_Relay extends \WP_UnitTestCase {

	/**
	 * Tear down the test.
	 */
	public function tear_down() {
		// Clean up options.
		\delete_option( 'activitypub_relay_mode' );
		\delete_option( 'activitypub_actor_mode' );

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

		// Get count of outbox posts before.
		$outbox_count_before = \wp_count_posts( 'ap_outbox' );

		Relay::handle_activity( $activity, array( Actors::BLOG_USER_ID ), true );

		// Get count of outbox posts after.
		$outbox_count_after = \wp_count_posts( 'ap_outbox' );

		// Verify nothing was added to outbox.
		$this->assertEquals( $outbox_count_before->pending, $outbox_count_after->pending );
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

		// Get count of outbox posts before.
		$outbox_count_before = \wp_count_posts( 'ap_outbox' );

		Relay::handle_activity( $activity, array( Actors::BLOG_USER_ID ), false );

		// Get count of outbox posts after.
		$outbox_count_after = \wp_count_posts( 'ap_outbox' );

		// Verify nothing was added to outbox.
		$this->assertEquals( $outbox_count_before->pending, $outbox_count_after->pending );
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

		// Get count of outbox posts before.
		$outbox_count_before = \wp_count_posts( 'ap_outbox' );

		// Test with different user ID.
		Relay::handle_activity( $activity, array( 1 ), true );

		// Get count of outbox posts after.
		$outbox_count_after = \wp_count_posts( 'ap_outbox' );

		// Verify nothing was added to outbox.
		$this->assertEquals( $outbox_count_before->pending, $outbox_count_after->pending );
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

		// Get count of outbox posts before.
		$outbox_count_before = \wp_count_posts( 'ap_outbox' );

		Relay::handle_activity( $activity, array( Actors::BLOG_USER_ID ), true );

		// Get count of outbox posts after.
		$outbox_count_after = \wp_count_posts( 'ap_outbox' );

		// Verify nothing was added to outbox.
		$this->assertEquals( $outbox_count_before->pending, $outbox_count_after->pending );
	}

	/**
	 * Test handle_activity relays public activity to blog user.
	 *
	 * @covers ::handle_activity
	 */
	public function test_handle_activity_relays_public() {
		\update_option( 'activitypub_relay_mode', true );
		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_BLOG_MODE );

		$activity = array(
			'type'  => 'Create',
			'id'    => 'https://example.com/activity/123',
			'actor' => 'https://example.com/users/test',
			'to'    => array( 'https://www.w3.org/ns/activitystreams#Public' ),
		);

		// Get IDs of outbox posts before.
		$outbox_posts_before = \get_posts(
			array(
				'post_type'   => 'ap_outbox',
				'post_status' => 'any',
				'numberposts' => -1,
				'fields'      => 'ids',
			)
		);

		Relay::handle_activity( $activity, array( Actors::BLOG_USER_ID ), true );

		// Get IDs of outbox posts after.
		$outbox_posts_after = \get_posts(
			array(
				'post_type'   => 'ap_outbox',
				'post_status' => 'any',
				'numberposts' => -1,
				'fields'      => 'ids',
			)
		);

		// Verify an outbox post was created.
		$this->assertCount( count( $outbox_posts_before ) + 1, $outbox_posts_after );

		// Find the new post ID.
		$new_post_ids = array_diff( $outbox_posts_after, $outbox_posts_before );
		$this->assertCount( 1, $new_post_ids );
		$new_post_id = reset( $new_post_ids );

		// Get the activity content.
		$activity_json = \json_decode( \get_post_field( 'post_content', $new_post_id ), true );

		// Verify the activity is an Announce.
		$this->assertEquals( 'Announce', $activity_json['type'] );

		// Verify the announce wraps the original activity.
		$this->assertIsArray( $activity_json['object'] );
		$this->assertEquals( 'Create', $activity_json['object']['type'] );
		$this->assertEquals( 'https://example.com/activity/123', $activity_json['object']['id'] );
	}

	/**
	 * Test handle_activity sets correct announce properties.
	 *
	 * @covers ::handle_activity
	 */
	public function test_handle_activity_announce_properties() {
		\update_option( 'activitypub_relay_mode', true );
		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_BLOG_MODE );

		$activity = array(
			'type' => 'Create',
			'id'   => 'https://example.com/activity/456',
			'to'   => array( 'https://www.w3.org/ns/activitystreams#Public' ),
		);

		// Get IDs of outbox posts before.
		$outbox_posts_before = \get_posts(
			array(
				'post_type'   => 'ap_outbox',
				'post_status' => 'any',
				'numberposts' => -1,
				'fields'      => 'ids',
			)
		);

		Relay::handle_activity( $activity, array( Actors::BLOG_USER_ID ), true );

		// Get IDs of outbox posts after.
		$outbox_posts_after = \get_posts(
			array(
				'post_type'   => 'ap_outbox',
				'post_status' => 'any',
				'numberposts' => -1,
				'fields'      => 'ids',
			)
		);

		// Find the new post ID.
		$new_post_ids = array_diff( $outbox_posts_after, $outbox_posts_before );
		$this->assertCount( 1, $new_post_ids );
		$new_post_id = reset( $new_post_ids );

		// Get the activity content.
		$activity_json = \json_decode( \get_post_field( 'post_content', $new_post_id ), true );

		// Verify announce properties.
		$this->assertEquals( 'Announce', $activity_json['type'] );
		$this->assertContains( 'https://www.w3.org/ns/activitystreams#Public', $activity_json['to'] );
		$this->assertNotEmpty( $activity_json['published'] );

		// Verify the object is the original activity array.
		$this->assertIsArray( $activity_json['object'] );
		$this->assertEquals( 'Create', $activity_json['object']['type'] );
		$this->assertEquals( 'https://example.com/activity/456', $activity_json['object']['id'] );
	}
}
