<?php
class Test_Activitypub_Comment extends WP_UnitTestCase {
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
