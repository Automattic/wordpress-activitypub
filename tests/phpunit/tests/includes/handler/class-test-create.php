<?php
/**
 * Test file for Create Handler.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Handler;

use Activitypub\Activity\Activity;
use Activitypub\Activity\Base_Object;
use Activitypub\Collection\Posts;
use Activitypub\Handler\Create;
use Activitypub\Post_Types;
use Activitypub\Tombstone;

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
		$mock_callback = function ( $pre, $url_or_object ) {
			$url = \Activitypub\object_to_uri( $url_or_object );
			if ( 'https://example.com/users/testuser' === $url ) {
				return array(
					'id'                => 'https://example.com/users/testuser',
					'type'              => 'Person',
					'name'              => 'Test Actor',
					'preferredUsername' => 'testuser',
					'summary'           => 'A test actor',
					'url'               => 'https://example.com/users/testuser',
					'inbox'             => 'https://example.com/users/testuser/inbox',
					'outbox'            => 'https://example.com/users/testuser/outbox',
				);
			}
			return $pre;
		};
		add_filter( 'activitypub_pre_http_get_remote_object', $mock_callback, 10, 2 );

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

		\update_option( 'activitypub_create_posts', true );

		Create::handle_create( $activity, $this->user_id );

		// Verify the object was created with sanitized content.
		$created_object = Posts::get_by_guid( 'https://example.com/objects/note_sanitize' );

		$this->assertNotNull( $created_object );

		// Content should be sanitized (no script tags).
		$this->assertStringNotContainsString( 'script', $created_object->post_title );
		$this->assertStringNotContainsString( 'script', $created_object->post_content );
		$this->assertStringContainsString( 'Safe content', $created_object->post_content );

		// Clean up filter.
		\remove_filter( 'activitypub_pre_http_get_remote_object', $mock_callback );
		\delete_option( 'activitypub_create_posts' );
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
			'object' => array(
				'id'           => 'https://example.com/objects/malformed',
				'type'         => 'Note',
				'content'      => 'Test content',
				'attributedTo' => 'https://example.com/users/testuser',
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

	/**
	 * Test create_post returns false when activitypub_create_posts option is disabled.
	 *
	 * @covers ::create_post
	 */
	public function test_create_post_disabled_by_option() {
		// Ensure option is not set.
		\delete_option( 'activitypub_create_posts' );

		// Mock HTTP request for Remote_Actors::fetch_by_uri.
		$mock_callback = function ( $pre, $url_or_object ) {
			$url = \Activitypub\object_to_uri( $url_or_object );
			if ( 'https://example.com/users/testuser' === $url ) {
				return array(
					'id'                => 'https://example.com/users/testuser',
					'type'              => 'Person',
					'name'              => 'Test Actor',
					'preferredUsername' => 'testuser',
					'url'               => 'https://example.com/users/testuser',
					'inbox'             => 'https://example.com/users/testuser/inbox',
				);
			}
			return $pre;
		};
		\add_filter( 'activitypub_pre_http_get_remote_object', $mock_callback, 10, 2 );

		$activity = array(
			'id'     => 'https://example.com/activities/create_disabled',
			'type'   => 'Create',
			'actor'  => 'https://example.com/users/testuser',
			'to'     => array( 'https://www.w3.org/ns/activitystreams#Public' ),
			'object' => array(
				'id'           => 'https://example.com/objects/note_disabled',
				'type'         => 'Note',
				'content'      => '<p>This should not be created</p>',
				'attributedTo' => 'https://example.com/users/testuser',
				'published'    => '2023-01-01T12:00:00Z',
				'to'           => array( 'https://www.w3.org/ns/activitystreams#Public' ),
			),
		);

		$result = Create::create_post( $activity, array( $this->user_id ) );

		$this->assertFalse( $result );

		// Verify no post was created.
		$created_object = Posts::get_by_guid( 'https://example.com/objects/note_disabled' );
		$this->assertTrue( \is_wp_error( $created_object ) );

		\remove_filter( 'activitypub_pre_http_get_remote_object', $mock_callback );
	}

	/**
	 * Test create_post works when activitypub_create_posts option is enabled.
	 *
	 * @covers ::create_post
	 */
	public function test_create_post_enabled_by_option() {
		// Enable the option.
		\update_option( 'activitypub_create_posts', '1' );

		// Mock HTTP request for Remote_Actors::fetch_by_uri.
		$mock_callback = function ( $pre, $url_or_object ) {
			$url = \Activitypub\object_to_uri( $url_or_object );
			if ( 'https://example.com/users/testuser2' === $url ) {
				return array(
					'id'                => 'https://example.com/users/testuser2',
					'type'              => 'Person',
					'name'              => 'Test Actor 2',
					'preferredUsername' => 'testuser2',
					'url'               => 'https://example.com/users/testuser2',
					'inbox'             => 'https://example.com/users/testuser2/inbox',
				);
			}
			return $pre;
		};
		\add_filter( 'activitypub_pre_http_get_remote_object', $mock_callback, 10, 2 );

		$activity = array(
			'id'     => 'https://example.com/activities/create_enabled',
			'type'   => 'Create',
			'actor'  => 'https://example.com/users/testuser2',
			'to'     => array( 'https://www.w3.org/ns/activitystreams#Public' ),
			'object' => array(
				'id'           => 'https://example.com/objects/note_enabled',
				'type'         => 'Note',
				'content'      => '<p>This should be created</p>',
				'attributedTo' => 'https://example.com/users/testuser2',
				'published'    => '2023-01-01T12:00:00Z',
				'to'           => array( 'https://www.w3.org/ns/activitystreams#Public' ),
			),
		);

		$result = Create::create_post( $activity, array( $this->user_id ) );

		$this->assertInstanceOf( 'WP_Post', $result );

		// Verify post was created.
		$created_object = Posts::get_by_guid( 'https://example.com/objects/note_enabled' );
		$this->assertNotNull( $created_object );
		$this->assertStringContainsString( 'This should be created', $created_object->post_content );

		\remove_filter( 'activitypub_pre_http_get_remote_object', $mock_callback );
		\delete_option( 'activitypub_create_posts' );
	}

	/**
	 * Test that replies to non-existent posts return false.
	 *
	 * @covers \Activitypub\Collection\Interactions::add_comment
	 */
	public function test_reply_to_non_existent_post_returns_false() {
		$object = array(
			'actor'  => $this->user_url,
			'type'   => 'Create',
			'id'     => 'https://example.com/id/' . microtime( true ),
			'to'     => array( $this->user_url ),
			'cc'     => array( 'https://www.w3.org/ns/activitystreams#Public' ),
			'object' => array(
				'id'        => 'https://example.com/reply/123',
				'url'       => 'https://example.com/reply/123',
				'inReplyTo' => 'https://non-existent-site.example/post/999',
				'content'   => 'Reply to non-existent post',
			),
		);

		$result = Create::handle_create( $object, $this->user_id );

		$this->assertNull( $result );

		// Verify no comment was created.
		$args = array(
			'type'   => 'comment',
			'status' => 'any',
		);

		$query    = new \WP_Comment_Query( $args );
		$comments = $query->comments;

		// Filter to check for our specific comment.
		$found = array_filter(
			$comments,
			function ( $comment ) {
				return 'Reply to non-existent post' === $comment->comment_content;
			}
		);

		$this->assertEmpty( $found );
	}

	/**
	 * Test maybe_unbury removes URL from tombstone registry for Create activity.
	 *
	 * @covers ::maybe_unbury
	 */
	public function test_maybe_unbury_removes_url_for_create_activity() {
		$object_url = 'https://example.com/posts/unbury-create-' . time();

		// First, bury the URL.
		Tombstone::bury( $object_url );
		$this->assertTrue( Tombstone::exists_local( $object_url ) );

		// Create a mock activity object.
		$object = new Base_Object();
		$object->set_id( $object_url );
		$object->set_url( $object_url );
		$object->set_type( 'Note' );

		$activity = new Activity();
		$activity->set_type( 'Create' );
		$activity->set_object( $object );

		// Trigger maybe_unbury.
		Create::maybe_unbury( 1, $activity );

		// Verify URL was removed from tombstone registry.
		$this->assertFalse( Tombstone::exists_local( $object_url ) );
	}

	/**
	 * Test maybe_unbury removes URL from tombstone registry for Update activity.
	 *
	 * @covers ::maybe_unbury
	 */
	public function test_maybe_unbury_removes_url_for_update_activity() {
		$object_url = 'https://example.com/posts/unbury-update-' . time();

		// First, bury the URL.
		Tombstone::bury( $object_url );
		$this->assertTrue( Tombstone::exists_local( $object_url ) );

		// Create a mock activity object.
		$object = new Base_Object();
		$object->set_id( $object_url );
		$object->set_url( $object_url );
		$object->set_type( 'Note' );

		$activity = new Activity();
		$activity->set_type( 'Update' );
		$activity->set_object( $object );

		// Trigger maybe_unbury.
		Create::maybe_unbury( 1, $activity );

		// Verify URL was removed from tombstone registry.
		$this->assertFalse( Tombstone::exists_local( $object_url ) );
	}

	/**
	 * Test maybe_unbury ignores non-Create/Update activities.
	 *
	 * @covers ::maybe_unbury
	 */
	public function test_maybe_unbury_ignores_other_activities() {
		$object_url = 'https://example.com/posts/unbury-ignore-' . time();

		// First, bury the URL.
		Tombstone::bury( $object_url );
		$this->assertTrue( Tombstone::exists_local( $object_url ) );

		// Create a mock activity object.
		$object = new Base_Object();
		$object->set_id( $object_url );
		$object->set_url( $object_url );
		$object->set_type( 'Note' );

		// Test with Delete activity.
		$activity = new Activity();
		$activity->set_type( 'Delete' );
		$activity->set_object( $object );

		Create::maybe_unbury( 1, $activity );

		// URL should still be in tombstone registry.
		$this->assertTrue( Tombstone::exists_local( $object_url ) );

		// Test with Announce activity.
		$activity->set_type( 'Announce' );
		Create::maybe_unbury( 1, $activity );

		// URL should still be in tombstone registry.
		$this->assertTrue( Tombstone::exists_local( $object_url ) );

		// Clean up.
		\delete_option( 'activitypub_tombstone_urls' );
	}

	/**
	 * Test maybe_unbury handles activity with null object.
	 *
	 * @covers ::maybe_unbury
	 */
	public function test_maybe_unbury_handles_null_object() {
		$activity = new Activity();
		$activity->set_type( 'Create' );
		// Object is null/not set.

		// This should not throw any errors.
		Create::maybe_unbury( 1, $activity );

		// Just verify no exception was thrown.
		$this->assertTrue( true );
	}

	/**
	 * Test maybe_unbury removes both ID and URL when they differ.
	 *
	 * @covers ::maybe_unbury
	 */
	public function test_maybe_unbury_removes_both_id_and_url() {
		$object_id  = 'https://example.com/posts/id-unbury-' . time();
		$object_url = 'https://example.com/@user/posts/url-unbury-' . time();

		// Bury both URLs.
		Tombstone::bury( $object_id );
		Tombstone::bury( $object_url );
		$this->assertTrue( Tombstone::exists_local( $object_id ) );
		$this->assertTrue( Tombstone::exists_local( $object_url ) );

		$object = new Base_Object();
		$object->set_id( $object_id );
		$object->set_url( $object_url );
		$object->set_type( 'Note' );

		$activity = new Activity();
		$activity->set_type( 'Create' );
		$activity->set_object( $object );

		Create::maybe_unbury( 1, $activity );

		// Both ID and URL should be removed from tombstone registry.
		$this->assertFalse( Tombstone::exists_local( $object_id ) );
		$this->assertFalse( Tombstone::exists_local( $object_url ) );
	}

	/**
	 * Test soft delete to re-federate lifecycle.
	 *
	 * This tests the complete cycle of:
	 * 1. Burying a URL when Delete activity is sent (soft delete)
	 * 2. Unburying the URL when Create/Update activity is sent (re-federate)
	 *
	 * @covers ::maybe_unbury
	 */
	public function test_soft_delete_refederate_lifecycle() {
		$object_url = 'https://example.com/posts/lifecycle-' . time();

		$object = new Base_Object();
		$object->set_id( $object_url );
		$object->set_url( $object_url );
		$object->set_type( 'Note' );

		// Step 1: Simulate soft delete (Delete activity sent).
		$delete_activity = new Activity();
		$delete_activity->set_type( 'Delete' );
		$delete_activity->set_object( $object );

		\Activitypub\Handler\Delete::maybe_bury( 1, $delete_activity );

		// URL should be in tombstone registry.
		$this->assertTrue( Tombstone::exists_local( $object_url ), 'URL should be tombstoned after Delete' );

		// Step 2: Simulate re-federation (Update activity sent).
		$update_activity = new Activity();
		$update_activity->set_type( 'Update' );
		$update_activity->set_object( $object );

		Create::maybe_unbury( 2, $update_activity );

		// URL should be removed from tombstone registry.
		$this->assertFalse( Tombstone::exists_local( $object_url ), 'URL should not be tombstoned after Update' );

		// Step 3: Soft delete again.
		\Activitypub\Handler\Delete::maybe_bury( 3, $delete_activity );
		$this->assertTrue( Tombstone::exists_local( $object_url ), 'URL should be tombstoned again after second Delete' );

		// Step 4: Re-federate with Create.
		$create_activity = new Activity();
		$create_activity->set_type( 'Create' );
		$create_activity->set_object( $object );

		Create::maybe_unbury( 4, $create_activity );
		$this->assertFalse( Tombstone::exists_local( $object_url ), 'URL should not be tombstoned after Create' );

		// Clean up.
		\delete_option( 'activitypub_tombstone_urls' );
	}
}
