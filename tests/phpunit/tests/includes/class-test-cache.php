<?php
/**
 * Cache Orchestrator Test Class
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

use Activitypub\Cache;
use Activitypub\Cache\Avatar;
use Activitypub\Cache\Comment_Media;
use Activitypub\Cache\Emoji;
use Activitypub\Cache\Media;
use WP_UnitTestCase;

/**
 * Test class for Cache orchestrator.
 */
class Test_Cache extends WP_UnitTestCase {

	/**
	 * Test is_enabled returns true by default.
	 */
	public function test_is_enabled_default() {
		$this->assertTrue( Cache::is_enabled() );
	}

	/**
	 * Test is_enabled respects filter.
	 */
	public function test_is_enabled_filter() {
		add_filter( 'activitypub_remote_cache_enabled', '__return_false' );
		$this->assertFalse( Cache::is_enabled() );
		remove_filter( 'activitypub_remote_cache_enabled', '__return_false' );

		$this->assertTrue( Cache::is_enabled() );
	}

	/**
	 * Test is_enabled respects deprecated sideloading filter.
	 */
	public function test_is_enabled_deprecated_sideloading_filter() {
		$this->setExpectedDeprecated( 'activitypub_sideloading_enabled' );

		add_filter( 'activitypub_sideloading_enabled', '__return_false' );
		$this->assertFalse( Cache::is_enabled() );
		remove_filter( 'activitypub_sideloading_enabled', '__return_false' );
	}

	/**
	 * Test init does not run when disabled.
	 */
	public function test_init_disabled() {
		// Remove existing filters first.
		remove_all_filters( 'activitypub_remote_media_url' );

		add_filter( 'activitypub_remote_cache_enabled', '__return_false' );

		Cache::init();

		// Cache handlers should not be registered.
		$this->assertFalse(
			has_filter( 'activitypub_remote_media_url', array( Avatar::class, 'maybe_cache' ) )
		);

		remove_filter( 'activitypub_remote_cache_enabled', '__return_false' );
	}

	/**
	 * Comment images are removed with their comment, also when caching is disabled.
	 */
	public function test_init_disabled_removes_comment_images() {
		// The plugin registered both at bootstrap, with caching enabled.
		\remove_filter( 'activitypub_remote_media_url', array( Comment_Media::class, 'maybe_cache' ) );
		\remove_action( 'transition_comment_status', array( Comment_Media::class, 'maybe_cleanup_comment' ) );
		\add_filter( 'activitypub_remote_cache_enabled', '__return_false' );
		Cache::init();
		\remove_filter( 'activitypub_remote_cache_enabled', '__return_false' );

		$comment_id = self::factory()->comment->create();
		$paths      = Comment_Media::get_storage_paths( $comment_id );
		\wp_mkdir_p( $paths['basedir'] );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $paths['basedir'] . '/test.txt', 'test' );

		\wp_delete_comment( $comment_id, true );

		$this->assertFalse( has_filter( 'activitypub_remote_media_url', array( Comment_Media::class, 'maybe_cache' ) ) );
		$this->assertFileDoesNotExist( $paths['basedir'] . '/test.txt' );
	}

	/**
	 * Test register_caches fires action.
	 */
	public function test_register_caches_action() {
		$action_fired = false;

		add_action(
			'activitypub_register_caches',
			function () use ( &$action_fired ) {
				$action_fired = true;
			}
		);

		Cache::register_caches();

		$this->assertTrue( $action_fired );
	}

	/**
	 * Test all cache handlers are registered on init.
	 */
	public function test_init_registers_all_handlers() {
		Cache::init();

		// Avatar uses the filter for lazy caching.
		$this->assertNotFalse(
			has_filter( 'activitypub_remote_media_url', array( Avatar::class, 'maybe_cache' ) )
		);

		// Media uses the filter for lazy caching (blocks handle URL replacement at render time).
		$this->assertNotFalse(
			has_filter( 'activitypub_remote_media_url', array( Media::class, 'maybe_cache' ) )
		);

		// Emoji uses the filter for lazy caching.
		$this->assertNotFalse(
			has_filter( 'activitypub_remote_media_url', array( Emoji::class, 'maybe_cache' ) )
		);
	}
}
