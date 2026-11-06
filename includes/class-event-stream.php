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
	 *
	 * Registered on `init`, so the setting is read once the site context is settled.
	 *
	 * @return void
	 */
	public static function init() {
		if ( ! \get_option( 'activitypub_api', false ) ) {
			return;
		}

		\add_action( 'post_activitypub_add_to_outbox', array( self::class, 'signal_outbox' ), 10, 3 );
		\add_action( 'activitypub_handled_inbox', array( self::class, 'signal_inbox' ), 10, 5 );
	}

	/**
	 * Set a transient signal when a new item is added to the outbox.
	 *
	 * The signal holds the ID of the new item, so every new item changes it. Each open stream
	 * compares it with the value it saw last, so none of them clears it and all of them see it.
	 *
	 * @param int                            $outbox_activity_id The outbox post ID.
	 * @param \Activitypub\Activity\Activity $activity           The activity object.
	 * @param int                            $user_id            The user ID.
	 *
	 * @return void
	 */
	public static function signal_outbox( $outbox_activity_id, $activity, $user_id ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		$signal_key = \sprintf( 'activitypub_sse_signal_%s_outbox', $user_id );
		\set_transient( $signal_key, (int) $outbox_activity_id, HOUR_IN_SECONDS );
	}

	/**
	 * Set transient signals when a new item is added to the inbox.
	 *
	 * The signal holds the ID of the inbox item, see signal_outbox().
	 *
	 * @param array              $data     The activity data array.
	 * @param array              $user_ids The user IDs that received the activity.
	 * @param string|null        $type     Optional. The activity type.
	 * @param mixed              $activity Optional. The activity.
	 * @param int|\WP_Error|null $item_id  Optional. The ID of the inbox item, or a WP_Error if it was not stored.
	 *
	 * @return void
	 */
	public static function signal_inbox( $data, $user_ids, $type = null, $activity = null, $item_id = null ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		// Nothing was stored, so there is nothing to stream.
		if ( ! \is_numeric( $item_id ) || (int) $item_id <= 0 ) {
			return;
		}

		$item_id = (int) $item_id;

		if ( ! \is_array( $user_ids ) ) {
			$user_ids = array( $user_ids );
		}

		foreach ( $user_ids as $user_id ) {
			$signal_key = \sprintf( 'activitypub_sse_signal_%s_inbox', $user_id );
			\set_transient( $signal_key, $item_id, HOUR_IN_SECONDS );
		}
	}
}
