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
	 * Test maybe_cache filter is registered when caching is enabled.
	 */
	public function test_init_registers_filter_when_enabled() {
		// Remove existing filters.
		remove_all_filters( 'activitypub_remote_media_url' );

		Media::init();

		$this->assertNotFalse(
			has_filter( 'activitypub_remote_media_url', array( Media::class, 'maybe_cache' ) )
		);
	}

	/**
	 * Test maybe_cache filter is NOT registered when caching is disabled.
	 */
	public function test_init_does_not_register_filter_when_disabled() {
		// Remove existing filters.
		remove_all_filters( 'activitypub_remote_media_url' );

		add_filter( 'activitypub_cache_media_enabled', '__return_false' );

		Media::init();

		// maybe_cache should NOT be registered.
		$this->assertFalse(
			has_filter( 'activitypub_remote_media_url', array( Media::class, 'maybe_cache' ) )
		);

		remove_filter( 'activitypub_cache_media_enabled', '__return_false' );
	}

	/**
	 * Test cleanup action is registered when caching is enabled.
	 */
	public function test_init_registers_cleanup_action_when_enabled() {
		// Remove existing actions.
		remove_all_actions( 'before_delete_post' );

		Media::init();

		$this->assertNotFalse(
			has_action( 'before_delete_post', array( Media::class, 'maybe_cleanup' ) )
		);
	}

	/**
	 * Test cleanup action is NOT registered when caching is disabled.
	 */
	public function test_init_does_not_register_cleanup_when_disabled() {
		// Remove existing actions.
		remove_all_actions( 'before_delete_post' );

		add_filter( 'activitypub_cache_media_enabled', '__return_false' );

		Media::init();

		// Cleanup should NOT be registered.
		$this->assertFalse(
			has_action( 'before_delete_post', array( Media::class, 'maybe_cleanup' ) )
		);

		remove_filter( 'activitypub_cache_media_enabled', '__return_false' );
	}

	/**
	 * Test that CDN filter can transform URLs at render time via blocks.
	 */
	public function test_cdn_filter_works_at_render_time() {
		// Simulate a CDN filter.
		$cdn_filter = function ( $url, $context ) {
			if ( 'media' === $context ) {
				return 'https://cdn.example.com/' . md5( $url );
			}
			return $url;
		};
		add_filter( 'activitypub_remote_media_url', $cdn_filter, 10, 2 );

		// Create content with a media block (as it would be stored).
		$remote_url = 'https://remote.example.com/image.jpg';
		$content    = '<!-- wp:activitypub/media {"url":"' . $remote_url . '"} --><img src="' . $remote_url . '" alt="Test" /><!-- /wp:activitypub/media -->';

		// Render the block.
		$rendered = \do_blocks( $content );

		// URL should be transformed by CDN filter at render time.
		$this->assertStringContainsString( 'https://cdn.example.com/', $rendered );
		$this->assertStringNotContainsString( $remote_url, $rendered );

		// Clean up.
		remove_filter( 'activitypub_remote_media_url', $cdn_filter );
	}
}
