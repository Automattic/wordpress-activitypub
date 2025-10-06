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
}
