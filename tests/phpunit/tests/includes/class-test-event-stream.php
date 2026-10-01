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
}
