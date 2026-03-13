<?php
/**
 * Emoji Cache Test Class
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Cache;

use Activitypub\Cache\Emoji;
use WP_UnitTestCase;

/**
 * Test class for Emoji cache.
 */
class Test_Emoji extends WP_UnitTestCase {

	/**
	 * Test that get_type returns correct value.
	 */
	public function test_get_type() {
		$this->assertEquals( 'emoji', Emoji::get_type() );
	}

	/**
	 * Test that get_base_dir returns correct value.
	 */
	public function test_get_base_dir() {
		$this->assertEquals( '/activitypub/emoji/', Emoji::get_base_dir() );
	}

	/**
	 * Test that get_context returns correct value.
	 */
	public function test_get_context() {
		$this->assertEquals( 'emoji', Emoji::get_context() );
	}

	/**
	 * Test that get_max_dimension returns correct value.
	 */
	public function test_get_max_dimension() {
		$this->assertEquals( 128, Emoji::get_max_dimension() );
	}

	/**
	 * Test that is_enabled returns true by default.
	 */
	public function test_is_enabled_default() {
		$this->assertTrue( Emoji::is_enabled() );
	}

	/**
	 * Test that is_enabled respects filter.
	 */
	public function test_is_enabled_filter() {
		add_filter( 'activitypub_cache_emoji_enabled', '__return_false' );
		$this->assertFalse( Emoji::is_enabled() );
		remove_filter( 'activitypub_cache_emoji_enabled', '__return_false' );

		$this->assertTrue( Emoji::is_enabled() );
	}

	/**
	 * Test maybe_cache returns unchanged URL for wrong context.
	 */
	public function test_maybe_cache_wrong_context() {
		$url    = 'https://example.com/emoji.png';
		$result = Emoji::maybe_cache( $url, 'avatar', 123 );

		$this->assertEquals( $url, $result );
	}

	/**
	 * Test maybe_cache returns unchanged URL for empty URL.
	 */
	public function test_maybe_cache_empty_url() {
		$result = Emoji::maybe_cache( '', 'emoji', null );

		$this->assertEquals( '', $result );
	}

	/**
	 * Test maybe_cache extracts domain from URL when entity_id is null.
	 */
	public function test_maybe_cache_extracts_domain() {
		// This will fail the cache (no real download) but should not error.
		$url    = 'https://example.com/emoji/kappa.png';
		$result = Emoji::maybe_cache( $url, 'emoji', null );

		// Result should be the original URL since download would fail.
		$this->assertEquals( $url, $result );
	}

	/**
	 * Test import returns false for invalid URL.
	 */
	public function test_import_invalid_url() {
		$result = Emoji::import( 'not-a-url', null );
		$this->assertFalse( $result );

		$result = Emoji::import( '', null );
		$this->assertFalse( $result );
	}

	/**
	 * Test import respects pre_import filter.
	 */
	public function test_import_pre_import_filter() {
		$cached_url = 'https://local.test/emoji/kappa.png';

		add_filter(
			'activitypub_pre_import_emoji',
			function () use ( $cached_url ) {
				return $cached_url;
			}
		);

		$result = Emoji::import( 'https://example.com/emoji.png', null );

		$this->assertEquals( $cached_url, $result );

		remove_all_filters( 'activitypub_pre_import_emoji' );
	}

	/**
	 * Test get returns false for non-existent cache.
	 */
	public function test_get_returns_false_for_non_existent() {
		$result = Emoji::get( 'https://example.com/emoji/kappa.png', 'example.com' );
		$this->assertFalse( $result );
	}

	/**
	 * Test storage paths are organized by domain.
	 */
	public function test_storage_paths_by_domain() {
		$paths = Emoji::get_storage_paths( 'mastodon.social' );

		$this->assertStringContainsString( 'mastodon.social', $paths['basedir'] );
		$this->assertStringContainsString( 'mastodon.social', $paths['baseurl'] );
	}

	/**
	 * Test filter registration on init.
	 */
	public function test_init_registers_filter() {
		Emoji::init();

		$this->assertNotFalse(
			has_filter( 'activitypub_remote_media_url', array( Emoji::class, 'maybe_cache' ) )
		);
	}
}
