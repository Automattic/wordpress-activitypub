<?php
/**
 * Test file for Admin.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

use Activitypub\WP_Admin\Admin;

/**
 * Test class for Admin.
 *
 * @coversDefaultClass \Activitypub\WP_Admin\Admin
 */
class Test_Admin extends \WP_UnitTestCase {
	/**
	 * Test adding a source post link to received comments.
	 *
	 * @covers ::comment_row_actions
	 */
	public function test_comment_row_actions_adds_source_link() {
		$comment_id = self::factory()->comment->create();
		add_comment_meta( $comment_id, 'protocol', 'activitypub' );
		add_comment_meta( $comment_id, 'source_url', 'https://example.com/posts/123' );

		$actions = Admin::comment_row_actions( array(), get_comment( $comment_id ) );

		$this->assertArrayHasKey( 'view_source', $actions );
		$this->assertSame(
			'<a href="https://example.com/posts/123" target="_blank" rel="noopener">View source post</a>',
			$actions['view_source']
		);
	}

	/**
	 * Test that comments without a source URL do not get a source post link.
	 *
	 * Reactions like Likes or Announces only store an ActivityPub ID as `source_id`,
	 * which is not always a browsable page.
	 *
	 * @covers ::comment_row_actions
	 */
	public function test_comment_row_actions_skips_comments_without_source_link() {
		$comment_id = self::factory()->comment->create();
		add_comment_meta( $comment_id, 'protocol', 'activitypub' );

		$actions = Admin::comment_row_actions( array(), get_comment( $comment_id ) );

		$this->assertArrayNotHasKey( 'view_source', $actions );

		delete_comment_meta( $comment_id, 'source_id' );
		add_comment_meta( $comment_id, 'source_id', 'https://example.com/activity/1' );

		$actions = Admin::comment_row_actions( array(), get_comment( $comment_id ) );

		$this->assertArrayNotHasKey( 'view_source', $actions );
	}

	/**
	 * Test that local comments do not get a source post link.
	 *
	 * @covers ::comment_row_actions
	 */
	public function test_comment_row_actions_skips_local_comments() {
		$comment_id = self::factory()->comment->create();

		$actions = Admin::comment_row_actions( array(), get_comment( $comment_id ) );

		$this->assertArrayNotHasKey( 'view_source', $actions );
	}

	/**
	 * Test that the method accepts a comment ID as well as a comment object.
	 *
	 * @covers ::comment_row_actions
	 */
	public function test_comment_row_actions_accepts_comment_id() {
		$comment_id = self::factory()->comment->create();
		add_comment_meta( $comment_id, 'protocol', 'activitypub' );
		add_comment_meta( $comment_id, 'source_url', 'https://example.com/posts/123' );

		$actions = Admin::comment_row_actions( array( 'approve' => 'Approve' ), $comment_id );

		$this->assertArrayHasKey( 'view_source', $actions );
	}
}
