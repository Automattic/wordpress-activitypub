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
	 * Clean up after each test.
	 */
	public function tear_down() {
		// Clean up any outbox posts.
		_delete_all_posts();

		// Remove any HTTP mocking filters.
		\remove_all_filters( 'pre_get_remote_metadata_by_actor' );
		\remove_all_filters( 'activitypub_pre_http_get_remote_object' );

		// Remove action hooks.
		\remove_all_actions( 'activitypub_followers_post_follow' );
		\remove_all_actions( 'activitypub_handled_follow' );

		parent::tear_down();
	}

	/**
	 * Clean up after tests.
	 */
	public static function wpTearDownAfterClass() {
		wp_delete_user( self::$user_id );
	}

	/**
	 * Test handle_follow method with different scenarios.
	 *
	 * @dataProvider handle_follow_provider
	 * @covers ::handle_follow
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
			\add_filter(
				'pre_get_remote_metadata_by_actor',
				function () use ( $actor_url ) {
					return array(
						'id'                => $actor_url,
						'actor'             => $actor_url,
						'type'              => 'Person',
						'preferredUsername' => 'testactor',
						'inbox'             => str_replace( '/actor', '/inbox', $actor_url ),
					);
				}
			);
		}

		$local_actor     = Actors::get_by_id( $target_user_id );
		$activity_object = array(
			'id'     => $actor_url . '/activity/123',
			'type'   => 'Follow',
			'actor'  => $actor_url,
			'object' => $local_actor->get_id(),
		);

		// Track followers count before.
		$followers_before       = Followers::get_followers( $target_user_id );
		$followers_count_before = count( $followers_before );

		Follow::handle_follow( $activity_object, $target_user_id );

		// Check if follower was added.
		if ( $should_add_follower ) {
			$followers_after       = Followers::get_followers( $target_user_id );
			$followers_count_after = count( $followers_after );
			$this->assertEquals( $followers_count_before + 1, $followers_count_after, $description . ' - Follower should be added' );
		} else {
			$followers_after       = Followers::get_followers( $target_user_id );
			$followers_count_after = count( $followers_after );
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
		_delete_all_posts();
		\remove_all_filters( 'pre_get_remote_metadata_by_actor' );
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

		\add_filter(
			'pre_get_remote_metadata_by_actor',
			function () use ( $actor ) {
				return array(
					'id'                => $actor,
					'actor'             => $actor,
					'type'              => 'Person',
					'preferredUsername' => 'testactor',
					'inbox'             => 'https://example.com/inbox',
				);
			}
		);

		$remote_actor = Followers::add_follower(
			self::$user_id,
			$activity_object['actor']
		);
		$remote_actor = \get_post( $remote_actor );

		Follow::queue_accept( $activity_object, self::$user_id, $remote_actor, $remote_actor );

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
		wp_delete_post( $outbox_post->ID, true );
		wp_delete_post( $remote_actor->ID, true );
		remove_all_filters( 'pre_get_remote_metadata_by_actor' );
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

		// Clean up.
		wp_delete_post( $outbox_post->ID, true );
	}

	/**
	 * Test that deprecated hook still fires for backward compatibility.
	 *
	 * @covers ::handle_follow
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
		\add_action(
			'activitypub_followers_post_follow',
			function ( $actor, $activity, $user_id, $remote_actor ) use ( &$hook_fired, &$hook_actor, &$hook_activity, &$hook_user_id, &$hook_remote_actor ) {
				$hook_fired        = true;
				$hook_actor        = $actor;
				$hook_activity     = $activity;
				$hook_user_id      = $user_id;
				$hook_remote_actor = $remote_actor;
			},
			10,
			4
		);

		$actor_url = 'https://example.com/deprecated-test-actor';

		// Mock HTTP requests for actor metadata.
		\add_filter(
			'pre_get_remote_metadata_by_actor',
			function () use ( $actor_url ) {
				return array(
					'id'                => $actor_url,
					'actor'             => $actor_url,
					'type'              => 'Person',
					'preferredUsername' => 'testactor',
					'inbox'             => str_replace( '/deprecated-test-actor', '/inbox', $actor_url ),
				);
			}
		);

		$activity_object = array(
			'id'     => $actor_url . '/activity/deprecated',
			'type'   => 'Follow',
			'actor'  => $actor_url,
			'object' => Actors::get_by_id( self::$user_id )->get_id(),
		);

		Follow::handle_follow( $activity_object, self::$user_id );

		// Verify deprecated hook fired.
		$this->assertTrue( $hook_fired, 'Deprecated hook should fire' );
		$this->assertEquals( $actor_url, $hook_actor );
		$this->assertEquals( $activity_object, $hook_activity );
		$this->assertEquals( self::$user_id, $hook_user_id );
		$this->assertInstanceOf( \WP_Post::class, $hook_remote_actor );

		// Clean up outbox posts.
		$outbox_posts = \get_posts(
			array(
				'post_type'   => Outbox::POST_TYPE,
				'author'      => self::$user_id,
				'post_status' => 'any',
			)
		);
		foreach ( $outbox_posts as $post ) {
			wp_delete_post( $post->ID, true );
		}

		// Clean up hooks and filters.
		\remove_all_actions( 'activitypub_followers_post_follow' );
		\remove_all_filters( 'pre_get_remote_metadata_by_actor' );
		if ( $hook_remote_actor instanceof \WP_Post ) {
			wp_delete_post( $hook_remote_actor->ID, true );
		}
	}

	/**
	 * Test new hook fires correctly.
	 *
	 * @covers ::handle_follow
	 */
	public function test_new_hook_fires() {
		$hook_fired        = false;
		$hook_activity     = null;
		$hook_user_id      = null;
		$hook_success      = null;
		$hook_remote_actor = null;

		// Hook into the new action.
		\add_action(
			'activitypub_handled_follow',
			function ( $activity, $user_id, $success, $remote_actor ) use ( &$hook_fired, &$hook_activity, &$hook_user_id, &$hook_success, &$hook_remote_actor ) {
				$hook_fired        = true;
				$hook_activity     = $activity;
				$hook_user_id      = $user_id;
				$hook_success      = $success;
				$hook_remote_actor = $remote_actor;
			},
			10,
			4
		);

		$actor_url = 'https://example.com/new-hook-test-actor';

		// Mock HTTP requests for actor metadata.
		\add_filter(
			'pre_get_remote_metadata_by_actor',
			function () use ( $actor_url ) {
				return array(
					'id'                => $actor_url,
					'actor'             => $actor_url,
					'type'              => 'Person',
					'preferredUsername' => 'testactor',
					'inbox'             => str_replace( '/new-hook-test-actor', '/inbox', $actor_url ),
				);
			}
		);

		$activity_object = array(
			'id'     => $actor_url . '/activity/new-hook',
			'type'   => 'Follow',
			'actor'  => $actor_url,
			'object' => Actors::get_by_id( self::$user_id )->get_id(),
		);

		Follow::handle_follow( $activity_object, self::$user_id );

		// Verify new hook fired.
		$this->assertTrue( $hook_fired, 'New hook should fire' );
		$this->assertEquals( $activity_object, $hook_activity );
		$this->assertEquals( self::$user_id, $hook_user_id );
		$this->assertTrue( $hook_success );
		$this->assertInstanceOf( \WP_Post::class, $hook_remote_actor );

		// Clean up outbox posts.
		$outbox_posts = \get_posts(
			array(
				'post_type'   => Outbox::POST_TYPE,
				'author'      => self::$user_id,
				'post_status' => 'any',
			)
		);
		foreach ( $outbox_posts as $post ) {
			wp_delete_post( $post->ID, true );
		}

		// Clean up hooks and filters.
		\remove_all_actions( 'activitypub_handled_follow' );
		\remove_all_filters( 'pre_get_remote_metadata_by_actor' );
		if ( $hook_remote_actor instanceof \WP_Post ) {
			wp_delete_post( $hook_remote_actor->ID, true );
		}
	}
}
