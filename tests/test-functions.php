<?php
class Test_Functions extends ActivityPub_TestCase_Cache_HTTP {
	public $user_id;
	public $post_id;

	public function test_get_remote_metadata_by_actor() {
		$metadata = \ActivityPub\get_remote_metadata_by_actor( 'pfefferle@notiz.blog' );
		$this->assertEquals( 'https://notiz.blog/author/matthias-pfefferle/', $metadata['url'] );
		$this->assertEquals( 'pfefferle', $metadata['preferredUsername'] );
		$this->assertEquals( 'Matthias Pfefferle', $metadata['name'] );
	}

	public function set_up() {
		$this->post_id = \wp_insert_post(
			array(
				'post_author' => $this->user_id,
				'post_content' => 'test',
			)
		);
	}

	public function test_object_id_to_comment_basic() {
		$single_comment_source_id = 'https://example.com/single';
		$content = 'example';
		$comment_id = \wp_new_comment(
			array(
				'comment_post_ID' => $this->post_id,
				'comment_author' => 'Example User',
				'comment_author_url' => 'https://example.com/user',
				'comment_content' => $content,
				'comment_type' => '',
				'comment_author_email' => '',
				'comment_parent' => 0,
				'comment_meta' => array(
					'source_id' => $single_comment_source_id,
					'source_url' => 'https://example.com/123',
					'avatar_url' => 'https://example.com/icon',
					'protocol' => 'activitypub',
				),
			),
			true
		);
		$query_result = \Activitypub\object_id_to_comment( $single_comment_source_id );
		$this->assertInstanceOf( WP_Comment::class, $query_result );
		$this->assertEquals( $comment_id, $query_result->comment_ID );
		$this->assertEquals( $content, $query_result->comment_content );
	}

	public function test_object_id_to_comment_none() {
		$single_comment_source_id = 'https://example.com/none';
		$query_result = \Activitypub\object_id_to_comment( $single_comment_source_id );
		$this->assertFalse( $query_result );
	}

	public function test_object_id_to_comment_duplicate() {
		$duplicate_comment_source_id = 'https://example.com/duplicate';
		for ( $i = 0; $i < 2; ++$i ) {
			\wp_new_comment(
				array(
					'comment_post_ID' => $this->post_id,
					'comment_author' => 'Example User',
					'comment_author_url' => 'https://example.com/user',
					'comment_content' => 'example',
					'comment_type' => '',
					'comment_author_email' => '',
					'comment_parent' => 0,
					'comment_meta' => array(
						'source_id' => $duplicate_comment_source_id,
						'source_url' => 'https://example.com/123',
						'avatar_url' => 'https://example.com/icon',
						'protocol' => 'activitypub',
					),
				),
				true
			);
		}
		$query_result = \Activitypub\object_id_to_comment( $duplicate_comment_source_id );
		$this->assertFalse( $query_result );
	}

	/**
	 * @dataProvider object_to_uri_provider
	 */
	public function test_object_to_uri( $input, $output ) {
		$this->assertEquals( $output, \Activitypub\object_to_uri( $input ) );
	}

	public function object_to_uri_provider() {
		return array(
			array( null, null ),
			array( 'https://example.com', 'https://example.com' ),
			array( array( 'https://example.com' ), 'https://example.com' ),
			array(
				array(
					'https://example.com',
					'https://example.org',
				),
				'https://example.com',
			),
			array(
				array(
					'type' => 'Link',
					'href' => 'https://example.com',
				),
				'https://example.com',
			),
			array(
				array(
					array(
						'type' => 'Link',
						'href' => 'https://example.com',
					),
					array(
						'type' => 'Link',
						'href' => 'https://example.org',
					),
				),
				'https://example.com',
			),
			array(
				array(
					'type' => 'Actor',
					'id' => 'https://example.com',
				),
				'https://example.com',
			),
			array(
				array(
					array(
						'type' => 'Actor',
						'id' => 'https://example.com',
					),
					array(
						'type' => 'Actor',
						'id' => 'https://example.org',
					),
				),
				'https://example.com',
			),
			array(
				array(
					'type' => 'Activity',
					'id' => 'https://example.com',
				),
				'https://example.com',
			),
		);
	}
}
