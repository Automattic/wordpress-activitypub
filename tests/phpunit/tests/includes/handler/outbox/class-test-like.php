<?php
/**
 * Test file for Outbox Like Handler.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Handler\Outbox;

use Activitypub\Handler\Outbox\Like;

/**
 * Test class for Outbox Like Handler.
 *
 * @coversDefaultClass \Activitypub\Handler\Outbox\Like
 */
class Test_Like extends \WP_UnitTestCase {

	/**
	 * Test that handle_like fires the action hook.
	 *
	 * @covers ::handle_like
	 */
	public function test_handle_like_fires_action() {
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
		\add_action( 'activitypub_outbox_like_sent', $callback, 10, 3 );

		$data = array(
			'type'   => 'Like',
			'object' => 'https://example.com/note/123',
		);

		Like::handle_like( $data, 1 );

		$this->assertTrue( $fired, 'activitypub_outbox_like_sent action should fire.' );
		$this->assertEquals( 'https://example.com/note/123', $hook_url );
		$this->assertEquals( $data, $hook_data );
		$this->assertEquals( 1, $hook_user );

		\remove_action( 'activitypub_outbox_like_sent', $callback );
	}

	/**
	 * Test that handle_like extracts ID from object array.
	 *
	 * @covers ::handle_like
	 */
	public function test_handle_like_with_object_array() {
		$hook_url = null;

		$callback = function ( $object_url ) use ( &$hook_url ) {
			$hook_url = $object_url;
		};
		\add_action( 'activitypub_outbox_like_sent', $callback, 10, 1 );

		$data = array(
			'type'   => 'Like',
			'object' => array(
				'type' => 'Note',
				'id'   => 'https://example.com/note/456',
			),
		);

		Like::handle_like( $data, 1 );

		$this->assertEquals( 'https://example.com/note/456', $hook_url );

		\remove_action( 'activitypub_outbox_like_sent', $callback );
	}

	/**
	 * Test that handle_like returns early for empty object.
	 *
	 * @covers ::handle_like
	 */
	public function test_handle_like_empty_object() {
		$fired = false;

		$callback = function () use ( &$fired ) {
			$fired = true;
		};
		\add_action( 'activitypub_outbox_like_sent', $callback );

		Like::handle_like( array( 'type' => 'Like' ), 1 );

		$this->assertFalse( $fired, 'Action should not fire for missing object.' );

		\remove_action( 'activitypub_outbox_like_sent', $callback );
	}

	/**
	 * Test that handle_like returns early for empty string object.
	 *
	 * @covers ::handle_like
	 */
	public function test_handle_like_empty_string_object() {
		$fired = false;

		$callback = function () use ( &$fired ) {
			$fired = true;
		};
		\add_action( 'activitypub_outbox_like_sent', $callback );

		Like::handle_like(
			array(
				'type'   => 'Like',
				'object' => '',
			),
			1
		);

		$this->assertFalse( $fired, 'Action should not fire for empty string object.' );

		\remove_action( 'activitypub_outbox_like_sent', $callback );
	}

	/**
	 * Test that init registers the filter.
	 *
	 * @covers ::init
	 */
	public function test_init_registers_filter() {
		Like::init();

		$this->assertNotFalse(
			\has_filter( 'activitypub_outbox_like', array( Like::class, 'handle_like' ) ),
			'Filter should be registered.'
		);
	}
}
