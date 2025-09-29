<?php
/**
 * Handler class.
 *
 * @package Activitypub
 */

namespace Activitypub;

use Activitypub\Handler\Accept;
use Activitypub\Handler\Announce;
use Activitypub\Handler\Create;
use Activitypub\Handler\Delete;
use Activitypub\Handler\Follow;
use Activitypub\Handler\Inbox;
use Activitypub\Handler\Like;
use Activitypub\Handler\Move;
use Activitypub\Handler\Quote_Request;
use Activitypub\Handler\Reject;
use Activitypub\Handler\Undo;
use Activitypub\Handler\Update;

/**
 * Handler class.
 */
class Handler {
	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		self::register_handlers();
	}

	/**
	 * Register handlers.
	 */
	public static function register_handlers() {
		Accept::init();
		Announce::init();
		Create::init();
		Delete::init();
		Follow::init();
		Inbox::init();
		Like::init();
		Move::init();
		Quote_Request::init();
		Reject::init();
		Undo::init();
		Update::init();

		/**
		 * Register additional handlers.
		 *
		 * @since 1.3.0
		 */
		do_action( 'activitypub_register_handlers' );
	}
}
