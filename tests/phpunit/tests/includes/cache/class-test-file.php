<?php
/**
 * File Cache Test Class
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Cache;

use Activitypub\Cache\Avatar;
use WP_UnitTestCase;

/**
 * Test class for the abstract File cache.
 *
 * Uses Avatar as a concrete implementation to test shared functionality
 * since the File class is abstract.
 */
class Test_File extends WP_UnitTestCase {

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
		$method->setAccessible( true );

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
		$method->setAccessible( true );

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
	 * A WebP source is already named after its hash, and sidestepping that name leaves a copy
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

		// The remote file is a WebP, so the cached file is named `<hash>.webp` before it is optimized.
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

		$this->assertStringEndsWith( \md5( $url ) . '.webp', $cached, 'The cached file is named after the URL.' );
		$this->assertSame( $cached, $again, 'The second request finds the cached file.' );
		$this->assertSame( 1, $downloads, 'The file is downloaded once.' );
		$this->assertCount( 1, $files, 'Only one copy of the file is kept.' );

		Avatar::invalidate_entity( $post_id );
	}

	/**
	 * Without `$delete`, the copies are only counted and nothing changes.
	 *
	 * @covers \Activitypub\Cache\File::remove_duplicates
	 */
	public function test_remove_duplicates_counts_without_touching_anything() {
		$dir  = Avatar::get_storage_paths( 'dedupe-count' )['basedir'];
		$hash = \md5( 'https://example.com/avatar.webp' );
		\wp_mkdir_p( $dir );
		foreach ( array( "{$hash}.webp", "{$hash}-1.webp", "{$hash}-2.webp" ) as $name ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			\file_put_contents( "{$dir}/{$name}", 'image' );
		}

		$result = Avatar::remove_duplicates( $dir );

		$this->assertSame(
			array(
				'removed'  => 2,
				'bytes'    => 10,
				'promoted' => 0,
				'failed'   => 0,
			),
			$result
		);
		$this->assertFileExists( "{$dir}/{$hash}-1.webp", 'A dry run leaves the copies in place.' );
		$this->assertFileExists( "{$dir}/{$hash}-2.webp" );

		Avatar::delete_directory( $dir );
	}

	/**
	 * With `$delete`, the copies go and the canonical file stays.
	 *
	 * @covers \Activitypub\Cache\File::remove_duplicates
	 */
	public function test_remove_duplicates_removes_the_copies() {
		$dir  = Avatar::get_storage_paths( 'dedupe-remove' )['basedir'];
		$hash = \md5( 'https://example.com/avatar.jpg' );
		\wp_mkdir_p( $dir );
		foreach ( array( "{$hash}.jpg", "{$hash}-1.jpg", "{$hash}-3.jpg" ) as $name ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			\file_put_contents( "{$dir}/{$name}", 'image' );
		}

		$result = Avatar::remove_duplicates( $dir, true );

		$this->assertSame( 2, $result['removed'] );
		$this->assertSame( 0, $result['promoted'] );
		$this->assertFileExists( "{$dir}/{$hash}.jpg", 'The canonical file is kept.' );
		$this->assertFileDoesNotExist( "{$dir}/{$hash}-1.jpg" );
		$this->assertFileDoesNotExist( "{$dir}/{$hash}-3.jpg" );

		Avatar::delete_directory( $dir );
	}

	/**
	 * With no canonical file, the newest copy takes its name and the rest go.
	 *
	 * @covers \Activitypub\Cache\File::remove_duplicates
	 */
	public function test_remove_duplicates_promotes_the_newest_copy() {
		$dir  = Avatar::get_storage_paths( 'dedupe-promote' )['basedir'];
		$hash = \md5( 'https://example.com/avatar.png' );
		\wp_mkdir_p( $dir );
		foreach ( array( 1, 2, 10 ) as $n ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			\file_put_contents( "{$dir}/{$hash}-{$n}.webp", "copy {$n}" );
		}

		$result = Avatar::remove_duplicates( $dir, true );

		$this->assertSame( 2, $result['removed'] );
		$this->assertSame( 1, $result['promoted'] );
		$this->assertFileExists( "{$dir}/{$hash}.webp" );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$this->assertSame( 'copy 10', \file_get_contents( "{$dir}/{$hash}.webp" ), 'The highest counter, not the first one found, is kept.' );
		$this->assertFileDoesNotExist( "{$dir}/{$hash}-10.webp" );
		$this->assertFileDoesNotExist( "{$dir}/{$hash}-1.webp" );

		Avatar::delete_directory( $dir );
	}

	/**
	 * Copies in another format than the canonical file are duplicates of it all the same.
	 *
	 * The lookup matches `<hash>.*` and serves the first file it finds, so a JPEG promoted next to an
	 * existing WebP would win over it.
	 *
	 * @covers \Activitypub\Cache\File::remove_duplicates
	 */
	public function test_remove_duplicates_treats_formats_as_one_asset() {
		$dir  = Avatar::get_storage_paths( 'dedupe-formats' )['basedir'];
		$hash = \md5( 'https://example.com/avatar.svg' );
		\wp_mkdir_p( $dir );
		foreach ( array( "{$hash}.webp", "{$hash}-1.jpg", "{$hash}-2.jpg", "{$hash}-3.webp" ) as $name ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			\file_put_contents( "{$dir}/{$name}", 'image' );
		}

		$result = Avatar::remove_duplicates( $dir, true );

		$this->assertSame( 3, $result['removed'] );
		$this->assertSame( 0, $result['promoted'], 'A WebP already serves this hash; nothing is promoted beside it.' );
		$this->assertSame( array( "{$dir}/{$hash}.webp" ), \glob( "{$dir}/{$hash}.*" ), 'Exactly one file is left for the hash.' );

		/*
		 * Without any canonical file, the newest copy wins whatever its format. The counter started
		 * over when the format changed, so the higher counter here is the older file.
		 */
		$other = \md5( 'https://example.com/other.svg' );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		\file_put_contents( "{$dir}/{$other}-9.webp", 'older' );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_touch -- The old file has to be old.
		\touch( "{$dir}/{$other}-9.webp", \time() - HOUR_IN_SECONDS );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		\file_put_contents( "{$dir}/{$other}-1.jpg", 'newest' );

		$result = Avatar::remove_duplicates( $dir, true );

		$this->assertSame( 1, $result['promoted'] );
		$this->assertSame( array( "{$dir}/{$other}.jpg" ), \glob( "{$dir}/{$other}.*" ), 'The newest copy became the one file, in its own format, whatever its counter.' );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$this->assertSame( 'newest', \file_get_contents( "{$dir}/{$other}.jpg" ) );

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
}
