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
			$remote_actor_id = Followers::add( self::$user_id, $actor_url );
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

	/**
	 * Test that deleting a quote comment sends a Reject activity.
	 *
	 * @covers ::handle_quote_delete
	 */
	public function test_delete_quote_comment_sends_reject() {
		$actor_url      = 'https://mastodon.example/users/alice';
		$instrument_url = 'https://mastodon.example/users/alice/statuses/123';

		// Create a quote comment (simulating an accepted QuoteRequest).
		$comment_id = wp_insert_comment(
			array(
				'comment_post_ID'    => self::$post_id,
				'comment_author'     => 'Alice',
				'comment_author_url' => $actor_url,
				'comment_content'    => 'Quote comment content',
				'comment_type'       => 'quote',
				'comment_approved'   => 1,
				'user_id'            => 0,
			)
		);

		$this->assertIsInt( $comment_id, 'Quote comment should be created' );

		// Add metadata that would be set when quote is accepted.
		\add_comment_meta( $comment_id, 'source_url', $instrument_url );
		\add_comment_meta( $comment_id, 'source_id', $instrument_url );
		\add_post_meta( self::$post_id, '_activitypub_quoted_by', $instrument_url );

		// Verify metadata is set.
		$quoted_by_meta = \get_post_meta( self::$post_id, '_activitypub_quoted_by', false );
		$this->assertContains( $instrument_url, $quoted_by_meta, 'Instrument URL should be in quoted_by meta' );

		// Track outbox activities.
		$outbox_activities = array();
		\add_action(
			'post_activitypub_add_to_outbox',
			function ( $outbox_id, $activity, $user_id, $visibility ) use ( &$outbox_activities ) {
				$outbox_activities[] = array(
					'outbox_id'  => $outbox_id,
					'activity'   => $activity,
					'user_id'    => $user_id,
					'visibility' => $visibility,
				);
			},
			10,
			4
		);

		// Delete the quote comment.
		wp_delete_comment( $comment_id, true );

		// Verify Reject activity was queued.
		$this->assertNotEmpty( $outbox_activities, 'A Reject activity should be queued' );

		$reject_activity = null;
		foreach ( $outbox_activities as $item ) {
			if ( isset( $item['activity'] ) && $item['activity'] instanceof \Activitypub\Activity\Activity ) {
				$activity_array = $item['activity']->to_array();
				if ( 'Reject' === $activity_array['type'] ) {
					$reject_activity = $activity_array;
					break;
				}
			}
		}

		$this->assertNotNull( $reject_activity, 'A Reject activity should be created' );

		// Verify the Reject activity has correct structure.
		$this->assertEquals( 'Reject', $reject_activity['type'] );
		$this->assertIsArray( $reject_activity['object'] );
		$this->assertEquals( 'QuoteRequest', $reject_activity['object']['type'] );
		$this->assertEquals( $actor_url, $reject_activity['object']['actor'] );
		$this->assertEquals( $instrument_url, $reject_activity['object']['instrument'] );

		// Verify metadata was removed.
		$quoted_by_after = \get_post_meta( self::$post_id, '_activitypub_quoted_by', false );
		$this->assertNotContains( $instrument_url, $quoted_by_after, 'Instrument URL should be removed from quoted_by meta' );
	}

	/**
	 * Test that deleting a non-quote comment doesn't send Reject.
	 *
	 * @covers ::handle_quote_delete
	 */
	public function test_delete_regular_comment_no_reject() {
		// Create a regular comment.
		$comment_id = wp_insert_comment(
			array(
				'comment_post_ID'    => self::$post_id,
				'comment_author'     => 'Bob',
				'comment_author_url' => 'https://example.com/users/bob',
				'comment_content'    => 'Regular comment',
				'comment_type'       => 'comment',
				'comment_approved'   => 1,
			)
		);

		// Track outbox activities.
		$reject_sent = false;
		\add_action(
			'post_activitypub_add_to_outbox',
			function ( $outbox_id, $activity ) use ( &$reject_sent ) {
				if ( $activity instanceof \Activitypub\Activity\Activity ) {
					$activity_array = $activity->to_array();
					if ( 'Reject' === $activity_array['type'] ) {
						$reject_sent = true;
					}
				}
			},
			10,
			2
		);

		// Delete the regular comment.
		wp_delete_comment( $comment_id, true );

		// Verify no Reject activity was sent.
		$this->assertFalse( $reject_sent, 'Reject should not be sent for non-quote comments' );
	}

	/**
	 * Test that deleting quote comment without metadata handles gracefully.
	 *
	 * @covers ::handle_quote_delete
	 */
	public function test_delete_quote_comment_without_metadata() {
		// Create a quote comment without metadata.
		$comment_id = wp_insert_comment(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_author'   => 'Carol',
				'comment_content'  => 'Quote without metadata',
				'comment_type'     => 'quote',
				'comment_approved' => 1,
			)
		);

		// This should not throw an error or send Reject.
		$exception_thrown = false;
		try {
			wp_delete_comment( $comment_id, true );
		} catch ( \Exception $e ) {
			$exception_thrown = true;
		}

		$this->assertFalse( $exception_thrown, 'Deleting quote without metadata should not throw exception' );
	}

	/**
	 * Test that deletion retrieves original QuoteRequest from inbox.
	 *
	 * @covers ::handle_quote_delete
	 */
	public function test_delete_uses_inbox_item() {
		$actor_url        = 'https://mastodon.example/users/dave';
		$instrument_url   = 'https://mastodon.example/users/dave/statuses/456';
		$quote_request_id = 'https://mastodon.example/users/dave/activities/789';

		// Create a full QuoteRequest activity and store it in inbox.
		$quote_request_activity = array(
			'@context'   => 'https://www.w3.org/ns/activitystreams',
			'id'         => $quote_request_id,
			'type'       => 'QuoteRequest',
			'actor'      => $actor_url,
			'object'     => \get_permalink( self::$post_id ),
			'instrument' => $instrument_url,
			'published'  => gmdate( 'Y-m-d\TH:i:s\Z' ),
		);

		// Create Activity object and set properties.
		$activity = new \Activitypub\Activity\Activity();
		$activity->from_array( $quote_request_activity );
		// Ensure the ID is explicitly set.
		$activity->set_id( $quote_request_id );

		// Store the activity in the inbox.
		$inbox_id = \Activitypub\Collection\Inbox::add( $activity, array( self::$user_id ) );
		$this->assertIsInt( $inbox_id, 'QuoteRequest should be stored in inbox' );

		// Verify the QuoteRequest was stored correctly in the inbox.
		$stored_object_id = \get_post_meta( $inbox_id, '_activitypub_object_id', true );
		$this->assertEquals( $instrument_url, $stored_object_id, 'Inbox should store instrument URL as object_id for QuoteRequest' );

		// Simulate accepting the quote request (what queue_accept does).
		\add_post_meta( self::$post_id, '_activitypub_quoted_by', $instrument_url );

		// Create the quote comment.
		$comment_id = wp_insert_comment(
			array(
				'comment_post_ID'    => self::$post_id,
				'comment_author'     => 'Dave',
				'comment_author_url' => $actor_url,
				'comment_content'    => 'Quote comment',
				'comment_type'       => 'quote',
				'comment_approved'   => 1,
			)
		);
		\add_comment_meta( $comment_id, 'source_url', $instrument_url );

		// Track outbox activities.
		$outbox_activities = array();
		\add_action(
			'post_activitypub_add_to_outbox',
			function ( $outbox_id, $activity, $user_id, $visibility ) use ( &$outbox_activities ) {
				$outbox_activities[] = array(
					'outbox_id'  => $outbox_id,
					'activity'   => $activity,
					'user_id'    => $user_id,
					'visibility' => $visibility,
				);
			},
			10,
			4
		);

		// Delete the quote comment.
		wp_delete_comment( $comment_id, true );

		// Verify Reject activity uses original QuoteRequest data.
		$this->assertNotEmpty( $outbox_activities, 'A Reject activity should be queued' );

		$reject_activity = null;
		foreach ( $outbox_activities as $item ) {
			if ( isset( $item['activity'] ) && $item['activity'] instanceof \Activitypub\Activity\Activity ) {
				$activity_array = $item['activity']->to_array();
				if ( 'Reject' === $activity_array['type'] ) {
					$reject_activity = $activity_array;
					break;
				}
			}
		}

		$this->assertNotNull( $reject_activity, 'A Reject activity should be created' );

		// Verify the Reject uses the original activity data (proof it came from inbox).
		$this->assertArrayHasKey( 'object', $reject_activity );

		// The key test: verify the reject object is a QuoteRequest with proper structure.
		$this->assertEquals( 'QuoteRequest', $reject_activity['object']['type'], 'Should have QuoteRequest type' );

		// Verify it has an ID field. If it was reconstructed via fallback, it wouldn't have an 'id' at all.
		// The ID value should be the exact value from the original QuoteRequest activity stored in the inbox ($quote_request_id), not auto-generated.
		$this->assertArrayHasKey( 'id', $reject_activity['object'], 'Should have ID from inbox (fallback reconstruction has no ID)' );
		$this->assertEquals( $quote_request_id, $reject_activity['object']['id'], 'ID should match the original QuoteRequest activity from inbox' );

		// Verify the minimal required fields are present.
		$this->assertEquals( $actor_url, $reject_activity['object']['actor'] );
		$this->assertEquals( \get_permalink( self::$post_id ), $reject_activity['object']['object'] );
		$this->assertEquals( $instrument_url, $reject_activity['object']['instrument'] );
	}
}
