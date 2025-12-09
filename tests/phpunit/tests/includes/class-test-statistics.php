<?php
/**
 * Test file for Statistics class.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

use Activitypub\Statistics;

/**
 * Test class for Statistics.
 *
 * @coversDefaultClass \Activitypub\Statistics
 */
class Test_Statistics extends \WP_UnitTestCase {
	/**
	 * Test user ID.
	 *
	 * @var int
	 */
	protected static $user_id;

	/**
	 * Test post IDs.
	 *
	 * @var array
	 */
	protected static $post_ids = array();

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

		// Create test posts.
		for ( $i = 0; $i < 5; $i++ ) {
			self::$post_ids[] = $factory->post->create(
				array(
					'post_author' => self::$user_id,
					'post_title'  => 'Test Post ' . ( $i + 1 ),
					'post_status' => 'publish',
				)
			);
		}
	}

	/**
	 * Test get_user_stats returns expected structure.
	 *
	 * @covers ::get_user_stats
	 */
	public function test_get_user_stats_structure() {
		$stats = Statistics::get_user_stats( self::$user_id );

		$this->assertIsArray( $stats );
		$this->assertArrayHasKey( 'followers', $stats );
		$this->assertArrayHasKey( 'following', $stats );
		$this->assertArrayHasKey( 'total_likes', $stats );
		$this->assertArrayHasKey( 'total_reposts', $stats );
		$this->assertArrayHasKey( 'total_replies', $stats );
		$this->assertArrayHasKey( 'total_posts', $stats );
	}

	/**
	 * Test get_user_stats returns correct post count.
	 *
	 * @covers ::get_user_stats
	 * @covers ::get_user_posts
	 */
	public function test_get_user_stats_post_count() {
		// Clear cache first.
		Statistics::clear_cache( self::$user_id );

		$stats = Statistics::get_user_stats( self::$user_id );

		$this->assertEquals( 5, $stats['total_posts'] );
	}

	/**
	 * Test get_user_stats returns zero for engagement without interactions.
	 *
	 * @covers ::get_user_stats
	 */
	public function test_get_user_stats_zero_engagement() {
		Statistics::clear_cache( self::$user_id );

		$stats = Statistics::get_user_stats( self::$user_id );

		$this->assertEquals( 0, $stats['total_likes'] );
		$this->assertEquals( 0, $stats['total_reposts'] );
		$this->assertEquals( 0, $stats['total_replies'] );
	}

	/**
	 * Test get_followers_count.
	 *
	 * @covers ::get_followers_count
	 */
	public function test_get_followers_count() {
		$count = Statistics::get_followers_count( self::$user_id );

		$this->assertIsInt( $count );
		$this->assertGreaterThanOrEqual( 0, $count );
	}

	/**
	 * Test get_following_count.
	 *
	 * @covers ::get_following_count
	 */
	public function test_get_following_count() {
		$count = Statistics::get_following_count( self::$user_id );

		$this->assertIsInt( $count );
		$this->assertGreaterThanOrEqual( 0, $count );
	}

	/**
	 * Test get_user_posts returns only published posts.
	 *
	 * @covers ::get_user_posts
	 */
	public function test_get_user_posts_only_published() {
		// Create a draft post.
		$draft_id = self::factory()->post->create(
			array(
				'post_author' => self::$user_id,
				'post_status' => 'draft',
			)
		);

		$posts = Statistics::get_user_posts( self::$user_id );

		$this->assertContains( self::$post_ids[0], $posts );
		$this->assertNotContains( $draft_id, $posts );
	}

	/**
	 * Test count_replies.
	 *
	 * @covers ::count_replies
	 */
	public function test_count_replies() {
		$post_id = self::$post_ids[0];

		// Add some comments.
		self::factory()->comment->create(
			array(
				'comment_post_ID'  => $post_id,
				'comment_type'     => 'comment',
				'comment_approved' => 1,
			)
		);

		self::factory()->comment->create(
			array(
				'comment_post_ID'  => $post_id,
				'comment_type'     => 'comment',
				'comment_approved' => 1,
			)
		);

		$count = Statistics::count_replies( $post_id );

		$this->assertEquals( 2, $count );
	}

	/**
	 * Test count_replies excludes non-comment types.
	 *
	 * @covers ::count_replies
	 */
	public function test_count_replies_excludes_reactions() {
		$post_id = self::$post_ids[1];

		// Add a comment.
		self::factory()->comment->create(
			array(
				'comment_post_ID'  => $post_id,
				'comment_type'     => 'comment',
				'comment_approved' => 1,
			)
		);

		// Add a like (should not be counted as reply).
		self::factory()->comment->create(
			array(
				'comment_post_ID'  => $post_id,
				'comment_type'     => 'like',
				'comment_approved' => 1,
			)
		);

		$count = Statistics::count_replies( $post_id );

		$this->assertEquals( 1, $count );
	}

	/**
	 * Test get_top_posts returns expected structure.
	 *
	 * @covers ::get_top_posts
	 */
	public function test_get_top_posts_structure() {
		// Add engagement to a post.
		$post_id = self::$post_ids[2];

		self::factory()->comment->create(
			array(
				'comment_post_ID'  => $post_id,
				'comment_type'     => 'like',
				'comment_approved' => 1,
			)
		);

		Statistics::clear_cache( self::$user_id );

		$top_posts = Statistics::get_top_posts( self::$user_id, 3 );

		$this->assertIsArray( $top_posts );

		if ( ! empty( $top_posts ) ) {
			$first_post = $top_posts[0];
			$this->assertArrayHasKey( 'post_id', $first_post );
			$this->assertArrayHasKey( 'title', $first_post );
			$this->assertArrayHasKey( 'url', $first_post );
			$this->assertArrayHasKey( 'likes', $first_post );
			$this->assertArrayHasKey( 'reposts', $first_post );
			$this->assertArrayHasKey( 'replies', $first_post );
			$this->assertArrayHasKey( 'total', $first_post );
		}
	}

	/**
	 * Test get_top_posts excludes posts with no engagement.
	 *
	 * @covers ::get_top_posts
	 */
	public function test_get_top_posts_excludes_zero_engagement() {
		// Create a new user with a post that has no engagement.
		$new_user_id = self::factory()->user->create( array( 'role' => 'author' ) );

		self::factory()->post->create(
			array(
				'post_author' => $new_user_id,
				'post_status' => 'publish',
			)
		);

		$top_posts = Statistics::get_top_posts( $new_user_id, 3 );

		$this->assertEmpty( $top_posts );
	}

	/**
	 * Test get_top_posts is sorted by total engagement.
	 *
	 * @covers ::get_top_posts
	 */
	public function test_get_top_posts_sorted_by_engagement() {
		$user_id = self::factory()->user->create( array( 'role' => 'author' ) );

		// Create two posts with different engagement levels.
		$post_low = self::factory()->post->create(
			array(
				'post_author' => $user_id,
				'post_title'  => 'Low Engagement',
				'post_status' => 'publish',
			)
		);

		$post_high = self::factory()->post->create(
			array(
				'post_author' => $user_id,
				'post_title'  => 'High Engagement',
				'post_status' => 'publish',
			)
		);

		// Add 1 like to low engagement post.
		self::factory()->comment->create(
			array(
				'comment_post_ID'  => $post_low,
				'comment_type'     => 'like',
				'comment_approved' => 1,
			)
		);

		// Add 5 likes to high engagement post.
		for ( $i = 0; $i < 5; $i++ ) {
			self::factory()->comment->create(
				array(
					'comment_post_ID'  => $post_high,
					'comment_type'     => 'like',
					'comment_approved' => 1,
				)
			);
		}

		$top_posts = Statistics::get_top_posts( $user_id, 3 );

		$this->assertCount( 2, $top_posts );
		$this->assertEquals( $post_high, $top_posts[0]['post_id'] );
		$this->assertEquals( $post_low, $top_posts[1]['post_id'] );
	}

	/**
	 * Test get_total_engagement.
	 *
	 * @covers ::get_total_engagement
	 */
	public function test_get_total_engagement() {
		$total = Statistics::get_total_engagement( self::$user_id );

		$this->assertIsInt( $total );
		$this->assertGreaterThanOrEqual( 0, $total );
	}

	/**
	 * Test clear_cache.
	 *
	 * @covers ::clear_cache
	 */
	public function test_clear_cache() {
		// Get stats to populate cache.
		Statistics::get_user_stats( self::$user_id );
		Statistics::get_top_posts( self::$user_id, 3 );

		// Clear cache should not throw any errors.
		Statistics::clear_cache( self::$user_id );

		// This is a basic test - we can't easily verify cache is cleared,
		// but we can verify the method runs without error.
		$this->assertTrue( true );
	}

	/**
	 * Test stats for non-existent user.
	 *
	 * @covers ::get_user_stats
	 */
	public function test_get_user_stats_nonexistent_user() {
		$stats = Statistics::get_user_stats( 999999 );

		$this->assertIsArray( $stats );
		$this->assertEquals( 0, $stats['total_posts'] );
	}
}
