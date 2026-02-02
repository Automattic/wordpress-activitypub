<?php
/**
 * Test file for Follow handler.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Handler;

use Activitypub\Collection\Actors;
use Activitypub\Collection\Followers;
use Activitypub\Collection\Outbox;
use Activitypub\Handler\Follow;

/**
 * Test class for Follow handler.
 *
 * @coversDefaultClass \Activitypub\Handler\Follow
 */
class Test_Follow extends \WP_UnitTestCase {
	/**
	 * Test user ID.
	 *
	 * @var int
	 */
	protected static $user_id;

	/**
	 * Create fake data before tests run.
	 *
	 * @param \WP_UnitTest_Factory $factory Helper that creates fake data.
	 */
	public static function wpSetUpBeforeClass( $factory ) {
		self::$user_id = $factory->user->create(
			array(
				'role' => 'author',
			)
		);
	}

	/**
	 * Test handle_follow method with different scenarios.
	 *
	 * @dataProvider handle_follow_provider
	 * @covers ::incoming
	 *
	 * @param mixed  $target_user_id      The user ID being followed (int or 'test_user').
	 * @param string $actor_url           The actor URL following.
	 * @param string $expected_response   Expected response type ('Accept', 'Reject', or 'none').
	 * @param bool   $should_add_follower Whether follower should be added.
	 * @param string $description         Description of the test case.
	 */
	public function test_handle_follow( $target_user_id, $actor_url, $expected_response, $should_add_follower, $description ) {
		// Resolve user ID if needed.
		if ( 'test_user' === $target_user_id ) {
			$target_user_id = self::$user_id;
		}
		// Mock HTTP requests for actor metadata if needed.
		if ( $should_add_follower ) {
			$mock_metadata_callback = function () use ( $actor_url ) {
				return array(
					'id'                => $actor_url,
					'actor'             => $actor_url,
					'type'              => 'Person',
					'preferredUsername' => 'testactor',
					'inbox'             => str_replace( '/actor', '/inbox', $actor_url ),
				);
			};
			\add_filter( 'pre_get_remote_metadata_by_actor', $mock_metadata_callback );
		}

		$local_actor     = Actors::get_by_id( $target_user_id );
		$activity_object = array(
			'id'     => $actor_url . '/activity/123',
			'type'   => 'Follow',
			'actor'  => $actor_url,
			'object' => $local_actor->get_id(),
		);

		// Track followers count before.
		$followers_before       = Followers::get_many( $target_user_id );
		$followers_count_before = count( $followers_before );

		Follow::incoming( $activity_object, $target_user_id );

		// Check if follower was added.
		$followers_after       = Followers::get_many( $target_user_id );
		$followers_count_after = count( $followers_after );
		if ( $should_add_follower ) {
			$this->assertEquals( $followers_count_before + 1, $followers_count_after, $description . ' - Follower should be added' );
		} else {
			$this->assertEquals( $followers_count_before, $followers_count_after, $description . ' - Follower should not be added' );
		}

		// Check outbox for expected response.
		if ( 'none' !== $expected_response ) {
			$outbox_posts = \get_posts(
				array(
					'post_type'   => Outbox::POST_TYPE,
					'post_status' => 'pending',
					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'meta_query'  => array(
						array(
							'key'   => '_activitypub_activity_type',
							'value' => $expected_response,
						),
					),
				)
			);
			$this->assertNotEmpty( $outbox_posts, $description . ' - Should create ' . $expected_response . ' response' );
		}

		// Clean up.
		if ( $should_add_follower ) {
			\remove_filter( 'pre_get_remote_metadata_by_actor', $mock_metadata_callback );
		}
	}

	/**
	 * Data provider for handle_follow tests.
	 *
	 * @return array Test cases with user ID, actor URL, expected response, should add follower, and description.
	 */
	public function handle_follow_provider() {
		return array(
			'application_user_follow' => array(
				Actors::APPLICATION_USER_ID,
				'https://example.com/actor',
				'Reject',
				false,
				'Following application user should be rejected',
			),
			'regular_user_follow'     => array(
				'test_user',
				'https://example.com/regular-actor',
				'Accept',
				true,
				'Following regular user should be accepted',
			),
			'subdomain_actor_follow'  => array(
				'test_user',
				'https://social.example.com/users/actor',
				'Accept',
				true,
				'Following with subdomain actor should work',
			),
		);
	}

	/**
	 * Test queue_accept method.
	 *
	 * @covers ::queue_accept
	 */
	public function test_queue_accept() {
		$local_actor     = Actors::get_by_id( self::$user_id );
		$actor           = 'https://example.com/actor';
		$activity_object = array(
			'id'     => 'https://example.com/activity/123',
			'type'   => 'Follow',
			'actor'  => $actor,
			'object' => $local_actor->get_id(),
		);

		// Test with WP_Error follower - should not create outbox entry.
		$wp_error = new \WP_Error( 'test_error', 'Test Error' );
		Follow::queue_accept( $activity_object, self::$user_id, true, $wp_error );

		$outbox_posts = \get_posts(
			array(
				'post_type'   => Outbox::POST_TYPE,
				'author'      => self::$user_id,
				'post_status' => 'pending',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_query'  => array(
					array(
						'key'   => '_activitypub_activity_actor',
						'value' => 'user',
					),
				),
			)
		);
		$this->assertEmpty( $outbox_posts, 'No outbox entry should be created for WP_Error follower' );

		$mock_metadata_callback = function () use ( $actor ) {
			return array(
				'id'                => $actor,
				'actor'             => $actor,
				'type'              => 'Person',
				'preferredUsername' => 'testactor',
				'inbox'             => 'https://example.com/inbox',
			);
		};
		\add_filter( 'pre_get_remote_metadata_by_actor', $mock_metadata_callback );

		$remote_actor = Followers::add(
			self::$user_id,
			$activity_object['actor']
		);
		$remote_actor = \get_post( $remote_actor );

		Follow::queue_accept( $activity_object, self::$user_id, $remote_actor instanceof \WP_Post, $remote_actor );

		$outbox_posts = \get_posts(
			array(
				'post_type'   => Outbox::POST_TYPE,
				'author'      => self::$user_id,
				'post_status' => 'pending',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_query'  => array(
					array(
						'key'   => '_activitypub_activity_actor',
						'value' => 'user',
					),
				),
			)
		);

		$this->assertCount( 1, $outbox_posts, 'One outbox entry should be created' );

		$outbox_post   = $outbox_posts[0];
		$activity_type = \get_post_meta( $outbox_post->ID, '_activitypub_activity_type', true );
		$activity_json = \json_decode( $outbox_post->post_content, true );
		$visibility    = \get_post_meta( $outbox_post->ID, 'activitypub_content_visibility', true );

		// Verify outbox entry.
		$this->assertEquals( 'Accept', $activity_type );
		$this->assertEquals( ACTIVITYPUB_CONTENT_VISIBILITY_PRIVATE, $visibility );

		$this->assertEquals( 'Follow', $activity_json['object']['type'] );
		$this->assertEquals( $local_actor->get_id(), $activity_json['object']['object'] );
		$this->assertEquals( array( $actor ), $activity_json['to'] );
		$this->assertEquals( $actor, $activity_json['object']['actor'] );
		$this->assertEquals( $local_actor->get_id(), $activity_json['actor'] );

		// Clean up.
		\remove_filter( 'pre_get_remote_metadata_by_actor', $mock_metadata_callback );
	}

	/**
	 * Test that duplicate follow requests don't trigger notifications.
	 *
	 * @covers ::incoming
	 */
	public function test_duplicate_follow_no_notification() {
		$actor_url = 'https://example.com/duplicate-actor';

		// Mock HTTP requests for actor metadata.
		$mock_actor_callback = function () use ( $actor_url ) {
			return array(
				'id'                => $actor_url,
				'actor'             => $actor_url,
				'type'              => 'Person',
				'preferredUsername' => 'duplicate_actor',
				'inbox'             => str_replace( '/actor', '/inbox', $actor_url ),
			);
		};
		\add_filter( 'pre_get_remote_metadata_by_actor', $mock_actor_callback );

		$local_actor     = Actors::get_by_id( self::$user_id );
		$activity_object = array(
			'id'     => $actor_url . '/activity/follow-1',
			'type'   => 'Follow',
			'actor'  => $actor_url,
			'object' => $local_actor->get_id(),
		);

		// Track calls to the handled_follow action.
		$handled_follow_calls = array();
		$test_callback        = function ( $activity, $user_ids, $success, $remote_actor ) use ( &$handled_follow_calls ) {
			$handled_follow_calls[] = array(
				'activity'     => $activity,
				'user_ids'     => $user_ids,
				'success'      => $success,
				'remote_actor' => $remote_actor,
			);
		};
		\add_action( 'activitypub_handled_follow', $test_callback, 10, 4 );

		// First follow request - should succeed.
		Follow::incoming( $activity_object, self::$user_id );

		// Verify first follow was successful.
		$this->assertCount( 1, $handled_follow_calls, 'First follow should trigger the action' );
		$this->assertTrue( $handled_follow_calls[0]['success'], 'First follow should be successful' );

		// Verify follower was added.
		$followers       = Followers::get_many( self::$user_id );
		$follower_actors = wp_list_pluck( $followers, 'guid' );
		$this->assertContains( $actor_url, $follower_actors, 'Follower should be added' );

		// Second follow request with a different activity ID (simulating a retry).
		$activity_object['id'] = $actor_url . '/activity/follow-2';
		Follow::incoming( $activity_object, self::$user_id );

		// Verify second follow was not successful (to prevent duplicate notification).
		$this->assertCount( 2, $handled_follow_calls, 'Second follow should also trigger the action' );
		$this->assertFalse( $handled_follow_calls[1]['success'], 'Second follow should NOT be successful to prevent duplicate notification' );

		// Verify follower count didn't change.
		$followers_after = Followers::get_many( self::$user_id );
		$this->assertCount( count( $followers ), $followers_after, 'Follower count should not change on duplicate follow' );

		// Clean up.
		\remove_filter( 'pre_get_remote_metadata_by_actor', $mock_actor_callback );
		\remove_action( 'activitypub_handled_follow', $test_callback );
	}

	/**
	 * Test queue_reject method.
	 *
	 * @covers ::queue_reject
	 */
	public function test_queue_reject() {
		$actor_url       = 'https://example.com/reject-actor';
		$activity_object = array(
			'id'     => $actor_url . '/activity/456',
			'type'   => 'Follow',
			'actor'  => $actor_url,
			'object' => Actors::get_by_id( self::$user_id )->get_id(),
		);

		Follow::queue_reject( $activity_object, self::$user_id );

		// Check that a Reject activity was queued.
		$outbox_posts = \get_posts(
			array(
				'post_type'   => Outbox::POST_TYPE,
				'author'      => self::$user_id,
				'post_status' => 'pending',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_query'  => array(
					array(
						'key'   => '_activitypub_activity_type',
						'value' => 'Reject',
					),
				),
			)
		);

		$this->assertCount( 1, $outbox_posts, 'One Reject outbox entry should be created' );

		$outbox_post   = $outbox_posts[0];
		$activity_type = \get_post_meta( $outbox_post->ID, '_activitypub_activity_type', true );
		$activity_json = \json_decode( $outbox_post->post_content, true );
		$visibility    = \get_post_meta( $outbox_post->ID, 'activitypub_content_visibility', true );

		// Verify outbox entry.
		$this->assertEquals( 'Reject', $activity_type );
		$this->assertEquals( ACTIVITYPUB_CONTENT_VISIBILITY_PRIVATE, $visibility );
		$this->assertEquals( 'Follow', $activity_json['object']['type'] );
		$this->assertEquals( array( $actor_url ), $activity_json['to'] );
		$this->assertEquals( $actor_url, $activity_json['object']['actor'] );
	}

	/**
	 * Test that deprecated hook still fires for backward compatibility.
	 *
	 * @covers ::incoming
	 */
	public function test_deprecated_hook_fires() {
		// Expect the deprecation notice.
		$this->setExpectedDeprecated( 'activitypub_followers_post_follow' );
		$hook_fired        = false;
		$hook_actor        = null;
		$hook_activity     = null;
		$hook_user_id      = null;
		$hook_remote_actor = null;

		// Hook into the deprecated action.
		$deprecated_callback = function ( $actor, $activity, $user_id, $remote_actor ) use ( &$hook_fired, &$hook_actor, &$hook_activity, &$hook_user_id, &$hook_remote_actor ) {
			$hook_fired        = true;
			$hook_actor        = $actor;
			$hook_activity     = $activity;
			$hook_user_id      = $user_id;
			$hook_remote_actor = $remote_actor;
		};
		\add_action( 'activitypub_followers_post_follow', $deprecated_callback, 10, 4 );

		$actor_url = 'https://example.com/deprecated-test-actor';

		// Mock HTTP requests for actor metadata.
		$mock_metadata_callback = function () use ( $actor_url ) {
			return array(
				'id'                => $actor_url,
				'actor'             => $actor_url,
				'type'              => 'Person',
				'preferredUsername' => 'testactor',
				'inbox'             => str_replace( '/deprecated-test-actor', '/inbox', $actor_url ),
			);
		};
		\add_filter( 'pre_get_remote_metadata_by_actor', $mock_metadata_callback );

		$activity_object = array(
			'id'     => $actor_url . '/activity/deprecated',
			'type'   => 'Follow',
			'actor'  => $actor_url,
			'object' => Actors::get_by_id( self::$user_id )->get_id(),
		);

		Follow::incoming( $activity_object, self::$user_id );

		// Verify deprecated hook fired.
		$this->assertTrue( $hook_fired, 'Deprecated hook should fire' );
		$this->assertEquals( $actor_url, $hook_actor );
		$this->assertEquals( $activity_object, $hook_activity );
		$this->assertEquals( self::$user_id, $hook_user_id );
		$this->assertInstanceOf( \WP_Post::class, $hook_remote_actor );

		// Clean up filters.
		\remove_action( 'activitypub_followers_post_follow', $deprecated_callback );
		\remove_filter( 'pre_get_remote_metadata_by_actor', $mock_metadata_callback );
	}

	/**
	 * Test new hook fires correctly.
	 *
	 * @covers ::incoming
	 */
	public function test_new_hook_fires() {
		$hook_fired        = false;
		$hook_activity     = null;
		$hook_user_id      = null;
		$hook_success      = null;
		$hook_remote_actor = null;

		// Hook into the new action.
		$new_hook_callback = function ( $activity, $user_id, $success, $remote_actor ) use ( &$hook_fired, &$hook_activity, &$hook_user_id, &$hook_success, &$hook_remote_actor ) {
			$hook_fired        = true;
			$hook_activity     = $activity;
			$hook_user_id      = $user_id;
			$hook_success      = $success;
			$hook_remote_actor = $remote_actor;
		};
		\add_action( 'activitypub_handled_follow', $new_hook_callback, 10, 4 );

		$actor_url = 'https://example.com/new-hook-test-actor';

		// Mock HTTP requests for actor metadata.
		$mock_metadata_callback = function () use ( $actor_url ) {
			return array(
				'id'                => $actor_url,
				'actor'             => $actor_url,
				'type'              => 'Person',
				'preferredUsername' => 'testactor',
				'inbox'             => str_replace( '/new-hook-test-actor', '/inbox', $actor_url ),
			);
		};
		\add_filter( 'pre_get_remote_metadata_by_actor', $mock_metadata_callback );

		$activity_object = array(
			'id'     => $actor_url . '/activity/new-hook',
			'type'   => 'Follow',
			'actor'  => $actor_url,
			'object' => Actors::get_by_id( self::$user_id )->get_id(),
		);

		Follow::incoming( $activity_object, self::$user_id );

		// Verify new hook fired.
		$this->assertTrue( $hook_fired, 'New hook should fire' );
		$this->assertEquals( $activity_object, $hook_activity );
		$this->assertIsArray( $hook_user_id, 'User ID should be an array' );
		$this->assertContains( self::$user_id, $hook_user_id, 'Array should contain user ID' );
		$this->assertTrue( $hook_success );
		$this->assertInstanceOf( \WP_Post::class, $hook_remote_actor );

		// Clean up filters.
		\remove_action( 'activitypub_handled_follow', $new_hook_callback );
		\remove_filter( 'pre_get_remote_metadata_by_actor', $mock_metadata_callback );
	}
}
