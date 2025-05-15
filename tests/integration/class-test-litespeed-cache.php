<?php
/**
 * Test Litespeed Cache integration.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Integration;

use Activitypub\Integration\Litespeed_Cache;

/**
 * Test Litespeed Cache integration.
 */
class Test_Litespeed_Cache extends \WP_UnitTestCase {
	/**
	 * Path to the temporary htaccess file.
	 *
	 * @var string
	 */
	private $htaccess_file;

	/**
	 * Original htaccess contents.
	 *
	 * @var string
	 */
	private $original_htaccess;

	/**
	 * Set up the test environment.
	 */
	public function set_up() {
		parent::set_up();
		// phpcs:ignore
		$this->htaccess_file     = \sys_get_temp_dir() . '/.htaccess-test';
		$this->original_htaccess = "# BEGIN WordPress\n# END WordPress";
		// phpcs:ignore
		\file_put_contents( $this->htaccess_file, $this->original_htaccess );
		// Patch get_home_path to use our temp dir.
		\add_filter( 'activitypub_litespeed_cache_home_path', array( $this, 'get_home_path' ) );
	}

	/**
	 * Tear down the test environment.
	 */
	public function tear_down() {
		parent::tear_down();
		if ( \file_exists( $this->htaccess_file ) ) {
			\wp_delete_file( $this->htaccess_file );
		}
		\remove_all_filters( 'activitypub_litespeed_cache_home_path' );
	}

	/**
	 * Get the home path for the test environment.
	 *
	 * @return string The home path.
	 */
	public function get_home_path() {
		return \dirname( $this->htaccess_file ) . '/';
	}

	/**
	 * Test adding htaccess rules.
	 */
	public function test_add_htaccess_rules() {
		// Ensure filter is set for correct htaccess file path
		\add_filter(
			'activitypub_litespeed_cache_htaccess_file',
			function( $file ) { return $this->htaccess_file; }
		);
		Litespeed_Cache::add_htaccess_rules();
		// phpcs:ignore
		$contents = \file_get_contents( $this->htaccess_file );
		$this->assertStringContainsString( Litespeed_Cache::$rules, $contents, 'Litespeed rules should be present in htaccess' );
	}

	/**
	 * Test removing htaccess rules.
	 */
	public function test_remove_htaccess_rules() {
		// First add, then remove.
		Litespeed_Cache::add_htaccess_rules();
		Litespeed_Cache::remove_htaccess_rules();
		// phpcs:ignore
		$contents = \file_get_contents( $this->htaccess_file );
		$this->assertStringNotContainsString( Litespeed_Cache::$rules, $contents, 'Litespeed rules should be removed from htaccess' );
	}

	/**
	 * Test no duplicate rules.
	 */
	public function test_no_duplicate_rules() {
		// Ensure filter is set for correct htaccess file path
		\add_filter(
			'activitypub_litespeed_cache_htaccess_file',
			function( $file ) { return $this->htaccess_file; }
		);
		Litespeed_Cache::add_htaccess_rules();
		Litespeed_Cache::add_htaccess_rules();
		// phpcs:ignore
		$contents = \file_get_contents( $this->htaccess_file );
		// Count number of rule blocks.
		$rule_count = substr_count( $contents, Litespeed_Cache::$rules );
		$this->assertEquals( 1, $rule_count, 'Litespeed rules should appear only once' );
	}

	/**
	 * Test that the option is updated when rules are added.
	 *
	 * @return void
	 */
	public function test_option_updated_on_add() {
		Litespeed_Cache::add_htaccess_rules();
		$option = \get_option( Litespeed_Cache::$option_name );
		$this->assertTrue( $option, 'Option should be updated to true after adding rules' );
	}
}
