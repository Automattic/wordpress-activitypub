<?php
/**
 * Test file for Activitypub Options class.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

use Activitypub\Options;

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
}
