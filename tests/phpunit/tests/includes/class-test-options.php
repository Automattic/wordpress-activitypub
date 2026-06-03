<?php
/**
 * Test file for Activitypub Options class.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

use Activitypub\Options;
use Activitypub\Scheduler;

/**
 * Test class for Activitypub Options.
 */
class Test_Options extends \WP_UnitTestCase {
	/**
	 * Set up the test.
	 */
	public function set_up() {
		parent::set_up();

		// Initialize Options to register hooks after storing original values.
		\Activitypub\Options::init();
	}

	/**
	 * Tear down the test.
	 */
	public function tear_down() {
		// Clean up relay-specific options.
		\delete_option( 'activitypub_relay_previous_blog_identifier' );
		\delete_option( 'activitypub_relay_previous_actor_mode' );
		\delete_option( 'activitypub_relay_mode' );
		\delete_option( 'activitypub_blog_identifier' );
		\delete_option( 'activitypub_actor_mode' );

		// Clean up reader-specific options.
		\delete_option( 'activitypub_reader_ui' );
		\delete_option( 'activitypub_following_ui' );
		\delete_option( 'activitypub_create_posts' );

		// Clean up quote policy option.
		\delete_option( 'activitypub_default_quote_policy' );

		// Clean up distribution mode options.
		\delete_option( 'activitypub_distribution_mode' );
		\delete_option( 'activitypub_custom_batch_size' );
		\delete_option( 'activitypub_custom_batch_pause' );

		parent::tear_down();
	}

	/**
	 * Test that delete() removes all options with the activitypub_ prefix.
	 */
	public function test_delete_removes_all_activitypub_options() {
		\add_option( 'activitypub_test_option_1', 'value1' );
		\add_option( 'activitypub_test_option_2', 'value2' );
		\add_option( 'activitypub_test_option_3', 'value3' );
		\add_option( 'no_activitypub_test_option', 'value4' );

		$this->assertEquals( 'value1', \get_option( 'activitypub_test_option_1' ) );
		$this->assertEquals( 'value2', \get_option( 'activitypub_test_option_2' ) );
		$this->assertEquals( 'value3', \get_option( 'activitypub_test_option_3' ) );
		$this->assertEquals( 'value4', \get_option( 'no_activitypub_test_option' ) );

		Options::delete();

		\wp_cache_flush();

		$this->assertFalse( \get_option( 'activitypub_test_option_1', false ) );
		$this->assertFalse( \get_option( 'activitypub_test_option_2', false ) );
		$this->assertFalse( \get_option( 'activitypub_test_option_3', false ) );
		$this->assertEquals( 'value4', \get_option( 'no_activitypub_test_option' ) );
	}

	/**
	 * Test enabling relay mode changes settings.
	 *
	 * @covers \Activitypub\Options::relay_mode_changed
	 */
	public function test_enabling_relay_mode() {
		// Set initial values.
		\update_option( 'activitypub_blog_identifier', 'myblog' );
		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_AND_BLOG_MODE );
		\update_option( 'activitypub_relay_mode', '0' );

		// Enable relay mode.
		\update_option( 'activitypub_relay_mode', '1' );

		// Verify blog identifier changed to 'relay'.
		$this->assertEquals( 'relay', \get_option( 'activitypub_blog_identifier' ) );

		// Verify actor mode changed to blog-only.
		$this->assertEquals( ACTIVITYPUB_BLOG_MODE, \get_option( 'activitypub_actor_mode' ) );

		// Verify previous values were stored.
		$this->assertEquals( 'myblog', \get_option( 'activitypub_relay_previous_blog_identifier' ) );
		$this->assertEquals( ACTIVITYPUB_ACTOR_AND_BLOG_MODE, \get_option( 'activitypub_relay_previous_actor_mode' ) );
	}

	/**
	 * Test disabling relay mode restores settings.
	 *
	 * @covers \Activitypub\Options::relay_mode_changed
	 */
	public function test_disabling_relay_mode() {
		// Enable relay mode first.
		\update_option( 'activitypub_blog_identifier', 'myblog' );
		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_AND_BLOG_MODE );
		\update_option( 'activitypub_relay_mode', '1' );

		// Now disable it.
		\update_option( 'activitypub_relay_mode', '0' );

		// Verify settings were restored.
		$this->assertEquals( 'myblog', \get_option( 'activitypub_blog_identifier' ) );
		$this->assertEquals( ACTIVITYPUB_ACTOR_AND_BLOG_MODE, \get_option( 'activitypub_actor_mode' ) );

		// Verify previous value options were deleted.
		$this->assertFalse( \get_option( 'activitypub_relay_previous_blog_identifier', false ) );
		$this->assertFalse( \get_option( 'activitypub_relay_previous_actor_mode', false ) );
	}

	/**
	 * Test that enabling reader UI enables following UI.
	 *
	 * @covers \Activitypub\Options::pre_option_activitypub_following_ui
	 */
	public function test_reader_ui_enables_following_ui() {
		// Initially following UI should be disabled.
		$this->assertEquals( '0', \get_option( 'activitypub_following_ui', '0' ) );

		// Enable reader UI.
		\update_option( 'activitypub_reader_ui', '1' );

		// Following UI should now be enabled via the filter.
		$this->assertEquals( '1', \get_option( 'activitypub_following_ui', '0' ) );
	}

	/**
	 * Test that enabling reader UI enables create posts.
	 *
	 * @covers \Activitypub\Options::pre_option_activitypub_create_posts
	 */
	public function test_reader_ui_enables_create_posts() {
		// Initially create posts should be disabled.
		$this->assertFalse( \get_option( 'activitypub_create_posts', false ) );

		// Enable reader UI.
		\update_option( 'activitypub_reader_ui', '1' );

		// Create posts should now be enabled via the filter.
		$this->assertEquals( '1', \get_option( 'activitypub_create_posts', false ) );
	}

	/**
	 * Test that disabling reader UI does not force following UI.
	 *
	 * @covers \Activitypub\Options::pre_option_activitypub_following_ui
	 */
	public function test_reader_ui_disabled_does_not_force_following_ui() {
		// Ensure reader UI is disabled.
		\delete_option( 'activitypub_reader_ui' );

		// Set following UI manually.
		\update_option( 'activitypub_following_ui', '1' );

		// Following UI should remain as set.
		$this->assertEquals( '1', \get_option( 'activitypub_following_ui', '0' ) );

		// Disable following UI.
		\update_option( 'activitypub_following_ui', '0' );

		// Following UI should be disabled.
		$this->assertEquals( '0', \get_option( 'activitypub_following_ui', '0' ) );
	}

	/**
	 * Test that disabling reader UI does not force create posts.
	 *
	 * @covers \Activitypub\Options::pre_option_activitypub_create_posts
	 */
	public function test_reader_ui_disabled_does_not_force_create_posts() {
		// Ensure reader UI is disabled.
		\delete_option( 'activitypub_reader_ui' );

		// Set create posts manually.
		\update_option( 'activitypub_create_posts', '1' );

		// Create posts should remain as set.
		$this->assertEquals( '1', \get_option( 'activitypub_create_posts', false ) );

		// Disable create posts.
		\delete_option( 'activitypub_create_posts' );

		// Create posts should be disabled.
		$this->assertFalse( \get_option( 'activitypub_create_posts', false ) );
	}

	/**
	 * Test default quote policy option has correct default value.
	 *
	 * @covers \Activitypub\Options::register_settings
	 */
	public function test_default_quote_policy_default_value() {
		// Without setting the option, it should return the default.
		$this->assertEquals(
			ACTIVITYPUB_INTERACTION_POLICY_ANYONE,
			\get_option( 'activitypub_default_quote_policy', ACTIVITYPUB_INTERACTION_POLICY_ANYONE )
		);
	}

	/**
	 * Test default quote policy option accepts valid values.
	 *
	 * @covers \Activitypub\Options::register_settings
	 */
	public function test_default_quote_policy_accepts_valid_values() {
		// Test 'anyone' value.
		\update_option( 'activitypub_default_quote_policy', ACTIVITYPUB_INTERACTION_POLICY_ANYONE );
		$this->assertEquals( ACTIVITYPUB_INTERACTION_POLICY_ANYONE, \get_option( 'activitypub_default_quote_policy' ) );

		// Test 'followers' value.
		\update_option( 'activitypub_default_quote_policy', ACTIVITYPUB_INTERACTION_POLICY_FOLLOWERS );
		$this->assertEquals( ACTIVITYPUB_INTERACTION_POLICY_FOLLOWERS, \get_option( 'activitypub_default_quote_policy' ) );

		// Test 'me' value.
		\update_option( 'activitypub_default_quote_policy', ACTIVITYPUB_INTERACTION_POLICY_ME );
		$this->assertEquals( ACTIVITYPUB_INTERACTION_POLICY_ME, \get_option( 'activitypub_default_quote_policy' ) );
	}

	/**
	 * Test distribution mode returns correct params for presets.
	 *
	 * @covers \Activitypub\Options::get_distribution_params
	 */
	public function test_distribution_params_presets() {
		\update_option( 'activitypub_distribution_mode', 'eco' );

		$params = Options::get_distribution_params();

		$this->assertEquals( 20, $params['batch_size'] );
		$this->assertEquals( 30, $params['pause'] );
	}

	/**
	 * Test distribution mode custom params.
	 *
	 * @covers \Activitypub\Options::get_distribution_params
	 */
	public function test_distribution_params_custom() {
		Options::register_settings();

		\update_option( 'activitypub_distribution_mode', 'custom' );
		\update_option( 'activitypub_custom_batch_size', 42 );
		\update_option( 'activitypub_custom_batch_pause', 120 );

		$params = Options::get_distribution_params();

		$this->assertEquals( 42, $params['batch_size'] );
		$this->assertEquals( 120, $params['pause'] );
	}

	/**
	 * Test get_distribution_params falls back to default for an unrecognized mode.
	 *
	 * Skips register_settings() so the bogus value bypasses the sanitize
	 * callback (mimicking a stale option from an older release or a direct
	 * DB write).
	 *
	 * @covers \Activitypub\Options::get_distribution_params
	 */
	public function test_distribution_params_unknown_mode_falls_back_to_default() {
		\update_option( 'activitypub_distribution_mode', 'turbo' );

		$params = Options::get_distribution_params();

		$this->assertEquals( 'default', $params['mode'] );
		$this->assertEquals( 100, $params['batch_size'] );
		$this->assertEquals( 15, $params['pause'] );
	}

	/**
	 * Test the default mode delivers faster than the generic async-batch baseline.
	 *
	 * Default mode imposes a 15s delivery pause on `activitypub_send_activity`
	 * while non-delivery batches keep the 30s scheduler baseline.
	 *
	 * @covers \Activitypub\Options::filter_scheduler_batch_pause
	 * @covers \Activitypub\Options::get_distribution_params
	 */
	public function test_default_distribution_pause_applies_to_delivery_only() {
		\update_option( 'activitypub_distribution_mode', 'default' );

		$this->assertEquals( 15, Options::get_distribution_params()['pause'] );
		$this->assertEquals( 15, Scheduler::get_retry_delay( 'activitypub_send_activity' ) );
		$this->assertEquals( 30, Scheduler::get_retry_delay( 'activitypub_create_post_outbox_items' ) );
	}

	/**
	 * Test custom distribution pause only applies to delivery batches.
	 *
	 * @covers \Activitypub\Options::filter_scheduler_batch_pause
	 */
	public function test_custom_distribution_pause_only_applies_to_delivery_batches() {
		\update_option( 'activitypub_distribution_mode', 'custom' );
		\update_option( 'activitypub_custom_batch_pause', 120 );

		$this->assertEquals( 120, Scheduler::get_retry_delay( 'activitypub_send_activity' ) );
		$this->assertEquals( 30, Scheduler::get_retry_delay( 'activitypub_create_post_outbox_items' ) );
	}

	/**
	 * Test custom batch size cannot be zero.
	 *
	 * @covers \Activitypub\Options::register_settings
	 */
	public function test_custom_batch_size_minimum() {
		Options::register_settings();

		\update_option( 'activitypub_custom_batch_size', 0 );
		$this->assertGreaterThanOrEqual( 1, \get_option( 'activitypub_custom_batch_size' ) );
	}

	/**
	 * Test custom batch size is capped at 500.
	 *
	 * @covers \Activitypub\Options::register_settings
	 */
	public function test_custom_batch_size_maximum() {
		Options::register_settings();

		\update_option( 'activitypub_custom_batch_size', 100000 );
		$this->assertEquals( 500, \get_option( 'activitypub_custom_batch_size' ) );
	}

	/**
	 * Test custom batch pause is capped at 3600.
	 *
	 * @covers \Activitypub\Options::register_settings
	 */
	public function test_custom_batch_pause_maximum() {
		Options::register_settings();

		\update_option( 'activitypub_custom_batch_pause', 99999 );
		$this->assertEquals( 3600, \get_option( 'activitypub_custom_batch_pause' ) );
	}

	/**
	 * Test custom batch pause normalizes negative input.
	 *
	 * @covers \Activitypub\Options::register_settings
	 */
	public function test_custom_batch_pause_negative_is_normalized() {
		Options::register_settings();

		\update_option( 'activitypub_custom_batch_pause', -120 );
		$this->assertEquals( 120, \get_option( 'activitypub_custom_batch_pause' ) );
	}

	/**
	 * Test distribution mode sanitizes invalid values.
	 *
	 * @covers \Activitypub\Options::register_settings
	 */
	public function test_distribution_mode_sanitizes_invalid() {
		Options::register_settings();

		\update_option( 'activitypub_distribution_mode', 'turbo' );
		$this->assertEquals( 'default', \get_option( 'activitypub_distribution_mode' ) );
	}

	/**
	 * Test default mode does not override filter values.
	 *
	 * @covers \Activitypub\Options::filter_dispatcher_batch_size
	 */
	public function test_default_mode_preserves_filter_value() {
		\update_option( 'activitypub_distribution_mode', 'default' );

		$result = Options::filter_dispatcher_batch_size( 42 );
		$this->assertEquals( 42, $result );
	}

	/**
	 * Test non-default mode overrides filter values.
	 *
	 * @covers \Activitypub\Options::filter_dispatcher_batch_size
	 */
	public function test_non_default_mode_overrides_filter_value() {
		\update_option( 'activitypub_distribution_mode', 'eco' );

		$result = Options::filter_dispatcher_batch_size( 42 );
		$this->assertEquals( 20, $result );
	}

	/**
	 * Test constant-lock returns a valid preset.
	 *
	 * @covers \Activitypub\Options::resolve_distribution_mode
	 */
	public function test_resolve_distribution_mode_locks_preset() {
		$this->assertEquals( 'balanced', Options::resolve_distribution_mode( 'eco', 'balanced' ) );
		$this->assertEquals( 'eco', Options::resolve_distribution_mode( false, 'eco' ) );
		$this->assertEquals( 'default', Options::resolve_distribution_mode( 'eco', 'default' ) );
	}

	/**
	 * Test constant-lock falls back to default for the 'custom' mode.
	 *
	 * 'custom' is deliberately excluded because its batch size and pause
	 * values are still read from the database, which would defeat the
	 * purpose of locking the mode via wp-config.php.
	 *
	 * @covers \Activitypub\Options::resolve_distribution_mode
	 */
	public function test_resolve_distribution_mode_rejects_custom() {
		$this->setExpectedIncorrectUsage( 'Activitypub\Options::resolve_distribution_mode' );

		$this->assertEquals( 'default', Options::resolve_distribution_mode( 'eco', 'custom' ) );
	}

	/**
	 * Test constant-lock falls back to default for bogus values.
	 *
	 * @covers \Activitypub\Options::resolve_distribution_mode
	 */
	public function test_resolve_distribution_mode_rejects_invalid_values() {
		$this->setExpectedIncorrectUsage( 'Activitypub\Options::resolve_distribution_mode' );

		$this->assertEquals( 'default', Options::resolve_distribution_mode( 'eco', 'turbo' ) );
		$this->assertEquals( 'default', Options::resolve_distribution_mode( false, '' ) );
	}

	/**
	 * Test constant-lock is a no-op when unset.
	 *
	 * @covers \Activitypub\Options::resolve_distribution_mode
	 */
	public function test_resolve_distribution_mode_noop_when_unset() {
		$this->assertEquals( 'eco', Options::resolve_distribution_mode( 'eco', false ) );
		$this->assertFalse( Options::resolve_distribution_mode( false, false ) );
	}

	/**
	 * Test default quote policy option sanitizes invalid values.
	 *
	 * @covers \Activitypub\Options::register_settings
	 */
	public function test_default_quote_policy_sanitizes_invalid_values() {
		// Register settings to enable sanitize callback (admin_init/rest_api_init don't fire in tests).
		\Activitypub\Options::register_settings();

		// Test invalid value gets sanitized to default.
		\update_option( 'activitypub_default_quote_policy', 'invalid_value' );
		$this->assertEquals( ACTIVITYPUB_INTERACTION_POLICY_ANYONE, \get_option( 'activitypub_default_quote_policy' ) );

		// Test empty value gets sanitized to default.
		\update_option( 'activitypub_default_quote_policy', '' );
		$this->assertEquals( ACTIVITYPUB_INTERACTION_POLICY_ANYONE, \get_option( 'activitypub_default_quote_policy' ) );
	}

	/**
	 * Test purge days returns default when option is not set.
	 *
	 * @covers \Activitypub\Options::sanitize_purge_days
	 */
	public function test_purge_days_returns_default_when_unset() {
		\delete_option( 'activitypub_outbox_purge_days' );

		$this->assertEquals(
			ACTIVITYPUB_OUTBOX_PURGE_DAYS,
			\get_option( 'activitypub_outbox_purge_days', ACTIVITYPUB_OUTBOX_PURGE_DAYS )
		);
	}

	/**
	 * Test purge days does not allow zero.
	 *
	 * @covers \Activitypub\Options::sanitize_purge_days
	 */
	public function test_purge_days_does_not_allow_zero() {
		Options::register_settings();

		\update_option( 'activitypub_outbox_purge_days', 0 );
		$this->assertGreaterThanOrEqual( 1, \get_option( 'activitypub_outbox_purge_days' ) );
	}

	/**
	 * Test purge days returns default when stored value is empty string.
	 *
	 * @covers \Activitypub\Options::sanitize_purge_days
	 */
	public function test_purge_days_returns_default_for_empty_string() {
		\update_option( 'activitypub_outbox_purge_days', '' );

		$this->assertEquals(
			ACTIVITYPUB_OUTBOX_PURGE_DAYS,
			\get_option( 'activitypub_outbox_purge_days' )
		);
	}

	/**
	 * Test purge days sanitizes negative values.
	 *
	 * @covers \Activitypub\Options::sanitize_purge_days
	 */
	public function test_purge_days_sanitizes_negative() {
		Options::register_settings();

		\update_option( 'activitypub_outbox_purge_days', -5 );
		$this->assertGreaterThanOrEqual( 1, \get_option( 'activitypub_outbox_purge_days' ) );
	}
}
