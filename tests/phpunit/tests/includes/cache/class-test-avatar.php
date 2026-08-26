<?php
/**
 * Avatar Cache Test Class
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Cache;

use Activitypub\Cache\Avatar;
use Activitypub\Collection\Remote_Actors;
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
	 * Test that caching a new avatar URL drops the previous version.
	 *
	 * This is the root cause of #3583: when an actor changes their icon URL,
	 * the old hash file must not linger next to the new one.
	 */
	public function test_new_avatar_url_prunes_previous_version() {
		$post_id     = self::factory()->post->create();
		$old_url     = 'https://example.com/old-avatar.jpg';
		$new_url     = 'https://example.com/new-avatar.jpg';
		$old_hash    = md5( $old_url );
		$new_hash    = md5( $new_url );
		$paths       = Avatar::get_storage_paths( $post_id );
		$mock_prefix = '/test-avatar-';

		$mock_download = function ( $result, $download_url ) use ( $old_url, $new_url, $mock_prefix ) {
			if ( $download_url === $old_url || $download_url === $new_url ) {
				$tmp_file = \wp_tempnam( $mock_prefix . md5( $download_url ) . '.jpg' );
				copy( AP_TESTS_DIR . '/data/assets/test.jpg', $tmp_file );

				return array(
					'file'      => $tmp_file,
					'mime_type' => 'image/jpeg',
				);
			}

			return $result;
		};

		\add_filter( 'activitypub_pre_download_url', $mock_download, 10, 2 );

		// Cache the first avatar.
		Avatar::maybe_cache( $old_url, 'avatar', $post_id );
		$this->assertTrue(
			\file_exists( $paths['basedir'] . '/' . $old_hash . '.webp' ) || (bool) \glob( $paths['basedir'] . '/' . $old_hash . '.*' ),
			'Old avatar should be cached after the first download'
		);

		// Cache a new avatar; the old one should be gone in the same call.
		Avatar::maybe_cache( $new_url, 'avatar', $post_id );
		$this->assertEmpty(
			\glob( $paths['basedir'] . '/' . $old_hash . '.*' ),
			'The previous avatar hash should be pruned when the new one is cached'
		);
		$this->assertNotEmpty(
			\glob( $paths['basedir'] . '/' . $new_hash . '.*' ),
			'The new avatar hash should be cached'
		);

		// Clean up.
		\remove_filter( 'activitypub_pre_download_url', $mock_download );
		Avatar::invalidate_entity( $post_id );
	}

	/**
	 * Test that a filename collision keeps the file that was written.
	 */
	public function test_cache_collision_keeps_written_file() {
		$post_id = self::factory()->post->create();
		$url     = 'https://example.com/collision-avatar.jpg';
		$hash    = md5( $url );
		$paths   = Avatar::get_storage_paths( $post_id );
		wp_mkdir_p( $paths['basedir'] );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $paths['basedir'] . '/' . $hash . '.webp', 'existing' );

		$mock_download = function ( $result, $download_url ) use ( $url ) {
			if ( $download_url === $url ) {
				$tmp_file = \wp_tempnam( 'collision-avatar.jpg' );
				copy( AP_TESTS_DIR . '/data/assets/test.jpg', $tmp_file );

				return array(
					'file'      => $tmp_file,
					'mime_type' => 'image/jpeg',
				);
			}

			return $result;
		};
		\add_filter( 'activitypub_pre_download_url', $mock_download, 10, 2 );

		$result = Avatar::cache( $url, $post_id, array( 'max_dimension' => Avatar::MAX_DIMENSION ) );

		$this->assertStringEndsWith( '/' . $hash . '-1.webp', $result );
		$this->assertTrue( file_exists( $paths['basedir'] . '/' . $hash . '-1.webp' ) );

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

	/**
	 * Test that get_actor_avatar_hash resolves the current icon without caching.
	 */
	public function test_get_actor_avatar_hash() {
		$icon_url = 'https://example.com/avatar.jpg';
		$post_id  = self::factory()->post->create(
			array(
				'post_type'    => Remote_Actors::POST_TYPE,
				'post_content' => wp_json_encode( array( 'icon' => array( 'url' => $icon_url ) ) ),
			)
		);

		$fired   = false;
		$capture = function ( $value ) use ( &$fired ) {
			$fired = true;
			return $value;
		};
		\add_filter( 'activitypub_remote_media_url', $capture );

		$this->assertEquals( md5( $icon_url ), Avatar::get_actor_avatar_hash( $post_id ) );
		$this->assertFalse( $fired, 'The cache filter should not fire when resolving the hash' );

		\remove_filter( 'activitypub_remote_media_url', $capture );
	}

	/**
	 * Test that get_actor_avatar_hash returns an empty hash when the actor has no icon.
	 */
	public function test_get_actor_avatar_hash_no_icon() {
		$post_id = self::factory()->post->create(
			array(
				'post_type'    => Remote_Actors::POST_TYPE,
				'post_content' => wp_json_encode( array( 'name' => 'Test' ) ),
			)
		);

		$this->assertSame( '', Avatar::get_actor_avatar_hash( $post_id ) );
	}

	/**
	 * Test that get_actor_avatar_hash returns false for a missing post.
	 */
	public function test_get_actor_avatar_hash_missing_post() {
		$this->assertFalse( Avatar::get_actor_avatar_hash( 999999 ) );
	}

	/**
	 * Test that prune_stale_files keeps the current hash and deletes others.
	 */
	public function test_prune_stale_files_keeps_current() {
		$post_id     = self::factory()->post->create();
		$current     = md5( 'https://example.com/current.jpg' );
		$stale       = md5( 'https://example.com/stale.jpg' );
		$paths       = Avatar::get_storage_paths( $post_id );
		$current_ext = 'webp';
		$stale_ext   = 'jpg';
		wp_mkdir_p( $paths['basedir'] );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $paths['basedir'] . "/{$current}.{$current_ext}", 'current' );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $paths['basedir'] . "/{$stale}.{$stale_ext}", 'stale' );

		Avatar::prune_stale_files( $post_id, $current );

		$this->assertTrue( file_exists( $paths['basedir'] . "/{$current}.{$current_ext}" ) );
		$this->assertFalse( file_exists( $paths['basedir'] . "/{$stale}.{$stale_ext}" ) );

		Avatar::invalidate_entity( $post_id );
	}

	/**
	 * Test that prune_stale_files deletes everything when nothing matches.
	 */
	public function test_prune_stale_files_no_match() {
		$post_id = self::factory()->post->create();
		$hash    = md5( 'https://example.com/some.jpg' );
		$paths   = Avatar::get_storage_paths( $post_id );
		wp_mkdir_p( $paths['basedir'] );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $paths['basedir'] . "/{$hash}.jpg", 'x' );

		Avatar::prune_stale_files( $post_id, md5( 'https://example.com/other.jpg' ) );

		$this->assertFalse( file_exists( $paths['basedir'] . "/{$hash}.jpg" ) );
		$this->assertFalse( file_exists( $paths['basedir'] ), 'Empty actor directory should be removed' );

		Avatar::invalidate_entity( $post_id );
	}

	/**
	 * Test that cleanup_actors removes orphaned actor directories.
	 */
	public function test_cleanup_actors_removes_orphan_dir() {
		$post_id = self::factory()->post->create();
		$paths   = Avatar::get_storage_paths( $post_id );
		wp_mkdir_p( $paths['basedir'] );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $paths['basedir'] . '/abc.jpg', 'x' );

		Avatar::cleanup_actors();

		$this->assertFalse( file_exists( $paths['basedir'] ) );
		\delete_option( 'activitypub_avatar_cache_cleanup_lock' );
		\delete_option( 'activitypub_avatar_cache_cursor' );
	}

	/**
	 * Test that cleanup_actors leaves non-numeric directories alone.
	 */
	public function test_cleanup_actors_leaves_non_numeric_dir() {
		$root  = wp_upload_dir()['basedir'] . Avatar::get_base_dir();
		$junk  = $root . '/not-a-number';
		wp_mkdir_p( $junk );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $junk . '/a.jpg', 'x' );

		Avatar::cleanup_actors();

		$this->assertTrue( file_exists( $junk . '/a.jpg' ) );

		wp_delete_file( $junk . '/a.jpg' );
		rmdir( $junk ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
		\delete_option( 'activitypub_avatar_cache_cleanup_lock' );
		\delete_option( 'activitypub_avatar_cache_cursor' );
	}

	/**
	 * Test that cleanup_actors prunes stale files for a surviving actor.
	 */
	public function test_cleanup_actors_prunes_surviving_actor() {
		$icon_url = 'https://example.com/current.png';
		$post_id  = self::factory()->post->create(
			array(
				'post_type'    => Remote_Actors::POST_TYPE,
				'post_content' => wp_json_encode( array( 'icon' => array( 'url' => $icon_url ) ) ),
			)
		);

		$current = md5( $icon_url );
		$stale   = md5( 'https://example.com/stale.png' );
		$paths   = Avatar::get_storage_paths( $post_id );
		wp_mkdir_p( $paths['basedir'] );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $paths['basedir'] . "/{$current}.png", 'current' );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $paths['basedir'] . "/{$stale}.png", 'stale' );

		Avatar::cleanup_actors();

		$this->assertTrue( file_exists( $paths['basedir'] . "/{$current}.png" ) );
		$this->assertFalse( file_exists( $paths['basedir'] . "/{$stale}.png" ) );

		Avatar::invalidate_entity( $post_id );
		\delete_option( 'activitypub_avatar_cache_cleanup_lock' );
		\delete_option( 'activitypub_avatar_cache_cursor' );
	}

	/**
	 * Test that a second cleanup run while locked returns early.
	 */
	public function test_cleanup_actors_reentrant_lock() {
		$post_id = self::factory()->post->create();
		$paths   = Avatar::get_storage_paths( $post_id );
		wp_mkdir_p( $paths['basedir'] );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $paths['basedir'] . '/abc.jpg', 'x' );

		\add_option( 'activitypub_avatar_cache_cleanup_lock', time(), '', false );

		Avatar::cleanup_actors();

		$this->assertTrue( file_exists( $paths['basedir'] . '/abc.jpg' ), 'Orphan dir should not be removed while locked' );

		Avatar::invalidate_entity( $post_id );
		\delete_option( 'activitypub_avatar_cache_cleanup_lock' );
		\delete_option( 'activitypub_avatar_cache_cursor' );
	}

	/**
	 * Test that a cleanup run advances past the batch so the backlog drains.
	 *
	 * With more actor directories than the per-run limit, the second run
	 * should pick up where the first left off rather than revisiting the
	 * same directories.
	 */
	public function test_cleanup_actors_advances_batch_cursor() {
		// Limit the batch to one directory per run.
		$limit_one = static function () {
			return 1;
		};
		\add_filter( 'activitypub_cleanup_actor_cache_limit', $limit_one );

		$root = wp_upload_dir()['basedir'] . Avatar::get_base_dir();
		$dirs = array();

		// Create unique numeric directories, then use DirectoryIterator order directly.
		// get_base_dir() ends with a slash, so concatenate names without another one.
		foreach ( range( 1, 3 ) as $index ) {
			$directory = $root . ( 900000 + getmypid() + $index );
			\wp_mkdir_p( $directory );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $directory . '/avatar.jpg', 'x' );
			$dirs[] = $directory;
		}

		$iterator = new \DirectoryIterator( $root );
		$ordered = array();
		foreach ( $iterator as $directory ) {
			if ( ! $directory->isDot() && $directory->isDir() && in_array( $directory->getPathname(), $dirs, true ) ) {
				$ordered[] = $directory->getPathname();
			}
		}
		$this->assertCount( 3, $ordered );
		\update_option( 'activitypub_avatar_cache_cursor', basename( $ordered[0] ), false );

		// First run processes directory after cursor, regardless of filesystem order.
		Avatar::cleanup_actors();
		$this->assertFalse( file_exists( $ordered[1] . '/avatar.jpg' ), 'First run should clean the first batch' );
		$this->assertTrue( file_exists( $ordered[2] . '/avatar.jpg' ), 'First run should stop after the batch limit' );

		// Second run should advance and clean the next directory.
		Avatar::cleanup_actors();
		$this->assertFalse( file_exists( $ordered[2] . '/avatar.jpg' ), 'Second run should clean the next batch' );

		\remove_filter( 'activitypub_cleanup_actor_cache_limit', $limit_one );
		\delete_option( 'activitypub_avatar_cache_cleanup_lock' );
		\delete_option( 'activitypub_avatar_cache_cursor' );
	}
}
