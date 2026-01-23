<?php
/**
 * Test file for User Functions.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

/**
 * Test class for User Functions.
 *
 * @coversDefaultClass \Activitypub
 */
class Test_Functions_User extends \WP_UnitTestCase {

	/**
	 * Test get_user_id function.
	 *
	 * @covers \Activitypub\get_user_id
	 */
	public function test_get_user_id() {
		$this->assertFalse( \Activitypub\get_user_id( 90210 ) );

		$user = self::factory()->user->create_and_get();
		$user->add_cap( 'activitypub' );

		$this->assertIsString( \Activitypub\get_user_id( $user->ID ) );

		\add_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_MODE );

		$this->assertIsString( \Activitypub\get_user_id( $user->ID ) );

		$user->remove_cap( 'activitypub' );
		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_BLOG_MODE );
		$this->assertIsString( \Activitypub\get_user_id( $user->ID ) );

		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_MODE );
		$this->assertFalse( \Activitypub\get_user_id( $user->ID ) );
	}
}
