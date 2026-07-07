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

		if ( $result ) {
			/*
			 * Fetch and persist the provider info while we are already in a POST
			 * handler, so the settings page itself never has to make a blocking
			 * outbound request. A failure here is fine: the page offers a load
			 * button, and approval still succeeds.
			 */
			$provider_info = Client::fetch_provider_info( Registrations::get( $fasp_id ) );
			if ( \is_array( $provider_info ) ) {
				Registrations::set_provider_info( $fasp_id, $provider_info );
			}
		}

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

		// Notify the provider first; only record local state once it acknowledges.
		$result = $enable
			? Client::activate_capability( $registration, $identifier, $version )
			: Client::deactivate_capability( $registration, $identifier, $version );

		if ( \is_wp_error( $result ) ) {
			self::redirect( array( 'error' => '1' ) );
		}

		Registrations::set_capability_enabled( $fasp_id, $identifier, $version, $enable );

		self::redirect( array( 'capability_updated' => '1' ) );
	}

	/**
	 * Handle refreshing the cached provider info of a FASP.
	 *
	 * Fetches a fresh copy into the cache here in the POST handler, so the
	 * settings page render path never makes an outbound request itself.
	 */
	public static function refresh_provider_info() {
		$fasp_id = self::verify_action_request( 'fasp_registration_' );

		$registration  = Registrations::get( $fasp_id );
		$provider_info = $registration ? Client::fetch_provider_info( $registration ) : null;

		if ( ! \is_array( $provider_info ) ) {
			// Keep the last-known-good copy, but surface the failure and keep the card in focus.
			self::redirect(
				array(
					'highlight' => $fasp_id,
					'error'     => '1',
				)
			);
		}

		Registrations::set_provider_info( $fasp_id, $provider_info );

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

		// Map each result query flag to the notice it shows.
		$notices = array(
			'approved'           => array( 'fasp_approved', \__( 'Service approved successfully. You can now choose which features it may use.', 'activitypub' ), 'success' ),
			'rejected'           => array( 'fasp_rejected', \__( 'Service request rejected.', 'activitypub' ), 'success' ),
			'deleted'            => array( 'fasp_deleted', \__( 'Service disconnected successfully.', 'activitypub' ), 'success' ),
			'capability_updated' => array( 'fasp_capability_updated', \__( 'Service capabilities updated.', 'activitypub' ), 'success' ),
			'error'              => array( 'fasp_error', \__( 'An error occurred while processing your request. Please try again.', 'activitypub' ), 'error' ),
		);

		foreach ( $notices as $flag => $notice ) {
			if ( isset( $_GET[ $flag ] ) && '1' === $_GET[ $flag ] ) {
				\add_settings_error( 'activitypub_fasp', $notice[0], $notice[1], $notice[2] );
			}
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
