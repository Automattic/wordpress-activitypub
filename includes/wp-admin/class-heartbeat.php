<?php
/**
 * ActivityPub Heartbeat API Integration.
 *
 * @package Activitypub
 */

namespace Activitypub\WP_Admin;

use Activitypub\Collection\Actors;
use Activitypub\Collection\Following;

/**
 * Heartbeat API integration for ActivityPub.
 */
class Heartbeat {

	/**
	 * Initialize the Heartbeat API integration.
	 */
	public static function init() {
		\add_action( 'admin_print_scripts-settings_page_activitypub', array( self::class, 'enqueue_scripts' ) );
		\add_action( 'admin_print_scripts-users_page_activitypub-following-list', array( self::class, 'enqueue_scripts' ) );

		\add_filter( 'heartbeat_received', array( self::class, 'heartbeat_received' ), 10, 2 );
	}

	/**
	 * Enqueue scripts and localize data for the Following list table.
	 */
	public static function enqueue_scripts() {
		$tab = \sanitize_text_field( \wp_unslash( $_GET['tab'] ?? 'welcome' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( \get_current_screen()->id === 'settings_page_activitypub' && 'following' !== $tab ) {
			return;
		}

		// Get the current user ID.
		$user_id = \get_current_screen()->id === 'settings_page_activitypub'
			? Actors::BLOG_USER_ID
			: \get_current_user_id();

		// Bail if there are no pending follows.
		if ( 0 === Following::count_pending( $user_id ) ) {
			return;
		}

		// Enqueue the script.
		\wp_enqueue_script(
			'activitypub-following',
			\plugins_url( 'assets/js/activitypub-following.js', ACTIVITYPUB_PLUGIN_FILE ),
			array( 'jquery', 'heartbeat' ),
			ACTIVITYPUB_PLUGIN_VERSION,
			true
		);

		// Localize the script with necessary data.
		\wp_localize_script(
			'activitypub-following',
			'ActivityPubFollowingSettings',
			array( 'user_id' => $user_id )
		);
	}

	/**
	 * Handle the Heartbeat API received event.
	 *
	 * @param array $response The Heartbeat response.
	 * @param array $data     The Heartbeat data.
	 * @return array The filtered Heartbeat response.
	 */
	public static function heartbeat_received( $response, $data ) {
		// Check if this is our data.
		if ( empty( $data['activitypub_following_check'] ) ) {
			return $response;
		}

		$user_id     = \absint( $data['activitypub_following_check']['user_id'] ?? 0 );
		$pending_ids = \array_map( 'absint', $data['activitypub_following_check']['pending_ids'] ?? array() );

		// Verify user can view this data.
		if ( ! \current_user_can( 'edit_user', $user_id ) ) {
			return $response;
		}

		// Initialize the response.
		$response['activitypub_following'] = array(
			'counts'        => Following::count( $user_id ),
			'message'       => __( 'Follow requests updated.', 'activitypub' ),
			'no_items'      => __( 'No profiles found.', 'activitypub' ),
			'updated_items' => array(),
		);

		// Check for status changes in the pending follow requests.
		foreach ( $pending_ids as $post_id ) {
			$status = Following::check_status( $user_id, $post_id );

			// If the status has changed from pending to accepted.
			if ( Following::ACCEPTED === $status ) {
				$response['activitypub_following']['updated_items'][] = array(
					'id'     => $post_id,
					'status' => $status,
				);
			}
		}

		return $response;
	}
}
