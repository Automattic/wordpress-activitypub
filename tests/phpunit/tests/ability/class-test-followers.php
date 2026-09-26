<?php
/**
 * Test Followers abilities.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Ability;

use Activitypub\Ability\Followers;

/**
 * Test Followers abilities.
 *
 * @coversDefaultClass \Activitypub\Ability\Followers
 */
class Test_Followers extends \WP_UnitTestCase {

	/**
	 * The permission callback requires the activitypub capability.
	 *
	 * @covers ::permission_callback
	 */
	public function test_permission_callback_requires_capability() {
		\wp_set_current_user( 0 );
		$this->assertFalse( Followers::permission_callback() );

		$user = self::factory()->user->create_and_get();
		$user->add_cap( 'activitypub' );
		\wp_set_current_user( $user->ID );
		$this->assertTrue( Followers::permission_callback() );
	}

	/**
	 * A user ID without an actor is rejected.
	 *
	 * @covers ::get_followers
	 */
	public function test_get_followers_rejects_invalid_user_id() {
		\wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$result = Followers::get_followers( array( 'user_id' => 99999 ) );

		$this->assertWPError( $result );
		$this->assertSame( 'activitypub_invalid_user_id', $result->get_error_code() );
	}

	/**
	 * The Blog actor is user ID `0` and has followers like any other actor.
	 *
	 * @covers ::get_followers
	 */
	public function test_get_followers_accepts_the_blog_actor() {
		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_AND_BLOG_MODE );
		\wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$result = Followers::get_followers( array( 'user_id' => 0 ) );

		\delete_option( 'activitypub_actor_mode' );

		$this->assertNotWPError( $result );
		$this->assertSame( array( 'followers', 'total' ), \array_keys( $result ) );
	}

	/**
	 * A non-admin cannot read another user's followers list.
	 *
	 * @covers ::get_followers
	 */
	public function test_get_followers_forbids_other_user() {
		$current = self::factory()->user->create();
		$other   = self::factory()->user->create();
		\wp_set_current_user( $current );

		$result = Followers::get_followers( array( 'user_id' => $other ) );

		$this->assertWPError( $result );
		$this->assertSame( 'activitypub_forbidden', $result->get_error_code() );
	}

	/**
	 * An administrator can read another user's followers list.
	 *
	 * @covers ::get_followers
	 */
	public function test_get_followers_allows_admin_for_other_user() {
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$other = self::factory()->user->create_and_get();
		$other->add_cap( 'activitypub' );
		\wp_set_current_user( $admin );

		$result = Followers::get_followers( array( 'user_id' => $other->ID ) );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'followers', $result );
		$this->assertArrayHasKey( 'total', $result );
	}
}
