<?php
/**
 * Unit tests for the Activitypub Accept handler.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Handler;

use Activitypub\Collection\Following;
use Activitypub\Collection\Outbox;
use Activitypub\Collection\Remote_Actors;
use Activitypub\Handler\Accept;
use Activitypub\Handler\Reject;

use function Activitypub\get_object_id;

/**
 * Class Test_Accept
 *
 * @coversDefaultClass \Activitypub\Handler\Accept
 */
class Test_Accept extends \WP_UnitTestCase {

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

		$result = Accept::validate_object( $input_valid, 'param', $request );

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
					'type'  => 'Accept',
					'actor' => 'foo',
				),
				true,
				false,
				'Should return false when required fields are missing',
			),
			// Valid cases - non-Accept type should pass through.
			'type_not_accept'         => array(
				array( 'type' => 'Follow' ),
				true,
				true,
				'Should return true when type is not Accept',
			),
			// Valid Accept activity.
			'valid_accept_activity'   => array(
				array(
					'type'   => 'Accept',
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
				'Should return true for valid Accept activity',
			),
			// Test with input_valid false.
			'input_valid_false'       => array(
				array( 'type' => 'Follow' ),
				false,
				false,
				'Should preserve input_valid when type is not Accept',
			),
		);
	}

	/**
	 * Functional test: handle_accept moves user from pending to following meta.
	 */
	public function test_handle_accept_moves_user_from_pending_to_following() {
		$user_id     = self::$user_id;
		$object_guid = 'https://example.com/actor/123';
		$outbox_guid = 'https://example.com/outbox/123';

		$outbox_post_id = self::factory()->post->create(
			array(
				'post_type'   => 'ap_outbox',
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

		// Prepare accept array as expected by handle_accept, using the real outbox guid.
		$accept = array(
			'type'   => 'Accept',
			'actor'  => $object_guid,
			'object' => array(
				'id'     => $outbox_guid,
				'actor'  => 'https://example.com/actor/123',
				'type'   => 'Follow',
				'object' => $object_guid,
			),
		);

		// Call the handler.
		Accept::handle_accept( $accept, $user_id );

		\clean_post_cache( $post_id );

		// Assert: user_id is now in _activitypub_followed_by.
		$following = \get_post_meta( $post_id, Following::FOLLOWING_META_KEY, false );
		$this->assertContains( (string) $user_id, $following );

		// Assert: user_id is no longer in _activitypub_followed_by_pending.
		$pending = \get_post_meta( $post_id, Following::PENDING_META_KEY, false );
		$this->assertNotContains( (string) $user_id, $pending );
	}

	/**
	 * An Accept whose sender is not the followed actor must be ignored.
	 *
	 * Guards against a same-instance actor accepting a Follow that targeted a
	 * different actor by referencing that pending Follow's outbox GUID.
	 */
	public function test_handle_accept_rejects_actor_object_mismatch() {
		$user_id     = self::$user_id;
		$object_guid = 'https://example.com/actor/123';
		$outbox_guid = 'https://example.com/outbox/123';

		$outbox_post_id = self::factory()->post->create(
			array(
				'post_type'   => 'ap_outbox',
				'post_status' => 'publish',
				'guid'        => $outbox_guid,
			)
		);
		\add_post_meta( $outbox_post_id, '_activitypub_activity_type', 'Follow' );

		$post_id = self::factory()->post->create(
			array(
				'post_type'   => Remote_Actors::POST_TYPE,
				'post_status' => 'publish',
				'guid'        => $object_guid,
			)
		);
		\add_post_meta( $post_id, Following::PENDING_META_KEY, (string) $user_id );

		// The Accept is sent by a different actor than the one that was followed.
		$accept = array(
			'type'   => 'Accept',
			'actor'  => 'https://example.com/actor/999',
			'object' => array(
				'id'     => $outbox_guid,
				'actor'  => 'https://example.com/actor/123',
				'type'   => 'Follow',
				'object' => $object_guid,
			),
		);

		Accept::handle_accept( $accept, $user_id );

		\clean_post_cache( $post_id );

		// The relationship must stay pending and must not be marked as followed.
		$this->assertNotContains( (string) $user_id, \get_post_meta( $post_id, Following::FOLLOWING_META_KEY, false ) );
		$this->assertContains( (string) $user_id, \get_post_meta( $post_id, Following::PENDING_META_KEY, false ) );
	}

	/**
	 * A non-Follow Accept (e.g. a quote request) is left untouched by the Follow logic.
	 *
	 * The sender/followed-actor guard is scoped to the Follow branch, so it must not
	 * affect other Accept types.
	 */
	public function test_handle_accept_ignores_non_follow_activity() {
		$user_id     = self::$user_id;
		$object_guid = 'https://example.com/actor/456';
		$outbox_guid = 'https://example.com/outbox/456';

		// The outbox item is a QuoteRequest, not a Follow.
		$outbox_post_id = self::factory()->post->create(
			array(
				'post_type'   => 'ap_outbox',
				'post_status' => 'publish',
				'guid'        => $outbox_guid,
			)
		);
		\add_post_meta( $outbox_post_id, '_activitypub_activity_type', 'QuoteRequest' );

		$post_id = self::factory()->post->create(
			array(
				'post_type'   => Remote_Actors::POST_TYPE,
				'post_status' => 'publish',
				'guid'        => $object_guid,
			)
		);
		\add_post_meta( $post_id, Following::PENDING_META_KEY, (string) $user_id );

		$accept = array(
			'type'   => 'Accept',
			'actor'  => $object_guid,
			'object' => array(
				'id'     => $outbox_guid,
				'type'   => 'QuoteRequest',
				'object' => $object_guid,
			),
		);

		Accept::handle_accept( $accept, $user_id );

		\clean_post_cache( $post_id );

		// A non-Follow Accept must not change the following state.
		$this->assertEmpty( \get_post_meta( $post_id, Following::FOLLOWING_META_KEY, false ) );
		$this->assertContains( (string) $user_id, \get_post_meta( $post_id, Following::PENDING_META_KEY, false ) );
	}

	/*
	 * ------------------------------------------------------------------
	 * Accepts of our own QuoteRequests (FEP-044f).
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
	 * Build the Accept the quoted author's server would send for our request.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $result  Stamp URI.
	 * @param string $actor   Sender.
	 *
	 * @return array Accept activity.
	 */
	private function build_accept( $post_id, $result = 'https://remote.example/stamps/1', $actor = 'https://remote.example/users/alice' ) {
		$requests = $this->get_quote_requests( $post_id );

		return array(
			'type'   => 'Accept',
			'actor'  => $actor,
			'object' => array(
				'id'         => $requests ? $requests[0]->guid : '',
				'type'       => 'QuoteRequest',
				'actor'      => \get_author_posts_url( self::$user_id ),
				'object'     => 'https://remote.example/notes/1',
				'instrument' => get_object_id( \get_post( $post_id ) ),
			),
			'result' => $result,
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
		$reject         = $this->build_accept( $post_id, '', $actor );
		$reject['type'] = 'Reject';
		unset( $reject['result'] );

		return $reject;
	}

	/**
	 * Mock a QuoteAuthorization stamp for the given post.
	 *
	 * @param int   $post_id   Post ID.
	 * @param array $overrides Fields to override.
	 *
	 * @return callable The filter, to remove later.
	 */
	private function mock_stamp( $post_id, $overrides = array() ) {
		$stamp  = \array_merge(
			array(
				'id'                => 'https://remote.example/stamps/1',
				'type'              => 'QuoteAuthorization',
				'attributedTo'      => 'https://remote.example/users/alice',
				'interactingObject' => get_object_id( \get_post( $post_id ) ),
				'interactionTarget' => 'https://remote.example/notes/1',
			),
			$overrides
		);
		$filter = function ( $pre, $url ) use ( $stamp ) {
			return 'https://remote.example/stamps/1' === $url ? $stamp : $pre;
		};
		\add_filter( 'activitypub_pre_http_get_remote_object', $filter, 10, 2 );

		return $filter;
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
	 * A valid Accept stores the stamp and queues an Update.
	 *
	 * @covers ::accept_quote_request
	 */
	public function test_accept_stores_stamp_and_updates() {
		$post_id = $this->create_quote_post();
		$filter  = $this->mock_stamp( $post_id );
		$before  = $this->count_updates( $post_id );

		$authorized = array();
		$track      = function ( $post_id, $stamp_uri ) use ( &$authorized ) {
			$authorized[] = array( $post_id, $stamp_uri );
		};
		\add_action( 'activitypub_quote_authorized', $track, 10, 2 );

		Accept::handle_accept( $this->build_accept( $post_id ), self::$user_id );

		\remove_action( 'activitypub_quote_authorized', $track );
		\remove_filter( 'activitypub_pre_http_get_remote_object', $filter );

		$this->assertSame( 'https://remote.example/stamps/1', \get_post_meta( $post_id, '_activitypub_quote_authorization', true ) );
		$this->assertSame( $before + 1, $this->count_updates( $post_id ) );
		$this->assertSame( array( array( $post_id, 'https://remote.example/stamps/1' ) ), $authorized );
	}

	/**
	 * Accepts from the wrong actor, with a stamp that does not bind this post, or with a foreign stamp host are ignored.
	 *
	 * @covers ::accept_quote_request
	 */
	public function test_accept_ignored_when_sender_or_stamp_invalid() {
		$post_id = $this->create_quote_post();

		$invalid = 0;
		$track   = function () use ( &$invalid ) {
			++$invalid;
		};
		\add_action( 'activitypub_quote_authorization_invalid', $track );

		// A sender who is not the quoted author is refused before the stamp is even looked at.
		$filter = $this->mock_stamp( $post_id );
		Accept::handle_accept( $this->build_accept( $post_id, 'https://remote.example/stamps/1', 'https://remote.example/users/mallory' ), self::$user_id );
		\remove_filter( 'activitypub_pre_http_get_remote_object', $filter );
		$this->assertEmpty( \get_post_meta( $post_id, '_activitypub_quote_authorization', true ) );
		$this->assertSame( 0, $invalid );

		$bad_stamps = array(
			array( 'interactingObject' => 'https://elsewhere.example/other' ),
			array( 'interactionTarget' => 'https://remote.example/notes/2' ),
			array( 'attributedTo' => 'https://remote.example/users/mallory' ),
			array( 'type' => 'Note' ),
		);

		foreach ( $bad_stamps as $i => $overrides ) {
			$filter = $this->mock_stamp( $post_id, $overrides );
			Accept::handle_accept( $this->build_accept( $post_id ), self::$user_id );
			\remove_filter( 'activitypub_pre_http_get_remote_object', $filter );
			$this->assertEmpty( \get_post_meta( $post_id, '_activitypub_quote_authorization', true ), \key( $overrides ) );
			$this->assertSame( $i + 1, $invalid, \key( $overrides ) );
		}

		\remove_action( 'activitypub_quote_authorization_invalid', $track );

		// A stamp on another host than the sender is never fetched.
		$fetched_urls = array();
		$fetched      = function ( $pre, $url ) use ( &$fetched_urls ) {
			$fetched_urls[] = $url;
			return $pre;
		};
		\add_filter( 'activitypub_pre_http_get_remote_object', $fetched, 9, 2 );
		Accept::handle_accept( $this->build_accept( $post_id, 'https://other.example/stamps/1' ), self::$user_id );
		\remove_filter( 'activitypub_pre_http_get_remote_object', $fetched, 9 );
		$this->assertEmpty( \get_post_meta( $post_id, '_activitypub_quote_authorization', true ) );
		$this->assertNotContains( 'https://other.example/stamps/1', $fetched_urls );
	}

	/**
	 * An Accept for a request the post has since superseded is ignored.
	 *
	 * @covers ::accept_quote_request
	 */
	public function test_accept_for_superseded_url_ignored() {
		$post_id = $this->create_quote_post();
		$accept  = $this->build_accept( $post_id );
		\update_post_meta( $post_id, '_activitypub_quote_request', 'https://remote.example/notes/2' );
		$before = $this->count_updates( $post_id );

		$authorized = 0;
		$track      = function () use ( &$authorized ) {
			++$authorized;
		};
		\add_action( 'activitypub_quote_authorized', $track );

		$filter = $this->mock_stamp( $post_id );
		Accept::handle_accept( $accept, self::$user_id );
		\remove_filter( 'activitypub_pre_http_get_remote_object', $filter );
		\remove_action( 'activitypub_quote_authorized', $track );

		$this->assertEmpty( \get_post_meta( $post_id, '_activitypub_quote_authorization', true ) );
		$this->assertSame( 'https://remote.example/notes/2', \get_post_meta( $post_id, '_activitypub_quote_request', true ) );
		$this->assertSame( $before, $this->count_updates( $post_id ) );
		$this->assertSame( 0, $authorized );
	}

	/**
	 * An Accept after an earlier Reject clears the rejection.
	 *
	 * @covers ::accept_quote_request
	 */
	public function test_accept_after_reject_clears_rejected() {
		$post_id = $this->create_quote_post();

		Reject::handle_reject( $this->build_reject( $post_id ), self::$user_id );
		$this->assertSame( '1', \get_post_meta( $post_id, '_activitypub_quote_rejected', true ) );

		$filter = $this->mock_stamp( $post_id );
		Accept::handle_accept( $this->build_accept( $post_id ), self::$user_id );
		\remove_filter( 'activitypub_pre_http_get_remote_object', $filter );

		$this->assertSame( 'https://remote.example/stamps/1', \get_post_meta( $post_id, '_activitypub_quote_authorization', true ) );
		$this->assertEmpty( \get_post_meta( $post_id, '_activitypub_quote_rejected', true ) );
	}
}
