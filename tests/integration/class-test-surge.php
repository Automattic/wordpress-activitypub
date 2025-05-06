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
 */
class Test_Surge extends \WP_UnitTestCase {
	/**
	 * Test file path.
	 *
	 * @var string
	 */
	private $test_file;

	/**
	 * Original config file path.
	 *
	 * @var string
	 */
	private $original_config_file;

	/**
	 * Set up the test.
	 */
	public function set_up() {
		parent::set_up();

		// Create a temporary wp-config.php file for testing.
		$this->test_file = sys_get_temp_dir() . '/wp-config-test.php';
		file_put_contents( $this->test_file, "<?php\n/* That's all, stop editing! */" );

		// Store original config file path.
		$reflection = new \ReflectionClass( 'Activitypub\Integration\Surge' );
		$property = $reflection->getProperty( 'config_file' );
		$property->setAccessible( true );
		$this->original_config_file = $property->getValue();

		// Set test config file path.
		$property->setValue( null, $this->test_file );
	}

	/**
	 * Tear down the test.
	 */
	public function tear_down() {
		parent::tear_down();

		// Clean up the test file.
		if ( file_exists( $this->test_file ) ) {
			unlink( $this->test_file );
		}
	}

	/**
	 * Test adding cache config.
	 */
	public function test_add_cache_config() {
		// Test adding config.
		Surge::add_cache_config();

		// Verify the config was added correctly.
		$actual = file_get_contents( $this->test_file );
		$this->assertStringContainsString( "<?php", $actual, "File should start with PHP opening tag" );
		$this->assertStringContainsString( "define( 'WP_CACHE_CONFIG', '/var/www/html/wp-content/plugins/activitypub/integration/surge-cache-config.php' );", $actual, "Config line should be present" );
		$this->assertStringContainsString( "/* That's all, stop editing! */", $actual, "Comment should be present" );
	}

	/**
	 * Test removing cache config.
	 */
	public function test_remove_cache_config() {
		// First add the config.
		file_put_contents( $this->test_file, "<?php\ndefine( 'WP_CACHE_CONFIG', '/var/www/html/wp-content/plugins/activitypub/integration/surge-cache-config.php' );\n\n/* That's all, stop editing! */" );

		$actual = file_get_contents( $this->test_file );
		$this->assertStringContainsString( "define( 'WP_CACHE_CONFIG', '/var/www/html/wp-content/plugins/activitypub/integration/surge-cache-config.php' );", $actual, "Config line should be present" );

		// Test removing config.
		Surge::remove_cache_config();

		// Verify the config was removed.
		$actual = file_get_contents( $this->test_file );
		$this->assertStringContainsString( "<?php", $actual, "File should start with PHP opening tag" );
		$this->assertStringNotContainsString( "define( 'WP_CACHE_CONFIG'", $actual, "Config line should be removed" );
		$this->assertStringContainsString( "/* That's all, stop editing! */", $actual, "Comment should be present" );
	}

	/**
	 * Test init method with Surge plugin active.
	 */
	public function test_init_with_surge_active() {
		$function = function() {
			return array( 'surge/surge.php' );
		};

		// Mock is_plugin_active to return true.
		add_filter( 'pre_option_active_plugins', $function );

		// Test init.
		Surge::init();

		// Verify the config was added.
		$actual = file_get_contents( $this->test_file );
		$this->assertStringContainsString( "<?php", $actual, "File should start with PHP opening tag" );
		$this->assertStringContainsString( "define( 'WP_CACHE_CONFIG', '/var/www/html/wp-content/plugins/activitypub/integration/surge-cache-config.php' );", $actual, "Config line should be present" );
		$this->assertStringContainsString( "/* That's all, stop editing! */", $actual, "Comment should be present" );

		remove_filter( 'pre_option_active_plugins', $function );
	}

	/**
	 * Test init method with Surge plugin inactive.
	 */
	public function test_init_with_surge_inactive() {
		// First add the config.
		file_put_contents( $this->test_file, "<?php\ndefine( 'WP_CACHE_CONFIG', '/var/www/html/wp-content/plugins/activitypub/integration/surge-cache-config.php' );\n\n/* That's all, stop editing! */" );

		\define( 'WP_CACHE_CONFIG', '/var/www/html/wp-content/plugins/activitypub/integration/surge-cache-config.php' );

		// Mock is_plugin_active to return false.
		add_filter( 'pre_option_active_plugins', '__return_empty_array' );

		// Test init.
		Surge::init();

		// Verify the config was removed.
		$actual = file_get_contents( $this->test_file );
		$this->assertStringContainsString( "<?php", $actual, "File should start with PHP opening tag" );
		$this->assertStringNotContainsString( "define( 'WP_CACHE_CONFIG'", $actual, "Config line should be removed" );
		$this->assertStringContainsString( "/* That's all, stop editing! */", $actual, "Comment should be present" );

		remove_filter( 'pre_option_active_plugins', '__return_empty_array' );
	}

	/**
	 * Test that duplicate configs are not added.
	 */
	public function test_no_duplicate_configs() {
		// First add the config.
		file_put_contents( $this->test_file, "<?php\ndefine( 'WP_CACHE_CONFIG', '/var/www/html/wp-content/plugins/activitypub/integration/surge-cache-config.php' );\n\n/* That's all, stop editing! */" );

		// Try to add config when it already exists.
		Surge::add_cache_config();

		// Verify no duplicate was added.
		$actual = file_get_contents( $this->test_file );
		$this->assertStringContainsString( "<?php", $actual, "File should start with PHP opening tag" );
		$this->assertEquals( 1, substr_count( $actual, "define( 'WP_CACHE_CONFIG'" ), "Config line should appear exactly once" );
		$this->assertStringContainsString( "/* That's all, stop editing! */", $actual, "Comment should be present" );
	}
}
