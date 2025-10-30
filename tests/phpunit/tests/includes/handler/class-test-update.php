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
	 * Test that the activitypub_inbox_create fallback is triggered.
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
			'object' => array(
				'id'      => 'https://example.com/objects/12345',
				'type'    => 'Note',
				'content' => 'Test note',
			),
		);

		// Add a fallback handler for the action.
		\add_action(
			'activitypub_inbox_create',
			function ( $activity_data ) use ( &$called, $test_actor ) {
				if ( isset( $activity_data['actor'] ) && $activity_data['actor'] === $test_actor ) {
					$called = true;
				}
			},
			10,
			3
		);

		// Call the handler via the new handled_inbox_update hook.
		\do_action( 'activitypub_handled_inbox_update', $activity, array( $this->user_id ), null );

		$this->assertTrue( $called, 'The fallback activitypub_inbox_create action should be triggered.' );

		// Clean up by removing the action.
		\remove_all_actions( 'activitypub_inbox_create' );
		\remove_all_actions( 'activitypub_handled_inbox_update' );
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
			return array(
				'response' => array( 'code' => 200 ),
				'body'     => wp_json_encode( $http_response ),
			);
		};

		// Mock HTTP request.
		\add_filter( 'pre_http_request', $fake_request, 10 );

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

		\remove_filter( 'pre_http_request', $fake_request, 10 );
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
