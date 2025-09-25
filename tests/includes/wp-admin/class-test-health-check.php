<?php
/**
 * Test Health_Check class.
 *
 * @package Activitypub
 */

use Activitypub\WP_Admin\Health_Check;

/**
 * Test Health_Check class.
 */
class Test_Health_Check extends WP_UnitTestCase {

	/**
	 * Test that health check tests are properly registered.
	 */
	public function test_add_tests() {
		$tests  = array();
		$result = Health_Check::add_tests( $tests );

		// Check that the captcha test is registered.
		$this->assertArrayHasKey( 'direct', $result );
		$this->assertArrayHasKey( 'activitypub_check_for_captcha_plugins', $result['direct'] );

		// Verify test structure.
		$captcha_test = $result['direct']['activitypub_check_for_captcha_plugins'];
		$this->assertArrayHasKey( 'label', $captcha_test );
		$this->assertArrayHasKey( 'test', $captcha_test );
		$this->assertEquals( array( Health_Check::class, 'test_check_for_captcha_plugins' ), $captcha_test['test'] );
	}

	/**
	 * Mock function to return active plugins without captcha.
	 *
	 * @return array List of active plugins.
	 */
	public function mock_active_plugins_no_captcha() {
		return array( 'some-other-plugin/plugin.php', 'another-plugin/main.php' );
	}

	/**
	 * Mock function to return active plugins with captcha.
	 *
	 * @return array List of active plugins.
	 */
	public function mock_active_plugins_with_captcha() {
		return array(
			'really-simple-captcha/really-simple-captcha.php',
			'some-other-plugin/plugin.php',
			'recaptcha-for-woocommerce/recaptcha.php',
		);
	}

	/**
	 * Mock function to return active plugins with mixed case captcha.
	 *
	 * @return array List of active plugins.
	 */
	public function mock_active_plugins_mixed_case() {
		return array(
			'CAPTCHA-plugin/captcha.php',
			'some-plugin-with-CaPtChA/main.php',
			'regular-plugin/plugin.php',
		);
	}

	/**
	 * Test captcha plugin detection when no captcha plugins are active.
	 */
	public function test_check_for_captcha_plugins_none_found() {
		// Mock empty active plugins.
		add_filter(
			'option_active_plugins',
			array( $this, 'mock_active_plugins_no_captcha' )
		);

		$result = Health_Check::test_check_for_captcha_plugins();

		$this->assertEquals( 'good', $result['status'] );
		$this->assertEquals( 'Check for Captcha Plugins', $result['label'] );
		$this->assertEquals( 'green', $result['badge']['color'] );
		$this->assertStringContainsString( 'No Captcha plugins were found', $result['description'] );

		remove_all_filters( 'option_active_plugins' );
	}

	/**
	 * Test captcha plugin detection when captcha plugins are found.
	 * This test focuses on the core detection logic rather than plugin name extraction.
	 */
	public function test_check_for_captcha_plugins_found() {
		// Mock active plugins with captcha plugins.
		add_filter(
			'option_active_plugins',
			array( $this, 'mock_active_plugins_with_captcha' )
		);

		$result = Health_Check::test_check_for_captcha_plugins();

		// Test the core functionality - captcha plugins should be detected.
		$this->assertEquals( 'recommended', $result['status'] );
		$this->assertEquals( 'Captcha plugins detected', $result['label'] );
		$this->assertEquals( 'orange', $result['badge']['color'] );
		$this->assertStringContainsString( 'The following Captcha plugins are active', $result['description'] );
		$this->assertStringContainsString( 'may interfere with ActivityPub functionality', $result['description'] );
		$this->assertStringContainsString( 'Plugin Page', $result['actions'] );

		// Clean up.
		remove_all_filters( 'option_active_plugins' );
	}

	/**
	 * Test captcha plugin detection with case-insensitive matching.
	 * This test focuses on the case-insensitive detection logic.
	 */
	public function test_check_for_captcha_plugins_case_insensitive() {
		// Mock active plugins with mixed case captcha plugins.
		add_filter(
			'option_active_plugins',
			array( $this, 'mock_active_plugins_mixed_case' )
		);

		$result = Health_Check::test_check_for_captcha_plugins();

		// Test that case-insensitive matching works.
		$this->assertEquals( 'recommended', $result['status'] );
		$this->assertEquals( 'Captcha plugins detected', $result['label'] );
		$this->assertEquals( 'orange', $result['badge']['color'] );

		remove_all_filters( 'option_active_plugins' );
	}

	/**
	 * Test count_results method.
	 */
	public function test_count_results() {
		// Test counting all results.
		$all_results = Health_Check::count_results( 'all' );
		$this->assertIsArray( $all_results );
		$this->assertArrayHasKey( 'good', $all_results );
		$this->assertArrayHasKey( 'critical', $all_results );
		$this->assertArrayHasKey( 'recommended', $all_results );

		// Test counting specific result types.
		$good_count = Health_Check::count_results( 'good' );
		$this->assertIsInt( $good_count );

		$critical_count = Health_Check::count_results( 'critical' );
		$this->assertIsInt( $critical_count );

		$recommended_count = Health_Check::count_results( 'recommended' );
		$this->assertIsInt( $recommended_count );
	}

	/**
	 * Test that the actions link points to the correct plugin page.
	 * This test focuses on the action link generation.
	 */
	public function test_captcha_plugins_actions_link() {
		// Mock active plugins with captcha plugin.
		add_filter(
			'option_active_plugins',
			array( $this, 'mock_active_plugins_with_captcha' )
		);

		$result = Health_Check::test_check_for_captcha_plugins();

		// Test that the actions contain the correct plugin management link.
		// WordPress encodes & as &#038; for security, so we check for the encoded version.
		$this->assertStringContainsString( 'plugins.php?s=captcha&#038;plugin_status=all', $result['actions'] );
		$this->assertStringContainsString( 'Plugin Page', $result['actions'] );

		remove_all_filters( 'option_active_plugins' );
	}

	/**
	 * Test debug_information method includes ActivityPub fields.
	 */
	public function test_debug_information() {
		$info   = array();
		$result = Health_Check::debug_information( $info );

		$this->assertArrayHasKey( 'activitypub', $result );
		$this->assertArrayHasKey( 'label', $result['activitypub'] );
		$this->assertArrayHasKey( 'fields', $result['activitypub'] );
		$this->assertEquals( 'ActivityPub', $result['activitypub']['label'] );
	}

	/**
	 * Test captcha plugin array filtering functionality.
	 * This tests the array_filter behavior used in the health check.
	 */
	public function test_captcha_plugin_array_filtering() {
		// Test the array filtering used in the health check to remove empty plugin names.
		$captcha_plugins = array( 'really-simple-captcha/captcha.php', 'another-captcha/main.php' );

		// Simulate the array_filter operation from the health check.
		$filtered_plugins = array_filter(
			$captcha_plugins,
			function ( $plugin ) {
				return str_contains( strtolower( $plugin ), 'captcha' );
			}
		);

		$this->assertCount( 2, $filtered_plugins );
		$this->assertContains( 'really-simple-captcha/captcha.php', $filtered_plugins );
		$this->assertContains( 'another-captcha/main.php', $filtered_plugins );
	}

	/**
	 * Test that array_filter works correctly to remove false values.
	 */
	public function test_array_filter_removes_false_values() {
		$plugin_names = array( 'Really Simple CAPTCHA', false, 'Another Plugin', false );
		$filtered     = array_filter( $plugin_names );

		$this->assertCount( 2, $filtered );
		$this->assertContains( 'Really Simple CAPTCHA', $filtered );
		$this->assertContains( 'Another Plugin', $filtered );
		$this->assertNotContains( false, $filtered );
	}
}
