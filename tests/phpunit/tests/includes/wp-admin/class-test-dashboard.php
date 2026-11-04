<?php
/**
 * Test Dashboard class.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\WP_Admin;

use Activitypub\WP_Admin\Dashboard;
use WP_UnitTestCase;

/**
 * Test Dashboard class.
 *
 * @coversDefaultClass \Activitypub\WP_Admin\Dashboard
 */
class Test_Dashboard extends WP_UnitTestCase {

	/**
	 * Author user, enabled for ActivityPub.
	 *
	 * @var int
	 */
	protected static $author_id;

	/**
	 * Administrator user.
	 *
	 * @var int
	 */
	protected static $admin_id;

	/**
	 * Set up fixtures.
	 *
	 * @param \WP_UnitTest_Factory $factory Helper that creates fake data.
	 */
	public static function wpSetUpBeforeClass( $factory ) {
		self::$author_id = $factory->user->create( array( 'role' => 'author' ) );
		self::$admin_id  = $factory->user->create( array( 'role' => 'administrator' ) );
	}

	/**
	 * Test that the check defaults to the current user when no ID is given.
	 *
	 * @covers ::user_can_see_stats
	 */
	public function test_user_can_see_stats_defaults_to_current_user() {
		\wp_set_current_user( self::$author_id );
		$this->assertTrue( Dashboard::user_can_see_stats(), 'An ActivityPub-enabled author sees the widget.' );

		\wp_set_current_user( 0 );
		$this->assertFalse( Dashboard::user_can_see_stats(), 'A logged out visitor does not see the widget.' );
	}

	/**
	 * Test that the check answers for the given user rather than the current one.
	 *
	 * @covers ::user_can_see_stats
	 */
	public function test_user_can_see_stats_for_a_given_user() {
		\wp_set_current_user( 0 );

		$this->assertTrue( Dashboard::user_can_see_stats( self::$author_id ), 'The author sees the widget regardless of who is logged in.' );
		$this->assertFalse( Dashboard::user_can_see_stats( 0 ), 'User 0 is nobody and sees nothing.' );
	}

	/**
	 * Test that an administrator sees the widget through the blog branch when only the
	 * blog actor is enabled.
	 *
	 * @covers ::user_can_see_stats
	 */
	public function test_user_can_see_stats_in_blog_mode() {
		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_BLOG_MODE );

		$this->assertTrue( Dashboard::user_can_see_stats( self::$admin_id ), 'An administrator sees the widget through the blog branch.' );
		$this->assertFalse( Dashboard::user_can_see_stats( self::$author_id ), 'An author has no blog access and no user actor in blog mode.' );
	}

	/**
	 * Test that a forced ActivityPub capability does not grant access while the user actor
	 * type is disabled.
	 *
	 * @covers ::user_can_see_stats
	 */
	public function test_user_can_see_stats_ignores_forced_capability_in_blog_mode() {
		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_BLOG_MODE );
		\add_filter( 'activitypub_user_can_activitypub', '__return_true' );

		$can_see = Dashboard::user_can_see_stats( self::$author_id );

		\remove_filter( 'activitypub_user_can_activitypub', '__return_true' );

		$this->assertFalse( $can_see, 'The actor type check runs after the capability filter.' );
	}

	/**
	 * Test that only the blog actor being disabled does not remove user access.
	 *
	 * @covers ::user_can_see_stats
	 */
	public function test_user_can_see_stats_in_actor_mode() {
		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_MODE );

		$this->assertTrue( Dashboard::user_can_see_stats( self::$author_id ), 'The author still sees the widget through the user branch.' );
	}
}
