<?php
/**
 * Handler class.
 *
 * @package Activitypub
 */

namespace Activitypub;

use Activitypub\Handler\Announce;
use Activitypub\Handler\Create;
use Activitypub\Handler\Delete;
use Activitypub\Handler\Follow;
use Activitypub\Handler\Like;
use Activitypub\Handler\Move;
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

		add_filter( 'activitypub_get_outbox_activity', array( self::class, 'outbox_activity' ), 99 );
	}

	/**
	 * Register handlers.
	 */
	public static function register_handlers() {
		Announce::init();
		Create::init();
		Delete::init();
		Follow::init();
		Undo::init();
		Update::init();
		Like::init();
		Move::init();

		/**
		 * Register additional handlers.
		 *
		 * @since 1.3.0
		 */
		do_action( 'activitypub_register_handlers' );
	}

	/**
	 * Filter the outbox activity.
	 *
	 * @param Activity $activity The activity.
	 * @return Activity The activity.
	 */
	public static function outbox_activity( $activity ) {
		var_dump( $activity->get_type() );
		if ( 'Inherit' === $activity->get_type() ) {
			$inherit_activity = $activity->get_object();
			$inherit_activity->set_id( $activity->get_id() );
			$inherit_activity->set_cc( $activity->get_cc() );
			$inherit_activity->set_to( $activity->get_to() );

			return $inherit_activity;
		}

		return $activity;
	}
}
