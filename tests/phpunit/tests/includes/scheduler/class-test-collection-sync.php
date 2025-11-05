<?php
/**
 * Test Collection Sync scheduler class.
 *
 * @package Activitypub\Tests\Scheduler
 */

namespace Activitypub\Tests\Scheduler;

use Activitypub\Collection\Following;
use Activitypub\Collection\Remote_Actors;
use Activitypub\Http;
use Activitypub\Scheduler\Collection_Sync;

use function Activitypub\get_url_authority;

/**
 * Test Collection Sync scheduler class.
 *
 * @coversDefaultClass \Activitypub\Scheduler\Collection_Sync
 */
class Test_Collection_Sync extends \WP_UnitTestCase {

	/**
	 * Test user ID.
	 *
	 * @var int
	 */
	protected static $user_id;

	/**
	 * Remote actor URLs for testing.
	 *
	 * @var array
	 */
	protected static $remote_actors = array(
		'https://example.com/users/alice',
		'https://example.com/users/bob',
		'https://example.com/users/charlie',
		'https://mastodon.social/users/dave',
		'https://mastodon.social/users/eve',
	);

	/**
	 * Set up the test environment.
	 *
	 * @param \WP_UnitTest_Factory $factory The factory object.
	 */
	public static function wpSetUpBeforeClass( $factory ) {
		self::$user_id = $factory->user->create(
			array(
				'user_login' => 'test_user',
				'user_email' => 'test@example.com',
			)
		);
	}

	/**
	 * Set up each test.
	 */
	public function set_up() {
		parent::set_up();
		_delete_all_posts();
	}

	/**
	 * Helper: Create a remote actor post.
	 *
	 * @param string $actor_url The actor URL.
	 * @return int The post ID.
	 */
	protected function create_remote_actor( $actor_url ) {
		$post_id = self::factory()->post->create(
			array(
				'post_title'  => $actor_url,
				'post_status' => 'publish',
				'post_type'   => Remote_Actors::POST_TYPE,
				'guid'        => $actor_url,
			)
		);

		// Set the inbox meta. The inbox should contain the home authority
		// because get_by_authority filters by inbox containing the authority.
		$home_authority = get_url_authority( \home_url() );
		\add_post_meta( $post_id, '_activitypub_inbox', $home_authority . '/inbox' );

		return $post_id;
	}

	/**
	 * Helper: Add an accepted follow.
	 *
	 * @param int $post_id The remote actor post ID.
	 * @param int $user_id The local user ID.
	 */
	protected function add_accepted_follow( $post_id, $user_id ) {
		\add_post_meta( $post_id, Following::FOLLOWING_META_KEY, $user_id );
	}

	/**
	 * Helper: Add a pending follow.
	 *
	 * @param int $post_id The remote actor post ID.
	 * @param int $user_id The local user ID.
	 */
	protected function add_pending_follow( $post_id, $user_id ) {
		\add_post_meta( $post_id, Following::PENDING_META_KEY, $user_id );
	}

	/**
	 * Test schedule_reconciliation schedules the correct action.
	 *
	 * @covers ::schedule_reconciliation
	 */
	public function test_schedule_reconciliation() {
		$user_id   = self::$user_id;
		$actor_url = 'https://example.com/users/test';
		$params    = array(
			'url'          => 'https://example.com/users/test/followers/sync',
			'collectionId' => 'https://example.com/users/test/followers',
			'digest'       => 'abcdef123456',
		);

		// Clear any existing scheduled events.
		wp_clear_scheduled_hook( 'activitypub_followers_sync_reconcile', array( $user_id, $actor_url, $params ) );

		// Schedule the reconciliation.
		Collection_Sync::schedule_reconciliation( 'followers', $user_id, $actor_url, $params );

		// Check that the event was scheduled.
		$scheduled = wp_next_scheduled( 'activitypub_followers_sync_reconcile', array( $user_id, $actor_url, $params ) );

		$this->assertNotFalse( $scheduled, 'Event should be scheduled' );
		$this->assertGreaterThan( time(), $scheduled, 'Event should be scheduled in the future' );
		$this->assertLessThanOrEqual( time() + 61, $scheduled, 'Event should be scheduled within ~60 seconds' );

		// Clean up.
		wp_clear_scheduled_hook( 'activitypub_followers_sync_reconcile', array( $user_id, $actor_url, $params ) );
	}

	/**
	 * Test reconcile_followers with empty URL parameter.
	 *
	 * @covers ::reconcile_followers
	 */
	public function test_reconcile_followers_empty_url() {
		$params = array(); // No URL.

		// Should return early without errors.
		Collection_Sync::reconcile_followers( self::$user_id, 'https://example.com/users/test', $params );

		// No assertions needed - just verify no errors.
		$this->assertTrue( true );
	}

	/**
	 * Test reconcile_followers with invalid remote response.
	 *
	 * @covers ::reconcile_followers
	 */
	public function test_reconcile_followers_invalid_remote_response() {
		$params = array(
			'url' => 'https://example.com/invalid',
		);

		// Mock Http::get_remote_object to return an error.
		add_filter(
			'activitypub_pre_http_get_remote_object',
			function ( $preempt, $url_or_object ) {
				if ( 'https://example.com/invalid' === $url_or_object ) {
					return new \WP_Error( 'http_request_failed', 'Request failed' );
				}
				return $preempt;
			},
			10,
			2
		);

		// Should return early without errors.
		Collection_Sync::reconcile_followers( self::$user_id, 'https://example.com/users/test', $params );

		// No assertions needed - just verify no errors.
		$this->assertTrue( true );

		// Clean up.
		remove_all_filters( 'activitypub_pre_http_get_remote_object' );
	}

	/**
	 * Test reconcile_followers rejects accepted follows not in remote list.
	 *
	 * @covers ::reconcile_followers
	 */
	public function test_reconcile_followers_rejects_stale_accepted() {
		$home_authority = get_url_authority( \home_url() );

		// Create remote actors.
		$alice_id = $this->create_remote_actor( self::$remote_actors[0] ); // example.com/alice.
		$bob_id   = $this->create_remote_actor( self::$remote_actors[1] ); // example.com/bob.

		// Add both as accepted follows.
		$this->add_accepted_follow( $alice_id, self::$user_id );
		$this->add_accepted_follow( $bob_id, self::$user_id );

		// Verify they're accepted.
		$accepted = Following::get_by_authority( self::$user_id, $home_authority );
		$this->assertCount( 2, $accepted );

		// Mock remote response with only Alice (Bob is missing).
		$params = array(
			'url' => 'https://example.com/users/test/followers/sync',
		);

		add_filter(
			'activitypub_pre_http_get_remote_object',
			function ( $preempt, $url_or_object ) {
				if ( 'https://example.com/users/test/followers/sync' === $url_or_object ) {
					return array(
						'type'         => 'OrderedCollection',
						'orderedItems' => array(
							self::$remote_actors[0], // Only Alice.
						),
					);
				}
				return $preempt;
			},
			10,
			2
		);

		// Run reconciliation.
		Collection_Sync::reconcile_followers( self::$user_id, 'https://example.com/users/test', $params );

		// Verify Bob was rejected (no longer accepted).
		$accepted = Following::get_by_authority( self::$user_id, $home_authority );
		$this->assertCount( 1, $accepted, 'Bob should have been rejected' );
		$this->assertEquals( self::$remote_actors[0], $accepted[0]->guid, 'Only Alice should remain' );

		// Clean up.
		remove_all_filters( 'activitypub_pre_http_get_remote_object' );
	}

	/**
	 * Test reconcile_followers accepts pending follows in remote list.
	 *
	 * @covers ::reconcile_followers
	 */
	public function test_reconcile_followers_accepts_pending_in_remote() {
		$home_authority = get_url_authority( \home_url() );

		// Create remote actor Charlie.
		$charlie_id = $this->create_remote_actor( self::$remote_actors[2] ); // example.com/charlie.

		// Add as pending follow.
		$this->add_pending_follow( $charlie_id, self::$user_id );

		// Verify it's pending.
		$pending = Following::get_by_authority( self::$user_id, $home_authority, Following::PENDING_META_KEY );
		$this->assertCount( 1, $pending );

		// Mock remote response with Charlie (already accepted on remote).
		$params = array(
			'url' => 'https://example.com/users/test/followers/sync',
		);

		add_filter(
			'activitypub_pre_http_get_remote_object',
			function ( $preempt, $url_or_object ) {
				if ( 'https://example.com/users/test/followers/sync' === $url_or_object ) {
					return array(
						'type'         => 'OrderedCollection',
						'orderedItems' => array(
							self::$remote_actors[2], // Charlie.
						),
					);
				}
				return $preempt;
			},
			10,
			2
		);

		// Run reconciliation.
		Collection_Sync::reconcile_followers( self::$user_id, 'https://example.com/users/test', $params );

		// Verify Charlie was accepted.
		$accepted = Following::get_by_authority( self::$user_id, $home_authority );
		$this->assertCount( 1, $accepted, 'Charlie should be accepted' );
		$this->assertEquals( self::$remote_actors[2], $accepted[0]->guid );

		// Verify Charlie is no longer pending.
		$pending = Following::get_by_authority( self::$user_id, $home_authority, Following::PENDING_META_KEY );
		$this->assertCount( 0, $pending, 'Charlie should not be pending' );

		// Clean up.
		remove_all_filters( 'activitypub_pre_http_get_remote_object' );
	}

	/**
	 * Test reconcile_followers rejects pending follows not in remote list.
	 *
	 * @covers ::reconcile_followers
	 */
	public function test_reconcile_followers_rejects_pending_not_in_remote() {
		// Create remote actor Dave (mastodon.social).
		$dave_id = $this->create_remote_actor( self::$remote_actors[3] ); // mastodon.social/dave.

		// Add as pending follow.
		$this->add_pending_follow( $dave_id, self::$user_id );

		// Mock remote response with empty list (Dave not included).
		$params = array(
			'url' => 'https://example.com/users/test/followers/sync',
		);

		add_filter(
			'activitypub_pre_http_get_remote_object',
			function ( $preempt, $url_or_object ) {
				if ( 'https://example.com/users/test/followers/sync' === $url_or_object ) {
					return array(
						'type'         => 'OrderedCollection',
						'orderedItems' => array(), // Empty.
					);
				}
				return $preempt;
			},
			10,
			2
		);

		// Run reconciliation.
		Collection_Sync::reconcile_followers( self::$user_id, 'https://example.com/users/test', $params );

		// Verify Dave was rejected (not accepted).
		$accepted = Following::get_by_authority( self::$user_id, 'https://mastodon.social' );
		$this->assertCount( 0, $accepted, 'Dave should not be accepted' );

		// Verify Dave is no longer pending.
		$pending = Following::get_by_authority( self::$user_id, 'https://mastodon.social', Following::PENDING_META_KEY );
		$this->assertCount( 0, $pending, 'Dave should not be pending' );

		// Clean up.
		remove_all_filters( 'activitypub_pre_http_get_remote_object' );
	}

	/**
	 * Test reconcile_followers handles mixed scenario correctly.
	 *
	 * @covers ::reconcile_followers
	 */
	public function test_reconcile_followers_mixed_scenario() {
		$home_authority = get_url_authority( \home_url() );

		// Create remote actors from example.com.
		$alice_id   = $this->create_remote_actor( self::$remote_actors[0] ); // Alice.
		$bob_id     = $this->create_remote_actor( self::$remote_actors[1] ); // Bob.
		$charlie_id = $this->create_remote_actor( self::$remote_actors[2] ); // Charlie.

		// Alice: accepted, in remote (should stay accepted).
		$this->add_accepted_follow( $alice_id, self::$user_id );

		// Bob: accepted, NOT in remote (should be rejected).
		$this->add_accepted_follow( $bob_id, self::$user_id );

		// Charlie: pending, in remote (should be accepted).
		$this->add_pending_follow( $charlie_id, self::$user_id );

		// Mock remote response with Alice and Charlie only.
		$params = array(
			'url' => 'https://example.com/users/test/followers/sync',
		);

		add_filter(
			'activitypub_pre_http_get_remote_object',
			function ( $preempt, $url_or_object ) {
				if ( 'https://example.com/users/test/followers/sync' === $url_or_object ) {
					return array(
						'type'         => 'OrderedCollection',
						'orderedItems' => array(
							self::$remote_actors[0], // Alice.
							self::$remote_actors[2], // Charlie.
						),
					);
				}
				return $preempt;
			},
			10,
			2
		);

		// Run reconciliation.
		Collection_Sync::reconcile_followers( self::$user_id, 'https://example.com/users/test', $params );

		// Verify final state.
		$accepted = Following::get_by_authority( self::$user_id, $home_authority );
		$this->assertCount( 2, $accepted, 'Should have 2 accepted follows' );

		$accepted_guids = array_map(
			function ( $post ) {
				return $post->guid;
			},
			$accepted
		);
		sort( $accepted_guids );

		$expected = array( self::$remote_actors[0], self::$remote_actors[2] );
		sort( $expected );

		$this->assertEquals( $expected, $accepted_guids, 'Alice and Charlie should be accepted' );

		// Verify no pending follows remain.
		$pending = Following::get_by_authority( self::$user_id, $home_authority, Following::PENDING_META_KEY );
		$this->assertCount( 0, $pending, 'No pending follows should remain' );

		// Clean up.
		remove_all_filters( 'activitypub_pre_http_get_remote_object' );
	}

	/**
	 * Test reconcile_followers fires action hook on completion.
	 *
	 * @covers ::reconcile_followers
	 */
	public function test_reconcile_followers_fires_action_hook() {
		$action_fired = false;
		$hook_user_id = null;
		$hook_actor   = null;

		add_action(
			'activitypub_followers_sync_reconciled',
			function ( $user_id, $actor_url ) use ( &$action_fired, &$hook_user_id, &$hook_actor ) {
				$action_fired = true;
				$hook_user_id = $user_id;
				$hook_actor   = $actor_url;
			},
			10,
			2
		);

		$params = array(
			'url' => 'https://example.com/users/test/followers/sync',
		);

		add_filter(
			'activitypub_pre_http_get_remote_object',
			function ( $preempt, $url_or_object ) {
				if ( 'https://example.com/users/test/followers/sync' === $url_or_object ) {
					return array(
						'type'         => 'OrderedCollection',
						'orderedItems' => array(),
					);
				}
				return $preempt;
			},
			10,
			2
		);

		// Run reconciliation.
		Collection_Sync::reconcile_followers( self::$user_id, 'https://example.com/users/test', $params );

		// Verify action was fired with correct parameters.
		$this->assertTrue( $action_fired, 'Action hook should fire' );
		$this->assertEquals( self::$user_id, $hook_user_id );
		$this->assertEquals( 'https://example.com/users/test', $hook_actor );

		// Clean up.
		remove_all_filters( 'activitypub_pre_http_get_remote_object' );
		remove_all_actions( 'activitypub_followers_sync_reconciled' );
	}

	/**
	 * Test reconcile_followers only processes followers from home authority.
	 *
	 * @covers ::reconcile_followers
	 */
	public function test_reconcile_followers_filters_by_authority() {
		$home_authority = get_url_authority( \home_url() );

		// Create actors with different inbox authorities.
		// Alice has home authority inbox (will be processed).
		$alice_id = $this->create_remote_actor( self::$remote_actors[0] ); // Has home authority inbox.

		// Dave has different authority inbox (won't be processed).
		$dave_id = self::factory()->post->create(
			array(
				'post_title'  => self::$remote_actors[3],
				'post_status' => 'publish',
				'post_type'   => Remote_Actors::POST_TYPE,
				'guid'        => self::$remote_actors[3],
			)
		);
		\add_post_meta( $dave_id, '_activitypub_inbox', 'https://mastodon.social/inbox' );

		// Add both as accepted follows.
		$this->add_accepted_follow( $alice_id, self::$user_id );
		$this->add_accepted_follow( $dave_id, self::$user_id );

		// Mock remote response with empty list (should only affect home authority followers).
		$params = array(
			'url' => 'https://example.com/users/test/followers/sync',
		);

		add_filter(
			'activitypub_pre_http_get_remote_object',
			function ( $preempt, $url_or_object ) {
				if ( 'https://example.com/users/test/followers/sync' === $url_or_object ) {
					return array(
						'type'         => 'OrderedCollection',
						'orderedItems' => array(),
					);
				}
				return $preempt;
			},
			10,
			2
		);

		// Run reconciliation.
		Collection_Sync::reconcile_followers( self::$user_id, 'https://example.com/users/test', $params );

		// Verify only home authority followers were processed.
		// Alice should be rejected (from home authority, not in remote list).
		$home_accepted = Following::get_by_authority( self::$user_id, $home_authority );
		$this->assertCount( 0, $home_accepted, 'Home authority followers should be processed' );

		// Dave should remain (different authority, not processed).
		$mastodon_accepted = Following::get_by_authority( self::$user_id, 'https://mastodon.social' );
		$this->assertCount( 1, $mastodon_accepted, 'Other authority followers should not be affected' );

		// Clean up.
		remove_all_filters( 'activitypub_pre_http_get_remote_object' );
	}
}
