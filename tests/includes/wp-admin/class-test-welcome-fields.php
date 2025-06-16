<?php
/**
 * Test file for Welcome_Fields.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

use Activitypub\WP_Admin\Welcome_Fields;

/**
 * Test class for Welcome_Fields.
 *
 * @coversDefaultClass \Activitypub\WP_Admin\Welcome_Fields
 */
class Test_Welcome_Fields extends \WP_UnitTestCase {
	/**
	 * Set up.
	 */
	public function set_up() {
		parent::set_up();

		Welcome_Fields::register_welcome_fields();
	}

	/**
	 * Test get_completed_steps_count.
	 *
	 * @throws \ReflectionException Reflection exception.
	 * @covers ::get_completed_steps_count
	 */
	public function test_get_completed_steps_count() {
		$get_completed_steps_count = new \ReflectionMethod( Welcome_Fields::class, 'get_completed_steps_count' );
		$get_completed_steps_count->setAccessible( true );

		$completed = 1; // Plugin is already installed.

		$this->assertSame( $completed, $get_completed_steps_count->invoke( null ) ); // Null for static methods.

		// Completed step.
		\update_option( 'activitypub_checklist_settings_visited', '1' );
		$this->assertSame( $completed + 1, $get_completed_steps_count->invoke( null ) );

		// Remove the completed step.
		\remove_action( 'activitypub_onboarding_steps', array( Welcome_Fields::class, 'render_step_profile_mode' ), 40 );

		$this->assertSame( $completed, $get_completed_steps_count->invoke( null ) );

		// Restore.
		\add_action( 'activitypub_onboarding_steps', array( Welcome_Fields::class, 'render_step_profile_mode' ), 40 );
		\delete_option( 'activitypub_checklist_settings_visited' );
	}

	/**
	 * Test get_total_steps_count.
	 *
	 * @throws \ReflectionException Reflection exception.
	 * @covers ::get_total_steps_count
	 */
	public function test_get_total_steps_count() {
		$get_total_steps_count = new \ReflectionMethod( Welcome_Fields::class, 'get_total_steps_count' );
		$get_total_steps_count->setAccessible( true );

		$steps = 6;

		$this->assertSame( $steps, $get_total_steps_count->invoke( null ) ); // Null for static methods.

		// Remove a step.
		\remove_action( 'activitypub_onboarding_steps', array( Welcome_Fields::class, 'render_step_site_health' ), 20 );

		$this->assertSame( $steps - 1, $get_total_steps_count->invoke( null ) );

		// Restore.
		\add_action( 'activitypub_onboarding_steps', array( Welcome_Fields::class, 'render_step_site_health' ), 20 );
	}

	/**
	 * Test get_next_incomplete_step.
	 *
	 * @throws \ReflectionException Reflection exception.
	 * @covers ::get_next_incomplete_step
	 */
	public function test_get_next_incomplete_step() {
		$get_next_incomplete_step = new \ReflectionMethod( Welcome_Fields::class, 'get_next_incomplete_step' );
		$get_next_incomplete_step->setAccessible( true );

		$this->assertSame( 'site_health', $get_next_incomplete_step->invoke( null ) ); // Null for static methods.

		// Complete steps.
		\add_filter( 'pre_option_activitypub_checklist_health_check_issues', array( $this, '__return_option_zero' ) );
		\update_option( 'activitypub_checklist_fediverse_intro_visited', '1' );
		$this->assertSame( 'profile_mode', $get_next_incomplete_step->invoke( null ) );

		// Remove the next step.
		\remove_action( 'activitypub_onboarding_steps', array( Welcome_Fields::class, 'render_step_profile_mode' ), 40 );

		$this->assertSame( 'profile_setup', $get_next_incomplete_step->invoke( null ) );

		// Restore.
		\add_action( 'activitypub_onboarding_steps', array( Welcome_Fields::class, 'render_step_profile_mode' ), 40 );
		\remove_filter( 'pre_option_activitypub_checklist_health_check_issues', array( $this, '__return_option_zero' ) );
		\delete_option( 'activitypub_checklist_fediverse_intro_visited' );
	}

	/**
	 * Return zero as a string.
	 *
	 * For when @see __return_zero() doesn't work because of how WordPress handles option values.
	 *
	 * @return string
	 */
	public function __return_option_zero() { // phpcs:ignore PHPCompatibility.FunctionNameRestrictions.ReservedFunctionNames.MethodDoubleUnderscore
		return '0';
	}
}
