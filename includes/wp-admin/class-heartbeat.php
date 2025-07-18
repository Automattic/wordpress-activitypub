<?php
/**
 * ActivityPub Heartbeat API Integration
 *
 * @package Activitypub
 */

namespace Activitypub\WP_Admin;

use Activitypub\Collection\Following;
use Activitypub\Collection\Actors;

/**
 * Heartbeat API integration for ActivityPub.
 */
class Heartbeat {

	/**
	 * Initialize the Heartbeat API integration.
	 */
	public static function init() {
		\add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_scripts' ) );
		\add_filter( 'heartbeat_received', array( self::class, 'heartbeat_received' ), 10, 2 );
		\add_filter( 'heartbeat_settings', array( self::class, 'heartbeat_settings' ) );
	}

	/**
	 * Enqueue scripts and localize data for the Following list table.
	 *
	 * @param string $hook The current admin page.
	 */
	public static function enqueue_scripts( $hook ) {
		$following_pages = array(
			'settings_page_activitypub',
			'users_page_activitypub-following-list',
		);

		// Only enqueue on the following list pages.
		if ( ! \in_array( $hook, $following_pages, true ) ) {
			return;
		}

		// Get the current user ID.
		$user_id = \get_current_screen()->id === 'settings_page_activitypub'
			? Actors::BLOG_USER_ID
			: \get_current_user_id();

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
			array(
				'screen_id' => \get_current_screen()->id,
				'user_id'   => $user_id,
			)
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

		$following_data = $data['activitypub_following_check'];
		$user_id        = \absint( $following_data['user_id'] ?? 0 );
		$pending_ids    = \array_map( 'absint', $following_data['pending_ids'] ?? array() );

		// Verify user can view this data.
		if ( ! \current_user_can( 'edit_user', $user_id ) ) {
			return $response;
		}

		// Initialize the response.
		$following_response = array(
			'updated_items' => array(),
			'message'       => __( 'Follow requests updated.', 'activitypub' ),
		);

		// Check for status changes in the pending follow requests.
		foreach ( $pending_ids as $post_id ) {
			$status = Following::check_status( $user_id, $post_id );

			// If the status has changed from pending to accepted.
			if ( Following::ACCEPTED === $status ) {
				$following_response['updated_items'][] = array(
					'id'     => $post_id,
					'status' => $status,
				);
			}
		}

		// If we have updates, include the updated counts.
		if ( ! empty( $following_response['updated_items'] ) ) {
			$following_response['counts'] = Following::count( $user_id );
		}

		// Add our data to the response.
		$response['activitypub_following_response'] = $following_response;

		return $response;
	}

	/**
	 * Adjust Heartbeat API settings for ActivityPub screens.
	 *
	 * @param array $settings Heartbeat settings.
	 * @return array Modified heartbeat settings.
	 */
	public static function heartbeat_settings( $settings ) {
		global $pagenow;

		// Check if we're on a Following list page.
		$is_following_page = false;

		// phpcs:ignore WordPress.Security.NonceVerification
		if ( 'users.php' === $pagenow && isset( $_GET['page'] ) && 'activitypub-following-list' === $_GET['page'] ) {
			$is_following_page = true;
		}

		// phpcs:ignore WordPress.Security.NonceVerification
		if ( 'options-general.php' === $pagenow && isset( $_GET['page'], $_GET['tab'] ) && 'activitypub' === $_GET['page'] && 'following' === $_GET['tab'] ) {
			$is_following_page = true;
		}

		// Increase heartbeat frequency on Following list pages.
		if ( $is_following_page ) {
			$settings['interval'] = 10;
		}

		return $settings;
	}
}
