<?php
/**
 * Test file for Dashboard_Widget.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

use Activitypub\Collection\Actors;
use Activitypub\WP_Admin\Dashboard_Widget;

/**
 * Test class for Dashboard_Widget.
 *
 * @coversDefaultClass \Activitypub\WP_Admin\Dashboard_Widget
 */
class Test_Dashboard_Widget extends \WP_UnitTestCase {

	/**
	 * Test that get_stats returns an array with the expected keys, all integers.
	 *
	 * @covers ::get_stats
	 */
	public function test_get_stats_returns_expected_keys() {
		$user_id = self::factory()->user->create( array( 'role' => 'author' ) );
		$user    = new \WP_User( $user_id );
		$user->add_cap( 'activitypub' );
		\wp_set_current_user( $user_id );

		$stats = Dashboard_Widget::get_stats( $user_id );

		$this->assertIsArray( $stats );
		$this->assertArrayHasKey( 'followers', $stats );
		$this->assertArrayHasKey( 'following', $stats );
		$this->assertArrayHasKey( 'likes', $stats );
		$this->assertArrayHasKey( 'reposts', $stats );
		$this->assertArrayHasKey( 'comments', $stats );

		$this->assertIsInt( $stats['followers'] );
		$this->assertIsInt( $stats['following'] );
		$this->assertIsInt( $stats['likes'] );
		$this->assertIsInt( $stats['reposts'] );
		$this->assertIsInt( $stats['comments'] );
	}

	/**
	 * Test that a fresh user has all zeros for stats.
	 *
	 * @covers ::get_stats
	 */
	public function test_get_stats_returns_zero_for_new_user() {
		$user_id = self::factory()->user->create( array( 'role' => 'author' ) );
		$user    = new \WP_User( $user_id );
		$user->add_cap( 'activitypub' );
		\wp_set_current_user( $user_id );

		$stats = Dashboard_Widget::get_stats( $user_id );

		$this->assertSame( 0, $stats['followers'] );
		$this->assertSame( 0, $stats['following'] );
		$this->assertSame( 0, $stats['likes'] );
		$this->assertSame( 0, $stats['reposts'] );
		$this->assertSame( 0, $stats['comments'] );
	}

	/**
	 * Test that get_stats counts likes correctly.
	 *
	 * @covers ::get_stats
	 */
	public function test_get_stats_counts_likes() {
		$user_id = self::factory()->user->create( array( 'role' => 'author' ) );
		$user    = new \WP_User( $user_id );
		$user->add_cap( 'activitypub' );
		\wp_set_current_user( $user_id );

		$post_id = self::factory()->post->create( array( 'post_author' => $user_id ) );

		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $post_id,
				'comment_type'     => 'like',
				'comment_approved' => 1,
			)
		);
		\add_comment_meta( $comment_id, 'protocol', 'activitypub' );

		$stats = Dashboard_Widget::get_stats( $user_id );

		$this->assertSame( 1, $stats['likes'] );
	}

	/**
	 * Test that get_stats counts reposts correctly.
	 *
	 * @covers ::get_stats
	 */
	public function test_get_stats_counts_reposts() {
		$user_id = self::factory()->user->create( array( 'role' => 'author' ) );
		$user    = new \WP_User( $user_id );
		$user->add_cap( 'activitypub' );
		\wp_set_current_user( $user_id );

		$post_id = self::factory()->post->create( array( 'post_author' => $user_id ) );

		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $post_id,
				'comment_type'     => 'repost',
				'comment_approved' => 1,
			)
		);
		\add_comment_meta( $comment_id, 'protocol', 'activitypub' );

		$stats = Dashboard_Widget::get_stats( $user_id );

		$this->assertSame( 1, $stats['reposts'] );
	}

	/**
	 * Test that get_stats counts only federated comments, not local ones.
	 *
	 * @covers ::get_stats
	 */
	public function test_get_stats_counts_federated_comments() {
		$user_id = self::factory()->user->create( array( 'role' => 'author' ) );
		$user    = new \WP_User( $user_id );
		$user->add_cap( 'activitypub' );
		\wp_set_current_user( $user_id );

		$post_id = self::factory()->post->create( array( 'post_author' => $user_id ) );

		// Federated comment (has protocol meta).
		$federated_comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $post_id,
				'comment_type'     => 'comment',
				'comment_approved' => 1,
			)
		);
		\add_comment_meta( $federated_comment_id, 'protocol', 'activitypub' );

		// Local comment (no protocol meta).
		self::factory()->comment->create(
			array(
				'comment_post_ID'  => $post_id,
				'comment_type'     => 'comment',
				'comment_approved' => 1,
			)
		);

		$stats = Dashboard_Widget::get_stats( $user_id );

		$this->assertSame( 1, $stats['comments'] );
	}

	/**
	 * Test register adds widget to global.
	 *
	 * @covers ::register
	 */
	public function test_register_adds_widget() {
		global $wp_meta_boxes;

		require_once ABSPATH . 'wp-admin/includes/dashboard.php';

		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$user    = new \WP_User( $user_id );
		$user->add_cap( 'activitypub' );

		wp_set_current_user( $user_id );
		set_current_screen( 'dashboard' );

		Dashboard_Widget::register();

		$this->assertArrayHasKey( 'activitypub_stats', $wp_meta_boxes['dashboard']['normal']['core'] );
	}

	/**
	 * Test get_stats with blog user ID.
	 *
	 * @covers ::get_stats
	 */
	public function test_get_stats_for_blog_user() {
		$stats = Dashboard_Widget::get_stats( Actors::BLOG_USER_ID );

		$this->assertArrayHasKey( 'followers', $stats );
		$this->assertArrayHasKey( 'likes', $stats );
		$this->assertSame( 0, $stats['followers'] );
	}
}
