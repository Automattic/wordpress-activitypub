<?php
/**
 * Test file for Federation Functions.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

use Activitypub\Activity\Activity;
use Activitypub\Collection\Outbox;

use function Activitypub\add_to_outbox;

/**
 * Test class for Federation Functions.
 */
class Test_Functions_Federation extends ActivityPub_TestCase_Cache_HTTP {

	/**
	 * Test is_self_ping.
	 *
	 * @covers \Activitypub\is_self_ping
	 */
	public function test_is_self_ping() {
		$this->assertFalse( \Activitypub\is_self_ping( \home_url() ) );
		$this->assertFalse( \Activitypub\is_self_ping( 'https://example.com' ) );
		$this->assertTrue( \Activitypub\is_self_ping( \home_url( '?c=123' ) ) );
		$this->assertFalse( \Activitypub\is_self_ping( 'https://example.com/?c=123' ) );
	}

	/**
	 * Tests follow method.
	 *
	 * @covers \Activitypub\follow
	 */
	public function test_follow() {
		$user_id = self::factory()->user->create(
			array(
				'role' => 'administrator',
			)
		);

		$actor_array = array(
			'id'                 => 'https://example.com/users/test',
			'type'               => 'Person',
			'name'               => 'Test Follower',
			'preferred_username' => 'Follower',
			'summary'            => '<p>HTML content</p>',
			'endpoints'          => array(
				'sharedInbox' => 'https://example.com/inbox',
			),
		);

		$remote_actor = function () use ( $actor_array ) {
			return $actor_array;
		};

		\add_filter( 'activitypub_pre_http_get_remote_object', $remote_actor );

		\Activitypub\follow( 'https://example.com/users/test', $user_id );

		$outbox_items = \get_posts(
			array(
				'post_type'   => \Activitypub\Collection\Outbox::POST_TYPE,
				'post_status' => 'any',
				'author'      => $user_id,
			)
		);

		$this->assertEquals( 1, count( $outbox_items ) );
		$this->assertEquals( 'Follow', \get_post_meta( $outbox_items[0]->ID, '_activitypub_activity_type', true ) );

		\remove_filter( 'activitypub_pre_http_get_remote_object', $remote_actor );
	}

	/**
	 * Test that Update activities have the updated attribute set.
	 *
	 * @covers \Activitypub\add_to_outbox
	 */
	public function test_webfinger_support() {
		$follow = new Activity();
		$follow->set_type( 'Follow' );
		$follow->set_actor( 'https://example.com/user/1' );
		$follow->set_object( 'user1@example.com' );
		$follow->set_to( array( 'https://example.com/user/2' ) );

		$filter = function () {
			return array(
				'response' => array(
					'code' => 200,
				),
				'body'     => wp_json_encode(
					array(
						'subject' => 'acct:pfefferle@example.org',
						'aliases' => array( 'https://example.org/?author=1' ),
						'links'   => array(
							array(
								'rel'  => 'self',
								'href' => 'https://example.org/?author=1',
								'type' => 'application/activity+json',
							),
						),
					)
				),
			);
		};
		\add_filter( 'pre_http_request', $filter );

		$id = add_to_outbox( $follow, null, 1 );

		$this->assertNotFalse( $id );

		\remove_filter( 'pre_http_request', $filter );

		// Get the activity from the outbox.
		$activity = Outbox::get_activity( $id );
		$this->assertNotInstanceOf( \WP_Error::class, $activity );

		$this->assertEquals( 'Follow', $activity->get_type() );
		$this->assertEquals( 'https://example.org/?author=1', get_post_meta( $id, '_activitypub_object_id', true ) );
	}
}
