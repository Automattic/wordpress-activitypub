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

	/**
	 * Test that an unresolvable target (no local actor matches the activity object)
	 * still produces a Reject so the curator gets a definitive answer.
	 *
	 * @covers ::handle_feature_request
	 */
	public function test_handle_feature_request_rejects_unresolvable_target() {
		update_option( 'activitypub_default_feature_policy', ACTIVITYPUB_INTERACTION_POLICY_ANYONE );

		$activity           = $this->create_feature_request_activity();
		$activity['object'] = 'https://this-host-does-not-host.example/users/ghost';

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
						'value' => 'Reject',
					),
				),
			)
		);
		$this->assertNotEmpty( $outbox, 'Unresolvable target should still produce a Reject activity.' );
	}

	/**
	 * Test that queue_accept stores a stamp for the blog actor in the option store.
	 *
	 * The blog actor has no users-table row, so its stamps cannot live in
	 * user meta.
	 *
	 * @covers ::queue_accept
	 * @covers ::add_stamp
	 */
	public function test_queue_accept_stores_stamp_for_blog_actor() {
		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_AND_BLOG_MODE );

		$blog_actor         = Actors::get_by_id( Actors::BLOG_USER_ID );
		$activity           = $this->create_feature_request_activity();
		$activity['object'] = $blog_actor->get_id();

		Feature_Request::queue_accept( $activity, Actors::BLOG_USER_ID );

		// The stamp is stored in the blog stamp option.
		$stamps = \get_option( Feature_Request::BLOG_STAMPS_OPTION, array() );
		$this->assertContains( $activity['instrument'], $stamps, 'Instrument URL should be recorded in the blog stamp option.' );

		$stamp_id = \array_search( $activity['instrument'], $stamps, true );

		// The Accept carries a resolvable stamp URL for actor 0.
		$outbox = get_posts(
			array(
				'post_type'   => Outbox::POST_TYPE,
				'post_status' => 'pending',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_query'  => array(
					array(
						'key'   => '_activitypub_activity_type',
						'value' => 'Accept',
					),
				),
			)
		);
		$this->assertNotEmpty( $outbox, 'Accept activity should be queued for the blog actor.' );

		$payload = json_decode( $outbox[0]->post_content, true );
		$this->assertSame( $blog_actor->get_id(), $payload['actor'], 'The Accept should be sent by the blog actor.' );
		$this->assertStringContainsString( 'actor=0', $payload['result'] );
		$this->assertStringContainsString( 'stamp=' . $stamp_id, $payload['result'] );
	}

	/**
	 * Test that blog-actor stamps are idempotent: a second FeatureRequest with the
	 * same instrument reuses the existing stamp.
	 *
	 * @covers ::queue_accept
	 * @covers ::add_stamp
	 */
	public function test_queue_accept_idempotent_for_blog_actor() {
		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_AND_BLOG_MODE );

		$activity           = $this->create_feature_request_activity();
		$activity['object'] = Actors::get_by_id( Actors::BLOG_USER_ID )->get_id();

		Feature_Request::queue_accept( $activity, Actors::BLOG_USER_ID );
		Feature_Request::queue_accept( $activity, Actors::BLOG_USER_ID );

		$stamps = \get_option( Feature_Request::BLOG_STAMPS_OPTION, array() );
		$this->assertCount( 1, $stamps, 'Duplicate FeatureRequests for the same instrument must not produce multiple blog stamps.' );
	}

	/**
	 * Test that distinct instruments get distinct blog stamp IDs and that the
	 * allocation lock is released afterwards.
	 *
	 * @covers ::add_stamp
	 */
	public function test_add_stamp_allocates_distinct_ids_for_blog_actor() {
		$first  = Feature_Request::add_stamp( Actors::BLOG_USER_ID, 'https://remote.example.com/featured/1' );
		$second = Feature_Request::add_stamp( Actors::BLOG_USER_ID, 'https://remote.example.com/featured/2' );

		$this->assertNotFalse( $first, 'First blog stamp should be created.' );
		$this->assertNotFalse( $second, 'Second blog stamp should be created.' );
		$this->assertNotSame( $first, $second, 'Distinct instruments must not reuse the same blog stamp ID.' );

		$stamps = \get_option( Feature_Request::BLOG_STAMPS_OPTION, array() );
		$this->assertCount( 2, $stamps, 'Both instruments should be recorded.' );

		$this->assertFalse(
			\get_option( Feature_Request::BLOG_STAMPS_OPTION . '_lock' ),
			'The allocation lock must be released after add_stamp() returns.'
		);
	}

	/**
	 * Test that a stale blog-stamp lock is recovered instead of deadlocking.
	 *
	 * @covers ::add_stamp
	 */
	public function test_add_stamp_recovers_stale_blog_lock() {
		// Simulate a lock abandoned by a request that died mid-write.
		\add_option( Feature_Request::BLOG_STAMPS_OPTION . '_lock', \time() - ( Feature_Request::BLOG_STAMPS_LOCK_TTL + 5 ), '', false );

		$stamp_id = Feature_Request::add_stamp( Actors::BLOG_USER_ID, 'https://remote.example.com/featured/stale' );

		$this->assertNotFalse( $stamp_id, 'A stale lock must be recovered so the stamp can still be created.' );
		$this->assertFalse(
			\get_option( Feature_Request::BLOG_STAMPS_OPTION . '_lock' ),
			'The recovered lock must be released after add_stamp() returns.'
		);
	}

	/**
	 * Test that FeatureRequests targeting the Application actor are rejected
	 * and never accepted, regardless of the site policy.
	 *
	 * @covers ::handle_feature_request
	 */
	public function test_handle_feature_request_rejects_application_actor() {
		update_option( 'activitypub_default_feature_policy', ACTIVITYPUB_INTERACTION_POLICY_ANYONE );

		$application        = Actors::get_by_id( Actors::APPLICATION_USER_ID );
		$activity           = $this->create_feature_request_activity();
		$activity['object'] = $application->get_id();

		Feature_Request::handle_feature_request( $activity, self::$user_id );

		/*
		 * The Application actor is not resolvable as a FeatureRequest target
		 * (Actors::get_by_resource() has no branch for it), so the request
		 * takes the unresolvable-target path and is rejected.
		 */
		$rejects = get_posts(
			array(
				'post_type'   => Outbox::POST_TYPE,
				'post_status' => 'pending',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_query'  => array(
					array(
						'key'   => '_activitypub_activity_type',
						'value' => 'Reject',
					),
				),
			)
		);
		$this->assertNotEmpty( $rejects, 'FeatureRequests targeting the Application actor should be rejected.' );

		$accepts = get_posts(
			array(
				'post_type'   => Outbox::POST_TYPE,
				'post_status' => 'pending',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_query'  => array(
					array(
						'key'   => '_activitypub_activity_type',
						'value' => 'Accept',
					),
				),
			)
		);
		$this->assertEmpty( $accepts, 'The Application actor must never accept FeatureRequests, regardless of policy.' );
	}
}
