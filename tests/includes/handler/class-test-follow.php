<?php
/**
 * Test file for Follow handler.
 *
 * @package ActivityPub
 */

namespace Activitypub\Tests\Handler;

use Activitypub\Handler\Follow;
use Activitypub\Model\Follower;
use Activitypub\Collection\Outbox;
use WP_UnitTestCase;

/**
 * Test class for Follow handler.
 *
 * @coversDefaultClass \Activitypub\Handler\Follow
 */
class Test_Follow extends WP_UnitTestCase {
	/**
	 * Test user ID.
	 *
	 * @var int
	 */
	protected static $user_id;

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
	}

	/**
	 * Clean up after tests.
	 */
	public static function wpTearDownAfterClass() {
		wp_delete_user( self::$user_id );
	}

	/**
	 * Test queue_accept method.
	 *
	 * @covers ::queue_accept
	 */
	public function test_queue_accept() {
		$actor           = 'https://example.com/actor';
		$activity_object = array(
			'id'     => 'https://example.com/activity/123',
			'type'   => 'Follow',
			'actor'  => $actor,
			'object' => 'https://example.com/user/1',
		);

		// Test with WP_Error follower - should not create outbox entry.
		$wp_error = new \WP_Error( 'test_error', 'Test Error' );
		Follow::queue_accept( $actor, $activity_object, self::$user_id, $wp_error );

		$outbox_posts = get_posts(
			array(
				'post_type'   => Outbox::POST_TYPE,
				'author'      => self::$user_id,
				'post_status' => 'pending',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_query'  => array(
					array(
						'key'   => '_activitypub_activity_actor',
						'value' => 'user',
					),
				),
			)
		);
		$this->assertEmpty( $outbox_posts, 'No outbox entry should be created for WP_Error follower' );

		// Test with valid follower.
		$follower = new Follower();
		$follower->set_actor( $actor );
		$follower->set_type( 'Person' );
		$follower->set_inbox( 'https://example.com/inbox' );

		Follow::queue_accept( $actor, $activity_object, self::$user_id, $follower );

		$outbox_posts = get_posts(
			array(
				'post_type'   => Outbox::POST_TYPE,
				'author'      => self::$user_id,
				'post_status' => 'pending',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_query'  => array(
					array(
						'key'   => '_activitypub_activity_actor',
						'value' => 'user',
					),
				),
			)
		);

		$this->assertCount( 1, $outbox_posts, 'One outbox entry should be created' );

		$outbox_post   = $outbox_posts[0];
		$activity_type = \get_post_meta( $outbox_post->ID, '_activitypub_activity_type', true );
		$activity_json = \json_decode( $outbox_post->post_content, true );
		$visibility    = \get_post_meta( $outbox_post->ID, 'activitypub_content_visibility', true );

		// Verify outbox entry.
		$this->assertEquals( 'Accept', $activity_type );
		$this->assertEquals( ACTIVITYPUB_CONTENT_VISIBILITY_PRIVATE, $visibility );

		$this->assertEquals( 'Follow', $activity_json['object']['type'] );
		$this->assertEquals( 'https://example.com/user/1', $activity_json['object']['object'] );
		$this->assertEquals( array( $actor ), $activity_json['to'] );
		$this->assertEquals( $actor, $activity_json['object']['actor'] );

		// Clean up.
		wp_delete_post( $outbox_post->ID, true );
	}
}
