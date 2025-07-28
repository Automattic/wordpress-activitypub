<?php
/**
 * Moderation Admin Class.
 *
 * @package Activitypub
 */

namespace Activitypub\WP_Admin;

use Activitypub\Moderation as Moderation_API;

/**
 * ActivityPub Moderation Admin Class.
 *
 * Handles admin-specific moderation functionality including script enqueuing and AJAX handlers.
 */
class Moderation {

	/**
	 * Initialize the moderation admin functionality.
	 */
	public static function init() {
		\add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_scripts' ) );
		\add_action( 'wp_ajax_activitypub_add_user_block', array( self::class, 'ajax_add_user_block' ) );
		\add_action( 'wp_ajax_activitypub_remove_user_block', array( self::class, 'ajax_remove_user_block' ) );
		\add_action( 'wp_ajax_activitypub_add_site_block', array( self::class, 'ajax_add_site_block' ) );
		\add_action( 'wp_ajax_activitypub_remove_site_block', array( self::class, 'ajax_remove_site_block' ) );
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @param string $hook_suffix The current admin page.
	 */
	public static function enqueue_scripts( $hook_suffix ) {
		// Only load on relevant admin pages.
		if ( ! in_array( $hook_suffix, array( 'profile.php', 'user-edit.php', 'settings_page_activitypub' ), true ) ) {
			return;
		}

		\wp_enqueue_script(
			'activitypub-moderation-admin',
			ACTIVITYPUB_PLUGIN_URL . 'assets/js/activitypub-moderation-admin.js',
			array( 'jquery', 'wp-util', 'wp-a11y' ),
			ACTIVITYPUB_PLUGIN_VERSION,
			true
		);

		// Localize script with translations and nonces.
		\wp_localize_script(
			'activitypub-moderation-admin',
			'activitypubModerationL10n',
			array(
				'enterValue'        => \__( 'Please enter a value to block.', 'activitypub' ),
				'addBlockFailed'    => \__( 'Failed to add block.', 'activitypub' ),
				'removeBlockFailed' => \__( 'Failed to remove block.', 'activitypub' ),
				'userNonce'         => \wp_create_nonce( 'activitypub_user_moderation' ),
				'siteNonce'         => \wp_create_nonce( 'activitypub_site_moderation' ),
			)
		);
	}

	/**
	 * AJAX handler to add user block.
	 */
	public static function ajax_add_user_block() {
		$user_id         = (int) ( $_POST['user_id'] ?? 0 );
		$current_user_id = \get_current_user_id();

		// Check permissions.
		if ( $current_user_id !== $user_id && ! \current_user_can( 'manage_options' ) ) {
			\wp_send_json_error( array( 'message' => \__( 'You do not have permission to perform this action.', 'activitypub' ) ) );
		}

		if ( ! \wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'activitypub_user_moderation' ) ) {
			\wp_send_json_error( array( 'message' => \__( 'Invalid nonce.', 'activitypub' ) ) );
		}

		$type  = \sanitize_text_field( $_POST['type'] ?? '' );
		$value = \sanitize_text_field( $_POST['value'] ?? '' );

		if ( empty( $type ) || empty( $value ) || ! $user_id ) {
			\wp_send_json_error( array( 'message' => \__( 'Invalid parameters.', 'activitypub' ) ) );
		}

		$success = Moderation_API::add_user_block( $user_id, $type, $value );

		if ( $success ) {
			\wp_send_json_success();
		} else {
			\wp_send_json_error( array( 'message' => \__( 'Failed to add block.', 'activitypub' ) ) );
		}
	}

	/**
	 * AJAX handler to remove user block.
	 */
	public static function ajax_remove_user_block() {
		$user_id         = (int) ( $_POST['user_id'] ?? 0 );
		$current_user_id = \get_current_user_id();

		// Check permissions.
		if ( $current_user_id !== $user_id && ! \current_user_can( 'manage_options' ) ) {
			\wp_send_json_error( array( 'message' => \__( 'You do not have permission to perform this action.', 'activitypub' ) ) );
		}

		if ( ! \wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'activitypub_user_moderation' ) ) {
			\wp_send_json_error( array( 'message' => \__( 'Invalid nonce.', 'activitypub' ) ) );
		}

		$type  = \sanitize_text_field( $_POST['type'] ?? '' );
		$value = \sanitize_text_field( $_POST['value'] ?? '' );

		if ( empty( $type ) || empty( $value ) || ! $user_id ) {
			\wp_send_json_error( array( 'message' => \__( 'Invalid parameters.', 'activitypub' ) ) );
		}

		$success = Moderation_API::remove_user_block( $user_id, $type, $value );

		if ( $success ) {
			\wp_send_json_success();
		} else {
			\wp_send_json_error( array( 'message' => \__( 'Failed to remove block.', 'activitypub' ) ) );
		}
	}

	/**
	 * AJAX handler to add site block.
	 */
	public static function ajax_add_site_block() {
		if ( ! \current_user_can( 'manage_options' ) ) {
			\wp_send_json_error( array( 'message' => \__( 'You do not have permission to perform this action.', 'activitypub' ) ) );
		}

		if ( ! \wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'activitypub_site_moderation' ) ) {
			\wp_send_json_error( array( 'message' => \__( 'Invalid nonce.', 'activitypub' ) ) );
		}

		$type  = \sanitize_text_field( $_POST['type'] ?? '' );
		$value = \sanitize_text_field( $_POST['value'] ?? '' );

		if ( empty( $type ) || empty( $value ) ) {
			\wp_send_json_error( array( 'message' => \__( 'Invalid parameters.', 'activitypub' ) ) );
		}

		$success = Moderation_API::add_site_block( $type, $value );

		if ( $success ) {
			\wp_send_json_success();
		} else {
			\wp_send_json_error( array( 'message' => \__( 'Failed to add block.', 'activitypub' ) ) );
		}
	}

	/**
	 * AJAX handler to remove site block.
	 */
	public static function ajax_remove_site_block() {
		if ( ! \current_user_can( 'manage_options' ) ) {
			\wp_send_json_error( array( 'message' => \__( 'You do not have permission to perform this action.', 'activitypub' ) ) );
		}

		if ( ! \wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'activitypub_site_moderation' ) ) {
			\wp_send_json_error( array( 'message' => \__( 'Invalid nonce.', 'activitypub' ) ) );
		}

		$type  = \sanitize_text_field( $_POST['type'] ?? '' );
		$value = \sanitize_text_field( $_POST['value'] ?? '' );

		if ( empty( $type ) || empty( $value ) ) {
			\wp_send_json_error( array( 'message' => \__( 'Invalid parameters.', 'activitypub' ) ) );
		}

		$success = Moderation_API::remove_site_block( $type, $value );

		if ( $success ) {
			\wp_send_json_success();
		} else {
			\wp_send_json_error( array( 'message' => \__( 'Failed to remove block.', 'activitypub' ) ) );
		}
	}
}
