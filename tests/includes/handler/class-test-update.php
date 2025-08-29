<?php
/**
 * Test Update Handler.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Handler;

use Activitypub\Activity\Actor;
use Activitypub\Collection\Actors;
use Activitypub\Collection\Followers;
use Activitypub\Handler\Update;

/**
 * Update Handler Test Class.
 *
 * @coversDefaultClass \Activitypub\Handler\Update
 */
class Test_Update extends \WP_UnitTestCase {

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
	 * Test updating an actor.
	 *
	 * @covers ::update_actor
	 */
	public function test_update_actor() {
		// Prepare test data.
		$actor_url = 'https://example.com/users/testuser';
		$activity  = array(
			'type'   => 'Update',
			'actor'  => $actor_url,
			'object' => array(
				'type'              => 'Person',
				'id'                => $actor_url,
				'name'              => 'Test User',
				'preferredUsername' => 'testuser',
				'inbox'             => 'https://example.com/users/testuser/inbox',
				'outbox'            => 'https://example.com/users/testuser/outbox',
				'followers'         => 'https://example.com/users/testuser/followers',
				'following'         => 'https://example.com/users/testuser/following',
				'publicKey'         => array(
					'id'           => $actor_url . '#main-key',
					'owner'        => $actor_url,
					'publicKeyPem' => '-----BEGIN PUBLIC KEY-----\nMIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA0Rdj53hR4AdsiRcqt1Fd\nF8YWepMN9K/B8xwKRI7P4x4w6c+4S8FRRvJOyJr3xhXvCgFNSM+a2v1rYMRLKIAa\nPJUZ1jPKGrPDv/zc25eFoMB1YqSq1FozYh+zdsEtiXj4Nd4o0rn3OnFAHYeYiroJ\nQkEYy4pV3CCXZODhYwvwPmJUZ4/uJVWJHlN6Og==\n-----END PUBLIC KEY-----',
				),
			),
		);

		$fake_request = function () use ( $activity ) {
			return array(
				'response' => array( 'code' => 200 ),
				'body'     => wp_json_encode( $activity['object'] ),
			);
		};

		// Mock of get_remote_metadata_by_actor function.
		\add_filter( 'pre_http_request', $fake_request, 10 );

		// Execute the update_actor method.
		Update::update_actor( $activity );

		// Check that the follower was correctly updated.
		$follower = Actors::get_remote_by_uri( $actor_url );

		$this->assertNotNull( $follower );

		$follower_initial = Actors::get_actor( Followers::add_follower( $this->user_id, $actor_url ) );
		$follower_from_db = Actors::get_actor( Actors::get_remote_by_uri( $actor_url ) );

		$this->assertInstanceOf( Actor::class, $follower_initial );
		$this->assertInstanceOf( Actor::class, $follower_from_db );
		$this->assertEquals( $follower_initial->get_id(), $follower_from_db->get_id() );
		$this->assertEquals( 'Test User', $follower_from_db->get_name() );

		remove_filter( 'pre_http_request', $fake_request, 10 );

		$activity['object']['name'] = 'Updated Name';

		$fake_request = function () use ( $activity ) {
			return array(
				'response' => array( 'code' => 200 ),
				'body'     => wp_json_encode( $activity['object'] ),
			);
		};

		// Mock of get_remote_metadata_by_actor function.
		\add_filter( 'pre_http_request', $fake_request, 10 );

		Update::update_actor( $activity );

		\clean_post_cache( $follower_initial->get_id() );

		$follower = Actors::get_remote_by_uri( $actor_url );
		$follower = Actors::get_actor( $follower );

		$this->assertInstanceOf( Actor::class, $follower );
		$this->assertEquals( $activity['object']['name'], $follower->get_name() );
		$this->assertEquals( $activity['object']['preferredUsername'], $follower->get_preferred_username() );
		$this->assertEquals( $activity['object']['inbox'], $follower->get_inbox() );

		\remove_filter( 'pre_http_request', $fake_request, 10 );
	}

	/**
	 * Test updating a non-existent actor.
	 *
	 * @covers ::update_actor
	 */
	public function test_update_nonexistent_actor() {
		$activity = array(
			'type'   => 'Update',
			'actor'  => 'https://example.com/nonexistent',
			'object' => array(
				'type' => 'Person',
			),
		);

		$fake_request = function () {
			return new \WP_Error( 'not_found', 'Actor not found' );
		};

		// Mock of get_remote_metadata_by_actor function to return an error.
		\add_filter( 'pre_http_request', $fake_request, 10 );

		// Execute the update_actor method.
		Update::update_actor( $activity );

		// Check that no follower was created.
		$follower = Actors::get_remote_by_uri( 'https://example.com/nonexistent' );
		$this->assertWPError( $follower );

		remove_filter( 'pre_http_request', $fake_request, 10 );
	}
}
