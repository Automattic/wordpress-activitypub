<?php
/**
 * Test file for Create Handler.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Handler;

use Activitypub\Handler\Create;

/**
 * Test class for Create Handler.
 *
 * @coversDefaultClass \Activitypub\Handler\Create
 */
class Test_Create extends \WP_UnitTestCase {

	/**
	 * User ID.
	 *
	 * @var int
	 */
	public $user_id;

	/**
	 * User URL.
	 *
	 * @var string
	 */
	public $user_url;

	/**
	 * Post ID.
	 *
	 * @var int
	 */
	public $post_id;

	/**
	 * Post permalink.
	 *
	 * @var string
	 */
	public $post_permalink;

	/**
	 * Set up the test.
	 */
	public function set_up() {
		$this->user_id  = 1;
		$authordata     = \get_userdata( $this->user_id );
		$this->user_url = $authordata->user_url;

		$this->post_id        = \wp_insert_post(
			array(
				'post_author'  => $this->user_id,
				'post_content' => 'test',
			)
		);
		$this->post_permalink = \get_permalink( $this->post_id );

		\add_filter( 'pre_get_remote_metadata_by_actor', array( get_called_class(), 'get_remote_metadata_by_actor' ), 0, 2 );
	}

	/**
	 * Tear down the test.
	 */
	public function tear_down() {
		\remove_filter( 'pre_get_remote_metadata_by_actor', array( get_called_class(), 'get_remote_metadata_by_actor' ) );
		parent::tear_down();
	}

	/**
	 * Get remote metadata by actor.
	 *
	 * @param string $value Value.
	 * @param string $actor Actor.
	 * @return array
	 */
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

	/**
	 * Create test object.
	 *
	 * @param string $id Optional. The ID. Default is 'https://example.com/123'.
	 * @return array
	 */
	public function create_test_object( $id = 'https://example.com/123' ) {
		return array(
			'actor'  => $this->user_url,
			'id'     => 'https://example.com/id/' . microtime( true ),
			'to'     => array( $this->user_url ),
			'cc'     => array( 'https://www.w3.org/ns/activitystreams#Public' ),
			'object' => array(
				'id'        => $id,
				'url'       => 'https://example.com/example',
				'inReplyTo' => $this->post_permalink,
				'content'   => 'example',
			),
		);
	}

	/**
	 * Test handle create.
	 *
	 * @covers ::handle_create
	 */
	public function test_handle_create_non_public_rejected() {
		$object       = $this->create_test_object();
		$object['cc'] = array();
		$converted    = Create::handle_create( $object, $this->user_id );
		$this->assertNull( $converted );
	}

	/**
	 * Test handle create.
	 *
	 * @covers ::handle_create
	 */
	public function test_handle_create_public_accepted() {
		$object = $this->create_test_object();
		Create::handle_create( $object, $this->user_id );

		$args = array(
			'type'    => 'comment',
			'post_id' => $this->post_id,
		);

		$query  = new \WP_Comment_Query( $args );
		$result = $query->comments;

		$this->assertInstanceOf( 'WP_Comment', $result[0] );
		$this->assertEquals( 'example', $result[0]->comment_content );
	}
}
