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
 * Tests Media Library import functionality and file-based markup generation.
 * For remote media caching tests, see the Cache namespace tests.
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
	 * Set up each test.
	 */
	public function set_up() {
		parent::set_up();

		// Disable Cache\Media so it doesn't interfere with Attachments tests.
		// Cache\Media hooks into save_post and would cache images before Attachments::import() runs.
		\add_filter( 'activitypub_cache_media_enabled', '__return_false' );

		// Allow the test directory for local file imports.
		\add_filter( 'activitypub_allowed_import_directories', array( $this, 'allow_test_directory' ) );

		// Mock HTTP requests only for remote attachment tests.
		\add_filter( 'pre_http_request', array( $this, 'mock_download_url' ), 10, 3 );
		\add_filter( 'wp_delete_file', '__return_empty_string' ); // Prevent actual file deletion during tests.
	}

	/**
	 * Tear down each test.
	 */
	public function tear_down() {
		// Re-enable Cache\Media.
		\remove_filter( 'activitypub_cache_media_enabled', '__return_false' );

		\remove_filter( 'activitypub_allowed_import_directories', array( $this, 'allow_test_directory' ) );
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
	 * Add the test directory to allowed import directories.
	 *
	 * @param array $allowed_dirs The allowed directories.
	 * @return array The modified allowed directories.
	 */
	public function allow_test_directory( $allowed_dirs ) {
		$allowed_dirs[] = \realpath( AP_TESTS_DIR );
		return $allowed_dirs;
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
	 * Test get_unique_path generates unique filenames.
	 *
	 * @covers ::get_unique_path
	 */
	public function test_get_unique_path() {
		$method = new \ReflectionMethod( Attachments::class, 'get_unique_path' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		$temp_dir = sys_get_temp_dir();

		// Test with non-existent file - should return same path.
		$non_existent = $temp_dir . '/unique-test-' . uniqid() . '.jpg';
		$result       = $method->invoke( null, $non_existent );
		$this->assertEquals( $non_existent, $result );

		// Test with existing file - should return path with counter.
		$existing_file = wp_tempnam( 'existing.jpg' ) . '.jpg';
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test file creation.
		file_put_contents( $existing_file, 'test' );

		$result = $method->invoke( null, $existing_file );
		$this->assertNotEquals( $existing_file, $result );
		$this->assertStringContainsString( '-1.jpg', $result );

		// Clean up.
		wp_delete_file( $existing_file );
	}

	/**
	 * Test generate_files_markup with single image.
	 *
	 * @covers ::generate_files_markup
	 */
	public function test_generate_files_markup_single_image() {
		$files = array(
			array(
				'url'       => 'https://example.com/uploads/image.webp',
				'mime_type' => 'image/webp',
				'alt'       => 'Test Image',
			),
		);

		$result = Attachments::generate_files_markup( $files );

		$this->assertStringContainsString( '<!-- wp:image', $result );
		$this->assertStringContainsString( 'https://example.com/uploads/image.webp', $result );
		$this->assertStringContainsString( 'Test Image', $result );
	}

	/**
	 * Test generate_files_markup with multiple images creates gallery.
	 *
	 * @covers ::generate_files_markup
	 * @covers ::get_files_gallery_block
	 */
	public function test_generate_files_markup_gallery() {
		$files = array(
			array(
				'url'       => 'https://example.com/uploads/image1.webp',
				'mime_type' => 'image/webp',
				'alt'       => 'First Image',
			),
			array(
				'url'       => 'https://example.com/uploads/image2.webp',
				'mime_type' => 'image/webp',
				'alt'       => 'Second Image',
			),
		);

		$result = Attachments::generate_files_markup( $files );

		$this->assertStringContainsString( '<!-- wp:gallery', $result );
		$this->assertStringContainsString( 'https://example.com/uploads/image1.webp', $result );
		$this->assertStringContainsString( 'https://example.com/uploads/image2.webp', $result );
		$this->assertStringContainsString( 'First Image', $result );
		$this->assertStringContainsString( 'Second Image', $result );
	}

	/**
	 * Test generate_files_markup with video.
	 *
	 * @covers ::generate_files_markup
	 */
	public function test_generate_files_markup_video() {
		$files = array(
			array(
				'url'       => 'https://example.com/video.mp4',
				'mime_type' => 'video/mp4',
				'alt'       => 'Test Video',
			),
		);

		$result = Attachments::generate_files_markup( $files );

		$this->assertStringContainsString( '<!-- wp:video', $result );
		$this->assertStringContainsString( '<video controls', $result );
		$this->assertStringContainsString( 'https://example.com/video.mp4', $result );
	}

	/**
	 * Test generate_files_markup with audio.
	 *
	 * @covers ::generate_files_markup
	 */
	public function test_generate_files_markup_audio() {
		$files = array(
			array(
				'url'       => 'https://example.com/audio.mp3',
				'mime_type' => 'audio/mpeg',
				'alt'       => 'Test Audio',
			),
		);

		$result = Attachments::generate_files_markup( $files );

		$this->assertStringContainsString( '<!-- wp:audio', $result );
		$this->assertStringContainsString( '<audio controls', $result );
		$this->assertStringContainsString( 'https://example.com/audio.mp3', $result );
	}

	/**
	 * Test generate_files_markup with empty array.
	 *
	 * @covers ::generate_files_markup
	 */
	public function test_generate_files_markup_empty() {
		$result = Attachments::generate_files_markup( array() );

		$this->assertEmpty( $result );
	}

	/**
	 * Test get_files_image_block.
	 *
	 * @covers ::get_files_image_block
	 */
	public function test_get_files_image_block() {
		$file = array(
			'url'       => 'https://example.com/image.webp',
			'mime_type' => 'image/webp',
			'alt'       => 'Test Alt Text',
		);

		$result = Attachments::get_files_image_block( $file );

		$this->assertStringContainsString( '<!-- wp:image', $result );
		$this->assertStringContainsString( '<figure class="wp-block-image size-large">', $result );
		$this->assertStringContainsString( 'src="https://example.com/image.webp"', $result );
		$this->assertStringContainsString( 'alt="Test Alt Text"', $result );
	}

	/**
	 * Test get_files_image_block handles missing alt.
	 *
	 * @covers ::get_files_image_block
	 */
	public function test_get_files_image_block_no_alt() {
		$file = array(
			'url'       => 'https://example.com/image.webp',
			'mime_type' => 'image/webp',
		);

		$result = Attachments::get_files_image_block( $file );

		$this->assertStringContainsString( 'alt=""', $result );
	}

	/**
	 * Test get_files_gallery_block.
	 *
	 * @covers ::get_files_gallery_block
	 */
	public function test_get_files_gallery_block() {
		$files = array(
			array(
				'url'       => 'https://example.com/image1.webp',
				'mime_type' => 'image/webp',
				'alt'       => 'Image 1',
			),
			array(
				'url'       => 'https://example.com/image2.webp',
				'mime_type' => 'image/webp',
				'alt'       => 'Image 2',
			),
		);

		$result = Attachments::get_files_gallery_block( $files );

		$this->assertStringContainsString( '<!-- wp:gallery', $result );
		$this->assertStringContainsString( 'has-nested-images', $result );
		$this->assertStringContainsString( 'columns-2', $result );
		$this->assertStringContainsString( 'https://example.com/image1.webp', $result );
		$this->assertStringContainsString( 'https://example.com/image2.webp', $result );
	}

	/**
	 * Test append_files_to_content for post.
	 *
	 * @covers ::append_files_to_content
	 */
	public function test_append_files_to_content_post() {
		// Create test post with content.
		$post_id = self::factory()->post->create(
			array(
				'post_type'    => 'ap_post',
				'post_content' => 'Original content here.',
			)
		);

		$files = array(
			array(
				'url'       => 'https://example.com/image.webp',
				'mime_type' => 'image/webp',
				'alt'       => 'Test Image',
			),
		);

		Attachments::append_files_to_content( $post_id, $files, 'post' );

		$post = \get_post( $post_id );
		$this->assertStringContainsString( 'Original content here.', $post->post_content );
		$this->assertStringContainsString( '<!-- wp:image', $post->post_content );
	}

	/**
	 * Test append_files_to_content for comment.
	 *
	 * @covers ::append_files_to_content
	 */
	public function test_append_files_to_content_comment() {
		// Create test comment with content.
		$comment_id = self::factory()->comment->create(
			array(
				'comment_content' => 'Original comment content.',
				'comment_post_ID' => self::$post_id,
			)
		);

		$files = array(
			array(
				'url'       => 'https://example.com/image.webp',
				'mime_type' => 'image/webp',
				'alt'       => 'Test Image',
			),
		);

		Attachments::append_files_to_content( $comment_id, $files, 'comment' );

		$comment = \get_comment( $comment_id );
		$this->assertStringContainsString( 'Original comment content.', $comment->comment_content );
		$this->assertStringContainsString( '<!-- wp:image', $comment->comment_content );
	}

	/**
	 * Test generate_files_markup filter.
	 *
	 * @covers ::generate_files_markup
	 */
	public function test_generate_files_markup_filter() {
		$files = array(
			array(
				'url'       => 'https://example.com/image.webp',
				'mime_type' => 'image/webp',
				'alt'       => 'Test Image',
			),
		);

		// Add filter to override markup.
		$custom_markup = '<custom-markup />';
		\add_filter(
			'activitypub_files_media_markup',
			function () use ( $custom_markup ) {
				return $custom_markup;
			}
		);

		$result = Attachments::generate_files_markup( $files );

		$this->assertEquals( $custom_markup, $result );
	}
}
