<?php
/**
 * FASP Settings file.
 *
 * @package Activitypub
 */

namespace Activitypub\WP_Admin;

use Activitypub\Fasp\Client;
use Activitypub\Fasp\Registrations;

/**
 * FASP Settings class.
 *
 * Handles the admin actions for the "Auxiliary Services" settings tab:
 * approving, rejecting and disconnecting provider registrations, and
 * enabling or disabling provider capabilities.
 *
 * @since unreleased
 */
class Fasp_Settings {

	/**
	 * The URL of the FASP registrations settings tab.
	 *
	 * @var string
	 */
	const SETTINGS_URL = 'options-general.php?page=activitypub&tab=fasp-registrations';

	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		\add_action( 'admin_post_approve_fasp_registration', array( self::class, 'approve_registration' ) );
		\add_action( 'admin_post_reject_fasp_registration', array( self::class, 'reject_registration' ) );
		\add_action( 'admin_post_delete_fasp_registration', array( self::class, 'delete_registration' ) );
		\add_action( 'admin_post_toggle_fasp_capability', array( self::class, 'toggle_capability' ) );
		\add_action( 'admin_post_refresh_fasp_provider_info', array( self::class, 'refresh_provider_info' ) );

		\add_action( 'admin_notices', array( self::class, 'process_admin_notices' ) );
	}

	/**
	 * Handle approve FASP registration action.
	 */
	public static function approve_registration() {
		$fasp_id = self::verify_action_request( 'fasp_registration_' );

		$result = Registrations::approve( $fasp_id, \get_current_user_id() );

		self::redirect( $result ? array( 'approved' => '1' ) : array( 'error' => '1' ) );
	}

	/**
	 * Handle reject FASP registration action.
	 */
	public static function reject_registration() {
		$fasp_id = self::verify_action_request( 'fasp_registration_' );

		$result = Registrations::reject( $fasp_id, \get_current_user_id() );

		self::redirect( $result ? array( 'rejected' => '1' ) : array( 'error' => '1' ) );
	}

	/**
	 * Handle delete FASP registration action.
	 */
	public static function delete_registration() {
		$fasp_id = self::verify_action_request( 'fasp_registration_' );

		$result = Registrations::delete( $fasp_id );

		self::redirect( $result ? array( 'deleted' => '1' ) : array( 'error' => '1' ) );
	}

	/**
	 * Handle enabling or disabling a provider capability.
	 *
	 * Notifies the provider first, per the FASP spec, and only records the
	 * local state change when the provider acknowledged the call.
	 */
	public static function toggle_capability() {
		$fasp_id = self::verify_action_request( 'fasp_capability_' );

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Verified in verify_action_request().
		$identifier = isset( $_POST['identifier'] ) ? \sanitize_text_field( \wp_unslash( $_POST['identifier'] ) ) : '';
		$version    = isset( $_POST['version'] ) ? \sanitize_text_field( \wp_unslash( $_POST['version'] ) ) : '';
		$enable     = isset( $_POST['enable'] ) && '1' === $_POST['enable'];
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$registration = Registrations::get( $fasp_id );

		if ( ! $registration || 'approved' !== $registration['status'] || ! $identifier || ! $version ) {
			self::redirect( array( 'error' => '1' ) );
		}

		if ( $enable ) {
			$result = Client::activate_capability( $registration, $identifier, $version );
		} else {
			$result = Client::deactivate_capability( $registration, $identifier, $version );
		}

		if ( \is_wp_error( $result ) ) {
			self::redirect( array( 'error' => '1' ) );
		}

		if ( $enable ) {
			Registrations::enable_capability( $fasp_id, $identifier, $version );
		} else {
			Registrations::disable_capability( $fasp_id, $identifier, $version );
		}

		self::redirect( array( 'capability_updated' => '1' ) );
	}

	/**
	 * Handle refreshing the cached provider info of a FASP.
	 */
	public static function refresh_provider_info() {
		$fasp_id = self::verify_action_request( 'fasp_registration_' );

		\delete_transient( Client::PROVIDER_INFO_TRANSIENT . $fasp_id );

		self::redirect( array( 'highlight' => $fasp_id ) );
	}

	/**
	 * Register settings errors based on query parameters from FASP admin actions.
	 */
	public static function process_admin_notices() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? \sanitize_text_field( \wp_unslash( $_GET['page'] ) ) : '';
		$tab  = isset( $_GET['tab'] ) ? \sanitize_text_field( \wp_unslash( $_GET['tab'] ) ) : '';

		if ( 'activitypub' !== $page || 'fasp-registrations' !== $tab ) {
			return;
		}

		if ( isset( $_GET['approved'] ) && '1' === $_GET['approved'] ) {
			\add_settings_error(
				'activitypub_fasp',
				'fasp_approved',
				\__( 'Service approved successfully. You can now choose which features it may use.', 'activitypub' ),
				'success'
			);
		}

		if ( isset( $_GET['rejected'] ) && '1' === $_GET['rejected'] ) {
			\add_settings_error(
				'activitypub_fasp',
				'fasp_rejected',
				\__( 'Service request rejected.', 'activitypub' ),
				'success'
			);
		}

		if ( isset( $_GET['deleted'] ) && '1' === $_GET['deleted'] ) {
			\add_settings_error(
				'activitypub_fasp',
				'fasp_deleted',
				\__( 'Service disconnected successfully.', 'activitypub' ),
				'success'
			);
		}

		if ( isset( $_GET['capability_updated'] ) && '1' === $_GET['capability_updated'] ) {
			\add_settings_error(
				'activitypub_fasp',
				'fasp_capability_updated',
				\__( 'Service capabilities updated.', 'activitypub' ),
				'success'
			);
		}

		if ( isset( $_GET['error'] ) && '1' === $_GET['error'] ) {
			\add_settings_error(
				'activitypub_fasp',
				'fasp_error',
				\__( 'An error occurred while processing your request. Please try again.', 'activitypub' ),
				'error'
			);
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Verify capability and nonce of an admin-post action request.
	 *
	 * Dies when the current user may not manage options or the nonce does
	 * not match; returns the targeted FASP ID otherwise.
	 *
	 * @param string $nonce_prefix The nonce action prefix, followed by the FASP ID.
	 * @return string The FASP ID.
	 */
	private static function verify_action_request( $nonce_prefix ) {
		if ( ! \current_user_can( 'manage_options' ) ) {
			\wp_die( \esc_html__( 'You do not have permission to perform this action.', 'activitypub' ) );
		}

		$fasp_id = isset( $_POST['fasp_id'] ) ? \sanitize_text_field( \wp_unslash( $_POST['fasp_id'] ) ) : '';
		$nonce   = isset( $_POST['_wpnonce'] ) ? \sanitize_text_field( \wp_unslash( $_POST['_wpnonce'] ) ) : '';

		if ( ! \wp_verify_nonce( $nonce, $nonce_prefix . $fasp_id ) ) {
			\wp_die( \esc_html__( 'Invalid nonce.', 'activitypub' ) );
		}

		return $fasp_id;
	}

	/**
	 * Redirect back to the settings tab with the given query arguments.
	 *
	 * @param array $args Query arguments to add.
	 */
	private static function redirect( $args ) {
		\wp_safe_redirect( \add_query_arg( $args, \admin_url( self::SETTINGS_URL ) ) );
		exit;
	}
}
