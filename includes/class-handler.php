<?php
namespace Activitypub;

use Activitypub\Handler\Announce;
use Activitypub\Handler\Create;
use Activitypub\Handler\Delete;
use Activitypub\Handler\Follow;
use Activitypub\Handler\Like;
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

		\add_action( 'transition_post_status', array( self::class, 'schedule_post_activity' ), 33, 3 );
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

		if ( ! ACTIVITYPUB_DISABLE_REACTIONS ) {
			Like::init();
		}

		do_action( 'activitypub_register_handlers' );
	}

	/**
	 * Schedule Activities.
	 *
	 * @param string  $new_status New post status.
	 * @param string  $old_status Old post status.
	 * @param WP_Post $post       Post object.
	 */
	public static function schedule_post_activity( $new_status, $old_status, $post ) {
		// Do not send activities if post is password protected.
		if ( \post_password_required( $post ) ) {
			return;
		}

		// Check if post-type supports ActivityPub.
		$post_types = \get_post_types_by_support( 'activitypub' );
		if ( ! \in_array( $post->post_type, $post_types, true ) ) {
			return;
		}

		$type = false;

		if ( 'publish' === $new_status && 'publish' !== $old_status ) {
			$type = 'Create';
		} elseif ( 'publish' === $new_status ) {
			$type = 'Update';
		} elseif ( 'trash' === $new_status ) {
			$type = 'Delete';
		}

		if ( ! $type ) {
			return;
		}

		\wp_schedule_single_event(
			\time(),
			'activitypub_send_activity',
			array( $post, $type )
		);

		\wp_schedule_single_event(
			\time(),
			sprintf(
				'activitypub_send_%s_activity',
				\strtolower( $type )
			),
			array( $post )
		);
	}
}
