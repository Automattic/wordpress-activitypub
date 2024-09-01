<?php
class Test_Activitypub_Replies extends WP_UnitTestCase {
	public function test_replies_collection_of_post_with_federated_comments() {
		$post = \wp_insert_post(
			array(
				'post_author' => 1,
				'post_content' => 'test',
			)
		);

		$comment = array(
			'user_id' => 1,
			'comment_type' => 'comment',
			'comment_content' => 'This is a comment.',
			'comment_author_url' => 'https://example.com',
			'comment_author_email' => '',
			'comment_meta' => array(
				'activitypub_status' => 'federated',
			),
			'comment_post_ID' => $post->ID,
		);

		$comment_id = wp_insert_comment( $comment );

		$replies = Activitypub\Collection\Replies::get_collection( $post );

		$this->assertEquals( $replies['id'], sprintf( 'https://example.com/wp-json/activitypub/1.0/posts/%d/replies', $post->ID ) );
		$this->assertCount( 0, $replies['first']['items'] );

		wp_set_comment_status( $comment_id, 'approve' );

		$replies = Activitypub\Collection\Replies::get_collection( $post );
		$this->assertCount( 1, $replies['first']['items'] );
		$this->assertEquals( $replies['first']['items'][0], sprintf( 'https://example.com/?c=%d', $comment_id ) );
	}
}
