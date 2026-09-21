<?php
/**
 * Unit tests for the Activitypub Reject handler.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Handler;

use Activitypub\Collection\Following;
use Activitypub\Collection\Outbox;
use Activitypub\Collection\Remote_Actors;
use Activitypub\Handler\Reject;
use Activitypub\Transformer\Post;

use function Activitypub\get_object_id;

/**
 * Class Test_Reject
 *
 * @coversDefaultClass \Activitypub\Handler\Reject
 */
class Test_Reject extends \WP_UnitTestCase {

	/**
	 * Test user ID.
	 *
	 * @var int
	 */
	protected static $user_id;

	/**
	 * Remote object mock for the quoted object and its author.
	 *
	 * @var callable
	 */
	protected $remote_object_filter;

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
		\get_user_by( 'id', self::$user_id )->add_cap( 'activitypub' );
	}

	/**
	 * Mock the remote objects the quote tests fetch.
	 */
	public function set_up() {
		parent::set_up();

		// The object our own quote posts quote, and its author.
		$this->remote_object_filter = function ( $pre, $url ) {
			if ( 'https://remote.example/notes/1' === $url ) {
				return array(
					'id'           => 'https://remote.example/notes/1',
					'type'         => 'Note',
					'attributedTo' => 'https://remote.example/users/alice',
				);
			}
			if ( 'https://remote.example/users/alice' === $url ) {
				return array(
					'id'    => 'https://remote.example/users/alice',
					'type'  => 'Person',
					'inbox' => 'https://remote.example/users/alice/inbox',
				);
			}
			return $pre;
		};
		\add_filter( 'activitypub_pre_http_get_remote_object', $this->remote_object_filter, 10, 2 );
	}

	/**
	 * Remove the remote object mock.
	 */
	public function tear_down() {
		\remove_filter( 'activitypub_pre_http_get_remote_object', $this->remote_object_filter );
		parent::tear_down();
	}

	/**
	 * Test validate_object with various scenarios.
	 *
	 * @dataProvider validate_object_provider
	 * @covers ::validate_object
	 *
	 * @param array  $request_data     The request data to test.
	 * @param bool   $input_valid      The input valid state.
	 * @param bool   $expected_result  The expected validation result.
	 * @param string $description      Description of the test case.
	 */
	public function test_validate_object( $request_data, $input_valid, $expected_result, $description ) {
		$request = $this->createMock( 'WP_REST_Request' );
		$request->method( 'get_json_params' )->willReturn( $request_data );

		$result = Reject::validate_object( $input_valid, 'param', $request );

		$this->assertEquals( $expected_result, $result, $description );
	}

	/**
	 * Data provider for validate_object tests.
	 *
	 * @return array Test cases with request data, input valid state, expected result, and description.
	 */
	public function validate_object_provider() {
		return array(
			// Invalid cases.
			'missing_type'            => array(
				array(),
				true,
				false,
				'Should return false when type is missing',
			),
			'missing_required_fields' => array(
				array(
					'type'  => 'Reject',
					'actor' => 'foo',
				),
				true,
				false,
				'Should return false when required fields are missing',
			),
			// Valid cases - non-Reject type should pass through.
			'type_not_reject'         => array(
				array( 'type' => 'Follow' ),
				true,
				true,
				'Should return true when type is not Reject',
			),
			// Valid Reject activity.
			'valid_reject_activity'   => array(
				array(
					'type'   => 'Reject',
					'actor'  => 'foo',
					'object' => array(
						'id'     => 'bar',
						'actor'  => 'foo',
						'type'   => 'Follow',
						'object' => 'foo',
					),
				),
				true,
				true,
				'Should return true for valid Reject activity',
			),
			// Test with input_valid false.
			'input_valid_false'       => array(
				array( 'type' => 'Follow' ),
				false,
				false,
				'Should preserve input_valid when type is not Reject',
			),
		);
	}

	/**
	 * Functional test: handle_reject keeps user in pending and does not move to following meta.
	 */
	public function test_handle_reject_keeps_user_in_pending() {
		$user_id     = self::$user_id;
		$object_guid = 'https://example.com/actor/123';
		$outbox_guid = 'https://example.com/outbox/123';

		$outbox_post_id = self::factory()->post->create(
			array(
				'post_type'   => Outbox::POST_TYPE,
				'post_status' => 'publish',
				'guid'        => $outbox_guid,
			)
		);

		\add_post_meta( $outbox_post_id, '_activitypub_activity_type', 'Follow' );

		// Create remote actor post.
		$post_id = self::factory()->post->create(
			array(
				'post_type'   => Remote_Actors::POST_TYPE,
				'post_status' => 'publish',
				'guid'        => $object_guid,
			)
		);

		// Add user to pending.
		\add_post_meta( $post_id, Following::PENDING_META_KEY, (string) $user_id );

		// Confirm precondition.
		$pending = \get_post_meta( $post_id, Following::PENDING_META_KEY, false );
		$this->assertContains( (string) $user_id, $pending );

		// Prepare reject array as expected by handle_reject, using the real outbox guid.
		// The sender (top-level actor) must be the actor that was followed.
		$reject = array(
			'type'   => 'Reject',
			'actor'  => $object_guid,
			'object' => array(
				'id'     => $outbox_guid,
				'actor'  => 'https://example.com/follower/123',
				'type'   => 'Follow',
				'object' => $object_guid,
			),
		);

		// Call the handler.
		Reject::handle_reject( $reject, $user_id );

		\clean_post_cache( $post_id );

		// Assert: user_id is NOT in _activitypub_followed_by.
		$following = \get_post_meta( $post_id, Following::FOLLOWING_META_KEY, false );
		$this->assertNotContains( (string) $user_id, $following );

		// Assert: user_id is STILL in _activitypub_followed_by_pending.
		$pending = \get_post_meta( $post_id, Following::PENDING_META_KEY, false );
		$this->assertNotContains( (string) $user_id, $pending );
	}

	/**
	 * A Reject whose sender is not the followed actor must be ignored.
	 *
	 * Guards against any peer cancelling a user's Follow of an unrelated actor
	 * by referencing that pending Follow's outbox GUID.
	 */
	public function test_handle_reject_rejects_actor_object_mismatch() {
		$user_id     = self::$user_id;
		$object_guid = 'https://example.com/actor/123';
		$outbox_guid = 'https://example.com/outbox/123';

		$outbox_post_id = self::factory()->post->create(
			array(
				'post_type'   => Outbox::POST_TYPE,
				'post_status' => 'publish',
				'guid'        => $outbox_guid,
			)
		);

		\add_post_meta( $outbox_post_id, '_activitypub_activity_type', 'Follow' );

		// Create remote actor post the local user is following.
		$post_id = self::factory()->post->create(
			array(
				'post_type'   => Remote_Actors::POST_TYPE,
				'post_status' => 'publish',
				'guid'        => $object_guid,
			)
		);

		\add_post_meta( $post_id, Following::FOLLOWING_META_KEY, (string) $user_id );

		// Reject signed by an unrelated actor, pointing at the victim's Follow GUID
		// and naming the followed actor so it would be unfollowed if unguarded.
		$reject = array(
			'type'   => 'Reject',
			'actor'  => 'https://evil.example/actor/999',
			'object' => array(
				'id'     => $outbox_guid,
				'actor'  => $object_guid,
				'type'   => 'Follow',
				'object' => $object_guid,
			),
		);

		Reject::handle_reject( $reject, $user_id );

		\clean_post_cache( $post_id );

		// Assert: the follow relationship is untouched.
		$following = \get_post_meta( $post_id, Following::FOLLOWING_META_KEY, false );
		$this->assertContains( (string) $user_id, $following );
	}

	/*
	 * ------------------------------------------------------------------
	 * Rejects of our own QuoteRequests (FEP-044f).
	 * ------------------------------------------------------------------
	 */

	/**
	 * Create a published quote post and return its ID.
	 *
	 * @param string $url The quoted URL.
	 *
	 * @return int Post ID.
	 */
	private function create_quote_post( $url = 'https://remote.example/notes/1' ) {
		return self::factory()->post->create(
			array(
				'post_author'  => self::$user_id,
				'post_status'  => 'publish',
				'post_content' => '<!-- wp:activitypub/quote {"url":"' . $url . '"} /-->',
			)
		);
	}

	/**
	 * Return the QuoteRequest outbox items for a post.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return \WP_Post[] Outbox items.
	 */
	private function get_quote_requests( $post_id ) {
		$items = \get_posts(
			array(
				'post_type'   => Outbox::POST_TYPE,
				'post_status' => 'any',
				'numberposts' => -1,
				'meta_key'    => '_activitypub_activity_type', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'  => 'QuoteRequest', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);

		// The outbox stores the quoted URI as object id; our post is the request's instrument.
		return \array_values(
			\array_filter(
				$items,
				function ( $item ) use ( $post_id ) {
					$activity = \json_decode( $item->post_content, true );
					return get_object_id( \get_post( $post_id ) ) === ( $activity['instrument'] ?? '' );
				}
			)
		);
	}

	/**
	 * Build the Reject the quoted author's server would send for our request.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $actor   Sender.
	 *
	 * @return array Reject activity.
	 */
	private function build_reject( $post_id, $actor = 'https://remote.example/users/alice' ) {
		$requests = $this->get_quote_requests( $post_id );

		return array(
			'type'   => 'Reject',
			'actor'  => $actor,
			'object' => array(
				'id'         => $requests ? $requests[0]->guid : '',
				'type'       => 'QuoteRequest',
				'actor'      => \get_author_posts_url( self::$user_id ),
				'object'     => 'https://remote.example/notes/1',
				'instrument' => get_object_id( \get_post( $post_id ) ),
			),
		);
	}

	/**
	 * Count Update outbox items for a post.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return int Count.
	 */
	private function count_updates( $post_id ) {
		return \count(
			\get_posts(
				array(
					'post_type'   => Outbox::POST_TYPE,
					'post_status' => 'any',
					'numberposts' => -1,
					'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						array(
							'key'   => '_activitypub_activity_type',
							'value' => 'Update',
						),
						array(
							'key'   => '_activitypub_object_id',
							'value' => get_object_id( \get_post( $post_id ) ),
						),
					),
				)
			)
		);
	}

	/**
	 * A Reject marks the quote declined, drops any stamp and queues an Update.
	 *
	 * @covers ::reject_quote_request
	 */
	public function test_reject_marks_declined_and_updates() {
		$post_id = $this->create_quote_post();
		\update_post_meta( $post_id, '_activitypub_quote_authorization', 'https://remote.example/stamps/1' );
		$before = $this->count_updates( $post_id );

		Reject::handle_reject( $this->build_reject( $post_id ), self::$user_id );

		$this->assertSame( '1', \get_post_meta( $post_id, '_activitypub_quote_rejected', true ) );
		$this->assertEmpty( \get_post_meta( $post_id, '_activitypub_quote_authorization', true ) );
		$this->assertSame( $before + 1, $this->count_updates( $post_id ) );

		$array = Post::transform( \get_post( $post_id ) )->to_object()->to_array();
		$this->assertArrayNotHasKey( 'quote', $array );
	}

	/**
	 * A Reject for a request the post has since superseded is ignored.
	 *
	 * @covers ::reject_quote_request
	 */
	public function test_reject_for_superseded_url_ignored() {
		$post_id = $this->create_quote_post();
		$reject  = $this->build_reject( $post_id );
		\update_post_meta( $post_id, '_activitypub_quote_request', 'https://remote.example/notes/2' );
		$before = $this->count_updates( $post_id );

		Reject::handle_reject( $reject, self::$user_id );

		$this->assertEmpty( \get_post_meta( $post_id, '_activitypub_quote_rejected', true ) );
		$this->assertSame( 'https://remote.example/notes/2', \get_post_meta( $post_id, '_activitypub_quote_request', true ) );
		$this->assertSame( $before, $this->count_updates( $post_id ) );
	}

	/**
	 * A Reject from someone other than the quoted author is ignored.
	 *
	 * @covers ::reject_quote_request
	 */
	public function test_reject_from_wrong_actor_ignored() {
		$post_id = $this->create_quote_post();

		Reject::handle_reject( $this->build_reject( $post_id, 'https://remote.example/users/mallory' ), self::$user_id );

		$this->assertEmpty( \get_post_meta( $post_id, '_activitypub_quote_rejected', true ) );
	}
}
