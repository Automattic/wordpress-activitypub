<?php
namespace Activitypub;

use Activitypub\Handler\Create;
use Activitypub\Handler\Delete;
use Activitypub\Handler\Follow;
use Activitypub\Handler\Undo;
use Activitypub\Handler\Update;

/**
 * Handler class.
 */
class Handler {
	/**
	 * Initialize the class, registering WordPress hooks
	 */
	public static function init() {
		self::register_handlers();
	}

	/**
	 * Register handlers.
	 */
	public static function register_handlers() {
		Create::init();
		Delete::init();
		Follow::init();
		Undo::init();
		Update::init();

		do_action( 'activitypub_register_handlers' );
	}
}
