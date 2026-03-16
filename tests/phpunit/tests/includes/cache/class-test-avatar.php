<?php
/**
 * Avatar Cache Test Class
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Cache;

use Activitypub\Cache\Avatar;
use WP_UnitTestCase;

/**
 * Test class for Avatar cache.
 */
class Test_Avatar extends WP_UnitTestCase {

	/**
	 * Test that get_type returns correct value.
	 */
	public function test_get_type() {
		$this->assertEquals( 'avatar', Avatar::get_type() );
	}

	/**
	 * Test that get_base_dir returns correct value.
	 */
	public function test_get_base_dir() {
		$this->assertEquals( '/activitypub/actors/', Avatar::get_base_dir() );
	}

	/**
	 * Test that get_context returns correct value.
	 */
	public function test_get_context() {
		$this->assertEquals( 'avatar', Avatar::get_context() );
	}

	/**
	 * Test that get_max_dimension returns correct value.
	 */
	public function test_get_max_dimension() {
		$this->assertEquals( 512, Avatar::get_max_dimension() );
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
	 * Test maybe_cache returns unchanged URL for wrong context.
	 */
	public function test_maybe_cache_wrong_context() {
		$url    = 'https://example.com/avatar.jpg';
		$result = Avatar::maybe_cache( $url, 'media', 123 );

		$this->assertEquals( $url, $result );
	}

	/**
	 * Test maybe_cache returns unchanged URL for empty URL.
	 */
	public function test_maybe_cache_empty_url() {
		$result = Avatar::maybe_cache( '', 'avatar', 123 );

		$this->assertEquals( '', $result );
	}

	/**
	 * Test maybe_cache returns unchanged URL for empty entity_id.
	 */
	public function test_maybe_cache_empty_entity_id() {
		$url    = 'https://example.com/avatar.jpg';
		$result = Avatar::maybe_cache( $url, 'avatar', null );

		$this->assertEquals( $url, $result );
	}

	/**
	 * Test maybe_cache caches a valid avatar URL via filesystem.
	 */
	public function test_maybe_cache_caches_valid_url() {
		$post_id = self::factory()->post->create();
		$url     = 'https://example.com/avatar.jpg';

		// Mock the file download to return a local fixture.
		$mock_download = function ( $result, $download_url ) use ( $url ) {
			if ( $download_url === $url ) {
				$tmp_file = \wp_tempnam( 'test-avatar.jpg' );
				copy( AP_TESTS_DIR . '/data/assets/test.jpg', $tmp_file );

				return array(
					'file'      => $tmp_file,
					'mime_type' => 'image/jpeg',
				);
			}

			return $result;
		};

		\add_filter( 'activitypub_pre_download_url', $mock_download, 10, 2 );

		// First call should download and cache.
		$result = Avatar::maybe_cache( $url, 'avatar', $post_id );
		$this->assertNotEquals( $url, $result, 'Should return a local cached URL, not the original' );
		$this->assertStringContainsString( '/activitypub/actors/', $result );

		// No meta should be stored (the old bug).
		$meta = \get_post_meta( $post_id, '_activitypub_avatar_url', true );
		$this->assertEmpty( $meta, 'Should not store avatar URL in post meta' );

		// Second call should return the same cached URL from filesystem.
		$result2 = Avatar::maybe_cache( $url, 'avatar', $post_id );
		$this->assertEquals( $result, $result2, 'Subsequent calls should return the same cached URL' );

		// Clean up.
		\remove_filter( 'activitypub_pre_download_url', $mock_download );
		Avatar::invalidate_entity( $post_id );
	}

	/**
	 * Test maybe_cache returns original URL when download fails.
	 */
	public function test_maybe_cache_returns_original_url_on_failure() {
		$post_id = self::factory()->post->create();
		$url     = 'https://example.com/broken-avatar.jpg';

		// Mock the download to fail by returning false.
		$mock_download = function ( $result, $download_url ) use ( $url ) {
			if ( $download_url === $url ) {
				return false;
			}

			return $result;
		};

		\add_filter( 'activitypub_pre_download_url', $mock_download, 10, 2 );

		// Should return the original URL as fallback.
		$result = Avatar::maybe_cache( $url, 'avatar', $post_id );
		$this->assertEquals( $url, $result, 'Should fall back to the original URL on download failure' );

		// No meta should be stored (this was the root cause of #3038).
		$meta = \get_post_meta( $post_id, '_activitypub_avatar_url', true );
		$this->assertEmpty( $meta, 'Should not persist a broken URL in post meta' );

		// Clean up.
		\remove_filter( 'activitypub_pre_download_url', $mock_download );
	}

	/**
	 * Test save returns false for invalid actor_id.
	 */
	public function test_save_invalid_actor_id() {
		$result = Avatar::save( 0, 'https://example.com/avatar.jpg' );
		$this->assertFalse( $result );

		$result = Avatar::save( -1, 'https://example.com/avatar.jpg' );
		$this->assertFalse( $result );
	}

	/**
	 * Test save returns false for invalid URL.
	 */
	public function test_save_invalid_url() {
		$result = Avatar::save( 123, 'not-a-url' );
		$this->assertFalse( $result );

		$result = Avatar::save( 123, '' );
		$this->assertFalse( $result );
	}

	/**
	 * Test that maybe_cleanup only runs for ap_actor post type.
	 */
	public function test_maybe_cleanup_wrong_post_type() {
		// Create a regular post.
		$post_id = self::factory()->post->create();

		// Create a file in the avatar directory.
		$paths = Avatar::get_storage_paths( $post_id );
		wp_mkdir_p( $paths['basedir'] );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $paths['basedir'] . '/test.txt', 'test' );

		// Call cleanup.
		Avatar::maybe_cleanup( $post_id );

		// File should still exist (not cleaned up because wrong post type).
		$this->assertTrue( file_exists( $paths['basedir'] . '/test.txt' ) );

		// Clean up.
		Avatar::invalidate_entity( $post_id );
	}

	/**
	 * Test filter registration on init.
	 */
	public function test_init_registers_filter() {
		Avatar::init();

		$this->assertNotFalse(
			has_filter( 'activitypub_remote_media_url', array( Avatar::class, 'maybe_cache' ) )
		);
	}

	/**
	 * Test action registration on init.
	 */
	public function test_init_registers_action() {
		Avatar::init();

		$this->assertNotFalse(
			has_action( 'before_delete_post', array( Avatar::class, 'maybe_cleanup' ) )
		);
	}
}
