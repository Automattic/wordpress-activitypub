<?php
/**
 * Test file for Settings.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

use Activitypub\WP_Admin\Settings;

/**
 * Test class for Settings.
 *
 * @coversDefaultClass \Activitypub\WP_Admin\Settings
 */
class Test_Settings extends \WP_UnitTestCase {
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
	}

	/**
	 * Tear down.
	 */
	public function tear_down() {
		unset( $_GET['welcome'], $_GET['help-tab'], $_GET['_wpnonce'], $_REQUEST['_wpnonce'] );
		\delete_option( 'activitypub_checklist_fediverse_intro_visited' );
		\delete_option( 'activitypub_checklist_blocks_visited' );

		parent::tear_down();
	}

	/**
	 * Hiding the Welcome tab through the query arg requires a nonce.
	 *
	 * @covers ::handle_welcome_query_arg
	 */
	public function test_welcome_query_arg_requires_nonce() {
		\update_user_meta( self::$user_id, 'activitypub_show_welcome_tab', 1 );

		$_GET['welcome'] = '0';

		$died = false;
		try {
			Settings::handle_welcome_query_arg();
		} catch ( \WPDieException $e ) {
			$died = true;
		}

		$this->assertTrue( $died );
		$this->assertSame( '1', \get_user_meta( self::$user_id, 'activitypub_show_welcome_tab', true ) );
	}

	/**
	 * Opening a help tab from the Welcome page marks its checklist step only with a nonce.
	 *
	 * @covers ::settings_page
	 */
	public function test_help_tab_step_is_marked_only_with_nonce() {
		\update_user_meta( self::$user_id, 'activitypub_show_welcome_tab', 1 );

		$_GET['help-tab'] = 'getting-started';

		\ob_start();
		Settings::settings_page();
		\ob_end_clean();

		$this->assertFalse( \get_option( 'activitypub_checklist_fediverse_intro_visited' ) );

		$_GET['_wpnonce'] = \wp_create_nonce( 'activitypub_help_tab' );

		\ob_start();
		Settings::settings_page();
		\ob_end_clean();

		$this->assertSame( '1', \get_option( 'activitypub_checklist_fediverse_intro_visited' ) );
	}
}
