<?php
/**
 * Test file for Screen_Options.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

use Activitypub\WP_Admin\Screen_Options;

/**
 * Test class for Screen_Options.
 *
 * @coversDefaultClass \Activitypub\WP_Admin\Screen_Options
 */
class Test_Screen_Options extends \WP_UnitTestCase {
	/**
	 * The ID of an administrator.
	 *
	 * @var int
	 */
	private static $user_id;

	/**
	 * Create fixtures.
	 *
	 * @param \WP_UnitTest_Factory $factory Factory.
	 */
	public static function wpSetUpBeforeClass( $factory ) {
		self::$user_id = $factory->user->create( array( 'role' => 'administrator' ) );
	}

	/**
	 * Set up.
	 */
	public function set_up() {
		parent::set_up();

		\wp_set_current_user( self::$user_id );
		\update_user_meta( self::$user_id, 'activitypub_show_welcome_tab', 1 );
	}

	/**
	 * Tear down.
	 */
	public function tear_down() {
		unset( $_POST['activitypub_show_welcome_tab'], $_POST['screenoptionnonce'] );

		parent::tear_down();
	}

	/**
	 * A screen option is not saved when the request carries no nonce.
	 *
	 * @covers ::add_screen_option
	 */
	public function test_screen_option_is_not_saved_without_nonce() {
		$_POST['activitypub_show_welcome_tab'] = '0';

		$output = Screen_Options::add_screen_option( '', (object) array( 'id' => 'settings_page_activitypub' ) );

		$this->assertStringContainsString( 'activitypub_show_welcome_tab', $output );
		$this->assertSame( '1', \get_user_meta( self::$user_id, 'activitypub_show_welcome_tab', true ) );
	}

	/**
	 * A screen option is not saved when the nonce is wrong.
	 *
	 * @covers ::add_screen_option
	 */
	public function test_screen_option_is_not_saved_with_invalid_nonce() {
		$_POST['activitypub_show_welcome_tab'] = '0';
		$_POST['screenoptionnonce']            = 'invalid';

		Screen_Options::add_screen_option( '', (object) array( 'id' => 'settings_page_activitypub' ) );

		$this->assertSame( '1', \get_user_meta( self::$user_id, 'activitypub_show_welcome_tab', true ) );
	}

	/**
	 * A screen option is saved when the request carries the screen options nonce.
	 *
	 * @covers ::add_screen_option
	 */
	public function test_screen_option_is_saved_with_nonce() {
		$_POST['activitypub_show_welcome_tab'] = '0';
		$_POST['screenoptionnonce']            = \wp_create_nonce( 'screen-options-nonce' );

		Screen_Options::add_screen_option( '', (object) array( 'id' => 'settings_page_activitypub' ) );

		$this->assertSame( '0', \get_user_meta( self::$user_id, 'activitypub_show_welcome_tab', true ) );
	}
}
