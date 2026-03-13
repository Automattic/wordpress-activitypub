<?php
/**
 * Test file for Outbox Announce Handler.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Handler\Outbox;

use Activitypub\Handler\Outbox\Announce;

/**
 * Test class for Outbox Announce Handler.
 *
 * @coversDefaultClass \Activitypub\Handler\Outbox\Announce
 */
class Test_Announce extends \WP_UnitTestCase {

	/**
	 * Test that handle_announce fires the action hook.
	 *
	 * @covers ::handle_announce
	 */
	public function test_handle_announce_fires_action() {
		$fired     = false;
		$hook_url  = null;
		$hook_data = null;
		$hook_user = null;

		$callback = function ( $object_url, $data, $user_id ) use ( &$fired, &$hook_url, &$hook_data, &$hook_user ) {
			$fired     = true;
			$hook_url  = $object_url;
			$hook_data = $data;
			$hook_user = $user_id;
		};
		\add_action( 'activitypub_outbox_announce_sent', $callback, 10, 3 );

		$data = array(
			'type'   => 'Announce',
			'object' => 'https://example.com/note/123',
		);

		Announce::handle_announce( $data, 1 );

		$this->assertTrue( $fired, 'activitypub_outbox_announce_sent action should fire.' );
		$this->assertEquals( 'https://example.com/note/123', $hook_url );
		$this->assertEquals( $data, $hook_data );
		$this->assertEquals( 1, $hook_user );

		\remove_action( 'activitypub_outbox_announce_sent', $callback );
	}

	/**
	 * Test that handle_announce extracts ID from object array.
	 *
	 * @covers ::handle_announce
	 */
	public function test_handle_announce_with_object_array() {
		$hook_url = null;

		$callback = function ( $object_url ) use ( &$hook_url ) {
			$hook_url = $object_url;
		};
		\add_action( 'activitypub_outbox_announce_sent', $callback, 10, 1 );

		$data = array(
			'type'   => 'Announce',
			'object' => array(
				'type' => 'Note',
				'id'   => 'https://example.com/note/456',
			),
		);

		Announce::handle_announce( $data, 1 );

		$this->assertEquals( 'https://example.com/note/456', $hook_url );

		\remove_action( 'activitypub_outbox_announce_sent', $callback );
	}

	/**
	 * Test that handle_announce returns early for empty object.
	 *
	 * @covers ::handle_announce
	 */
	public function test_handle_announce_empty_object() {
		$fired = false;

		$callback = function () use ( &$fired ) {
			$fired = true;
		};
		\add_action( 'activitypub_outbox_announce_sent', $callback );

		Announce::handle_announce( array( 'type' => 'Announce' ), 1 );

		$this->assertFalse( $fired, 'Action should not fire for empty object.' );

		\remove_action( 'activitypub_outbox_announce_sent', $callback );
	}

	/**
	 * Test that handle_announce returns early for missing object.
	 *
	 * @covers ::handle_announce
	 */
	public function test_handle_announce_missing_object() {
		$fired = false;

		$callback = function () use ( &$fired ) {
			$fired = true;
		};
		\add_action( 'activitypub_outbox_announce_sent', $callback );

		Announce::handle_announce(
			array(
				'type'   => 'Announce',
				'object' => '',
			),
			1
		);

		$this->assertFalse( $fired, 'Action should not fire for empty string object.' );

		\remove_action( 'activitypub_outbox_announce_sent', $callback );
	}

	/**
	 * Test that init registers the filter.
	 *
	 * @covers ::init
	 */
	public function test_init_registers_filter() {
		Announce::init();

		$this->assertNotFalse(
			\has_filter( 'activitypub_outbox_announce', array( Announce::class, 'handle_announce' ) ),
			'Filter should be registered.'
		);
	}
}
