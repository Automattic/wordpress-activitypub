<?php
/**
 * Test file for Quote Request handler.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Handler;

use Activitypub\Activity\Activity;
use Activitypub\Collection\Followers;
use Activitypub\Collection\Inbox;
use Activitypub\Collection\Outbox;
use Activitypub\Handler\Accept;
use Activitypub\Handler\Delete;
use Activitypub\Handler\Quote_Request;
use Activitypub\Handler\Reject;
use Activitypub\Tests\ActivityPub_Outbox_TestCase;
use Activitypub\Transformer\Post;

/**
 * Test class for Quote Request Handler.
 *
 * @coversDefaultClass \Activitypub\Handler\Quote_Request
 */
class Test_Quote_Request extends ActivityPub_Outbox_TestCase {
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
	 * Remote object mock for the quoted object and its author.
	 *
	 * @var callable
	 */
	protected $remote_object_filter;

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
		$pre_get_remote_metadata_callback = function () use ( $actor_url ) {
			return array(
				'id'    => $actor_url,
				'actor' => $actor_url,
				'type'  => 'Person',
				'inbox' => str_replace( '/users/', '/inbox/', $actor_url ),
			);
		};
		add_filter( 'pre_get_remote_metadata_by_actor', $pre_get_remote_metadata_callback );

		$remote_actor_id = false;

		// Run setup callback if provided.
		if ( 'add_follower' === $setup_callback ) {
			$remote_actor_id = Followers::add( self::$user_id, $actor_url );
			$this->assertNotFalse( $remote_actor_id, 'Should successfully add follower' );
		} elseif ( 'mock_actor_error' === $setup_callback ) {
			// Override the actor metadata filter to return an error.
			remove_filter( 'pre_get_remote_metadata_by_actor', $pre_get_remote_metadata_callback );
			$pre_get_remote_metadata_error_callback = function () {
				return new \WP_Error( 'not_found', 'Actor not found' );
			};
			add_filter( 'pre_get_remote_metadata_by_actor', $pre_get_remote_metadata_error_callback );
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

		// Clean up filters.
		remove_filter( 'pre_get_remote_metadata_by_actor', $pre_get_remote_metadata_callback );
		if ( isset( $pre_get_remote_metadata_error_callback ) ) {
			remove_filter( 'pre_get_remote_metadata_by_actor', $pre_get_remote_metadata_error_callback );
		}
	}

	/**
	 * Test handling of blocked QuoteRequest activities.
	 *
	 * @covers ::handle_blocked_request
	 */
	public function test_handle_blocked_request() {
		$activity = $this->create_quote_request_activity();

		Quote_Request::handle_blocked_request( $activity, self::$user_id, 'quote_request' );

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
	 * Test validate_object rejects an instrument hosted off the actor's domain.
	 *
	 * @covers ::validate_object
	 */
	public function test_validate_object_fails_for_cross_host_instrument() {
		$request_data = array(
			'type'       => 'QuoteRequest',
			'actor'      => 'https://remote.example.com/users/remote_user',
			'object'     => get_permalink( self::$post_id ),
			'instrument' => 'https://victim.example/posts/456',
		);

		$request = new \WP_REST_Request();
		$request->set_body( \wp_json_encode( $request_data ) );
		$request->set_header( 'content-type', 'application/json' );

		$result = Quote_Request::validate_object( true, 'object', $request );

		$this->assertFalse( $result, 'QuoteRequest with a cross-host instrument should fail validation' );
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
		// Call init.
		Quote_Request::init();

		// Check that hooks are registered.
		$this->assertTrue( has_action( 'activitypub_inbox_quote_request' ) );
		$this->assertTrue( has_action( 'activitypub_rest_inbox_disallowed' ) );
		$this->assertTrue( has_filter( 'activitypub_validate_object' ) );
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
		$outbox_activities     = array();
		$track_outbox_callback = function ( $outbox_id, $activity, $user_id, $visibility ) use ( &$outbox_activities ) {
			$outbox_activities[] = array(
				'outbox_id'  => $outbox_id,
				'activity'   => $activity,
				'user_id'    => $user_id,
				'visibility' => $visibility,
			);
		};
		\add_action( 'post_activitypub_add_to_outbox', $track_outbox_callback, 10, 4 );

		// Delete the quote comment.
		wp_delete_comment( $comment_id, true );

		// Verify Reject activity was queued.
		$this->assertNotEmpty( $outbox_activities, 'A Reject activity should be queued' );

		$reject_activity = null;
		foreach ( $outbox_activities as $item ) {
			if ( isset( $item['activity'] ) && $item['activity'] instanceof Activity ) {
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

		// Clean up action.
		\remove_action( 'post_activitypub_add_to_outbox', $track_outbox_callback );
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
		$reject_sent           = false;
		$track_reject_callback = function ( $outbox_id, $activity ) use ( &$reject_sent ) {
			if ( $activity instanceof Activity ) {
				$activity_array = $activity->to_array();
				if ( 'Reject' === $activity_array['type'] ) {
					$reject_sent = true;
				}
			}
		};
		\add_action( 'post_activitypub_add_to_outbox', $track_reject_callback, 10, 2 );

		// Delete the regular comment.
		wp_delete_comment( $comment_id, true );

		// Verify no Reject activity was sent.
		$this->assertFalse( $reject_sent, 'Reject should not be sent for non-quote comments' );

		// Clean up action.
		\remove_action( 'post_activitypub_add_to_outbox', $track_reject_callback );
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
		$activity = new Activity();
		$activity->from_array( $quote_request_activity );
		// Ensure the ID is explicitly set.
		$activity->set_id( $quote_request_id );

		// Store the activity in the inbox.
		$inbox_id = Inbox::add( $activity, array( self::$user_id ) );
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
		$outbox_activities               = array();
		$track_outbox_for_inbox_callback = function ( $outbox_id, $activity, $user_id, $visibility ) use ( &$outbox_activities ) {
			$outbox_activities[] = array(
				'outbox_id'  => $outbox_id,
				'activity'   => $activity,
				'user_id'    => $user_id,
				'visibility' => $visibility,
			);
		};
		\add_action( 'post_activitypub_add_to_outbox', $track_outbox_for_inbox_callback, 10, 4 );

		// Delete the quote comment.
		wp_delete_comment( $comment_id, true );

		// Verify Reject activity uses original QuoteRequest data.
		$this->assertNotEmpty( $outbox_activities, 'A Reject activity should be queued' );

		$reject_activity = null;
		foreach ( $outbox_activities as $item ) {
			if ( isset( $item['activity'] ) && $item['activity'] instanceof Activity ) {
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

		// Clean up action.
		\remove_action( 'post_activitypub_add_to_outbox', $track_outbox_for_inbox_callback );
	}

	/*
	 * ------------------------------------------------------------------
	 * Responses to our own QuoteRequests: Accept, Reject and stamp Delete.
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
					return \get_permalink( $post_id ) === ( $activity['instrument'] ?? '' );
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
				'instrument' => \get_permalink( $post_id ),
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
	 * Build the Delete for a stamp.
	 *
	 * @param string $actor Sender.
	 * @param string $stamp Stamp URI.
	 *
	 * @return array Delete activity.
	 */
	private function build_stamp_delete( $actor = 'https://remote.example/users/alice', $stamp = 'https://remote.example/stamps/1' ) {
		return array(
			'type'   => 'Delete',
			'actor'  => $actor,
			'object' => $stamp,
		);
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
				'interactingObject' => \get_permalink( $post_id ),
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
	 * Mock the HTTP response the stamp URL gives when fetched directly (tombstone check).
	 *
	 * @param int    $code Response code.
	 * @param string $body Response body.
	 *
	 * @return callable The filter, to remove later.
	 */
	private function mock_stamp_response( $code, $body = '' ) {
		$filter = function ( $pre, $args, $url ) use ( $code, $body ) {
			if ( 'https://remote.example/stamps/1' !== $url ) {
				return $pre;
			}

			return array(
				'response' => array( 'code' => $code ),
				'headers'  => array( 'content-type' => 'application/activity+json' ),
				'body'     => $body,
			);
		};
		\add_filter( 'pre_http_request', $filter, 10, 3 );

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
							'value' => \get_permalink( $post_id ),
						),
					),
				)
			)
		);
	}

	/**
	 * A valid Accept stores the stamp and queues an Update.
	 *
	 * @covers ::accept
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
	 * Accepts from the wrong actor, with a stamp for another object, or with a foreign stamp host are ignored.
	 *
	 * @covers ::accept
	 */
	public function test_accept_ignored_when_sender_or_stamp_invalid() {
		$post_id = $this->create_quote_post();

		$filter = $this->mock_stamp( $post_id );
		Accept::handle_accept( $this->build_accept( $post_id, 'https://remote.example/stamps/1', 'https://remote.example/users/mallory' ), self::$user_id );
		\remove_filter( 'activitypub_pre_http_get_remote_object', $filter );
		$this->assertEmpty( \get_post_meta( $post_id, '_activitypub_quote_authorization', true ) );

		$filter = $this->mock_stamp( $post_id, array( 'interactingObject' => 'https://elsewhere.example/other' ) );
		Accept::handle_accept( $this->build_accept( $post_id ), self::$user_id );
		\remove_filter( 'activitypub_pre_http_get_remote_object', $filter );
		$this->assertEmpty( \get_post_meta( $post_id, '_activitypub_quote_authorization', true ) );

		$filter = $this->mock_stamp( $post_id, array( 'type' => 'Note' ) );
		Accept::handle_accept( $this->build_accept( $post_id ), self::$user_id );
		\remove_filter( 'activitypub_pre_http_get_remote_object', $filter );
		$this->assertEmpty( \get_post_meta( $post_id, '_activitypub_quote_authorization', true ) );

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
	 * @covers ::accept
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
	 * @covers ::accept
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

	/**
	 * A Reject marks the quote declined, drops any stamp and queues an Update.
	 *
	 * @covers ::reject
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
	 * A Reject from someone other than the quoted author is ignored.
	 *
	 * @covers ::reject
	 */
	public function test_reject_from_wrong_actor_ignored() {
		$post_id = $this->create_quote_post();

		Reject::handle_reject( $this->build_reject( $post_id, 'https://remote.example/users/mallory' ), self::$user_id );

		$this->assertEmpty( \get_post_meta( $post_id, '_activitypub_quote_rejected', true ) );
	}

	/**
	 * Deleting the stamp clears it on our post and queues an Update once the stamp is gone.
	 *
	 * @covers ::revoke
	 */
	public function test_stamp_delete_clears_authorization() {
		$post_id = $this->create_quote_post();
		\update_post_meta( $post_id, '_activitypub_quote_authorization', 'https://remote.example/stamps/1' );
		$before = $this->count_updates( $post_id );

		$gone = $this->mock_stamp_response( 404 );
		Delete::handle_delete( $this->build_stamp_delete(), self::$user_id );
		\remove_filter( 'pre_http_request', $gone );

		$this->assertEmpty( \get_post_meta( $post_id, '_activitypub_quote_authorization', true ) );
		$this->assertSame( $before + 1, $this->count_updates( $post_id ) );
	}

	/**
	 * A Delete for a stamp from the wrong actor changes nothing.
	 *
	 * @covers ::revoke
	 */
	public function test_stamp_delete_from_wrong_actor_ignored() {
		$post_id = $this->create_quote_post();
		\update_post_meta( $post_id, '_activitypub_quote_authorization', 'https://remote.example/stamps/1' );
		$before = $this->count_updates( $post_id );

		$gone = $this->mock_stamp_response( 404 );
		Delete::handle_delete( $this->build_stamp_delete( 'https://remote.example/users/mallory' ), self::$user_id );
		\remove_filter( 'pre_http_request', $gone );

		$this->assertSame( 'https://remote.example/stamps/1', \get_post_meta( $post_id, '_activitypub_quote_authorization', true ) );
		$this->assertSame( $before, $this->count_updates( $post_id ) );
	}

	/**
	 * A Delete whose actor lives on another host than the stamp changes nothing.
	 *
	 * @covers ::revoke
	 */
	public function test_stamp_delete_cross_host_actor_ignored() {
		$post_id = $this->create_quote_post();
		\update_post_meta( $post_id, '_activitypub_quote_authorization', 'https://remote.example/stamps/1' );
		$before = $this->count_updates( $post_id );

		$gone = $this->mock_stamp_response( 404 );
		Delete::handle_delete( $this->build_stamp_delete( 'https://other.example/users/alice' ), self::$user_id );
		\remove_filter( 'pre_http_request', $gone );

		$this->assertSame( 'https://remote.example/stamps/1', \get_post_meta( $post_id, '_activitypub_quote_authorization', true ) );
		$this->assertSame( $before, $this->count_updates( $post_id ) );
	}
}
