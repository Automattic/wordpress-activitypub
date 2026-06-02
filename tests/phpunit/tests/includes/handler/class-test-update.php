<?php
/**
 * Test Update Handler.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Handler;

use Activitypub\Activity\Actor;
use Activitypub\Collection\Followers;
use Activitypub\Collection\Remote_Actors;
use Activitypub\Handler\Update;

/**
 * Update Handler Test Class.
 *
 * @coversDefaultClass \Activitypub\Handler\Update
 */
class Test_Update extends \WP_UnitTestCase {

	/**
	 * Test that the activitypub_handled_create fallback is triggered.
	 */
	public function test_activitypub_inbox_create_fallback() {
		\update_option( 'activitypub_create_posts', true );

		// Initialize Update handler to register hooks.
		Update::init();

		$called     = false;
		$test_actor = 'https://example.com/users/fallback';
		$activity   = array(
			'id'     => 'https://example.com/activities/12345',
			'type'   => 'Update',
			'actor'  => $test_actor,
			'to'     => array( 'https://www.w3.org/ns/activitystreams#Public' ),
			'object' => array(
				'id'           => 'https://example.com/objects/12345',
				'type'         => 'Note',
				'content'      => 'Test note',
				'attributedTo' => $test_actor,
			),
		);

		// Add a fallback handler for the action.
		$create_fallback_callback = function ( $activity_data ) use ( &$called, $test_actor ) {
			if ( isset( $activity_data['actor'] ) && $activity_data['actor'] === $test_actor ) {
				$called = true;
			}
		};
		\add_action( 'activitypub_handled_create', $create_fallback_callback, 10, 4 );

		// Call the handler via the handled_inbox_update hook.
		\do_action( 'activitypub_handled_inbox_update', $activity, array( $this->user_id ), null );

		$this->assertTrue( $called, 'The fallback activitypub_handled_create action should be triggered.' );

		// Clean up by removing the action.
		\remove_action( 'activitypub_handled_create', $create_fallback_callback );
		\delete_option( 'activitypub_create_posts' );
	}

	/**
	 * User ID.
	 *
	 * @var int
	 */
	public $user_id;

	/**
	 * Set up the test.
	 */
	public function set_up() {
		parent::set_up();

		$this->user_id = self::factory()->user->create();
	}

	/**
	 * Test updating an actor with various scenarios.
	 *
	 * @dataProvider update_actor_provider
	 * @covers ::update_actor
	 *
	 * @param array  $activity_data    The activity data.
	 * @param mixed  $http_response    The HTTP response to mock.
	 * @param string $expected_outcome The expected test outcome.
	 * @param string $description      Description of the test case.
	 */
	public function test_update_actor( $activity_data, $http_response, $expected_outcome, $description ) {
		$actor_url = $activity_data['actor'];

		$fake_request = function () use ( $http_response ) {
			if ( is_wp_error( $http_response ) ) {
				return $http_response;
			}
			return $http_response;
		};

		// Mock HTTP request.
		\add_filter( 'activitypub_pre_http_get_remote_object', $fake_request, 10, 2 );

		// Execute the update_actor method.
		Update::update_actor( $activity_data, 1 );

		// Verify results based on expected outcome.
		if ( 'error' === $expected_outcome ) {
			$follower = Remote_Actors::get_by_uri( $actor_url );
			$this->assertWPError( $follower, $description );
		} else {
			// For successful updates, add follower first then test update.
			Followers::add( $this->user_id, $actor_url );

			$follower = Remote_Actors::get_by_uri( $actor_url );
			$this->assertNotNull( $follower, $description );

			$follower_actor = Remote_Actors::get_actor( $follower );
			$this->assertInstanceOf( Actor::class, $follower_actor, $description );

			if ( isset( $http_response['name'] ) ) {
				$this->assertEquals( $http_response['name'], $follower_actor->get_name(), $description );
			}
		}

		\remove_filter( 'activitypub_pre_http_get_remote_object', $fake_request );
	}

	/**
	 * Test that update_actor uses embedded object data instead of stale cached data.
	 *
	 * @covers ::update_actor
	 */
	public function test_update_actor_uses_embedded_object_data() {
		$actor_url = 'https://example.com/users/embedded';

		$original_actor = array(
			'type'              => 'Person',
			'id'                => $actor_url,
			'name'              => 'Original Name',
			'preferredUsername' => 'embedded',
			'inbox'             => $actor_url . '/inbox',
			'outbox'            => $actor_url . '/outbox',
			'followers'         => $actor_url . '/followers',
			'following'         => $actor_url . '/following',
			'publicKey'         => array(
				'id'           => $actor_url . '#main-key',
				'owner'        => $actor_url,
				'publicKeyPem' => "-----BEGIN PUBLIC KEY-----\nMIIBIjANBgkqhkiG9w0BAQE\n-----END PUBLIC KEY-----",
			),
		);

		// Store the actor locally first, simulating a cached copy.
		Remote_Actors::upsert( $original_actor );

		$post = Remote_Actors::get_by_uri( $actor_url );
		$this->assertNotWPError( $post, 'Actor should be stored locally.' );

		// Build an Update activity with fresh embedded data.
		$updated_actor = array_merge( $original_actor, array( 'name' => 'Updated Name' ) );

		$activity = array(
			'type'   => 'Update',
			'actor'  => $actor_url,
			'object' => $updated_actor,
		);

		/*
		 * Mock HTTP to return stale data — if the handler incorrectly
		 * fetches remotely, we'll catch it.
		 */
		$stale_response = function () use ( $original_actor ) {
			return $original_actor;
		};
		\add_filter( 'activitypub_pre_http_get_remote_object', $stale_response );

		Update::update_actor( $activity, array( $this->user_id ) );

		\remove_filter( 'activitypub_pre_http_get_remote_object', $stale_response );

		// Verify the embedded (fresh) data was used.
		$post  = Remote_Actors::get_by_uri( $actor_url );
		$actor = Remote_Actors::get_actor( $post );
		$this->assertEquals( 'Updated Name', $actor->get_name(), 'Should use embedded object data, not stale cached data.' );
	}

	/**
	 * Test that update_actor refuses to overwrite a different actor than the one sending the activity.
	 *
	 * Regression: an Update whose object.id points at another host's cached actor must not
	 * overwrite it — an actor may only update itself.
	 *
	 * @covers ::update_actor
	 */
	public function test_update_actor_rejects_cross_actor_overwrite() {
		$victim_url   = 'https://victim.example/users/alice';
		$attacker_url = 'https://attacker.example/users/evil';

		$victim_actor = array(
			'type'              => 'Person',
			'id'                => $victim_url,
			'name'              => 'Alice',
			'preferredUsername' => 'alice',
			'inbox'             => $victim_url . '/inbox',
			'outbox'            => $victim_url . '/outbox',
			'followers'         => $victim_url . '/followers',
			'following'         => $victim_url . '/following',
			'publicKey'         => array(
				'id'           => $victim_url . '#main-key',
				'owner'        => $victim_url,
				'publicKeyPem' => "-----BEGIN PUBLIC KEY-----\nMIIBIjANBgkqhkiG9w0BAQE\n-----END PUBLIC KEY-----",
			),
		);

		// Cache the victim's actor.
		Remote_Actors::upsert( $victim_actor );
		$this->assertNotWPError( Remote_Actors::get_by_uri( $victim_url ), 'Victim actor should be cached.' );

		// Attacker sends an Update whose object points at the victim's actor.
		$activity = array(
			'type'   => 'Update',
			'actor'  => $attacker_url,
			'object' => \array_merge( $victim_actor, array( 'name' => 'Hacked' ) ),
		);

		Update::update_actor( $activity, array( $this->user_id ) );

		// The victim's cached actor must be unchanged.
		$post  = Remote_Actors::get_by_uri( $victim_url );
		$actor = Remote_Actors::get_actor( $post );
		$this->assertEquals( 'Alice', $actor->get_name(), 'A remote actor must not be overwritable by a different actor.' );
	}

	/**
	 * Test that update_actor falls back to remote fetch when object is a string IRI.
	 *
	 * @covers ::update_actor
	 */
	public function test_update_actor_fetches_remotely_for_string_iri() {
		$actor_url = 'https://example.com/users/iri-test';

		$remote_actor = array(
			'type'              => 'Person',
			'id'                => $actor_url,
			'name'              => 'Fetched Remotely',
			'preferredUsername' => 'iritest',
			'inbox'             => $actor_url . '/inbox',
			'outbox'            => $actor_url . '/outbox',
			'followers'         => $actor_url . '/followers',
			'following'         => $actor_url . '/following',
			'publicKey'         => array(
				'id'           => $actor_url . '#main-key',
				'owner'        => $actor_url,
				'publicKeyPem' => "-----BEGIN PUBLIC KEY-----\nMIIBIjANBgkqhkiG9w0BAQE\n-----END PUBLIC KEY-----",
			),
		);

		// Activity with string IRI instead of embedded object.
		$activity = array(
			'type'   => 'Update',
			'actor'  => $actor_url,
			'object' => $actor_url,
		);

		// Mock HTTP to return valid actor data.
		$mock_response = function () use ( $remote_actor ) {
			return $remote_actor;
		};
		\add_filter( 'activitypub_pre_http_get_remote_object', $mock_response );

		Update::update_actor( $activity, array( $this->user_id ) );

		\remove_filter( 'activitypub_pre_http_get_remote_object', $mock_response );

		$post  = Remote_Actors::get_by_uri( $actor_url );
		$actor = Remote_Actors::get_actor( $post );
		$this->assertEquals( 'Fetched Remotely', $actor->get_name(), 'Should fetch remotely when object is a string IRI.' );
	}

	/**
	 * Test that update_actor fires activitypub_handled_update with WP_Error when fetch fails.
	 *
	 * @covers ::update_actor
	 */
	public function test_update_actor_fires_error_on_failed_fetch() {
		$activity = array(
			'type'   => 'Update',
			'actor'  => 'https://example.com/users/missing',
			'object' => 'https://example.com/users/missing',
		);

		// Mock HTTP to return an error.
		$mock_error = function () {
			return new \WP_Error( 'http_error', 'Could not fetch' );
		};
		\add_filter( 'activitypub_pre_http_get_remote_object', $mock_error );

		$state    = null;
		$listener = function ( $act, $uids, $s ) use ( &$state ) {
			$state = $s;
		};
		\add_action( 'activitypub_handled_update', $listener, 10, 3 );

		Update::update_actor( $activity, array( $this->user_id ) );

		\remove_filter( 'activitypub_pre_http_get_remote_object', $mock_error );
		\remove_action( 'activitypub_handled_update', $listener );

		$this->assertWPError( $state, 'Should fire activitypub_handled_update with WP_Error when remote fetch fails.' );
		$this->assertEquals( 'activitypub_update_failed', $state->get_error_code() );
	}

	/**
	 * Test that update_actor always fires activitypub_handled_update exactly once.
	 *
	 * @covers ::update_actor
	 */
	public function test_update_actor_fires_action_exactly_once() {
		$actor_url = 'https://example.com/users/action-count';

		$actor_data = array(
			'type'              => 'Person',
			'id'                => $actor_url,
			'name'              => 'Action Counter',
			'preferredUsername' => 'actioncount',
			'inbox'             => $actor_url . '/inbox',
			'outbox'            => $actor_url . '/outbox',
			'followers'         => $actor_url . '/followers',
			'following'         => $actor_url . '/following',
			'publicKey'         => array(
				'id'           => $actor_url . '#main-key',
				'owner'        => $actor_url,
				'publicKeyPem' => "-----BEGIN PUBLIC KEY-----\nMIIBIjANBgkqhkiG9w0BAQE\n-----END PUBLIC KEY-----",
			),
		);

		$fire_count = 0;
		$listener   = function () use ( &$fire_count ) {
			++$fire_count;
		};
		\add_action( 'activitypub_handled_update', $listener );

		// Test with embedded object (success path).
		$activity = array(
			'type'   => 'Update',
			'actor'  => $actor_url,
			'object' => $actor_data,
		);

		Update::update_actor( $activity, array( $this->user_id ) );
		$this->assertSame( 1, $fire_count, 'Action should fire exactly once on success.' );

		// Test with failed fetch (error path).
		$fire_count = 0;
		$mock_error = function () {
			return new \WP_Error( 'http_error', 'fail' );
		};
		\add_filter( 'activitypub_pre_http_get_remote_object', $mock_error );

		$activity['object'] = 'https://example.com/users/nonexistent';
		Update::update_actor( $activity, array( $this->user_id ) );

		\remove_filter( 'activitypub_pre_http_get_remote_object', $mock_error );
		\remove_action( 'activitypub_handled_update', $listener );

		$this->assertSame( 1, $fire_count, 'Action should fire exactly once on error.' );
	}

	/**
	 * Data provider for update_actor tests.
	 *
	 * @return array Test cases with activity data, HTTP response, expected outcome, and description.
	 */
	public function update_actor_provider() {
		$valid_actor_object = array(
			'type'              => 'Person',
			'id'                => 'https://example.com/users/testuser',
			'name'              => 'Test User',
			'preferredUsername' => 'testuser',
			'inbox'             => 'https://example.com/users/testuser/inbox',
			'outbox'            => 'https://example.com/users/testuser/outbox',
			'followers'         => 'https://example.com/users/testuser/followers',
			'following'         => 'https://example.com/users/testuser/following',
			'publicKey'         => array(
				'id'           => 'https://example.com/users/testuser#main-key',
				'owner'        => 'https://example.com/users/testuser',
				'publicKeyPem' => '-----BEGIN PUBLIC KEY-----\nMIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA0Rdj53hR4AdsiRcqt1Fd\nF8YWepMN9K/B8xwKRI7P4x4w6c+4S8FRRvJOyJr3xhXvCgFNSM+a2v1rYMRLKIAa\nPJUZ1jPKGrPDv/zc25eFoMB1YqSq1FozYh+zdsEtiXj4Nd4o0rn3OnFAHYeYiroJ\nQkEYy4pV3CCXZODhYwvwPmJUZ4/uJVWJHlN6Og==\n-----END PUBLIC KEY-----',
			),
		);

		return array(
			'valid_actor_update' => array(
				array(
					'type'   => 'Update',
					'actor'  => 'https://example.com/users/testuser',
					'object' => $valid_actor_object,
				),
				$valid_actor_object,
				'success',
				'Should successfully update valid actor',
			),
			'updated_name'       => array(
				array(
					'type'   => 'Update',
					'actor'  => 'https://example.com/users/testuser2',
					'object' => array_merge(
						$valid_actor_object,
						array(
							'id'   => 'https://example.com/users/testuser2',
							'name' => 'Updated Name',
						)
					),
				),
				array_merge(
					$valid_actor_object,
					array(
						'id'   => 'https://example.com/users/testuser2',
						'name' => 'Updated Name',
					)
				),
				'success',
				'Should successfully update actor name',
			),
			'nonexistent_actor'  => array(
				array(
					'type'   => 'Update',
					'actor'  => 'https://example.com/nonexistent',
					'object' => array( 'type' => 'Person' ),
				),
				new \WP_Error( 'not_found', 'Actor not found' ),
				'error',
				'Should handle non-existent actor gracefully',
			),
		);
	}
}
