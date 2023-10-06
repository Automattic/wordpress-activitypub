<?php
class Test_Inbox extends WP_UnitTestCase {
	public $post_permalink;
	public $user_url;

	public function set_up() {
		$authordata = \get_userdata( 1 );
		$this->user_url = $authordata->user_url;

		$post = \wp_insert_post(
			array(
				'post_author' => 1,
				'post_content' => 'test',
			)
		);
		$this->post_permalink = \get_permalink( $post );

		\add_filter( 'pre_get_remote_metadata_by_actor', array( '\Test_Inbox', 'get_remote_metadata_by_actor' ), 10, 2 );
	}

	public static function get_remote_metadata_by_actor( $value, $actor ) {
		return array(
			'name' => 'Example User',
			'icon' => array(
				'url' => 'https://example.com/icon',
			),
		);
	}

	public function test_convert_object_to_comment_data_basic() {
		$object = array(
			'actor' => $this->user_url,
			'to' => [ $this->user_url ],
			'cc' => [ 'https://www.w3.org/ns/activitystreams#Public' ],
			'object' => array(
				'id' => '123',
				'url' => 'https://example.com/example',
				'inReplyTo' => $this->post_permalink,
				'content' => 'example',
			),
		);
		$converted = \Activitypub\Rest\Inbox::convert_object_to_comment_data( $object, 1 );

		$this->assertGreaterThan( 1, $converted['comment_post_ID'] );
		$this->assertEquals( $converted['comment_author'], 'Example User' );
		$this->assertEquals( $converted['comment_author_url'], 'http://example.org' );
		$this->assertEquals( $converted['comment_content'], 'example' );
		$this->assertEquals( $converted['comment_type'], 'comment' );
		$this->assertEquals( $converted['comment_author_email'], '' );
		$this->assertEquals( $converted['comment_parent'], 0 );
		$this->assertArrayHasKey( 'comment_meta', $converted );
		$this->assertEquals( $converted['comment_meta']['source_id'], 'http://123' );
		$this->assertEquals( $converted['comment_meta']['source_url'], 'https://example.com/example' );
		$this->assertEquals( $converted['comment_meta']['avatar_url'], 'https://example.com/icon' );
		$this->assertEquals( $converted['comment_meta']['protocol'], 'activitypub' );
	}

	public function test_convert_object_to_comment_data_non_public_rejected() {
		$object = array(
			'to' => array( 'https://example.com/profile/test' ),
			'cc' => array(),
		);
		$converted = \Activitypub\Rest\Inbox::convert_object_to_comment_data( $object, 1 );
		$this->assertFalse( $converted );
	}
}
