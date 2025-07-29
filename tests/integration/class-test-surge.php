<?php
/**
 * Test Surge integration.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Integration;

use Activitypub\Integration\Surge;

/**
 * Test Surge integration.
 *
 * @group integration
 * @coversDefaultClass \Activitypub\Integration\Surge
 */
class Test_Surge extends \WP_UnitTestCase {
	/**
	 * Test file path.
	 *
	 * @var string
	 */
	private $test_file;

	/**
	 * Config contents.
	 *
	 * @var string
	 */
	private $config_contents;

	/**
	 * Original cache config.
	 *
	 * @var string
	 * Set up the test.
	 */
	public function set_up() {
		parent::set_up();

		// Simulate a config file path.
		$this->test_file       = \sys_get_temp_dir() . '/wp-config-test.php';
		$this->config_contents = "<?php\n/* That's all, stop editing! */";

		// phpcs:ignore
		\file_put_contents( $this->test_file, $this->config_contents );

		require_once $this->test_file;

		// Patch Surge::get_config_file() to return our test file.
		\add_filter( 'activitypub_surge_cache_config_file', array( $this, 'get_test_file' ) );
	}

	/**
	 * Get test file.
	 *
	 * @access public
	 */
	public function get_test_file() {
		return $this->test_file;
	}

	/**
	 * Tear down the test.
	 *
	 * @access public
	 */
	public function tear_down() {
		parent::tear_down();

		if ( \file_exists( $this->test_file ) ) {
			\wp_delete_file( $this->test_file );
		}

		\remove_all_filters( 'activitypub_surge_cache_config_file' );
	}

	/**
	 * Test adding cache config.
	 *
	 * @access public
	 */
	public function test_add_cache_config() {
		\add_filter( 'pre_option_active_plugins', array( $this, 'get_active_plugins' ) );

		Surge::add_cache_config();
		// phpcs:ignore
		$file = \file_get_contents( Surge::get_config_file_path() );

		$this->assertStringContainsString( '<?php', $file, 'File should start with PHP opening tag' );
		$this->assertStringContainsString( "/* That's all, stop editing! */", $file, 'Comment should be present' );
		$this->assertStringContainsString( Surge::get_cache_config(), $file, 'Config line should be present' );

		\remove_all_filters( 'pre_option_active_plugins' );
	}

	/**
	 * Test removing cache config.
	 *
	 * @access public
	 */
	public function test_remove_cache_config() {
		$this->config_contents = "<?php\n" . Surge::get_config_file_path() . "\n\n/* That's all, stop editing! */";
		$actual                = $this->config_contents;
		$this->assertStringContainsString( Surge::get_config_file_path(), $actual, 'Config line should be present' );

		Surge::remove_cache_config();

		// phpcs:ignore
		$actual = \file_get_contents( Surge::get_config_file_path() );
		$this->assertStringContainsString( '<?php', $actual, 'File should start with PHP opening tag' );
		$this->assertStringNotContainsString( Surge::get_cache_config(), $actual, 'Config line should be removed' );
		$this->assertStringContainsString( "/* That's all, stop editing! */", $actual, 'Comment should be present' );
	}

	/**
	 * Test init method with Surge plugin active.
	 *
	 * @access public
	 */
	public function test_init_with_surge_active() {
		\add_filter( 'pre_option_active_plugins', array( $this, 'get_active_plugins' ) );

		// phpcs:ignore
		$before = \file_get_contents( Surge::get_config_file_path() );
		$this->assertStringNotContainsString( Surge::get_cache_config(), $before );

		Surge::init();

		// phpcs:ignore
		\do_action( 'activate_surge/surge.php' );

		// phpcs:ignore
		$after = \file_get_contents( Surge::get_config_file_path() );
		$this->assertStringContainsString( Surge::get_cache_config(), $after );

		\remove_all_filters( 'pre_option_active_plugins' );
	}

	/**
	 * Get active plugins.
	 *
	 * @access public
	 */
	public function get_active_plugins() {
		return array( 'surge/surge.php' );
	}

	/**
	 * Test init method with Surge plugin inactive.
	 *
	 * @access public
	 */
	public function test_init_with_surge_inactive() {
		// Needs to be set, because of the dummy `wp-config.php` file.
		\define( 'WP_CACHE_CONFIG', 'dummy' );
		// phpcs:ignore
		\file_put_contents( Surge::get_config_file_path(), "<?php\n" . Surge::get_cache_config() . "\n\n/* That's all, stop editing! */" );
		\add_filter( 'pre_option_active_plugins', array( $this, 'get_inactive_plugins' ) );

		Surge::init();

		// phpcs:ignore
		\do_action( 'deactivate_surge/surge.php' );

		// phpcs:ignore
		$after = \file_get_contents( Surge::get_config_file_path() );
		$this->assertStringNotContainsString( Surge::get_cache_config(), $after );

		\remove_all_filters( 'pre_option_active_plugins' );
	}

	/**
	 * Get inactive plugins.
	 *
	 * @access public
	 */
	public function get_inactive_plugins() {
		return array();
	}

	/**
	 * Test that duplicate configs are not added.
	 *
	 * @access public
	 */
	public function test_no_duplicate_configs() {
		// Start with config containing the cache config.
		$this->config_contents = "<?php\n" . Surge::get_config_file_path() . "\n\n/* That's all, stop editing! */";

		Surge::add_cache_config();

		$actual = $this->config_contents;

		$this->assertEquals( 1, substr_count( $actual, Surge::get_config_file_path() ), 'Config line should appear exactly once' );
	}

	/**
	 * Test maybe_add_site_health adds test when Surge is active
	 *
	 * @access public
	 */
	public function test_maybe_add_site_health() {
		\add_filter( 'pre_option_active_plugins', array( $this, 'get_active_plugins' ) );

		$tests  = array( 'direct' => array() );
		$result = Surge::maybe_add_site_health( $tests );

		$this->assertArrayHasKey( 'activitypub_test_surge_integration', $result['direct'] );

		\remove_all_filters( 'pre_option_active_plugins' );
	}

	/**
	 * Test test_surge_integration returns good when WP_CACHE_CONFIG is defined
	 *
	 * @access public
	 */
	public function test_test_surge_integration_good() {
		if ( ! \defined( 'WP_CACHE_CONFIG' ) ) {
			// phpcs:ignore
			\define( 'WP_CACHE_CONFIG', 'dummy' );
		}

		$result = Surge::test_surge_integration();

		$this->assertEquals( 'good', $result['status'] );
		$this->assertStringContainsString( 'Surge is well configured', $result['description'] );
	}
}
