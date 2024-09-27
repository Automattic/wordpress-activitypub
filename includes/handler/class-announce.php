<?php
namespace Activitypub\Handler;

use Activitypub\Http;
use Activitypub\Comment;
use Activitypub\Collection\Interactions;

use function Activitypub\object_to_uri;
use function Activitypub\is_activity_public;

/**
 * Handle Create requests
 */
class Announce {
	/**
	 * Initialize the class, registering WordPress hooks
	 */
	public static function init() {
		\add_action(
			'activitypub_inbox_announce',
			array( self::class, 'handle_announce' ),
			10,
			3
		);
	}

	/**
	 * Handles "Announce" requests
	 *
	 * @param array                $array    The activity-object
	 * @param int                  $user_id  The id of the local blog-user
	 * @param Activitypub\Activity $activity The activity object
	 *
	 * @return void
	 */
	public static function handle_announce( $array, $user_id, $activity = null ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.arrayFound
		// check if Activity is public or not
		if ( ! is_activity_public( $array ) ) {
			// @todo maybe send email
			return;
		}

		if ( ! ACTIVITYPUB_DISABLE_REACTIONS ) {
			self::maybe_save_announce( $array, $user_id, $activity );
		}

		if ( is_string( $array['object'] ) ) {
			$object = Http::get_remote_object( $array['object'] );
		} else {
			$object = $array['object'];
		}

		if ( ! $object || is_wp_error( $object ) ) {
			return;
		}

		if ( ! isset( $object['type'] ) ) {
			return;
		}

		$type = \strtolower( $object['type'] );

		\do_action( 'activitypub_inbox', $object, $user_id, $type, $activity );
		\do_action( "activitypub_inbox_{$type}", $object, $user_id, $activity );
	}

	/**
	 * Try to save the Announce
	 *
	 * @param array                $array    The activity-object
	 * @param int                  $user_id  The id of the local blog-user
	 * @param Activitypub\Activity $activity The activity object
	 *
	 * @return void
	 */
	public static function maybe_save_announce( $array, $user_id, $activity ) { // phpcs:ignore
		$url = object_to_uri( $array['object'] );

		if ( empty( $url ) ) {
			return;
		}

		$exists = Comment::object_id_to_comment( esc_url_raw( $url ) );
		if ( $exists ) {
			return;
		}

		$state    = Interactions::add_reaction( $array );
		$reaction = null;

		if ( $state && ! is_wp_error( $state ) ) {
			$reaction = get_comment( $state );
		}

		do_action( 'activitypub_handled_announce', $array, $user_id, $state, $reaction );
	}
}
