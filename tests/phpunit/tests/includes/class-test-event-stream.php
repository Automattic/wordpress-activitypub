<?php
/**
 * Test file for Event_Stream.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

use Activitypub\Event_Stream;

/**
 * Test class for Event_Stream.
 *
 * @coversDefaultClass \Activitypub\Event_Stream
 */
class Test_Event_Stream extends \WP_UnitTestCase {
	/**
	 * Tear down.
	 */
	public function tear_down() {
		\delete_option( 'activitypub_api' );
		\remove_action( 'post_activitypub_add_to_outbox', array( Event_Stream::class, 'signal_outbox' ) );
		\remove_action( 'activitypub_handled_inbox', array( Event_Stream::class, 'signal_inbox' ) );

		parent::tear_down();
	}

	/**
	 * The signals are hooked while the API is enabled.
	 *
	 * @covers ::init
	 */
	public function test_init_hooks_signals_when_api_is_enabled() {
		\update_option( 'activitypub_api', true );

		Event_Stream::init();

		$this->assertNotFalse( \has_action( 'post_activitypub_add_to_outbox', array( Event_Stream::class, 'signal_outbox' ) ) );
		$this->assertNotFalse( \has_action( 'activitypub_handled_inbox', array( Event_Stream::class, 'signal_inbox' ) ) );
	}

	/**
	 * Nothing is hooked while the API is disabled.
	 *
	 * The `init` action is registered unconditionally at `plugins_loaded`; the setting is
	 * read here, once the site context is settled.
	 *
	 * @covers ::init
	 */
	public function test_init_hooks_nothing_when_api_is_disabled() {
		Event_Stream::init();

		$this->assertFalse( \has_action( 'post_activitypub_add_to_outbox', array( Event_Stream::class, 'signal_outbox' ) ) );
		$this->assertFalse( \has_action( 'activitypub_handled_inbox', array( Event_Stream::class, 'signal_inbox' ) ) );
	}

	/**
	 * The outbox signal holds the ID of the new item, so each new item changes it.
	 *
	 * @covers ::signal_outbox
	 */
	public function test_signal_outbox_holds_item_id() {
		Event_Stream::signal_outbox( 41, null, 3 );
		$this->assertSame( 41, (int) \get_transient( 'activitypub_sse_signal_3_outbox' ) );

		Event_Stream::signal_outbox( 42, null, 3 );
		$this->assertSame( 42, (int) \get_transient( 'activitypub_sse_signal_3_outbox' ) );
	}

	/**
	 * A handled inbox activity signals the ID of its inbox item to every recipient.
	 *
	 * Goes through the action, so the item ID has to reach the callback.
	 *
	 * @covers ::init
	 * @covers ::signal_inbox
	 */
	public function test_handled_inbox_signals_item_id() {
		\update_option( 'activitypub_api', true );
		Event_Stream::init();

		\do_action( 'activitypub_handled_inbox', array(), array( 2, 3 ), 'create', null, 77, 'shared_inbox' );

		$this->assertSame( 77, (int) \get_transient( 'activitypub_sse_signal_2_inbox' ) );
		$this->assertSame( 77, (int) \get_transient( 'activitypub_sse_signal_3_inbox' ) );
	}

	/**
	 * An activity that was not stored leaves the signal alone.
	 *
	 * @covers ::signal_inbox
	 */
	public function test_signal_inbox_skips_unstored_item() {
		Event_Stream::signal_inbox( array(), array( 4 ), 'create', null, new \WP_Error( 'failed' ) );

		$this->assertFalse( \get_transient( 'activitypub_sse_signal_4_inbox' ) );
	}
}
