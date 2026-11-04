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
	 * The ID of a user enabled for ActivityPub.
	 *
	 * @var int
	 */
	private static $user_id;

	/**
	 * The host of the site, as it shows up in handles.
	 *
	 * @var string
	 */
	private static $host;

	/**
	 * Create fixtures.
	 *
	 * @param \WP_UnitTest_Factory $factory Factory.
	 */
	public static function wpSetUpBeforeClass( $factory ) {
		self::$user_id = $factory->user->create( array( 'role' => 'administrator' ) );
		self::$host    = \wp_parse_url( \home_url(), \PHP_URL_HOST );

		\get_userdata( self::$user_id )->add_cap( 'activitypub' );
	}

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
		if ( \PHP_VERSION_ID < 80100 ) {
			$get_completed_steps_count->setAccessible( true );
		}

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
		if ( \PHP_VERSION_ID < 80100 ) {
			$get_total_steps_count->setAccessible( true );
		}

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
		if ( \PHP_VERSION_ID < 80100 ) {
			$get_next_incomplete_step->setAccessible( true );
		}

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
	 * Test get_welcome_handles in blog-only mode.
	 *
	 * @throws \ReflectionException Reflection exception.
	 * @covers ::get_welcome_handles
	 */
	public function test_get_welcome_handles_in_blog_mode() {
		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_BLOG_MODE );
		\update_option( 'activitypub_blog_identifier', 'testblog' );
		\wp_set_current_user( self::$user_id );

		$handles = $this->get_welcome_handles();

		$this->assertCount( 1, $handles );
		$this->assertSame( 'activitypub-welcome-handle-blog', $handles[0]['id'] );
		$this->assertSame( '@testblog@' . self::$host, $handles[0]['handle'] );

		\delete_option( 'activitypub_blog_identifier' );
		\delete_option( 'activitypub_actor_mode' );
	}

	/**
	 * Test get_welcome_handles in author-only mode.
	 *
	 * @throws \ReflectionException Reflection exception.
	 * @covers ::get_welcome_handles
	 */
	public function test_get_welcome_handles_in_actor_mode() {
		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_MODE );
		\wp_set_current_user( self::$user_id );

		$handles = $this->get_welcome_handles();

		$this->assertCount( 1, $handles );
		$this->assertSame( 'activitypub-welcome-handle-user', $handles[0]['id'] );
		$this->assertSame( '@' . \get_userdata( self::$user_id )->user_login . '@' . self::$host, $handles[0]['handle'] );

		\delete_option( 'activitypub_actor_mode' );
	}

	/**
	 * Test get_welcome_handles with both profile types enabled.
	 *
	 * @throws \ReflectionException Reflection exception.
	 * @covers ::get_welcome_handles
	 */
	public function test_get_welcome_handles_in_actor_and_blog_mode() {
		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_AND_BLOG_MODE );
		\update_option( 'activitypub_blog_identifier', 'testblog' );
		\wp_set_current_user( self::$user_id );

		$handles = $this->get_welcome_handles();

		$this->assertCount( 2, $handles );
		$this->assertSame( '@testblog@' . self::$host, $handles[0]['handle'] );
		$this->assertSame( '@' . \get_userdata( self::$user_id )->user_login . '@' . self::$host, $handles[1]['handle'] );

		// Both buttons are on the same page, so screen readers need to tell them apart.
		$this->assertNotSame( $handles[0]['copy_label'], $handles[1]['copy_label'] );
		$this->assertStringContainsString( $handles[0]['handle'], $handles[0]['copy_label'] );
		$this->assertStringContainsString( $handles[1]['handle'], $handles[1]['copy_label'] );

		\delete_option( 'activitypub_blog_identifier' );
		\delete_option( 'activitypub_actor_mode' );
	}

	/**
	 * Test get_welcome_handles for a user without ActivityPub access.
	 *
	 * @throws \ReflectionException Reflection exception.
	 * @covers ::get_welcome_handles
	 */
	public function test_get_welcome_handles_without_an_enabled_actor() {
		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_MODE );
		\wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$this->assertSame( array(), $this->get_welcome_handles() );

		\delete_option( 'activitypub_actor_mode' );
	}

	/**
	 * Test that the welcome header renders the handle and a copy button.
	 *
	 * @covers ::render_welcome_header_section
	 */
	public function test_render_welcome_header_section_renders_the_handle() {
		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_BLOG_MODE );
		\update_option( 'activitypub_blog_identifier', 'testblog' );
		\wp_set_current_user( self::$user_id );

		\ob_start();
		Welcome_Fields::render_welcome_header_section();
		$output = \ob_get_clean();

		$handle = '@testblog@' . self::$host;

		$this->assertStringContainsString( 'value="' . \esc_attr( $handle ) . '"', $output );
		$this->assertStringContainsString( 'data-clipboard-text="' . \esc_attr( $handle ) . '"', $output );
		$this->assertStringContainsString( 'activitypub-copy-to-clipboard', $output );

		// The label the button goes back to after showing the success message.
		$this->assertStringContainsString( 'data-copy-label="Copy"', $output );

		// A single handle gets the singular wording.
		$this->assertStringContainsString( 'This is your address in the Fediverse.', $output );
		$this->assertStringNotContainsString( 'These are your addresses', $output );

		\delete_option( 'activitypub_blog_identifier' );
		\delete_option( 'activitypub_actor_mode' );
	}

	/**
	 * Invoke the private get_welcome_handles method.
	 *
	 * @throws \ReflectionException Reflection exception.
	 *
	 * @return array[] List of handles.
	 */
	private function get_welcome_handles() {
		$method = new \ReflectionMethod( Welcome_Fields::class, 'get_welcome_handles' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		return $method->invoke( null ); // Null for static methods.
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
