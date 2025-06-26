<?php
/**
 * Unit tests for the Activitypub Following collection.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Collection;

use Activitypub\Collection\Actors;
use Activitypub\Collection\Following;

/**
 * Class Test_Following
 *
 * @coversDefaultClass \Activitypub\Collection\Following
 */
class Test_Following extends \WP_UnitTestCase {

	/**
	 * Set up the test environment.
	 */
	public function set_up() {
		parent::set_up();
		_delete_all_posts();
	}

	/**
	 * Test the accept() method with a valid follow request.
	 *
	 * @covers ::accept
	 */
	public function test_accept_valid_follow_request() {
		// Create a test post (remote actor).
		$post_id = self::factory()->post->create(
			array(
				'post_title'  => 'Test Remote Actor',
				'post_status' => 'publish',
				'post_type'   => Actors::POST_TYPE,
			)
		);

		$user_id = 1; // WordPress default user.

		// First, create a pending follow request.
		\add_post_meta( $post_id, Following::PENDING_META_KEY, $user_id );

		\clean_post_cache( $post_id );

		// Verify the pending request exists.
		$pending_followers = \get_post_meta( $post_id, Following::PENDING_META_KEY, false );
		$this->assertContains( (string) $user_id, $pending_followers );

		// Accept the follow request.
		$result = Following::accept( $post_id, $user_id );

		// Verify the result is a WP_Post object.
		$this->assertInstanceOf( '\WP_Post', $result );
		$this->assertEquals( $post_id, $result->ID );

		// Verify the user is now in the following list.
		$following = \get_post_meta( $post_id, Following::FOLLOWING_META_KEY, false );
		$this->assertContains( (string) $user_id, $following );

		// Verify the user is removed from pending list.
		$pending_followers = \get_post_meta( $post_id, Following::PENDING_META_KEY, false );
		$this->assertNotContains( (string) $user_id, $pending_followers );

		\wp_delete_post( $post_id );
	}

	/**
	 * Tests accept method with non-existent post.
	 *
	 * @covers ::accept
	 */
	public function test_accept_non_existent_post() {
		$non_existent_post_id = 99999;
		$user_id              = 1;

		$result = Following::accept( $non_existent_post_id, $user_id );

		$this->assertWPError( $result );
		$this->assertEquals( 'activitypub_remote_actor_not_found', $result->get_error_code() );
		$this->assertEquals( 'Remote actor not found', $result->get_error_message() );
	}

	/**
	 * Tests accept method with non-existent follow request.
	 *
	 * @covers ::accept
	 */
	public function test_accept_non_existent_follow_request() {
		// Create a test post (remote actor).
		$post_id = self::factory()->post->create(
			array(
				'post_title'  => 'Test Remote Actor',
				'post_status' => 'publish',
				'post_type'   => Actors::POST_TYPE,
			)
		);

		$user_id = 1;

		// Try to accept a follow request that doesn't exist.
		$result = Following::accept( $post_id, $user_id );

		$this->assertWPError( $result );
		$this->assertEquals( 'activitypub_following_not_found', $result->get_error_code() );
		$this->assertEquals( 'Follow request not found', $result->get_error_message() );

		\wp_delete_post( $post_id );
	}

	/**
	 * Tests accept method with empty pending followers array.
	 *
	 * @covers ::accept
	 */
	public function test_accept_empty_pending_followers() {
		// Create a test post (remote actor).
		$post_id = self::factory()->post->create(
			array(
				'post_title'  => 'Test Remote Actor',
				'post_status' => 'publish',
				'post_type'   => Actors::POST_TYPE,
			)
		);

		$user_id = 1;

		// Add an empty array to pending meta (simulating no pending followers).
		\add_post_meta( $post_id, Following::PENDING_META_KEY, array() );

		// Try to accept a follow request.
		$result = Following::accept( $post_id, $user_id );

		$this->assertWPError( $result );
		$this->assertEquals( 'activitypub_following_not_found', $result->get_error_code() );
		$this->assertEquals( 'Follow request not found', $result->get_error_message() );

		\wp_delete_post( $post_id );
	}

	/**
	 * Tests accept method with different user ID than pending request.
	 *
	 * @covers ::accept
	 */
	public function test_accept_different_user_id() {
		// Create a test post (remote actor).
		$post_id = self::factory()->post->create(
			array(
				'post_title'  => 'Test Remote Actor',
				'post_status' => 'publish',
				'post_type'   => Actors::POST_TYPE,
			)
		);

		$pending_user_id   = 1;
		$different_user_id = 2;

		// Add a pending follow request for user 1.
		\add_post_meta( $post_id, Following::PENDING_META_KEY, $pending_user_id );

		// Try to accept with user 2.
		$result = Following::accept( $post_id, $different_user_id );

		$this->assertWPError( $result );
		$this->assertEquals( 'activitypub_following_not_found', $result->get_error_code() );
		$this->assertEquals( 'Follow request not found', $result->get_error_message() );

		// Verify the original pending request is still there.
		$pending_followers = \get_post_meta( $post_id, Following::PENDING_META_KEY, false );
		$this->assertContains( (string) $pending_user_id, $pending_followers );

		\wp_delete_post( $post_id );
	}

	/**
	 * Tests accept method with WP_Post object instead of post ID.
	 *
	 * @covers ::accept
	 */
	public function test_accept_with_post_object() {
		// Create a test post (remote actor).
		$post_id = self::factory()->post->create(
			array(
				'post_title'  => 'Test Remote Actor',
				'post_status' => 'publish',
				'post_type'   => Actors::POST_TYPE,
			)
		);

		$post    = \get_post( $post_id );
		$user_id = 1;

		// Add a pending follow request.
		\add_post_meta( $post_id, Following::PENDING_META_KEY, $user_id );

		// Accept using the post object.
		$result = Following::accept( $post, $user_id );

		// Verify the result is a WP_Post object.
		$this->assertInstanceOf( '\WP_Post', $result );
		$this->assertEquals( $post_id, $result->ID );

		// Verify the user is now in the following list.
		$following = \get_post_meta( $post_id, Following::FOLLOWING_META_KEY, false );
		$this->assertContains( (string) $user_id, $following );

		\wp_delete_post( $post_id );
	}

	/**
	 * Tests accept method with multiple pending requests.
	 *
	 * @covers ::accept
	 */
	public function test_accept_with_multiple_pending_requests() {
		// Create a test post (remote actor).
		$post_id = self::factory()->post->create(
			array(
				'post_title'  => 'Test Remote Actor',
				'post_status' => 'publish',
				'post_type'   => Actors::POST_TYPE,
			)
		);

		$user_id_1 = 1;
		$user_id_2 = 2;

		// Add multiple pending follow requests.
		\add_post_meta( $post_id, Following::PENDING_META_KEY, $user_id_1 );
		\add_post_meta( $post_id, Following::PENDING_META_KEY, $user_id_2 );

		// Accept only user 1's request.
		$result = Following::accept( $post_id, $user_id_1 );

		// Verify the result is a WP_Post object.
		$this->assertInstanceOf( '\WP_Post', $result );

		// Verify user 1 is now in the following list.
		$following = \get_post_meta( $post_id, Following::FOLLOWING_META_KEY, false );
		$this->assertContains( (string) $user_id_1, $following );

		// Verify user 1 is removed from pending list.
		$pending_followers = \get_post_meta( $post_id, Following::PENDING_META_KEY, false );
		$this->assertNotContains( (string) $user_id_1, $pending_followers );

		// Verify user 2 is still in pending list.
		$this->assertContains( (string) $user_id_2, $pending_followers );

		\wp_delete_post( $post_id );
	}

	/**
	 * Tests accept method with non-array pending meta value.
	 *
	 * @covers ::accept
	 */
	public function test_accept_with_non_array_pending_meta() {
		// Create a test post (remote actor).
		$post_id = self::factory()->post->create(
			array(
				'post_title'  => 'Test Remote Actor',
				'post_status' => 'publish',
				'post_type'   => Actors::POST_TYPE,
			)
		);

		$user_id = 1;

		// Add a non-array value to pending meta (simulating corrupted data).
		\update_post_meta( $post_id, Following::PENDING_META_KEY, 'invalid_value' );

		// Try to accept a follow request.
		$result = Following::accept( $post_id, $user_id );

		$this->assertWPError( $result );
		$this->assertEquals( 'activitypub_following_not_found', $result->get_error_code() );
		$this->assertEquals( 'Follow request not found', $result->get_error_message() );

		\wp_delete_post( $post_id );
	}
}
