<?php
class Test_Activitypub_Comment extends WP_UnitTestCase {
	public function test_get_source_id_or_url() {
		$comment_id = wp_insert_comment(
			array(
				'comment_type' => 'comment id',
				'comment_content' => 'This is a comment id test',
				'comment_author_url' => 'https://example.com',
				'comment_author_email' => '',
				'comment_meta' => array(
					'protocol' => 'activitypub',
					'source_id' => 'https://example.com/id',
				),
			)
		);

		$this->assertEquals( 'https://example.com/id', \Activitypub\Comment::get_source_url( $comment_id ) );
		$this->assertEquals( 'https://example.com/id', \Activitypub\Comment::get_source_id( $comment_id ) );
		$this->assertEquals( 'https://example.com/id', \Activitypub\Comment::get_source_id( $comment_id, false ) );
		$this->assertEquals( null, \Activitypub\Comment::get_source_url( $comment_id, false ) );

		$comment_id = wp_insert_comment(
			array(
				'comment_type' => 'comment url',
				'comment_content' => 'This is a comment url test',
				'comment_author_url' => 'https://example.com',
				'comment_author_email' => '',
				'comment_meta' => array(
					'protocol' => 'activitypub',
					'source_url' => 'https://example.com/url',
				),
			)
		);

		$this->assertEquals( 'https://example.com/url', \Activitypub\Comment::get_source_id( $comment_id ) );
		$this->assertEquals( 'https://example.com/url', \Activitypub\Comment::get_source_url( $comment_id ) );
		$this->assertEquals( 'https://example.com/url', \Activitypub\Comment::get_source_url( $comment_id, false ) );
		$this->assertEquals( null, \Activitypub\Comment::get_source_id( $comment_id, false ) );

		$comment_id = wp_insert_comment(
			array(
				'comment_type' => 'comment url and id',
				'comment_content' => 'This is a comment url and id test',
				'comment_author_url' => 'https://example.com',
				'comment_author_email' => '',
				'comment_meta' => array(
					'protocol' => 'activitypub',
					'source_url' => 'https://example.com/url',
					'source_id' => 'https://example.com/id',
				),
			)
		);

		$this->assertEquals( 'https://example.com/id', \Activitypub\Comment::get_source_id( $comment_id ) );
		$this->assertEquals( 'https://example.com/id', \Activitypub\Comment::get_source_id( $comment_id, false ) );
		$this->assertEquals( 'https://example.com/url', \Activitypub\Comment::get_source_url( $comment_id ) );
		$this->assertEquals( 'https://example.com/url', \Activitypub\Comment::get_source_url( $comment_id, false ) );
	}

	/**
	 * @dataProvider ability_to_federate_comment
	 */
	public function test_check_ability_to_federate_comment( $comment, $expected ) {
		$comment_id = wp_insert_comment( $comment );
		$comment = get_comment( $comment_id );

		$this->assertEquals( $expected['was_sent'], \Activitypub\Comment::was_sent( $comment ) );
		$this->assertEquals( $expected['was_received'], \Activitypub\Comment::was_received( $comment ) );
		$this->assertEquals( $expected['should_be_federated'], \Activitypub\Comment::should_be_federated( $comment ) );
	}

	/**
	 * @dataProvider ability_to_federate_threaded_comment
	 */
	public function test_check_ability_to_federate_threaded_comment( $parent_comment, $comment, $expected ) {
		$parent_comment_id = wp_insert_comment( $parent_comment );
		$comment['comment_parent'] = $parent_comment_id;
		$comment_id = wp_insert_comment( $comment );
		$comment = get_comment( $comment_id );

		$this->assertEquals( $expected['was_sent'], \Activitypub\Comment::was_sent( $parent_comment_id ) );
		$this->assertEquals( $expected['was_received'], \Activitypub\Comment::was_received( $parent_comment_id ) );
		$this->assertEquals( $expected['should_be_federated'], \Activitypub\Comment::should_be_federated( $comment ) );
	}

	public function test_get_comment_ancestors() {
		$comment_id = wp_insert_comment(
			array(
				'comment_type' => 'comment',
				'comment_content' => 'This is a comment.',
				'comment_author_url' => 'https://example.com',
				'comment_author_email' => '',
				'comment_meta' => array(
					'protocol' => 'activitypub',
				),
			)
		);

		$this->assertEquals( array(), \Activitypub\get_comment_ancestors( $comment_id ) );

		$comment_array = get_comment( $comment_id, ARRAY_A );

		$parent_comment_id = wp_insert_comment(
			array(
				'comment_type' => 'parent comment',
				'comment_content' => 'This is a comment.',
				'comment_author_url' => 'https://example.com',
				'comment_author_email' => '',
				'comment_meta' => array(
					'protocol' => 'activitypub',
				),
			)
		);

		$comment_array['comment_parent'] = $parent_comment_id;

		wp_update_comment( $comment_array );

		$this->assertEquals( array( $parent_comment_id ), \Activitypub\get_comment_ancestors( $comment_id ) );
	}

	public function test_pre_comment_approved() {
		// Disable flood control.
		\remove_action( 'check_comment_flood', 'check_comment_flood_db', 10 );

		$post_id = \wp_insert_post(
			array(
				'post_title' => 'Test Post',
				'post_content' => 'This is a test post.',
				'post_status' => 'publish',
			)
		);

		$comment_id_to_approve = \wp_new_comment(
			array(
				'comment_post_ID'      => '',
				'comment_type'         => 'comment',
				'comment_content'      => 'This is a comment.',
				'comment_author'       => 'Approved',
				'comment_author_url'   => 'https://example.com/@approved',
				'comment_post_ID'      => $post_id,
				'comment_author_email' => '',
				'comment_meta'         => array(
					'protocol' => 'activitypub',
				),
			)
		);

		$comment_to_approve = \get_comment( $comment_id_to_approve );
		$this->assertEquals( '0', $comment_to_approve->comment_approved );

		\wp_set_comment_status( $comment_id_to_approve, 'approve' );
		$comment_to_approve = \get_comment( $comment_id_to_approve );
		$this->assertEquals( '1', $comment_to_approve->comment_approved );

		$comment_id_autoapproved = \wp_new_comment(
			array(
				'comment_post_ID'      => '',
				'comment_type'         => 'comment',
				'comment_content'      => 'This is another comment.',
				'comment_author'       => 'Approved',
				'comment_author_url'   => 'https://example.com/@approved',
				'comment_post_ID'      => $post_id,
				'comment_author_email' => '',
				'comment_meta'         => array(
					'protocol' => 'activitypub',
				),
			)
		);

		$comment_autoapproved = \get_comment( $comment_id_autoapproved );
		$this->assertEquals( '1', $comment_autoapproved->comment_approved );

		\remove_filter( 'pre_comment_approved', array( 'Activitypub\Comment', 'pre_comment_approved' ), 10 );

		$comment_id_unapproved = \wp_new_comment(
			array(
				'comment_post_ID'      => '',
				'comment_type'         => 'comment',
				'comment_content'      => 'This is final comment.',
				'comment_author'       => 'Approved',
				'comment_author_url'   => 'https://example.com/@approved',
				'comment_post_ID'      => $post_id,
				'comment_author_email' => '',
				'comment_meta'         => array(
					'protocol' => 'activitypub',
				),
			)
		);

		$comment_unapproved = \get_comment( $comment_id_unapproved );
		$this->assertEquals( '0', $comment_unapproved->comment_approved );

		// Restore flood control.
		\add_action( 'check_comment_flood', 'check_comment_flood_db', 10, 4 );
	}

	public function ability_to_federate_comment() {
		return array(
			array(
				'comment' => array(
					'comment_type' => 'comment',
					'comment_content' => 'This is a comment.',
					'comment_author_url' => 'https://example.com',
					'comment_author_email' => '',
					'comment_meta' => array(
						'protocol' => 'activitypub',
					),
				),
				'expected' => array(
					'was_sent' => false,
					'was_received' => true,
					'should_be_federated' => false,
				),
			),
			array(
				'comment' => array(
					'user_id' => 1,
					'comment_type' => 'comment',
					'comment_content' => 'This is a comment.',
					'comment_author_url' => 'https://example.com',
					'comment_author_email' => '',
				),
				'expected' => array(
					'was_sent' => true,
					'was_received' => false,
					'should_be_federated' => true,
				),
			),
			array(
				'comment' => array(
					'comment_type' => 'comment',
					'comment_content' => 'This is a comment.',
					'comment_author_url' => 'https://example.com',
					'comment_author_email' => '',
				),
				'expected' => array(
					'was_sent' => false,
					'was_received' => false,
					'should_be_federated' => false,
				),
			),
		);
	}

	public function ability_to_federate_threaded_comment() {
		return array(
			array(
				'parent_comment' => array(
					'comment_type' => 'comment',
					'comment_content' => 'This is a comment.',
					'comment_author_url' => 'https://example.com',
					'comment_author_email' => '',
					'comment_meta' => array(
						'protocol' => 'activitypub',
					),
				),
				'comment' => array(
					'comment_type' => 'comment',
					'comment_content' => 'This is a comment.',
					'comment_author_url' => 'https://example.com',
					'comment_author_email' => '',
					'comment_meta' => array(
						'protocol' => 'activitypub',
					),
				),
				'expected' => array(
					'was_sent' => false,
					'was_received' => true,
					'should_be_federated' => false,
				),
			),
			array(
				'parent_comment' => array(
					'comment_type' => 'comment',
					'comment_content' => 'This is a comment.',
					'comment_author_url' => 'https://example.com',
					'comment_author_email' => '',
					'comment_meta' => array(
						'protocol' => 'activitypub',
					),
				),
				'comment' => array(
					'user_id' => 1,
					'comment_type' => 'comment',
					'comment_content' => 'This is a comment.',
					'comment_author_url' => 'https://example.com',
					'comment_author_email' => '',
				),
				'expected' => array(
					'was_sent' => false,
					'was_received' => true,
					'should_be_federated' => true,
				),
			),
			array(
				'parent_comment' => array(
					'user_id' => 1,
					'comment_type' => 'comment',
					'comment_content' => 'This is a comment.',
					'comment_author_url' => 'https://example.com',
					'comment_author_email' => '',
					'comment_meta' => array(
						'activitypub_status' => 'federated',
					),
				),
				'comment' => array(
					'user_id' => 1,
					'comment_type' => 'comment',
					'comment_content' => 'This is a comment.',
					'comment_author_url' => 'https://example.com',
					'comment_author_email' => '',
				),
				'expected' => array(
					'was_sent' => true,
					'was_received' => false,
					'should_be_federated' => true,
				),
			),
			array(
				'parent_comment' => array(
					'comment_type' => 'comment',
					'comment_content' => 'This is a comment.',
					'comment_author_url' => 'https://example.com',
					'comment_author_email' => '',
				),
				'comment' => array(
					'comment_type' => 'comment',
					'comment_content' => 'This is a comment.',
					'comment_author_url' => 'https://example.com',
					'comment_author_email' => '',
				),
				'expected' => array(
					'was_sent' => false,
					'was_received' => false,
					'should_be_federated' => false,
				),
			),
			array(
				'parent_comment' => array(
					'comment_type' => 'comment',
					'comment_content' => 'This is a comment.',
					'comment_author_url' => 'https://example.com',
					'comment_author_email' => '',
				),
				'comment' => array(
					'user_id' => 1,
					'comment_type' => 'comment',
					'comment_content' => 'This is a comment.',
					'comment_author_url' => 'https://example.com',
					'comment_author_email' => '',
				),
				'expected' => array(
					'was_sent' => false,
					'was_received' => false,
					'should_be_federated' => false,
				),
			),
			// this should not be possible but we test it anyway
			array(
				'parent_comment' => array(
					'user_id' => 1,
					'comment_type' => 'comment',
					'comment_content' => 'This is a comment.',
					'comment_author_url' => 'https://example.com',
					'comment_author_email' => '',
				),
				'comment' => array(
					'comment_type' => 'comment',
					'comment_content' => 'This is a comment.',
					'comment_author_url' => 'https://example.com',
					'comment_author_email' => '',
				),
				'expected' => array(
					'was_sent' => true,
					'was_received' => false,
					'should_be_federated' => false,
				),
			),
		);
	}
}
