<?php
class Test_Functions extends ActivityPub_TestCase_Cache_HTTP {
	public function test_get_remote_metadata_by_actor() {
		$metadata = \ActivityPub\get_remote_metadata_by_actor( 'pfefferle@notiz.blog' );
		$this->assertEquals( 'https://notiz.blog/author/matthias-pfefferle/', $metadata['url'] );
		$this->assertEquals( 'pfefferle', $metadata['preferredUsername'] );
		$this->assertEquals( 'Matthias Pfefferle', $metadata['name'] );
	}
}

class Test_ObjectIdToComment extends WP_UnitTestCase {
	var $user_id;
	var $post_id;

	public function set_up() {
		$this->post_id = \wp_insert_post(
			array(
				'post_author' => $this->user_id,
				'post_content' => 'test',
			)
		);
	}

	public function test_object_id_to_comment_basic() {
		$single_comment_source_id = "https://example.com/single";
		$content = "example";
		$comment_id = \wp_new_comment( array(
			'comment_post_ID' => $this->post_id,
			'comment_author' => "Example User",
			'comment_author_url' => "https://example.com/user",
			'comment_content' => $content,
			'comment_type' => '',
			'comment_author_email' => '',
			'comment_parent' => 0,
			'comment_meta' => array(
				'source_id' => $single_comment_source_id,
				'source_url' => "https://example.com/123",
				'avatar_url' => "https://example.com/icon",
				'protocol' => 'activitypub',
			),
		), true );
		$query_result = \Activitypub\object_id_to_comment( $single_comment_source_id );
		$this->assertInstanceOf( WP_Comment::class, $query_result );
		$this->assertEquals( $comment_id, $query_result->comment_ID );
		$this->assertEquals( $content, $query_result->comment_content );
	}

	public function test_object_id_to_comment_none() {
		$single_comment_source_id = "https://example.com/none";
		$query_result = \Activitypub\object_id_to_comment( $single_comment_source_id );
		$this->assertNull( $query_result );
	}

	public function test_object_id_to_comment_duplicate() {
		$duplicate_comment_source_id = "https://example.com/duplicate";
		for ($i = 0; $i < 2; ++$i) {
			\wp_new_comment( array(
				'comment_post_ID' => $this->post_id,
				'comment_author' => "Example User",
				'comment_author_url' => "https://example.com/user",
				'comment_content' => "example",
				'comment_type' => '',
				'comment_author_email' => '',
				'comment_parent' => 0,
				'comment_meta' => array(
					'source_id' => $duplicate_comment_source_id,
					'source_url' => "https://example.com/123",
					'avatar_url' => "https://example.com/icon",
					'protocol' => 'activitypub',
				),
			), true );
		}
		$query_result = \Activitypub\object_id_to_comment( $duplicate_comment_source_id );
		$this->assertNull( $query_result );
	}
}

class Test_ObjectToPostIdByFieldName extends WP_UnitTestCase {
	var $post_id;
	var $post_permalink;

	public function set_up() {
		$user_id = 1;
		$this->post_id = \wp_insert_post(
			array(
				'post_author' => $user_id,
				'post_content' => 'test',
			)
		);
		$this->post_permalink = \get_permalink( $this->post_id );
	}

	public function test_object_to_post_id_by_field_name_basic() {
		$found_post_id = \Activitypub\object_to_post_id_by_field_name(
			array (
				'object' => array(
					'field' => $this->post_permalink,
				),
			),
			"field"
		);
		$this->assertEquals( $this->post_id, $found_post_id );
	}

	public function test_object_to_post_id_by_field_name_not_present() {
		$found_post_id = \Activitypub\object_to_post_id_by_field_name(
			array (
				'object' => array(),
			),
			"field"
		);
		$this->assertNull( $found_post_id );
	}
}
