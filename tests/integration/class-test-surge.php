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
	 * Original cache config.
	 *
	 * @var string
	 */
	private $original_cache_config;

	/**
	 * Set up the test.
	 */
	public function set_up() {
		parent::set_up();

		// Create a temporary wp-config.php file for testing.
		$this->test_file = \sys_get_temp_dir() . '/wp-config-test.php';
		// phpcs:ignore
		\file_put_contents( $this->test_file, "<?php\n/* That's all, stop editing! */" );

		// Store original config file path.
		$reflection = new \ReflectionClass( 'Activitypub\Integration\Surge' );
		$property   = $reflection->getProperty( 'config_file' );
		$property->setAccessible( true );
		$this->original_config_file = $property->getValue();

		$property2 = $reflection->getProperty( 'cache_config' );
		$property2->setAccessible( true );
		$this->original_cache_config = $property2->getValue();

		// Set test config file path.
		$property->setValue( null, $this->test_file );
	}

	/**
	 * Tear down the test.
	 */
	public function tear_down() {
		parent::tear_down();

		if ( \file_exists( $this->test_file ) ) {
			\wp_delete_file( $this->test_file );
		}
	}

	/**
	 * Test adding cache config.
	 */
	public function test_add_cache_config() {
		Surge::add_cache_config();

		$actual = \file_get_contents( $this->test_file ); // phpcs:ignore
		$this->assertStringContainsString( '<?php', $actual, 'File should start with PHP opening tag' );
		$this->assertStringContainsString( $this->original_cache_config, $actual, 'Config line should be present' );
		$this->assertStringContainsString( "/* That's all, stop editing! */", $actual, 'Comment should be present' );
	}

	/**
	 * Test removing cache config.
	 */
	public function test_remove_cache_config() {
		// phpcs:ignore
		\file_put_contents( $this->test_file, "<?php\n" . $this->original_cache_config . "\n\n/* That's all, stop editing! */" );

		// phpcs:ignore
		$actual = \file_get_contents( $this->test_file );
		$this->assertStringContainsString( $this->original_cache_config, $actual, 'Config line should be present' );

		Surge::remove_cache_config();

		// phpcs:ignore
		$actual = \file_get_contents( $this->test_file );
		$this->assertStringContainsString( '<?php', $actual, 'File should start with PHP opening tag' );
		$this->assertStringNotContainsString( "define( 'WP_CACHE_CONFIG'", $actual, 'Config line should be removed' );
		$this->assertStringContainsString( "/* That's all, stop editing! */", $actual, 'Comment should be present' );
	}

	/**
	 * Test init method with Surge plugin active.
	 */
	public function test_init_with_surge_active() {
		$function = function () {
			return array( 'surge/surge.php' );
		};

		add_filter( 'pre_option_active_plugins', $function );

		Surge::init();

		// phpcs:ignore
		$actual = \file_get_contents( $this->test_file );
		$this->assertStringContainsString( '<?php', $actual, 'File should start with PHP opening tag' );
		$this->assertStringContainsString( $this->original_cache_config, $actual, 'Config line should be present' );
		$this->assertStringContainsString( "/* That's all, stop editing! */", $actual, 'Comment should be present' );

		remove_filter( 'pre_option_active_plugins', $function );
	}

	/**
	 * Test init method with Surge plugin inactive.
	 */
	public function test_init_with_surge_inactive() {
		// phpcs:ignore
		\file_put_contents( $this->test_file, "<?php\n" . $this->original_cache_config . "\n\n/* That's all, stop editing! */" );

		\define( 'WP_CACHE_CONFIG', $this->original_cache_config );

		add_filter( 'pre_option_active_plugins', '__return_empty_array' );

		Surge::init();

		// phpcs:ignore
		$actual = \file_get_contents( $this->test_file );
		$this->assertStringContainsString( '<?php', $actual, 'File should start with PHP opening tag' );
		$this->assertStringNotContainsString( $this->original_cache_config, $actual, 'Config line should be removed' );
		$this->assertStringContainsString( "/* That's all, stop editing! */", $actual, 'Comment should be present' );

		remove_filter( 'pre_option_active_plugins', '__return_empty_array' );
	}

	/**
	 * Test that duplicate configs are not added.
	 */
	public function test_no_duplicate_configs() {
		// phpcs:ignore
		\file_put_contents( $this->test_file, "<?php\n" . $this->original_cache_config . "\n\n/* That's all, stop editing! */" );

		Surge::add_cache_config();

		// phpcs:ignore
		$actual = \file_get_contents( $this->test_file );
		$this->assertStringContainsString( '<?php', $actual, 'File should start with PHP opening tag' );
		$this->assertEquals( 1, substr_count( $actual, $this->original_cache_config ), 'Config line should appear exactly once' );
		$this->assertStringContainsString( "/* That's all, stop editing! */", $actual, 'Comment should be present' );
	}
}
