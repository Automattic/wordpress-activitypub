<?php
/**
 * Event Stream signal writer.
 *
 * Sets lightweight transient signals when new items arrive in
 * outbox or inbox collections, so the SSE polling loop can avoid
 * unnecessary database queries.
 *
 * @package Activitypub
 * @see https://swicg.github.io/activitypub-api/sse
 * @since 8.1.0
 */

namespace Activitypub;

/**
 * Event_Stream class.
 *
 * Hooks into outbox and inbox actions to set transient signals
 * that the SSE controller checks during its polling loop.
 *
 * @since 8.1.0
 */
class Event_Stream {
	/**
	 * Initialize the event stream signals.
	 */
	public static function init() {
		\add_action( 'post_activitypub_add_to_outbox', array( self::class, 'signal_outbox' ), 10, 3 );
		\add_action( 'activitypub_handled_inbox', array( self::class, 'signal_inbox' ), 10, 2 );
	}

	/**
	 * Set a transient signal when a new item is added to the outbox.
	 *
	 * @param int                            $outbox_activity_id The outbox post ID.
	 * @param \Activitypub\Activity\Activity $activity           The activity object.
	 * @param int                            $user_id            The user ID.
	 */
	public static function signal_outbox( $outbox_activity_id, $activity, $user_id ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		$signal_key = sprintf( 'activitypub_sse_signal_%s_outbox', $user_id );
		\set_transient( $signal_key, time(), HOUR_IN_SECONDS );
	}

	/**
	 * Set transient signals when a new item is added to the inbox.
	 *
	 * @param array $data     The activity data array.
	 * @param array $user_ids The user IDs that received the activity.
	 */
	public static function signal_inbox( $data, $user_ids ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		if ( ! \is_array( $user_ids ) ) {
			$user_ids = array( $user_ids );
		}

		foreach ( $user_ids as $user_id ) {
			$signal_key = sprintf( 'activitypub_sse_signal_%s_inbox', $user_id );
			\set_transient( $signal_key, time(), HOUR_IN_SECONDS );
		}
	}
}
