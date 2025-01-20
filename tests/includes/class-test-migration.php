<?php
/**
 * Test file for Activitypub Migrate.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

use Activitypub\Migration;
use Activitypub\Comment;

/**
 * Test class for Activitypub Migrate.
 *
 * @coversDefaultClass \Activitypub\Migration
 */
class Test_Migration extends ActivityPub_TestCase_Cache_HTTP {

	/**
	 * Set up the test.
	 */
	public static function set_up_before_class() {
		\remove_action( 'transition_post_status', array( \Activitypub\Scheduler\Post::class, 'schedule_post_activity' ), 33 );
		\remove_action( 'transition_comment_status', array( \Activitypub\Scheduler\Comment::class, 'schedule_comment_activity' ), 20 );
		\remove_action( 'wp_insert_comment', array( \Activitypub\Scheduler\Comment::class, 'schedule_comment_activity_on_insert' ) );
	}
	/**
	 * Tear down the test.
	 */
	public function tear_down() {
		\delete_option( 'activitypub_object_type' );
		\delete_option( 'activitypub_custom_post_content' );
		\delete_option( 'activitypub_post_content_type' );
	}

	/**
	 * Test migrate actor mode.
	 *
	 * @covers ::migrate_actor_mode
	 */
	public function test_migrate_actor_mode() {
		\delete_option( 'activitypub_actor_mode' );

		Migration::migrate_actor_mode();

		$this->assertEquals( ACTIVITYPUB_ACTOR_MODE, \get_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_MODE ) );

		\update_option( 'activitypub_enable_blog_user', '0' );
		\update_option( 'activitypub_enable_users', '1' );
		\delete_option( 'activitypub_actor_mode' );

		Migration::migrate_actor_mode();

		$this->assertEquals( ACTIVITYPUB_ACTOR_MODE, \get_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_MODE ) );

		\update_option( 'activitypub_enable_blog_user', '1' );
		\update_option( 'activitypub_enable_users', '1' );
		\delete_option( 'activitypub_actor_mode' );

		Migration::migrate_actor_mode();

		$this->assertEquals( ACTIVITYPUB_ACTOR_AND_BLOG_MODE, \get_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_MODE ) );

		\update_option( 'activitypub_enable_blog_user', '1' );
		\update_option( 'activitypub_enable_users', '0' );
		\delete_option( 'activitypub_actor_mode' );

		Migration::migrate_actor_mode();

		$this->assertEquals( ACTIVITYPUB_BLOG_MODE, \get_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_MODE ) );

		\delete_option( 'activitypub_enable_blog_user' );
		\update_option( 'activitypub_enable_users', '0' );
		\delete_option( 'activitypub_actor_mode' );

		Migration::migrate_actor_mode();

		$this->assertEquals( ACTIVITYPUB_ACTOR_MODE, \get_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_MODE ) );

		\update_option( 'activitypub_enable_blog_user', '0' );
		\delete_option( 'activitypub_enable_users' );
		\delete_option( 'activitypub_actor_mode' );

		Migration::migrate_actor_mode();

		$this->assertEquals( ACTIVITYPUB_ACTOR_MODE, \get_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_MODE ) );
	}

	/**
	 * Test migrate to 4.1.0.
	 *
	 * @covers ::migrate_to_4_1_0
	 */
	public function test_migrate_to_4_1_0() {
		$post1 = \wp_insert_post(
			array(
				'post_author'  => 1,
				'post_content' => 'activitypub_content_visibility test',
			)
		);

		$post2 = \wp_insert_post(
			array(
				'post_author'  => 1,
				'post_content' => 'activitypub_content_visibility test',
			)
		);

		\update_post_meta( $post1, 'activitypub_content_visibility', '' );
		\update_post_meta( $post1, 'activitypub_content_123', '456' );
		\update_post_meta( $post2, 'activitypub_content_visibility', 'local' );
		\update_post_meta( $post2, 'activitypub_content_123', '' );

		$metas1 = \get_post_meta( $post1 );

		$this->assertEquals(
			array(
				'activitypub_content_visibility' => array( '' ),
				'activitypub_content_123'        => array( '456' ),
			),
			$metas1
		);

		$metas2 = \get_post_meta( $post2 );

		$this->assertEquals(
			array(
				'activitypub_content_visibility' => array( 'local' ),
				'activitypub_content_123'        => array( '' ),
			),
			$metas2
		);

		$template    = \get_option( 'activitypub_custom_post_content', ACTIVITYPUB_CUSTOM_POST_CONTENT );
		$object_type = \get_option( 'activitypub_object_type', ACTIVITYPUB_DEFAULT_OBJECT_TYPE );

		$this->assertEquals( ACTIVITYPUB_CUSTOM_POST_CONTENT, $template );
		$this->assertEquals( ACTIVITYPUB_DEFAULT_OBJECT_TYPE, $object_type );

		\update_option( 'activitypub_post_content_type', 'title' );

		Migration::migrate_to_4_1_0();

		\clean_post_cache( $post1 );
		$metas1 = \get_post_meta( $post1 );
		$this->assertEquals(
			array(
				'activitypub_content_123' => array( '456' ),
			),
			$metas1
		);

		\clean_post_cache( $post2 );
		$metas2 = \get_post_meta( $post2 );
		$this->assertEquals(
			array(
				'activitypub_content_visibility' => array( 'local' ),
				'activitypub_content_123'        => array( '' ),
			),
			$metas2
		);

		$template     = \get_option( 'activitypub_custom_post_content' );
		$content_type = \get_option( 'activitypub_post_content_type' );
		$object_type  = \get_option( 'activitypub_object_type' );

		$this->assertEquals( "[ap_title type=\"html\"]\n\n[ap_permalink type=\"html\"]", $template );
		$this->assertFalse( $content_type );
		$this->assertEquals( 'note', $object_type );

		\update_option( 'activitypub_post_content_type', 'content' );
		\update_option( 'activitypub_custom_post_content', '[ap_content]' );

		Migration::migrate_to_4_1_0();

		$template     = \get_option( 'activitypub_custom_post_content' );
		$content_type = \get_option( 'activitypub_post_content_type' );

		$this->assertEquals( "[ap_content]\n\n[ap_permalink type=\"html\"]\n\n[ap_hashtags]", $template );
		$this->assertFalse( $content_type );

		$custom = '[ap_title] [ap_content] [ap_hashcats] [ap_authorurl]';

		\update_option( 'activitypub_post_content_type', 'custom' );
		\update_option( 'activitypub_custom_post_content', $custom );

		Migration::migrate_to_4_1_0();

		$template     = \get_option( 'activitypub_custom_post_content' );
		$content_type = \get_option( 'activitypub_post_content_type' );

		$this->assertEquals( $custom, $template );
		$this->assertFalse( $content_type );

		\wp_delete_post( $post1, true );
		\wp_delete_post( $post2, true );
	}

	/**
	 * Test migrate to 4.7.1.
	 *
	 * @covers ::migrate_to_4_7_1
	 */
	public function test_migrate_to_4_7_1() {
		$post1 = \wp_insert_post(
			array(
				'post_author'  => 1,
				'post_content' => 'Test post 1',
			)
		);

		$post2 = \wp_insert_post(
			array(
				'post_author'  => 1,
				'post_content' => 'Test post 2',
			)
		);

		// Set up test meta data.
		$meta_data = array(
			'activitypub_actor_json'    => '{"type":"Person"}',
			'activitypub_canonical_url' => 'https://example.com/post-1',
			'activitypub_errors'        => 'Test error',
			'activitypub_inbox'         => 'https://example.com/inbox',
			'activitypub_user_id'       => '123',
			'unrelated_meta'            => 'should not change',
		);

		foreach ( $meta_data as $key => $value ) {
			\update_post_meta( $post1, $key, $value );
			\update_post_meta( $post2, $key, $value . '-2' );
		}

		// Run migration.
		Migration::migrate_to_4_7_1();

		// Clean post cache to ensure fresh meta data.
		\clean_post_cache( $post1 );
		\clean_post_cache( $post2 );

		// Check post 1 meta.
		$this->assertEmpty( \get_post_meta( $post1, 'activitypub_actor_json', true ), 'Old actor_json meta should be empty' );
		$this->assertEmpty( \get_post_meta( $post1, 'activitypub_canonical_url', true ), 'Old canonical_url meta should be empty' );
		$this->assertEmpty( \get_post_meta( $post1, 'activitypub_errors', true ), 'Old errors meta should be empty' );
		$this->assertEmpty( \get_post_meta( $post1, 'activitypub_inbox', true ), 'Old inbox meta should be empty' );
		$this->assertEmpty( \get_post_meta( $post1, 'activitypub_user_id', true ), 'Old user_id meta should be empty' );

		$this->assertEquals( '{"type":"Person"}', \get_post_meta( $post1, '_activitypub_actor_json', true ), 'New actor_json meta should match' );
		$this->assertEquals( 'https://example.com/post-1', \get_post_meta( $post1, '_activitypub_canonical_url', true ), 'New canonical_url meta should match' );
		$this->assertEquals( 'Test error', \get_post_meta( $post1, '_activitypub_errors', true ), 'New errors meta should match' );
		$this->assertEquals( 'https://example.com/inbox', \get_post_meta( $post1, '_activitypub_inbox', true ), 'New inbox meta should match' );
		$this->assertEquals( '123', \get_post_meta( $post1, '_activitypub_user_id', true ), 'New user_id meta should match' );

		// Check post 2 meta.
		$this->assertEmpty( \get_post_meta( $post2, 'activitypub_actor_json', true ), 'Old actor_json meta should be empty' );
		$this->assertEmpty( \get_post_meta( $post2, 'activitypub_canonical_url', true ), 'Old canonical_url meta should be empty' );
		$this->assertEmpty( \get_post_meta( $post2, 'activitypub_errors', true ), 'Old errors meta should be empty' );
		$this->assertEmpty( \get_post_meta( $post2, 'activitypub_inbox', true ), 'Old inbox meta should be empty' );
		$this->assertEmpty( \get_post_meta( $post2, 'activitypub_user_id', true ), 'Old user_id meta should be empty' );

		$this->assertEquals( '{"type":"Person"}-2', \get_post_meta( $post2, '_activitypub_actor_json', true ), 'New actor_json meta should match' );
		$this->assertEquals( 'https://example.com/post-1-2', \get_post_meta( $post2, '_activitypub_canonical_url', true ), 'New canonical_url meta should match' );
		$this->assertEquals( 'Test error-2', \get_post_meta( $post2, '_activitypub_errors', true ), 'New errors meta should match' );
		$this->assertEquals( 'https://example.com/inbox-2', \get_post_meta( $post2, '_activitypub_inbox', true ), 'New inbox meta should match' );
		$this->assertEquals( '123-2', \get_post_meta( $post2, '_activitypub_user_id', true ), 'New user_id meta should match' );

		// Verify unrelated meta is unchanged.
		$this->assertEquals( 'should not change', \get_post_meta( $post1, 'unrelated_meta', true ), 'Unrelated meta should not change' );
		$this->assertEquals( 'should not change-2', \get_post_meta( $post2, 'unrelated_meta', true ), 'Unrelated meta should not change' );

		\wp_delete_post( $post1, true );
		\wp_delete_post( $post2, true );
	}

	/**
	 * Tests that a new migration lock can be successfully acquired when no lock exists.
	 *
	 * @covers ::lock
	 */
	public function test_lock_acquire_new() {
		$this->assertFalse( get_option( 'activitypub_migration_lock' ) );

		$this->assertTrue( Migration::lock() );

		// Clean up.
		delete_option( 'activitypub_migration_lock' );
	}

	/**
	 * Test retrieving the timestamp of an existing lock.
	 *
	 * @covers ::lock
	 */
	public function test_lock_get_existing() {
		$lock_time = time() - MINUTE_IN_SECONDS; // Set lock to 1 minute ago.
		update_option( 'activitypub_migration_lock', $lock_time );

		$lock_result = Migration::lock();

		$this->assertEquals( $lock_time, $lock_result );

		// Clean up.
		delete_option( 'activitypub_migration_lock' );
	}

	/**
	 * Test update_comment_counts() properly cleans up the lock.
	 *
	 * @covers ::update_comment_counts
	 */
	public function test_update_comment_counts_with_lock() {
		// Register comment types.
		Comment::register_comment_types();

		// Create test comments.
		$post_id    = $this->factory->post->create();
		$comment_id = $this->factory->comment->create(
			array(
				'comment_post_ID'  => $post_id,
				'comment_approved' => '1',
				'comment_type'     => 'repost', // One of the registered comment types.
			)
		);

		Migration::update_comment_counts( 10, 0 );

		// Verify lock was cleaned up.
		$this->assertFalse( get_option( 'activitypub_migration_lock' ) );

		// Clean up.
		wp_delete_comment( $comment_id, true );
		wp_delete_post( $post_id, true );
	}

	/**
	 * Test update_comment_counts() with existing valid lock.
	 *
	 * @covers ::update_comment_counts
	 */
	public function test_update_comment_counts_with_existing_valid_lock() {
		// Register comment types.
		Comment::register_comment_types();

		// Set a lock.
		Migration::lock();

		Migration::update_comment_counts( 10, 0 );

		// Verify a scheduled event was created.
		$next_scheduled = wp_next_scheduled(
			'activitypub_update_comment_counts',
			array(
				'batch_size' => 10,
				'offset'     => 0,
			)
		);
		$this->assertNotFalse( $next_scheduled );

		// Clean up.
		delete_option( 'activitypub_migration_lock' );
		wp_clear_scheduled_hook(
			'activitypub_update_comment_counts',
			array(
				'batch_size' => 10,
				'offset'     => 0,
			)
		);
	}

	/**
	 * Test create outbox items.
	 *
	 * @covers ::create_outbox_items
	 */
	public function test_create_outbox_items() {
		// Create test posts.
		$post1 = self::factory()->post->create(
			array(
				'post_author'  => 1,
				'post_content' => 'Test post 1',
				'post_status'  => 'publish',
				'post_type'    => 'post',
			)
		);

		$post2 = self::factory()->post->create(
			array(
				'post_author'  => 1,
				'post_content' => 'Test post 2',
				'post_status'  => 'publish',
				'post_type'    => 'post',
				'post_date'    => '2020-01-01 00:00:00',
			)
		);
		self::factory()->post->update_object( $post2, array( 'post_content' => 'Test post 2 updated' ) );

		$post3 = self::factory()->post->create(
			array(
				'post_author'  => 1,
				'post_content' => 'Test post 3',
				'post_status'  => 'publish',
				'post_type'    => 'page',
			)
		);

		$post4 = self::factory()->post->create(
			array(
				'post_author'  => 1,
				'post_content' => 'Test post 4',
				'post_status'  => 'publish',
				'post_type'    => 'post',
				'meta_input'   => array(
					'activitypub_content_visibility' => ACTIVITYPUB_CONTENT_VISIBILITY_LOCAL,
				),
			)
		);

		// Create test comment.
		$comment = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $post1,
				'user_id'          => 1,
				'comment_content'  => 'Test comment',
				'comment_approved' => '1',
			)
		);

		// Run migration.
		add_filter( 'pre_schedule_event', '__return_false' );
		Migration::create_outbox_items( 10, 0 );
		remove_filter( 'pre_schedule_event', '__return_false' );

		// Get outbox items.
		$outbox_items = \get_posts(
			array(
				'post_type'      => 'ap_outbox',
				'posts_per_page' => -1,
				'post_status'    => 'draft',
			)
		);

		// Should have 4 outbox items: 1 for post1 (create), 2 for post2 (create + update), none for post3 (wrong type), none for post4 (private), and 1 for comment.
		$this->assertEquals( 4, count( $outbox_items ) );

		// Verify first post create activity.
		$this->assertEquals( 'Create', \get_post_meta( $outbox_items[0]->ID, '_activitypub_activity_type', true ) );
		$this->assertEquals( '', \get_post_meta( $outbox_items[0]->ID, 'activitypub_content_visibility', true ) );

		// Verify second post update activity.
		$this->assertEquals( 'Update', \get_post_meta( $outbox_items[1]->ID, '_activitypub_activity_type', true ) );
		$this->assertEquals( '', \get_post_meta( $outbox_items[1]->ID, 'activitypub_content_visibility', true ) );

		// Verify second post create activity.
		$this->assertEquals( 'Create', \get_post_meta( $outbox_items[2]->ID, '_activitypub_activity_type', true ) );
		$this->assertEquals( '', \get_post_meta( $outbox_items[2]->ID, 'activitypub_content_visibility', true ) );

		// Verify comment create activity.
		$this->assertEquals( 'Create', \get_post_meta( $outbox_items[3]->ID, '_activitypub_activity_type', true ) );
		$this->assertEquals( '', \get_post_meta( $outbox_items[3]->ID, 'activitypub_content_visibility', true ) );

		// Clean up.
		\wp_delete_post( $post1, true );
		\wp_delete_post( $post2, true );
		\wp_delete_post( $post3, true );
		\wp_delete_post( $post4, true );
		\wp_delete_comment( $comment, true );
		foreach ( $outbox_items as $item ) {
			\wp_delete_post( $item->ID, true );
		}
	}

	/**
	 * Test create outbox items with batching.
	 *
	 * @covers ::create_outbox_items
	 */
	public function test_create_outbox_items_batching() {
		// Create 3 test posts.
		$posts = self::factory()->post->create_many(
			3,
			array(
				'post_author' => 1,
				'post_status' => 'publish',
				'post_type'   => 'post',
			)
		);

		// Run migration with batch size of 2.
		Migration::create_outbox_items( 2, 0 );

		// Get outbox items.
		$outbox_items = \get_posts(
			array(
				'post_type'      => 'ap_outbox',
				'posts_per_page' => -1,
				'post_status'    => 'draft',
			)
		);

		// Should have 2 outbox items (batch size).
		$this->assertEquals( 2, count( $outbox_items ) );

		// Run migration with next batch.
		Migration::create_outbox_items( 2, 2 );

		// Get outbox items again.
		$outbox_items = \get_posts(
			array(
				'post_type'      => 'ap_outbox',
				'posts_per_page' => -1,
				'post_status'    => 'draft',
			)
		);

		// Should now have 3 outbox items total.
		$this->assertEquals( 3, count( $outbox_items ) );

		// Clean up.
		foreach ( $posts as $post ) {
			\wp_delete_post( $post, true );
		}
		foreach ( $outbox_items as $item ) {
			\wp_delete_post( $item->ID, true );
		}
	}

	/**
	 * Test create outbox items with locking.
	 *
	 * @covers ::create_outbox_items
	 */
	public function test_create_outbox_items_locking() {
		// Create a lock.
		Migration::lock();

		// Create test post.
		$post = self::factory()->post->create(
			array(
				'post_author'  => 1,
				'post_content' => 'Test post',
				'post_status'  => 'publish',
				'post_type'    => 'post',
			)
		);

		// Run migration.
		Migration::create_outbox_items();

		// Get outbox items.
		$outbox_items = \get_posts(
			array(
				'post_type'      => 'ap_outbox',
				'posts_per_page' => -1,
			)
		);

		// Should have no outbox items due to lock.
		$this->assertEquals( 0, count( $outbox_items ) );

		// Clean up.
		Migration::unlock();
		\wp_delete_post( $post, true );
	}
}
