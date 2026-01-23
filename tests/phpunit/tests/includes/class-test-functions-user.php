<?php
/**
 * Test file for User Functions.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

/**
 * Test class for User Functions.
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

	/**
	 * Test get_total_users function.
	 *
	 * @covers \Activitypub\get_total_users
	 */
	public function test_get_total_users() {
		// Create users with activitypub capability.
		$user1 = self::factory()->user->create_and_get();
		$user1->add_cap( 'activitypub' );

		$user2 = self::factory()->user->create_and_get();
		$user2->add_cap( 'activitypub' );

		// Ensure we're not in single user mode.
		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_AND_BLOG_MODE );

		$total = \Activitypub\get_total_users();
		$this->assertGreaterThanOrEqual( 2, $total );

		// Test single user mode returns 1.
		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_BLOG_MODE );
		$this->assertEquals( 1, \Activitypub\get_total_users() );
	}

	/**
	 * Test get_active_users function.
	 *
	 * @covers \Activitypub\get_active_users
	 */
	public function test_get_active_users() {
		// Delete transients to ensure fresh count.
		\delete_transient( 'monthly_active_users_1' );
		\delete_transient( 'monthly_active_users_6' );

		// Set actor mode to ensure get_total_users() returns correct count.
		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_AND_BLOG_MODE );

		// Create a user with activitypub capability and a recent post.
		$user = self::factory()->user->create_and_get();
		$user->add_cap( 'activitypub' );

		self::factory()->post->create(
			array(
				'post_author' => $user->ID,
				'post_status' => 'publish',
				'post_type'   => 'post',
			)
		);

		// Clear transient again after creating the post.
		\delete_transient( 'monthly_active_users_1' );

		$active_users = \Activitypub\get_active_users( 1 );
		$this->assertGreaterThanOrEqual( 1, $active_users );

		// Test with different duration.
		\delete_transient( 'monthly_active_users_6' );
		$active_users_6_months = \Activitypub\get_active_users( 6 );
		$this->assertGreaterThanOrEqual( 1, $active_users_6_months );

		// Clean up.
		\delete_transient( 'monthly_active_users_1' );
		\delete_transient( 'monthly_active_users_6' );
	}
}
