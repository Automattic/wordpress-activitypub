<?php
class Test_Admin extends WP_UnitTestCase {
	public function test_no_permalink_structure_has_errors() {
		\add_option( 'permalink_structure', '' );
		\do_action( 'init' );
		\do_action( 'admin_notices' );
		$this->expectOutputRegex( "/notice-error/" );

		\delete_option( 'permalink_structure' );
	}

	public function test_has_permalink_structure_no_errors() {
		\add_option( 'permalink_structure', '/archives/%post_id%' );
		\do_action( 'init' );
		\do_action( 'admin_notices' );
		$this->expectOutputRegex( "/^((?!notice-error).)*$/s" );

		\delete_option( 'permalink_structure' );
	}
}
