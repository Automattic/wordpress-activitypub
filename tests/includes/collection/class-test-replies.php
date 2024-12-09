<?php
/**
 * Test file for Activitypub Replies.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Collection;

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
		$this->assertEquals( $replies['id'], sprintf( 'http://example.org/index.php?rest_route=/activitypub/1.0/posts/%d/replies', $post_id ) );
		$this->assertCount( 0, $replies['first']['items'] );

		wp_set_comment_status( $comment_id, 'approve' );
		$replies = Replies::get_collection( get_post( $post_id ) );
		$this->assertCount( 1, $replies['first']['items'] );
		$this->assertEquals( $replies['first']['items'][0], $source_id );
	}
}
