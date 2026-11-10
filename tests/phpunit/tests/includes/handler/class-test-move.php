<?php
/**
 * Test file for Activitypub Move Handler.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Handler;

use Activitypub\Activity\Actor;
use Activitypub\Collection\Followers;
use Activitypub\Collection\Following;
use Activitypub\Collection\Inbox;
use Activitypub\Collection\Outbox;
use Activitypub\Collection\Remote_Actors;
use Activitypub\Collection\Remote_Posts;
use Activitypub\Handler\Move;
use Activitypub\Moderation;

use function Activitypub\get_id_hash;
use function Activitypub\object_to_uri;

/**
 * Test class for the Move handler.
 *
 * @coversDefaultClass \Activitypub\Handler\Move
 */
class Test_Move extends \WP_UnitTestCase {
	/**
	 * The user ID.
	 *
	 * @var int
	 */
	private $user_id;

	/**
	 * The second user ID.
	 *
	 * @var int
	 */
	private $user_id_2;

	/**
	 * Set up the test.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->user_id   = self::factory()->user->create();
		$this->user_id_2 = self::factory()->user->create();
	}

	/**
	 * Test the handle_move method with a target and origin.
	 */
	public function test_handle_move_with_target_and_origin() {
		$target = 'https://example.com/new-profile';
		$origin = 'https://example.com/old-profile';

		// Mock the HTTP response for the origin object.
		$origin_object = array(
			'type'    => 'Person',
			'id'      => $origin,
			'url'     => $origin,
			'name'    => 'Old Profile',
			'inbox'   => 'https://example.com/old-profile/inbox',
			'movedTo' => $target,
		);

		// Mock the HTTP response for the target object.
		$target_object = array(
			'type'        => 'Person',
			'id'          => $target,
			'url'         => $target,
			'name'        => 'New Profile',
			'inbox'       => 'https://example.com/new-profile/inbox',
			'alsoKnownAs' => array(
				$origin,
			),
		);

		$id = Remote_Actors::upsert( $origin_object );

		// Add the user ID meta value.
		\add_post_meta( $id, Followers::FOLLOWER_META_KEY, $this->user_id );

		$filter = function ( $pre, $url_or_object ) use ( $target, $target_object, $origin, $origin_object ) {
			$url = object_to_uri( $url_or_object );
			if ( $url === $target ) {
				return $target_object;
			}
			if ( $url === $origin ) {
				return $origin_object;
			}

			return $pre;
		};

		// Mock the HTTP request.
		add_filter( 'activitypub_pre_http_get_remote_object', $filter, 10, 2 );

		$activity = array(
			'type'   => 'Move',
			'actor'  => $origin,
			'object' => $target,
		);

		Move::handle_move( $activity, 1 );

		$old_follower     = Remote_Actors::get_by_uri( $origin );
		$updated_follower = Remote_Actors::get_by_uri( $target );

		$this->assertWPError( $old_follower );
		$this->assertNotNull( $updated_follower );
		$this->assertEquals( $target, $updated_follower->guid );
		$this->assertSame( get_id_hash( $target ), $updated_follower->post_name );

		\remove_filter( 'activitypub_pre_http_get_remote_object', $filter );
	}

	/**
	 * A Move whose target document is served under a mismatched cross-host id must not
	 * cache anything under that id. The self-confirming fetch re-fetches the declared
	 * id from its own host; when that does not confirm, the target cannot be resolved
	 * and the move does not proceed.
	 */
	public function test_handle_move_does_not_cache_cross_host_target_id() {
		$target_url    = 'https://example.org/target';
		$origin        = 'https://example.org/origin';
		$mismatched_id = 'https://example.com/users/alice';

		$documents = array(
			// The target document (served at $target_url) claims the canonical actor's id.
			$target_url    => array(
				'type'        => 'Person',
				'id'          => $mismatched_id,
				'url'         => $target_url,
				'inbox'       => 'https://example.org/target/inbox',
				'alsoKnownAs' => array( $origin ),
			),
			// The canonical actor's real server does not confirm the mismatched id (it serves another id).
			$mismatched_id => array(
				'type'  => 'Person',
				'id'    => 'https://example.net/someone-else',
				'inbox' => 'https://example.net/someone-else/inbox',
			),
		);

		$origin_object        = array(
			'type'    => 'Person',
			'id'      => $origin,
			'url'     => $origin,
			'inbox'   => 'https://example.org/origin/inbox',
			'movedTo' => $mismatched_id,
		);
		$documents[ $origin ] = $origin_object;

		$id = Remote_Actors::upsert( $origin_object );
		\add_post_meta( $id, Followers::FOLLOWER_META_KEY, $this->user_id );

		$filter = function ( $pre, $args, $url ) use ( $documents ) {
			if ( ! \array_key_exists( $url, $documents ) ) {
				return $pre;
			}
			return array(
				'headers'  => array(),
				'body'     => \wp_json_encode( $documents[ $url ] ),
				'response' => array(
					'code'    => 200,
					'message' => 'OK',
				),
				'cookies'  => array(),
			);
		};
		\add_filter( 'pre_http_request', $filter, 10, 3 );

		Move::handle_move(
			array(
				'type'   => 'Move',
				'actor'  => $origin,
				'object' => $target_url,
			),
			1
		);

		\remove_filter( 'pre_http_request', $filter );

		$this->assertWPError( Remote_Actors::get_by_uri( $mismatched_id ), 'The mismatched target id must not be cached.' );
	}

	/**
	 * Test the handle_move method with an invalid target.
	 *
	 * @covers ::verify_move
	 */
	public function test_handle_move_with_invalid_target() {
		$target = 'https://example.com/new-profile';
		$origin = 'https://example.com/old-profile';

		// Create a follower for the origin.
		$id = Remote_Actors::upsert(
			array(
				'inbox' => 'https://example.com/old-profile/inbox',
				'name'  => 'Old Profile',
				'type'  => 'Person',
				'id'    => $origin,
				'url'   => $origin,
			)
		);

		// Add the user ID meta value.
		\add_post_meta( $id, Followers::FOLLOWER_META_KEY, $this->user_id );

		// Mock HTTP request to return invalid data.
		$filter = function () {
			return array(
				'type' => 'Invalid',
			);
		};
		\add_filter( 'activitypub_pre_http_get_remote_object', $filter, 10, 2 );

		$activity = array(
			'type'   => 'Move',
			'actor'  => $origin,
			'object' => $target,
		);

		Move::handle_move( $activity, 1 );

		// Assert that the original follower still exists and wasn't modified.
		$existing_follower = Followers::get_by_uri( $this->user_id, $origin );
		$this->assertNotNull( $existing_follower );
		$this->assertEquals( $origin, $existing_follower->guid );

		// Assert that no new follower was created for the target.
		$target_follower = Followers::get_by_uri( $this->user_id, $target );
		$this->assertWPError( $target_follower );

		// Cleanup.
		\remove_filter( 'activitypub_pre_http_get_remote_object', $filter );
	}

	/**
	 * Test the handle_move method without a target or origin.
	 *
	 * @covers ::verify_move
	 */
	public function test_handle_move_without_target_or_origin() {
		// Create a test follower to ensure it's not affected.
		$test_follower = new Actor();
		$test_follower->set_inbox( 'https://example.com/test/inbox' );
		$test_follower->set_name( 'Test Profile' );
		$test_follower->set_type( 'Person' );
		$test_follower->set_id( 'https://example.com/test-profile' );
		$test_follower->set_url( 'https://example.com/test-profile' );

		$id = Remote_Actors::upsert( $test_follower );

		// Add the user ID meta value.
		\add_post_meta( $id, Followers::FOLLOWER_META_KEY, $this->user_id );

		// Store initial followers count.
		$initial_followers = Followers::get_many( $this->user_id );
		$initial_count     = count( $initial_followers );

		$activity = array(
			'type' => 'Move',
		);

		Move::handle_move( $activity, 1 );

		// Verify that no followers were added or removed.
		$final_followers = Followers::get_many( $this->user_id );
		$this->assertEquals( $initial_count, count( $final_followers ) );

		// Verify that our test follower remains unchanged.
		$existing_follower = Followers::get_by_uri( $this->user_id, 'https://example.com/test-profile' );
		$this->assertNotNull( $existing_follower );

		$actor = Remote_Actors::get_actor( $existing_follower );

		$this->assertEquals( 'https://example.com/test-profile', $actor->get_id() );
		$this->assertEquals( 'https://example.com/test/inbox', $actor->get_inbox() );

		// Cleanup.
		$test_follower->delete();
	}

	/**
	 * Test the handle_move method when target exists but origin does not.
	 *
	 * This tests the scenario where the new profile is already followed,
	 * but the old profile is not. In this case, no action should be taken.
	 */
	public function test_handle_move_with_existing_target_but_missing_origin() {
		$target = 'https://example.com/new-profile';
		$origin = 'https://example.com/old-profile';

		// Create only the target follower, not the origin.
		$target_follower = new Actor();
		$target_follower->set_inbox( 'https://example.com/new-profile/inbox' );
		$target_follower->set_type( 'Person' );
		$target_follower->set_id( $target );
		$target_follower->set_url( $target );
		$target_id = Remote_Actors::upsert( $target_follower );

		// Add user ID to target.
		\add_post_meta( $target_id, Followers::FOLLOWER_META_KEY, $this->user_id );

		$filter = function ( $preempt, $args, $url ) use ( $target, $origin ) {
			if ( $url === $target ) {
				return array(
					'body'     => \wp_json_encode(
						array(
							'type'        => 'Person',
							'id'          => $target,
							'url'         => $target,
							'name'        => 'New Profile',
							'inbox'       => 'https://example.com/new-profile/inbox',
							'alsoKnownAs' => array( $origin ),
						)
					),
					'response' => array( 'code' => 200 ),
				);
			}
			if ( $url === $origin ) {
				return array(
					'body'     => \wp_json_encode(
						array(
							'type'    => 'Person',
							'id'      => $origin,
							'url'     => $origin,
							'name'    => 'Old Profile',
							'inbox'   => 'https://example.com/old-profile/inbox',
							'movedTo' => $target,
						)
					),
					'response' => array( 'code' => 200 ),
				);
			}
			return $preempt;
		};

		// Mock the HTTP request.
		\add_filter( 'pre_http_request', $filter, 10, 3 );

		$activity = array(
			'type'   => 'Move',
			'actor'  => $origin,
			'object' => $target,
		);

		Move::handle_move( $activity, 1 );

		// Verify that the target still exists with the same followers.
		$target_users = \get_post_meta( $target_id, Followers::FOLLOWER_META_KEY, false );
		$this->assertContains( (string) $this->user_id, $target_users );
		$this->assertCount( 1, $target_users );

		// Verify that no origin follower was created.
		$this->assertWPError( Remote_Actors::get_by_uri( $origin ) );

		// Cleanup.
		\remove_filter( 'pre_http_request', $filter );
	}

	/**
	 * Test the handle_move method with an existing target and origin.
	 */
	public function test_handle_move_with_existing_target_and_origin() {
		$target = 'https://example.com/new-profile';
		$origin = 'https://example.com/old-profile';

		// Create followers for target and origin.
		$target_follower = new Actor();
		$target_follower->set_inbox( 'https://example.com/new-profile/inbox' );
		$target_follower->set_type( 'Person' );
		$target_follower->set_id( $target );
		$target_follower->set_url( $target );
		$target_id = Remote_Actors::upsert( $target_follower );

		$origin_follower = new Actor();
		$origin_follower->set_inbox( 'https://example.com/old-profile/inbox' );
		$origin_follower->set_type( 'Person' );
		$origin_follower->set_id( $origin );
		$origin_follower->set_url( $origin );
		$origin_id = Remote_Actors::upsert( $origin_follower );

		// Add user IDs.
		\add_post_meta( $origin_id, Followers::FOLLOWER_META_KEY, $this->user_id );
		\add_post_meta( $origin_id, Followers::FOLLOWER_META_KEY, $this->user_id_2 );
		\add_post_meta( $target_id, Followers::FOLLOWER_META_KEY, $this->user_id );

		// A comment and a Reader post of the origin.
		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID' => self::factory()->post->create(),
				'comment_meta'    => array(
					'protocol'                     => 'activitypub',
					'_activitypub_remote_actor_id' => $origin_id,
				),
			)
		);
		$reader_id  = self::factory()->post->create(
			array(
				'post_type'  => Remote_Posts::POST_TYPE,
				'meta_input' => array( '_activitypub_remote_actor_id' => $origin_id ),
			)
		);

		// Clear the cache.
		\wp_cache_delete( $origin_id, 'posts' );
		\wp_cache_delete( $target_id, 'posts' );

		// Warm the inbox caches the Move has to invalidate.
		$this->assertSame( array( 'https://example.com/old-profile/inbox' ), Followers::get_inboxes( $this->user_id_2 ) );
		$this->assertContains( 'https://example.com/old-profile/inbox', Remote_Actors::get_inboxes() );

		$filter = function ( $pre, $url_or_object ) use ( $target, $origin ) {
			$url = object_to_uri( $url_or_object );

			if ( $url === $target ) {
				return array(
					'type'        => 'Person',
					'id'          => $target,
					'url'         => $target,
					'name'        => 'New Profile',
					'inbox'       => 'https://example.com/new-profile/inbox',
					'alsoKnownAs' => array(
						$origin,
					),
				);
			}

			if ( $url === $origin ) {
				return array(
					'type'    => 'Person',
					'id'      => $origin,
					'url'     => $origin,
					'name'    => 'Old Profile',
					'inbox'   => 'https://example.com/old-profile/inbox',
					'movedTo' => $target,
				);
			}

			return $pre;
		};

		// Mock the HTTP request.
		\add_filter( 'activitypub_pre_http_get_remote_object', $filter, 10, 2 );

		$activity = array(
			'type'   => 'Move',
			'actor'  => $origin,
			'object' => $target,
		);

		Move::handle_move( $activity, 1 );

		// Check if the user IDs were moved correctly.
		$target_users = \get_post_meta( $target_id, Followers::FOLLOWER_META_KEY, false );

		$this->assertContains( (string) $this->user_id, $target_users );
		$this->assertContains( (string) $this->user_id_2, $target_users );

		// Check if the origin follower was deleted.
		$this->assertWPError( Remote_Actors::get_by_uri( $origin ) );

		// The origin's comments and Reader posts belong to the target now.
		$this->assertEquals( $target_id, \get_comment_meta( $comment_id, '_activitypub_remote_actor_id', true ) );
		$this->assertEquals( $target_id, \get_post_meta( $reader_id, '_activitypub_remote_actor_id', true ) );

		// The moved follower's inbox list points at the target now, and the origin is gone everywhere.
		$this->assertSame( array( 'https://example.com/new-profile/inbox' ), Followers::get_inboxes( $this->user_id_2 ) );
		$this->assertNotContains( 'https://example.com/old-profile/inbox', Remote_Actors::get_inboxes() );

		\remove_filter( 'activitypub_pre_http_get_remote_object', $filter );
	}

	/**
	 * Test that verify_move accepts alsoKnownAs entries given as objects.
	 */
	public function test_handle_move_matches_also_known_as_object() {
		$target = 'https://example.com/new-profile3';
		$origin = 'https://example.com/old-profile3';

		$origin_object = array(
			'type'    => 'Person',
			'id'      => $origin,
			'inbox'   => 'https://example.com/old-profile3/inbox',
			'movedTo' => $target,
		);

		$target_object = array(
			'type'        => 'Person',
			'id'          => $target,
			'inbox'       => 'https://example.com/new-profile3/inbox',
			'alsoKnownAs' => array(
				array(
					'id'   => $origin,
					'type' => 'Person',
				),
			),
		);

		$id = Remote_Actors::upsert( $origin_object );
		\add_post_meta( $id, Followers::FOLLOWER_META_KEY, $this->user_id );

		$filter = function ( $pre, $url_or_object ) use ( $target, $target_object, $origin, $origin_object ) {
			$objects = array(
				$target => $target_object,
				$origin => $origin_object,
			);
			return $objects[ object_to_uri( $url_or_object ) ] ?? $pre;
		};
		\add_filter( 'activitypub_pre_http_get_remote_object', $filter, 10, 2 );

		Move::handle_move(
			array(
				'type'   => 'Move',
				'actor'  => $origin,
				'object' => $target,
			),
			1
		);

		\remove_filter( 'activitypub_pre_http_get_remote_object', $filter );

		$this->assertSame( $target, Remote_Actors::get_by_uri( $target )->guid, 'The move should be applied.' );
	}

	/**
	 * Test that verify_move does not accept the origin's url in place of its id.
	 */
	public function test_handle_move_ignores_origin_url() {
		$target = 'https://example.com/new-profile';
		$origin = 'https://example.com/old-profile';

		$origin_object = array(
			'type'    => 'Person',
			'id'      => $origin,
			'url'     => 'https://example.com/@oldprofile',
			'name'    => 'Old Profile',
			'inbox'   => 'https://example.com/old-profile/inbox',
			'movedTo' => $target,
		);

		// Target's alsoKnownAs contains the origin's url, not its id.
		$target_object = array(
			'type'        => 'Person',
			'id'          => $target,
			'url'         => $target,
			'name'        => 'New Profile',
			'inbox'       => 'https://example.com/new-profile/inbox',
			'alsoKnownAs' => array(
				'https://example.com/@oldprofile',
			),
		);

		$id = Remote_Actors::upsert( $origin_object );
		\add_post_meta( $id, Followers::FOLLOWER_META_KEY, $this->user_id );

		$filter = function ( $pre, $url_or_object ) use ( $target, $target_object, $origin, $origin_object ) {
			$url = object_to_uri( $url_or_object );
			if ( $url === $target ) {
				return $target_object;
			}
			if ( $url === $origin ) {
				return $origin_object;
			}
			return $pre;
		};

		\add_filter( 'activitypub_pre_http_get_remote_object', $filter, 10, 2 );

		$activity = array(
			'type'   => 'Move',
			'actor'  => $origin,
			'object' => $target,
		);

		Move::handle_move( $activity, 1 );

		\remove_filter( 'activitypub_pre_http_get_remote_object', $filter );

		$this->assertWPError( Remote_Actors::get_by_uri( $target ), 'The move must not be applied.' );
		$this->assertSame( $id, Remote_Actors::get_by_uri( $origin )->ID, 'The origin must stay as it is.' );
	}

	/**
	 * Test that verify_move does not accept the origin's webfinger in place of its id.
	 */
	public function test_handle_move_ignores_origin_webfinger() {
		$target = 'https://example.com/new-profile2';
		$origin = 'https://example.com/old-profile2';

		$origin_object = array(
			'type'      => 'Person',
			'id'        => $origin,
			'url'       => $origin,
			'name'      => 'Old Profile',
			'inbox'     => 'https://example.com/old-profile2/inbox',
			'movedTo'   => $target,
			'webfinger' => 'olduser@example.com',
		);

		// Target's alsoKnownAs contains the origin's webfinger, not its id.
		$target_object = array(
			'type'        => 'Person',
			'id'          => $target,
			'url'         => $target,
			'name'        => 'New Profile',
			'inbox'       => 'https://example.com/new-profile2/inbox',
			'alsoKnownAs' => array(
				'olduser@example.com',
			),
		);

		$id = Remote_Actors::upsert( $origin_object );
		\add_post_meta( $id, Followers::FOLLOWER_META_KEY, $this->user_id );

		$filter = function ( $pre, $url_or_object ) use ( $target, $target_object, $origin, $origin_object ) {
			$url = object_to_uri( $url_or_object );
			if ( $url === $target ) {
				return $target_object;
			}
			if ( $url === $origin ) {
				return $origin_object;
			}
			return $pre;
		};

		\add_filter( 'activitypub_pre_http_get_remote_object', $filter, 10, 2 );

		$activity = array(
			'type'   => 'Move',
			'actor'  => $origin,
			'object' => $target,
		);

		Move::handle_move( $activity, 1 );

		\remove_filter( 'activitypub_pre_http_get_remote_object', $filter );

		$this->assertWPError( Remote_Actors::get_by_uri( $target ), 'The move must not be applied.' );
		$this->assertSame( $id, Remote_Actors::get_by_uri( $origin )->ID, 'The origin must stay as it is.' );
	}

	/**
	 * A Mastodon Move (old actor as `object`, new one as `target`) makes our users follow the new actor.
	 *
	 * @covers ::handle_move
	 */
	public function test_handle_move_follows_target_for_following_users() {
		$origin = 'https://old.example/users/alice';
		$target = 'https://new.example/users/alice';

		\get_user_by( 'id', $this->user_id )->add_cap( 'activitypub' );
		$id = Remote_Actors::upsert( $this->actor( $origin, array( 'movedTo' => $target ) ) );
		\add_post_meta( $id, Following::FOLLOWING_META_KEY, (string) $this->user_id );

		$filter = $this->mock_remote(
			array(
				$origin => $this->actor( $origin, array( 'movedTo' => $target ) ),
				$target => $this->actor( $target, array( 'alsoKnownAs' => array( $origin ) ) ),
			)
		);

		Move::handle_move(
			array(
				'type'   => 'Move',
				'actor'  => $origin,
				'object' => $origin,
				'target' => $target,
			),
			$this->user_id
		);

		\remove_filter( 'activitypub_pre_http_get_remote_object', $filter );

		$moved = Remote_Actors::get_by_uri( $target );
		$this->assertSame( $id, $moved->ID, 'The stored actor must be moved in place.' );
		$this->assertNotContains( (string) $this->user_id, \get_post_meta( $id, Following::FOLLOWING_META_KEY, false ), 'The new server has not accepted yet.' );
		$this->assertContains( (string) $this->user_id, \get_post_meta( $id, Following::PENDING_META_KEY, false ) );
		$this->assertCount( 1, $this->get_follows( $this->user_id, $target ), 'A Follow must be sent to the new actor.' );
	}

	/**
	 * A merge keeps blocks of the old actor and follows the new one for our users.
	 *
	 * @covers ::handle_move
	 */
	public function test_handle_move_merge_keeps_blocks_and_follows() {
		$origin = 'https://old.example/users/bob';
		$target = 'https://new.example/users/bob';

		\get_user_by( 'id', $this->user_id )->add_cap( 'activitypub' );
		$origin_id = Remote_Actors::upsert( $this->actor( $origin, array( 'movedTo' => $target ) ) );
		$target_id = Remote_Actors::upsert( $this->actor( $target, array( 'alsoKnownAs' => array( $origin ) ) ) );
		\add_post_meta( $origin_id, Following::FOLLOWING_META_KEY, (string) $this->user_id );
		\add_post_meta( $origin_id, Moderation::BLOCKED_ACTORS_META_KEY, (string) $this->user_id_2 );

		$filter = $this->mock_remote(
			array(
				$origin => $this->actor( $origin, array( 'movedTo' => $target ) ),
				$target => $this->actor( $target, array( 'alsoKnownAs' => array( $origin ) ) ),
			)
		);

		Move::handle_move(
			array(
				'type'   => 'Move',
				'actor'  => $origin,
				'object' => $origin,
				'target' => $target,
			),
			$this->user_id
		);

		\remove_filter( 'activitypub_pre_http_get_remote_object', $filter );

		$this->assertWPError( Remote_Actors::get_by_uri( $origin ), 'The old actor must be merged away.' );
		$this->assertContains( (string) $this->user_id_2, \get_post_meta( $target_id, Moderation::BLOCKED_ACTORS_META_KEY, false ), 'The block must carry over.' );
		$this->assertTrue( Moderation::is_actor_blocked( $target, $this->user_id_2 ) );
		$this->assertContains( (string) $this->user_id, \get_post_meta( $target_id, Following::PENDING_META_KEY, false ) );
		$this->assertCount( 1, $this->get_follows( $this->user_id, $target ) );
	}

	/**
	 * Our users do not follow a new actor they block.
	 *
	 * @covers ::handle_move
	 */
	public function test_handle_move_does_not_follow_blocked_target() {
		$origin = 'https://old.example/users/carol';
		$target = 'https://new.example/users/carol';

		\get_user_by( 'id', $this->user_id )->add_cap( 'activitypub' );
		Moderation::add_user_block( $this->user_id, Moderation::TYPE_DOMAIN, 'new.example' );
		$id = Remote_Actors::upsert( $this->actor( $origin, array( 'movedTo' => $target ) ) );
		\add_post_meta( $id, Following::FOLLOWING_META_KEY, (string) $this->user_id );

		$filter = $this->mock_remote(
			array(
				$origin => $this->actor( $origin, array( 'movedTo' => $target ) ),
				$target => $this->actor( $target, array( 'alsoKnownAs' => array( $origin ) ) ),
			)
		);

		Move::handle_move(
			array(
				'type'   => 'Move',
				'actor'  => $origin,
				'object' => $origin,
				'target' => $target,
			),
			$this->user_id
		);

		\remove_filter( 'activitypub_pre_http_get_remote_object', $filter );

		$this->assertEmpty( \get_post_meta( $id, Following::FOLLOWING_META_KEY, false ) );
		$this->assertEmpty( \get_post_meta( $id, Following::PENDING_META_KEY, false ) );
		$this->assertCount( 0, $this->get_follows( $this->user_id, $target ) );
	}

	/**
	 * A Move sent by the new actor (pull mode) is accepted when the old actor points at it.
	 *
	 * @covers ::handle_move
	 */
	public function test_handle_move_pull_mode() {
		$origin = 'https://old.example/users/dave';
		$target = 'https://new.example/users/dave';

		$id     = Remote_Actors::upsert( $this->actor( $origin ) );
		$filter = $this->mock_remote(
			array(
				$origin => $this->actor( $origin, array( 'movedTo' => $target ) ),
				$target => $this->actor( $target, array( 'alsoKnownAs' => array( $origin ) ) ),
			)
		);

		Move::handle_move(
			array(
				'type'   => 'Move',
				'actor'  => $target,
				'object' => $origin,
				'target' => $target,
			),
			$this->user_id
		);

		\remove_filter( 'activitypub_pre_http_get_remote_object', $filter );

		$this->assertSame( $id, Remote_Actors::get_by_uri( $target )->ID );
	}

	/**
	 * A `copiedTo` origin stays active, so it is not merged away.
	 *
	 * @covers ::handle_move
	 */
	public function test_handle_move_ignores_copied_to() {
		$origin = 'https://old.example/users/gina';
		$target = 'https://new.example/users/gina';

		$id     = Remote_Actors::upsert( $this->actor( $origin ) );
		$filter = $this->mock_remote(
			array(
				$origin => $this->actor( $origin, array( 'copiedTo' => $target ) ),
				$target => $this->actor( $target, array( 'alsoKnownAs' => array( $origin ) ) ),
			)
		);

		Move::handle_move(
			array(
				'type'   => 'Move',
				'actor'  => $origin,
				'object' => $origin,
				'target' => $target,
			),
			$this->user_id
		);

		\remove_filter( 'activitypub_pre_http_get_remote_object', $filter );

		$this->assertSame( $id, Remote_Actors::get_by_uri( $origin )->ID );
		$this->assertWPError( Remote_Actors::get_by_uri( $target ) );
	}

	/**
	 * A remote actor can not move to an actor on this site.
	 *
	 * @covers ::handle_move
	 */
	public function test_handle_move_rejects_local_target() {
		$origin = 'https://old.example/users/hank';
		$target = \home_url( '/?author=' . $this->user_id );

		$id     = Remote_Actors::upsert( $this->actor( $origin ) );
		$filter = $this->mock_remote(
			array(
				$origin => $this->actor( $origin, array( 'movedTo' => $target ) ),
				$target => $this->actor( $target, array( 'alsoKnownAs' => array( $origin ) ) ),
			)
		);

		Move::handle_move(
			array(
				'type'   => 'Move',
				'actor'  => $origin,
				'object' => $origin,
				'target' => $target,
			),
			$this->user_id
		);

		\remove_filter( 'activitypub_pre_http_get_remote_object', $filter );

		$this->assertSame( $id, Remote_Actors::get_by_uri( $origin )->ID );
	}

	/**
	 * A Move to an actor the site blocks is not applied.
	 *
	 * @covers ::handle_move
	 */
	public function test_handle_move_rejects_site_blocked_target() {
		$origin = 'https://old.example/users/ivy';
		$target = 'https://blocked.example/users/ivy';

		Moderation::add_site_block( Moderation::TYPE_DOMAIN, 'blocked.example' );
		$id     = Remote_Actors::upsert( $this->actor( $origin ) );
		$filter = $this->mock_remote(
			array(
				$origin => $this->actor( $origin, array( 'movedTo' => $target ) ),
				$target => $this->actor( $target, array( 'alsoKnownAs' => array( $origin ) ) ),
			)
		);

		Move::handle_move(
			array(
				'type'   => 'Move',
				'actor'  => $origin,
				'object' => $origin,
				'target' => $target,
			),
			$this->user_id
		);

		\remove_filter( 'activitypub_pre_http_get_remote_object', $filter );
		Moderation::remove_site_block( Moderation::TYPE_DOMAIN, 'blocked.example' );

		$this->assertSame( $id, Remote_Actors::get_by_uri( $origin )->ID );
		$this->assertWPError( Remote_Actors::get_by_uri( $target ) );
	}

	/**
	 * A user who blocks the new actor does not keep it as a follower.
	 *
	 * @covers ::handle_move
	 */
	public function test_handle_move_drops_follower_blocked_by_user() {
		$origin = 'https://old.example/users/jack';
		$target = 'https://new.example/users/jack';

		Moderation::add_user_block( $this->user_id, Moderation::TYPE_DOMAIN, 'new.example' );
		$id = Remote_Actors::upsert( $this->actor( $origin ) );
		\add_post_meta( $id, Followers::FOLLOWER_META_KEY, (string) $this->user_id );
		\add_post_meta( $id, Followers::FOLLOWER_META_KEY, (string) $this->user_id_2 );

		$filter = $this->mock_remote(
			array(
				$origin => $this->actor( $origin, array( 'movedTo' => $target ) ),
				$target => $this->actor( $target, array( 'alsoKnownAs' => array( $origin ) ) ),
			)
		);

		Move::handle_move(
			array(
				'type'   => 'Move',
				'actor'  => $origin,
				'object' => $origin,
				'target' => $target,
			),
			$this->user_id
		);

		\remove_filter( 'activitypub_pre_http_get_remote_object', $filter );

		$followers = \get_post_meta( $id, Followers::FOLLOWER_META_KEY, false );
		$this->assertNotContains( (string) $this->user_id, $followers );
		$this->assertContains( (string) $this->user_id_2, $followers );
	}

	/**
	 * The shared inbox hands one Move to every recipient; it is handled once, on the shared hook.
	 *
	 * @covers ::handle_move
	 */
	public function test_handle_move_runs_once_from_shared_inbox() {
		$origin   = 'https://old.example/users/kim';
		$activity = array(
			'type'   => 'Move',
			'actor'  => $origin,
			'object' => $origin,
			'target' => 'https://new.example/users/kim',
		);

		Remote_Actors::upsert( $this->actor( $origin ) );

		$fetches = 0;
		$filter  = function ( $pre, $url_or_object ) use ( &$fetches, $origin ) {
			++$fetches;
			return object_to_uri( $url_or_object ) === $origin ? $this->actor( $origin ) : $pre;
		};
		\add_filter( 'activitypub_pre_http_get_remote_object', $filter, 10, 2 );

		\do_action( 'activitypub_inbox_move', $activity, $this->user_id, null, Inbox::CONTEXT_SHARED_INBOX );
		\do_action( 'activitypub_inbox_move', $activity, $this->user_id_2, null, Inbox::CONTEXT_SHARED_INBOX );
		$per_recipient = $fetches;

		\do_action( 'activitypub_inbox_shared_move', $activity, array( $this->user_id, $this->user_id_2 ), null, Inbox::CONTEXT_SHARED_INBOX );

		\remove_filter( 'activitypub_pre_http_get_remote_object', $filter );

		$this->assertSame( 0, $per_recipient, 'The per-recipient calls must not fetch.' );
		$this->assertGreaterThan( 0, $fetches, 'The shared hook handles the Move.' );
	}

	/**
	 * A target id with a query string ends up on the moved post, not on a second one.
	 *
	 * @covers ::handle_move
	 */
	public function test_handle_move_target_with_query_string() {
		$origin = 'https://old.example/users/lena';
		$target = 'https://new.example/?author=7&type=actor';

		\get_user_by( 'id', $this->user_id )->add_cap( 'activitypub' );
		$id = Remote_Actors::upsert( $this->actor( $origin ) );
		\add_post_meta( $id, Following::FOLLOWING_META_KEY, (string) $this->user_id );

		$filter = $this->mock_remote(
			array(
				$origin => $this->actor( $origin, array( 'movedTo' => $target ) ),
				$target => $this->actor( $target, array( 'alsoKnownAs' => array( $origin ) ) ),
			)
		);

		Move::handle_move(
			array(
				'type'   => 'Move',
				'actor'  => $origin,
				'object' => $origin,
				'target' => $target,
			),
			$this->user_id
		);

		\remove_filter( 'activitypub_pre_http_get_remote_object', $filter );

		$this->assertSame( $id, Remote_Actors::get_by_uri( $target )->ID, 'Only the moved post may hold the new id.' );
		$this->assertContains( (string) $this->user_id, \get_post_meta( $id, Following::PENDING_META_KEY, false ) );

		$actors = \get_posts(
			array(
				'post_type'   => Remote_Actors::POST_TYPE,
				'post_status' => 'any',
				'fields'      => 'ids',
				'numberposts' => -1,
			)
		);
		$this->assertSame( array( $id ), $actors, 'No second actor may be created.' );
	}

	/**
	 * A new actor can not take over an old one that does not point at it.
	 *
	 * @covers ::handle_move
	 */
	public function test_handle_move_pull_mode_requires_origin_consent() {
		$origin = 'https://old.example/users/erin';
		$target = 'https://new.example/users/erin';

		$id     = Remote_Actors::upsert( $this->actor( $origin ) );
		$filter = $this->mock_remote(
			array(
				$origin => $this->actor( $origin ),
				$target => $this->actor( $target, array( 'alsoKnownAs' => array( $origin ) ) ),
			)
		);

		Move::handle_move(
			array(
				'type'   => 'Move',
				'actor'  => $target,
				'object' => $origin,
				'target' => $target,
			),
			$this->user_id
		);

		\remove_filter( 'activitypub_pre_http_get_remote_object', $filter );

		$this->assertWPError( Remote_Actors::get_by_uri( $target ) );
		$this->assertSame( $id, Remote_Actors::get_by_uri( $origin )->ID );
	}

	/**
	 * Only the old or the new actor may send the Move.
	 *
	 * @covers ::handle_move
	 */
	public function test_handle_move_rejects_third_party_actor() {
		$origin = 'https://old.example/users/frank';
		$target = 'https://new.example/users/frank';

		$id     = Remote_Actors::upsert( $this->actor( $origin ) );
		$filter = $this->mock_remote(
			array(
				$origin => $this->actor( $origin, array( 'movedTo' => $target ) ),
				$target => $this->actor( $target, array( 'alsoKnownAs' => array( $origin ) ) ),
			)
		);

		Move::handle_move(
			array(
				'type'   => 'Move',
				'actor'  => 'https://third.example/users/mallory',
				'object' => $origin,
				'target' => $target,
			),
			$this->user_id
		);

		\remove_filter( 'activitypub_pre_http_get_remote_object', $filter );

		$this->assertWPError( Remote_Actors::get_by_uri( $target ) );
		$this->assertSame( $id, Remote_Actors::get_by_uri( $origin )->ID );
	}

	/**
	 * Any actor type can move, not only a Person.
	 *
	 * @covers ::handle_move
	 */
	public function test_handle_move_accepts_group() {
		$origin = 'https://old.example/@blog';
		$target = 'https://new.example/@blog';

		$id     = Remote_Actors::upsert( $this->actor( $origin, array( 'type' => 'Group' ) ) );
		$filter = $this->mock_remote(
			array(
				$origin => $this->actor(
					$origin,
					array(
						'type'    => 'Group',
						'movedTo' => $target,
					)
				),
				$target => $this->actor(
					$target,
					array(
						'type'        => 'Group',
						'alsoKnownAs' => array( $origin ),
					)
				),
			)
		);

		Move::handle_move(
			array(
				'type'   => 'Move',
				'actor'  => $origin,
				'object' => $origin,
				'target' => $target,
			),
			$this->user_id
		);

		\remove_filter( 'activitypub_pre_http_get_remote_object', $filter );

		$this->assertSame( $id, Remote_Actors::get_by_uri( $target )->ID );
	}

	/**
	 * A Move for an actor we do not store fetches nothing.
	 *
	 * @covers ::handle_move
	 */
	public function test_handle_move_skips_unknown_origin() {
		$fetches = 0;
		$filter  = static function ( $pre ) use ( &$fetches ) {
			++$fetches;
			return $pre;
		};
		\add_filter( 'activitypub_pre_http_get_remote_object', $filter );

		Move::handle_move(
			array(
				'type'   => 'Move',
				'actor'  => 'https://old.example/users/unknown',
				'object' => 'https://old.example/users/unknown',
				'target' => 'https://new.example/users/unknown',
			),
			$this->user_id
		);

		\remove_filter( 'activitypub_pre_http_get_remote_object', $filter );

		$this->assertSame( 0, $fetches );
	}

	/**
	 * A user who can no longer follow does not end up following the new actor without a Follow.
	 *
	 * @covers ::handle_move
	 */
	public function test_handle_move_does_not_keep_following_without_follow() {
		$origin = 'https://old.example/users/mia';
		$target = 'https://new.example/users/mia';

		$id = Remote_Actors::upsert( $this->actor( $origin ) );
		\add_post_meta( $id, Following::FOLLOWING_META_KEY, (string) $this->user_id );

		$filter = $this->mock_remote(
			array(
				$origin => $this->actor( $origin, array( 'movedTo' => $target ) ),
				$target => $this->actor( $target, array( 'alsoKnownAs' => array( $origin ) ) ),
			)
		);

		Move::handle_move(
			array(
				'type'   => 'Move',
				'actor'  => $origin,
				'object' => $origin,
				'target' => $target,
			),
			$this->user_id
		);

		\remove_filter( 'activitypub_pre_http_get_remote_object', $filter );

		$this->assertNotContains( (string) $this->user_id, \get_post_meta( $id, Following::FOLLOWING_META_KEY, false ), 'The new server never got a Follow.' );
		$this->assertCount( 0, $this->get_follows( $this->user_id, $target ) );
	}

	/**
	 * A Move to a site-blocked target fetches nothing.
	 *
	 * @covers ::handle_move
	 */
	public function test_handle_move_does_not_fetch_blocked_target() {
		$origin = 'https://old.example/users/noa';
		$target = 'https://blocked.example/users/noa';

		Moderation::add_site_block( Moderation::TYPE_DOMAIN, 'blocked.example' );
		Remote_Actors::upsert( $this->actor( $origin ) );

		$fetched = array();
		$filter  = static function ( $pre, $url_or_object ) use ( &$fetched ) {
			$fetched[] = object_to_uri( $url_or_object );
			return $pre;
		};
		\add_filter( 'activitypub_pre_http_get_remote_object', $filter, 10, 2 );

		Move::handle_move(
			array(
				'type'   => 'Move',
				'actor'  => $origin,
				'object' => $origin,
				'target' => $target,
			),
			$this->user_id
		);

		\remove_filter( 'activitypub_pre_http_get_remote_object', $filter );
		Moderation::remove_site_block( Moderation::TYPE_DOMAIN, 'blocked.example' );

		$this->assertNotContains( $target, $fetched );
	}

	/**
	 * A target that can not be stored (no inbox) leaves the old actor as it was.
	 *
	 * @covers ::handle_move
	 */
	public function test_handle_move_keeps_origin_when_target_can_not_be_stored() {
		$origin = 'https://old.example/users/ola';
		$target = 'https://new.example/users/ola';

		$id     = Remote_Actors::upsert( $this->actor( $origin ) );
		$broken = $this->actor( $target, array( 'alsoKnownAs' => array( $origin ) ) );
		unset( $broken['inbox'] );

		$filter = $this->mock_remote(
			array(
				$origin => $this->actor( $origin, array( 'movedTo' => $target ) ),
				$target => $broken,
			)
		);

		Move::handle_move(
			array(
				'type'   => 'Move',
				'actor'  => $origin,
				'object' => $origin,
				'target' => $target,
			),
			$this->user_id
		);

		\remove_filter( 'activitypub_pre_http_get_remote_object', $filter );

		$this->assertSame( $id, Remote_Actors::get_by_uri( $origin )->ID, 'The old actor must stay where it was.' );
		$this->assertWPError( Remote_Actors::get_by_uri( $target ) );
	}

	/**
	 * An update that fails without an error (wp_update_post() returns 0) leaves the old actor as it was.
	 *
	 * @covers ::handle_move
	 */
	public function test_handle_move_keeps_origin_when_update_fails() {
		$origin = 'https://old.example/users/uma';
		$target = 'https://new.example/users/uma';

		$id = Remote_Actors::upsert( $this->actor( $origin ) );

		$filter = $this->mock_remote(
			array(
				$origin => $this->actor( $origin, array( 'movedTo' => $target ) ),
				$target => $this->actor( $target, array( 'alsoKnownAs' => array( $origin ) ) ),
			)
		);
		\add_filter( 'wp_insert_post_empty_content', '__return_true' );

		Move::handle_move(
			array(
				'type'   => 'Move',
				'actor'  => $origin,
				'object' => $origin,
				'target' => $target,
			),
			$this->user_id
		);

		\remove_filter( 'wp_insert_post_empty_content', '__return_true' );
		\remove_filter( 'activitypub_pre_http_get_remote_object', $filter );

		$this->assertSame( $id, Remote_Actors::get_by_uri( $origin )->ID, 'The old actor must stay where it was.' );
		$this->assertWPError( Remote_Actors::get_by_uri( $target ) );
	}

	/**
	 * A merge stores the fresh copy of the new actor, so the new Follows reach its current inbox.
	 *
	 * @covers ::handle_move
	 */
	public function test_handle_move_merge_refreshes_target() {
		$origin = 'https://old.example/users/vic';
		$target = 'https://new.example/users/vic';

		Remote_Actors::upsert( $this->actor( $origin ) );
		$target_id = Remote_Actors::upsert( $this->actor( $target, array( 'inbox' => 'https://new.example/old-inbox' ) ) );

		$filter = $this->mock_remote(
			array(
				$origin => $this->actor( $origin, array( 'movedTo' => $target ) ),
				$target => $this->actor( $target, array( 'alsoKnownAs' => array( $origin ) ) ),
			)
		);

		Move::handle_move(
			array(
				'type'   => 'Move',
				'actor'  => $origin,
				'object' => $origin,
				'target' => $target,
			),
			$this->user_id
		);

		\remove_filter( 'activitypub_pre_http_get_remote_object', $filter );

		$this->assertSame( $target . '/inbox', \get_post_meta( $target_id, '_activitypub_inbox', true ) );
	}

	/**
	 * Build a remote actor document.
	 *
	 * @param string $id    The actor id.
	 * @param array  $extra Extra properties.
	 *
	 * @return array The actor document.
	 */
	private function actor( $id, $extra = array() ) {
		return \array_merge(
			array(
				'type'  => 'Person',
				'id'    => $id,
				'url'   => $id,
				'name'  => 'Actor',
				'inbox' => $id . '/inbox',
			),
			$extra
		);
	}

	/**
	 * Serve remote documents by URL.
	 *
	 * @param array $documents The documents, keyed by URL.
	 *
	 * @return callable The filter, to remove it again.
	 */
	private function mock_remote( $documents ) {
		$filter = static function ( $pre, $url_or_object ) use ( $documents ) {
			return $documents[ object_to_uri( $url_or_object ) ] ?? $pre;
		};
		\add_filter( 'activitypub_pre_http_get_remote_object', $filter, 10, 2 );

		return $filter;
	}

	/**
	 * Get the Follow activities a user sent to an actor.
	 *
	 * @param int    $user_id  The user ID.
	 * @param string $actor_id The followed actor id.
	 *
	 * @return int[] The outbox post IDs.
	 */
	private function get_follows( $user_id, $actor_id ) {
		return \get_posts(
			array(
				'post_type'   => Outbox::POST_TYPE,
				'post_status' => 'any',
				'author'      => $user_id,
				'fields'      => 'ids',
				'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => '_activitypub_object_id',
						'value' => $actor_id,
					),
					array(
						'key'   => '_activitypub_activity_type',
						'value' => 'Follow',
					),
				),
			)
		);
	}
}
