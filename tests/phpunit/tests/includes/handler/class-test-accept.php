<?php
/**
 * Unit tests for the Activitypub Accept handler.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Handler;

use Activitypub\Collection\Following;
use Activitypub\Collection\Remote_Actors;
use Activitypub\Handler\Accept;
use Activitypub\Handler\Reject;
use Activitypub\Tests\Quote_Post_Fixtures;

/**
 * Class Test_Accept
 *
 * @coversDefaultClass \Activitypub\Handler\Accept
 */
class Test_Accept extends \WP_UnitTestCase {
	use Quote_Post_Fixtures;

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
		\get_user_by( 'id', self::$user_id )->add_cap( 'activitypub' );
	}

	/**
	 * Mock the remote objects the quote tests fetch.
	 */
	public function set_up() {
		parent::set_up();

		$this->add_quoted_object_mock();
	}

	/**
	 * Remove the remote object mock.
	 */
	public function tear_down() {
		$this->remove_quoted_object_mock();
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
