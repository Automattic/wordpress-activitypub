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
	 * Emoji test directory path.
	 *
	 * @var string
	 */
	protected static $emoji_dir;

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

		// Create emoji test directory.
		$upload_dir      = \wp_upload_dir();
		self::$emoji_dir = $upload_dir['basedir'] . Attachments::$emoji_dir;
		\wp_mkdir_p( self::$emoji_dir );
	}

	/**
	 * Clean up after all tests.
	 */
	public static function tear_down_after_class() {
		global $wp_filesystem;
		\WP_Filesystem();

		if ( $wp_filesystem->is_dir( self::$emoji_dir ) ) {
			$wp_filesystem->rmdir( self::$emoji_dir, true );
		}

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
		if ( \PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

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
		if ( \PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

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
		if ( \PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

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
		if ( \PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

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
		$this->assertStringContainsString( '<!-- wp:gallery', $post->post_content );
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

		// Verify image block or gallery was added for attachment-only.jpg.
		$this->assertTrue(
			str_contains( $post->post_content, '<!-- wp:image' ) || str_contains( $post->post_content, '<!-- wp:gallery' ),
			'Expected image or gallery block in post content'
		);
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
	}

	/**
	 * Test that query parameters are stripped from attachment filenames.
	 *
	 * This prevents "Filename too long" errors when downloading from CDN URLs
	 * (like Instagram) that include long query strings.
	 *
	 * @covers ::save_attachment
	 */
	public function test_attachment_filename_strips_query_parameters() {
		// Instagram-style URL with very long query parameters.
		$attachments = array(
			array(
				'url'       => 'https://example.com/image.jpg?stp=dst-jpg_e35&nc_cat=101&ccb7-5&_nc_sid=18de74&nc_ohc=example&nc_oc=example',
				'mediaType' => 'image/jpeg',
				'name'      => 'Test Image',
				'type'      => 'Image',
			),
		);

		$result = Attachments::import( $attachments, self::$post_id, self::$author_id );

		$this->assertIsArray( $result );
		$this->assertCount( 1, $result );
		$this->assertIsInt( $result[0] );

		// Verify the attachment filename is clean without query parameters.
		// Note: Image may be converted to WebP during optimization.
		$attachment_file = get_attached_file( $result[0] );
		$this->assertMatchesRegularExpression( '/\.(jpg|webp)$/', $attachment_file );
		$this->assertStringNotContainsString( '?', $attachment_file );
		$this->assertStringNotContainsString( 'stp=', $attachment_file );
		$this->assertStringNotContainsString( 'nc_cat=', $attachment_file );
	}

	/**
	 * Test that query parameters are stripped from direct file storage filenames.
	 *
	 * @covers ::save_file
	 */
	public function test_file_storage_filename_strips_query_parameters() {
		// Create a test post.
		$post_id = self::factory()->post->create(
			array(
				'post_type' => 'ap_post',
			)
		);

		// URL with query parameters.
		$attachments = array(
			array(
				'url'       => 'https://example.com/photo.png?size=large&quality=high&cache=12345',
				'mediaType' => 'image/png',
				'name'      => 'Test Photo',
				'type'      => 'Image',
			),
		);

		$result = Attachments::import_post_files( $attachments, $post_id );

		$this->assertIsArray( $result );
		$this->assertCount( 1, $result );

		// Verify the URL doesn't contain query parameters.
		// Note: Image may be converted to WebP during optimization.
		$this->assertMatchesRegularExpression( '/\.(png|webp)$/', $result[0]['url'] );
		$this->assertStringNotContainsString( '?', $result[0]['url'] );
		$this->assertStringNotContainsString( 'size=', $result[0]['url'] );
	}

	/**
	 * Test that save_file returns the correct mime type after image optimization.
	 *
	 * Optimization can change the format (e.g., JPEG to WebP), so the returned
	 * mime_type must reflect the actual file, not the original.
	 *
	 * @covers ::save_file
	 * @covers ::optimize_image
	 */
	public function test_save_file_mime_type_matches_after_optimization() {
		$post_id = self::factory()->post->create(
			array(
				'post_type' => 'ap_post',
			)
		);

		$attachments = array(
			array(
				'url'       => 'https://example.com/photo.png',
				'mediaType' => 'image/png',
				'name'      => 'Test Photo',
				'type'      => 'Image',
			),
		);

		$result = Attachments::import_post_files( $attachments, $post_id );

		$this->assertIsArray( $result );
		$this->assertCount( 1, $result );

		$url       = $result[0]['url'];
		$mime_type = $result[0]['mime_type'];
		$extension = pathinfo( wp_parse_url( $url, PHP_URL_PATH ), PATHINFO_EXTENSION );

		// The mime type must match the actual file extension.
		$expected_mimes = array(
			'webp' => 'image/webp',
			'png'  => 'image/png',
			'jpg'  => 'image/jpeg',
		);

		$this->assertArrayHasKey( $extension, $expected_mimes, "Unexpected extension: $extension" );
		$this->assertSame( $expected_mimes[ $extension ], $mime_type, "Mime type '$mime_type' does not match extension '$extension'" );
	}

	/**
	 * Test that video attachments use remote URL directly without downloading.
	 *
	 * @covers ::save_file
	 */
	public function test_video_uses_remote_url() {
		// Create a test post.
		$post_id = self::factory()->post->create(
			array(
				'post_type'    => 'ap_post',
				'post_content' => 'Test video content',
			)
		);

		$attachments = array(
			array(
				'url'       => 'https://example.com/video.mp4',
				'mediaType' => 'video/mp4',
				'name'      => 'Test Video',
				'type'      => 'Video',
			),
		);

		$result = Attachments::import_post_files( $attachments, $post_id );

		$this->assertIsArray( $result );
		$this->assertCount( 1, $result );

		// Verify the URL is the original remote URL (not downloaded).
		$this->assertEquals( 'https://example.com/video.mp4', $result[0]['url'] );
		$this->assertEquals( 'video/mp4', $result[0]['mime_type'] );
		$this->assertEquals( 'Test Video', $result[0]['alt'] );

		// Verify content was updated with video block.
		$post = get_post( $post_id );
		$this->assertStringContainsString( '<!-- wp:video', $post->post_content );
		$this->assertStringContainsString( 'https://example.com/video.mp4', $post->post_content );
	}

	/**
	 * Test that audio attachments use remote URL directly without downloading.
	 *
	 * @covers ::save_file
	 */
	public function test_audio_uses_remote_url() {
		// Create a test post.
		$post_id = self::factory()->post->create(
			array(
				'post_type'    => 'ap_post',
				'post_content' => 'Test audio content',
			)
		);

		$attachments = array(
			array(
				'url'       => 'https://example.com/podcast.mp3',
				'mediaType' => 'audio/mpeg',
				'name'      => 'Test Audio',
				'type'      => 'Audio',
			),
		);

		$result = Attachments::import_post_files( $attachments, $post_id );

		$this->assertIsArray( $result );
		$this->assertCount( 1, $result );

		// Verify the URL is the original remote URL (not downloaded).
		$this->assertEquals( 'https://example.com/podcast.mp3', $result[0]['url'] );
		$this->assertEquals( 'audio/mpeg', $result[0]['mime_type'] );
		$this->assertEquals( 'Test Audio', $result[0]['alt'] );

		// Verify content was updated with audio block.
		$post = get_post( $post_id );
		$this->assertStringContainsString( '<!-- wp:audio', $post->post_content );
		$this->assertStringContainsString( 'https://example.com/podcast.mp3', $post->post_content );
	}

	/**
	 * Test mixed attachments with images, video, and audio.
	 *
	 * @covers ::import_post_files
	 * @covers ::save_file
	 */
	public function test_mixed_attachments_images_video_audio() {
		// Create a test post.
		$post_id = self::factory()->post->create(
			array(
				'post_type'    => 'ap_post',
				'post_content' => 'Mixed media content',
			)
		);

		$attachments = array(
			array(
				'url'       => 'https://example.com/image.jpg',
				'mediaType' => 'image/jpeg',
				'name'      => 'Test Image',
				'type'      => 'Image',
			),
			array(
				'url'       => 'https://example.com/video.mp4',
				'mediaType' => 'video/mp4',
				'name'      => 'Test Video',
				'type'      => 'Video',
			),
			array(
				'url'       => 'https://example.com/audio.mp3',
				'mediaType' => 'audio/mpeg',
				'name'      => 'Test Audio',
				'type'      => 'Audio',
			),
		);

		$result = Attachments::import_post_files( $attachments, $post_id );

		$this->assertIsArray( $result );
		$this->assertCount( 3, $result );

		// Image should be downloaded to local storage (may be converted to WebP).
		$this->assertStringContainsString( 'activitypub/ap_posts', $result[0]['url'] );
		$this->assertContains( $result[0]['mime_type'], array( 'image/jpeg', 'image/webp' ) );

		// Video should use remote URL.
		$this->assertEquals( 'https://example.com/video.mp4', $result[1]['url'] );
		$this->assertEquals( 'video/mp4', $result[1]['mime_type'] );

		// Audio should use remote URL.
		$this->assertEquals( 'https://example.com/audio.mp3', $result[2]['url'] );
		$this->assertEquals( 'audio/mpeg', $result[2]['mime_type'] );
	}

	/**
	 * Test optimize_image returns original path for non-image files.
	 *
	 * @covers ::optimize_image
	 */
	public function test_optimize_image_skips_non_images() {
		$method = new \ReflectionMethod( Attachments::class, 'optimize_image' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		// Test with audio file.
		$audio_file = AP_TESTS_DIR . '/data/assets/sample-audio.mp3';
		$result     = $method->invoke( null, $audio_file, 1200 );
		$this->assertEquals( $audio_file, $result );

		// Test with video file.
		$video_file = AP_TESTS_DIR . '/data/assets/sample-video.mp4';
		$result     = $method->invoke( null, $video_file, 1200 );
		$this->assertEquals( $video_file, $result );
	}

	/**
	 * Test optimize_image skips GIF files (may be animated).
	 *
	 * @covers ::optimize_image
	 */
	public function test_optimize_image_skips_gif() {
		$method = new \ReflectionMethod( Attachments::class, 'optimize_image' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		// Create a simple GIF file.
		$gif_file = wp_tempnam( 'test.gif' ) . '.gif';
		// Minimal valid GIF89a header.
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Test data, not obfuscation.
		$gif_data = base64_decode( 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7' );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test file creation.
		file_put_contents( $gif_file, $gif_data );

		$result = $method->invoke( null, $gif_file, 1200 );
		$this->assertEquals( $gif_file, $result );

		// Clean up.
		wp_delete_file( $gif_file );
	}

	/**
	 * Test optimize_image converts JPEG to WebP when supported.
	 *
	 * @covers ::optimize_image
	 */
	public function test_optimize_image_converts_to_webp() {
		$method = new \ReflectionMethod( Attachments::class, 'optimize_image' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		// Copy test image to temp location with proper extension.
		$source    = AP_TESTS_DIR . '/data/assets/test.jpg';
		$temp_dir  = sys_get_temp_dir();
		$temp_file = $temp_dir . '/test-webp-' . uniqid() . '.jpg';
		copy( $source, $temp_file );

		$result = $method->invoke( null, $temp_file, 1200 );

		// Check if WebP is supported in this environment.
		$editor = wp_get_image_editor( $source );
		if ( ! is_wp_error( $editor ) && $editor->supports_mime_type( 'image/webp' ) ) {
			// Should be converted to WebP.
			$this->assertStringEndsWith( '.webp', $result );
			$this->assertFileExists( $result );
			// Clean up result file.
			wp_delete_file( $result );
			// Clean up original if it still exists (shouldn't, but be safe).
			if ( file_exists( $temp_file ) ) {
				wp_delete_file( $temp_file );
			}
		} else {
			// WebP not supported, should convert to JPEG or keep original.
			$this->assertFileExists( $result );
			wp_delete_file( $result );
			if ( file_exists( $temp_file ) ) {
				wp_delete_file( $temp_file );
			}
		}
	}

	/**
	 * Test optimize_image resizes large images.
	 *
	 * @covers ::optimize_image
	 */
	public function test_optimize_image_resizes_large_images() {
		$method = new \ReflectionMethod( Attachments::class, 'optimize_image' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		// Create a large test image (2000x2000).
		$large_image = wp_tempnam( 'large.jpg' ) . '.jpg';
		$image       = imagecreatetruecolor( 2000, 2000 );
		$white       = imagecolorallocate( $image, 255, 255, 255 );
		imagefill( $image, 0, 0, $white );
		imagejpeg( $image, $large_image, 90 );
		unset( $image );

		// Optimize with max dimension of 500.
		$result = $method->invoke( null, $large_image, 500 );

		$this->assertFileExists( $result );

		// Check the dimensions of the result.
		$editor = wp_get_image_editor( $result );
		if ( ! is_wp_error( $editor ) ) {
			$size = $editor->get_size();
			$this->assertLessThanOrEqual( 500, $size['width'] );
			$this->assertLessThanOrEqual( 500, $size['height'] );
		}

		// Clean up.
		wp_delete_file( $result );
		if ( file_exists( $large_image ) ) {
			wp_delete_file( $large_image );
		}
	}

	/**
	 * Test optimize_image preserves small images dimensions.
	 *
	 * @covers ::optimize_image
	 */
	public function test_optimize_image_preserves_small_images() {
		$method = new \ReflectionMethod( Attachments::class, 'optimize_image' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		// Copy small test image (100x100) to temp location.
		$source    = AP_TESTS_DIR . '/data/assets/test.jpg';
		$temp_file = wp_tempnam( 'small.jpg' ) . '.jpg';
		copy( $source, $temp_file );

		// Get original dimensions.
		$original_editor = wp_get_image_editor( $temp_file );
		$this->assertNotWPError( $original_editor );
		$original_size = $original_editor->get_size();

		// Optimize with max dimension larger than image.
		$result = $method->invoke( null, $temp_file, 1200 );

		$this->assertFileExists( $result );

		// Check dimensions are preserved.
		$result_editor = wp_get_image_editor( $result );
		if ( ! is_wp_error( $result_editor ) ) {
			$result_size = $result_editor->get_size();
			$this->assertEquals( $original_size['width'], $result_size['width'] );
			$this->assertEquals( $original_size['height'], $result_size['height'] );
		}

		// Clean up.
		wp_delete_file( $result );
		if ( file_exists( $temp_file ) ) {
			wp_delete_file( $temp_file );
		}
	}

	/**
	 * Test optimize_image returns original for non-existent file.
	 *
	 * @covers ::optimize_image
	 */
	public function test_optimize_image_handles_nonexistent_file() {
		$method = new \ReflectionMethod( Attachments::class, 'optimize_image' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		$fake_path = '/tmp/nonexistent-image-12345.jpg';
		$result    = $method->invoke( null, $fake_path, 1200 );

		// Should return original path when file doesn't exist or can't be processed.
		$this->assertEquals( $fake_path, $result );
	}

	/**
	 * Test optimize_image handles PNG files correctly.
	 *
	 * @covers ::optimize_image
	 */
	public function test_optimize_image_handles_png() {
		$method = new \ReflectionMethod( Attachments::class, 'optimize_image' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		// Create a test PNG image.
		$png_file = wp_tempnam( 'test.png' ) . '.png';
		$image    = imagecreatetruecolor( 100, 100 );
		// Enable alpha channel for transparency.
		imagesavealpha( $image, true );
		$transparent = imagecolorallocatealpha( $image, 0, 0, 0, 127 );
		imagefill( $image, 0, 0, $transparent );
		imagepng( $image, $png_file );
		unset( $image );

		$result = $method->invoke( null, $png_file, 1200 );

		$this->assertFileExists( $result );

		// Check if WebP is supported.
		$editor = wp_get_image_editor( $png_file );
		if ( ! is_wp_error( $editor ) && $editor->supports_mime_type( 'image/webp' ) ) {
			// Should be converted to WebP.
			$this->assertStringEndsWith( '.webp', $result );
		}

		// Clean up.
		wp_delete_file( $result );
		if ( file_exists( $png_file ) ) {
			wp_delete_file( $png_file );
		}
	}

	/**
	 * Test save_actor_avatar with valid URL.
	 *
	 * @covers ::save_actor_avatar
	 */
	public function test_save_actor_avatar_success() {
		// Create a test actor post.
		$actor_id = self::factory()->post->create(
			array(
				'post_type' => 'ap_actor',
			)
		);

		$avatar_url = 'https://example.com/avatar.jpg';
		$result     = Attachments::save_actor_avatar( $actor_id, $avatar_url );

		$this->assertIsString( $result );
		$this->assertStringContainsString( 'wp-content/uploads/activitypub/actors/' . $actor_id, $result );
		// Note: Image may be converted to WebP during optimization.
		$this->assertMatchesRegularExpression( '/\.(jpg|webp)$/', $result );
	}

	/**
	 * Test save_actor_avatar with empty URL.
	 *
	 * @covers ::save_actor_avatar
	 */
	public function test_save_actor_avatar_empty_url() {
		$actor_id = self::factory()->post->create(
			array(
				'post_type' => 'ap_actor',
			)
		);

		$result = Attachments::save_actor_avatar( $actor_id, '' );

		$this->assertFalse( $result );
	}

	/**
	 * Test save_actor_avatar with invalid URL.
	 *
	 * @covers ::save_actor_avatar
	 */
	public function test_save_actor_avatar_invalid_url() {
		$actor_id = self::factory()->post->create(
			array(
				'post_type' => 'ap_actor',
			)
		);

		$result = Attachments::save_actor_avatar( $actor_id, 'not-a-valid-url' );

		$this->assertFalse( $result );
	}

	/**
	 * Test save_actor_avatar with download error.
	 *
	 * @covers ::save_actor_avatar
	 */
	public function test_save_actor_avatar_download_error() {
		$actor_id = self::factory()->post->create(
			array(
				'post_type' => 'ap_actor',
			)
		);

		// Use the missing.jpg URL that our mock returns as an error.
		$result = Attachments::save_actor_avatar( $actor_id, 'https://example.com/missing.jpg' );

		$this->assertFalse( $result );
	}

	/**
	 * Test save_actor_avatar replaces existing avatar.
	 *
	 * @covers ::save_actor_avatar
	 * @covers ::delete_actors_directory
	 */
	public function test_save_actor_avatar_replaces_existing() {
		$actor_id = self::factory()->post->create(
			array(
				'post_type' => 'ap_actor',
			)
		);

		// Save first avatar.
		$first_result = Attachments::save_actor_avatar( $actor_id, 'https://example.com/avatar1.jpg' );
		$this->assertIsString( $first_result );

		// Save second avatar (should replace the first).
		$second_result = Attachments::save_actor_avatar( $actor_id, 'https://example.com/avatar2.jpg' );
		$this->assertIsString( $second_result );

		// URLs should be different (different source filenames).
		$this->assertNotEquals( $first_result, $second_result );
	}

	/**
	 * Test delete_actors_directory removes actor files.
	 *
	 * @covers ::delete_actors_directory
	 */
	public function test_delete_actors_directory() {
		$actor_id = self::factory()->post->create(
			array(
				'post_type' => 'ap_actor',
			)
		);

		// Save an avatar first.
		$avatar_url = Attachments::save_actor_avatar( $actor_id, 'https://example.com/avatar.jpg' );
		$this->assertIsString( $avatar_url );

		// Get the directory path.
		$upload_dir = \wp_upload_dir();
		$actor_dir  = $upload_dir['basedir'] . '/activitypub/actors/' . $actor_id;

		// Verify directory exists.
		$this->assertTrue( \is_dir( $actor_dir ) );

		// Delete the directory.
		Attachments::delete_actors_directory( $actor_id );

		// Verify directory is gone.
		$this->assertFalse( \is_dir( $actor_dir ) );
	}

	/**
	 * Test delete_actors_directory ignores non-actor post types.
	 *
	 * @covers ::delete_actors_directory
	 */
	public function test_delete_actors_directory_ignores_non_actors() {
		// Create a regular post (not an actor).
		$post_id = self::factory()->post->create(
			array(
				'post_type' => 'post',
			)
		);

		// Create a directory that would match the path pattern.
		$upload_dir = \wp_upload_dir();
		$fake_dir   = $upload_dir['basedir'] . '/activitypub/actors/' . $post_id;
		\wp_mkdir_p( $fake_dir );

		// Verify directory exists.
		$this->assertTrue( \is_dir( $fake_dir ) );

		// Try to delete - should be ignored because it's not an actor post type.
		Attachments::delete_actors_directory( $post_id );

		// Directory should still exist.
		$this->assertTrue( \is_dir( $fake_dir ) );

		// Clean up.
		global $wp_filesystem;
		\WP_Filesystem();
		$wp_filesystem->rmdir( $fake_dir );
	}

	/**
	 * Test emoji import caches file and returns local URL.
	 *
	 * @covers ::import_emoji
	 */
	public function test_import_emoji_caches_file() {
		$emoji_url = 'https://example.com/emoji/kappa.png';
		$result    = Attachments::import_emoji( $emoji_url );

		$this->assertNotFalse( $result );
		$this->assertStringContainsString( '/activitypub/emoji/', $result );
		$this->assertStringContainsString( 'kappa.', $result );
	}

	/**
	 * Test emoji import returns cached URL when no updated timestamp provided.
	 *
	 * @covers ::import_emoji
	 */
	public function test_import_emoji_uses_cache_without_updated() {
		$emoji_url = 'https://example.com/emoji/smile.png';

		// First import.
		$first_result = Attachments::import_emoji( $emoji_url );
		$this->assertNotFalse( $first_result );

		// Second import without updated timestamp - should use cache.
		$second_result = Attachments::import_emoji( $emoji_url );
		$this->assertEquals( $first_result, $second_result );
	}

	/**
	 * Test emoji import uses cache when updated timestamp is older than cached file.
	 *
	 * @covers ::import_emoji
	 */
	public function test_import_emoji_uses_cache_when_updated_is_older() {
		$emoji_url = 'https://example.com/emoji/old.png';

		// First import.
		$first_result = Attachments::import_emoji( $emoji_url );
		$this->assertNotFalse( $first_result );

		// Second import with old updated timestamp - should use cache.
		$old_timestamp = '2020-01-01T00:00:00Z';
		$second_result = Attachments::import_emoji( $emoji_url, $old_timestamp );
		$this->assertEquals( $first_result, $second_result );
	}

	/**
	 * Test emoji import re-downloads when updated timestamp is newer than cached file.
	 *
	 * @covers ::import_emoji
	 */
	public function test_import_emoji_redownloads_when_updated_is_newer() {
		$emoji_url = 'https://example.com/emoji/new.png';

		// First import.
		$first_result = Attachments::import_emoji( $emoji_url );
		$this->assertNotFalse( $first_result );

		// Get the cached file path and modify its timestamp to be old.
		$upload_dir   = \wp_upload_dir();
		$glob_pattern = $upload_dir['basedir'] . '/activitypub/emoji/example.com/new.*';
		$matches      = \glob( $glob_pattern );
		$this->assertNotEmpty( $matches, 'Cached file should exist' );
		$file_path = \reset( $matches );

		// Set file modification time to the past.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_touch -- Direct touch() needed for test timestamp manipulation.
		\touch( $file_path, \strtotime( '2020-01-01' ) );

		// Track if download was attempted.
		$download_attempted = false;
		$track_download     = function ( $response, $parsed_args, $url ) use ( &$download_attempted, $emoji_url ) {
			if ( $url === $emoji_url ) {
				$download_attempted = true;
			}
			return $response;
		};
		\add_filter( 'pre_http_request', $track_download, 5, 3 );

		// Import with newer updated timestamp - should re-download.
		$new_timestamp = '2025-01-01T00:00:00Z';
		$second_result = Attachments::import_emoji( $emoji_url, $new_timestamp );

		\remove_filter( 'pre_http_request', $track_download, 5 );

		$this->assertNotFalse( $second_result );
		$this->assertTrue( $download_attempted, 'Should have attempted to re-download the emoji' );
	}

	/**
	 * Test emoji import returns false for invalid URL.
	 *
	 * @covers ::import_emoji
	 */
	public function test_import_emoji_returns_false_for_invalid_url() {
		$this->assertFalse( Attachments::import_emoji( '' ) );
		$this->assertFalse( Attachments::import_emoji( 'not-a-url' ) );
	}

	/**
	 * Test emoji import returns false when download fails.
	 *
	 * @covers ::import_emoji
	 */
	public function test_import_emoji_returns_false_on_download_failure() {
		$emoji_url = 'https://example.com/emoji/download-fail.png';

		// Mock a failed HTTP request.
		$fail_download = function () {
			return new \WP_Error( 'http_request_failed', 'Connection failed' );
		};
		\add_filter( 'pre_http_request', $fail_download );

		$result = Attachments::import_emoji( $emoji_url );

		\remove_filter( 'pre_http_request', $fail_download );

		$this->assertFalse( $result );
	}

	/**
	 * Call private get_emoji_url method via reflection.
	 *
	 * @param string $emoji_url The emoji URL.
	 * @return string|false The local URL or false.
	 */
	private function call_get_emoji_url( $emoji_url ) {
		$method = new \ReflectionMethod( Attachments::class, 'get_emoji_url' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		return $method->invoke( null, $emoji_url );
	}

	/**
	 * Test that glob metacharacters in emoji URLs don't match unintended files.
	 *
	 * Without sanitization, glob patterns could match unintended files:
	 * - '[abc].*' would match files starting with a, b, or c
	 * - '*.*' would match all files
	 * - '?.*' would match any single-character filename
	 *
	 * With sanitization, these metacharacters are removed.
	 *
	 * @covers ::get_emoji_url
	 * @dataProvider data_glob_metacharacter_urls
	 *
	 * @param string   $malicious_filename Filename containing glob metacharacters.
	 * @param string[] $files_to_create    Files that would match the unsanitized pattern.
	 */
	public function test_get_emoji_url_sanitizes_glob_metacharacters( $malicious_filename, $files_to_create ) {
		global $wp_filesystem;
		\WP_Filesystem();

		$domain_dir = self::$emoji_dir . 'glob-test.example.com';
		$wp_filesystem->mkdir( $domain_dir, FS_CHMOD_DIR );

		// Create files that would match the unsanitized glob pattern.
		foreach ( $files_to_create as $filename ) {
			$wp_filesystem->put_contents( $domain_dir . '/' . $filename, 'test' );
		}

		// URL with glob metacharacters.
		$url    = 'https://glob-test.example.com/emoji/' . $malicious_filename;
		$result = $this->call_get_emoji_url( $url );

		// Should NOT match any files because metacharacters are sanitized.
		$this->assertFalse( $result, sprintf( 'Glob pattern "%s" should not match existing files', $malicious_filename ) );
	}

	/**
	 * Data provider for glob metacharacter tests.
	 *
	 * @return array Test cases: [malicious_filename, files_that_would_match_if_not_sanitized].
	 */
	public function data_glob_metacharacter_urls() {
		return array(
			'brackets'      => array( '[abc].png', array( 'a.png', 'b.png', 'c.png' ) ),
			'asterisk'      => array( 'test*.png', array( 'test1.png', 'test2.png', 'testing.png' ) ),
			'question_mark' => array( 'tes?.png', array( 'test.png', 'tess.png', 'tesx.png' ) ),
			'curly_braces'  => array( '{foo,bar}.png', array( 'foo.png', 'bar.png' ) ),
		);
	}

	/**
	 * Test that get_emoji_url finds cached files for normal URLs.
	 *
	 * @covers ::get_emoji_url
	 */
	public function test_get_emoji_url_finds_cached_file() {
		global $wp_filesystem;
		\WP_Filesystem();

		$domain_dir = self::$emoji_dir . 'cache-test.example.com';
		$wp_filesystem->mkdir( $domain_dir, FS_CHMOD_DIR );
		$wp_filesystem->put_contents( $domain_dir . '/normal-emoji.png', 'test' );

		// Normal URL should find the cached file.
		$url    = 'https://cache-test.example.com/emoji/normal-emoji.png';
		$result = $this->call_get_emoji_url( $url );

		$this->assertNotFalse( $result );
		$this->assertStringContainsString( 'normal-emoji.png', $result );
	}

	/**
	 * Test is_sideloading_enabled returns true by default.
	 *
	 * @covers ::is_sideloading_enabled
	 */
	public function test_is_sideloading_enabled_default() {
		$this->assertTrue( Attachments::is_sideloading_enabled() );
	}

	/**
	 * Test is_sideloading_enabled respects filter.
	 *
	 * @covers ::is_sideloading_enabled
	 */
	public function test_is_sideloading_enabled_filter() {
		\add_filter( 'activitypub_sideloading_enabled', '__return_false' );

		$this->assertFalse( Attachments::is_sideloading_enabled() );

		\remove_filter( 'activitypub_sideloading_enabled', '__return_false' );

		$this->assertTrue( Attachments::is_sideloading_enabled() );
	}

	/**
	 * Test import_emoji returns filtered remote URL when sideloading disabled.
	 *
	 * @covers ::import_emoji
	 */
	public function test_import_emoji_returns_remote_url_when_sideloading_disabled() {
		\add_filter( 'activitypub_sideloading_enabled', '__return_false' );

		$emoji_url = 'https://example.com/emoji/test.png';
		$result    = Attachments::import_emoji( $emoji_url );

		// Should return the remote URL (not false, not local).
		$this->assertSame( $emoji_url, $result );

		\remove_filter( 'activitypub_sideloading_enabled', '__return_false' );
	}

	/**
	 * Test import_emoji applies activitypub_remote_media_url filter when sideloading disabled.
	 *
	 * @covers ::import_emoji
	 */
	public function test_import_emoji_applies_cdn_filter_when_sideloading_disabled() {
		\add_filter( 'activitypub_sideloading_enabled', '__return_false' );

		$cdn_filter = function ( $url, $mime_type, $context ) {
			if ( 'emoji' === $context ) {
				return 'https://cdn.example.com/' . basename( $url );
			}
			return $url;
		};
		\add_filter( 'activitypub_remote_media_url', $cdn_filter, 10, 3 );

		$emoji_url = 'https://example.com/emoji/test.png';
		$result    = Attachments::import_emoji( $emoji_url );

		$this->assertSame( 'https://cdn.example.com/test.png', $result );

		\remove_filter( 'activitypub_remote_media_url', $cdn_filter );
		\remove_filter( 'activitypub_sideloading_enabled', '__return_false' );
	}

	/**
	 * Test save_file returns remote URL when sideloading disabled.
	 *
	 * @covers ::save_file
	 */
	public function test_save_file_returns_remote_url_when_sideloading_disabled() {
		\add_filter( 'activitypub_sideloading_enabled', '__return_false' );

		$attachment_data = array(
			'url'       => 'https://example.com/image.jpg',
			'mediaType' => 'image/jpeg',
			'name'      => 'Test image',
		);

		$method = new \ReflectionMethod( Attachments::class, 'save_file' );
		$method->setAccessible( true );

		$result = $method->invoke( null, $attachment_data, self::$post_id, 'post' );

		$this->assertIsArray( $result );
		$this->assertSame( 'https://example.com/image.jpg', $result['url'] );
		$this->assertSame( 'image/jpeg', $result['mime_type'] );
		$this->assertSame( 'Test image', $result['alt'] );

		\remove_filter( 'activitypub_sideloading_enabled', '__return_false' );
	}

	/**
	 * Test save_file applies activitypub_remote_media_url filter when sideloading disabled.
	 *
	 * @covers ::save_file
	 */
	public function test_save_file_applies_cdn_filter_when_sideloading_disabled() {
		\add_filter( 'activitypub_sideloading_enabled', '__return_false' );

		$received_main_type = null;
		$cdn_filter         = function ( $url, $main_type, $context ) use ( &$received_main_type ) {
			$received_main_type = $main_type;
			if ( 'attachment' === $context ) {
				return 'https://cdn.example.com/' . basename( $url );
			}
			return $url;
		};
		\add_filter( 'activitypub_remote_media_url', $cdn_filter, 10, 3 );

		$attachment_data = array(
			'url'       => 'https://example.com/image.jpg',
			'mediaType' => 'image/jpeg',
			'name'      => 'Test image',
		);

		$method = new \ReflectionMethod( Attachments::class, 'save_file' );
		$method->setAccessible( true );

		$result = $method->invoke( null, $attachment_data, self::$post_id, 'post' );

		$this->assertSame( 'https://cdn.example.com/image.jpg', $result['url'] );
		// Verify main type is passed, not full MIME type.
		$this->assertSame( 'image', $received_main_type );

		\remove_filter( 'activitypub_remote_media_url', $cdn_filter );
		\remove_filter( 'activitypub_sideloading_enabled', '__return_false' );
	}

	/**
	 * Test save_actor_avatar returns false when sideloading disabled.
	 *
	 * @covers ::save_actor_avatar
	 */
	public function test_save_actor_avatar_returns_false_when_sideloading_disabled() {
		\add_filter( 'activitypub_sideloading_enabled', '__return_false' );

		$result = Attachments::save_actor_avatar( 123, 'https://example.com/avatar.jpg' );

		$this->assertFalse( $result );

		\remove_filter( 'activitypub_sideloading_enabled', '__return_false' );
	}

	/**
	 * Test save_file still downloads video/audio even when sideloading enabled.
	 *
	 * Video and audio files always return remote URL regardless of sideloading setting.
	 *
	 * @covers ::save_file
	 */
	public function test_save_file_returns_remote_url_for_video_audio() {
		$method = new \ReflectionMethod( Attachments::class, 'save_file' );
		$method->setAccessible( true );

		// Test video.
		$video_data = array(
			'url'       => 'https://example.com/video.mp4',
			'mediaType' => 'video/mp4',
			'name'      => 'Test video',
		);
		$result     = $method->invoke( null, $video_data, self::$post_id, 'post' );

		$this->assertSame( 'https://example.com/video.mp4', $result['url'] );

		// Test audio.
		$audio_data = array(
			'url'       => 'https://example.com/audio.mp3',
			'mediaType' => 'audio/mpeg',
			'name'      => 'Test audio',
		);
		$result     = $method->invoke( null, $audio_data, self::$post_id, 'post' );

		$this->assertSame( 'https://example.com/audio.mp3', $result['url'] );
	}

	/**
	 * Test is_safe_url validates URLs correctly.
	 *
	 * @covers ::is_safe_url
	 * @dataProvider data_is_safe_url
	 *
	 * @param string $url      The URL to validate.
	 * @param bool   $expected Expected result.
	 */
	public function test_is_safe_url( $url, $expected ) {
		$method = new \ReflectionMethod( Attachments::class, 'is_safe_url' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		$this->assertSame( $expected, $method->invoke( null, $url ) );
	}

	/**
	 * Data provider for is_safe_url tests.
	 *
	 * @return array Test cases: [url, expected_result].
	 */
	public function data_is_safe_url() {
		return array(
			'valid_https'       => array( 'https://example.com/image.jpg', true ),
			'valid_http'        => array( 'http://example.com/image.jpg', true ),
			'empty_string'      => array( '', false ),
			'null_value'        => array( null, false ),
			'invalid_url'       => array( 'not-a-url', false ),
			'javascript_scheme' => array( 'javascript:alert(1)', false ),
			'data_uri'          => array( 'data:image/png;base64,abc', false ),
			'file_scheme'       => array( 'file:///etc/passwd', false ),
			'ftp_scheme'        => array( 'ftp://example.com/file', false ),
		);
	}

	/**
	 * Test is_valid_file_path validates paths correctly.
	 *
	 * @covers ::is_valid_file_path
	 */
	public function test_is_valid_file_path_allows_content_dir() {
		$method = new \ReflectionMethod( Attachments::class, 'is_valid_file_path' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		// Use the test image from assets which is in the plugin directory.
		$test_image = AP_TESTS_DIR . '/data/assets/test.jpg';
		$this->assertTrue( $method->invoke( null, $test_image ) );
	}

	/**
	 * Test is_valid_file_path rejects paths outside allowed directories.
	 *
	 * @covers ::is_valid_file_path
	 */
	public function test_is_valid_file_path_rejects_outside_paths() {
		$method = new \ReflectionMethod( Attachments::class, 'is_valid_file_path' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		// Non-existent path.
		$this->assertFalse( $method->invoke( null, '/nonexistent/path/file.jpg' ) );

		// Path outside allowed directories (using system temp as example).
		$this->assertFalse( $method->invoke( null, '/etc/passwd' ) );
	}

	/**
	 * Test get_base_filename_from_url extracts filenames correctly.
	 *
	 * @covers ::get_base_filename_from_url
	 * @dataProvider data_get_base_filename_from_url
	 *
	 * @param string $url      The URL to extract filename from.
	 * @param string $expected Expected base filename.
	 */
	public function test_get_base_filename_from_url( $url, $expected ) {
		$method = new \ReflectionMethod( Attachments::class, 'get_base_filename_from_url' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		$result = $method->invoke( null, $url );

		if ( 'generated' === $expected ) {
			// For empty filenames, a random one is generated.
			$this->assertMatchesRegularExpression( '/^image-[a-zA-Z0-9]{8}$/', $result );
		} else {
			$this->assertSame( $expected, $result );
		}
	}

	/**
	 * Data provider for get_base_filename_from_url tests.
	 *
	 * @return array Test cases: [url, expected_base_filename].
	 */
	public function data_get_base_filename_from_url() {
		return array(
			'simple'           => array( 'https://example.com/image.jpg', 'image' ),
			'double_extension' => array( 'https://example.com/shell.php.jpg', 'shell' ),
			'multiple_dots'    => array( 'https://example.com/profile.photo.v2.jpg', 'profile' ),
			'query_string'     => array( 'https://example.com/image.jpg?size=large', 'image' ),
			'no_extension'     => array( 'https://example.com/filename', 'filename' ),
			'path_with_dirs'   => array( 'https://example.com/path/to/image.png', 'image' ),
			'empty_filename'   => array( 'https://example.com/', 'generated' ),
			'unicode_filename' => array( 'https://example.com/日本語.jpg', '日本語' ),
		);
	}

	/**
	 * Test get_base_filename_from_url limits length.
	 *
	 * @covers ::get_base_filename_from_url
	 */
	public function test_get_base_filename_from_url_limits_length() {
		$method = new \ReflectionMethod( Attachments::class, 'get_base_filename_from_url' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		// Create URL with very long filename (300 characters).
		$long_name = str_repeat( 'a', 300 );
		$url       = 'https://example.com/' . $long_name . '.jpg';
		$result    = $method->invoke( null, $url );

		$this->assertLessThanOrEqual( 200, strlen( $result ) );
	}

	/**
	 * Test sanitize_image_filename sanitizes correctly.
	 *
	 * @covers ::sanitize_image_filename
	 * @dataProvider data_sanitize_image_filename
	 *
	 * @param string       $filename  The filename to sanitize.
	 * @param string       $mime_type The mime type.
	 * @param string|false $expected  Expected result.
	 */
	public function test_sanitize_image_filename( $filename, $mime_type, $expected ) {
		$method = new \ReflectionMethod( Attachments::class, 'sanitize_image_filename' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		$result = $method->invoke( null, $filename, $mime_type );
		$this->assertSame( $expected, $result );
	}

	/**
	 * Data provider for sanitize_image_filename tests.
	 *
	 * Note: WordPress sanitize_file_name() handles some edge cases before our code runs:
	 * - '.htaccess' becomes 'htaccess' (leading dot stripped)
	 * - '.jpg' becomes 'unnamed-file.jpg' (WordPress generates a name)
	 *
	 * @return array Test cases: [filename, mime_type, expected_result].
	 */
	public function data_sanitize_image_filename() {
		return array(
			'simple_jpg'          => array( 'image.jpg', 'image/jpeg', 'image.jpg' ),
			'simple_png'          => array( 'image.png', 'image/png', 'image.png' ),
			'double_extension'    => array( 'shell.php.jpg', 'image/jpeg', 'shell.jpg' ),
			'multiple_dots'       => array( 'profile.photo.v2.jpg', 'image/jpeg', 'profile.jpg' ),
			'dotfile_sanitized'   => array( '.htaccess', 'image/jpeg', 'htaccess.jpg' ),
			'unsupported_mime'    => array( 'image.jpg', 'application/pdf', false ),
			'webp'                => array( 'image.webp', 'image/webp', 'image.webp' ),
			'gif'                 => array( 'animation.gif', 'image/gif', 'animation.gif' ),
			'empty_base_fixed'    => array( '.jpg', 'image/jpeg', 'unnamed-file.jpg' ),
			'mime_determines_ext' => array( 'image.png', 'image/jpeg', 'image.jpg' ),
		);
	}

	/**
	 * Test sanitize_image_filename limits length.
	 *
	 * @covers ::sanitize_image_filename
	 */
	public function test_sanitize_image_filename_limits_length() {
		$method = new \ReflectionMethod( Attachments::class, 'sanitize_image_filename' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		// Create very long filename (300 characters).
		$long_name = str_repeat( 'a', 300 ) . '.jpg';
		$result    = $method->invoke( null, $long_name, 'image/jpeg' );

		// Base name should be max 200 chars + extension.
		$this->assertLessThanOrEqual( 204, strlen( $result ) ); // 200 + '.jpg'.
	}

	/**
	 * Test validate_image_file validates image content.
	 *
	 * @covers ::validate_image_file
	 */
	public function test_validate_image_file_with_valid_image() {
		$method = new \ReflectionMethod( Attachments::class, 'validate_image_file' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		// Use the test image from assets.
		$test_image = AP_TESTS_DIR . '/data/assets/test.jpg';
		$result     = $method->invoke( null, $test_image );

		$this->assertSame( 'image/jpeg', $result );
	}

	/**
	 * Test validate_image_file rejects non-existent files.
	 *
	 * @covers ::validate_image_file
	 */
	public function test_validate_image_file_rejects_nonexistent() {
		$method = new \ReflectionMethod( Attachments::class, 'validate_image_file' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		$result = $method->invoke( null, '/nonexistent/file.jpg' );
		$this->assertFalse( $result );
	}

	/**
	 * Test validate_image_file rejects non-image files.
	 *
	 * @covers ::validate_image_file
	 */
	public function test_validate_image_file_rejects_non_image() {
		$method = new \ReflectionMethod( Attachments::class, 'validate_image_file' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		global $wp_filesystem;
		\WP_Filesystem();

		// Create a text file disguised as an image.
		$fake_image = \wp_tempnam( 'fake.jpg' );
		$wp_filesystem->put_contents( $fake_image, 'This is not an image, just text content.' );

		$result = $method->invoke( null, $fake_image );
		$this->assertFalse( $result );

		\wp_delete_file( $fake_image );
	}

	/**
	 * Test validate_image_file detects correct mime types.
	 *
	 * @covers ::validate_image_file
	 */
	public function test_validate_image_file_detects_png() {
		$method = new \ReflectionMethod( Attachments::class, 'validate_image_file' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		// Create a test PNG image.
		$png_file = \wp_tempnam( 'test.png' ) . '.png';
		$image    = \imagecreatetruecolor( 10, 10 );
		\imagepng( $image, $png_file );
		\imagedestroy( $image );

		$result = $method->invoke( null, $png_file );
		$this->assertSame( 'image/png', $result );

		\wp_delete_file( $png_file );
	}
}
