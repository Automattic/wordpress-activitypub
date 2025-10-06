<?php
/**
 * Test file for Activitypub Replies.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Collection;

use Activitypub\Collection\Actors;
use Activitypub\Collection\Replies;

/**
 * Test class for Activitypub Replies.
 *
 * @coversDefaultClass \Activitypub\Collection\Replies
 */
class Test_Replies extends \WP_UnitTestCase {

	/**
	 * Test the replies collection of a post.
	 *
	 * @covers ::get_collection
	 */
	public function test_replies_collection_of_post_with_federated_comments() {
		$post_id = \wp_insert_post(
			array(
				'post_author'  => 1,
				'post_content' => 'test',
			)
		);

		$source_id = 'https://example.instance/notes/123';

		$comment = array(
			'user_id'              => 1,
			'comment_type'         => 'comment',
			'comment_content'      => 'This is a comment.',
			'comment_author_url'   => 'https://example.com',
			'comment_author_email' => '',
			'comment_meta'         => array(
				'protocol'  => 'activitypub',
				'source_id' => $source_id,
			),
			'comment_post_ID'      => $post_id,
		);

		$comment_id = wp_insert_comment( $comment );

		wp_set_comment_status( $comment_id, 'hold' );
		$replies = Replies::get_collection( get_post( $post_id ) );
		$this->assertEquals( $replies['id'], \rest_url( \sprintf( '/activitypub/1.0/posts/%d/replies', $post_id ) ) );
		$this->assertCount( 0, $replies['first']['items'] );

		wp_set_comment_status( $comment_id, 'approve' );
		$replies = Replies::get_collection( get_post( $post_id ) );
		$this->assertCount( 1, $replies['first']['items'] );
		$this->assertEquals( $replies['first']['items'][0], $source_id );
	}

	/**
	 * Test get_context_collection method.
	 *
	 * @covers ::get_context_collection
	 */
	public function test_get_context_collection() {
		// Create a test post.
		$context_post_id = self::factory()->post->create(
			array(
				'post_author' => 1,
			)
		);

		// Test with disabled post.
		add_post_meta( $context_post_id, 'activitypub_content_visibility', ACTIVITYPUB_CONTENT_VISIBILITY_LOCAL );
		$this->assertFalse( Replies::get_context_collection( $context_post_id ), 'Should return false for disabled posts' );
		delete_post_meta( $context_post_id, 'activitypub_content_visibility' );

		// Test with non-existent post.
		$this->assertFalse( Replies::get_context_collection( 999999 ), 'Should return false for non-existent posts' );

		// Test without comments.
		$context = Replies::get_context_collection( $context_post_id );
		$this->assertIsArray( $context, 'Should return an array for posts without comments' );
		$this->assertCount( 1, $context['items'], 'Array should contain only one item for posts without comments' );

		// Create test comments.
		$comments = array();

		// Local comment.
		$comments[] = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $context_post_id,
				'comment_content'  => 'Local comment',
				'comment_approved' => '1',
				'comment_meta'     => array(
					'activitypub_status' => 'federated',
				),
			)
		);

		// ActivityPub comment.
		$comments[] = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $context_post_id,
				'comment_content'  => 'ActivityPub comment',
				'comment_approved' => '1',
				'comment_meta'     => array(
					'protocol'  => 'activitypub',
					'source_id' => 'https://example.com/comment/1',
				),
			)
		);

		// Test with comments.
		$context = Replies::get_context_collection( $context_post_id );

		$this->assertIsArray( $context, 'Should return an array' );
		$this->assertEquals( 'OrderedCollection', $context['type'], 'Should be of type OrderedCollection' );
		$this->assertEquals( get_permalink( $context_post_id ), $context['url'], 'Should contain the post URL' );
		$this->assertArrayHasKey( 'attributedTo', $context, 'Should contain attributedTo' );
		$this->assertArrayHasKey( 'totalItems', $context, 'Should contain totalItems' );
		$this->assertArrayHasKey( 'items', $context, 'Should contain items' );

		// Check the number of items (Post + all comments).
		$this->assertEquals( 3, $context['totalItems'], 'Should count Post + all comments' );
		$this->assertCount( 3, $context['items'], 'Items should contain Post + all comments' );

		// Check that the post URI is the first item.
		$this->assertStringContainsString( (string) $context_post_id, $context['items'][0], 'First item should be the post URI' );

		// Check that the ActivityPub comment is contained.
		$this->assertContains( 'https://example.com/comment/1', $context['items'], 'Should contain ActivityPub comment ID' );

		// Clean up.
		wp_delete_post( $context_post_id, true );
		foreach ( $comments as $comment_id ) {
			wp_delete_comment( $comment_id, true );
		}
	}

	/**
	 * Test get_context_collection method with disabled author.
	 *
	 * @covers ::get_context_collection
	 */
	public function test_get_context_collection_disabled_author() {
		$user_id         = self::factory()->user->create( array( 'role' => 'author' ) );
		$context_post_id = self::factory()->post->create( array( 'post_author' => $user_id ) );
		get_user_by( 'id', $user_id )->remove_cap( 'activitypub' );

		// Author disabled, Blog user disabled.
		$this->assertFalse( Replies::get_context_collection( $context_post_id ) );

		// Enable Blog user.
		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_BLOG_MODE );

		$context = Replies::get_context_collection( $context_post_id );

		$this->assertSame( \get_author_posts_url( Actors::BLOG_USER_ID ), $context['attributedTo'] );

		\delete_option( 'activitypub_actor_mode' );
	}
}
