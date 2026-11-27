<?php
/**
 * Avatar Cache Test Class
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Cache;

use Activitypub\Cache\Avatar;
use Activitypub\Collection\Remote_Actors;
use Activitypub\Tests\Image_Download_Stub;
use WP_UnitTestCase;

use function Activitypub\get_id_hash;

/**
 * Test class for Avatar cache.
 */
class Test_Avatar extends WP_UnitTestCase {
	use Image_Download_Stub;

	/**
	 * Store a remote actor.
	 *
	 * @param string $actor_id The id of the actor.
	 *
	 * @return int The ID of the post the actor is stored in.
	 */
	private function create_actor( $actor_id ) {
		return Remote_Actors::create(
			array(
				'id'                => $actor_id,
				'type'              => 'Person',
				'inbox'             => $actor_id . '/inbox',
				'preferredUsername' => 'avatar',
				'icon'              => array(
					'type' => 'Image',
					'url'  => 'https://example.com/avatar.jpg',
				),
			)
		);
	}

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
		$actor_id = 'https://example.com/users/cached';
		$post_id  = $this->create_actor( $actor_id );
		$url      = 'https://example.com/avatar.jpg';

		// Mock the file download to return a local fixture.
		$mock_download = function ( $result, $download_url ) use ( $url ) {
			if ( $download_url === $url ) {
				return self::image_download();
			}

			return $result;
		};

		\add_filter( 'activitypub_pre_download_url', $mock_download, 10, 2 );

		// First call should download and cache.
		$result = Avatar::maybe_cache( $url, 'avatar', $actor_id );
		$this->assertNotEquals( $url, $result, 'Should return a local cached URL, not the original' );
		$this->assertStringContainsString( '/activitypub/actors/' . get_id_hash( $actor_id ) . '/', $result, 'The folder is named after the hash of the actor id, not after the post.' );

		// No meta should be stored (the old bug).
		$meta = \get_post_meta( $post_id, '_activitypub_avatar_url', true );
		$this->assertEmpty( $meta, 'Should not store avatar URL in post meta' );

		// Second call should return the same cached URL from filesystem.
		$result2 = Avatar::maybe_cache( $url, 'avatar', $actor_id );
		$this->assertEquals( $result, $result2, 'Subsequent calls should return the same cached URL' );

		// The stored actor asks for the same file.
		$this->assertSame( $result, Remote_Actors::get_avatar_url( $post_id ) );

		// Saving the actor again clears the file, and so does deleting it.
		$file = Avatar::get_storage_paths( $actor_id )['basedir'] . '/' . \basename( $result );
		$this->assertFileExists( $file );
		Avatar::clear_cached_avatar( $post_id );
		$this->assertFileDoesNotExist( $file );

		Avatar::maybe_cache( $url, 'avatar', $actor_id );
		$this->assertFileExists( $file );
		Avatar::maybe_cleanup( $post_id );
		$this->assertFileDoesNotExist( $file );

		// Clean up.
		\remove_filter( 'activitypub_pre_download_url', $mock_download );
	}

	/**
	 * Test maybe_cache returns original URL when download fails.
	 */
	public function test_maybe_cache_returns_original_url_on_failure() {
		$actor_id = 'https://example.com/users/broken';
		$post_id  = $this->create_actor( $actor_id );
		$url      = 'https://example.com/broken-avatar.jpg';

		// Mock the download to fail by returning false.
		$mock_download = function ( $result, $download_url ) use ( $url ) {
			if ( $download_url === $url ) {
				return false;
			}

			return $result;
		};

		\add_filter( 'activitypub_pre_download_url', $mock_download, 10, 2 );

		// Should return the original URL as fallback.
		$result = Avatar::maybe_cache( $url, 'avatar', $actor_id );
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

		// The actor is given by its id, a post ID is not one.
		$result = Avatar::save( 123, 'https://example.com/avatar.jpg' );
		$this->assertFalse( $result );
	}

	/**
	 * A value that is not a valid actor id never addresses the folder all avatars are in.
	 *
	 * @covers \Activitypub\Cache\Avatar::get_storage_paths
	 * @covers \Activitypub\Cache\Avatar::save
	 */
	public function test_an_invalid_id_does_not_reach_the_other_avatars() {
		$base = \untrailingslashit( \wp_upload_dir()['basedir'] . Avatar::get_base_dir() );

		foreach ( array( '', 'javascript://x', 'file://x', 'not an id' ) as $id ) {
			$this->assertNotSame( $base, \untrailingslashit( Avatar::get_storage_paths( $id )['basedir'] ), $id );
		}

		// The avatar of another actor.
		$paths = Avatar::get_storage_paths( 'https://example.com/users/other' );
		\wp_mkdir_p( $paths['basedir'] );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		\file_put_contents( $paths['basedir'] . '/test.txt', 'test' );

		// This id passes as a URL, but WordPress does not allow its scheme.
		$failing = static function () {
			return false;
		};
		\add_filter( 'activitypub_pre_download_url', $failing );
		Avatar::save( 'javascript://x', 'https://example.com/avatar.jpg' );
		\remove_filter( 'activitypub_pre_download_url', $failing );

		$this->assertFileExists( $paths['basedir'] . '/test.txt' );

		Avatar::invalidate_entity( 'https://example.com/users/other' );
	}

	/**
	 * Test save returns false for invalid URL.
	 */
	public function test_save_invalid_url() {
		$result = Avatar::save( 'https://example.com/users/alice', 'not-a-url' );
		$this->assertFalse( $result );

		$result = Avatar::save( 'https://example.com/users/alice', '' );
		$this->assertFalse( $result );
	}

	/**
	 * Test that maybe_cleanup only runs for ap_actor post type.
	 */
	public function test_maybe_cleanup_wrong_post_type() {
		// Create a regular post.
		$post_id = self::factory()->post->create();

		// Create a file in the avatar directory this post would have as an actor.
		$paths = Avatar::get_storage_paths( \get_post( $post_id )->guid );
		wp_mkdir_p( $paths['basedir'] );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $paths['basedir'] . '/test.txt', 'test' );

		// Call cleanup.
		Avatar::maybe_cleanup( $post_id );

		// File should still exist (not cleaned up because wrong post type).
		$this->assertTrue( file_exists( $paths['basedir'] . '/test.txt' ) );

		// Clean up.
		Avatar::invalidate_entity( \get_post( $post_id )->guid );
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
