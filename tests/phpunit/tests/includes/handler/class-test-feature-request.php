<?php
/**
 * Test file for Feature Request handler.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Handler;

use Activitypub\Collection\Actors;
use Activitypub\Collection\Followers;
use Activitypub\Collection\Outbox;
use Activitypub\Handler\Feature_Request;
use Activitypub\Tests\ActivityPub_Outbox_TestCase;

/**
 * Test class for Feature Request Handler.
 *
 * @coversDefaultClass \Activitypub\Handler\Feature_Request
 *
 * @group activitypub
 */
class Test_Feature_Request extends ActivityPub_Outbox_TestCase {
	/**
	 * Set up each test.
	 */
	public function set_up() {
		parent::set_up();
		Feature_Request::init();
	}

	/**
	 * Build a sample FeatureRequest activity targeting the test user.
	 *
	 * @param string $actor_uri The remote actor URI.
	 * @return array The activity array.
	 */
	private function create_feature_request_activity( $actor_uri = 'https://remote.example.com/users/curator' ) {
		return array(
			'id'         => 'https://remote.example.com/activities/feat-1',
			'type'       => 'FeatureRequest',
			'actor'      => $actor_uri,
			'object'     => Actors::get_by_id( self::$user_id )->get_id(),
			'instrument' => 'https://remote.example.com/users/curator/featured/42',
		);
	}

	/**
	 * Test that validate_object accepts a well-formed FeatureRequest.
	 *
	 * @covers ::validate_object
	 */
	public function test_validate_object_passes_for_valid_feature_request() {
		$activity = $this->create_feature_request_activity();

		$request = new \WP_REST_Request( 'POST', '/inbox' );
		$request->set_body( wp_json_encode( $activity ) );
		$request->set_header( 'Content-Type', 'application/json' );

		$valid = Feature_Request::validate_object( true, 'object', $request );
		$this->assertTrue( $valid );
	}

	/**
	 * Test that validate_object rejects a FeatureRequest missing required keys.
	 *
	 * @covers ::validate_object
	 */
	public function test_validate_object_fails_for_missing_instrument() {
		$activity = $this->create_feature_request_activity();
		unset( $activity['instrument'] );

		$request = new \WP_REST_Request( 'POST', '/inbox' );
		$request->set_body( wp_json_encode( $activity ) );
		$request->set_header( 'Content-Type', 'application/json' );

		$valid = Feature_Request::validate_object( true, 'object', $request );
		$this->assertFalse( $valid );
	}

	/**
	 * Test that validate_object passes through unrelated activity types unchanged.
	 *
	 * @covers ::validate_object
	 */
	public function test_validate_object_passes_through_other_types() {
		$activity = array(
			'type'   => 'Follow',
			'actor'  => 'https://x',
			'object' => 'https://y',
		);

		$request = new \WP_REST_Request( 'POST', '/inbox' );
		$request->set_body( wp_json_encode( $activity ) );
		$request->set_header( 'Content-Type', 'application/json' );

		$valid = Feature_Request::validate_object( true, 'object', $request );
		$this->assertTrue( $valid );
	}

	/**
	 * Test the blocked-request path emits a Reject for FeatureRequest activities.
	 *
	 * @covers ::handle_blocked_request
	 */
	public function test_handle_blocked_request_rejects_feature_request() {
		$activity = $this->create_feature_request_activity();

		Feature_Request::handle_blocked_request( $activity, self::$user_id, 'FeatureRequest' );

		$outbox = get_posts(
			array(
				'post_type'   => Outbox::POST_TYPE,
				'post_status' => 'pending',
				'author'      => self::$user_id,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_query'  => array(
					array(
						'key'   => '_activitypub_activity_type',
						'value' => 'Reject',
					),
				),
			)
		);
		$this->assertNotEmpty( $outbox, 'Reject activity should be queued for blocked FeatureRequest.' );
	}

	/**
	 * Test the blocked-request path ignores unrelated activity types.
	 *
	 * @covers ::handle_blocked_request
	 */
	public function test_handle_blocked_request_ignores_other_types() {
		$activity = $this->create_feature_request_activity();

		Feature_Request::handle_blocked_request( $activity, self::$user_id, 'Follow' );

		$outbox = get_posts(
			array(
				'post_type'   => Outbox::POST_TYPE,
				'post_status' => 'pending',
				'author'      => self::$user_id,
			)
		);
		$this->assertEmpty( $outbox, 'No outbox activity should be created for unrelated types.' );
	}

	/**
	 * Test that the snake_case 'feature_request' type alias is accepted.
	 *
	 * The inbox dispatcher snake-cases activity types before firing per-type
	 * actions, so handle_blocked_request must accept both spellings.
	 *
	 * @covers ::handle_blocked_request
	 */
	public function test_handle_blocked_request_accepts_snake_case_alias() {
		$activity = $this->create_feature_request_activity();

		Feature_Request::handle_blocked_request( $activity, self::$user_id, 'feature_request' );

		$outbox = get_posts(
			array(
				'post_type'   => Outbox::POST_TYPE,
				'post_status' => 'pending',
				'author'      => self::$user_id,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_query'  => array(
					array(
						'key'   => '_activitypub_activity_type',
						'value' => 'Reject',
					),
				),
			)
		);
		$this->assertNotEmpty( $outbox, 'Reject activity should be queued when dispatcher uses snake_case type.' );
	}

	/**
	 * Test that queue_reject creates a private Reject activity addressed to the requester.
	 *
	 * @covers ::queue_reject
	 */
	public function test_queue_reject_emits_minimal_private_activity() {
		$activity = $this->create_feature_request_activity();

		Feature_Request::queue_reject( $activity, self::$user_id );

		$outbox = get_posts(
			array(
				'post_type'   => Outbox::POST_TYPE,
				'post_status' => 'pending',
				'author'      => self::$user_id,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_query'  => array(
					array(
						'key'   => '_activitypub_activity_type',
						'value' => 'Reject',
					),
				),
			)
		);
		$this->assertNotEmpty( $outbox, 'Reject activity should be created.' );

		$payload    = json_decode( $outbox[0]->post_content, true );
		$visibility = get_post_meta( $outbox[0]->ID, 'activitypub_content_visibility', true );

		$this->assertSame( 'Reject', $payload['type'] );
		$this->assertSame( 'private', $visibility );
		$this->assertContains( $activity['actor'], $payload['to'] );

		// Object payload should be trimmed to a stable allow-list.
		$expected_keys = array( 'id', 'type', 'object', 'actor', 'instrument' );
		$actual_keys   = array_keys( $payload['object'] );
		$this->assertEmpty( array_diff( $expected_keys, $actual_keys ), 'All expected keys should be present.' );
		$this->assertEmpty( array_diff( $actual_keys, $expected_keys ), 'No unexpected keys should be present.' );
	}

	/**
	 * Data provider for policy tests.
	 *
	 * @return array Test cases keyed by name.
	 */
	public function policy_test_data() {
		return array(
			'default (me) - reject'                      => array( '', null, 'Reject' ),
			'me policy - reject'                         => array( ACTIVITYPUB_INTERACTION_POLICY_ME, null, 'Reject' ),
			'anyone policy - accept'                     => array( ACTIVITYPUB_INTERACTION_POLICY_ANYONE, null, 'Accept' ),
			'followers policy with follower - accept'    => array(
				ACTIVITYPUB_INTERACTION_POLICY_FOLLOWERS,
				'add_follower',
				'Accept',
			),
			'followers policy without follower - reject' => array(
				ACTIVITYPUB_INTERACTION_POLICY_FOLLOWERS,
				null,
				'Reject',
			),
		);
	}

	/**
	 * Test handle_feature_request branches.
	 *
	 * @dataProvider policy_test_data
	 * @covers ::handle_feature_request
	 *
	 * @param string      $policy        Site policy to set, or '' to leave default.
	 * @param string|null $setup         Optional setup callback name.
	 * @param string      $expected_type Activity type expected in the outbox.
	 */
	public function test_handle_feature_request_policies( $policy, $setup, $expected_type ) {
		if ( '' !== $policy ) {
			update_option( 'activitypub_default_feature_policy', $policy );
		} else {
			delete_option( 'activitypub_default_feature_policy' );
		}

		$activity = $this->create_feature_request_activity();
		$actor    = $activity['actor'];

		$pre = function () use ( $actor ) {
			return array(
				'id'    => $actor,
				'type'  => 'Person',
				'inbox' => str_replace( '/users/', '/inbox/', $actor ),
			);
		};
		add_filter( 'pre_get_remote_metadata_by_actor', $pre );

		if ( 'add_follower' === $setup ) {
			$follower_id = Followers::add( self::$user_id, $actor );
			$this->assertNotFalse( $follower_id );
		}

		Feature_Request::handle_feature_request( $activity, self::$user_id );

		$outbox = get_posts(
			array(
				'post_type'   => Outbox::POST_TYPE,
				'post_status' => 'pending',
				'author'      => self::$user_id,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_query'  => array(
					array(
						'key'   => '_activitypub_activity_type',
						'value' => $expected_type,
					),
				),
			)
		);
		$this->assertNotEmpty( $outbox, "{$expected_type} activity should be queued." );

		remove_filter( 'pre_get_remote_metadata_by_actor', $pre );
	}

	/**
	 * Test that queue_accept stores a stamp and emits an Accept with the stamp URL.
	 *
	 * @covers ::queue_accept
	 */
	public function test_queue_accept_stores_stamp_and_emits_result() {
		$activity = $this->create_feature_request_activity();

		Feature_Request::queue_accept( $activity, self::$user_id );

		// Verify usermeta row was created.
		$stored = get_user_meta( self::$user_id, '_activitypub_featured_by', false );
		$this->assertContains( $activity['instrument'], $stored, 'Instrument URL should be recorded in user meta.' );

		// Verify Accept activity in outbox carries a `result` URL containing actor and stamp params.
		$outbox = get_posts(
			array(
				'post_type'   => Outbox::POST_TYPE,
				'post_status' => 'pending',
				'author'      => self::$user_id,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_query'  => array(
					array(
						'key'   => '_activitypub_activity_type',
						'value' => 'Accept',
					),
				),
			)
		);
		$this->assertNotEmpty( $outbox );
		$payload = json_decode( $outbox[0]->post_content, true );
		$this->assertSame( 'Accept', $payload['type'] );
		$this->assertNotEmpty( $payload['result'] );
		$this->assertStringContainsString( 'actor=' . self::$user_id, $payload['result'] );
		$this->assertStringContainsString( 'stamp=', $payload['result'] );
	}

	/**
	 * Test that queue_accept is idempotent: calling it twice with the same instrument
	 * reuses the existing usermeta row and does not duplicate stamps.
	 *
	 * @covers ::queue_accept
	 */
	public function test_queue_accept_idempotent() {
		$activity = $this->create_feature_request_activity();

		Feature_Request::queue_accept( $activity, self::$user_id );
		Feature_Request::queue_accept( $activity, self::$user_id );

		$stored = get_user_meta( self::$user_id, '_activitypub_featured_by', false );
		$this->assertCount( 1, $stored, 'Duplicate FeatureRequests for the same instrument must not produce multiple stamps.' );
	}
}
