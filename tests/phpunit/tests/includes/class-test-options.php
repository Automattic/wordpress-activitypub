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
}
