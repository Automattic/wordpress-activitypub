<?php
/**
 * Test file for Create Handler.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Handler;

use Activitypub\Collection\Posts;
use Activitypub\Handler\Create;
use Activitypub\Post_Types;

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
		parent::set_up();

		// Register required post types.
		Post_Types::register_remote_actors_post_type();
		Post_Types::register_post_post_type();

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
			'name'              => 'Example User',
			'preferredUsername' => 'exampleuser',
			'icon'              => array(
				'url' => 'https://example.com/icon',
			),
			'url'               => $actor,
			'id'                => 'http://example.org/users/example',
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
			'type'   => 'Create',
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
		$this->assertCount( 1, $result );
	}

	/**
	 * Test handle create.
	 *
	 * @covers ::handle_create
	 */
	public function test_handle_create_public_accepted_without_type() {
		$object = $this->create_test_object( 'https://example.com/123456' );
		unset( $object['type'] );

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

	/**
	 * Test handle create check duplicate ID.
	 *
	 * @covers ::handle_create
	 */
	public function test_handle_create_check_duplicate_id() {
		$id     = 'https://example.com/id/' . microtime( true );
		$object = $this->create_test_object( $id );
		Create::handle_create( $object, $this->user_id );

		$args = array(
			'type'    => 'comment',
			'post_id' => $this->post_id,
		);

		$query  = new \WP_Comment_Query( $args );
		$result = $query->comments;

		$this->assertInstanceOf( 'WP_Comment', $result[0] );
		$this->assertEquals( 'example', $result[0]->comment_content );
		$this->assertCount( 1, $result );

		$object['object']['content'] = 'example2';
		Create::handle_create( $object, $this->user_id );

		$args = array(
			'type'    => 'comment',
			'post_id' => $this->post_id,
		);

		$query  = new \WP_Comment_Query( $args );
		$result = $query->comments;

		$this->assertCount( 1, $result );
	}

	/**
	 * Test handle create check duplicate content.
	 *
	 * @covers ::handle_create
	 */
	public function test_handle_create_check_duplicate_content() {
		$id     = 'https://example.com/id/' . microtime( true );
		$object = $this->create_test_object( $id );
		Create::handle_create( $object, $this->user_id );

		$args = array(
			'type'    => 'comment',
			'post_id' => $this->post_id,
		);

		$query  = new \WP_Comment_Query( $args );
		$result = $query->comments;

		$this->assertInstanceOf( 'WP_Comment', $result[0] );
		$this->assertEquals( 'example', $result[0]->comment_content );
		$this->assertCount( 1, $result );

		$id     = 'https://example.com/id/' . microtime( true );
		$object = $this->create_test_object( $id );
		Create::handle_create( $object, $this->user_id );

		$args = array(
			'type'    => 'comment',
			'post_id' => $this->post_id,
		);

		$query  = new \WP_Comment_Query( $args );
		$result = $query->comments;

		$this->assertCount( 1, $result );
	}

	/**
	 * Test handle create multiple comments.
	 *
	 * @covers ::handle_create
	 */
	public function test_handle_create_check_multiple_comments() {
		$id     = 'https://example.com/id/4711';
		$object = $this->create_test_object( $id );
		Create::handle_create( $object, $this->user_id );

		$args = array(
			'type'    => 'comment',
			'post_id' => $this->post_id,
		);

		$query  = new \WP_Comment_Query( $args );
		$result = $query->comments;

		$this->assertInstanceOf( 'WP_Comment', $result[0] );
		$this->assertEquals( 'example', $result[0]->comment_content );
		$this->assertCount( 1, $result );

		$id                          = 'https://example.com/id/23';
		$object                      = $this->create_test_object( $id );
		$object['object']['content'] = 'example2';
		Create::handle_create( $object, $this->user_id );

		$args = array(
			'type'    => 'comment',
			'post_id' => $this->post_id,
			'orderby' => 'comment_ID',
			'order'   => 'ASC',
		);

		$query  = new \WP_Comment_Query( $args );
		$result = $query->comments;

		$this->assertInstanceOf( 'WP_Comment', $result[1] );
		$this->assertEquals( 'example2', $result[1]->comment_content );
		$this->assertCount( 2, $result );
	}

	/**
	 * Test handling create activity for objects with content sanitization.
	 *
	 * @covers ::handle_create
	 * @covers ::create_post
	 */
	public function test_handle_create_object_with_sanitization() {
		// Mock HTTP request for Remote_Actors::fetch_by_uri.
		add_filter(
			'pre_http_request',
			function ( $response, $parsed_args, $url ) {
				if ( 'https://example.com/users/testuser' === $url ) {
					return array(
						'response' => array( 'code' => 200 ),
						'body'     => wp_json_encode(
							array(
								'id'                => 'https://example.com/users/testuser',
								'type'              => 'Person',
								'name'              => 'Test Actor',
								'preferredUsername' => 'testuser',
								'summary'           => 'A test actor',
								'url'               => 'https://example.com/users/testuser',
								'inbox'             => 'https://example.com/users/testuser/inbox',
								'outbox'            => 'https://example.com/users/testuser/outbox',
							)
						),
					);
				}
				return $response;
			},
			10,
			3
		);

		$activity = array(
			'id'     => 'https://example.com/activities/create_note_sanitize',
			'type'   => 'Create',
			'actor'  => 'https://example.com/users/testuser',
			'to'     => array( 'https://www.w3.org/ns/activitystreams#Public' ),
			'object' => array(
				'id'           => 'https://example.com/objects/note_sanitize',
				'type'         => 'Note',
				'name'         => 'Test Note with <script>alert("xss")</script>',
				'content'      => '<p>Safe content</p><script>alert("XSS")</script>',
				'summary'      => 'A test note with malicious content',
				'attributedTo' => 'https://example.com/users/testuser',
				'published'    => '2023-01-01T12:00:00Z',
				'to'           => array( 'https://www.w3.org/ns/activitystreams#Public' ),
			),
		);

		Create::handle_create( $activity, $this->user_id );

		// Verify the object was created with sanitized content.
		$created_object = Posts::get_by_guid( 'https://example.com/objects/note_sanitize' );

		$this->assertNotNull( $created_object );

		// Content should be sanitized (no script tags).
		$this->assertStringNotContainsString( 'script', $created_object->post_title );
		$this->assertStringNotContainsString( 'script', $created_object->post_content );
		$this->assertStringContainsString( 'Safe content', $created_object->post_content );

		// Clean up filter.
		remove_all_filters( 'pre_http_request' );
	}

	/**
	 * Test handling private create activity.
	 *
	 * @covers ::handle_create
	 */
	public function test_handle_create_private_activity() {
		$private_activity = array(
			'type'   => 'Create',
			'actor'  => 'https://example.com/users/testuser',
			'object' => array(
				'id'           => 'https://example.com/objects/private_note',
				'type'         => 'Note',
				'content'      => '<p>This is a private note</p>',
				'attributedTo' => 'https://example.com/users/testuser',
				'to'           => array( 'https://example.com/users/recipient' ), // Private message.
			),
		);

		// Count objects before.
		$objects_before = get_posts(
			array(
				'post_type'      => Posts::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
			)
		);

		Create::handle_create( $private_activity, $this->user_id );

		// Count objects after.
		$objects_after = get_posts(
			array(
				'post_type'      => Posts::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
			)
		);

		// Private activities should not create objects (or should be handled differently).
		$this->assertEquals( count( $objects_before ), count( $objects_after ) );
	}

	/**
	 * Test create activity with malformed object data.
	 *
	 * @covers ::handle_create
	 */
	public function test_handle_create_malformed_object() {
		$malformed_activity = array(
			'type'   => 'Create',
			'actor'  => 'https://example.com/users/testuser',
			'object' => 'not_an_array', // Invalid object format.
		);

		// Count objects before.
		$objects_before = get_posts(
			array(
				'post_type'      => Posts::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
			)
		);

		Create::handle_create( $malformed_activity, $this->user_id );

		// Count objects after.
		$objects_after = get_posts(
			array(
				'post_type'      => Posts::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
			)
		);

		// Should not create objects with malformed data.
		$this->assertEquals( count( $objects_before ), count( $objects_after ) );
	}
}
