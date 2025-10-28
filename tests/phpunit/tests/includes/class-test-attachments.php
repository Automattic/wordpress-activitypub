<?php
/**
 * Test Attachments class.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

use Activitypub\Attachments;
use Activitypub\Post_Types;

/**
 * Attachments Test Class.
 *
 * @coversDefaultClass \Activitypub\Attachments
 */
class Test_Attachments extends \WP_UnitTestCase {

	/**
	 * Test post ID.
	 *
	 * @var int
	 */
	protected static $post_id;

	/**
	 * Test author ID.
	 *
	 * @var int
	 */
	protected static $author_id;

	/**
	 * Set up before class.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		// Register post type for tests.
		Post_Types::register_post_post_type();

		// Create test author.
		self::$author_id = self::factory()->user->create( array( 'role' => 'editor' ) );

		// Create test post.
		self::$post_id = self::factory()->post->create(
			array(
				'post_type'    => 'ap_post',
				'post_content' => 'Original content',
				'post_author'  => self::$author_id,
			)
		);
	}

	/**
	 * Clean up after class.
	 */
	public static function tear_down_after_class() {
		\wp_delete_post( self::$post_id, true );
		\wp_delete_user( self::$author_id );

		parent::tear_down_after_class();
	}

	/**
	 * Set up each test.
	 */
	public function set_up() {
		parent::set_up();

		// Mock HTTP requests only for remote attachment tests.
		\add_filter( 'pre_http_request', array( $this, 'mock_download_url' ), 10, 3 );
		\add_filter( 'wp_delete_file', '__return_empty_string' ); // Prevent actual file deletion during tests.
	}

	/**
	 * Tear down each test.
	 */
	public function tear_down() {
		\remove_filter( 'pre_http_request', array( $this, 'mock_download_url' ) );
		\remove_filter( 'wp_delete_file', '__return_empty_string' );

		// Reset post content.
		\wp_update_post(
			array(
				'ID'           => self::$post_id,
				'post_content' => 'Original content',
			)
		);

		parent::tear_down();
	}

	/**
	 * Mock HTTP download for remote URLs.
	 *
	 * This follows the WordPress core pattern for mocking download_url().
	 * Handles all test URLs for both attachment and inline image tests.
	 *
	 * @param mixed  $response The response to return.
	 * @param array  $parsed_args The parsed arguments.
	 * @param string $url The URL being requested.
	 * @return mixed The mocked response or original response.
	 */
	public function mock_download_url( $response, $parsed_args, $url ) {
		// Accept any URL that matches the example.com domain pattern (except missing.jpg).
		if ( preg_match( '#^https://example\.com/(?!missing\.jpg).+#', $url ) && isset( $parsed_args['filename'] ) ) {
			copy( AP_TESTS_DIR . '/data/assets/test.jpg', $parsed_args['filename'] );

			return array(
				'response' => array( 'code' => 200 ),
				'headers'  => array( 'content-type' => 'image/jpeg' ),
			);
		}

		// Mock the missing.jpg URL to simulate download errors.
		if ( 'https://example.com/missing.jpg' === $url ) {
			return new \WP_Error( 'http_request_failed', 'Could not download file' );
		}

		return $response;
	}

	/**
	 * Test processing empty attachments array.
	 *
	 * @covers ::import
	 */
	public function test_process_empty_attachments() {
		$result = Attachments::import( array(), self::$post_id, self::$author_id );

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * Test normalize_attachment with array input.
	 *
	 * @covers ::normalize_attachment
	 */
	public function test_normalize_attachment_array() {
		$attachment = array(
			'url'       => 'https://example.com/image.jpg',
			'mediaType' => 'image/jpeg',
			'name'      => 'Test Image',
			'type'      => 'Image',
		);

		$reflection = new \ReflectionClass( Attachments::class );
		$method     = $reflection->getMethod( 'normalize_attachment' );
		$method->setAccessible( true );

		$result = $method->invoke( null, $attachment );

		$this->assertIsArray( $result );
		$this->assertEquals( 'https://example.com/image.jpg', $result['url'] );
		$this->assertEquals( 'image/jpeg', $result['mediaType'] );
		$this->assertEquals( 'Test Image', $result['name'] );
		$this->assertEquals( 'Image', $result['type'] );
	}

	/**
	 * Test normalize_attachment with object input.
	 *
	 * @covers ::normalize_attachment
	 */
	public function test_normalize_attachment_object() {
		$attachment = (object) array(
			'url'       => 'https://example.com/image.jpg',
			'mediaType' => 'image/jpeg',
			'name'      => 'Test Image',
			'type'      => 'Image',
		);

		$reflection = new \ReflectionClass( Attachments::class );
		$method     = $reflection->getMethod( 'normalize_attachment' );
		$method->setAccessible( true );

		$result = $method->invoke( null, $attachment );

		$this->assertIsArray( $result );
		$this->assertEquals( 'https://example.com/image.jpg', $result['url'] );
		$this->assertEquals( 'image/jpeg', $result['mediaType'] );
		$this->assertEquals( 'Test Image', $result['name'] );
		$this->assertEquals( 'Image', $result['type'] );
	}

	/**
	 * Test normalize_attachment with missing URL.
	 *
	 * @covers ::normalize_attachment
	 */
	public function test_normalize_attachment_missing_url() {
		$attachment = array(
			'mediaType' => 'image/jpeg',
			'name'      => 'Test Image',
		);

		$reflection = new \ReflectionClass( Attachments::class );
		$method     = $reflection->getMethod( 'normalize_attachment' );
		$method->setAccessible( true );

		$result = $method->invoke( null, $attachment );

		$this->assertFalse( $result );
	}

	/**
	 * Test normalize_attachment with minimal data.
	 *
	 * @covers ::normalize_attachment
	 */
	public function test_normalize_attachment_minimal() {
		$attachment = array(
			'url' => 'https://example.com/image.jpg',
		);

		$reflection = new \ReflectionClass( Attachments::class );
		$method     = $reflection->getMethod( 'normalize_attachment' );
		$method->setAccessible( true );

		$result = $method->invoke( null, $attachment );

		$this->assertIsArray( $result );
		$this->assertEquals( 'https://example.com/image.jpg', $result['url'] );
		$this->assertEquals( '', $result['mediaType'] );
		$this->assertEquals( '', $result['name'] );
		$this->assertEquals( 'Document', $result['type'] );
	}

	/**
	 * Test processing local file attachment (like Mastodon import).
	 *
	 * @covers ::import
	 * @covers ::save_attachment
	 */
	public function test_process_local_file_attachment() {
		$attachments = array(
			array(
				'url'       => AP_TESTS_DIR . '/data/assets/test.jpg',
				'mediaType' => 'image/jpeg',
				'name'      => 'Test Local Image',
				'type'      => 'Image',
			),
		);

		$result = Attachments::import( $attachments, self::$post_id, self::$author_id );

		$this->assertIsArray( $result );
		$this->assertCount( 1, $result );
		$this->assertIsInt( $result[0] );

		// Verify attachment was created.
		$attachment = get_post( $result[0] );
		$this->assertEquals( 'attachment', $attachment->post_type );
		$this->assertEquals( self::$author_id, $attachment->post_author );
		$this->assertEquals( self::$post_id, $attachment->post_parent );

		// Verify source URL was stored.
		$source_url = get_post_meta( $result[0], '_source_url', true );
		$this->assertEquals( AP_TESTS_DIR . '/data/assets/test.jpg', $source_url );

		// Verify alt text was stored for image.
		$alt_text = get_post_meta( $result[0], '_wp_attachment_image_alt', true );
		$this->assertEquals( 'Test Local Image', $alt_text );

		// Verify content was updated with media markup.
		$post = get_post( self::$post_id );
		$this->assertStringContainsString( 'Original content', $post->post_content );
		$this->assertStringContainsString( 'wp-image-' . $result[0], $post->post_content );
	}

	/**
	 * Test processing multiple local file attachments.
	 *
	 * @covers ::import
	 * @covers ::save_attachment
	 */
	public function test_process_multiple_attachments() {
		$attachments = array(
			array(
				'url'       => AP_TESTS_DIR . '/data/assets/test.jpg',
				'mediaType' => 'image/jpeg',
				'name'      => 'First Image',
				'type'      => 'Image',
			),
			array(
				'url'       => AP_TESTS_DIR . '/data/assets/test.jpg',
				'mediaType' => 'image/jpeg',
				'name'      => 'Second Image',
				'type'      => 'Image',
			),
		);

		$result = Attachments::import( $attachments, self::$post_id, self::$author_id );

		$this->assertIsArray( $result );
		$this->assertCount( 2, $result );

		// Verify content includes gallery block.
		$post = get_post( self::$post_id );
		$this->assertStringContainsString( 'wp:gallery', $post->post_content );
	}

	/**
	 * Test processing attachment with object array (like Mastodon import).
	 *
	 * @covers ::import
	 * @covers ::normalize_attachment
	 */
	public function test_process_attachment_objects() {
		$attachments = array(
			(object) array(
				'url'       => AP_TESTS_DIR . '/data/assets/test.jpg',
				'mediaType' => 'image/jpeg',
				'name'      => 'Test Image',
				'type'      => 'Image',
			),
		);

		$result = Attachments::import( $attachments, self::$post_id, self::$author_id );

		$this->assertIsArray( $result );
		$this->assertCount( 1, $result );
		$this->assertIsInt( $result[0] );
	}

	/**
	 * Test processing attachment with download error.
	 *
	 * @covers ::import
	 * @covers ::save_attachment
	 */
	public function test_process_attachment_download_error() {
		$attachments = array(
			array(
				'url'       => 'https://example.com/missing.jpg',
				'mediaType' => 'image/jpeg',
				'name'      => 'Missing Image',
				'type'      => 'Image',
			),
		);

		$result = Attachments::import( $attachments, self::$post_id, self::$author_id );

		// Should return empty array when attachment fails.
		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * Test that non-image attachments don't get alt text.
	 *
	 * @covers ::save_attachment
	 */
	public function test_non_image_no_alt_text() {
		$attachments = array(
			array(
				'url'       => AP_TESTS_DIR . '/data/assets/test.jpg',
				'mediaType' => 'video/mp4',  // Treating as video, not image.
				'name'      => 'Test Video',
				'type'      => 'Video',
			),
		);

		$result = Attachments::import( $attachments, self::$post_id, self::$author_id );

		$this->assertIsArray( $result );
		$this->assertCount( 1, $result );

		// Verify alt text was NOT stored for non-image.
		$alt_text = get_post_meta( $result[0], '_wp_attachment_image_alt', true );
		$this->assertEmpty( $alt_text );
	}

	/**
	 * Test appending media to empty post content.
	 *
	 * @covers ::append_media_to_post_content
	 */
	public function test_append_media_to_empty_content() {
		// Update post to have empty content.
		wp_update_post(
			array(
				'ID'           => self::$post_id,
				'post_content' => '',
			)
		);

		$attachments = array(
			array(
				'url'       => 'https://example.com/image.jpg',
				'mediaType' => 'image/jpeg',
				'name'      => 'Test Image',
				'type'      => 'Image',
			),
		);

		Attachments::import( $attachments, self::$post_id, self::$author_id );

		// Verify no extra separator when content is empty.
		$post = get_post( self::$post_id );
		$this->assertStringNotContainsString( "\n\n\n", $post->post_content );
		$this->assertStringStartsWith( '<!--', $post->post_content );
	}

	/**
	 * Test inline image processing without attachments.
	 *
	 * @covers ::import_inline_images
	 */
	public function test_process_inline_images_only() {
		// Create a post with inline images.
		$post_content = '<p>Check out this image: <img src="https://example.com/image1.jpg" alt="Test image"> and this one <img src="https://example.com/image2.png" alt=""/></p>';
		$post_id      = self::factory()->post->create(
			array(
				'post_content' => $post_content,
				'post_type'    => 'ap_post',
			)
		);

		// Process inline images.
		Attachments::import( array(), $post_id, self::$author_id );

		// Get updated post.
		$post = \get_post( $post_id );

		// Verify images were replaced with local URLs.
		$this->assertStringNotContainsString( 'https://example.com/image1.jpg', $post->post_content );
		$this->assertStringNotContainsString( 'https://example.com/image2.png', $post->post_content );
		$this->assertStringContainsString( 'wp-content/uploads', $post->post_content );

		// Verify attachments were created.
		$attachments = \get_attached_media( '', $post_id );
		$this->assertCount( 2, $attachments );

		// Clean up.
		\wp_delete_post( $post_id, true );
	}

	/**
	 * Test inline images with overlapping attachments.
	 *
	 * @covers ::import
	 * @covers ::import_inline_images
	 */
	public function test_inline_images_with_attachment_overlap() {
		// Create a post with inline images.
		$post_content = '<p>Inline image: <img src="https://example.com/shared.jpg" alt="Shared"> and unique: <img src="https://example.com/inline-only.jpg" alt=""/></p>';
		$post_id      = self::factory()->post->create(
			array(
				'post_content' => $post_content,
				'post_type'    => 'ap_post',
			)
		);

		// Attachments array with one overlapping and one unique.
		$attachments = array(
			array(
				'type'      => 'Image',
				'url'       => 'https://example.com/shared.jpg',
				'mediaType' => 'image/jpeg',
				'name'      => 'Shared image',
			),
			array(
				'type'      => 'Image',
				'url'       => 'https://example.com/attachment-only.jpg',
				'mediaType' => 'image/jpeg',
				'name'      => 'Attachment only',
			),
		);

		// Process attachments (which also processes inline images).
		Attachments::import( $attachments, $post_id, self::$author_id );

		// Get updated post.
		$post = \get_post( $post_id );

		// Verify inline images were replaced.
		$this->assertStringNotContainsString( 'https://example.com/shared.jpg', $post->post_content );
		$this->assertStringNotContainsString( 'https://example.com/inline-only.jpg', $post->post_content );
		$this->assertStringContainsString( 'wp-content/uploads', $post->post_content );

		// Verify correct number of attachments (no duplicates).
		$attachments = \get_attached_media( '', $post_id );
		$this->assertCount( 3, $attachments ); // shared.jpg, inline-only.jpg, attachment-only.jpg.

		// Verify gallery was added for attachment-only.jpg.
		$this->assertStringContainsString( '<!-- wp:gallery', $post->post_content );

		// Clean up.
		\wp_delete_post( $post_id, true );
	}

	/**
	 * Test inline images without any overlap with attachments.
	 *
	 * @covers ::import
	 * @covers ::import_inline_images
	 */
	public function test_inline_images_no_overlap() {
		// Create a post with inline images.
		$post_content = '<p>First: <img src="https://example.com/inline1.jpg" alt=""> Second: <img src="https://example.com/inline2.jpg" alt=""></p>';
		$post_id      = self::factory()->post->create(
			array(
				'post_content' => $post_content,
				'post_type'    => 'ap_post',
			)
		);

		// Completely different attachments.
		$attachments = array(
			array(
				'type'      => 'Image',
				'url'       => 'https://example.com/attachment1.jpg',
				'mediaType' => 'image/jpeg',
				'name'      => 'Attachment 1',
			),
			array(
				'type'      => 'Image',
				'url'       => 'https://example.com/attachment2.jpg',
				'mediaType' => 'image/jpeg',
				'name'      => 'Attachment 2',
			),
		);

		// Process attachments.
		Attachments::import( $attachments, $post_id, self::$author_id );

		// Get updated post.
		$post = \get_post( $post_id );

		// Verify all inline images were replaced.
		$this->assertStringNotContainsString( 'https://example.com/inline1.jpg', $post->post_content );
		$this->assertStringNotContainsString( 'https://example.com/inline2.jpg', $post->post_content );

		// Verify all 4 images are attached (2 inline + 2 attachments).
		$attachments = \get_attached_media( '', $post_id );
		$this->assertCount( 4, $attachments );

		// Verify gallery was added for the attachment images.
		$this->assertStringContainsString( '<!-- wp:gallery', $post->post_content );

		// Clean up.
		\wp_delete_post( $post_id, true );
	}

	/**
	 * Test that duplicate inline images are not processed twice.
	 *
	 * @covers ::import_inline_images
	 */
	public function test_duplicate_inline_images() {
		// Create a post with duplicate inline images.
		$post_content = '<p>Image 1: <img src="https://example.com/same.jpg" alt=""> Image 2: <img src="https://example.com/same.jpg" alt=""></p>';
		$post_id      = self::factory()->post->create(
			array(
				'post_content' => $post_content,
				'post_type'    => 'ap_post',
			)
		);

		// Process with empty attachments array.
		Attachments::import( array(), $post_id, self::$author_id );

		// Verify only one attachment was created despite duplicate URLs.
		$attachments = \get_attached_media( '', $post_id );
		$this->assertCount( 1, $attachments );

		// Get updated post.
		$post = \get_post( $post_id );

		// Both instances should be replaced with the same local URL.
		$this->assertStringNotContainsString( 'https://example.com/same.jpg', $post->post_content );
		preg_match_all( '/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $post->post_content, $matches );
		$this->assertCount( 2, $matches[1] );
		$this->assertEquals( $matches[1][0], $matches[1][1] ); // Both should have same URL.

		// Clean up.
		\wp_delete_post( $post_id, true );
	}

	/**
	 * Test inline image processing with invalid URLs.
	 *
	 * @covers ::import_inline_images
	 */
	public function test_inline_images_with_invalid_urls() {
		// Create a post with valid and invalid image URLs.
		$post_content = '<p>Valid: <img src="https://example.com/valid.jpg" alt=""> Invalid: <img src="not-a-url" alt=""> Data URI: <img src="data:image/gif;base64,R0lGODlhAQABAIAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==" alt=""></p>';
		$post_id      = self::factory()->post->create(
			array(
				'post_content' => $post_content,
				'post_type'    => 'ap_post',
			)
		);

		// Process inline images.
		Attachments::import( array(), $post_id, self::$author_id );

		// Get updated post.
		$post = \get_post( $post_id );

		// Only valid URL should be replaced.
		$this->assertStringNotContainsString( 'https://example.com/valid.jpg', $post->post_content );
		$this->assertStringContainsString( 'not-a-url', $post->post_content ); // Invalid URL unchanged.
		$this->assertStringContainsString( 'base64', $post->post_content ); // Data URI still present (may be modified by WordPress).

		// Only one attachment should be created.
		$attachments = \get_attached_media( '', $post_id );
		$this->assertCount( 1, $attachments );

		// Clean up.
		\wp_delete_post( $post_id, true );
	}
}
