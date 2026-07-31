<?php
/**
 * Test LiteSpeed Cache integration.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Integration;

use Activitypub\Integration\Litespeed_Cache;

/**
 * Test LiteSpeed Cache integration.
 *
 * @group integration
 * @coversDefaultClass \Activitypub\Integration\Litespeed_Cache
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
		// Patch htaccess file path to use our temp file.
		\add_filter( 'activitypub_litespeed_cache_htaccess_file', array( $this, 'get_htaccess_file_path' ) );
	}

	/**
	 * Tear down the test environment.
	 */
	public function tear_down() {
		parent::tear_down();
		if ( \file_exists( $this->htaccess_file ) ) {
			\wp_delete_file( $this->htaccess_file );
		}
		\remove_filter( 'activitypub_litespeed_cache_htaccess_file', array( $this, 'get_htaccess_file_path' ) );
	}

	/**
	 * Get the htaccess file path for the test environment.
	 *
	 * @return string The htaccess file path.
	 */
	public function get_htaccess_file_path() {
		return $this->htaccess_file;
	}

	/**
	 * Test adding htaccess rules.
	 *
	 * @covers ::add_htaccess_rules
	 * @covers ::append_with_markers
	 * @covers ::get_htaccess_file_path
	 */
	public function test_add_htaccess_rules() {
		Litespeed_Cache::add_htaccess_rules();
		// phpcs:ignore
		$contents = \file_get_contents( $this->htaccess_file );
		$this->assertStringContainsString( Litespeed_Cache::$rules, $contents, 'LiteSpeed rules should be present in htaccess' );
	}

	/**
	 * Test removing htaccess rules.
	 *
	 * @covers ::remove_htaccess_rules
	 * @covers ::append_with_markers
	 */
	public function test_remove_htaccess_rules() {
		// First add, then remove.
		Litespeed_Cache::add_htaccess_rules();
		Litespeed_Cache::remove_htaccess_rules();
		// phpcs:ignore
		$contents = \file_get_contents( $this->htaccess_file );
		$this->assertStringNotContainsString( Litespeed_Cache::$rules, $contents, 'LiteSpeed rules should be removed from htaccess' );
	}

	/**
	 * Test no duplicate rules.
	 *
	 * @covers ::add_htaccess_rules
	 * @covers ::append_with_markers
	 */
	public function test_no_duplicate_rules() {
		Litespeed_Cache::add_htaccess_rules();
		Litespeed_Cache::add_htaccess_rules();
		// phpcs:ignore
		$contents = \file_get_contents( $this->htaccess_file );
		// Count number of rule blocks.
		$rule_count = substr_count( $contents, Litespeed_Cache::$rules );
		$this->assertEquals( 1, $rule_count, 'LiteSpeed rules should appear only once' );
	}

	/**
	 * Test that the option is updated when rules are added.
	 *
	 * @covers ::add_htaccess_rules
	 */
	public function test_option_updated_on_add() {
		Litespeed_Cache::add_htaccess_rules();
		$option = \get_option( Litespeed_Cache::$option_name );
		$this->assertEquals( \md5( Litespeed_Cache::$rules ), $option, 'Option should store the current rules fingerprint after adding rules' );
	}

	/**
	 * The htaccess Accept condition must classify a request exactly like the plugin's own
	 * is_json_only_accept(), or a page served as one representation could be cached as the other.
	 */
	public function test_rewrite_condition_matches_is_json_only_accept() {
		\preg_match( '/RewriteCond %\{HTTP:Accept\} (\S+) \[NC\]/', Litespeed_Cache::$rules, $matches );
		$this->assertNotEmpty( $matches[1] ?? '', 'Could not find the Accept RewriteCond pattern in the rules.' );
		$pattern = '#' . $matches[1] . '#i';

		$cases = array(
			'application/activity+json'            => true,
			'application/ld+json'                  => true,
			'application/json'                     => true,
			'application/ld+json; profile="https://www.w3.org/ns/activitystreams"' => true,
			'application/activity+json, application/ld+json' => true,
			'application/activity+json;q=0.9'      => true,
			'application/activity+json,'           => true,
			'text/html'                            => false,
			'text/html, application/activity+json' => false,
			'application/activity+json, text/html' => false,
			'*/*'                                  => false,
			'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8' => false,
			''                                     => false,
		);

		foreach ( $cases as $accept => $expected ) {
			$this->assertSame( $expected, (bool) \preg_match( $pattern, $accept ), "htaccess regex disagrees for: $accept" );
			$this->assertSame( $expected, \Activitypub\is_json_only_accept( $accept ), "is_json_only_accept disagrees for: $accept" );
		}
	}

	/**
	 * Test that the option is deleted when rules are removed.
	 *
	 * @covers ::remove_htaccess_rules
	 */
	public function test_option_deleted_on_remove() {
		// First add rules to set the option.
		Litespeed_Cache::add_htaccess_rules();
		$this->assertNotFalse( \get_option( Litespeed_Cache::$option_name ), 'Option should exist after adding rules' );

		// Then remove rules.
		Litespeed_Cache::remove_htaccess_rules();
		$this->assertFalse( \get_option( Litespeed_Cache::$option_name ), 'Option should be deleted after removing rules' );
	}

	/**
	 * Test Site Health status when properly configured.
	 *
	 * @covers ::test_litespeed_cache_integration
	 */
	public function test_site_health_when_configured() {
		// Set up as if rules were added successfully.
		\update_option( Litespeed_Cache::$option_name, '1' );

		$result = Litespeed_Cache::test_litespeed_cache_integration();

		$this->assertEquals( 'good', $result['status'], 'Status should be good when configured' );
		$this->assertEquals( 'green', $result['badge']['color'], 'Badge should be green' );
		$this->assertStringContainsString( 'well configured', $result['description'] );
	}

	/**
	 * Test Site Health status when not configured.
	 *
	 * @covers ::test_litespeed_cache_integration
	 */
	public function test_site_health_when_not_configured() {
		// Ensure option is false (not configured).
		\delete_option( Litespeed_Cache::$option_name );

		$result = Litespeed_Cache::test_litespeed_cache_integration();

		$this->assertEquals( 'critical', $result['status'], 'Status should be critical when not configured' );
		$this->assertEquals( 'red', $result['badge']['color'], 'Badge should be red' );
		$this->assertStringContainsString( 'not be properly configured', $result['label'] );
		$this->assertStringContainsString( 'add the following rules', $result['actions'] );
		$this->assertStringContainsString( \esc_html( Litespeed_Cache::$rules ), $result['actions'], 'Actions should contain HTML-escaped rules' );
	}

	/**
	 * Test write failure handling when htaccess is not writable.
	 *
	 * @covers ::add_htaccess_rules
	 * @covers ::append_with_markers
	 */
	public function test_write_failure_handling() {
		// Skip if running as root (e.g., in Docker), since root can write to any file.
		if ( 0 === posix_getuid() ) {
			$this->markTestSkipped( 'Cannot test file permission handling as root user.' );
		}

		// Create a read-only file.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod
		\chmod( $this->htaccess_file, 0444 );

		Litespeed_Cache::add_htaccess_rules();

		// Option should be set to '0' on failure.
		$option = \get_option( Litespeed_Cache::$option_name );
		$this->assertEquals( '0', $option, 'Option should be 0 when write fails' );

		// Restore permissions for cleanup.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod
		\chmod( $this->htaccess_file, 0644 );
	}

	/**
	 * Test that rules are removed when LiteSpeed Cache is deactivated.
	 *
	 * @covers ::init
	 * @covers ::remove_htaccess_rules
	 */
	public function test_cleanup_when_litespeed_deactivated() {
		// Simulate rules being previously added.
		Litespeed_Cache::add_htaccess_rules();
		$this->assertEquals( \md5( Litespeed_Cache::$rules ), \get_option( Litespeed_Cache::$option_name ) );

		// Mock that LiteSpeed is NOT active.
		$plugin_active_filter = function ( $is_active, $plugin ) {
			if ( Litespeed_Cache::$plugin_slug === $plugin ) {
				return false;
			}
			return $is_active;
		};
		\add_filter( 'activitypub_is_plugin_active', $plugin_active_filter, 10, 2 );

		// Run init (should detect LiteSpeed is deactivated and clean up).
		Litespeed_Cache::init();

		// Verify cleanup occurred.
		$this->assertFalse( \get_option( Litespeed_Cache::$option_name ), 'Option should be deleted when LiteSpeed is deactivated' );

		// phpcs:ignore
		$contents = \file_get_contents( $this->htaccess_file );
		$this->assertStringNotContainsString( Litespeed_Cache::$rules, $contents, 'Rules should be removed when LiteSpeed is deactivated' );

		\remove_filter( 'activitypub_is_plugin_active', $plugin_active_filter );
	}

	/**
	 * Test that rules are cleaned up when ActivityPub is deactivated.
	 *
	 * @covers \Activitypub\Activitypub::deactivate
	 */
	public function test_cleanup_on_activitypub_deactivation() {
		// Add rules first.
		Litespeed_Cache::add_htaccess_rules();
		$this->assertEquals( \md5( Litespeed_Cache::$rules ), \get_option( Litespeed_Cache::$option_name ) );

		// phpcs:ignore
		$contents_before = \file_get_contents( $this->htaccess_file );
		$this->assertStringContainsString( Litespeed_Cache::$rules, $contents_before );

		// Simulate deactivation.
		\Activitypub\Activitypub::deactivate( false );
		\do_action( 'deactivate_' . ACTIVITYPUB_PLUGIN_BASENAME );

		// Verify cleanup.
		$this->assertFalse( \get_option( Litespeed_Cache::$option_name ), 'Option should be deleted on deactivation' );

		// phpcs:ignore
		$contents_after = \file_get_contents( $this->htaccess_file );
		$this->assertStringNotContainsString( Litespeed_Cache::$rules, $contents_after, 'Rules should be removed on deactivation' );
	}

	/**
	 * Test that rules are cleaned up when LiteSpeed Cache plugin is deleted.
	 *
	 * @covers ::on_plugin_deleted
	 */
	public function test_cleanup_when_litespeed_deleted() {
		// Add rules first.
		Litespeed_Cache::add_htaccess_rules();
		$this->assertEquals( \md5( Litespeed_Cache::$rules ), \get_option( Litespeed_Cache::$option_name ) );

		// phpcs:ignore
		$contents_before = \file_get_contents( $this->htaccess_file );
		$this->assertStringContainsString( Litespeed_Cache::$rules, $contents_before );

		// Simulate LiteSpeed Cache plugin deletion.
		\do_action( 'deleted_plugin', Litespeed_Cache::$plugin_slug, false );

		// Verify cleanup.
		$this->assertFalse( \get_option( Litespeed_Cache::$option_name ), 'Option should be deleted when plugin is deleted' );

		// phpcs:ignore
		$contents_after = \file_get_contents( $this->htaccess_file );
		$this->assertStringNotContainsString( Litespeed_Cache::$rules, $contents_after, 'Rules should be removed when plugin is deleted' );
	}

	/**
	 * Test that rules are NOT cleaned up when a different plugin is deleted.
	 *
	 * @covers ::on_plugin_deleted
	 */
	public function test_no_cleanup_when_other_plugin_deleted() {
		// Add rules first.
		Litespeed_Cache::add_htaccess_rules();
		$this->assertEquals( \md5( Litespeed_Cache::$rules ), \get_option( Litespeed_Cache::$option_name ) );

		// Simulate a different plugin deletion.
		\do_action( 'deleted_plugin', 'some-other-plugin/plugin.php', false );

		// Verify rules still exist.
		$this->assertEquals( \md5( Litespeed_Cache::$rules ), \get_option( Litespeed_Cache::$option_name ), 'Option should remain when other plugin is deleted' );

		// phpcs:ignore
		$contents = \file_get_contents( $this->htaccess_file );
		$this->assertStringContainsString( Litespeed_Cache::$rules, $contents, 'Rules should remain when other plugin is deleted' );
	}
}
