<?php
/**
 * Handler class.
 *
 * @package Activitypub
 */

namespace Activitypub;

/**
 * Handler class.
 */
class Handler {
	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		self::register_handlers();
		self::register_outbox_handlers();
	}

	/**
	 * Register handlers.
	 */
	public static function register_handlers() {
		Handler\Accept::init();
		Handler\Announce::init();
		Handler\Collection_Sync::init();
		Handler\Create::init();
		Handler\Delete::init();
		Handler\Follow::init();
		Handler\Like::init();
		Handler\Move::init();
		Handler\Quote_Request::init();
		Handler\Reject::init();
		Handler\Undo::init();
		Handler\Update::init();

		/**
		 * Register additional handlers.
		 *
		 * @since 1.3.0
		 */
		do_action( 'activitypub_register_handlers' );
	}

	/**
	 * Register outbox handlers.
	 */
	public static function register_outbox_handlers() {
		Handler\Outbox\Add::init();
		Handler\Outbox\Announce::init();
		Handler\Outbox\Arrive::init();
		Handler\Outbox\Block::init();
		Handler\Outbox\Create::init();
		Handler\Outbox\Delete::init();
		Handler\Outbox\Follow::init();
		Handler\Outbox\Like::init();
		Handler\Outbox\Remove::init();
		Handler\Outbox\Undo::init();
		Handler\Outbox\Update::init();

		/**
		 * Register additional outbox handlers.
		 *
		 * @since 8.1.0
		 */
		do_action( 'activitypub_register_outbox_handlers' );
	}
}
