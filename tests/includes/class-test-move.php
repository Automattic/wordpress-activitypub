<?php
/**
 * Test file for Activitypub Move.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

use Activitypub\Model\User;

/**
 * Test class for Activitypub Move.
 *
 * @coversDefaultClass \Activitypub\Move
 */
class Test_Move extends \WP_UnitTestCase {

	/**
	 * Test user ID.
	 *
	 * @var int
	 */
	protected static $user_id;

	/**
	 * Create fake data before tests run.
	 */
	public static function set_up_before_class() {
		self::$user_id = self::factory()->user->create( array( 'role' => 'author' ) );
	}

	/**
	 * Clean up after tests.
	 */
	public static function tear_down_after_class() {
		wp_delete_user( self::$user_id );
	}

	/**
	 * Test the extend_actor_profiles.
	 *
	 * @covers ::extend_actor_profiles
	 */
	public function test_extend_actor_profiles() {
		$actor = array(
			'type' => 'Person',
			'id'   => 'https://example.com/user/1',
		);

		// Invalid user.
		$this->assertSameSets( $actor, \Activitypub\Move::extend_actor_profiles( $actor, 1, get_userdata( self::$user_id ) ) );

		// No move_to or also_known_as.
		$actor = User::from_wp_user( self::$user_id )->to_array();

		$this->assertArrayNotHasKey( 'movedTo', $actor );
		$this->assertArrayNotHasKey( 'alsoKnownAs', $actor );

		// Move_to and also_known_as.
		update_user_option( self::$user_id, 'activitypub_move_to', 'https://example.com/user/2' );
		update_user_option( self::$user_id, 'activitypub_also_known_as', 'https://example.com/user/3' );

		$actor = User::from_wp_user( self::$user_id )->to_array();

		$this->assertArrayHasKey( 'movedTo', $actor );
		$this->assertArrayHasKey( 'alsoKnownAs', $actor );
	}
}
