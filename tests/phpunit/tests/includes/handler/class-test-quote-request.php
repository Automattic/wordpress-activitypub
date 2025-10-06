<?php
/**
 * Test file for Quote Request handler.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Handler;

use Activitypub\Collection\Followers;
use Activitypub\Collection\Outbox;
use Activitypub\Handler\Quote_Request;

/**
 * Test class for Quote Request Handler.
 *
 * @coversDefaultClass \Activitypub\Handler\Quote_Request
 */
class Test_Quote_Request extends \Activitypub\Tests\ActivityPub_Outbox_TestCase {
	/**
	 * Test post ID.
	 *
	 * @var int
	 */
	protected static $post_id;

	/**
	 * Test remote actor.
	 *
	 * @var object
	 */
	protected static $remote_actor;

	/**
	 * Set up the test case.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		// Mock remote actor.
		self::$remote_actor = (object) array(
			'ID'       => 999,
			'user_url' => 'https://remote.example.com/users/remote_user',
		);
	}

	/**
	 * Set up each test.
	 */
	public function set_up() {
		parent::set_up();

		// Create a fresh post for each test since parent::tear_down() deletes all posts.
		self::$post_id = self::factory()->post->create(
			array(
				'post_author'  => self::$user_id,
				'post_content' => 'Test post content',
				'post_title'   => 'Test Post',
				'post_status'  => 'publish',
			)
		);

		// Initialize the Quote Request handler.
		Quote_Request::init();
	}

	/**
	 * Create a sample QuoteRequest activity.
	 *
	 * @param string $actor_uri The actor URI.
	 * @return array The activity array.
	 */
	private function create_quote_request_activity( $actor_uri = 'https://remote.example.com/users/remote_user' ) {
		return array(
			'id'         => 'https://remote.example.com/activities/123',
			'type'       => 'QuoteRequest',
			'actor'      => $actor_uri,
			'object'     => \get_permalink( self::$post_id ),
			'instrument' => 'https://remote.example.com/posts/456',
		);
	}

	/**
	 * Data provider for quote request policy tests.
	 *
	 * @return array Test cases with policy, setup callback, and expected response type.
	 */
	public function policy_test_data() {
		return array(
			'default (no policy) - should accept' => array(
				'policy'          => '',
				'setup_callback'  => null,
				'expected_type'   => 'Accept',
				'expected_result' => true,
			),
			'anyone policy - should accept'       => array(
				'policy'          => ACTIVITYPUB_INTERACTION_POLICY_ANYONE,
				'setup_callback'  => null,
				'expected_type'   => 'Accept',
				'expected_result' => true,
			),
			'me policy - should reject'           => array(
				'policy'          => ACTIVITYPUB_INTERACTION_POLICY_ME,
				'setup_callback'  => null,
				'expected_type'   => 'Reject',
				'expected_result' => true,
			),
			'followers policy with follower - should accept' => array(
				'policy'          => ACTIVITYPUB_INTERACTION_POLICY_FOLLOWERS,
				'setup_callback'  => 'add_follower',
				'expected_type'   => 'Accept',
				'expected_result' => true,
			),
			'followers policy with non-follower - should reject' => array(
				'policy'          => ACTIVITYPUB_INTERACTION_POLICY_FOLLOWERS,
				'setup_callback'  => null,
				'expected_type'   => 'Reject',
				'expected_result' => true,
			),
			'followers policy with actor error - should reject' => array(
				'policy'          => ACTIVITYPUB_INTERACTION_POLICY_FOLLOWERS,
				'setup_callback'  => 'mock_actor_error',
				'expected_type'   => 'Reject',
				'expected_result' => true,
			),
		);
	}

	/**
	 * Test QuoteRequest handling with various policies.
	 *
	 * @dataProvider policy_test_data
	 * @covers ::handle_quote_request
	 *
	 * @param string      $policy          The interaction policy to set.
	 * @param string|null $setup_callback  Optional setup callback method name.
	 * @param string      $expected_type   Expected activity type (Accept/Reject).
	 * @param bool        $expected_result Expected test result.
	 */
	public function test_handle_quote_request_policies( $policy, $setup_callback, $expected_type, $expected_result ) {
		// Set policy if provided.
		if ( ! empty( $policy ) ) {
			update_post_meta( self::$post_id, 'activitypub_interaction_policy_quote', $policy );
		}

		$activity  = $this->create_quote_request_activity();
		$actor_url = $activity['actor'];

		// Mock HTTP requests for actor metadata.
		add_filter(
			'pre_get_remote_metadata_by_actor',
			function () use ( $actor_url ) {
				return array(
					'id'    => $actor_url,
					'actor' => $actor_url,
					'type'  => 'Person',
					'inbox' => str_replace( '/users/', '/inbox/', $actor_url ),
				);
			}
		);

		$remote_actor_id = false;

		// Run setup callback if provided.
		if ( 'add_follower' === $setup_callback ) {
			$remote_actor_id = Followers::add_follower( self::$user_id, $actor_url );
			$this->assertNotFalse( $remote_actor_id, 'Should successfully add follower' );
		} elseif ( 'mock_actor_error' === $setup_callback ) {
			// Override the actor metadata filter to return an error.
			remove_all_filters( 'pre_get_remote_metadata_by_actor' );
			add_filter(
				'pre_get_remote_metadata_by_actor',
				function () {
					return new \WP_Error( 'not_found', 'Actor not found' );
				}
			);
		}

		// Handle the quote request.
		Quote_Request::handle_quote_request( $activity, self::$user_id );

		// Check outbox for expected response.
		$outbox_posts = get_posts(
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

		if ( $expected_result ) {
			$this->assertNotEmpty( $outbox_posts, "{$expected_type} activity should be queued" );

			$outbox_post   = $outbox_posts[0];
			$activity_json = json_decode( $outbox_post->post_content, true );

			$this->assertEquals( $expected_type, $activity_json['type'] );
			$this->assertContains( $activity['actor'], $activity_json['to'] );
		} else {
			$this->assertEmpty( $outbox_posts, "No {$expected_type} activity should be queued" );
		}

		// Clean up follower if created.
		if ( $remote_actor_id ) {
			wp_delete_post( $remote_actor_id, true );
		}
	}

	/**
	 * Test handling of blocked QuoteRequest activities.
	 *
	 * @covers ::handle_blocked_request
	 */
	public function test_handle_blocked_request() {
		$activity = $this->create_quote_request_activity();

		Quote_Request::handle_blocked_request( $activity, self::$user_id, 'QuoteRequest' );

		// Check outbox for Reject response.
		$outbox_posts = get_posts(
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

		$this->assertNotEmpty( $outbox_posts, 'Reject activity should be queued for blocked request' );

		$outbox_post   = $outbox_posts[0];
		$activity_json = json_decode( $outbox_post->post_content, true );

		$this->assertEquals( 'Reject', $activity_json['type'] );
	}

	/**
	 * Test that non-QuoteRequest types are ignored by handle_blocked_request.
	 *
	 * @covers ::handle_blocked_request
	 */
	public function test_handle_blocked_request_ignores_other_types() {
		$activity = $this->create_quote_request_activity();

		Quote_Request::handle_blocked_request( $activity, self::$user_id, 'Follow' );

		// Check that no outbox activity was created.
		$outbox_posts = get_posts(
			array(
				'post_type'   => Outbox::POST_TYPE,
				'post_status' => 'pending',
				'author'      => self::$user_id,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_query'  => array(
					array(
						'key'   => '_activitypub_activity_type',
						'value' => 'Follow',
					),
				),
			)
		);

		$this->assertEmpty( $outbox_posts, 'Should not handle non-QuoteRequest activities' );
	}

	/**
	 * Test queue_accept method creates correct Accept activity.
	 *
	 * @covers ::queue_accept
	 */
	public function test_queue_accept() {
		$activity = $this->create_quote_request_activity();

		Quote_Request::queue_accept( $activity, self::$user_id, self::$post_id );

		// Check outbox for Accept response.
		$outbox_posts = get_posts(
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

		$this->assertNotEmpty( $outbox_posts, 'Accept activity should be created' );

		$outbox_post   = $outbox_posts[0];
		$activity_json = json_decode( $outbox_post->post_content, true );
		$visibility    = get_post_meta( $outbox_post->ID, 'activitypub_content_visibility', true );

		$this->assertEquals( 'Accept', $activity_json['type'] );
		$this->assertEquals( 'private', $visibility );
		$this->assertContains( $activity['actor'], $activity_json['to'] );

		// Check that the activity object contains only minimal data.
		$expected_keys = array( 'id', 'type', 'object', 'actor', 'instrument' );
		$actual_keys   = array_keys( $activity_json['object'] );
		$this->assertEmpty( array_diff( $expected_keys, $actual_keys ), 'All expected keys should be present' );
		$this->assertEmpty( array_diff( $actual_keys, $expected_keys ), 'No unexpected keys should be present' );
	}

	/**
	 * Test queue_reject method creates correct Reject activity.
	 *
	 * @covers ::queue_reject
	 */
	public function test_queue_reject() {
		$activity = $this->create_quote_request_activity();

		Quote_Request::queue_reject( $activity, self::$user_id );

		// Check outbox for Reject response.
		$outbox_posts = get_posts(
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

		$this->assertNotEmpty( $outbox_posts, 'Reject activity should be created' );

		$outbox_post   = $outbox_posts[0];
		$activity_json = json_decode( $outbox_post->post_content, true );
		$visibility    = get_post_meta( $outbox_post->ID, 'activitypub_content_visibility', true );

		$this->assertEquals( 'Reject', $activity_json['type'] );
		$this->assertEquals( 'private', $visibility );
		$this->assertContains( $activity['actor'], $activity_json['to'] );

		// Check that the activity object contains only minimal data.
		$expected_keys = array( 'id', 'type', 'object', 'actor', 'instrument' );
		$actual_keys   = array_keys( $activity_json['object'] );
		$this->assertEmpty( array_diff( $expected_keys, $actual_keys ), 'All expected keys should be present' );
		$this->assertEmpty( array_diff( $actual_keys, $expected_keys ), 'No unexpected keys should be present' );
	}

	/**
	 * Test validate_object with valid QuoteRequest.
	 *
	 * @covers ::validate_object
	 */
	public function test_validate_object_valid_quote_request() {
		$request_data = array(
			'type'       => 'QuoteRequest',
			'actor'      => 'https://remote.example.com/users/remote_user',
			'object'     => get_permalink( self::$post_id ),
			'instrument' => 'https://remote.example.com/posts/456',
		);

		$request = new \WP_REST_Request();
		$request->set_body( \wp_json_encode( $request_data ) );
		$request->set_header( 'content-type', 'application/json' );

		$result = Quote_Request::validate_object( true, 'object', $request );

		$this->assertTrue( $result, 'Valid QuoteRequest should pass validation' );
	}

	/**
	 * Test validate_object with missing required attributes.
	 *
	 * @covers ::validate_object
	 */
	public function test_validate_object_missing_required_attributes() {
		$request_data = array(
			'type'  => 'QuoteRequest',
			'actor' => 'https://remote.example.com/users/remote_user',
			// Missing 'object' and 'instrument'.
		);

		$request = new \WP_REST_Request();
		$request->set_body( \wp_json_encode( $request_data ) );
		$request->set_header( 'content-type', 'application/json' );

		$result = Quote_Request::validate_object( true, 'object', $request );

		$this->assertFalse( $result, 'QuoteRequest missing required attributes should fail validation' );
	}

	/**
	 * Test validate_object with non-QuoteRequest type.
	 *
	 * @covers ::validate_object
	 */
	public function test_validate_object_non_quote_request_type() {
		$request_data = array(
			'type'   => 'Follow',
			'actor'  => 'https://remote.example.com/users/remote_user',
			'object' => get_permalink( self::$post_id ),
		);

		$request = new \WP_REST_Request();
		$request->set_body( \wp_json_encode( $request_data ) );
		$request->set_header( 'content-type', 'application/json' );

		$result = Quote_Request::validate_object( true, 'object', $request );

		$this->assertTrue( $result, 'Non-QuoteRequest types should pass through unchanged' );
	}

	/**
	 * Test validate_object with no type specified.
	 *
	 * @covers ::validate_object
	 */
	public function test_validate_object_no_type() {
		$request_data = array(
			'actor'  => 'https://remote.example.com/users/remote_user',
			'object' => get_permalink( self::$post_id ),
		);

		$request = new \WP_REST_Request();
		$request->set_body( \wp_json_encode( $request_data ) );
		$request->set_header( 'content-type', 'application/json' );

		$result = Quote_Request::validate_object( true, 'object', $request );

		$this->assertFalse( $result, 'Request without type should fail validation' );
	}

	/**
	 * Test that init method properly registers hooks.
	 *
	 * @covers ::init
	 */
	public function test_init_registers_hooks() {
		// Remove existing hooks first.
		remove_all_actions( 'activitypub_inbox_quote_request' );
		remove_all_actions( 'activitypub_rest_inbox_disallowed' );
		remove_all_filters( 'activitypub_validate_object' );

		// Call init.
		Quote_Request::init();

		// Check that hooks are registered.
		$this->assertTrue( has_action( 'activitypub_inbox_quote_request' ) );
		$this->assertTrue( has_action( 'activitypub_rest_inbox_disallowed' ) );
		$this->assertTrue( has_filter( 'activitypub_validate_object' ) );
	}

	/**
	 * Clean up filters after each test.
	 */
	public function tear_down() {
		// Remove all the filters we added during tests.
		remove_all_filters( 'pre_get_remote_metadata_by_actor' );

		parent::tear_down();
	}
}
