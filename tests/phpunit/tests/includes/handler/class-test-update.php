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
use Activitypub\Scheduler\Post;

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

		// Prevent wp_update_post() from triggering the full outbox chain.
		\remove_action( 'wp_after_insert_post', array( Post::class, 'triage' ), 33 );

		$this->user_id = self::factory()->user->create();
	}

	/**
	 * Tear down the test.
	 */
	public function tear_down() {
		\add_action( 'wp_after_insert_post', array( Post::class, 'triage' ), 33, 4 );

		parent::tear_down();
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
	 * Test outgoing Update with a Note updates the post.
	 *
	 * @covers ::outgoing
	 */
	public function test_outgoing_updates_post() {
		$post_id = self::factory()->post->create(
			array(
				'post_author'  => $this->user_id,
				'post_title'   => 'Original Title',
				'post_content' => 'Original content',
				'post_status'  => 'publish',
			)
		);

		$permalink = \get_permalink( $post_id );

		$data = array(
			'type'   => 'Update',
			'object' => array(
				'type'    => 'Note',
				'id'      => $permalink,
				'content' => 'Updated content',
				'name'    => 'Updated Title',
				'summary' => 'Updated summary',
			),
		);

		Update::outgoing( $data, $this->user_id, null, 0 );

		$post = \get_post( $post_id );
		$this->assertEquals( 'Updated Title', $post->post_title );
		$this->assertEquals( 'Updated content', $post->post_content );
		$this->assertEquals( 'Updated summary', $post->post_excerpt );
	}

	/**
	 * Test outgoing Update generates title from content for Notes without name.
	 *
	 * @covers ::outgoing
	 */
	public function test_outgoing_generates_title_from_content() {
		$post_id = self::factory()->post->create(
			array(
				'post_author' => $this->user_id,
				'post_status' => 'publish',
			)
		);

		$permalink = \get_permalink( $post_id );

		$data = array(
			'type'   => 'Update',
			'object' => array(
				'type'    => 'Note',
				'id'      => $permalink,
				'content' => 'This is a short note without a title field.',
			),
		);

		Update::outgoing( $data, $this->user_id, null, 0 );

		$post = \get_post( $post_id );
		$this->assertNotEmpty( $post->post_title );
		$this->assertStringContainsString( 'This is a short', $post->post_title );
	}

	/**
	 * Test outgoing Update ignores non-Note/Article types.
	 *
	 * @covers ::outgoing
	 */
	public function test_outgoing_ignores_unsupported_types() {
		$post_id = self::factory()->post->create(
			array(
				'post_author' => $this->user_id,
				'post_title'  => 'Original',
				'post_status' => 'publish',
			)
		);

		$permalink = \get_permalink( $post_id );

		$data = array(
			'type'   => 'Update',
			'object' => array(
				'type'    => 'Event',
				'id'      => $permalink,
				'content' => 'Should not update',
			),
		);

		Update::outgoing( $data, $this->user_id, null, 0 );

		$post = \get_post( $post_id );
		$this->assertEquals( 'Original', $post->post_title );
	}

	/**
	 * Test outgoing Update skips posts not owned by user.
	 *
	 * @covers ::outgoing
	 */
	public function test_outgoing_skips_unowned_post() {
		$other_user = self::factory()->user->create();
		$post_id    = self::factory()->post->create(
			array(
				'post_author' => $other_user,
				'post_title'  => 'Other User Post',
				'post_status' => 'publish',
			)
		);

		$permalink = \get_permalink( $post_id );

		$data = array(
			'type'   => 'Update',
			'object' => array(
				'type'    => 'Note',
				'id'      => $permalink,
				'content' => 'Should not update',
				'name'    => 'Hijacked',
			),
		);

		Update::outgoing( $data, $this->user_id, null, 0 );

		$post = \get_post( $post_id );
		$this->assertEquals( 'Other User Post', $post->post_title );
	}

	/**
	 * Test outgoing Update returns early for non-array object.
	 *
	 * @covers ::outgoing
	 */
	public function test_outgoing_returns_early_for_string_object() {
		$data = array(
			'type'   => 'Update',
			'object' => 'https://example.com/note/1',
		);

		// Should not throw errors.
		Update::outgoing( $data, $this->user_id, null, 0 );
		$this->assertTrue( true );
	}

	/**
	 * Test outgoing Update returns early for empty object ID.
	 *
	 * @covers ::outgoing
	 */
	public function test_outgoing_returns_early_for_empty_id() {
		$data = array(
			'type'   => 'Update',
			'object' => array(
				'type'    => 'Note',
				'content' => 'No ID provided',
			),
		);

		// Should not throw errors.
		Update::outgoing( $data, $this->user_id, null, 0 );
		$this->assertTrue( true );
	}

	/**
	 * Test outgoing Update fires action hook on success.
	 *
	 * @covers ::outgoing
	 */
	public function test_outgoing_fires_action() {
		$post_id = self::factory()->post->create(
			array(
				'post_author' => $this->user_id,
				'post_status' => 'publish',
			)
		);

		$permalink = \get_permalink( $post_id );
		$fired     = false;

		$callback = function () use ( &$fired ) {
			$fired = true;
		};
		\add_action( 'activitypub_outbox_updated_post', $callback );

		$data = array(
			'type'   => 'Update',
			'object' => array(
				'type'    => 'Note',
				'id'      => $permalink,
				'content' => 'Updated',
			),
		);

		Update::outgoing( $data, $this->user_id, null, 0 );

		$this->assertTrue( $fired, 'activitypub_outbox_updated_post action should fire.' );

		\remove_action( 'activitypub_outbox_updated_post', $callback );
	}

	/**
	 * Test outgoing Update recursion guard prevents infinite loop.
	 *
	 * @covers ::outgoing
	 */
	public function test_outgoing_recursion_guard() {
		$post_id = self::factory()->post->create(
			array(
				'post_author' => $this->user_id,
				'post_status' => 'publish',
				'post_title'  => 'Original',
			)
		);

		$permalink  = \get_permalink( $post_id );
		$call_count = 0;

		// Hook into the update action to count calls and re-trigger.
		$callback = function () use ( &$call_count, $permalink ) {
			++$call_count;

			// Simulate what the scheduler would do: re-trigger the outgoing handler.
			$data = array(
				'type'   => 'Update',
				'object' => array(
					'type'    => 'Note',
					'id'      => $permalink,
					'content' => 'Re-triggered',
				),
			);
			Update::outgoing( $data, 0, null, 0 );
		};
		\add_action( 'activitypub_outbox_updated_post', $callback );

		$data = array(
			'type'   => 'Update',
			'object' => array(
				'type'    => 'Note',
				'id'      => $permalink,
				'content' => 'First update',
			),
		);

		Update::outgoing( $data, $this->user_id, null, 0 );

		// Should only fire once due to recursion guard.
		$this->assertEquals( 1, $call_count, 'Recursion guard should prevent re-entrant calls.' );

		\remove_action( 'activitypub_outbox_updated_post', $callback );
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
