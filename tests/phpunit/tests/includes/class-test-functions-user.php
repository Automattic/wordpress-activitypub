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

	/**
	 * Test get_active_users counts users who only comment.
	 *
	 * @covers \Activitypub\get_active_users
	 */
	public function test_get_active_users_with_comments() {
		\delete_transient( 'monthly_active_users_1' );
		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_AND_BLOG_MODE );

		// Create a user with activitypub capability but no posts.
		$commenter = self::factory()->user->create_and_get();
		$commenter->add_cap( 'activitypub' );

		// Create a post by another user for the comment to attach to.
		$post_id = self::factory()->post->create();

		// Create an approved comment by the user.
		self::factory()->comment->create(
			array(
				'comment_post_ID'  => $post_id,
				'user_id'          => $commenter->ID,
				'comment_approved' => '1',
			)
		);

		\delete_transient( 'monthly_active_users_1' );

		$active_users = \Activitypub\get_active_users( 1 );
		$this->assertGreaterThanOrEqual( 1, $active_users, 'Users who only comment should be counted as active.' );

		\delete_transient( 'monthly_active_users_1' );
	}

	/**
	 * Test get_active_users counts custom post types with ActivityPub support.
	 *
	 * @covers \Activitypub\get_active_users
	 */
	public function test_get_active_users_with_custom_post_types() {
		\delete_transient( 'monthly_active_users_1' );
		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_AND_BLOG_MODE );

		// Register a custom post type with ActivityPub support.
		\register_post_type(
			'ap_test_cpt',
			array(
				'public'   => true,
				'supports' => array( 'title', 'editor', 'activitypub' ),
			)
		);

		// Ensure cleanup even if assertion fails.
		$cleanup = function () {
			\delete_transient( 'monthly_active_users_1' );
			\unregister_post_type( 'ap_test_cpt' );
		};

		try {
			// Create a user with activitypub capability.
			$user = self::factory()->user->create_and_get();
			$user->add_cap( 'activitypub' );

			// Create a post of the custom type.
			self::factory()->post->create(
				array(
					'post_author' => $user->ID,
					'post_status' => 'publish',
					'post_type'   => 'ap_test_cpt',
				)
			);

			\delete_transient( 'monthly_active_users_1' );

			$active_users = \Activitypub\get_active_users( 1 );
			$this->assertGreaterThanOrEqual( 1, $active_users, 'Users publishing custom post types with ActivityPub support should be counted as active.' );
		} finally {
			$cleanup();
		}
	}
}
