<?php
/**
 * Test Posts Collection.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Collection;

use Activitypub\Attachments;
use Activitypub\Collection\Posts;
use Activitypub\Post_Types;

/**
 * Posts Collection Test Class.
 *
 * @coversDefaultClass \Activitypub\Collection\Posts
 */
class Test_Posts extends \WP_UnitTestCase {

	/**
	 * Set up test environment.
	 */
	public function set_up() {
		parent::set_up();

		// Register required post types.
		Post_Types::register_remote_actors_post_type();
		Post_Types::register_post_post_type();

		// Mock HTTP requests for Remote_Actors::fetch_by_uri.
		add_filter( 'pre_http_request', array( $this, 'mock_http_request' ), 10, 3 );
	}

	/**
	 * Tear down test environment.
	 */
	public function tear_down() {
		remove_filter( 'pre_http_request', array( $this, 'mock_http_request' ) );

		$this->remove_added_uploads();

		parent::tear_down();
	}

	/**
	 * Mock HTTP requests for remote actor fetching and attachment downloads.
	 *
	 * @param mixed  $response The response to return.
	 * @param array  $parsed_args The parsed arguments.
	 * @param string $url The URL being requested.
	 * @return mixed The mocked response or original response.
	 */
	public function mock_http_request( $response, $parsed_args, $url ) {
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

		if ( 'https://nonexistent.com/users/unknown' === $url ) {
			return new \WP_Error( 'http_request_failed', 'Could not resolve host' );
		}

		// Mock attachment downloads.
		if ( 'https://example.com/image.jpg' === $url && isset( $parsed_args['filename'] ) ) {
			copy( AP_TESTS_DIR . '/data/assets/test.jpg', $parsed_args['filename'] );

			return array(
				'response' => array( 'code' => 200 ),
				'headers'  => array( 'content-type' => 'image/jpeg' ),
			);
		}

		return $response;
	}

	/**
	 * Test adding an object to the collection.
	 *
	 * @covers ::add
	 */
	public function test_add() {
		$activity = array(
			'object' => array(
				'id'           => 'https://example.com/objects/123',
				'type'         => 'Note',
				'name'         => 'Test Object',
				'content'      => '<p>This is a test object content</p>',
				'summary'      => 'Test summary',
				'attributedTo' => 'https://example.com/users/testuser',
				'published'    => '2023-01-01T12:00:00Z',
			),
		);

		$result = Posts::add( $activity, 1 );

		$this->assertInstanceOf( '\WP_Post', $result );
		$this->assertEquals( 'Test Object', $result->post_title );
		$this->assertEquals( Posts::POST_TYPE, $result->post_type );
		$this->assertEquals( 'publish', $result->post_status );
		$this->assertEquals( 'https://example.com/objects/123', $result->guid );
	}

	/**
	 * Test updating an existing object.
	 *
	 * @covers ::update
	 */
	public function test_update() {
		// First, create an object.
		$activity = array(
			'object' => array(
				'id'           => 'https://example.com/objects/456',
				'type'         => 'Note',
				'name'         => 'Original Title',
				'content'      => '<p>Original content</p>',
				'attributedTo' => 'https://example.com/users/testuser',
			),
		);

		$original_post = Posts::add( $activity, 1 );
		$this->assertInstanceOf( '\WP_Post', $original_post );

		// Now update it.
		$update_activity = array(
			'object' => array(
				'id'      => 'https://example.com/objects/456',
				'type'    => 'Note',
				'name'    => 'Updated Title',
				'content' => '<p>Updated content</p>',
			),
		);

		$updated_post = Posts::update( $update_activity, 1 );

		$this->assertInstanceOf( '\WP_Post', $updated_post );
		$this->assertEquals( 'Updated Title', $updated_post->post_title );
		$this->assertStringContainsString( 'Updated content', $updated_post->post_content );
		$this->assertEquals( $original_post->ID, $updated_post->ID );
	}

	/**
	 * Test updating a non-existent object.
	 *
	 * @covers ::update
	 */
	public function test_update_nonexistent() {
		$activity = array(
			'object' => array(
				'id'      => 'https://example.com/objects/nonexistent',
				'type'    => 'Note',
				'name'    => 'Updated Title',
				'content' => '<p>Updated content</p>',
			),
		);

		$result = Posts::update( $activity, 1 );

		$this->assertInstanceOf( '\WP_Error', $result );
	}

	/**
	 * Test getting an object by GUID.
	 *
	 * @covers ::get_by_guid
	 */
	public function test_get_by_guid() {
		// Create an object.
		$activity = array(
			'object' => array(
				'id'           => 'https://example.com/objects/789',
				'type'         => 'Note',
				'name'         => 'Test Object',
				'content'      => '<p>Test content</p>',
				'attributedTo' => 'https://example.com/users/testuser',
			),
		);

		$post = Posts::add( $activity, 1 );
		$this->assertInstanceOf( '\WP_Post', $post );

		// Test retrieval.
		$retrieved_post = Posts::get_by_guid( 'https://example.com/objects/789' );

		$this->assertInstanceOf( '\WP_Post', $retrieved_post );
		$this->assertEquals( $post->ID, $retrieved_post->ID );
		$this->assertEquals( 'Test Object', $retrieved_post->post_title );
	}

	/**
	 * Test getting a non-existent object by GUID.
	 *
	 * @covers ::get_by_guid
	 */
	public function test_get_by_guid_nonexistent() {
		$result = Posts::get_by_guid( 'https://example.com/objects/nonexistent' );

		$this->assertInstanceOf( '\WP_Error', $result );
	}

	/**
	 * Test activity to post conversion.
	 *
	 * @covers ::activity_to_post
	 */
	public function test_activity_to_post() {
		$activity = array(
			'id'        => 'https://example.com/objects/test',
			'type'      => 'Note',
			'name'      => 'Test Title',
			'content'   => '<p>Test content with <strong>HTML</strong></p>',
			'summary'   => 'Test summary',
			'published' => '2023-01-01T12:00:00Z',
		);

		// Use reflection to access the private method.
		$reflection = new \ReflectionClass( Posts::class );
		$method     = $reflection->getMethod( 'activity_to_post' );
		$method->setAccessible( true );

		try {
			$result = $method->invoke( null, $activity );
		} catch ( \Exception $exception ) {
			$result = $exception;
		}

		$this->assertIsArray( $result );
		$this->assertEquals( 'Test Title', $result['post_title'] );
		$this->assertEquals( 'Test summary', $result['post_excerpt'] );
		$this->assertEquals( Posts::POST_TYPE, $result['post_type'] );
		$this->assertEquals( 'publish', $result['post_status'] );
		$this->assertEquals( 'https://example.com/objects/test', $result['guid'] );
		$this->assertStringContainsString( 'Test content', $result['post_content'] );
	}

	/**
	 * Test activity to post conversion with invalid data.
	 *
	 * @covers ::activity_to_post
	 */
	public function test_activity_to_post_invalid() {
		// Use reflection to access the private method.
		$reflection = new \ReflectionClass( Posts::class );
		$method     = $reflection->getMethod( 'activity_to_post' );
		$method->setAccessible( true );

		try {
			$result = $method->invoke( null, 'invalid_data' );
		} catch ( \Exception $exception ) {
			$result = $exception;
		}

		$this->assertInstanceOf( '\WP_Error', $result );
	}

	/**
	 * Test activity to post conversion with minimal data.
	 *
	 * @covers ::activity_to_post
	 */
	public function test_activity_to_post_minimal() {
		$activity = array(
			'type' => 'Note',
		);

		// Use reflection to access the private method.
		$reflection = new \ReflectionClass( Posts::class );
		$method     = $reflection->getMethod( 'activity_to_post' );
		$method->setAccessible( true );

		try {
			$result = $method->invoke( null, $activity );
		} catch ( \Exception $exception ) {
			$result = $exception;
		}

		$this->assertIsArray( $result );
		$this->assertEquals( '', $result['post_title'] );
		$this->assertEquals( '', $result['post_content'] );
		$this->assertEquals( '', $result['post_excerpt'] );
		$this->assertEquals( Posts::POST_TYPE, $result['post_type'] );
		$this->assertEquals( 'publish', $result['post_status'] );
	}

	/**
	 * Test adding an object with multiple recipients.
	 *
	 * @covers ::add
	 * @covers ::get_recipients
	 */
	public function test_add_with_multiple_recipients() {
		$activity = array(
			'object' => array(
				'id'           => 'https://example.com/objects/multi-user',
				'type'         => 'Note',
				'name'         => 'Multi-User Post',
				'content'      => '<p>This post is for multiple users</p>',
				'attributedTo' => 'https://example.com/users/testuser',
			),
		);

		$result = Posts::add( $activity, array( 1, 2, 3 ) );

		$this->assertInstanceOf( '\WP_Post', $result );
		$this->assertEquals( 'Multi-User Post', $result->post_title );

		// Verify all recipients were added.
		$recipients = Posts::get_recipients( $result->ID );
		$this->assertCount( 3, $recipients );
		$this->assertContains( 1, $recipients );
		$this->assertContains( 2, $recipients );
		$this->assertContains( 3, $recipients );
	}

	/**
	 * Test adding an object with attachments.
	 *
	 * @covers ::add
	 */
	public function test_add_with_attachments() {
		$activity = array(
			'object' => array(
				'id'           => 'https://example.com/objects/with-attachment',
				'type'         => 'Note',
				'name'         => 'Post with Image',
				'content'      => '<p>Test content</p>',
				'attributedTo' => 'https://example.com/users/testuser',
				'attachment'   => array(
					array(
						'url'       => 'https://example.com/image.jpg',
						'mediaType' => 'image/jpeg',
						'name'      => 'Test Image',
						'type'      => 'Image',
					),
				),
			),
		);

		$result = Posts::add( $activity, 1 );

		$this->assertInstanceOf( '\WP_Post', $result );
		$this->assertEquals( 'Post with Image', $result->post_title );

		// Verify file was created in activitypub directory.
		$upload_dir = \wp_upload_dir();
		$file_dir   = $upload_dir['basedir'] . Attachments::$ap_posts_dir . $result->ID;
		$this->assertTrue( file_exists( $file_dir ), 'ActivityPub directory should exist' );

		// Verify file exists.
		$files = glob( $file_dir . '/*' );
		$this->assertCount( 1, $files, 'One file should be created' );

		// Verify content includes media markup with the file URL.
		$this->assertStringContainsString( Attachments::$ap_posts_dir . $result->ID . '/', $result->post_content );
	}

	/**
	 * Test updating an object with new attachments.
	 *
	 * @covers ::update
	 */
	public function test_update_with_new_attachments() {
		// Create initial post without attachments.
		$activity = array(
			'object' => array(
				'id'           => 'https://example.com/objects/update-test',
				'type'         => 'Note',
				'name'         => 'Original Post',
				'content'      => '<p>Original content</p>',
				'attributedTo' => 'https://example.com/users/testuser',
			),
		);

		$original_post = Posts::add( $activity, 1 );
		$this->assertInstanceOf( '\WP_Post', $original_post );

		// Verify initial recipient.
		$recipients = Posts::get_recipients( $original_post->ID );
		$this->assertCount( 1, $recipients );
		$this->assertContains( 1, $recipients );

		// Now update it with multiple new recipients.
		$update_activity = array(
			'object' => array(
				'id'      => 'https://example.com/objects/update-test',
				'type'    => 'Note',
				'name'    => 'Updated Title',
				'content' => '<p>Updated content</p>',
			),
		);

		$updated_post = Posts::update( $update_activity, array( 2, 3, 4 ) );

		$this->assertInstanceOf( '\WP_Post', $updated_post );
		$this->assertEquals( 'Updated Title', $updated_post->post_title );

		// Verify all recipients are present (original + new ones).
		$recipients = Posts::get_recipients( $updated_post->ID );
		$this->assertCount( 4, $recipients );
		$this->assertContains( 1, $recipients );
		$this->assertContains( 2, $recipients );
		$this->assertContains( 3, $recipients );
		$this->assertContains( 4, $recipients );
	}

	/**
	 * Test updating with duplicate recipients doesn't create duplicates.
	 *
	 * @covers ::update
	 * @covers ::get_recipients
	 * @covers ::has_recipient
	 */
	public function test_update_prevents_duplicate_recipients() {
		// Create an object.
		$activity = array(
			'object' => array(
				'id'           => 'https://example.com/objects/no-duplicates',
				'type'         => 'Note',
				'name'         => 'No Duplicates',
				'content'      => '<p>Test deduplication</p>',
				'attributedTo' => 'https://example.com/users/testuser',
			),
		);

		$post = Posts::add( $activity, array( 1, 2 ) );
		$this->assertInstanceOf( '\WP_Post', $post );

		// Update with overlapping recipients.
		$update_activity = array(
			'object' => array(
				'id'      => 'https://example.com/objects/no-duplicates',
				'type'    => 'Note',
				'name'    => 'Updated',
				'content' => '<p>Updated</p>',
			),
		);

		$updated_post = Posts::update( $update_activity, array( 2, 3 ) );

		$this->assertInstanceOf( '\WP_Post', $updated_post );

		// Verify no duplicates - should have 1, 2, 3 (not 2 twice).
		$recipients = Posts::get_recipients( $updated_post->ID );
		$this->assertCount( 3, $recipients );
		$this->assertContains( 1, $recipients );
		$this->assertContains( 2, $recipients );
		$this->assertContains( 3, $recipients );

		// Verify has_recipient works.
		$this->assertTrue( Posts::has_recipient( $updated_post->ID, 1 ) );
		$this->assertTrue( Posts::has_recipient( $updated_post->ID, 2 ) );
		$this->assertTrue( Posts::has_recipient( $updated_post->ID, 3 ) );
		$this->assertFalse( Posts::has_recipient( $updated_post->ID, 4 ) );
	}

	/**
	 * Test adding with single recipient still works (backward compatibility).
	 *
	 * @covers ::add
	 * @covers ::get_recipients
	 */
	public function test_add_with_single_recipient_backward_compatibility() {
		$activity = array(
			'object' => array(
				'id'           => 'https://example.com/objects/single-user',
				'type'         => 'Note',
				'name'         => 'Single User Post',
				'content'      => '<p>This post is for one user</p>',
				'attributedTo' => 'https://example.com/users/testuser',
			),
		);

		$result = Posts::add( $activity, 1 );

		$this->assertInstanceOf( '\WP_Post', $result );

		// Verify single recipient was added.
		$recipients = Posts::get_recipients( $result->ID );
		$this->assertCount( 1, $recipients );
		$this->assertContains( 1, $recipients );
	}

	/**
	 * Test updating with single recipient still works (backward compatibility).
	 *
	 * @covers ::update
	 * @covers ::get_recipients
	 */
	public function test_update_with_single_recipient_backward_compatibility() {
		// Create an object.
		$activity = array(
			'object' => array(
				'id'           => 'https://example.com/objects/single-update',
				'type'         => 'Note',
				'name'         => 'Original',
				'content'      => '<p>Original</p>',
				'attributedTo' => 'https://example.com/users/testuser',
			),
		);

		Posts::add( $activity, 1 );

		// Update with single recipient.
		$update_activity = array(
			'object' => array(
				'id'      => 'https://example.com/objects/single-update',
				'type'    => 'Note',
				'name'    => 'Updated',
				'content' => '<p>Updated</p>',
			),
		);

		$updated_post = Posts::update( $update_activity, 2 );

		$this->assertInstanceOf( '\WP_Post', $updated_post );

		// Verify both recipients are present.
		$recipients = Posts::get_recipients( $updated_post->ID );
		$this->assertCount( 2, $recipients );
		$this->assertContains( 1, $recipients );
		$this->assertContains( 2, $recipients );
	}

	/**
	 * Test add_recipient method.
	 *
	 * @covers ::add_recipient
	 * @covers ::has_recipient
	 * @covers ::get_recipients
	 */
	public function test_add_recipient() {
		$activity = array(
			'object' => array(
				'id'           => 'https://example.com/objects/add-recipient',
				'type'         => 'Note',
				'name'         => 'Add Recipient Test',
				'content'      => '<p>Test add_recipient</p>',
				'attributedTo' => 'https://example.com/users/testuser',
			),
		);

		$post = Posts::add( $activity, 1 );

		// Add another recipient.
		$result = Posts::add_recipient( $post->ID, 2 );
		$this->assertTrue( $result );

		// Verify recipient was added.
		$this->assertTrue( Posts::has_recipient( $post->ID, 2 ) );
		$recipients = Posts::get_recipients( $post->ID );
		$this->assertCount( 2, $recipients );

		// Adding duplicate should return true but not add again.
		$result = Posts::add_recipient( $post->ID, 2 );
		$this->assertTrue( $result );
		$recipients = Posts::get_recipients( $post->ID );
		$this->assertCount( 2, $recipients );
	}

	/**
	 * Test add_recipients method.
	 *
	 * @covers ::add_recipients
	 * @covers ::get_recipients
	 */
	public function test_add_recipients() {
		$activity = array(
			'object' => array(
				'id'           => 'https://example.com/objects/add-recipients',
				'type'         => 'Note',
				'name'         => 'Add Recipients Test',
				'content'      => '<p>Test add_recipients</p>',
				'attributedTo' => 'https://example.com/users/testuser',
			),
		);

		$post = Posts::add( $activity, 1 );

		// Add multiple recipients.
		Posts::add_recipients( $post->ID, array( 2, 3, 4 ) );

		// Verify all recipients were added.
		$recipients = Posts::get_recipients( $post->ID );
		$this->assertCount( 4, $recipients );
		$this->assertContains( 1, $recipients );
		$this->assertContains( 2, $recipients );
		$this->assertContains( 3, $recipients );
		$this->assertContains( 4, $recipients );
	}

	/**
	 * Test remove_recipient method.
	 *
	 * @covers ::remove_recipient
	 * @covers ::has_recipient
	 * @covers ::get_recipients
	 */
	public function test_remove_recipient() {
		$activity = array(
			'object' => array(
				'id'           => 'https://example.com/objects/remove-recipient',
				'type'         => 'Note',
				'name'         => 'Remove Recipient Test',
				'content'      => '<p>Test remove_recipient</p>',
				'attributedTo' => 'https://example.com/users/testuser',
			),
		);

		$post = Posts::add( $activity, array( 1, 2, 3 ) );

		// Remove a recipient.
		$result = Posts::remove_recipient( $post->ID, 2 );
		$this->assertTrue( $result );

		// Verify recipient was removed.
		$this->assertFalse( Posts::has_recipient( $post->ID, 2 ) );
		$recipients = Posts::get_recipients( $post->ID );
		$this->assertCount( 2, $recipients );
		$this->assertContains( 1, $recipients );
		$this->assertContains( 3, $recipients );
		$this->assertNotContains( 2, $recipients );
	}

	/**
	 * Test that add with existing post calls update instead of creating duplicate.
	 *
	 * @covers ::add
	 * @covers ::update
	 * @covers ::get_recipients
	 */
	public function test_add_existing_post_adds_recipients() {
		$activity = array(
			'object' => array(
				'id'           => 'https://example.com/objects/existing-post',
				'type'         => 'Note',
				'name'         => 'Existing Post',
				'content'      => '<p>Test existing post</p>',
				'attributedTo' => 'https://example.com/users/testuser',
			),
		);

		// First add.
		$post1 = Posts::add( $activity, 1 );
		$this->assertInstanceOf( '\WP_Post', $post1 );

		// Second add with same activity ID but different recipient.
		$post2 = Posts::add( $activity, 2 );
		$this->assertInstanceOf( '\WP_Post', $post2 );

		// Should be the same post.
		$this->assertEquals( $post1->ID, $post2->ID );

		// Should have both recipients.
		$recipients = Posts::get_recipients( $post1->ID );
		$this->assertCount( 2, $recipients );
		$this->assertContains( 1, $recipients );
		$this->assertContains( 2, $recipients );

		// Verify only one post exists with this GUID.
		$posts = \get_posts(
			array(
				'post_type'      => Posts::POST_TYPE,
				'guid'           => 'https://example.com/objects/existing-post',
				'posts_per_page' => -1,
			)
		);
		$this->assertCount( 1, $posts );
		// Verify no attachments initially.
		$attachments = get_attached_media( '', $post1->ID );
		$this->assertEmpty( $attachments );

		// Update with attachments.
		$update_activity = array(
			'object' => array(
				'id'         => 'https://example.com/objects/existing-post',
				'type'       => 'Note',
				'name'       => 'Updated Post',
				'content'    => '<p>Updated content</p>',
				'attachment' => array(
					array(
						'url'       => 'https://example.com/image.jpg',
						'mediaType' => 'image/jpeg',
						'name'      => 'New Image',
						'type'      => 'Image',
					),
				),
			),
		);

		$updated_post = Posts::update( $update_activity, 1 );
		$this->assertInstanceOf( '\WP_Post', $updated_post );

		// Verify file was created.
		$upload_dir = \wp_upload_dir();
		$file_dir   = $upload_dir['basedir'] . Attachments::$ap_posts_dir . $updated_post->ID;
		$this->assertTrue( file_exists( $file_dir ), 'ActivityPub directory should exist' );

		$files = glob( $file_dir . '/*' );
		$this->assertCount( 1, $files, 'One file should be created' );
	}

	/**
	 * Test updating an object with changed attachments.
	 *
	 * @covers ::update
	 */
	public function test_update_with_changed_attachments() {
		// Create post with attachment.
		$activity = array(
			'object' => array(
				'id'           => 'https://example.com/objects/change-test',
				'type'         => 'Note',
				'name'         => 'Original Post',
				'content'      => '<p>Original content</p>',
				'attributedTo' => 'https://example.com/users/testuser',
				'attachment'   => array(
					array(
						'url'       => 'https://example.com/image.jpg',
						'mediaType' => 'image/jpeg',
						'name'      => 'Original Image',
						'type'      => 'Image',
					),
				),
			),
		);

		$original_post = Posts::add( $activity, 1 );

		// Verify original file was created.
		$upload_dir = \wp_upload_dir();
		$file_dir   = $upload_dir['basedir'] . Attachments::$ap_posts_dir . $original_post->ID;
		$this->assertTrue( file_exists( $file_dir ), 'ActivityPub directory should exist' );
		$original_files = glob( $file_dir . '/*' );
		$this->assertCount( 1, $original_files );

		// Update with different attachment URL.
		$update_activity = array(
			'object' => array(
				'id'         => 'https://example.com/objects/change-test',
				'type'       => 'Note',
				'name'       => 'Updated Post',
				'content'    => '<p>Updated content</p>',
				'attachment' => array(
					array(
						'url'       => 'https://example.com/new-image.jpg',
						'mediaType' => 'image/jpeg',
						'name'      => 'New Image',
						'type'      => 'Image',
					),
				),
			),
		);

		// Mock the new image URL.
		add_filter(
			'pre_http_request',
			function ( $response, $parsed_args, $url ) {
				if ( 'https://example.com/new-image.jpg' === $url && isset( $parsed_args['filename'] ) ) {
					copy( AP_TESTS_DIR . '/data/assets/test.jpg', $parsed_args['filename'] );

					return array(
						'response' => array( 'code' => 200 ),
						'headers'  => array( 'content-type' => 'image/jpeg' ),
					);
				}
				return $response;
			},
			11,
			3
		);

		Posts::update( $update_activity, 1 );

		// Verify old file was deleted and new file was created.
		$new_files = glob( $file_dir . '/*' );
		$this->assertCount( 1, $new_files );
		$this->assertNotEquals( basename( $original_files[0] ), basename( $new_files[0] ), 'New file should have different name' );
	}

	/**
	 * Test updating an object keeps same attachments when unchanged.
	 *
	 * @covers ::update
	 */
	public function test_update_keeps_same_attachments() {
		// Create post with attachment.
		$activity = array(
			'object' => array(
				'id'           => 'https://example.com/objects/keep-test',
				'type'         => 'Note',
				'name'         => 'Original Post',
				'content'      => '<p>Original content</p>',
				'attributedTo' => 'https://example.com/users/testuser',
				'attachment'   => array(
					array(
						'url'       => 'https://example.com/image.jpg',
						'mediaType' => 'image/jpeg',
						'name'      => 'Test Image',
						'type'      => 'Image',
					),
				),
			),
		);

		$original_post = Posts::add( $activity, 1 );

		// Verify original file was created.
		$upload_dir = \wp_upload_dir();
		$file_dir   = $upload_dir['basedir'] . Attachments::$ap_posts_dir . $original_post->ID;
		$this->assertTrue( file_exists( $file_dir ), 'ActivityPub directory should exist' );
		$original_files = glob( $file_dir . '/*' );
		$this->assertCount( 1, $original_files );

		// Update with same attachment URL (just change content).
		$update_activity = array(
			'object' => array(
				'id'         => 'https://example.com/objects/keep-test',
				'type'       => 'Note',
				'name'       => 'Updated Post',
				'content'    => '<p>Updated content</p>',
				'attachment' => array(
					array(
						'url'       => 'https://example.com/image.jpg',
						'mediaType' => 'image/jpeg',
						'name'      => 'Test Image',
						'type'      => 'Image',
					),
				),
			),
		);

		Posts::update( $update_activity, 1 );

		// Verify file still exists (should not be recreated since attachment hasn't changed).
		// Note: With file-based storage, we don't detect unchanged attachments, so files get replaced.
		$new_files = glob( $file_dir . '/*' );
		$this->assertCount( 1, $new_files, 'File should still exist after update' );
	}
}
