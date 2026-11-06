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
	 * Every outbox signal is a new value, so each open stream sees the change.
	 *
	 * @covers ::signal_outbox
	 */
	public function test_signal_outbox_sets_a_new_value_each_time() {
		Event_Stream::signal_outbox( 41, null, 3 );
		$first = \get_transient( 'activitypub_sse_signal_3_outbox' );

		Event_Stream::signal_outbox( 41, null, 3 );
		$second = \get_transient( 'activitypub_sse_signal_3_outbox' );

		$this->assertNotFalse( $first );
		$this->assertNotSame( $first, $second, 'The same item signalled again still changes the signal.' );
	}

	/**
	 * A handled inbox activity sets a new signal for every recipient.
	 *
	 * Goes through the action, as a redelivery of a stored activity does.
	 *
	 * @covers ::init
	 * @covers ::signal_inbox
	 */
	public function test_handled_inbox_sets_a_new_signal_for_every_recipient() {
		\update_option( 'activitypub_api', true );
		Event_Stream::init();

		\do_action( 'activitypub_handled_inbox', array(), array( 2, 3 ), 'create', null, 77, 'shared_inbox' );
		$first = \get_transient( 'activitypub_sse_signal_2_inbox' );

		\do_action( 'activitypub_handled_inbox', array(), array( 2, 3 ), 'create', null, 77, 'shared_inbox' );

		$this->assertNotFalse( $first );
		$this->assertNotFalse( \get_transient( 'activitypub_sse_signal_3_inbox' ) );
		$this->assertNotSame( $first, \get_transient( 'activitypub_sse_signal_2_inbox' ), 'A redelivery still changes the signal.' );
	}
}
