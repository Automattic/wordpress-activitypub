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
}
