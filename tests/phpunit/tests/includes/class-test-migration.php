<?php
/**
 * Test file for Activitypub Migrate.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

use Activitypub\Activity\Actor;
use Activitypub\Collection\Actors;
use Activitypub\Collection\Extra_Fields;
use Activitypub\Collection\Followers;
use Activitypub\Collection\Following;
use Activitypub\Collection\Outbox;
use Activitypub\Collection\Remote_Actors;
use Activitypub\Comment;
use Activitypub\Migration;
use Activitypub\Scheduler;

/**
 * Test class for Activitypub Migrate.
 *
 * @coversDefaultClass \Activitypub\Migration
 */
class Test_Migration extends \WP_UnitTestCase {

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
		// Mock Jetpack class if it doesn't exist.
		if ( ! class_exists( 'Jetpack' ) ) {
			require_once AP_TESTS_DIR . '/data/mocks/class-jetpack.php';
		}

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

		$custom = '[ap_title] [ap_content] [ap_authorurl]';

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
		$post_id    = self::factory()->post->create(
			array(
				'post_author' => 1,
			)
		);
		$comment_id = self::factory()->comment->create(
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
	 * Test async upgrade with multiple arguments.
	 *
	 * @covers ::update_comment_counts
	 * @covers \Activitypub\Scheduler::async_batch
	 */
	public function test_async_upgrade_multiple_args() {
		// Test that multiple arguments are passed correctly.
		Scheduler::async_batch( array( Migration::class, 'update_comment_counts' ), 50, 100 );
		$scheduled = \wp_next_scheduled( 'activitypub_async_batch', array( array( Migration::class, 'update_comment_counts' ), 50, 150 ) );
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

	/**
	 * Test update_actor_json_slashing updates unslashed meta values.
	 *
	 * @covers ::update_actor_json_slashing
	 */
	public function test_update_actor_json_slashing() {
		$follower = array(
			'id'                 => 'https://example.com/users/test',
			'type'               => 'Person',
			'name'               => 'Test Follower',
			'preferred_username' => 'Follower',
			'summary'            => '<p>unescaped backslash 04\2024</p>',
			'endpoints'          => array(
				'sharedInbox' => 'https://example.com/inbox',
			),
		);

		$post_id = Remote_Actors::upsert( $follower );

		\add_post_meta( $post_id, '_activitypub_actor_json', \wp_json_encode( $follower ) );

		$original_meta = \get_post_meta( $post_id, '_activitypub_actor_json', true );
		$this->assertNull( \json_decode( $original_meta, true ) );
		$this->assertEquals( JSON_ERROR_SYNTAX, \json_last_error() );

		$result = Migration::update_actor_json_slashing();

		// No additional batch should be scheduled.
		$this->assertNull( $result );

		$updated_meta = \get_post_meta( $post_id, '_activitypub_actor_json', true );

		// Verify the updated value can be successfully decoded.
		$decoded = \json_decode( $updated_meta, true );
		$this->assertNotNull( $decoded, 'Updated meta should be valid JSON' );
		$this->assertEquals( JSON_ERROR_NONE, \json_last_error() );
	}

	/**
	 * Test update_comment_author_emails updates emails with webfinger addresses.
	 *
	 * @covers ::update_comment_author_emails
	 */
	public function test_update_comment_author_emails() {
		$author_url = 'https://example.com/users/test';
		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID'      => self::$fixtures['posts'][0],
				'comment_author'       => 'Test User',
				'comment_author_url'   => $author_url,
				'comment_author_email' => '',
				'comment_type'         => 'comment',
				'comment_meta'         => array( 'protocol' => 'activitypub' ),
			)
		);

		// Mock the HTTP request.
		\add_filter( 'pre_http_request', array( $this, 'mock_webfinger' ) );

		$result = Migration::update_comment_author_emails( 50, 0 );

		$this->assertNull( $result );

		$updated_comment = \get_comment( $comment_id );
		$this->assertEquals( 'test@example.com', $updated_comment->comment_author_email );

		// Clean up.
		\remove_filter( 'pre_http_request', array( $this, 'mock_webfinger' ) );
		\wp_delete_comment( $comment_id, true );
	}

	/**
	 * Test update_comment_author_emails handles batching correctly.
	 *
	 * @covers ::update_comment_author_emails
	 */
	public function test_update_comment_author_emails_batching() {
		// Create multiple comments.
		$comment_ids = array();
		for ( $i = 0; $i < 3; $i++ ) {
			$comment_ids[] = self::factory()->comment->create(
				array(
					'comment_post_ID'      => self::$fixtures['posts'][0],
					'comment_author'       => "Test User $i",
					'comment_author_url'   => "https://example.com/users/test$i",
					'comment_author_email' => '',
					'comment_content'      => "Test comment $i",
					'comment_type'         => 'comment',
					'comment_meta'         => array( 'protocol' => 'activitypub' ),
				)
			);
		}

		// Mock the HTTP request.
		\add_filter( 'pre_http_request', array( $this, 'mock_webfinger' ) );

		// Process first batch of 2 comments.
		$result = Migration::update_comment_author_emails( 2, 0 );
		$this->assertEqualSets(
			array(
				'batch_size' => 2,
				'offset'     => 2,
			),
			$result
		);

		// Process second batch with remaining comment.
		$result = Migration::update_comment_author_emails( 2, 2 );
		$this->assertNull( $result );

		// Verify all comments were updated.
		foreach ( $comment_ids as $comment_id ) {
			$comment = \get_comment( $comment_id );
			$this->assertEquals( 'test@example.com', $comment->comment_author_email );

			wp_delete_comment( $comment_id, true );
		}

		_delete_all_data();
		\remove_filter( 'pre_http_request', array( $this, 'mock_webfinger' ) );
	}

	/**
	 * Mock webfinger response.
	 *
	 * @return array
	 */
	public function mock_webfinger() {
		return array(
			'body'     => wp_json_encode( array( 'subject' => 'acct:test@example.com' ) ),
			'response' => array( 'code' => 200 ),
		);
	}

	/**
	 * Test add_default_extra_field.
	 */
	public function test_add_default_extra_field() {
		// Create a test user with ActivityPub permission.
		$user_id = self::factory()->user->create();
		$user    = get_user_by( 'id', $user_id );
		$user->add_cap( 'activitypub' );

		// Run the private method over Reflection.
		$reflection = new \ReflectionClass( Migration::class );
		$method     = $reflection->getMethod( 'add_default_extra_field' );
		$method->setAccessible( true );
		$method->invoke( null );

		// Check the extra field for the user.
		$user_fields = get_posts(
			array(
				'post_type'      => Extra_Fields::USER_POST_TYPE,
				'author'         => $user_id,
				'posts_per_page' => -1,
			)
		);

		$this->assertCount( 1, $user_fields, 'There should be one extra field for the user' );
		$this->assertEquals( 'Powered by', $user_fields[0]->post_title, 'The title should be "Powered by"' );
		$this->assertEquals( 'WordPress', $user_fields[0]->post_content, 'The content should be "WordPress"' );

		// Check the extra field for the blog user.
		$blog_fields = get_posts(
			array(
				'post_type'      => Extra_Fields::BLOG_POST_TYPE,
				'author'         => 0,
				'posts_per_page' => -1,
			)
		);

		$this->assertCount( 1, $blog_fields, 'There should be one extra field for the blog user' );
		$this->assertEquals( 'Powered by', $blog_fields[0]->post_title, 'The title should be "Powered by"' );
		$this->assertEquals( 'WordPress', $blog_fields[0]->post_content, 'The content should be "WordPress"' );

		_delete_all_data();
	}

	/**
	 * Test add_default_extra_field with multiple users.
	 */
	public function test_add_default_extra_field_multiple_users() {
		// Create a user without ActivityPub permission.
		$non_ap_user_id = self::factory()->user->create();

		// Run the private method over Reflection.
		$reflection = new \ReflectionClass( Migration::class );
		$method     = $reflection->getMethod( 'add_default_extra_field' );
		$method->setAccessible( true );
		$method->invoke( null );

		// Check that the user without ActivityPub permission has no extra field.
		$non_ap_user_fields = get_posts(
			array(
				'post_type'      => Extra_Fields::USER_POST_TYPE,
				'author'         => $non_ap_user_id,
				'posts_per_page' => -1,
			)
		);

		$this->assertCount( 0, $non_ap_user_fields, 'User without ActivityPub permission should not have an extra field' );

		_delete_all_data();
	}

	/**
	 * Test update_notification_options.
	 *
	 * @covers ::update_notification_options
	 */
	public function test_update_notification_options() {
		// Set up test user with the ActivityPub capability.
		$user_id1 = self::factory()->user->create();

		// Add the ActivityPub capability to the test users.
		$user1 = get_user_by( 'id', $user_id1 );
		$user1->add_cap( 'activitypub' );

		// Set up the old notification options.
		\update_option( 'activitypub_mailer_new_dm', '1' );
		\update_option( 'activitypub_mailer_new_follower', '0' );
		\update_option( 'activitypub_mailer_new_mention', '1' ); // This one doesn't get migrated, just added.

		\delete_option( 'activitypub_blog_user_mailer_new_dm' );
		\delete_option( 'activitypub_blog_user_mailer_new_follower' );
		\delete_option( 'activitypub_blog_user_mailer_new_mention' );

		// Run the migration method.
		Migration::update_notification_options();

		// Verify blog user notification options were created with correct values.
		$this->assertEquals( '1', \get_option( 'activitypub_blog_user_mailer_new_dm' ), 'Blog user new DM option should match old value' );
		$this->assertEquals( '0', \get_option( 'activitypub_blog_user_mailer_new_follower' ), 'Blog user new follower option should match old value' );
		$this->assertEquals( '1', \get_option( 'activitypub_blog_user_mailer_new_mention' ), 'Blog user new mention option should be set to 1' );

		// Verify actor notification options were created with correct values.
		$this->assertEquals( '1', \get_user_option( 'activitypub_mailer_new_dm', $user_id1 ), 'Actor 1 new DM option should match old value' );
		$this->assertEquals( '0', \get_user_option( 'activitypub_mailer_new_follower', $user_id1 ), 'Actor 1 new follower option should match old value' );
		$this->assertEquals( '1', \get_user_option( 'activitypub_mailer_new_mention', $user_id1 ), 'Actor 1 new mention option should be set to 1' );

		// Verify old options were deleted.
		$this->assertFalse( \get_option( 'activitypub_mailer_new_dm' ), 'Old DM option should be deleted' );
		$this->assertFalse( \get_option( 'activitypub_mailer_new_follower' ), 'Old follower option should be deleted' );

		// Clean up.
		\delete_option( 'activitypub_blog_user_mailer_new_dm' );
		\delete_option( 'activitypub_blog_user_mailer_new_follower' );
		\delete_option( 'activitypub_blog_user_mailer_new_mention' );
		\delete_user_option( $user_id1, 'activitypub_mailer_new_dm' );
		\delete_user_option( $user_id1, 'activitypub_mailer_new_follower' );
		\delete_user_option( $user_id1, 'activitypub_mailer_new_mention' );
		\wp_delete_user( $user_id1 );
	}

	/**
	 * Test migrate followers to AP Actor CPT.
	 *
	 * @covers ::migrate_followers_to_ap_actor_cpt
	 */
	public function test_migrate_followers_to_ap_actor_cpt() {
		$follower = self::factory()->post->create(
			array(
				'post_type' => 'ap_follower',
			)
		);

		\add_post_meta( $follower, '_activitypub_user_id', '5' );

		Migration::migrate_followers_to_ap_actor_cpt();

		\clean_post_cache( $follower );

		$this->assertEquals( Remote_Actors::POST_TYPE, \get_post_type( $follower ) );
		$this->assertEquals( '5', \get_post_meta( $follower, Followers::FOLLOWER_META_KEY, true ) );

		\wp_delete_post( $follower );
	}

	/**
	 * Test update_actor_json_storage with valid JSON.
	 *
	 * @covers ::update_actor_json_storage
	 */
	public function test_update_actor_json_storage() {
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
			return array(
				'code' => 200,
				'body' => $actor_array,
			);
		};

		\add_filter(
			'activitypub_pre_http_get_remote_object',
			$remote_actor
		);

		$post_id = Remote_Actors::upsert( $actor_array );

		\wp_update_post(
			array(
				'ID'           => $post_id,
				'post_type'    => Remote_Actors::POST_TYPE,
				'post_excerpt' => \sanitize_text_field( \wp_kses( $actor_array['summary'], 'user_description' ) ),
			)
		);

		\add_post_meta( $post_id, '_activitypub_actor_json', \wp_slash( \wp_json_encode( $actor_array ) ) );

		$original_meta = \get_post_meta( $post_id, '_activitypub_actor_json', true );

		$this->assertIsObject( \json_decode( $original_meta ) );

		$result = Migration::update_actor_json_storage();

		// No additional batch should be scheduled.
		$this->assertNull( $result );

		\clean_post_cache( $post_id );

		$post    = \get_post( $post_id );
		$content = \json_decode( $post->post_content, true );
		$meta    = \get_post_meta( $post_id, '_activitypub_actor_json', true );

		$this->assertEmpty( $meta, 'Updated meta should be empty' );
		$this->assertEquals( JSON_ERROR_NONE, \json_last_error() );
		$this->assertIsObject( \json_decode( $original_meta ) );
		$this->assertContains( 'Test Follower', $content );
		$this->assertContains( '<p>HTML content</p>', $content );

		$actor = Actor::init_from_json( $post->post_content );

		$this->assertEquals( '<p>HTML content</p>', $actor->get_summary() );

		\remove_filter( 'activitypub_pre_http_get_remote_object', $remote_actor );
		\wp_delete_post( $post_id );
	}

	/**
	 * Test update_actor_json_storage with broken JSON.
	 *
	 * @covers ::update_actor_json_storage
	 */
	public function test_update_actor_json_storage_broken_json() {
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

		$post_id = Remote_Actors::upsert( $actor_array );

		\wp_update_post(
			array(
				'ID'           => $post_id,
				'post_type'    => Remote_Actors::POST_TYPE,
				'post_excerpt' => \sanitize_text_field( \wp_kses( $actor_array['summary'], 'user_description' ) ),
			)
		);

		\add_post_meta( $post_id, '_activitypub_actor_json', 'no json' );

		$original_meta = \get_post_meta( $post_id, '_activitypub_actor_json', true );

		$this->assertEmpty( \json_decode( $original_meta ) );

		$result = Migration::update_actor_json_storage();

		// No additional batch should be scheduled.
		$this->assertNull( $result );

		\clean_post_cache( $post_id );

		$post    = \get_post( $post_id );
		$content = \json_decode( $post->post_content, true );
		$meta    = \get_post_meta( $post_id, '_activitypub_actor_json', true );

		$this->assertEmpty( $meta, 'Updated meta should be empty' );
		$this->assertContains( 'Test Follower', $content );
		$this->assertContains( '<p>HTML content</p>', $content );

		$actor = Actor::init_from_json( $post->post_content );

		$this->assertEquals( '<p>HTML content</p>', $actor->get_summary() );

		\wp_delete_post( $post_id );
	}

	/**
	 * Test remove_pending_application_user_follow_requests removes correct meta entries.
	 *
	 * @covers ::remove_pending_application_user_follow_requests
	 */
	public function test_remove_pending_application_user_follow_requests() {
		global $wpdb;

		// Create test posts with various meta entries.
		$post1 = self::factory()->post->create();
		$post2 = self::factory()->post->create();
		$post3 = self::factory()->post->create();

		// Add _activitypub_following meta with APPLICATION_USER_ID value.
		\add_post_meta( $post1, '_activitypub_following', Actors::APPLICATION_USER_ID );
		\add_post_meta( $post2, '_activitypub_following', Actors::APPLICATION_USER_ID );

		// Add _activitypub_following meta with different values (should not be removed).
		\add_post_meta( $post3, '_activitypub_following', '123' );
		\add_post_meta( $post1, '_activitypub_following', '456' );

		// Add other meta keys (should not be affected).
		\add_post_meta( $post1, '_activitypub_other_meta', Actors::APPLICATION_USER_ID );
		\add_post_meta( $post2, 'some_other_meta', Actors::APPLICATION_USER_ID );

		// Verify initial state.
		$initial_count = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_activitypub_following' AND meta_value = %s",
				Actors::APPLICATION_USER_ID
			)
		);
		$this->assertEquals( 2, $initial_count, 'Should have 2 _activitypub_following entries with APPLICATION_USER_ID' );

		$other_following_count = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_activitypub_following' AND meta_value != %s",
				Actors::APPLICATION_USER_ID
			)
		);
		$this->assertEquals( 2, $other_following_count, 'Should have 2 _activitypub_following entries with other values' );

		// Run the migration.
		Migration::remove_pending_application_user_follow_requests();

		// Verify APPLICATION_USER_ID entries were removed.
		$remaining_count = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_activitypub_following' AND meta_value = %s",
				Actors::APPLICATION_USER_ID
			)
		);
		$this->assertEquals( 0, $remaining_count, 'All _activitypub_following entries with APPLICATION_USER_ID should be removed' );

		// Verify other _activitypub_following entries remain.
		$remaining_other_count = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_activitypub_following' AND meta_value != %s",
				Actors::APPLICATION_USER_ID
			)
		);
		$this->assertEquals( 2, $remaining_other_count, 'Other _activitypub_following entries should remain' );

		// Verify other meta keys are unaffected.
		$this->assertEquals( Actors::APPLICATION_USER_ID, \get_post_meta( $post1, '_activitypub_other_meta', true ), 'Other meta keys should not be affected' );
		$this->assertEquals( Actors::APPLICATION_USER_ID, \get_post_meta( $post2, 'some_other_meta', true ), 'Other meta keys should not be affected' );

		// Clean up.
		\wp_delete_post( $post1, true );
		\wp_delete_post( $post2, true );
		\wp_delete_post( $post3, true );
	}

	/**
	 * Test remove_pending_application_user_follow_requests with no matching entries.
	 *
	 * @covers ::remove_pending_application_user_follow_requests
	 */
	public function test_remove_pending_application_user_follow_requests_no_matches() {
		global $wpdb;

		// Create test posts with non-matching meta entries.
		$post1 = self::factory()->post->create();
		$post2 = self::factory()->post->create();

		// Add _activitypub_following meta with different values.
		\add_post_meta( $post1, '_activitypub_following', '123' );
		\add_post_meta( $post2, '_activitypub_following', '456' );

		// Add other meta keys with APPLICATION_USER_ID.
		\add_post_meta( $post1, '_activitypub_other_meta', Actors::APPLICATION_USER_ID );
		\add_post_meta( $post2, 'different_meta', Actors::APPLICATION_USER_ID );

		// Get initial counts.
		$initial_following_count = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_activitypub_following'"
		);
		$initial_total_count     = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT COUNT(*) FROM {$wpdb->postmeta}"
		);

		// Run the migration.
		Migration::remove_pending_application_user_follow_requests();

		// Verify no entries were removed.
		$final_following_count = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_activitypub_following'"
		);
		$final_total_count     = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT COUNT(*) FROM {$wpdb->postmeta}"
		);

		$this->assertEquals( $initial_following_count, $final_following_count, 'No _activitypub_following entries should be removed' );
		$this->assertEquals( $initial_total_count, $final_total_count, 'Total meta count should remain the same' );

		// Verify specific entries remain.
		$this->assertEquals( '123', \get_post_meta( $post1, '_activitypub_following', true ), '_activitypub_following with different value should remain' );
		$this->assertEquals( '456', \get_post_meta( $post2, '_activitypub_following', true ), '_activitypub_following with different value should remain' );
		$this->assertEquals( Actors::APPLICATION_USER_ID, \get_post_meta( $post1, '_activitypub_other_meta', true ), 'Other meta keys should not be affected' );
		$this->assertEquals( Actors::APPLICATION_USER_ID, \get_post_meta( $post2, 'different_meta', true ), 'Other meta keys should not be affected' );

		// Clean up.
		\wp_delete_post( $post1, true );
		\wp_delete_post( $post2, true );
	}

	/**
	 * Test remove_pending_application_user_follow_requests with multiple APPLICATION_USER_ID entries on same post.
	 *
	 * @covers ::remove_pending_application_user_follow_requests
	 */
	public function test_remove_pending_application_user_follow_requests_multiple_entries() {
		global $wpdb;

		// Create test post.
		$post_id = self::factory()->post->create();

		// Add multiple _activitypub_following meta entries with APPLICATION_USER_ID.
		\add_post_meta( $post_id, '_activitypub_following', Actors::APPLICATION_USER_ID );
		\add_post_meta( $post_id, '_activitypub_following', Actors::APPLICATION_USER_ID );
		\add_post_meta( $post_id, '_activitypub_following', Actors::APPLICATION_USER_ID );

		// Add one with different value.
		\add_post_meta( $post_id, '_activitypub_following', '789' );

		// Verify initial state.
		$initial_app_count = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_activitypub_following' AND meta_value = %s",
				Actors::APPLICATION_USER_ID
			)
		);
		$this->assertEquals( 3, $initial_app_count, 'Should have 3 APPLICATION_USER_ID entries' );

		// Run the migration.
		Migration::remove_pending_application_user_follow_requests();

		// Verify all APPLICATION_USER_ID entries were removed.
		$remaining_app_count = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_activitypub_following' AND meta_value = %s",
				Actors::APPLICATION_USER_ID
			)
		);
		$this->assertEquals( 0, $remaining_app_count, 'All APPLICATION_USER_ID entries should be removed' );

		// Verify the other entry remains.
		$remaining_other_count = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_activitypub_following'",
				$post_id
			)
		);
		$this->assertEquals( 1, $remaining_other_count, 'One _activitypub_following entry should remain' );
		$this->assertEquals( '789', \get_post_meta( $post_id, '_activitypub_following', true ), 'Non-APPLICATION_USER_ID entry should remain' );

		// Clean up.
		\wp_delete_post( $post_id, true );
	}

	/**
	 * Test sync_jetpack_following_meta triggers actions correctly.
	 *
	 * @covers ::sync_jetpack_following_meta
	 */
	public function test_sync_jetpack_following_meta() {
		// Create test posts with following meta.
		$posts = self::factory()->post->create_many( 3, array( 'post_type' => Remote_Actors::POST_TYPE ) );

		// Add following meta to each post.
		\add_post_meta( $posts[0], Following::FOLLOWING_META_KEY, '123' );
		\add_post_meta( $posts[1], Following::FOLLOWING_META_KEY, '456' );
		\add_post_meta( $posts[2], Following::FOLLOWING_META_KEY, '789' );

		// Track action calls.
		$action_calls   = array();
		$capture_action = function () use ( &$action_calls ) {
			$action_calls[] = func_get_args();
		};

		\add_action( 'added_post_meta', $capture_action, 10, 4 );

		// Run the migration with Jetpack available.
		Migration::sync_jetpack_following_meta();

		// Verify the correct actions were triggered.
		$this->assertCount( 3, $action_calls, 'Should trigger action for each following meta entry' );

		// Check the first action call structure.
		$this->assertCount( 4, $action_calls[0], 'Action should be called with 4 parameters' );
		list( $meta_id, $post_id, $meta_key, $meta_value ) = $action_calls[0];

		$this->assertEquals( Following::FOLLOWING_META_KEY, $meta_key, 'Meta key should be Following::FOLLOWING_META_KEY' );
		$this->assertIsNumeric( $meta_id, 'Meta ID should be numeric' );
		$this->assertIsNumeric( $post_id, 'Post ID should be numeric' );
		$this->assertContains( $meta_value, array( '123', '456', '789' ), 'Meta value should be one of the test values' );

		// Clean up.
		\remove_action( 'added_post_meta', $capture_action, 10 );
		foreach ( $posts as $post ) {
			\wp_delete_post( $post, true );
		}
	}

	/**
	 * Test sync_jetpack_following_meta with no following meta.
	 *
	 * @covers ::sync_jetpack_following_meta
	 */
	public function test_sync_jetpack_following_meta_no_entries() {
		// Track action calls for the specific meta key we care about.
		$following_actions = array();
		$capture_action    = function ( $meta_id, $post_id, $meta_key, $meta_value ) use ( &$following_actions ) {
			if ( Following::FOLLOWING_META_KEY === $meta_key ) {
				$following_actions[] = array( $meta_id, $post_id, $meta_key, $meta_value );
			}
		};

		\add_action( 'added_post_meta', $capture_action, 10, 4 );

		// Run migration with no following meta (should not trigger our specific actions).
		Migration::sync_jetpack_following_meta();

		// Verify no following-specific actions were triggered.
		$this->assertEmpty( $following_actions, 'No following-specific actions should be triggered when no following meta exists' );

		// Clean up.
		\remove_action( 'added_post_meta', $capture_action, 10 );
	}
}
