<?php
/**
 * ActivityPub Relay Class
 *
 * Handles forwarding of activities when relay mode is enabled.
 *
 * @package Activitypub
 */

namespace Activitypub;

use Activitypub\Activity\Activity;
use Activitypub\Collection\Actors;
use Activitypub\Collection\Outbox;

/**
 * ActivityPub Relay Class
 *
 * Provides relay functionality to forward public activities to all followers.
 */
class Relay {
	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		\add_action( 'activitypub_handled_create', array( self::class, 'handle_activity' ), 10, 3 );
		\add_action( 'activitypub_handled_update', array( self::class, 'handle_activity' ), 10, 3 );
		\add_action( 'activitypub_handled_delete', array( self::class, 'handle_activity' ), 10, 3 );
		\add_action( 'activitypub_handled_announce', array( self::class, 'handle_activity' ), 10, 3 );
		\add_action( 'load-settings_page_activitypub', array( self::class, 'unhook_settings_fields' ), 11 );
	}

	/**
	 * Handle incoming activity and relay if needed.
	 *
	 * @param array $activity The activity data.
	 * @param array $user_ids The user IDs that are recipients.
	 * @param bool  $success  Whether the activity was handled successfully.
	 */
	public static function handle_activity( $activity, $user_ids, $success ) {
		// Only relay if: successfully handled, Blog actor is recipient, activity is public, and in single-user mode.
		if (
			! $success ||
			! in_array( Actors::BLOG_USER_ID, (array) $user_ids, true ) ||
			! is_activity_public( $activity ) ||
			! is_single_user()
		) {
			return;
		}

		// Create Announce wrapper.
		$announce = new Activity();
		$announce->set_type( 'Announce' );
		$announce->set_actor( Actors::BLOG_USER_ID );
		$announce->set_object( $activity );
		$announce->set_published( gmdate( ACTIVITYPUB_DATE_TIME_RFC3339 ) );

		// Add to outbox for distribution. The outbox will generate the ID.
		Outbox::add( $announce, Actors::BLOG_USER_ID );
	}

	/**
	 * Unhook settings fields when relay mode is enabled.
	 *
	 * Removes all settings sections except moderation when relay mode is active.
	 */
	public static function unhook_settings_fields() {
		global $wp_settings_sections;

		if ( ! isset( $wp_settings_sections['activitypub_settings'] ) ) {
			return;
		}

		// Keep only the moderation section.
		foreach ( $wp_settings_sections['activitypub_settings'] as $section_id => $section ) {
			if ( 'activitypub_moderation' !== $section_id ) {
				unset( $wp_settings_sections['activitypub_settings'][ $section_id ] );
			}
		}
	}
}
