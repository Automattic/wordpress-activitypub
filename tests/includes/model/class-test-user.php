<?php
/**
 * Test file for Activitypub User.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Model;

/**
 * Test class for Activitypub User.
 *
 * @coversDefaultClass \Activitypub\Model\User
 */
class Test_User extends \WP_UnitTestCase {

	/**
	 * User object.
	 *
	 * @var \WP_User
	 */
	protected static $user;

	/**
	 * Set up before class.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		self::$user = self::factory()->user->create_and_get(
			array(
				'user_email' => 'editor@example.com',
				'first_name' => 'Max',
				'last_name'  => 'Mustermann',
				'user_login' => 'editor',
				'user_pass'  => 'editor',
				'role'       => 'editor',
			)
		);
	}

	/**
	 * Tear down after class.
	 */
	public static function tear_down_after_class() {
		wp_delete_user( self::$user->ID );

		parent::tear_down_after_class();
	}

	/**
	 * Test the activitypub capability.
	 */
	public function test_activitypub_cap() {
		$this->assertTrue( self::$user->has_cap( 'activitypub' ) );
	}
}
