<?php
/**
 * Test file for Activitypub Migrate.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

use Activitypub\Collection\Outbox;
use Activitypub\Migration;
use Activitypub\Comment;

/**
 * Test class for Activitypub Migrate.
 *
 * @coversDefaultClass \Activitypub\Migration
 */
class Test_Migration extends ActivityPub_TestCase_Cache_HTTP {

	/**
	 * Test fixture.
	 *
	 * @var array
	 */
	public static $fixtures = array();

	/**
	 * Set up the test.
	 */
	public static function set_up_before_class() {
		\remove_action( 'wp_after_insert_post', array( \Activitypub\Scheduler\Post::class, 'schedule_post_activity' ), 33 );
		\remove_action( 'transition_comment_status', array( \Activitypub\Scheduler\Comment::class, 'schedule_comment_activity' ), 20 );
		\remove_action( 'wp_insert_comment', array( \Activitypub\Scheduler\Comment::class, 'schedule_comment_activity_on_insert' ) );

		// Create test posts.
		self::$fixtures['posts'] = self::factory()->post->create_many(
			3,
			array(
				'post_author' => 1,
				'meta_input'  => array( 'activitypub_status' => 'federated' ),
			)
		);

		$modified_post_id = self::factory()->post->create(
			array(
				'post_author'  => 1,
				'post_content' => 'Test post 2',
				'post_status'  => 'publish',
				'post_type'    => 'post',
				'post_date'    => '2020-01-01 00:00:00',
				'meta_input'   => array( 'activitypub_status' => 'federated' ),
			)
		);
		self::factory()->post->update_object( $modified_post_id, array( 'post_content' => 'Test post 2 updated' ) );

		self::$fixtures['posts'][] = $modified_post_id;
		self::$fixtures['posts'][] = self::factory()->post->create(
			array(
				'post_author'  => 1,
				'post_content' => 'Test post 3',
				'post_status'  => 'publish',
				'post_type'    => 'page',
			)
		);
		self::$fixtures['posts'][] = self::factory()->post->create(
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
		self::$fixtures['comment'] = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$fixtures['posts'][0],
				'user_id'          => 1,
				'comment_content'  => 'Test comment',
				'comment_approved' => '1',
			)
		);
		\add_comment_meta( self::$fixtures['comment'], 'activitypub_status', 'federated' );
	}

	/**
	 * Tear down the test.
	 */
	public static function tear_down_after_class() {
		// Clean up posts.
		foreach ( self::$fixtures['posts'] as $post_id ) {
			\wp_delete_post( $post_id, true );
		}

		// Clean up comment.
		if ( isset( self::$fixtures['comment'] ) ) {
			\wp_delete_comment( self::$fixtures['comment'], true );
		}
	}

	/**
	 * Tear down the test.
	 */
	public function tear_down() {
		\delete_option( 'activitypub_object_type' );
		\delete_option( 'activitypub_custom_post_content' );
		\delete_option( 'activitypub_post_content_type' );

		// Clean up outbox items.
		$outbox_items = \get_posts(
			array(
				'post_type'      => Outbox::POST_TYPE,
				'posts_per_page' => -1,
				'post_status'    => 'any',
				'fields'         => 'ids',
			)
		);

		foreach ( $outbox_items as $item_id ) {
			\wp_delete_post( $item_id, true );
		}
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
	 * Tests scheduling of migration.
	 *
	 * @covers ::maybe_migrate
	 */
	public function test_migration_scheduling() {
		update_option( 'activitypub_db_version', '0.0.1' );

		Migration::maybe_migrate();

		$schedule = \wp_next_scheduled( 'activitypub_migrate', array( '0.0.1' ) );
		$this->assertNotFalse( $schedule );

		// Clean up.
		delete_option( 'activitypub_db_version' );
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
				'activitypub_content_123' => array( '456' ),
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
		$post1 = self::$fixtures['posts'][0];
		$post2 = self::$fixtures['posts'][1];

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
		$post_id    = $this->factory->post->create(
			array(
				'post_author' => 1,
			)
		);
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
	 * Test create post outbox items.
	 *
	 * @covers ::create_post_outbox_items
	 */
	public function test_create_outbox_items() {
		// Create additional post that should not be included in outbox.
		$post_id = self::factory()->post->create( array( 'post_author' => 90210 ) );

		// Run migration.
		add_filter( 'pre_schedule_event', '__return_false' );
		Migration::create_post_outbox_items( 10, 0 );
		remove_filter( 'pre_schedule_event', '__return_false' );

		// Get outbox items.
		$outbox_items = \get_posts(
			array(
				'post_type'      => Outbox::POST_TYPE,
				'posts_per_page' => -1,
			)
		);

		// Should now have 5 outbox items total, 4 post Create, 1 post Update.
		$this->assertEquals( 5, count( $outbox_items ) );

		\wp_delete_post( $post_id, true );
	}

	/**
	 * Test create post outbox items with batching.
	 *
	 * @covers ::create_post_outbox_items
	 */
	public function test_create_outbox_items_batching() {
		// Run migration with batch size of 2.
		$next = Migration::create_post_outbox_items( 2, 0 );

		$this->assertSame(
			array(
				'batch_size' => 2,
				'offset'     => 2,
			),
			$next
		);

		// Get outbox items.
		$outbox_items = \get_posts(
			array(
				'post_type'      => Outbox::POST_TYPE,
				'posts_per_page' => -1,
			)
		);

		// Should have 2 outbox items.
		$this->assertEquals( 2, count( $outbox_items ) );

		// Run migration with next batch.
		Migration::create_post_outbox_items( 2, 2 );

		// Get outbox items again.
		$outbox_items = \get_posts(
			array(
				'post_type'      => Outbox::POST_TYPE,
				'posts_per_page' => -1,
			)
		);

		// Should now have 5 outbox items total, 4 post Create, 1 post Update.
		$this->assertEquals( 5, count( $outbox_items ) );
	}

	/**
	 * Test async upgrade functionality.
	 *
	 * @covers ::async_upgrade
	 * @covers ::lock
	 * @covers ::unlock
	 * @covers ::create_post_outbox_items
	 */
	public function test_async_upgrade() {
		// Test that lock prevents simultaneous upgrades.
		Migration::lock();
		Migration::async_upgrade( 'create_post_outbox_items' );
		$scheduled = \wp_next_scheduled( 'activitypub_upgrade', array( 'create_post_outbox_items' ) );
		$this->assertNotFalse( $scheduled );
		Migration::unlock();

		// Test scheduling next batch when callback returns more work.
		Migration::async_upgrade( 'create_post_outbox_items', 1, 0 ); // Small batch size to force multiple batches.
		$scheduled = \wp_next_scheduled( 'activitypub_upgrade', array( 'create_post_outbox_items', 1, 1 ) );
		$this->assertNotFalse( $scheduled );

		// Test no scheduling when callback returns null (no more work).
		Migration::async_upgrade( 'create_post_outbox_items', 100, 1000 ); // Large offset to ensure no posts found.
		$this->assertFalse(
			\wp_next_scheduled( 'activitypub_upgrade', array( 'create_post_outbox_items', 100, 1100 ) )
		);
	}

	/**
	 * Test async upgrade with multiple arguments.
	 *
	 * @covers ::async_upgrade
	 */
	public function test_async_upgrade_multiple_args() {
		// Test that multiple arguments are passed correctly.
		Migration::async_upgrade( 'update_comment_counts', 50, 100 );
		$scheduled = \wp_next_scheduled( 'activitypub_upgrade', array( 'update_comment_counts', 50, 150 ) );
		$this->assertFalse( $scheduled, 'Should not schedule next batch when no comments found' );
	}

	/**
	 * Test create_comment_outbox_items batch processing.
	 *
	 * @covers ::create_comment_outbox_items
	 */
	public function test_create_comment_outbox_items_batching() {
		// Test with small batch size.
		$result = Migration::create_comment_outbox_items( 1, 0 );
		$this->assertIsArray( $result );
		$this->assertEquals(
			array(
				'batch_size' => 1,
				'offset'     => 1,
			),
			$result
		);

		// Test with large offset (no more comments).
		$result = Migration::create_comment_outbox_items( 1, 1000 );
		$this->assertNull( $result );
	}
}
