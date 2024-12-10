<?php
/**
 * Test file for Admin.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

/**
 * Test class for Admin.
 *
 * @coversDefaultClass \Activitypub\Admin
 */
class Test_Admin extends \WP_UnitTestCase {

	/**
	 * Test the admin notice for missing permalink structure.
	 */
	public function test_no_permalink_structure_has_errors() {
		\add_option( 'permalink_structure', '' );
		\do_action( 'admin_notices' );
		$this->expectOutputRegex( '/notice-error/' );

		\delete_option( 'permalink_structure' );
	}

	/**
	 * Test there is no error notice when the permalink structure is set.
	 */
	public function test_has_permalink_structure_no_errors() {
		\add_option( 'permalink_structure', '/archives/%post_id%' );
		\do_action( 'admin_notices' );
		$this->expectOutputRegex( '/^((?!notice-error).)*$/s' );

		\delete_option( 'permalink_structure' );
	}
}
