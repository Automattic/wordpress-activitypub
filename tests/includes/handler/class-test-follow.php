<?php
/**
 * Test file for Follow handler.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Handler;

use Activitypub\Collection\Actors;
use Activitypub\Collection\Followers;
use Activitypub\Collection\Outbox;
use Activitypub\Handler\Follow;

/**
 * Test class for Follow handler.
 *
 * @coversDefaultClass \Activitypub\Handler\Follow
 */
class Test_Follow extends \WP_UnitTestCase {
	/**
	 * Test user ID.
	 *
	 * @var int
	 */
	protected static $user_id;

	/**
	 * Create fake data before tests run.
	 *
	 * @param \WP_UnitTest_Factory $factory Helper that creates fake data.
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
	 * Test handle_follow method.
	 *
	 * @covers ::handle_follow
	 */
	public function test_handle_follow() {
		$local_actor     = Actors::get_by_id( Actors::APPLICATION_USER_ID );
		$actor           = 'https://example.com/actor';
		$activity_object = array(
			'id'     => 'https://example.com/activity/123',
			'type'   => 'Follow',
			'actor'  => $actor,
			'object' => $local_actor->get_id(),
		);

		Follow::handle_follow( $activity_object, Actors::APPLICATION_USER_ID );

		$outbox_posts = \get_posts(
			array(
				'post_type'   => Outbox::POST_TYPE,
				'post_status' => 'pending',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_query'  => array(
					array(
						'key'   => '_activitypub_activity_type',
						'value' => 'Accept',
					),
				),
			)
		);
		$this->assertEmpty( $outbox_posts );

		$outbox_posts = \get_posts(
			array(
				'post_type'   => Outbox::POST_TYPE,
				'post_status' => 'pending',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_query'  => array(
					array(
						'key'   => '_activitypub_activity_type',
						'value' => 'Reject',
					),
				),
			)
		);
		$this->assertNotEmpty( $outbox_posts );

		_delete_all_posts();
	}

	/**
	 * Test queue_accept method.
	 *
	 * @covers ::queue_accept
	 */
	public function test_queue_accept() {
		$local_actor     = Actors::get_by_id( self::$user_id );
		$actor           = 'https://example.com/actor';
		$activity_object = array(
			'id'     => 'https://example.com/activity/123',
			'type'   => 'Follow',
			'actor'  => $actor,
			'object' => $local_actor->get_id(),
		);

		// Test with WP_Error follower - should not create outbox entry.
		$wp_error = new \WP_Error( 'test_error', 'Test Error' );
		Follow::queue_accept( $actor, $activity_object, self::$user_id, $wp_error );

		$outbox_posts = \get_posts(
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

		\add_filter(
			'pre_get_remote_metadata_by_actor',
			function () use ( $actor ) {
				return array(
					'id'    => $actor,
					'actor' => $actor,
					'type'  => 'Person',
					'inbox' => 'https://example.com/inbox',
				);
			}
		);

		$remote_actor = Followers::add_follower(
			self::$user_id,
			$activity_object['actor']
		);
		$remote_actor = \get_post( $remote_actor );

		Follow::queue_accept( $actor, $activity_object, self::$user_id, $remote_actor );

		$outbox_posts = \get_posts(
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
		$this->assertEquals( $local_actor->get_id(), $activity_json['object']['object'] );
		$this->assertEquals( array( $actor ), $activity_json['to'] );
		$this->assertEquals( $actor, $activity_json['object']['actor'] );
		$this->assertEquals( $local_actor->get_id(), $activity_json['actor'] );

		// Clean up.
		wp_delete_post( $outbox_post->ID, true );
		wp_delete_post( $remote_actor->ID, true );
		remove_all_filters( 'pre_get_remote_metadata_by_actor' );
	}
}
