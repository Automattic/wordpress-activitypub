<?php
/**
 * Test Comment scheduler class.
 *
 * @package Activitypub\Tests\Scheduler
 */

namespace Activitypub\Tests\Scheduler;

use Activitypub\Collection\Outbox;
use Activitypub\Comment;

/**
 * Test Comment scheduler class.
 *
 * @coversDefaultClass \Activitypub\Scheduler\Comment
 */
class Test_Comment extends \Activitypub\Tests\ActivityPub_Outbox_TestCase {

	/**
	 * Post ID for testing.
	 *
	 * @var int
	 */
	protected static $comment_post_ID;

	/**
	 * Set up test resources.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		self::$comment_post_ID = self::factory()->post->create( array( 'post_author' => self::$user_id ) );
	}

	/**
	 * Test scheduling comment activity on approval.
	 */
	public function test_schedule_comment_activity_on_approval() {
		$comment_id    = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$comment_post_ID,
				'user_id'          => self::$user_id,
				'comment_approved' => 0,
			)
		);
		$activitpub_id = Comment::generate_id( $comment_id );

		wp_set_comment_status( $comment_id, 'approve' );

		$post = $this->get_latest_outbox_item( $activitpub_id );
		$id   = \get_post_meta( $post->ID, '_activitypub_object_id', true );
		$this->assertSame( $activitpub_id, $id );

		\wp_delete_comment( $comment_id, true );
	}

	/**
	 * Test scheduling comment activity on direct insert with approval.
	 */
	public function test_schedule_comment_activity_on_insert() {
		$comment_id    = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$comment_post_ID,
				'user_id'          => self::$user_id,
				'comment_approved' => 1,
			)
		);
		$activitpub_id = Comment::generate_id( $comment_id );

		$post = $this->get_latest_outbox_item( $activitpub_id );
		$id   = \get_post_meta( $post->ID, '_activitypub_object_id', true );
		$this->assertSame( $activitpub_id, $id );

		\wp_delete_comment( $comment_id, true );
	}

	/**
	 * Data provider for no activity tests.
	 *
	 * @return array[] Test parameters.
	 */
	public function no_activity_comment_provider() {
		return array(
			'unapproved_comment'  => array(
				array(
					'comment_post_ID'  => self::$comment_post_ID,
					'user_id'          => self::$user_id,
					'comment_approved' => 0,
				),
			),
			'non_registered_user' => array(
				array(
					'comment_post_ID'  => self::$comment_post_ID,
					'comment_approved' => 1,
				),
			),
			'federation_disabled' => array(
				array(
					'comment_post_ID'  => self::$comment_post_ID,
					'user_id'          => self::$user_id,
					'comment_approved' => 1,
					'comment_meta'     => array(
						'protocol' => 'activitypub',
					),
				),
			),
		);
	}

	/**
	 * Test comment activity scheduling under various conditions.
	 *
	 * @dataProvider no_activity_comment_provider
	 *
	 * @param array $comment_data   Comment data for creating the test comment.
	 */
	public function test_no_activity_scheduled( $comment_data ) {
		foreach ( array( 'comment_post_ID', 'user_id' ) as $key ) {
			if ( isset( $comment_data[ $key ] ) ) {
				$comment_data[ $key ] = self::$$key;
			}
		}

		$comment_id    = self::factory()->comment->create( $comment_data );
		$activitpub_id = Comment::generate_id( $comment_id );

		$this->assertNull( $this->get_latest_outbox_item( $activitpub_id ) );

		\wp_delete_comment( $comment_id, true );
	}

	/**
	 * Test scheduling Delete activity when comment is permanently deleted.
	 *
	 * @covers ::schedule_comment_delete_activity
	 */
	public function test_schedule_comment_delete_activity() {
		// Create a comment that gets federated.
		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$comment_post_ID,
				'user_id'          => self::$user_id,
				'comment_approved' => 1,
				'comment_meta'     => array(
					'activitypub_status' => 'federated',
				),
			)
		);

		$activitypub_id = Comment::generate_id( $comment_id );

		// Permanently delete the comment - this should trigger a Delete activity.
		\wp_delete_comment( $comment_id, true );

		// Check if a Delete activity was created.
		$outbox_posts = \get_posts(
			array(
				'post_type'   => Outbox::POST_TYPE,
				'post_status' => array( 'publish', 'draft', 'pending', 'private' ),
				'numberposts' => 1,
				'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => '_activitypub_object_id',
						'value' => $activitypub_id,
					),
					array(
						'key'   => '_activitypub_activity_type',
						'value' => 'Delete',
					),
				),
			)
		);

		$this->assertCount( 1, $outbox_posts, 'Should create exactly one Delete activity for permanently deleted federated comment' );
		$outbox_post = $outbox_posts[0];

		// Verify the outbox post has correct metadata.
		$this->assertEquals( 'Delete', \get_post_meta( $outbox_post->ID, '_activitypub_activity_type', true ) );
		$this->assertEquals( $activitypub_id, \get_post_meta( $outbox_post->ID, '_activitypub_object_id', true ) );
		$this->assertEquals( self::$user_id, $outbox_post->post_author );
	}

	/**
	 * Test that non-federated comments don't create Delete activities.
	 *
	 * @covers ::schedule_comment_delete_activity
	 */
	public function test_no_delete_activity_for_non_federated_comment() {
		// Create a comment that was NOT federated.
		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$comment_post_ID,
				'user_id'          => self::$user_id,
				'comment_approved' => 1,
			)
		);

		$activitypub_id = Comment::generate_id( $comment_id );

		// Ensure this comment is NOT marked as sent.
		\delete_comment_meta( $comment_id, 'activitypub_status' );

		// Permanently delete the comment.
		\wp_delete_comment( $comment_id, true );

		// Check that no Delete activity was created for this specific comment.
		$outbox_posts = \get_posts(
			array(
				'post_type'   => Outbox::POST_TYPE,
				'post_status' => array( 'publish', 'draft', 'pending', 'private' ),
				'numberposts' => 1,
				'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => '_activitypub_object_id',
						'value' => $activitypub_id,
					),
					array(
						'key'   => '_activitypub_activity_type',
						'value' => 'Delete',
					),
				),
			)
		);

		$this->assertEmpty( $outbox_posts, 'Should not create Delete activity for non-federated comment deletion' );
	}

	/**
	 * Test that no announce is created when not in Blog and User mode.
	 *
	 * @covers ::maybe_announce_interaction
	 */
	public function test_no_announce_when_not_in_blog_and_user_mode() {
		$post_id = self::factory()->post->create( array( 'post_author' => self::$user_id ) );

		// Set actor mode to User only mode.
		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_MODE );

		$activity = array(
			'type'   => 'Create',
			'actor'  => 'https://example.com/users/testuser',
			'object' => array(
				'type'    => 'Note',
				'content' => 'Test comment content',
			),
		);

		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $post_id,
				'comment_approved' => 0,
				'comment_meta'     => array(
					'protocol'              => 'activitypub',
					'_activitypub_activity' => $activity,
				),
			)
		);

		// Get count of Announce activities before approval.
		$before_count = \count(
			\get_posts(
				array(
					'post_type'   => Outbox::POST_TYPE,
					'numberposts' => -1,
					'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						array(
							'key'   => '_activitypub_activity_type',
							'value' => 'Announce',
						),
					),
				)
			)
		);

		\wp_set_comment_status( $comment_id, 'approve' );

		// Get count after approval.
		$after_count = \count(
			\get_posts(
				array(
					'post_type'   => Outbox::POST_TYPE,
					'numberposts' => -1,
					'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						array(
							'key'   => '_activitypub_activity_type',
							'value' => 'Announce',
						),
					),
				)
			)
		);

		$this->assertEquals( $before_count, $after_count, 'Should not create Announce when not in Blog and User mode' );

		\wp_delete_comment( $comment_id, true );
	}

	/**
	 * Test that no announce is created for non-received comments.
	 *
	 * @covers ::maybe_announce_interaction
	 */
	public function test_no_announce_for_non_received_comments() {
		$post_id = self::factory()->post->create( array( 'post_author' => self::$user_id ) );

		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_AND_BLOG_MODE );

		// Create a comment WITHOUT ActivityPub protocol meta (not received).
		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $post_id,
				'comment_approved' => 0,
			)
		);

		$before_count = \count(
			\get_posts(
				array(
					'post_type'   => Outbox::POST_TYPE,
					'numberposts' => -1,
					'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						array(
							'key'   => '_activitypub_activity_type',
							'value' => 'Announce',
						),
					),
				)
			)
		);

		\wp_set_comment_status( $comment_id, 'approve' );

		$after_count = \count(
			\get_posts(
				array(
					'post_type'   => Outbox::POST_TYPE,
					'numberposts' => -1,
					'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						array(
							'key'   => '_activitypub_activity_type',
							'value' => 'Announce',
						),
					),
				)
			)
		);

		$this->assertEquals( $before_count, $after_count, 'Should not create Announce for non-received comments' );

		\wp_delete_comment( $comment_id, true );
	}

	/**
	 * Test that no announce is created when activity data is missing.
	 *
	 * @covers ::maybe_announce_interaction
	 */
	public function test_no_announce_when_activity_data_missing() {
		$post_id = self::factory()->post->create( array( 'post_author' => self::$user_id ) );

		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_AND_BLOG_MODE );

		// Create a comment with protocol meta but no activity data.
		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $post_id,
				'comment_approved' => 0,
				'comment_meta'     => array(
					'protocol' => 'activitypub',
				),
			)
		);

		$before_count = \count(
			\get_posts(
				array(
					'post_type'   => Outbox::POST_TYPE,
					'numberposts' => -1,
					'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						array(
							'key'   => '_activitypub_activity_type',
							'value' => 'Announce',
						),
					),
				)
			)
		);

		\wp_set_comment_status( $comment_id, 'approve' );

		$after_count = \count(
			\get_posts(
				array(
					'post_type'   => Outbox::POST_TYPE,
					'numberposts' => -1,
					'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						array(
							'key'   => '_activitypub_activity_type',
							'value' => 'Announce',
						),
					),
				)
			)
		);

		$this->assertEquals( $before_count, $after_count, 'Should not create Announce when activity data is missing' );

		\wp_delete_comment( $comment_id, true );
	}

	/**
	 * Test that no announce is created when activity is malformed.
	 *
	 * @covers ::maybe_announce_interaction
	 */
	public function test_no_announce_when_activity_malformed() {
		$post_id = self::factory()->post->create( array( 'post_author' => self::$user_id ) );

		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_AND_BLOG_MODE );

		// Create a comment with malformed activity (missing 'object' field).
		$malformed_activity = array(
			'type'  => 'Create',
			'actor' => 'https://example.com/users/testuser',
		);

		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $post_id,
				'comment_approved' => 0,
				'comment_meta'     => array(
					'protocol'              => 'activitypub',
					'_activitypub_activity' => $malformed_activity,
				),
			)
		);

		$before_count = \count(
			\get_posts(
				array(
					'post_type'   => Outbox::POST_TYPE,
					'numberposts' => -1,
					'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						array(
							'key'   => '_activitypub_activity_type',
							'value' => 'Announce',
						),
					),
				)
			)
		);

		\wp_set_comment_status( $comment_id, 'approve' );

		$after_count = \count(
			\get_posts(
				array(
					'post_type'   => Outbox::POST_TYPE,
					'numberposts' => -1,
					'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						array(
							'key'   => '_activitypub_activity_type',
							'value' => 'Announce',
						),
					),
				)
			)
		);

		$this->assertEquals( $before_count, $after_count, 'Should not create Announce when activity is malformed' );

		\wp_delete_comment( $comment_id, true );
	}
}
