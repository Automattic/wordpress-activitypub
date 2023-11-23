<?php
class Test_Activitypub_Create_Handler extends WP_UnitTestCase {
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

		\add_filter( 'pre_get_remote_metadata_by_actor', array( '\Test_Activitypub_Create_Handler', 'get_remote_metadata_by_actor' ), 0, 2 );
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

	public function test_handle_create_object_unset_rejected() {
		$object = $this->create_test_object();
		unset( $object['object'] );
		$converted = Activitypub\Handler\Create::handle_create( $object, $this->user_id );
		$this->assertNull( $converted );
	}

	public function test_handle_create_non_public_rejected() {
		$object = $this->create_test_object();
		$object['cc'] = [];
		$converted = Activitypub\Handler\Create::handle_create( $object, $this->user_id );
		$this->assertNull( $converted );
	}

	public function test_handle_create_no_id_rejected() {
		$object = $this->create_test_object();
		unset( $object['object']['id'] );
		$converted = Activitypub\Handler\Create::handle_create( $object, $this->user_id );
		$this->assertNull( $converted );
	}
}
