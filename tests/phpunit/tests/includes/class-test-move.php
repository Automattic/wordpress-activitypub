<?php
/**
 * Test file for Activitypub Move.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

use Activitypub\Collection\Actors;
use Activitypub\Collection\Outbox;
use Activitypub\Move;

/**
 * Test class for Activitypub Move.
 *
 * @coversDefaultClass \Activitypub\Move
 */
class Test_Move extends \WP_UnitTestCase {

	/**
	 * Test user ID.
	 *
	 * @var int
	 */
	protected static $user_id;

	/**
	 * Create fake data before tests run.
	 */
	public static function set_up_before_class() {
		self::$user_id = self::factory()->user->create( array( 'role' => 'author' ) );
	}

	/**
	 * Test the account() method with valid input.
	 *
	 * @covers ::account
	 */
	public function test_account_with_valid_input() {
		$from = Actors::get_by_id( self::$user_id )->get_id();
		$to   = 'https://newsite.com/user/1';

		$filter = function () use ( $from, $to ) {
			return array(
				'body'     => wp_json_encode(
					array(
						'id'          => $to,
						'type'        => 'Person',
						'alsoKnownAs' => array( $from ),
					)
				),
				'response' => array( 'code' => 200 ),
			);
		};
		add_filter( 'pre_http_request', $filter );

		Move::externally( $from, $to );

		$moved_to = Actors::get_by_id( self::$user_id )->get_moved_to();
		$this->assertEquals( $to, $moved_to );

		remove_filter( 'pre_http_request', $filter );
	}

	/**
	 * Test the account() method with invalid user.
	 *
	 * @covers ::account
	 */
	public function test_account_with_invalid_user() {
		$result = Move::externally(
			'https://example.com/nonexistent/user',
			'https://newsite.com/user/999'
		);

		$this->assertWPError( $result );
		$this->assertEquals( 'activitypub_no_user_found', $result->get_error_code() );
	}

	/**
	 * Test the account() method with invalid target URL.
	 *
	 * @covers ::account
	 */
	public function test_account_with_invalid_target() {
		$from = Actors::get_by_id( self::$user_id )->get_id();
		$to   = 'https://example.com/user/1';

		$filter = function () {
			return new \WP_Error( 'http_request_failed', 'Invalid URL' );
		};
		\add_filter( 'activitypub_pre_http_get_remote_object', $filter, 10, 2 );

		$result = Move::externally( $from, $to );

		$this->assertWPError( $result );
		$this->assertEquals( 'http_request_failed', $result->get_error_code() );

		// A move that never verified must not leave the actor pointing at the target.
		$this->assertNull( Actors::get_by_id( self::$user_id )->get_moved_to() );

		\remove_filter( 'activitypub_pre_http_get_remote_object', $filter );
	}

	/**
	 * A verified move federates a profile Update so followers refresh the actor (FEP-7628).
	 *
	 * @covers ::externally
	 */
	public function test_move_federates_profile_update() {
		$from = Actors::get_by_id( self::$user_id )->get_id();
		$to   = 'https://newsite.com/user/1';

		$filter = function () use ( $from, $to ) {
			return array(
				'body'     => wp_json_encode(
					array(
						'id'          => $to,
						'type'        => 'Person',
						'alsoKnownAs' => array( $from ),
					)
				),
				'response' => array( 'code' => 200 ),
			);
		};
		add_filter( 'pre_http_request', $filter );

		Move::externally( $from, $to );

		remove_filter( 'pre_http_request', $filter );

		$updates = get_posts(
			array(
				'post_type'   => Outbox::POST_TYPE,
				'post_status' => 'any',
				'author'      => self::$user_id,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_query'  => array(
					array(
						'key'   => '_activitypub_activity_type',
						'value' => 'Update',
					),
				),
			)
		);

		$this->assertCount( 1, $updates, 'A move should federate exactly one profile Update.' );
	}

	/**
	 * When the Move itself is not federated, no follower notification is sent.
	 *
	 * @covers ::externally
	 */
	public function test_move_does_not_notify_followers_when_move_fails() {
		$from = Actors::get_by_id( self::$user_id )->get_id();
		$to   = 'https://newsite.com/user/1';

		// Target links back, so verification passes.
		$http = function () use ( $from, $to ) {
			return array(
				'body'     => wp_json_encode(
					array(
						'id'          => $to,
						'type'        => 'Person',
						'alsoKnownAs' => array( $from ),
					)
				),
				'response' => array( 'code' => 200 ),
			);
		};
		add_filter( 'pre_http_request', $http );

		// Fail only the Move's outbox insert; a follow-up profile Update would still succeed.
		$fail_move = function ( $maybe_empty, $postarr ) {
			$data = json_decode( stripslashes( (string) ( $postarr['post_content'] ?? '' ) ), true );

			return ( is_array( $data ) && isset( $data['type'] ) && 'Move' === $data['type'] ) ? true : $maybe_empty;
		};
		add_filter( 'wp_insert_post_empty_content', $fail_move, 10, 2 );

		$result = Move::externally( $from, $to );

		remove_filter( 'wp_insert_post_empty_content', $fail_move, 10 );
		remove_filter( 'pre_http_request', $http );

		$this->assertTrue( empty( $result ) || \is_wp_error( $result ), 'A Move that could not be federated must not return a success id.' );

		$updates = get_posts(
			array(
				'post_type'   => Outbox::POST_TYPE,
				'post_status' => 'any',
				'author'      => self::$user_id,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_query'  => array(
					array(
						'key'   => '_activitypub_activity_type',
						'value' => 'Update',
					),
				),
			)
		);

		$this->assertEmpty( $updates, 'No profile Update should be federated when the Move was not.' );
	}

	/**
	 * A target that does not link back is rejected and the actor is not moved.
	 *
	 * @covers ::externally
	 */
	public function test_account_rejects_unlinked_target() {
		$from = Actors::get_by_id( self::$user_id )->get_id();
		$to   = 'https://newsite.com/user/1';

		// Target resolves, but its alsoKnownAs does not list this actor.
		$filter = function () use ( $to ) {
			return array(
				'body'     => wp_json_encode(
					array(
						'id'          => $to,
						'type'        => 'Person',
						'alsoKnownAs' => array( 'https://newsite.com/user/999' ),
					)
				),
				'response' => array( 'code' => 200 ),
			);
		};
		\add_filter( 'pre_http_request', $filter );

		$result = Move::externally( $from, $to );

		$this->assertWPError( $result );
		$this->assertEquals( 'invalid_target', $result->get_error_code() );
		$this->assertNull( Actors::get_by_id( self::$user_id )->get_moved_to() );

		\remove_filter( 'pre_http_request', $filter );
	}

	/**
	 * The advertised `movedTo` is the target's canonical id, not the URL the move was requested with.
	 *
	 * Receiving servers match the advertised `movedTo` against the Move's `target` and skip the move
	 * when the two differ, so an alias or redirecting input URL must not end up as the stored value.
	 *
	 * @covers ::externally
	 */
	public function test_account_stores_canonical_target_id() {
		$from      = Actors::get_by_id( self::$user_id )->get_id();
		$alias     = 'https://newsite.com/alias/1';
		$canonical = 'https://newsite.com/user/1';

		$filter = function () use ( $from, $canonical ) {
			return array(
				'id'          => $canonical,
				'type'        => 'Person',
				'alsoKnownAs' => array( $from ),
			);
		};
		\add_filter( 'activitypub_pre_http_get_remote_object', $filter );

		$outbox_id = Move::externally( $from, $alias );

		\remove_filter( 'activitypub_pre_http_get_remote_object', $filter );

		$this->assertEquals( $canonical, Actors::get_by_id( self::$user_id )->get_moved_to() );

		$activity = \json_decode( \get_post_field( 'post_content', $outbox_id ) );
		$this->assertEquals( $canonical, $activity->target, 'The federated target must match the advertised movedTo.' );
	}

	/**
	 * A target document that declares no id cannot be federated, so the move is rejected.
	 *
	 * @covers ::externally
	 */
	public function test_account_rejects_target_without_id() {
		$from = Actors::get_by_id( self::$user_id )->get_id();
		$to   = 'https://newsite.com/user/1';

		$filter = function () use ( $from ) {
			return array(
				'type'        => 'Person',
				'alsoKnownAs' => array( $from ),
			);
		};
		\add_filter( 'activitypub_pre_http_get_remote_object', $filter );

		$result = Move::externally( $from, $to );

		\remove_filter( 'activitypub_pre_http_get_remote_object', $filter );

		$this->assertWPError( $result );
		$this->assertEquals( 'invalid_target', $result->get_error_code() );
		$this->assertNull( Actors::get_by_id( self::$user_id )->get_moved_to() );
	}

	/**
	 * Test the account() method with duplicate moves.
	 *
	 * @covers ::account
	 */
	public function test_account_with_duplicate_moves() {
		$from = Actors::get_by_id( self::$user_id )->get_id();
		$to   = 'https://newsite.com/user/1';

		\update_user_option( self::$user_id, 'activitypub_also_known_as', array( 'https://old.example.com/user/1' ) );

		$filter = function () use ( $from, $to ) {
			return array(
				'body'     => wp_json_encode(
					array(
						'id'          => $to,
						'type'        => 'Person',
						'alsoKnownAs' => array( $from ),
					)
				),
				'response' => array( 'code' => 200 ),
			);
		};
		\add_filter( 'pre_http_request', $filter );

		Move::externally( $from, $to );

		$moved_to = Actors::get_by_id( self::$user_id )->get_moved_to();
		$this->assertEquals( $to, $moved_to );

		\remove_filter( 'pre_http_request', $filter );
	}

	/**
	 * Test the account() method with duplicate moves.
	 *
	 * @covers ::account
	 */
	public function test_account_with_blog_author_as_actor() {
		// Change user mode to blog author.
		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_BLOG_MODE );

		$from = Actors::get_by_id( Actors::BLOG_USER_ID )->get_id();
		$to   = 'https://newsite.com/user/0';

		$filter = function () use ( $from, $to ) {
			return array(
				'body'     => wp_json_encode(
					array(
						'id'          => $to,
						'type'        => 'Person',
						'alsoKnownAs' => array( $from ),
					)
				),
				'response' => array( 'code' => 200 ),
			);
		};
		\add_filter( 'pre_http_request', $filter );

		Move::externally( $from, $to );

		$moved_to = Actors::get_by_id( Actors::BLOG_USER_ID )->get_moved_to();
		$this->assertEquals( $to, $moved_to );

		\remove_filter( 'pre_http_request', $filter );
		\delete_option( 'activitypub_actor_mode' );
	}

	/**
	 * Test the internally() method with valid input.
	 *
	 * @covers ::internally
	 */
	public function test_internally_with_valid_input() {
		$from = get_author_posts_url( self::$user_id );
		$to   = Actors::get_by_id( self::$user_id )->get_id();

		Move::internally( $from, $to );

		// Clear cache.
		wp_cache_delete( self::$user_id, 'users' );

		// Updated user should not have moved_to set.
		$moved_to = Actors::get_by_id( self::$user_id )->get_moved_to();
		$this->assertNull( $moved_to );

		$also_known_as = Actors::get_by_id( self::$user_id )->get_also_known_as();
		$this->assertContains( $from, $also_known_as );
	}

	/**
	 * An internal move between two different local users links the target back to the source.
	 *
	 * @covers ::internally
	 */
	public function test_internally_between_distinct_users() {
		$target_id = self::factory()->user->create( array( 'role' => 'author' ) );

		$from = Actors::get_by_id( self::$user_id )->get_id();
		$to   = Actors::get_by_id( $target_id )->get_id();

		Move::internally( $from, $to );

		wp_cache_delete( self::$user_id, 'users' );
		wp_cache_delete( $target_id, 'users' );

		// The source points at the target.
		$this->assertEquals( $to, Actors::get_by_id( self::$user_id )->get_moved_to() );

		// The target links back to the source via alsoKnownAs, so receiving servers accept the move.
		$this->assertContains( $from, Actors::get_by_id( $target_id )->get_also_known_as() );
	}

	/**
	 * Test that the Move Activity created by internally() has the correct properties.
	 *
	 * @covers ::internally
	 */
	public function test_internally_activity_object_properties() {
		$from = get_author_posts_url( self::$user_id );
		$to   = Actors::get_by_id( self::$user_id )->get_id();

		// Call the method and get the outbox item ID.
		$outbox_id = Move::internally( $from, $to );

		// Verify we got a valid outbox ID.
		$this->assertIsInt( $outbox_id );

		// Get the outbox item from the database.
		$outbox_item = get_post( $outbox_id );

		// Verify the outbox item exists.
		$this->assertNotNull( $outbox_item );

		// Get the activity JSON from the outbox item.
		$activity = json_decode( $outbox_item->post_content );

		// Verify the activity type is Move.
		$this->assertEquals( 'Move', $activity->type );

		// Verify the activity object is set to the actor, not the target.
		$this->assertEquals( $from, $activity->object );
		$this->assertEquals( $from, $activity->actor );
		$this->assertEquals( $from, $activity->origin );
		$this->assertEquals( $to, $activity->target );
	}

	/**
	 * Test the change_domain() method with valid input.
	 *
	 * @covers ::change_domain
	 */
	public function test_change_domain_with_valid_input() {
		// Enable blog actor.
		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_AND_BLOG_MODE );

		$old_domain = home_url();
		$new_domain = 'http://newdomain.com';
		\remove_filter( 'option_home', '_config_wp_home' );
		\update_option( 'home', $new_domain );

		// Run the domain change.
		$results = Move::change_domain( $old_domain, $new_domain );

		// Verify the results.
		$this->assertIsArray( $results );

		// Check that each result has the expected structure.
		$result      = reset( $results );
		$outbox_item = json_decode( get_post_field( 'post_content', $result['result'] ) );

		$this->assertSame( $outbox_item->target, $result['actor'] );
		$this->assertStringStartsWith( $new_domain, $outbox_item->target );

		// Verify the old host was stored.
		$this->assertEquals( \wp_parse_url( $old_domain, PHP_URL_HOST ), \get_option( 'activitypub_old_host' ) );

		// Clean up.
		\delete_option( 'activitypub_old_host' );
		\delete_option( 'activitypub_blog_user_old_host_data' );
		\delete_option( 'activitypub_actor_mode' );
		\update_option( 'home', $old_domain );
		\add_filter( 'option_home', '_config_wp_home' );
	}
}
