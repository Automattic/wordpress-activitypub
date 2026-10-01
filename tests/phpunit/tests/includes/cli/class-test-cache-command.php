<?php
/**
 * Cache cleanup CLI tests.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Cli;

use Activitypub\Cli\Cache_Command;
use Activitypub\Tests\Cache_Directory_Stream;

/**
 * Test cleanup failure reporting.
 *
 * @group activitypub
 */
class Test_Cache_Command extends \WP_UnitTestCase {

	/**
	 * Uploads filter restored after expected CLI errors.
	 *
	 * @var callable
	 */
	private $upload_filter;

	/**
	 * Load CLI and directory stubs.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();
		require_once AP_TESTS_DIR . '/includes/class-wp-cli-command.php';
		require_once AP_TESTS_DIR . '/includes/class-wp-cli.php';
		require_once AP_TESTS_DIR . '/includes/functions-wp-cli.php';
		require_once AP_TESTS_DIR . '/includes/class-cache-directory-stream.php';
	}

	/**
	 * Use isolated directory states rather than real uploads.
	 */
	public function set_up() {
		parent::set_up();
		\WP_CLI::$last_success            = null;
		Cache_Directory_Stream::$vanished = false;
		\stream_wrapper_register( 'activitypubcachetest', Cache_Directory_Stream::class );
	}

	/**
	 * Restore uploads and the stream registry even after an expected CLI error.
	 */
	public function tear_down() {
		\remove_filter( 'upload_dir', $this->upload_filter );
		\stream_wrapper_unregister( 'activitypubcachetest' );
		\clearstatcache();
		Cache_Directory_Stream::$vanished = false;
		parent::tear_down();
	}

	/**
	 * Point cache scans at a simulated directory.
	 *
	 * @param string $kind Directory state.
	 */
	private function use_directory( $kind ) {
		$this->upload_filter = static function ( $uploads ) use ( $kind ) {
			$uploads['basedir'] = 'activitypubcachetest://' . $kind;
			return $uploads;
		};
		\add_filter( 'upload_dir', $this->upload_filter );
	}

	/**
	 * An incomplete scan must halt instead of claiming that the cache is clean.
	 *
	 * @dataProvider cleanup_modes
	 * @param bool $delete Whether to delete duplicates.
	 */
	public function test_cleanup_reports_directory_failure( $delete ) {
		$this->use_directory( 'unreadable' );
		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Cleanup incomplete' );

		( new Cache_Command() )->cleanup(
			array(),
			array(
				'type'   => 'avatar',
				'delete' => $delete,
			)
		);
	}

	/**
	 * Both command modes.
	 *
	 * @return array Test cases.
	 */
	public function cleanup_modes() {
		return array(
			'dry-run' => array( false ),
			'delete'  => array( true ),
		);
	}

	/**
	 * A missing cache directory still reports success.
	 *
	 * @dataProvider cleanup_modes
	 * @param bool $delete Whether to delete duplicates.
	 */
	public function test_cleanup_reports_success_without_failures( $delete ) {
		$this->use_directory( 'missing' );
		( new Cache_Command() )->cleanup( array(), array( 'delete' => $delete ) );

		$this->assertSame( $delete ? 'Removed 0 duplicate file(s), 0 B.' : 'No duplicate files found.', \WP_CLI::$last_success );
	}
}
