<?php
class Test_Activitypub_Interactions extends WP_UnitTestCase {
	public $user_id;
	public $user_url;
	public $post_id;
	public $post_permalink;

	public function set_up() {
		$this->user_id = 1;
		$authordata = \get_userdata( $this->user_id );
		$this->user_url = $authordata->user_url;

		$this->post_id = \wp_insert_post(
			array(
				'post_author' => $this->user_id,
				'post_content' => 'test',
			)
		);
		$this->post_permalink = \get_permalink( $this->post_id );

		\add_filter( 'pre_get_remote_metadata_by_actor', array( '\Test_Activitypub_Interactions', 'get_remote_metadata_by_actor' ), 0, 2 );
	}

	public static function get_remote_metadata_by_actor( $value, $actor ) {
		return array(
			'name' => 'Example User',
			'icon' => array(
				'url' => 'https://example.com/icon',
			),
			'url'  => $actor,
			'id'   => 'http://example.org/users/example',
		);
	}

	public function create_test_object( $id = 'https://example.com/123' ) {
		return array(
			'actor' => $this->user_url,
			'id' => 'https://example.com/id/' . microtime( true ),
			'to' => [ $this->user_url ],
			'cc' => [ 'https://www.w3.org/ns/activitystreams#Public' ],
			'object' => array(
				'id'        => $id,
				'url'       => 'https://example.com/example',
				'inReplyTo' => $this->post_permalink,
				'content'   => 'example',
			),
		);
	}

	public function test_handle_create_basic() {
		$comment_id = Activitypub\Collection\Interactions::add_comment( $this->create_test_object() );
		$comment   = get_comment( $comment_id, ARRAY_A );

		$this->assertIsArray( $comment );
		$this->assertEquals( $this->post_id, $comment['comment_post_ID'] );
		$this->assertEquals( 'Example User', $comment['comment_author'] );
		$this->assertEquals( $this->user_url, $comment['comment_author_url'] );
		$this->assertEquals( 'example', $comment['comment_content'] );
		$this->assertEquals( 'comment', $comment['comment_type'] );
		$this->assertEquals( '', $comment['comment_author_email'] );
		$this->assertEquals( 0, $comment['comment_parent'] );
		$this->assertEquals( 'https://example.com/123', get_comment_meta( $comment_id, 'source_id', true ) );
		$this->assertEquals( 'https://example.com/example', get_comment_meta( $comment_id, 'source_url', true ) );
		$this->assertEquals( 'https://example.com/icon', get_comment_meta( $comment_id, 'avatar_url', true ) );
		$this->assertEquals( 'activitypub', get_comment_meta( $comment_id, 'protocol', true ) );
	}

	public function test_convert_object_to_comment_not_reply_rejected() {
		$object = $this->create_test_object();
		unset( $object['object']['inReplyTo'] );
		$converted = Activitypub\Collection\Interactions::add_comment( $object );
		$this->assertEquals( $converted->get_error_code(), 'activitypub_no_reply' );
	}

	public function test_convert_object_to_comment_already_exists_rejected() {
		$object = $this->create_test_object( 'https://example.com/test_convert_object_to_comment_already_exists_rejected' );
		Activitypub\Collection\Interactions::add_comment( $object );
		$converted = Activitypub\Collection\Interactions::add_comment( $object );
		$this->assertEquals( $converted->get_error_code(), 'comment_duplicate' );
	}

	public function test_convert_object_to_comment_reply_to_comment() {
		$id = 'https://example.com/test_convert_object_to_comment_reply_to_comment';
		$object = $this->create_test_object( $id );
		Activitypub\Collection\Interactions::add_comment( $object );
		$comment = \Activitypub\object_id_to_comment( $id );

		$object['object']['inReplyTo'] = $id;
		$object['object']['id'] = 'https://example.com/234';
		$id = Activitypub\Collection\Interactions::add_comment( $object );
		$converted = get_comment( $id, ARRAY_A );

		$this->assertIsArray( $converted );
		$this->assertEquals( $this->post_id, $converted['comment_post_ID'] );
		$this->assertEquals( $comment->comment_ID, $converted['comment_parent'] );
	}

	public function test_convert_object_to_comment_reply_to_non_existent_comment_rejected() {
		$object = $this->create_test_object();
		$object['object']['inReplyTo'] = 'https://example.com/not_found';
		$converted = Activitypub\Collection\Interactions::add_comment( $object );
		$this->assertEquals( $converted->get_error_code(), 'activitypub_no_reply' );
	}

	public function test_handle_create_basic2() {
		$id = 'https://example.com/test_handle_create_basic';
		$object = $this->create_test_object( $id );
		Activitypub\Collection\Interactions::add_comment( $object );
		$comment = \Activitypub\object_id_to_comment( $id );
		$this->assertInstanceOf( WP_Comment::class, $comment );
	}
}
