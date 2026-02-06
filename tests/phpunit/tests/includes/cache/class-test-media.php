<?php
/**
 * Media Cache Test Class
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Cache;

use Activitypub\Cache\Media;
use WP_UnitTestCase;

/**
 * Test class for Media cache.
 */
class Test_Media extends WP_UnitTestCase {

	/**
	 * Test that get_type returns correct value.
	 */
	public function test_get_type() {
		$this->assertEquals( 'media', Media::get_type() );
	}

	/**
	 * Test that get_base_dir returns correct value.
	 */
	public function test_get_base_dir() {
		$this->assertEquals( '/activitypub/ap_posts/', Media::get_base_dir() );
	}

	/**
	 * Test that get_context returns correct value.
	 */
	public function test_get_context() {
		$this->assertEquals( 'media', Media::get_context() );
	}

	/**
	 * Test that get_max_dimension returns correct value.
	 */
	public function test_get_max_dimension() {
		$this->assertEquals( 1200, Media::get_max_dimension() );
	}

	/**
	 * Test that is_enabled returns true by default.
	 */
	public function test_is_enabled_default() {
		$this->assertTrue( Media::is_enabled() );
	}

	/**
	 * Test that is_enabled respects filter.
	 */
	public function test_is_enabled_filter() {
		add_filter( 'activitypub_cache_media_enabled', '__return_false' );
		$this->assertFalse( Media::is_enabled() );
		remove_filter( 'activitypub_cache_media_enabled', '__return_false' );

		$this->assertTrue( Media::is_enabled() );
	}

	/**
	 * Test cache_post_media skips non-existent posts.
	 */
	public function test_cache_post_media_non_existent_post() {
		// Should not throw error for non-existent post.
		Media::cache_post_media( 999999 );
		$this->assertTrue( true );
	}

	/**
	 * Test cache_post_media skips posts without content.
	 */
	public function test_cache_post_media_empty_content() {
		$post_id = self::factory()->post->create(
			array(
				'post_type'    => 'ap_post',
				'post_content' => '',
			)
		);

		// Should not throw error.
		Media::cache_post_media( $post_id );
		$this->assertTrue( true );

		wp_delete_post( $post_id, true );
	}

	/**
	 * Test get_storage_paths_for_context returns correct paths for media.
	 */
	public function test_get_storage_paths_for_context_media() {
		$paths = Media::get_storage_paths_for_context( 123, 'media' );

		$this->assertIsArray( $paths );
		$this->assertStringContainsString( '/activitypub/ap_posts/', $paths['basedir'] );
		$this->assertStringContainsString( '/activitypub/ap_posts/', $paths['baseurl'] );
	}

	/**
	 * Test get_storage_paths_for_context returns correct paths for comment_media.
	 */
	public function test_get_storage_paths_for_context_comment() {
		$paths = Media::get_storage_paths_for_context( 123, 'comment_media' );

		$this->assertIsArray( $paths );
		$this->assertStringContainsString( '/activitypub/comments/', $paths['basedir'] );
		$this->assertStringContainsString( '/activitypub/comments/', $paths['baseurl'] );
	}

	/**
	 * Test maybe_cleanup only runs for ap_post post type.
	 */
	public function test_maybe_cleanup_wrong_post_type() {
		// Create a regular post.
		$post_id = self::factory()->post->create();

		// Create a file in the media directory.
		$paths = Media::get_storage_paths( $post_id );
		wp_mkdir_p( $paths['basedir'] );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $paths['basedir'] . '/test.txt', 'test' );

		// Call cleanup.
		Media::maybe_cleanup( $post_id );

		// File should still exist (not cleaned up because wrong post type).
		$this->assertTrue( file_exists( $paths['basedir'] . '/test.txt' ) );

		// Clean up.
		Media::invalidate_entity( $post_id );
	}

	/**
	 * Test invalidate_comment removes comment directory.
	 */
	public function test_invalidate_comment() {
		$paths = Media::get_storage_paths_for_context( 'test-comment', 'comment_media' );

		// Create directory with a file.
		wp_mkdir_p( $paths['basedir'] );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $paths['basedir'] . '/test.txt', 'test content' );

		$this->assertTrue( is_dir( $paths['basedir'] ) );

		// Invalidate.
		$result = Media::invalidate_comment( 'test-comment' );

		$this->assertTrue( $result );
		$this->assertFalse( is_dir( $paths['basedir'] ) );
	}

	/**
	 * Test save_post hook registration on init.
	 */
	public function test_init_registers_save_post_hook() {
		Media::init();

		$this->assertNotFalse(
			has_action( 'save_post_ap_post', array( Media::class, 'cache_post_media' ) )
		);
	}

	/**
	 * Test action registration on init.
	 */
	public function test_init_registers_action() {
		Media::init();

		$this->assertNotFalse(
			has_action( 'before_delete_post', array( Media::class, 'maybe_cleanup' ) )
		);
	}
}
