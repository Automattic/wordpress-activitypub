<?php
/**
 * Test Following abilities.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Ability;

use Activitypub\Ability\Following;

/**
 * Test Following abilities.
 *
 * @coversDefaultClass \Activitypub\Ability\Following
 */
class Test_Following extends \WP_UnitTestCase {

	/**
	 * Reset the following feature flag after each test.
	 */
	public function tear_down() {
		\delete_option( 'activitypub_following_ui' );
		parent::tear_down();
	}

	/**
	 * The permission callback requires the activitypub capability.
	 *
	 * @covers ::permission_callback
	 */
	public function test_permission_callback_requires_capability() {
		\wp_set_current_user( 0 );
		$this->assertFalse( Following::permission_callback() );

		$user = self::factory()->user->create_and_get();
		$user->add_cap( 'activitypub' );
		\wp_set_current_user( $user->ID );
		$this->assertTrue( Following::permission_callback() );
	}

	/**
	 * An invalid user ID is rejected.
	 *
	 * @covers ::get_following
	 */
	public function test_get_following_rejects_invalid_user_id() {
		$result = Following::get_following( array( 'user_id' => 0 ) );

		$this->assertWPError( $result );
		$this->assertSame( 'activitypub_invalid_user_id', $result->get_error_code() );
	}

	/**
	 * A non-admin cannot read another user's following list.
	 *
	 * @covers ::get_following
	 */
	public function test_get_following_forbids_other_user() {
		$current = self::factory()->user->create();
		$other   = self::factory()->user->create();
		\wp_set_current_user( $current );

		$result = Following::get_following( array( 'user_id' => $other ) );

		$this->assertWPError( $result );
		$this->assertSame( 'activitypub_forbidden', $result->get_error_code() );
	}

	/**
	 * A user can read their own following list.
	 *
	 * @covers ::get_following
	 */
	public function test_get_following_allows_own_user() {
		$user = self::factory()->user->create();
		\wp_set_current_user( $user );

		$result = Following::get_following( array( 'user_id' => $user ) );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'following', $result );
		$this->assertArrayHasKey( 'total', $result );
	}

	/**
	 * Follow is rejected when the following feature is disabled.
	 *
	 * @covers ::follow
	 */
	public function test_follow_requires_following_feature() {
		\update_option( 'activitypub_following_ui', '0' );

		$user = self::factory()->user->create( array( 'role' => 'administrator' ) );
		\wp_set_current_user( $user );

		$result = Following::follow( array( 'actor' => 'alice@example.com' ) );

		$this->assertWPError( $result );
		$this->assertSame( 'activitypub_following_disabled', $result->get_error_code() );
	}

	/**
	 * Unfollow is rejected when the following feature is disabled.
	 *
	 * @covers ::unfollow
	 */
	public function test_unfollow_requires_following_feature() {
		\update_option( 'activitypub_following_ui', '0' );

		$user = self::factory()->user->create( array( 'role' => 'administrator' ) );
		\wp_set_current_user( $user );

		$result = Following::unfollow( array( 'actor' => 'alice@example.com' ) );

		$this->assertWPError( $result );
		$this->assertSame( 'activitypub_following_disabled', $result->get_error_code() );
	}

	/**
	 * A non-admin cannot follow on behalf of another user.
	 *
	 * @covers ::follow
	 */
	public function test_follow_forbids_acting_for_other_user() {
		\update_option( 'activitypub_following_ui', '1' );

		$current = self::factory()->user->create();
		$other   = self::factory()->user->create();
		\wp_set_current_user( $current );

		$result = Following::follow(
			array(
				'actor'   => 'alice@example.com',
				'user_id' => $other,
			)
		);

		$this->assertWPError( $result );
		$this->assertSame( 'activitypub_forbidden', $result->get_error_code() );
	}
}
