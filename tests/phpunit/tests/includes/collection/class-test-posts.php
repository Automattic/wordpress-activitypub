<?php
/**
 * Test Posts Collection.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Collection;

use Activitypub\Collection\Posts;
use Activitypub\Collection\Remote_Actors;
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

		$result = $method->invoke( null, $activity );

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

		$result = $method->invoke( null, 'invalid_data' );

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

		$result = $method->invoke( null, $activity );

		$this->assertIsArray( $result );
		$this->assertEquals( '', $result['post_title'] );
		$this->assertEquals( '', $result['post_content'] );
		$this->assertEquals( '', $result['post_excerpt'] );
		$this->assertEquals( Posts::POST_TYPE, $result['post_type'] );
		$this->assertEquals( 'publish', $result['post_status'] );
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

		$result = Posts::add( $activity );

		$this->assertInstanceOf( '\WP_Post', $result );
		$this->assertEquals( 'Post with Image', $result->post_title );

		// Verify attachment was created.
		$attachments = get_attached_media( '', $result->ID );
		$this->assertCount( 1, $attachments );

		$attachment = reset( $attachments );
		$this->assertEquals( 'attachment', $attachment->post_type );
		$this->assertEquals( $result->ID, $attachment->post_parent );

		// Verify source URL was stored.
		$source_url = get_post_meta( $attachment->ID, '_activitypub_source_url', true );
		$this->assertEquals( 'https://example.com/image.jpg', $source_url );

		// Verify alt text was stored.
		$alt_text = get_post_meta( $attachment->ID, '_wp_attachment_image_alt', true );
		$this->assertEquals( 'Test Image', $alt_text );

		// Verify content includes media markup.
		$this->assertStringContainsString( 'wp-image-' . $attachment->ID, $result->post_content );
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

		$original_post = Posts::add( $activity );
		$this->assertInstanceOf( '\WP_Post', $original_post );

		// Verify no attachments initially.
		$attachments = get_attached_media( '', $original_post->ID );
		$this->assertEmpty( $attachments );

		// Update with attachments.
		$update_activity = array(
			'object' => array(
				'id'         => 'https://example.com/objects/update-test',
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

		$updated_post = Posts::update( $update_activity );
		$this->assertInstanceOf( '\WP_Post', $updated_post );

		// Verify attachment was added.
		$attachments = get_attached_media( '', $updated_post->ID );
		$this->assertCount( 1, $attachments );

		$attachment = reset( $attachments );
		$source_url = get_post_meta( $attachment->ID, '_activitypub_source_url', true );
		$this->assertEquals( 'https://example.com/image.jpg', $source_url );
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

		$original_post        = Posts::add( $activity );
		$original_attachments = get_attached_media( '', $original_post->ID );
		$this->assertCount( 1, $original_attachments );
		$original_attachment_id = reset( $original_attachments )->ID;

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

		$updated_post = Posts::update( $update_activity );

		// Verify old attachment was deleted.
		$this->assertNull( get_post( $original_attachment_id ) );

		// Verify new attachment was created.
		$new_attachments = get_attached_media( '', $updated_post->ID );
		$this->assertCount( 1, $new_attachments );

		$new_attachment = reset( $new_attachments );
		$source_url     = get_post_meta( $new_attachment->ID, '_activitypub_source_url', true );
		$this->assertEquals( 'https://example.com/new-image.jpg', $source_url );
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

		$original_post        = Posts::add( $activity );
		$original_attachments = get_attached_media( '', $original_post->ID );
		$this->assertCount( 1, $original_attachments );
		$original_attachment_id = reset( $original_attachments )->ID;

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

		$updated_post = Posts::update( $update_activity );

		// Verify attachment was NOT recreated (same ID still exists).
		$updated_attachments = get_attached_media( '', $updated_post->ID );
		$this->assertCount( 1, $updated_attachments );
		$this->assertEquals( $original_attachment_id, reset( $updated_attachments )->ID );
	}
}
