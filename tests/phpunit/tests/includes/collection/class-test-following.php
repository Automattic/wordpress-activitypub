<?php
/**
 * Unit tests for the Activitypub Following collection.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Collection;

use Activitypub\Collection\Following;
use Activitypub\Collection\Remote_Actors;
use Activitypub\Handler\Accept;

use function Activitypub\follow;

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
				'post_type'   => Remote_Actors::POST_TYPE,
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
				'post_type'   => Remote_Actors::POST_TYPE,
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
				'post_type'   => Remote_Actors::POST_TYPE,
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
				'post_type'   => Remote_Actors::POST_TYPE,
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
				'post_type'   => Remote_Actors::POST_TYPE,
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
				'post_type'   => Remote_Actors::POST_TYPE,
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
				'post_type'   => Remote_Actors::POST_TYPE,
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

	/**
	 * Test unfollow removes user from following list.
	 *
	 * @covers ::unfollow
	 */
	public function test_unfollow_removes_user() {
		// Create a test post (remote actor).
		$post_id = self::factory()->post->create(
			array(
				'post_title'  => 'Test Remote Actor',
				'post_status' => 'publish',
				'post_type'   => Remote_Actors::POST_TYPE,
			)
		);

		$user_id = 1;

		// Use global follow() function to add a follow request.
		$remote_actor_url = \get_post( $post_id )->guid;
		\Activitypub\follow( $remote_actor_url, $user_id );
		\clean_post_cache( $post_id );

		// Verify user is in following list (pending or following).
		$following = \get_post_meta( $post_id, \Activitypub\Collection\Following::FOLLOWING_META_KEY, false );
		$pending   = \get_post_meta( $post_id, \Activitypub\Collection\Following::PENDING_META_KEY, false );
		$this->assertTrue( in_array( (string) $user_id, $following, true ) || in_array( (string) $user_id, $pending, true ) );

		// Remove following.
		$result = \Activitypub\Collection\Following::unfollow( $post_id, $user_id );

		\clean_post_cache( $post_id );

		// Should return WP_Post.
		$this->assertInstanceOf( '\WP_Post', $result );
		$this->assertEquals( $post_id, $result->ID );

		// User should no longer be in following list.
		$following = \get_post_meta( $post_id, \Activitypub\Collection\Following::FOLLOWING_META_KEY, false );
		$pending   = \get_post_meta( $post_id, \Activitypub\Collection\Following::PENDING_META_KEY, false );

		$this->assertNotContains( (string) $user_id, $following );
		$this->assertNotContains( (string) $user_id, $pending );

		\wp_delete_post( $post_id );
	}

	/**
	 * Tests unfollow method.
	 *
	 * @covers ::unfollow
	 */
	public function test_unfollow() {
		\add_filter( 'activitypub_pre_http_get_remote_object', array( $this, 'mock_remote_actor' ), 10, 2 );

		$user_ids = self::factory()->user->create_many( 3 );
		foreach ( $user_ids as $user_id ) {
			\get_user_by( 'id', $user_id )->add_cap( 'activitypub' );
		}

		$outbox_item_1 = \get_post( follow( 'https://example.com/actor/1', $user_ids[0] ) );
		$outbox_item_2 = \get_post( follow( 'https://example.com/actor/1', $user_ids[1] ) );
		$outbox_item_3 = \get_post( follow( 'https://example.com/actor/1', $user_ids[2] ) );
		$outbox_item_4 = \get_post( follow( 'https://example.com/actor/1', 0 ) );
		$outbox_item_5 = \get_post( follow( 'https://example.com/actor/1', -1 ) );

		\wp_publish_post( $outbox_item_1 );
		\wp_publish_post( $outbox_item_2 );
		\wp_publish_post( $outbox_item_3 );
		\wp_publish_post( $outbox_item_4 );
		\wp_publish_post( $outbox_item_5 );

		$accept_1 = array(
			'object' => array(
				'id'     => $outbox_item_1->guid,
				'object' => 'https://example.com/actor/1',
			),
		);
		$accept_2 = array(
			'object' => array(
				'id'     => $outbox_item_2->guid,
				'object' => 'https://example.com/actor/1',
			),
		);
		$accept_3 = array(
			'object' => array(
				'id'     => $outbox_item_3->guid,
				'object' => 'https://example.com/actor/1',
			),
		);
		$accept_4 = array(
			'object' => array(
				'id'     => $outbox_item_4->guid,
				'object' => 'https://example.com/actor/1',
			),
		);
		$accept_5 = array(
			'object' => array(
				'id'     => $outbox_item_5->guid,
				'object' => 'https://example.com/actor/1',
			),
		);

		Accept::handle_accept( $accept_1, $user_ids[0] );
		Accept::handle_accept( $accept_2, $user_ids[1] );
		Accept::handle_accept( $accept_3, $user_ids[2] );
		Accept::handle_accept( $accept_4, 0 );
		Accept::handle_accept( $accept_5, -1 );

		// User 1 follows https://example.com/actor/1.
		$following = Following::query( $user_ids[0] );
		$this->assertCount( 1, $following['following'] );
		$this->assertSame( 1, $following['total'] );

		$following = Following::query( -1 );
		$this->assertCount( 1, $following['following'] );
		$this->assertSame( 1, $following['total'] );

		// User 3 unfollows https://example.com/actor/1.
		Following::unfollow( Remote_Actors::get_by_uri( 'https://example.com/actor/1' ), $user_ids[2] );

		// User 3 unfollows https://example.com/actor/1.
		Following::unfollow( Remote_Actors::get_by_uri( 'https://example.com/actor/1' ), 0 );

		$following = Following::query( 0 );
		$this->assertCount( 0, $following['following'] );
		$this->assertSame( 0, $following['total'] );

		$following = Following::query( -1 );
		$this->assertCount( 1, $following['following'] );
		$this->assertSame( 1, $following['total'] );

		// User 1 still follows https://example.com/actor/1.
		$posts = get_posts(
			array(
				'post_type'   => 'ap_outbox',
				'post_status' => 'any',
				'author'      => $user_ids[2],
				'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => '_activitypub_object_id',
						'value' => 'https://example.com/actor/1',
					),
					array(
						'key'   => '_activitypub_activity_type',
						'value' => 'Undo',
					),
				),
			)
		);

		// There should be an Undo post for user 3.
		$this->assertCount( 1, $posts );
	}

	/**
	 * Test get_follower_ids method.
	 *
	 * @covers ::get_follower_ids
	 */
	public function test_get_follower_ids() {
		\add_filter( 'activitypub_pre_http_get_remote_object', array( $this, 'mock_remote_actor' ), 10, 2 );

		// Create a remote actor by fetching (which will use the mock).
		$remote_actor = Remote_Actors::fetch_by_uri( 'https://example.com/actor/1' );
		$this->assertNotWPError( $remote_actor );

		// Test with no followers.
		$user_ids = Following::get_follower_ids( 'https://example.com/actor/1' );
		$this->assertIsArray( $user_ids );
		$this->assertEmpty( $user_ids );

		// Add some followers.
		$user_id_1 = 1;
		$user_id_2 = 2;
		$user_id_3 = 3;

		\add_post_meta( $remote_actor->ID, Following::FOLLOWING_META_KEY, $user_id_1 );
		\add_post_meta( $remote_actor->ID, Following::FOLLOWING_META_KEY, $user_id_2 );
		\add_post_meta( $remote_actor->ID, Following::FOLLOWING_META_KEY, $user_id_3 );

		// Get user IDs.
		$user_ids = Following::get_follower_ids( 'https://example.com/actor/1' );
		$this->assertIsArray( $user_ids );
		$this->assertCount( 3, $user_ids );
		$this->assertContains( $user_id_1, $user_ids );
		$this->assertContains( $user_id_2, $user_ids );
		$this->assertContains( $user_id_3, $user_ids );

		// Test with non-existent actor URL.
		$user_ids = Following::get_follower_ids( 'https://example.com/actor/nonexistent' );
		$this->assertIsArray( $user_ids );
		$this->assertEmpty( $user_ids );

		\wp_delete_post( $remote_actor->ID );
		\remove_filter( 'activitypub_pre_http_get_remote_object', array( $this, 'mock_remote_actor' ) );
	}

	/**
	 * Mock remote actor.
	 *
	 * @param array  $response The response.
	 * @param string $url      The URL.
	 *
	 * @return array
	 */
	public function mock_remote_actor( $response, $url ) {
		if ( 'https://example.com/actor/1' === $url ) {
			$response = array(
				'id'    => $url,
				'type'  => 'Person',
				'url'   => $url,
				'name'  => 'John Doe',
				'inbox' => 'https://example.com/inbox',
			);
		}

		return $response;
	}
}
