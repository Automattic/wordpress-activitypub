<?php
/**
 * File Cache Test Class
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Cache;

use Activitypub\Cache\Avatar;
use Activitypub\Tests\Cache_Directory_Stream;
use Activitypub\Tests\Image_Download_Stub;
use Activitypub\Tests\Remote_Request_Stub;
use WP_UnitTestCase;

/**
 * Test class for the abstract File cache.
 *
 * Uses Avatar as a concrete implementation to test shared functionality
 * since the File class is abstract.
 */
class Test_File extends WP_UnitTestCase {
	use Image_Download_Stub;
	use Remote_Request_Stub;

	/**
	 * A failed download is not tried again for a while, so page views do not wait for it again.
	 * Caching a file directly still downloads it.
	 *
	 * @covers \Activitypub\Cache\File::get_or_cache
	 */
	public function test_failed_download_is_remembered() {
		$url = 'https://slow.example.com/photo.jpg';

		\add_filter( 'activitypub_cache_is_safe_url', '__return_true' );
		$this->stub_remote_requests();
		$this->responses[ $url ] = new \WP_Error( 'http_request_failed', 'The remote host timed out.' );

		Avatar::get_or_cache( $url, 1 );
		$remembered = Avatar::get_or_cache( $url, 1 );
		$requests   = $this->requests;
		Avatar::cache( $url, 1 );

		$this->unstub_remote_requests();
		\remove_filter( 'activitypub_cache_is_safe_url', '__return_true' );

		$this->assertFalse( $remembered );
		$this->assertSame( 1, $requests );
		$this->assertSame( 2, $this->requests );
	}

	/**
	 * The stored file is the image editor's output, not the download.
	 *
	 * @covers \Activitypub\Cache\File::cache
	 * @covers \Activitypub\Cache\File::optimize_image
	 */
	public function test_cache_stores_only_what_the_editor_wrote() {
		$post_id  = self::factory()->post->create();
		$url      = 'https://example.com/photo.jpg';
		$download = static function () {
			$download = self::image_download();
			\file_put_contents( $download['file'], 'extra-bytes-after-the-image', FILE_APPEND ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

			return $download;
		};

		\add_filter( 'activitypub_pre_download_url', $download );
		$cached = Avatar::cache( $url, $post_id );
		\remove_filter( 'activitypub_pre_download_url', $download );

		$this->assertNotFalse( $cached );
		$file = Avatar::get_storage_paths( $post_id )['basedir'] . '/' . \basename( $cached );
		$this->assertStringNotContainsString( 'extra-bytes-after-the-image', (string) \file_get_contents( $file ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$this->assertSame( \md5( $url ) . '.jpg', \basename( $file ) );

		Avatar::invalidate_entity( $post_id );
	}

	/**
	 * An image that declares more pixels than the limit is not cached, so nothing decodes it.
	 *
	 * @covers \Activitypub\Cache\File::cache
	 * @covers \Activitypub\Cache\File::optimize_image
	 */
	public function test_cache_skips_an_image_above_the_pixel_limit() {
		$download = static function () {
			$file = \get_temp_dir() . \wp_unique_filename( \get_temp_dir(), 'bomb.gif' );
			\file_put_contents( $file, 'GIF89a' . \pack( 'vv', 30000, 30000 ) . "\x00\x00\x00;" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

			return array(
				'file'      => $file,
				'mime_type' => 'image/gif',
			);
		};
		$post_id  = self::factory()->post->create();

		// WordPress picks an image editor before it decodes, so nothing may ask for one.
		$asked_for_editor = false;
		$editors          = static function ( $editors ) use ( &$asked_for_editor ) {
			$asked_for_editor = true;

			return $editors;
		};

		\add_filter( 'activitypub_pre_download_url', $download );
		\add_filter( 'wp_image_editors', $editors );
		$cached = Avatar::cache( 'https://example.com/bomb.gif', $post_id );
		\remove_filter( 'activitypub_pre_download_url', $download );
		\remove_filter( 'wp_image_editors', $editors );

		$this->assertFalse( $cached );
		$this->assertFalse( $asked_for_editor );

		Avatar::invalidate_entity( $post_id );
	}

	/**
	 * A JPEG is cached as a JPEG and every other image as a PNG, named after the URL.
	 *
	 * @dataProvider data_cache_output_formats
	 *
	 * @covers \Activitypub\Cache\File::cache
	 * @covers \Activitypub\Cache\File::optimize_image
	 *
	 * @param string $format   The GD function suffix that writes the source image.
	 * @param string $expected The extension of the cached file.
	 */
	public function test_cache_writes_jpeg_or_png( $format, $expected ) {
		if ( ! \function_exists( 'image' . $format ) ) {
			$this->markTestSkipped( "GD cannot write {$format}." );
		}

		$download = static function () use ( $format ) {
			$file  = \get_temp_dir() . \wp_unique_filename( \get_temp_dir(), 'source.' . $format );
			$image = \imagecreatetruecolor( 10, 10 );
			\call_user_func( 'image' . $format, $image, $file );

			return array(
				'file'      => $file,
				'mime_type' => \wp_check_filetype( $file )['type'],
			);
		};
		$post_id  = self::factory()->post->create();
		$url      = 'https://example.com/source.' . $format;

		\add_filter( 'activitypub_pre_download_url', $download );
		$cached = Avatar::cache( $url, $post_id );
		\remove_filter( 'activitypub_pre_download_url', $download );

		if ( false === $cached && 'webp' === $format ) {
			$this->markTestSkipped( 'The image editor cannot read WebP.' );
		}

		$this->assertStringEndsWith( '/' . \md5( $url ) . '.' . $expected, (string) $cached );
		$this->assertSame( array( \md5( $url ) . '.' . $expected ), \array_map( 'basename', (array) \glob( Avatar::get_storage_paths( $post_id )['basedir'] . '/*' ) ) );

		Avatar::invalidate_entity( $post_id );
	}

	/**
	 * Source formats and the extension they are cached under.
	 *
	 * @return array[]
	 */
	public function data_cache_output_formats() {
		return array(
			'jpeg' => array( 'jpeg', 'jpg' ),
			'png'  => array( 'png', 'png' ),
			'gif'  => array( 'gif', 'png' ),
			'webp' => array( 'webp', 'png' ),
		);
	}

	/**
	 * A newer copy in the other format replaces the old one, so it is found and not downloaded again.
	 *
	 * @covers \Activitypub\Cache\File::cache
	 * @covers \Activitypub\Cache\File::get_or_cache
	 */
	public function test_cache_replaces_a_copy_in_the_other_format() {
		$post_id   = self::factory()->post->create();
		$url       = 'https://example.com/emoji';
		$format    = 'jpeg';
		$downloads = 0;
		$download  = static function () use ( &$format, &$downloads ) {
			++$downloads;
			$file  = \get_temp_dir() . \wp_unique_filename( \get_temp_dir(), 'source.' . $format );
			$image = \imagecreatetruecolor( 10, 10 );
			\call_user_func( 'image' . $format, $image, $file );

			return array(
				'file'      => $file,
				'mime_type' => \wp_check_filetype( $file )['type'],
			);
		};
		$basedir   = Avatar::get_storage_paths( $post_id )['basedir'];

		\add_filter( 'activitypub_pre_download_url', $download );
		$first = Avatar::get_or_cache( $url, $post_id );
		\touch( $basedir . '/' . \basename( $first ), \time() - HOUR_IN_SECONDS ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_touch
		\clearstatcache();

		// The source is now a PNG, updated after the first copy was made.
		$format  = 'png';
		$options = array( 'updated' => \gmdate( 'c', \time() - MINUTE_IN_SECONDS ) );
		$second  = Avatar::get_or_cache( $url, $post_id, $options );
		$third   = Avatar::get_or_cache( $url, $post_id, $options );
		\remove_filter( 'activitypub_pre_download_url', $download );

		$this->assertStringEndsWith( '.jpg', (string) $first );
		$this->assertStringEndsWith( '.png', (string) $second );
		$this->assertSame( $second, $third );
		$this->assertSame( 2, $downloads );
		$this->assertSame( array( \md5( $url ) . '.png' ), \array_map( 'basename', (array) \glob( $basedir . '/*' ) ) );

		Avatar::invalidate_entity( $post_id );
	}

	/**
	 * A site that maps the editor's output to another format still gets the format the cache picks.
	 *
	 * @covers \Activitypub\Cache\File::cache
	 * @covers \Activitypub\Cache\File::optimize_image
	 */
	public function test_cache_ignores_the_site_output_format() {
		$editor = \wp_get_image_editor( AP_TESTS_DIR . '/data/assets/test.jpg' );
		if ( \is_wp_error( $editor ) || ! $editor->supports_mime_type( 'image/webp' ) ) {
			$this->markTestSkipped( 'The image editor cannot write WebP.' );
		}

		$output_format = static function () {
			return array( 'image/jpeg' => 'image/webp' );
		};
		$download      = static function () {
			return self::image_download();
		};
		$post_id       = self::factory()->post->create();
		$url           = 'https://example.com/photo.jpg';

		\add_filter( 'image_editor_output_format', $output_format );
		\add_filter( 'activitypub_pre_download_url', $download );
		$cached = Avatar::cache( $url, $post_id );
		\remove_filter( 'activitypub_pre_download_url', $download );
		\remove_filter( 'image_editor_output_format', $output_format );

		$this->assertStringEndsWith( '/' . \md5( $url ) . '.jpg', (string) $cached );
		$file = Avatar::get_storage_paths( $post_id )['basedir'] . '/' . \basename( (string) $cached );
		$this->assertSame( 'image/jpeg', ( new \finfo( FILEINFO_MIME_TYPE ) )->file( $file ) );

		Avatar::invalidate_entity( $post_id );
	}

	/**
	 * A file with a valid image header that the editor cannot decode is not cached.
	 *
	 * @covers \Activitypub\Cache\File::cache
	 * @covers \Activitypub\Cache\File::optimize_image
	 */
	public function test_cache_rejects_an_image_the_editor_cannot_decode() {
		$header   = 'IHDR' . \pack( 'NN', 16, 16 ) . "\x08\x06\x00\x00\x00";
		$tmp_file = \get_temp_dir() . \wp_unique_filename( \get_temp_dir(), 'header-only.png' );
		\file_put_contents( $tmp_file, "\x89PNG\r\n\x1a\n" . \pack( 'N', 13 ) . $header . \pack( 'N', \crc32( $header ) ) . 'not-image-data' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$download = static function () use ( $tmp_file ) {
			return array(
				'file'      => $tmp_file,
				'mime_type' => 'image/png',
			);
		};
		$post_id  = self::factory()->post->create();

		\add_filter( 'activitypub_pre_download_url', $download );
		$cached = Avatar::cache( 'https://example.com/header-only.png', $post_id );
		\remove_filter( 'activitypub_pre_download_url', $download );

		$this->assertFalse( $cached );
		$this->assertFileDoesNotExist( $tmp_file );
		$this->assertSame( array(), (array) \glob( Avatar::get_storage_paths( $post_id )['basedir'] . '/*' ) );
	}

	/**
	 * A file the image editor cannot read is not cached and leaves nothing behind.
	 *
	 * @covers \Activitypub\Cache\File::cache
	 * @covers \Activitypub\Cache\File::optimize_image
	 */
	public function test_cache_rejects_what_the_editor_cannot_read() {
		$post_id  = self::factory()->post->create();
		$fake     = self::image_download();
		$tmp_file = $fake['file'];
		\file_put_contents( $tmp_file, 'not an image' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$download = static function () use ( $fake ) {
			return $fake;
		};

		\add_filter( 'activitypub_pre_download_url', $download );
		$cached = Avatar::cache( 'https://example.com/fake.png', $post_id );
		\remove_filter( 'activitypub_pre_download_url', $download );

		$this->assertFalse( $cached );
		$this->assertFileDoesNotExist( $tmp_file );
		$this->assertSame( array(), (array) \glob( Avatar::get_storage_paths( $post_id )['basedir'] . '/*' ) );
	}

	/**
	 * A file whose extension does not match its content gets a name of its own, with the extension of its content.
	 *
	 * @covers \Activitypub\Cache\File::validate_mime_type
	 */
	public function test_validate_mime_type_gives_the_file_its_own_name() {
		$download = self::image_download();
		$file     = \preg_replace( '/\.jpg$/', '.txt', $download['file'] );
		\rename( $download['file'], $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename

		$method = new \ReflectionMethod( Avatar::class, 'validate_mime_type' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}
		$result = $method->invoke( null, $file );

		$this->assertIsString( $result );
		$this->assertSame( 'jpg', \pathinfo( $result, PATHINFO_EXTENSION ) );
		$this->assertStringNotContainsString( \pathinfo( $file, PATHINFO_FILENAME ), \basename( $result ) );
		$this->assertFileExists( $result );
		$this->assertFileDoesNotExist( $file );

		\wp_delete_file( $result );
	}

	/**
	 * Only the names the cache gives its files are served.
	 *
	 * @covers \Activitypub\Cache\File::get
	 */
	public function test_get_serves_only_the_names_the_cache_gives() {
		$url  = 'https://example.com/image';
		$dir  = Avatar::get_storage_paths( 'get-images' )['basedir'];
		$hash = \md5( $url );
		\wp_mkdir_p( $dir );
		foreach ( array( 'txt', 'webp', 'gif', 'bmp' ) as $extension ) {
			\file_put_contents( "{$dir}/{$hash}.{$extension}", 'file' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}

		$this->assertFalse( Avatar::get( $url, 'get-images' ) );

		\file_put_contents( "{$dir}/{$hash}.png", 'file' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$this->assertStringEndsWith( "/{$hash}.png", (string) Avatar::get( $url, 'get-images' ) );

		Avatar::delete_directory( $dir );
	}

	/**
	 * The download goes to a temporary file of the plugin's own, and stops one byte over the size limit.
	 *
	 * @covers \Activitypub\Cache\File::cache
	 */
	public function test_download_is_streamed_with_a_size_limit() {
		$url     = 'https://example.com/image.jpg';
		$request = array();
		$respond = static function ( $pre, $args, $request_url ) use ( $url, &$request ) {
			if ( $request_url !== $url ) {
				return $pre;
			}
			$request = $args;

			return new \WP_Error( 'http_request_failed', 'Stopped by the test.' );
		};

		\add_filter( 'activitypub_cache_is_safe_url', '__return_true' );
		\add_filter( 'pre_http_request', $respond, 10, 3 );
		$cached = Avatar::cache( $url, self::factory()->post->create() );
		\remove_filter( 'pre_http_request', $respond );
		\remove_filter( 'activitypub_cache_is_safe_url', '__return_true' );

		$this->assertFalse( $cached );
		$this->assertTrue( $request['stream'] );
		$this->assertSame( Avatar::MAX_FILE_SIZE + 1, $request['limit_response_size'] );
		$this->assertTrue( $request['reject_unsafe_urls'] );
		$this->assertStringStartsWith( 'activitypub-cache', \basename( $request['filename'] ) );
		$this->assertFileDoesNotExist( $request['filename'] );
	}

	/**
	 * Only a valid `updated` newer than the cached file downloads it again, not one in the future or an invalid one.
	 *
	 * @covers \Activitypub\Cache\File::get_or_cache
	 */
	public function test_updated_in_the_future_does_not_download_again() {
		$post_id   = self::factory()->post->create();
		$url       = 'https://example.com/avatar.jpg';
		$downloads = 0;
		$download  = static function () use ( &$downloads ) {
			++$downloads;

			return self::image_download();
		};

		\add_filter( 'activitypub_pre_download_url', $download );
		$cached = Avatar::get_or_cache( $url, $post_id );
		foreach ( array( '2099-01-01T00:00:00Z', 'not a date' ) as $updated ) {
			$this->assertSame( $cached, Avatar::get_or_cache( $url, $post_id, array( 'updated' => $updated ) ), $updated );
		}
		$this->assertSame( 1, $downloads );

		// A real update downloads again.
		$file = Avatar::get_storage_paths( $post_id )['basedir'] . '/' . \basename( $cached );
		\touch( $file, \time() - HOUR_IN_SECONDS ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_touch
		\clearstatcache();
		Avatar::get_or_cache( $url, $post_id, array( 'updated' => \gmdate( 'c', \time() - MINUTE_IN_SECONDS ) ) );
		\remove_filter( 'activitypub_pre_download_url', $download );

		$this->assertSame( 2, $downloads );

		Avatar::invalidate_entity( $post_id );
	}

	/**
	 * Test that is_enabled returns true by default.
	 */
	public function test_is_enabled_default() {
		$this->assertTrue( Avatar::is_enabled() );
	}

	/**
	 * Test that is_enabled respects filter.
	 */
	public function test_is_enabled_filter() {
		add_filter( 'activitypub_cache_avatar_enabled', '__return_false' );
		$this->assertFalse( Avatar::is_enabled() );
		remove_filter( 'activitypub_cache_avatar_enabled', '__return_false' );

		$this->assertTrue( Avatar::is_enabled() );
	}

	/**
	 * Test get_storage_paths returns correct structure.
	 */
	public function test_get_storage_paths() {
		$paths = Avatar::get_storage_paths( 123 );

		$this->assertIsArray( $paths );
		$this->assertArrayHasKey( 'basedir', $paths );
		$this->assertArrayHasKey( 'baseurl', $paths );

		$upload_dir = wp_upload_dir();
		$this->assertStringContainsString( $upload_dir['basedir'], $paths['basedir'] );
		$this->assertStringContainsString( $upload_dir['baseurl'], $paths['baseurl'] );
		$this->assertStringContainsString( '123', $paths['basedir'] );
		$this->assertStringContainsString( '123', $paths['baseurl'] );
	}

	/**
	 * Test get returns false for non-existent cache.
	 */
	public function test_get_returns_false_for_non_existent() {
		$result = Avatar::get( 'https://example.com/image.jpg', 'test-entity' );
		$this->assertFalse( $result );
	}

	/**
	 * Test get returns false for invalid URL.
	 */
	public function test_get_returns_false_for_invalid_url() {
		$result = Avatar::get( 'not-a-url', 'test-entity' );
		$this->assertFalse( $result );
	}

	/**
	 * Test get returns false for empty URL.
	 */
	public function test_get_returns_false_for_empty_url() {
		$result = Avatar::get( '', 'test-entity' );
		$this->assertFalse( $result );
	}

	/**
	 * Test invalidate_entity removes directory.
	 */
	public function test_invalidate_entity() {
		$paths = Avatar::get_storage_paths( 'test-invalidate' );

		// Create directory with a file.
		wp_mkdir_p( $paths['basedir'] );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $paths['basedir'] . '/test.txt', 'test content' );

		$this->assertTrue( is_dir( $paths['basedir'] ) );

		// Invalidate.
		$result = Avatar::invalidate_entity( 'test-invalidate' );

		$this->assertTrue( $result );
		$this->assertFalse( is_dir( $paths['basedir'] ) );
	}

	/**
	 * Test invalidate_entity returns true for non-existent directory.
	 */
	public function test_invalidate_entity_non_existent() {
		$result = Avatar::invalidate_entity( 'non-existent-entity' );
		$this->assertTrue( $result );
	}

	/**
	 * Test validate_mime_type accepts valid JPEG files.
	 *
	 * Validates that wp_check_filetype_and_ext receives a proper
	 * extension-to-MIME map instead of a plain MIME list.
	 */
	public function test_validate_mime_type_accepts_valid_jpeg() {
		$method = new \ReflectionMethod( Avatar::class, 'validate_mime_type' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		// Copy test asset to a temp file (simulates download_url() output with .tmp extension).
		$tmp_file = \wp_tempnam( 'test-image.jpg' );
		copy( AP_TESTS_DIR . '/data/assets/test.jpg', $tmp_file );

		$result = $method->invoke( null, $tmp_file );

		// Should return a file path string, not a WP_Error.
		$this->assertNotWPError( $result, 'validate_mime_type should accept valid JPEG files' );
		$this->assertIsString( $result );

		// Clean up.
		if ( \file_exists( $result ) && $result !== $tmp_file ) {
			\wp_delete_file( $result );
		}
		if ( \file_exists( $tmp_file ) ) {
			\wp_delete_file( $tmp_file );
		}
	}

	/**
	 * Test validate_mime_type rejects non-image files.
	 */
	public function test_validate_mime_type_rejects_text_file() {
		$method = new \ReflectionMethod( Avatar::class, 'validate_mime_type' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		$tmp_file = \wp_tempnam( 'test.txt' );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		\file_put_contents( $tmp_file, 'This is plain text, not an image.' );

		$result = $method->invoke( null, $tmp_file );

		$this->assertWPError( $result );

		// Clean up.
		if ( \file_exists( $tmp_file ) ) {
			\wp_delete_file( $tmp_file );
		}
	}

	/**
	 * A converted file keeps the name the cache looks it up under.
	 *
	 * A WebP source can already be named after its hash, and sidestepping that name leaves a copy
	 * behind on every request, because the lookup only finds the hash.
	 *
	 * @covers \Activitypub\Cache\File::optimize_image
	 */
	public function test_cache_keeps_the_hash_name_when_the_source_is_webp() {
		$editor = \wp_get_image_editor( AP_TESTS_DIR . '/data/assets/test.jpg' );

		if ( \is_wp_error( $editor ) || ! $editor->supports_mime_type( 'image/webp' ) ) {
			$this->markTestSkipped( 'The image editor cannot write WebP.' );
		}

		$post_id   = self::factory()->post->create();
		$url       = 'https://example.com/avatar.webp';
		$downloads = 0;

		// The remote file is a WebP, named like the file the cache will look for.
		$mock_download = function ( $result, $download_url ) use ( $url, $editor, &$downloads ) {
			if ( $download_url !== $url ) {
				return $result;
			}

			++$downloads;

			// `wp_tempnam()` creates the file it names, and only its name is needed here.
			$placeholder = \wp_tempnam( 'test-avatar' );
			$tmp_file    = \preg_replace( '/\.tmp$/', '.webp', $placeholder );
			$editor->save( $tmp_file, 'image/webp' );
			\wp_delete_file( $placeholder );

			return array(
				'file'      => $tmp_file,
				'mime_type' => 'image/webp',
			);
		};

		\add_filter( 'activitypub_pre_download_url', $mock_download, 10, 2 );
		$cached = Avatar::maybe_cache( $url, 'avatar', $post_id );
		$again  = Avatar::maybe_cache( $url, 'avatar', $post_id );
		\remove_filter( 'activitypub_pre_download_url', $mock_download );

		$paths = Avatar::get_storage_paths( $post_id );
		$files = \glob( $paths['basedir'] . '/*' );

		$this->assertStringEndsWith( \md5( $url ) . '.png', $cached, 'The cached file is named after the URL.' );
		$this->assertSame( $cached, $again, 'The second request finds the cached file.' );
		$this->assertSame( 1, $downloads, 'The file is downloaded once.' );
		$this->assertCount( 1, $files, 'Only one copy of the file is kept.' );

		Avatar::invalidate_entity( $post_id );
	}

	/**
	 * A dry run leaves both formats untouched; deletion keeps the canonical image.
	 *
	 * @covers \Activitypub\Cache\File::remove_duplicates
	 */
	public function test_remove_duplicates_counts_then_removes_copies() {
		$dir  = Avatar::get_storage_paths( 'dedupe-remove' )['basedir'];
		$hash = \md5( 'https://example.com/avatar.webp' );
		\wp_mkdir_p( $dir );
		foreach ( array( "{$hash}.webp", "{$hash}-1.jpg", "{$hash}-3.webp" ) as $name ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			\file_put_contents( "{$dir}/{$name}", 'image' );
		}
		$before = \glob( "{$dir}/*" );

		$preview = Avatar::remove_duplicates( $dir );
		$this->assertSame(
			array(
				'removed'  => 2,
				'bytes'    => 10,
				'promoted' => 0,
				'failed'   => 0,
			),
			$preview
		);
		$this->assertSame( $before, \glob( "{$dir}/*" ), 'A preview changes nothing.' );
		$this->assertSame( $preview, Avatar::remove_duplicates( $dir, true ) );
		$this->assertSame( array( "{$dir}/{$hash}.webp" ), \glob( "{$dir}/*" ) );
		Avatar::delete_directory( $dir );
	}

	/**
	 * Modification time, not the format's counter, determines the promoted copy.
	 *
	 * @covers \Activitypub\Cache\File::remove_duplicates
	 */
	public function test_remove_duplicates_promotes_the_newest_format() {
		$dir  = Avatar::get_storage_paths( 'dedupe-formats' )['basedir'];
		$hash = \md5( 'https://example.com/avatar.svg' );
		\wp_mkdir_p( $dir );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		\file_put_contents( "{$dir}/{$hash}-9.webp", 'older' );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_touch -- Different formats have independent counters.
		\touch( "{$dir}/{$hash}-9.webp", \time() - HOUR_IN_SECONDS );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		\file_put_contents( "{$dir}/{$hash}-1.jpg", 'newest' );

		$preview = Avatar::remove_duplicates( $dir );
		$this->assertSame( 1, $preview['removed'] );
		$this->assertSame( 5, $preview['bytes'] );
		$this->assertSame( 1, $preview['promoted'] );
		$this->assertCount( 2, \glob( "{$dir}/*" ) );
		$this->assertSame( $preview, Avatar::remove_duplicates( $dir, true ) );
		$this->assertSame( array( "{$dir}/{$hash}.jpg" ), \glob( "{$dir}/*" ) );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$this->assertSame( 'newest', \file_get_contents( "{$dir}/{$hash}.jpg" ) );
		Avatar::delete_directory( $dir );
	}

	/**
	 * Only the exact shape the old code produced is touched.
	 *
	 * @covers \Activitypub\Cache\File::remove_duplicates
	 */
	public function test_remove_duplicates_leaves_other_files_alone() {
		$dir  = Avatar::get_storage_paths( 'dedupe-others' )['basedir'];
		$hash = \md5( 'https://example.com/avatar.gif' );
		\wp_mkdir_p( $dir . '/nested' );
		$others = array(
			"{$hash}.webp",          // The canonical file itself.
			'photo-1.webp',          // Not a hash.
			"{$hash}-1.png",         // A format the conversion never produced.
			"{$hash}-0.webp",        // Not a counter wp_unique_filename() hands out.
			\substr( $hash, 0, 8 ) . '-1.webp', // Not a full hash.
		);
		foreach ( $others as $name ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			\file_put_contents( "{$dir}/{$name}", 'image' );
		}
		// A copy in a subdirectory is still found.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		\file_put_contents( "{$dir}/nested/{$hash}-1.webp", 'image' );

		$result = Avatar::remove_duplicates( $dir, true );

		$this->assertSame( 0, $result['removed'], 'Nothing to remove: the nested copy has no canonical file and is promoted.' );
		$this->assertSame( 1, $result['promoted'] );
		foreach ( $others as $name ) {
			$this->assertFileExists( "{$dir}/{$name}", "{$name} is not a duplicate." );
		}
		$this->assertFileExists( "{$dir}/nested/{$hash}.webp" );

		Avatar::delete_directory( $dir );
	}

	/**
	 * A linked directory is not followed, so the cleanup cannot leave the cache or run in circles.
	 *
	 * @covers \Activitypub\Cache\File::remove_duplicates
	 */
	public function test_remove_duplicates_does_not_follow_links() {
		$dir     = Avatar::get_storage_paths( 'dedupe-links' )['basedir'];
		$outside = \get_temp_dir() . 'activitypub-dedupe-outside-' . \wp_generate_password( 8, false );
		$hash    = \md5( 'https://example.com/linked.webp' );
		\wp_mkdir_p( $dir );
		\wp_mkdir_p( $outside );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		\file_put_contents( "{$outside}/{$hash}-1.webp", 'not ours to remove' );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_symlink -- The link is the thing under test.
		\symlink( $outside, "{$dir}/elsewhere" );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_symlink -- A link back up must not recurse.
		\symlink( $dir, "{$dir}/loop" );

		$result = Avatar::remove_duplicates( $dir, true );

		$this->assertSame( 0, $result['removed'] + $result['promoted'], 'Nothing behind a link is touched.' );
		$this->assertFileExists( "{$outside}/{$hash}-1.webp" );

		// The links go first: the recursive delete below follows links, into the outside directory and round the loop.
		\wp_delete_file( "{$dir}/elsewhere" );
		\wp_delete_file( "{$dir}/loop" );
		Avatar::delete_directory( $dir );
		Avatar::delete_directory( $outside );
	}

	/**
	 * A directory that does not exist is nothing to clean.
	 *
	 * @covers \Activitypub\Cache\File::remove_duplicates
	 */
	public function test_remove_duplicates_handles_a_missing_directory() {
		$this->assertSame(
			array(
				'removed'  => 0,
				'bytes'    => 0,
				'promoted' => 0,
				'failed'   => 0,
			),
			Avatar::remove_duplicates( Avatar::get_storage_paths( 'nope' )['basedir'], true )
		);
	}

	/**
	 * Read failures are counted, but a vanished directory remains harmless.
	 *
	 * @dataProvider directory_read_cases
	 * @covers \Activitypub\Cache\File::remove_duplicates
	 * @param string $kind   Directory state.
	 * @param int    $failed Expected failure count.
	 * @param bool   $delete Whether to delete duplicates.
	 */
	public function test_remove_duplicates_reports_directory_read_failures( $kind, $failed, $delete ) {
		require_once AP_TESTS_DIR . '/includes/class-cache-directory-stream.php';
		Cache_Directory_Stream::$vanished = false;
		\stream_wrapper_register( 'activitypubcachetest', Cache_Directory_Stream::class );

		$directory = 'empty' === $kind ? Avatar::get_storage_paths( 'dedupe-empty' )['basedir'] : 'activitypubcachetest://' . $kind;
		if ( 'empty' === $kind ) {
			\wp_mkdir_p( $directory );
		}
		$result = Avatar::remove_duplicates( $directory, $delete );
		if ( 'empty' === $kind ) {
			Avatar::delete_directory( $directory );
		}

		\stream_wrapper_unregister( 'activitypubcachetest' );
		\clearstatcache();
		Cache_Directory_Stream::$vanished = false;

		$this->assertSame( $failed, $result['failed'] );
		$this->assertSame( 0, $result['removed'] + $result['promoted'] + $result['bytes'] );
	}

	/**
	 * Directory states in dry-run and deletion modes.
	 *
	 * @return array Test cases.
	 */
	public function directory_read_cases() {
		$cases = array();
		foreach ( array( false, true ) as $delete ) {
			foreach ( array(
				'unreadable'   => 1,
				'open-failure' => 1,
				'vanished'     => 0,
				'missing'      => 0,
				'empty'        => 0,
			) as $kind => $failed ) {
				$cases[ $kind . ( $delete ? '-delete' : '-dry-run' ) ] = array( $kind, $failed, $delete );
			}
		}
		return $cases;
	}

	/**
	 * A heavily duplicated hash uses bounded memory and keeps its newest copy.
	 *
	 * @covers \Activitypub\Cache\File::remove_duplicates
	 */
	public function test_remove_duplicates_bounds_memory_with_many_copies() {
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';

		$cache              = new class() extends Avatar {
			/**
			 * Filesystem used to measure memory during cleanup.
			 *
			 * @var \WP_Filesystem_Direct
			 */
			public static $filesystem;

			/**
			 * Get the measuring filesystem.
			 *
			 * @return \WP_Filesystem_Direct The filesystem.
			 */
			protected static function get_filesystem() {
				return self::$filesystem;
			}
		};
		$filesystem         = new \WP_Filesystem_Direct( null );
		$memory             = 0;
		$cache::$filesystem = $this->getMockBuilder( \WP_Filesystem_Direct::class )
			->setConstructorArgs( array( null ) )
			->onlyMethods( array( 'move' ) )
			->getMock();
		$cache::$filesystem->method( 'move' )->willReturnCallback(
			static function ( $source, $destination, $overwrite = false ) use ( &$memory, $filesystem ) {
				$memory = \memory_get_usage();
				return $filesystem->move( $source, $destination, $overwrite );
			}
		);

		$dir    = Avatar::get_storage_paths( 'dedupe-many' )['basedir'];
		$hash   = \md5( 'https://example.com/many.webp' );
		$copies = 10000;
		\wp_mkdir_p( $dir );

		try {
			for ( $n = 1; $n <= $copies; ++$n ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
				\file_put_contents( "{$dir}/{$hash}-{$n}.webp", 'image' );
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_touch -- Ensure counters break equal modification times.
				\touch( "{$dir}/{$hash}-{$n}.webp", 1000000000 );
			}
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			\file_put_contents( "{$dir}/{$hash}-{$copies}.webp", 'newest' );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_touch -- Keep the tie deterministic.
			\touch( "{$dir}/{$hash}-{$copies}.webp", 1000000000 );

			$before = \memory_get_usage();
			$result = $cache::remove_duplicates( $dir, true );

			$this->assertLessThan( 2 * MB_IN_BYTES, $memory - $before, 'Memory must not grow with the number of copies of one hash.' );
			$this->assertSame( $copies - 1, $result['removed'] );
			$this->assertSame( 5 * ( $copies - 1 ), $result['bytes'] );
			$this->assertSame( 1, $result['promoted'] );
			$this->assertSame( 0, $result['failed'] );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$this->assertSame( 'newest', \file_get_contents( "{$dir}/{$hash}.webp" ) );
			$this->assertCount( 1, \glob( "{$dir}/*" ) );
		} finally {
			Avatar::delete_directory( $dir );
		}
	}
}
