<?php
/**
 * Test file for Activitypub Announce Handler.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Handler;

use Activitypub\Activity\Activity;
use Activitypub\Handler\Announce;
use Activitypub\Model\Blog;

/**
 * Test class for Activitypub Announce Handler.
 *
 * @coversDefaultClass \Activitypub\Handler\Announce
 */
class Test_Announce extends \WP_UnitTestCase {

	/**
	 * User ID.
	 *
	 * @var int
	 */
	public $user_id;

	/**
	 * Post ID.
	 *
	 * @var int
	 */
	public $post_id;

	/**
	 * Post permalink.
	 *
	 * @var string
	 */
	public $post_permalink;

	/**
	 * Set up the test.
	 */
	public function set_up() {
		parent::set_up();
		$this->user_id = 1;

		$this->post_id        = \wp_insert_post(
			array(
				'post_author'  => $this->user_id,
				'post_content' => 'test',
				'post_status'  => 'publish',
			)
		);
		$this->post_permalink = \get_permalink( $this->post_id );

		\add_filter( 'pre_get_remote_metadata_by_actor', array( $this, 'get_remote_metadata_by_actor' ), 0, 2 );
	}

	/**
	 * Tear down the test.
	 */
	public function tear_down() {
		\remove_filter( 'pre_get_remote_metadata_by_actor', array( $this, 'get_remote_metadata_by_actor' ) );
		parent::tear_down();
	}

	/**
	 * Get remote metadata by actor.
	 *
	 * @param string $value The value.
	 * @param string $actor The actor.
	 * @return array The metadata.
	 */
	public function get_remote_metadata_by_actor( $value, $actor ) {
		return array(
			'name' => 'Example User',
			'icon' => array(
				'url' => 'https://example.com/icon',
			),
			'url'  => $actor,
			'id'   => 'http://example.org/users/example',
		);
	}

	/**
	 * Create a test object.
	 *
	 * @return array The test object.
	 */
	public static function create_test_object() {
		return array(
			'actor'  => 'https://example.com/user',
			'type'   => 'Announce',
			'id'     => 'https://example.com/id/' . microtime( true ),
			'to'     => array( 'https://example.com/user' ),
			'cc'     => array( 'https://www.w3.org/ns/activitystreams#Public' ),
			'object' => 'https://example.com/post/123',
		);
	}

	/**
	 * Test handle announce.
	 *
	 * @covers ::handle_announce
	 */
	public function test_handle_announce() {
		$external_actor = 'https://example.com/users/testuser';

		$object = array(
			'actor'  => $external_actor,
			'type'   => 'Announce',
			'id'     => 'https://example.com/id/' . microtime( true ),
			'to'     => array( $external_actor ),
			'cc'     => array( 'https://www.w3.org/ns/activitystreams#Public' ),
			'object' => $this->post_permalink,
		);

		Announce::handle_announce( $object, $this->user_id );

		$args = array(
			'type'    => 'repost',
			'post_id' => $this->post_id,
		);

		$query  = new \WP_Comment_Query( $args );
		$result = $query->comments;

		$this->assertInstanceOf( 'WP_Comment', $result[0] );
	}

	/**
	 * Test handle announces.
	 *
	 * @covers ::handle_announce
	 *
	 * @dataProvider data_handle_announces
	 *
	 * @param array  $announce  The announce.
	 * @param int    $recursion The recursion.
	 * @param string $message   The message.
	 */
	public function test_handle_announces( $announce, $recursion, $message ) {
		// Set up mock action.
		$inbox_action = new \MockAction();
		\add_action( 'activitypub_inbox', array( $inbox_action, 'action' ) );

		$activity = Activity::init_from_array( $announce );
		Announce::handle_announce( $announce, $this->user_id, $activity );

		$this->assertEquals( $recursion, $inbox_action->get_call_count(), $message );
	}

	/**
	 * An announced activity that is fetchable from its id and attributed to an
	 * actor on that same host is relayed to the inbox handlers.
	 *
	 * @covers ::handle_announce
	 */
	public function test_handle_announce_relays_origin_authentic_activity() {
		$activity_url = 'https://example.com/activities/like-1';
		$fetch        = function ( $pre, $url_or_object ) use ( $activity_url ) {
			if ( $activity_url !== $url_or_object ) {
				return $pre;
			}

			return array(
				'id'     => $activity_url,
				'type'   => 'Like',
				'actor'  => 'https://example.com/user',
				'object' => $this->post_permalink,
			);
		};
		\add_filter( 'activitypub_pre_http_get_remote_object', $fetch, 10, 2 );

		$inbox = new \MockAction();
		\add_action( 'activitypub_inbox', array( $inbox, 'action' ) );

		$announce = array(
			'actor'  => 'https://booster.example/user',
			'type'   => 'Announce',
			'id'     => 'https://booster.example/a/1',
			'to'     => array( 'https://www.w3.org/ns/activitystreams#Public' ),
			'object' => $activity_url,
		);
		Announce::handle_announce( $announce, $this->user_id, Activity::init_from_array( $announce ) );

		\remove_filter( 'activitypub_pre_http_get_remote_object', $fetch, 10 );

		$this->assertSame( 1, $inbox->get_call_count(), 'An origin-authentic announced activity must be relayed.' );
	}

	/**
	 * An announced activity that declares no id is still relayed.
	 *
	 * `Http::get_remote_object()` returns an id-less document as served, so it was never re-fetched
	 * and the origin check already covers it. Requiring an id would drop legitimate relayed traffic.
	 *
	 * @covers ::handle_announce
	 */
	public function test_handle_announce_relays_activity_without_id() {
		$activity_url = 'https://example.com/activities/idless-1';
		$fetch        = function ( $pre, $url_or_object ) use ( $activity_url ) {
			if ( $activity_url !== $url_or_object ) {
				return $pre;
			}

			return array(
				'type'   => 'Like',
				'actor'  => 'https://example.com/user',
				'object' => $this->post_permalink,
			);
		};
		\add_filter( 'activitypub_pre_http_get_remote_object', $fetch, 10, 2 );

		$inbox = new \MockAction();
		\add_action( 'activitypub_inbox', array( $inbox, 'action' ) );

		$announce = array(
			'actor'  => 'https://booster.example/user',
			'type'   => 'Announce',
			'id'     => 'https://booster.example/a/idless',
			'to'     => array( 'https://www.w3.org/ns/activitystreams#Public' ),
			'object' => $activity_url,
		);
		Announce::handle_announce( $announce, $this->user_id, Activity::init_from_array( $announce ) );

		\remove_filter( 'activitypub_pre_http_get_remote_object', $fetch, 10 );

		$this->assertSame( 1, $inbox->get_call_count(), 'An announced activity without an id must still be relayed.' );
	}

	/**
	 * An announced activity whose own id is on a different host than the actor is not relayed.
	 *
	 * `Http::get_remote_object()` may return a document re-fetched from the id it declares, so the
	 * URL that was requested is not always the host that served the answer. The document's own id
	 * is, and an authentic activity always shares a host with its actor.
	 *
	 * @covers ::handle_announce
	 */
	public function test_handle_announce_rejects_activity_whose_id_host_differs_from_actor() {
		$activity_url = 'https://example.com/activities/like-2';
		$fetch        = function ( $pre, $url_or_object ) use ( $activity_url ) {
			if ( $activity_url !== $url_or_object ) {
				return $pre;
			}

			// Requested from example.com, but the document belongs to attacker.test.
			return array(
				'id'     => 'https://attacker.test/activities/like-2',
				'type'   => 'Like',
				'actor'  => 'https://example.com/user',
				'object' => $this->post_permalink,
			);
		};
		\add_filter( 'activitypub_pre_http_get_remote_object', $fetch, 10, 2 );

		$inbox = new \MockAction();
		\add_action( 'activitypub_inbox', array( $inbox, 'action' ) );

		$announce = array(
			'actor'  => 'https://booster.example/user',
			'type'   => 'Announce',
			'id'     => 'https://booster.example/a/2',
			'to'     => array( 'https://www.w3.org/ns/activitystreams#Public' ),
			'object' => $activity_url,
		);
		Announce::handle_announce( $announce, $this->user_id, Activity::init_from_array( $announce ) );

		\remove_filter( 'activitypub_pre_http_get_remote_object', $fetch, 10 );

		$this->assertSame( 0, $inbox->get_call_count(), 'An activity whose id host differs from its actor must not be relayed.' );
	}

	/**
	 * An announced activity whose actor is on a different host than the origin it
	 * was fetched from is a forgery and must not be relayed, regardless of type.
	 * This is the core fix for the nested Announce -> Undo/Delete authority bypass.
	 *
	 * @covers ::handle_announce
	 */
	public function test_handle_announce_rejects_cross_origin_activity() {
		$activity_url = 'https://attacker.test/activities/undo-1';
		$fetch        = function ( $pre, $url_or_object ) use ( $activity_url ) {
			if ( $activity_url !== $url_or_object ) {
				return $pre;
			}

			// Served by attacker.test but claims a victim actor on another host.
			return array(
				'id'     => $activity_url,
				'type'   => 'Undo',
				'actor'  => 'https://victim.test/users/carol',
				'object' => 'https://victim.test/acts/reply-1',
			);
		};
		\add_filter( 'activitypub_pre_http_get_remote_object', $fetch, 10, 2 );

		$inbox = new \MockAction();
		\add_action( 'activitypub_inbox', array( $inbox, 'action' ) );

		$announce = array(
			'actor'  => 'https://attacker.test/users/mallory',
			'type'   => 'Announce',
			'id'     => 'https://attacker.test/a/1',
			'to'     => array( 'https://www.w3.org/ns/activitystreams#Public' ),
			'object' => $activity_url,
		);
		Announce::handle_announce( $announce, $this->user_id, Activity::init_from_array( $announce ) );

		\remove_filter( 'activitypub_pre_http_get_remote_object', $fetch, 10 );

		$this->assertSame( 0, $inbox->get_call_count(), 'A cross-origin (forged) announced activity must not be relayed.' );
	}

	/**
	 * The reported PoC shape: an Undo naming a victim actor, embedded inline in the
	 * Announce, must never be dispatched from that inline copy. It is resolved from
	 * its id, and an unfetchable / unverifiable activity is dropped.
	 *
	 * @covers ::handle_announce
	 */
	public function test_handle_announce_does_not_relay_embedded_activity() {
		$undo_id = 'https://attacker.test/acts/undo-2';
		$fetch   = function ( $pre, $url_or_object ) use ( $undo_id ) {
			if ( $undo_id !== $url_or_object ) {
				return $pre;
			}

			// The inline Undo is not served at its id (transient activity).
			return new \WP_Error( 'http_request_failed', 'not found' );
		};
		\add_filter( 'activitypub_pre_http_get_remote_object', $fetch, 10, 2 );

		$inbox = new \MockAction();
		\add_action( 'activitypub_inbox_undo', array( $inbox, 'action' ) );

		$announce = array(
			'actor'  => 'https://attacker.test/users/mallory',
			'type'   => 'Announce',
			'id'     => 'https://attacker.test/a/2',
			'to'     => array( 'https://www.w3.org/ns/activitystreams#Public' ),
			'object' => array(
				'type'   => 'Undo',
				'id'     => $undo_id,
				'actor'  => 'https://victim.test/users/carol',
				'object' => 'https://victim.test/acts/reply-2',
			),
		);
		Announce::handle_announce( $announce, $this->user_id, Activity::init_from_array( $announce ) );

		\remove_filter( 'activitypub_pre_http_get_remote_object', $fetch, 10 );

		$this->assertSame( 0, $inbox->get_call_count(), 'An embedded inner activity must not be dispatched from its inline copy.' );
	}

	/**
	 * Test maybe save announce.
	 *
	 * @covers ::maybe_save_announce
	 */
	public function test_maybe_save_announce() {
		$external_actor = 'https://example.com/users/testuser';

		$activity = array(
			'actor'  => $external_actor,
			'type'   => 'Announce',
			'id'     => 'https://example.com/id/' . microtime( true ),
			'to'     => array( $external_actor ),
			'object' => $this->post_permalink,
		);

		// Set up mock action.
		$inbox_action = new \MockAction();
		\add_action( 'activitypub_handled_announce', array( $inbox_action, 'action' ) );

		Announce::maybe_save_announce( $activity, $this->user_id );
		Announce::maybe_save_announce( $activity, $this->user_id );

		$activity['id'] = 'https://example.com/id/' . microtime( true );
		Announce::maybe_save_announce( $activity, $this->user_id );

		$this->assertEquals( 2, $inbox_action->get_call_count() );
	}

	/**
	 * Data provider for test_handle_announces.
	 *
	 * @return array The data provider.
	 */
	public static function data_handle_announces() {
		return array(
			array(
				'announce'  => self::create_test_object(),
				'recursion' => 0,
				'message'   => 'Simple Announce of an URL.',
			),
			array(
				'announce'  => array(
					'actor'  => 'https://example.com/user',
					'type'   => 'Announce',
					'id'     => 'https://example.com/id/' . microtime( true ),
					'to'     => array( 'https://example.com/user' ),
					'object' => array(
						'actor'   => 'https://example.com/user',
						'type'    => 'Note',
						'id'      => 'https://example.com/post/123',
						'to'      => array( 'https://example.com/user' ),
						'content' => 'text',
					),
				),
				'recursion' => 0,
				'message'   => 'Announce of a Note-Object.',
			),
			array(
				'announce'  => array(
					'actor'  => 'https://example.com/user',
					'type'   => 'Announce',
					'id'     => 'https://example.com/id/' . microtime( true ),
					'to'     => array( 'https://example.com/user' ),
					'object' => self::create_test_object(),
				),
				'recursion' => 0,
				'message'   => 'Embedded inner activity is not relayed without an origin-authenticated fetch.',
			),
		);
	}

	/**
	 * Test that announces from the blog actor are ignored.
	 *
	 * @covers ::handle_announce
	 */
	public function test_ignore_blog_actor_announce() {
		$blog     = new Blog();
		$blog_url = $blog->get_id();

		$object = array(
			'actor'  => $blog_url,
			'type'   => 'Announce',
			'id'     => 'https://example.com/id/' . microtime( true ),
			'to'     => array( 'https://www.w3.org/ns/activitystreams#Public' ),
			'cc'     => array( 'https://www.w3.org/ns/activitystreams#Public' ),
			'object' => $this->post_permalink,
		);

		// Set up mock action to track whether the announce is handled (should be ignored).
		$handled_action = new \MockAction();
		\add_action( 'activitypub_handled_announce', array( $handled_action, 'action' ) );

		// Call with blog actor as sender - should be ignored.
		Announce::handle_announce( $object, $this->user_id );

		// Verify the announce was NOT handled.
		$this->assertEquals( 0, $handled_action->get_call_count() );

		// Verify no comment was created.
		$args = array(
			'type'    => 'repost',
			'post_id' => $this->post_id,
		);

		$query  = new \WP_Comment_Query( $args );
		$result = $query->comments;

		$this->assertEmpty( $result );

		\remove_action( 'activitypub_handled_announce', array( $handled_action, 'action' ) );
	}

	/**
	 * Test that announces from external actors are not ignored.
	 *
	 * @covers ::handle_announce
	 */
	public function test_external_actor_announce_not_ignored() {
		$external_actor = 'https://external.example.com/users/someone';

		$object = array(
			'actor'  => $external_actor,
			'type'   => 'Announce',
			'id'     => 'https://external.example.com/id/' . microtime( true ),
			'to'     => array( 'https://www.w3.org/ns/activitystreams#Public' ),
			'cc'     => array( 'https://www.w3.org/ns/activitystreams#Public' ),
			'object' => $this->post_permalink,
		);

		// Set up mock action to verify the announce is handled.
		$handled_action = new \MockAction();
		\add_action( 'activitypub_handled_announce', array( $handled_action, 'action' ) );

		// Call with external actor - should be processed.
		Announce::handle_announce( $object, $this->user_id );

		// Verify the announce WAS handled.
		$this->assertEquals( 1, $handled_action->get_call_count() );

		// Verify comment was created.
		$args = array(
			'type'    => 'repost',
			'post_id' => $this->post_id,
		);

		$query  = new \WP_Comment_Query( $args );
		$result = $query->comments;

		$this->assertNotEmpty( $result );
		$this->assertInstanceOf( 'WP_Comment', $result[0] );

		\remove_action( 'activitypub_handled_announce', array( $handled_action, 'action' ) );
	}

	/**
	 * Test that announces from same domain but different actor are not ignored.
	 *
	 * @covers ::handle_announce
	 */
	public function test_same_domain_different_actor_not_ignored() {
		// Get a regular user actor URL (not the blog actor).
		$user_url = \get_author_posts_url( $this->user_id );

		$object = array(
			'actor'  => $user_url,
			'type'   => 'Announce',
			'id'     => \home_url( '/activity/' . microtime( true ) ),
			'to'     => array( 'https://www.w3.org/ns/activitystreams#Public' ),
			'cc'     => array( 'https://www.w3.org/ns/activitystreams#Public' ),
			'object' => $this->post_permalink,
		);

		// Set up mock action to verify the announce is handled.
		$handled_action = new \MockAction();
		\add_action( 'activitypub_handled_announce', array( $handled_action, 'action' ) );

		// Call with same domain but user actor - should be processed.
		Announce::handle_announce( $object, $this->user_id );

		// Verify the announce WAS handled.
		$this->assertEquals( 1, $handled_action->get_call_count() );

		// Verify comment was created.
		$args = array(
			'type'    => 'repost',
			'post_id' => $this->post_id,
		);

		$query  = new \WP_Comment_Query( $args );
		$result = $query->comments;

		$this->assertNotEmpty( $result );
		$this->assertInstanceOf( 'WP_Comment', $result[0] );

		\remove_action( 'activitypub_handled_announce', array( $handled_action, 'action' ) );
	}
}
