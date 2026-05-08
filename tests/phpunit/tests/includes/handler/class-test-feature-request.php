<?php
/**
 * Test file for Feature Request handler.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Handler;

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
			'object'     => \Activitypub\Collection\Actors::get_by_id( self::$user_id )->get_id(),
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
}
