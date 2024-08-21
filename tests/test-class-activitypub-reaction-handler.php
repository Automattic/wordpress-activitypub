<?php
class Test_Activitypub_Reaction_Handler extends WP_UnitTestCase {
	public $user_id;
	public $user_url;
	public $post_id;
	public $post_permalink;

	public function set_up() {
		parent::set_up();
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

		\add_filter( 'pre_get_remote_metadata_by_actor', array( get_called_class(), 'get_remote_metadata_by_actor' ), 0, 2 );
	}

	public function tear_down() {
		\remove_filter( 'pre_get_remote_metadata_by_actor', array( get_called_class(), 'get_remote_metadata_by_actor' ) );
		parent::tear_down();
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

	public function create_test_object() {
		return array(
			'actor' => $this->user_url,
			'type' => 'Like',
			'id' => 'https://example.com/id/' . microtime( true ),
			'to' => [ $this->user_url ],
			'cc' => [ 'https://www.w3.org/ns/activitystreams#Public' ],
			'object' => $this->post_permalink,
		);
	}

	public function test_handle_like() {
		$object = $this->create_test_object();
		Activitypub\Handler\Like::handle_like( $object, $this->user_id );

		$args = array(
			'type'    => 'like',
			'post_id' => $this->post_id,
		);

		$query = new \WP_Comment_Query( $args );
		$result = $query->comments;

		$this->assertInstanceOf( 'WP_Comment', $result[0] );
	}

	public function test_handle_announce() {
		$object = $this->create_test_object();
		$object['type'] = 'Announce';

		Activitypub\Handler\Announce::handle_announce( $object, $this->user_id );

		$args = array(
			'type'    => 'repost',
			'post_id' => $this->post_id,
		);

		$query = new \WP_Comment_Query( $args );
		$result = $query->comments;

		$this->assertInstanceOf( 'WP_Comment', $result[0] );
	}
}
