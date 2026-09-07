<?php
/**
 * Test for retrying a cache move whose directory disappeared.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Cache;

use Activitypub\Tests\Stub_File_Cache;
use Activitypub\Tests\Vanishing_Filesystem;
use WP_UnitTestCase;

require_once __DIR__ . '/../../../includes/class-stub-file-cache.php';

/**
 * Test class for the cache move retry.
 *
 * @coversDefaultClass \Activitypub\Cache\File
 */
class Test_File_Move_Retry extends WP_UnitTestCase {
	/**
	 * Set up the test.
	 */
	public function set_up() {
		parent::set_up();

		Vanishing_Filesystem::$moves = 0;
	}

	/**
	 * Tear down the test.
	 */
	public function tear_down() {
		Stub_File_Cache::invalidate_entity( 'entity-1' );

		parent::tear_down();
	}

	/**
	 * A download is still cached when the entity directory disappears mid-move.
	 *
	 * Invalidating an entity deletes its whole directory, which can land between creating that
	 * directory and moving the download into it. Losing the download to that race means the file
	 * is fetched again on the next request for no reason.
	 *
	 * @covers ::cache
	 */
	public function test_cache_retries_move_after_directory_disappears() {
		$local_url = Stub_File_Cache::cache( 'https://remote.example/avatar.png', 'entity-1' );

		$this->assertSame( 2, Vanishing_Filesystem::$moves, 'The move should be retried once.' );
		$this->assertIsString( $local_url, 'The file should still be cached after the retry.' );
		$this->assertStringContainsString( 'activitypub/test/entity-1', $local_url );
	}
}
