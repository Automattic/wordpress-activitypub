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
	 * Directory state used by the uploads filter.
	 *
	 * @var string
	 */
	private $directory_kind = 'empty';

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
		\add_filter( 'upload_dir', array( $this, 'filter_upload_directory' ) );
		\wp_upload_dir( null, false, true );
	}

	/**
	 * Restore uploads and the stream registry even after an expected CLI error.
	 */
	public function tear_down() {
		\remove_filter( 'upload_dir', array( $this, 'filter_upload_directory' ) );
		\stream_wrapper_unregister( 'activitypubcachetest' );
		\clearstatcache();
		Cache_Directory_Stream::$vanished = false;
		\wp_upload_dir( null, false, true );
		parent::tear_down();
	}

	/**
	 * Point cache scans at the test directory stream.
	 *
	 * @param array $uploads Upload configuration.
	 * @return array Upload configuration with a simulated cache root.
	 */
	public function filter_upload_directory( $uploads ) {
		$uploads['basedir'] = 'activitypubcachetest://' . $this->directory_kind;
		return $uploads;
	}

	/**
	 * An incomplete scan must halt instead of claiming that the cache is clean.
	 *
	 * @dataProvider directory_failure_cases
	 * @param string $kind   Directory state.
	 * @param bool   $delete Whether to delete duplicates.
	 */
	public function test_cleanup_reports_directory_failure( $kind, $delete ) {
		$this->directory_kind = $kind;

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
	 * Read failures in both command modes.
	 *
	 * @return array Test cases.
	 */
	public function directory_failure_cases() {
		return array(
			'unreadable-dry-run'   => array( 'unreadable', false ),
			'unreadable-delete'    => array( 'unreadable', true ),
			'open-failure-dry-run' => array( 'open-failure', false ),
			'open-failure-delete'  => array( 'open-failure', true ),
		);
	}

	/**
	 * A readable, empty cache still reports success.
	 */
	public function test_cleanup_reports_success_for_an_empty_directory() {
		( new Cache_Command() )->cleanup( array(), array( 'type' => 'avatar' ) );

		$this->assertSame( 'No duplicate files found.', \WP_CLI::$last_success );
	}
}
