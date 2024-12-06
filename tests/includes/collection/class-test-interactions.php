<?php
/**
 * Test file for Activitypub Interactions.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Collection;

use Activitypub\Collection\Interactions;

/**
 * Test class for Activitypub Interactions.
 *
 * @coversDefaultClass \Activitypub\Collection\Interactions
 */
class Test_Interactions extends \WP_UnitTestCase {

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

		\add_filter( 'pre_get_remote_metadata_by_actor', array( __CLASS__, 'get_remote_metadata_by_actor' ), 0, 2 );
	}

	/**
	 * Filter for get_remote_metadata_by_actor.
	 *
	 * @param string $value The value.
	 * @param string $actor The actor.
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
	 * Data provider.
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
	 * Data provider for test_handle_create_rich.
	 *
	 * @param string $id Optional. The ID. Default is 'https://example.com/123'.
	 * @return array
	 */
	public function create_test_rich_object( $id = 'https://example.com/123' ) {
		return array(
			'actor'  => $this->user_url,
			'id'     => 'https://example.com/id/' . microtime( true ),
			'to'     => array( $this->user_url ),
			'cc'     => array( 'https://www.w3.org/ns/activitystreams#Public' ),
			'object' => array(
				'id'        => $id,
				'url'       => 'https://example.com/example',
				'inReplyTo' => $this->post_permalink,
				'content'   => 'Hello<br />example<p>example</p><img src="https://example.com/image.jpg" />',
			),
		);
	}

	/**
	 * Test handle create basic.
	 *
	 * @covers ::add_comment
	 */
	public function test_handle_create_basic() {
		$comment_id = Interactions::add_comment( $this->create_test_object() );
		$comment    = get_comment( $comment_id, ARRAY_A );

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

	/**
	 * Test handle create rich.
	 *
	 * @covers ::add_comment
	 */
	public function test_handle_create_rich() {
		$comment_id = Interactions::add_comment( $this->create_test_rich_object() );
		$comment    = get_comment( $comment_id, ARRAY_A );

		$this->assertEquals( 'Hello<br />example<p>example</p>', $comment['comment_content'] );

		$commentarray = array(
			'comment_post_ID'      => $this->post_id,
			'comment_author'       => 'Example User',
			'comment_author_url'   => $this->user_url,
			'comment_content'      => 'Hello<br />example<p>example</p>',
			'comment_type'         => 'comment',
			'comment_author_email' => '',
			'comment_parent'       => 0,
			'comment_meta'         => array(
				'source_id'  => 'https://example.com/123',
				'source_url' => 'https://example.com/example',
				'protocol'   => 'activitypub',
			),
		);

		\add_filter( 'duplicate_comment_id', '__return_false' );
		\remove_action( 'check_comment_flood', 'check_comment_flood_db' );
		$comment_id = wp_new_comment( $commentarray );
		\remove_filter( 'duplicate_comment_id', '__return_false' );
		\add_action( 'check_comment_flood', 'check_comment_flood_db', 10, 4 );

		$comment = get_comment( $comment_id, ARRAY_A );

		$this->assertEquals( 'Helloexampleexample', $comment['comment_content'] );
	}

	/**
	 * Test convert object to comment already exists.
	 *
	 * @covers ::add_comment
	 */
	public function test_convert_object_to_comment_already_exists_rejected() {
		$object = $this->create_test_object( 'https://example.com/test_convert_object_to_comment_already_exists_rejected' );
		Interactions::add_comment( $object );
		$converted = Interactions::add_comment( $object );
		$this->assertEquals( $converted->get_error_code(), 'comment_duplicate' );
	}

	/**
	 * Test convert object to comment reply to comment.
	 *
	 * @covers ::add_comment
	 */
	public function test_convert_object_to_comment_reply_to_comment() {
		$id     = 'https://example.com/test_convert_object_to_comment_reply_to_comment';
		$object = $this->create_test_object( $id );
		Interactions::add_comment( $object );
		$comment = \Activitypub\object_id_to_comment( $id );

		$object['object']['inReplyTo'] = $id;
		$object['object']['id']        = 'https://example.com/234';
		$id                            = Interactions::add_comment( $object );
		$converted                     = get_comment( $id, ARRAY_A );

		$this->assertIsArray( $converted );
		$this->assertEquals( $this->post_id, $converted['comment_post_ID'] );
		$this->assertEquals( $comment->comment_ID, $converted['comment_parent'] );
	}

	/**
	 * Test convert object to comment reply to non existent comment.
	 *
	 * @covers ::add_comment
	 */
	public function test_convert_object_to_comment_reply_to_non_existent_comment_rejected() {
		$object                        = $this->create_test_object();
		$object['object']['inReplyTo'] = 'https://example.com/not_found';
		$converted                     = Interactions::add_comment( $object );
		$this->assertFalse( $converted );
	}

	/**
	 * Test convert object to comment reply to non existent post.
	 *
	 * @covers ::add_comment
	 */
	public function test_handle_create_basic2() {
		$id     = 'https://example.com/test_handle_create_basic';
		$object = $this->create_test_object( $id );
		Interactions::add_comment( $object );
		$comment = \Activitypub\object_id_to_comment( $id );
		$this->assertInstanceOf( \WP_Comment::class, $comment );
	}

	/**
	 * Test get interaction by ID.
	 *
	 * @covers ::get_interaction_by_id
	 */
	public function test_get_interaction_by_id() {
		$id                      = 'https://example.com/test_get_interaction_by_id';
		$url                     = 'https://example.com/test_get_interaction_by_url';
		$object                  = $this->create_test_object( $id );
		$object['object']['url'] = $url;

		Interactions::add_comment( $object );
		$comment      = \Activitypub\object_id_to_comment( $id );
		$interactions = Interactions::get_interaction_by_id( $id );
		$this->assertIsArray( $interactions );
		$this->assertEquals( $comment->comment_ID, $interactions[0]->comment_ID );

		$comment      = \Activitypub\object_id_to_comment( $id );
		$interactions = Interactions::get_interaction_by_id( $url );
		$this->assertIsArray( $interactions );
		$this->assertEquals( $comment->comment_ID, $interactions[0]->comment_ID );
	}
}
