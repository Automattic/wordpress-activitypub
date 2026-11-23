<?php
/**
 * Test Health_Check class.
 *
 * @package Activitypub
 */

use Activitypub\Collection\Outbox;
use Activitypub\OAuth\Server;
use Activitypub\Scheduler;
use Activitypub\WP_Admin\Health_Check;

use function Activitypub\get_rest_url_by_path;

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
		add_filter( 'option_active_plugins', array( $this, 'mock_active_plugins_no_captcha' ) );

		$result = Health_Check::test_check_for_captcha_plugins();

		$this->assertEquals( 'good', $result['status'] );
		$this->assertEquals( 'Check for Captcha Plugins', $result['label'] );
		$this->assertEquals( 'green', $result['badge']['color'] );
		$this->assertStringContainsString( 'No Captcha plugins were found', $result['description'] );

		remove_filter( 'option_active_plugins', array( $this, 'mock_active_plugins_no_captcha' ) );
	}

	/**
	 * Test captcha plugin detection when captcha plugins are found.
	 * This test focuses on the core detection logic rather than plugin name extraction.
	 */
	public function test_check_for_captcha_plugins_found() {
		// Mock active plugins with captcha plugins.
		add_filter( 'option_active_plugins', array( $this, 'mock_active_plugins_with_captcha' ) );

		$result = Health_Check::test_check_for_captcha_plugins();

		// Test the core functionality - captcha plugins should be detected.
		$this->assertEquals( 'recommended', $result['status'] );
		$this->assertEquals( 'Captcha plugins detected', $result['label'] );
		$this->assertEquals( 'orange', $result['badge']['color'] );
		$this->assertStringContainsString( 'The following Captcha plugins are active', $result['description'] );
		$this->assertStringContainsString( 'may interfere with ActivityPub functionality', $result['description'] );
		$this->assertStringContainsString( 'Plugin Page', $result['actions'] );

		// Clean up.
		remove_filter( 'option_active_plugins', array( $this, 'mock_active_plugins_with_captcha' ) );
	}

	/**
	 * Test captcha plugin detection with case-insensitive matching.
	 * This test focuses on the case-insensitive detection logic.
	 */
	public function test_check_for_captcha_plugins_case_insensitive() {
		// Mock active plugins with mixed case captcha plugins.
		add_filter( 'option_active_plugins', array( $this, 'mock_active_plugins_mixed_case' ) );

		$result = Health_Check::test_check_for_captcha_plugins();

		// Test that case-insensitive matching works.
		$this->assertEquals( 'recommended', $result['status'] );
		$this->assertEquals( 'Captcha plugins detected', $result['label'] );
		$this->assertEquals( 'orange', $result['badge']['color'] );

		remove_filter( 'option_active_plugins', array( $this, 'mock_active_plugins_mixed_case' ) );
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
		add_filter( 'option_active_plugins', array( $this, 'mock_active_plugins_with_captcha' ) );

		$result = Health_Check::test_check_for_captcha_plugins();

		// Test that the actions contain the correct plugin management link.
		// WordPress encodes & as &#038; for security, so we check for the encoded version.
		$this->assertStringContainsString( 'plugins.php?s=captcha&#038;plugin_status=all', $result['actions'] );
		$this->assertStringContainsString( 'Plugin Page', $result['actions'] );

		remove_filter( 'option_active_plugins', array( $this, 'mock_active_plugins_with_captcha' ) );
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

	/**
	 * Test that REST API accessibility test is registered.
	 */
	public function test_rest_api_accessibility_test_registered() {
		$tests  = array();
		$result = Health_Check::add_tests( $tests );

		$this->assertArrayHasKey( 'activitypub_test_rest_api_accessibility', $result['direct'] );

		$test = $result['direct']['activitypub_test_rest_api_accessibility'];
		$this->assertArrayHasKey( 'label', $test );
		$this->assertArrayHasKey( 'test', $test );
		$this->assertEquals( array( Health_Check::class, 'test_rest_api_accessibility' ), $test['test'] );
	}

	/**
	 * Mock HTTP response for accessible ActivityPub endpoint.
	 *
	 * @return array Mocked response.
	 */
	public function mock_activitypub_accessible() {
		return array(
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'body'     => '{"@context":"https://www.w3.org/ns/activitystreams","type":"OrderedCollection","totalItems":0}',
		);
	}

	/**
	 * Mock HTTP response for blocked ActivityPub endpoint (security plugin).
	 *
	 * @return array Mocked response.
	 */
	public function mock_activitypub_blocked() {
		return array(
			'response' => array(
				'code'    => 401,
				'message' => 'Unauthorized',
			),
			'body'     => '{"title":"rest_login_required","message":"REST API restricted to authenticated users.","data":{"status":401}}',
		);
	}

	/**
	 * Mock HTTP response for ActivityPub's own error (not a security plugin).
	 *
	 * @return array Mocked response.
	 */
	public function mock_activitypub_own_error() {
		return array(
			'response' => array(
				'code'    => 401,
				'message' => 'Unauthorized',
			),
			'body'     => '{"title":"activitypub_signature_verification_failed","message":"Signature verification failed."}',
		);
	}

	/**
	 * Mock HTTP response for connection error.
	 *
	 * @return WP_Error Mocked error response.
	 */
	public function mock_activitypub_connection_error() {
		return new WP_Error( 'http_request_failed', 'Connection refused' );
	}

	/**
	 * Test REST API accessibility when ActivityPub endpoint is accessible.
	 */
	public function test_rest_api_accessible() {
		add_filter( 'pre_http_request', array( $this, 'mock_activitypub_accessible' ) );

		$result = Health_Check::test_rest_api_accessibility();

		$this->assertEquals( 'good', $result['status'] );
		$this->assertEquals( 'REST API is accessible', $result['label'] );
		$this->assertEquals( 'green', $result['badge']['color'] );

		remove_filter( 'pre_http_request', array( $this, 'mock_activitypub_accessible' ) );
	}

	/**
	 * Test REST API accessibility when endpoint is blocked.
	 */
	public function test_rest_api_blocked() {
		add_filter( 'pre_http_request', array( $this, 'mock_activitypub_blocked' ) );

		$result = Health_Check::test_rest_api_accessibility();

		$this->assertEquals( 'critical', $result['status'] );
		$this->assertEquals( 'REST API is restricted to authenticated users', $result['label'] );
		$this->assertEquals( 'red', $result['badge']['color'] );
		$this->assertStringContainsString( 'security plugin settings', $result['actions'] );

		remove_filter( 'pre_http_request', array( $this, 'mock_activitypub_blocked' ) );
	}

	/**
	 * Test REST API accessibility with connection error.
	 */
	public function test_rest_api_connection_error() {
		add_filter( 'pre_http_request', array( $this, 'mock_activitypub_connection_error' ) );

		$result = Health_Check::test_rest_api_accessibility();

		$this->assertEquals( 'critical', $result['status'] );
		$this->assertStringContainsString( 'Could not connect to REST API', $result['description'] );

		remove_filter( 'pre_http_request', array( $this, 'mock_activitypub_connection_error' ) );
	}

	/**
	 * Test is_rest_api_accessible returns true for successful response.
	 */
	public function test_is_rest_api_accessible_returns_true() {
		add_filter( 'pre_http_request', array( $this, 'mock_activitypub_accessible' ) );

		$result = Health_Check::is_rest_api_accessible();

		$this->assertTrue( $result );

		remove_filter( 'pre_http_request', array( $this, 'mock_activitypub_accessible' ) );
	}

	/**
	 * Test is_rest_api_accessible returns WP_Error when blocked by security plugin.
	 */
	public function test_is_rest_api_accessible_returns_error_when_blocked() {
		add_filter( 'pre_http_request', array( $this, 'mock_activitypub_blocked' ) );

		$result = Health_Check::is_rest_api_accessible();

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'rest_api_restricted', $result->get_error_code() );

		remove_filter( 'pre_http_request', array( $this, 'mock_activitypub_blocked' ) );
	}

	/**
	 * Test is_rest_api_accessible ignores ActivityPub's own errors.
	 */
	public function test_is_rest_api_accessible_ignores_activitypub_errors() {
		add_filter( 'pre_http_request', array( $this, 'mock_activitypub_own_error' ) );

		$result = Health_Check::is_rest_api_accessible();

		// Should return true because error title starts with 'activitypub_'.
		$this->assertTrue( $result );

		remove_filter( 'pre_http_request', array( $this, 'mock_activitypub_own_error' ) );
	}

	/**
	 * Test that scheduled events test is registered.
	 */
	public function test_scheduled_events_test_registered() {
		$tests  = array();
		$result = Health_Check::add_tests( $tests );

		$this->assertArrayHasKey( 'activitypub_test_scheduled_events', $result['direct'] );

		$test = $result['direct']['activitypub_test_scheduled_events'];
		$this->assertArrayHasKey( 'label', $test );
		$this->assertArrayHasKey( 'test', $test );
		$this->assertEquals( array( Health_Check::class, 'test_scheduled_events' ), $test['test'] );
	}

	/**
	 * Test scheduled events health check when all schedules are registered.
	 */
	public function test_scheduled_events_all_registered() {
		// Ensure all schedules are registered.
		Scheduler::register_schedules();

		$result = Health_Check::test_scheduled_events();

		$this->assertEquals( 'good', $result['status'] );
		$this->assertEquals( 'ActivityPub scheduled events are registered', $result['label'] );
		$this->assertEquals( 'green', $result['badge']['color'] );
	}

	/**
	 * Test scheduled events health check auto-repairs missing schedules.
	 */
	public function test_scheduled_events_auto_repair() {
		// Remove all schedules.
		Scheduler::deregister_schedules();

		// Verify they are missing.
		$missing_before = Health_Check::get_missing_schedules();
		$this->assertNotEmpty( $missing_before );

		// Run the health check (should auto-repair).
		$result = Health_Check::test_scheduled_events();

		// Should report good status after auto-repair.
		$this->assertEquals( 'good', $result['status'] );
		$this->assertStringContainsString( 'automatically restored', $result['description'] );

		// Verify schedules are now registered.
		$missing_after = Health_Check::get_missing_schedules();
		$this->assertEmpty( $missing_after );
	}

	/**
	 * Test get_missing_schedules returns empty when all schedules are registered.
	 */
	public function test_get_missing_schedules_none_missing() {
		// Ensure all schedules are registered.
		Scheduler::register_schedules();

		$missing = Health_Check::get_missing_schedules();

		$this->assertEmpty( $missing );
	}

	/**
	 * Test get_missing_schedules returns missing schedules.
	 */
	public function test_get_missing_schedules_some_missing() {
		// Remove all schedules.
		Scheduler::deregister_schedules();

		$missing = Health_Check::get_missing_schedules();

		$this->assertNotEmpty( $missing );
		$this->assertArrayHasKey( 'activitypub_update_remote_actors', $missing );
		$this->assertArrayHasKey( 'activitypub_cleanup_remote_actors', $missing );
		$this->assertArrayHasKey( 'activitypub_reprocess_outbox', $missing );

		// Re-register for other tests.
		Scheduler::register_schedules();
	}

	/**
	 * Test ensure_schedules_registered repairs missing schedules.
	 */
	public function test_ensure_schedules_registered() {
		// Remove all schedules.
		Scheduler::deregister_schedules();

		// Verify they are missing.
		$missing_before = Health_Check::get_missing_schedules();
		$this->assertNotEmpty( $missing_before );

		// Call ensure_schedules_registered.
		Health_Check::ensure_schedules_registered();

		// Verify schedules are now registered.
		$missing_after = Health_Check::get_missing_schedules();
		$this->assertEmpty( $missing_after );
	}

	/**
	 * Test ensure_schedules_registered does nothing when all schedules exist.
	 */
	public function test_ensure_schedules_registered_no_op_when_all_exist() {
		// Ensure all schedules are registered.
		Scheduler::register_schedules();

		// Get next scheduled time for a schedule.
		$before = wp_next_scheduled( 'activitypub_update_remote_actors' );

		// Call ensure_schedules_registered.
		Health_Check::ensure_schedules_registered();

		// Verify the schedule time hasn't changed (wasn't re-registered).
		$after = wp_next_scheduled( 'activitypub_update_remote_actors' );
		$this->assertEquals( $before, $after );
	}

	/**
	 * Test Scheduler::SCHEDULES constant contains expected schedules.
	 */
	public function test_scheduler_schedules_constant() {
		$schedules = Scheduler::SCHEDULES;

		$this->assertIsArray( $schedules );
		$this->assertArrayHasKey( 'activitypub_update_remote_actors', $schedules );
		$this->assertArrayHasKey( 'activitypub_cleanup_remote_actors', $schedules );
		$this->assertArrayHasKey( 'activitypub_reprocess_outbox', $schedules );
		$this->assertArrayHasKey( 'activitypub_outbox_purge', $schedules );
		$this->assertArrayHasKey( 'activitypub_inbox_purge', $schedules );
		$this->assertArrayHasKey( 'activitypub_ap_post_purge', $schedules );
		$this->assertArrayHasKey( 'activitypub_sync_blocklist_subscriptions', $schedules );

		// Verify recurrence values.
		$this->assertEquals( 'hourly', $schedules['activitypub_update_remote_actors'] );
		$this->assertEquals( 'daily', $schedules['activitypub_cleanup_remote_actors'] );
		$this->assertEquals( 'weekly', $schedules['activitypub_sync_blocklist_subscriptions'] );
	}

	/**
	 * Test that outbox rate test is registered.
	 */
	public function test_outbox_rate_test_registered() {
		$tests  = array();
		$result = Health_Check::add_tests( $tests );

		$this->assertArrayHasKey( 'activitypub_test_outbox_rate', $result['direct'] );

		$test = $result['direct']['activitypub_test_outbox_rate'];
		$this->assertArrayHasKey( 'label', $test );
		$this->assertArrayHasKey( 'test', $test );
		$this->assertEquals( array( Health_Check::class, 'test_outbox_rate' ), $test['test'] );
	}

	/**
	 * Test outbox rate returns good status with no outbox items.
	 */
	public function test_outbox_rate_good() {
		$result = Health_Check::test_outbox_rate();

		$this->assertEquals( 'good', $result['status'] );
		$this->assertEquals( 'Outbox activity rate is normal', $result['label'] );
		$this->assertEquals( 'green', $result['badge']['color'] );
	}

	/**
	 * Test outbox rate returns recommended status with moderate activity.
	 */
	public function test_outbox_rate_recommended() {
		$this->delete_all_outbox_items();

		// Create 15 outbox items (above 10, below 50).
		$this->create_outbox_items( 15, 'https://example.com/post/1' );

		$result = Health_Check::test_outbox_rate();

		$this->assertEquals( 'recommended', $result['status'] );
		$this->assertEquals( 'Unusual outbox activity detected', $result['label'] );
		$this->assertEquals( 'orange', $result['badge']['color'] );
		$this->assertStringContainsString( '15 outbox items', $result['description'] );
	}

	/**
	 * Test outbox rate returns critical status with excessive activity.
	 */
	public function test_outbox_rate_critical() {
		$this->delete_all_outbox_items();

		// Create 55 outbox items (above 50).
		$this->create_outbox_items( 55, 'https://example.com/post/2' );

		$result = Health_Check::test_outbox_rate();

		$this->assertEquals( 'critical', $result['status'] );
		$this->assertEquals( 'Excessive outbox activity detected', $result['label'] );
		$this->assertEquals( 'red', $result['badge']['color'] );
		$this->assertStringContainsString( '55 outbox items', $result['description'] );
		$this->assertStringContainsString( 'excessive federation activity', $result['description'] );
	}

	/**
	 * Test get_outbox_rate_count returns correct total.
	 */
	public function test_outbox_rate_count() {
		$this->delete_all_outbox_items();

		$this->create_outbox_items( 5, 'https://example.com/post/a' );
		$this->create_outbox_items( 3, 'https://example.com/post/b' );
		$this->create_outbox_items( 7, 'https://example.com/post/c' );

		$this->assertEquals( 15, Health_Check::get_outbox_rate_count() );
	}

	/**
	 * Test get_outbox_count returns correct counts.
	 */
	public function test_get_outbox_count() {
		$this->create_outbox_items( 3, 'https://example.com/post/count-test' );

		// Outbox items are created with 'pending' status.
		$pending_count = Health_Check::get_outbox_count( 'pending' );
		$this->assertGreaterThanOrEqual( 3, $pending_count );

		$total_count = Health_Check::get_outbox_count();
		$this->assertGreaterThanOrEqual( 3, $total_count );
	}

	/**
	 * Test debug_information includes outbox stats.
	 */
	public function test_debug_information_includes_outbox_stats() {
		$info   = array();
		$result = Health_Check::debug_information( $info );

		$fields = $result['activitypub']['fields'];

		$this->assertArrayHasKey( 'outbox_total_count', $fields );
		$this->assertArrayHasKey( 'outbox_pending_count', $fields );
		$this->assertArrayHasKey( 'outbox_last_hour_count', $fields );

		$this->assertEquals( 'Outbox Total Items', $fields['outbox_total_count']['label'] );
		$this->assertEquals( 'Outbox Pending Items', $fields['outbox_pending_count']['label'] );
		$this->assertEquals( 'Outbox Items (Last Hour)', $fields['outbox_last_hour_count']['label'] );
	}

	/**
	 * Test outbox rate recommended status with multiple objects.
	 */
	public function test_outbox_rate_recommended_multiple_objects() {
		$this->delete_all_outbox_items();

		$this->create_outbox_items( 4, 'https://example.com/post/1' );
		$this->create_outbox_items( 3, 'https://example.com/post/2' );
		$this->create_outbox_items( 2, 'https://example.com/post/3' );
		$this->create_outbox_items( 3, 'https://example.com/post/4' );

		$result = Health_Check::test_outbox_rate();

		// 12 items total, should be "recommended".
		$this->assertEquals( 'recommended', $result['status'] );
		$this->assertStringContainsString( '12 outbox items', $result['description'] );
	}

	/**
	 * Test that outbox rate only counts items from the last hour.
	 */
	public function test_outbox_rate_excludes_old_items() {
		$this->delete_all_outbox_items();

		// Create 15 recent items (within the last hour).
		$this->create_outbox_items( 15, 'https://example.com/post/recent' );

		// Create 20 old items (2 hours ago — outside the window).
		$this->create_outbox_items( 20, 'https://example.com/post/old', '2 hours ago' );

		// Only the 15 recent items should be counted.
		$this->assertEquals( 15, Health_Check::get_outbox_rate_count() );
	}

	/**
	 * Delete all existing outbox items to ensure a clean test state.
	 */
	private function delete_all_outbox_items() {
		$posts = get_posts(
			array(
				'post_type'      => Outbox::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		foreach ( $posts as $post_id ) {
			wp_delete_post( $post_id, true );
		}
	}

	/**
	 * Mocked answers for the well-known paths, keyed by the document name.
	 *
	 * @var array
	 */
	private $well_known_responses = array();

	/**
	 * Answer requests for the well-known paths with the mocked responses.
	 *
	 * @param false|array|WP_Error $response    The preempted response.
	 * @param array                $parsed_args The request arguments.
	 * @param string               $url         The request URL.
	 *
	 * @return false|array|WP_Error The mocked response, or the original one for other URLs.
	 */
	public function mock_well_known( $response, $parsed_args, $url ) {
		foreach ( $this->well_known_responses as $name => $mocked ) {
			if ( \home_url( '/.well-known/' . $name ) === $url ) {
				return $mocked;
			}
		}

		return $response;
	}

	/**
	 * Build a mocked JSON response.
	 *
	 * @param array $data The document.
	 * @param int   $code The HTTP status code.
	 *
	 * @return array The mocked response.
	 */
	private function json_response( $data, $code = 200 ) {
		return array(
			'headers'  => array( 'content-type' => 'application/json' ),
			'response' => array(
				'code'    => $code,
				'message' => '',
			),
			'body'     => \wp_json_encode( $data ),
		);
	}

	/**
	 * Run the API discovery test with the given answers, the plugin's own documents by default.
	 *
	 * @param array $responses Mocked answers keyed by the document name.
	 *
	 * @return array The test result.
	 */
	private function run_well_known_test( $responses = array() ) {
		$this->well_known_responses = \array_merge(
			array(
				'oauth-authorization-server' => $this->json_response( Server::get_metadata() ),
				'activitypub-client-config'  => $this->json_response(
					array(
						'links' => array(
							array(
								'rel'  => 'https://w3id.org/fep/65ca/1.0',
								'href' => get_rest_url_by_path( 'client-config/1.0' ),
							),
						),
					)
				),
			),
			$responses
		);

		\add_filter( 'pre_http_request', array( $this, 'mock_well_known' ), 10, 3 );
		$result = Health_Check::test_well_known_api();
		\remove_filter( 'pre_http_request', array( $this, 'mock_well_known' ), 10 );

		return $result;
	}

	/**
	 * Test that the API discovery test runs async over admin-ajax, and only when the API is on and pretty permalinks are set.
	 */
	public function test_well_known_api_test_registered() {
		\update_option( 'permalink_structure', '/%postname%/' );

		\update_option( 'activitypub_api', '1' );
		$result = Health_Check::add_tests( array() );
		$this->assertArrayHasKey( 'activitypub_test_well_known_api', $result['async'] );
		$this->assertArrayNotHasKey( 'activitypub_test_well_known_api', $result['direct'] );

		$test = $result['async']['activitypub_test_well_known_api'];
		$this->assertArrayNotHasKey( 'has_rest', $test, 'Runs over admin-ajax, so a REST restriction cannot stop the test.' );
		$this->assertSame( 1, \substr_count( $test['test'], '_' ), 'Core replaces only the first underscore when it builds the action.' );
		Health_Check::init();
		$this->assertNotFalse( \has_action( 'wp_ajax_health-check-' . \str_replace( '_', '-', $test['test'] ), array( Health_Check::class, 'ajax_well_known_api' ) ) );
		$this->assertSame( array( Health_Check::class, 'test_well_known_api' ), $test['async_direct_test'] );
		$this->assertTrue( $test['skip_cron'] );

		\update_option( 'activitypub_api', '' );
		$result = Health_Check::add_tests( array() );
		$this->assertArrayNotHasKey( 'async', $result );

		\update_option( 'activitypub_api', '1' );
		\update_option( 'permalink_structure', '' );
		$result = Health_Check::add_tests( array() );
		$this->assertArrayNotHasKey( 'async', $result );
	}

	/**
	 * Test that the plugin's own documents pass.
	 */
	public function test_well_known_api_good() {
		$result = $this->run_well_known_test();

		$this->assertEquals( 'good', $result['status'] );
		$this->assertEquals( 'green', $result['badge']['color'] );
	}

	/**
	 * Test that another OAuth server on the well-known path is reported.
	 */
	public function test_well_known_api_oauth_taken_over() {
		$result = $this->run_well_known_test(
			array(
				'oauth-authorization-server' => $this->json_response(
					array(
						'issuer'                 => \home_url(),
						'authorization_endpoint' => \home_url( '/oauth/authorize' ),
					)
				),
			)
		);

		$this->assertEquals( 'recommended', $result['status'] );
		$this->assertEquals( 'orange', $result['badge']['color'] );
		$this->assertStringContainsString( 'Another plugin or the web server answers <code>' . \home_url( '/.well-known/oauth-authorization-server' ) . '</code>', $result['description'] );
		$this->assertStringNotContainsString( 'activitypub-client-config', $result['description'] );
	}

	/**
	 * Test that a client configuration without the plugin's link is reported.
	 */
	public function test_well_known_api_client_config_taken_over() {
		$result = $this->run_well_known_test(
			array(
				'activitypub-client-config' => $this->json_response( array( 'links' => array( 'not a link' ) ) ),
			)
		);

		$this->assertEquals( 'recommended', $result['status'] );
		$this->assertStringContainsString( 'answers <code>' . \home_url( '/.well-known/activitypub-client-config' ) . '</code>', $result['description'] );
		$this->assertStringNotContainsString( 'oauth-authorization-server', $result['description'] );
	}

	/**
	 * Test that an answer that is not JSON is reported as taken over.
	 */
	public function test_well_known_api_not_json() {
		$response         = $this->json_response( array() );
		$response['body'] = '<html><body>Login</body></html>';

		$result = $this->run_well_known_test( array( 'oauth-authorization-server' => $response ) );

		$this->assertEquals( 'recommended', $result['status'] );
		$this->assertStringContainsString( 'Another plugin or the web server answers', $result['description'] );
	}

	/**
	 * Test that an error status is reported as not reachable.
	 */
	public function test_well_known_api_not_found() {
		$result = $this->run_well_known_test(
			array( 'oauth-authorization-server' => $this->json_response( array( 'code' => 'rest_no_route' ), 404 ) )
		);

		$this->assertEquals( 'recommended', $result['status'] );
		$this->assertStringContainsString( 'is not reachable: HTTP 404', $result['description'] );
	}

	/**
	 * Test that a connection error is reported as not reachable.
	 */
	public function test_well_known_api_connection_error() {
		$result = $this->run_well_known_test(
			array( 'activitypub-client-config' => new WP_Error( 'http_request_failed', 'Connection refused' ) )
		);

		$this->assertEquals( 'recommended', $result['status'] );
		$this->assertStringContainsString( 'is not reachable: Connection refused', $result['description'] );
	}

	/**
	 * Run the admin-ajax handler of the API discovery test and return its output.
	 *
	 * The handler ends in wp_die(). The ajax die handler is made to throw, so the output buffer
	 * holds exactly the response.
	 *
	 * @return string The JSON response.
	 */
	private function run_ajax_well_known_api() {
		$die_handler = static function () {
			return static function ( $message ) {
				throw new \WPDieException( \esc_html( (string) $message ) );
			};
		};

		\add_filter( 'wp_doing_ajax', '__return_true' );
		\add_filter( 'wp_die_ajax_handler', $die_handler );

		\ob_start();
		try {
			Health_Check::ajax_well_known_api();
			$this->fail( 'Expected the handler to end in wp_die().' );
		} catch ( \WPDieException $e ) {
			return (string) \ob_get_clean();
		} finally {
			if ( \ob_get_level() > 0 && '' !== (string) \ob_get_contents() ) {
				\ob_end_clean();
			}
			\remove_filter( 'wp_doing_ajax', '__return_true' );
			\remove_filter( 'wp_die_ajax_handler', $die_handler );
			unset( $_REQUEST['_wpnonce'] );
		}
	}

	/**
	 * Create a user who may view Site Health.
	 *
	 * @return int The user ID.
	 */
	private function create_site_health_viewer() {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );

		// On a network, core grants Site Health to super admins only.
		if ( \is_multisite() ) {
			\grant_super_admin( $user_id );
		}

		return $user_id;
	}

	/**
	 * Test that the admin-ajax handler returns the result the way the Site Health screen reads it.
	 */
	public function test_ajax_well_known_api_returns_result() {
		\wp_set_current_user( $this->create_site_health_viewer() );
		$_REQUEST['_wpnonce'] = \wp_create_nonce( 'health-check-site-status' );

		$this->well_known_responses = array(
			'oauth-authorization-server' => new WP_Error( 'http_request_failed', 'Connection refused' ),
			'activitypub-client-config'  => new WP_Error( 'http_request_failed', 'Connection refused' ),
		);
		\add_filter( 'pre_http_request', array( $this, 'mock_well_known' ), 10, 3 );

		$json = \json_decode( $this->run_ajax_well_known_api(), true );

		\remove_filter( 'pre_http_request', array( $this, 'mock_well_known' ), 10 );

		$this->assertTrue( $json['success'] );
		$this->assertSame( 'test_well_known_api', $json['data']['test'] );
		$this->assertSame( 'recommended', $json['data']['status'] );
	}

	/**
	 * Test that the admin-ajax handler makes no request without the Site Health nonce.
	 */
	public function test_ajax_well_known_api_requires_nonce() {
		\wp_set_current_user( $this->create_site_health_viewer() );
		$_REQUEST['_wpnonce'] = 'wrong';

		$requested = false;
		$spy       = static function ( $response ) use ( &$requested ) {
			$requested = true;

			return $response;
		};
		\add_filter( 'pre_http_request', $spy );

		$output = $this->run_ajax_well_known_api();

		\remove_filter( 'pre_http_request', $spy );

		$this->assertStringNotContainsString( '"success":true', $output );
		$this->assertFalse( $requested );
	}

	/**
	 * Test that the admin-ajax handler rejects users who may not view Site Health.
	 */
	public function test_ajax_well_known_api_requires_capability() {
		\wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$_REQUEST['_wpnonce'] = \wp_create_nonce( 'health-check-site-status' );

		$json = \json_decode( $this->run_ajax_well_known_api(), true );

		$this->assertFalse( $json['success'] );
	}

	/**
	 * Helper to create outbox items for testing.
	 *
	 * @param int    $count     Number of items to create.
	 * @param string $object_id The object ID meta value.
	 * @param string $post_date Optional. The post date. Default current time.
	 *
	 * @return int[] Array of created post IDs.
	 */
	private function create_outbox_items( $count, $object_id, $post_date = '' ) {
		$post_ids  = array();
		$base_time = $post_date ? \strtotime( $post_date ) : \time();

		for ( $i = 0; $i < $count; $i++ ) {
			$post_id = $this->factory()->post->create(
				array(
					'post_type'   => Outbox::POST_TYPE,
					'post_status' => 'pending',
					'post_title'  => '[Update] Test Post',
					'post_date'   => \gmdate( 'Y-m-d H:i:s', $base_time - $i ),
					'meta_input'  => array(
						'_activitypub_object_id'     => $object_id,
						'_activitypub_activity_type' => 'Update',
					),
				)
			);

			$post_ids[] = $post_id;
		}

		return $post_ids;
	}
}
