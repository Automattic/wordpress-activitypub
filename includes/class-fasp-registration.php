<?php
/**
 * FASP registration management using WordPress options.
 *
 * @package Activitypub
 */

namespace Activitypub;

/**
 * FASP Registration Management class.
 *
 * Handles FASP registration functionality using WordPress options instead of custom tables.
 */
class Fasp_Registration {

	/**
	 * Initialize FASP registration management.
	 */
	public static function init() {
		// Nothing needed for initialization since we're using WordPress options.
	}

	/**
	 * Get all pending registration requests.
	 *
	 * @return array Array of registration requests.
	 */
	public static function get_pending_registrations() {
		$registrations = get_option( 'activitypub_fasp_registrations', array() );
		$pending       = array();

		foreach ( $registrations as $registration ) {
			if ( 'pending' === $registration['status'] ) {
				$pending[] = $registration;
			}
		}

		// Sort by requested_at DESC.
		usort(
			$pending,
			function ( $a, $b ) {
				return strcmp( $b['requested_at'], $a['requested_at'] );
			}
		);

		return $pending;
	}

	/**
	 * Get all approved registrations.
	 *
	 * @return array Array of approved registrations.
	 */
	public static function get_approved_registrations() {
		$registrations = get_option( 'activitypub_fasp_registrations', array() );
		$approved      = array();

		foreach ( $registrations as $registration ) {
			if ( 'approved' === $registration['status'] ) {
				$approved[] = $registration;
			}
		}

		// Sort by approved_at DESC.
		usort(
			$approved,
			function ( $a, $b ) {
				$approved_at_a = isset( $a['approved_at'] ) ? $a['approved_at'] : '';
				$approved_at_b = isset( $b['approved_at'] ) ? $b['approved_at'] : '';
				return strcmp( $approved_at_b, $approved_at_a );
			}
		);

		return $approved;
	}

	/**
	 * Approve a registration request.
	 *
	 * @param string $fasp_id FASP ID.
	 * @param int    $user_id User ID who approved.
	 * @return bool True on success, false on failure.
	 */
	public static function approve_registration( $fasp_id, $user_id ) {
		$registrations = get_option( 'activitypub_fasp_registrations', array() );

		if ( ! isset( $registrations[ $fasp_id ] ) ) {
			return false;
		}

		$registrations[ $fasp_id ]['status']      = 'approved';
		$registrations[ $fasp_id ]['approved_at'] = current_time( 'mysql', true );
		$registrations[ $fasp_id ]['approved_by'] = $user_id;

		return update_option( 'activitypub_fasp_registrations', $registrations );
	}

	/**
	 * Reject a registration request.
	 *
	 * @param string $fasp_id FASP ID.
	 * @param int    $user_id User ID who rejected.
	 * @return bool True on success, false on failure.
	 */
	public static function reject_registration( $fasp_id, $user_id ) {
		$registrations = get_option( 'activitypub_fasp_registrations', array() );

		if ( ! isset( $registrations[ $fasp_id ] ) ) {
			return false;
		}

		$registrations[ $fasp_id ]['status']      = 'rejected';
		$registrations[ $fasp_id ]['approved_at'] = current_time( 'mysql', true );
		$registrations[ $fasp_id ]['approved_by'] = $user_id;

		return update_option( 'activitypub_fasp_registrations', $registrations );
	}

	/**
	 * Get registration by FASP ID.
	 *
	 * @param string $fasp_id FASP ID.
	 * @return array|null Registration data or null if not found.
	 */
	public static function get_registration( $fasp_id ) {
		$registrations = get_option( 'activitypub_fasp_registrations', array() );

		return isset( $registrations[ $fasp_id ] ) ? $registrations[ $fasp_id ] : null;
	}

	/**
	 * Delete a registration request.
	 *
	 * @param string $fasp_id FASP ID.
	 * @return bool True on success, false on failure.
	 */
	public static function delete_registration( $fasp_id ) {
		$registrations = get_option( 'activitypub_fasp_registrations', array() );

		if ( ! isset( $registrations[ $fasp_id ] ) ) {
			return false;
		}

		unset( $registrations[ $fasp_id ] );

		return update_option( 'activitypub_fasp_registrations', $registrations );
	}

	/**
	 * Generate public key fingerprint.
	 *
	 * @param string $public_key Base64 encoded public key.
	 * @return string SHA-256 fingerprint.
	 */
	public static function get_public_key_fingerprint( $public_key ) {
		$decoded_key = base64_decode( $public_key ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		$hash        = hash( 'sha256', $decoded_key, true );
		return base64_encode( $hash ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Get enabled capabilities for a FASP.
	 *
	 * @param string $fasp_id FASP ID.
	 * @return array Array of enabled capabilities.
	 */
	public static function get_enabled_capabilities( $fasp_id ) {
		$capabilities = get_option( 'activitypub_fasp_capabilities', array() );
		$enabled      = array();

		foreach ( $capabilities as $capability ) {
			if ( $capability['fasp_id'] === $fasp_id && $capability['enabled'] ) {
				$enabled[] = $capability;
			}
		}

		return $enabled;
	}

	/**
	 * Check if a FASP has a specific capability enabled.
	 *
	 * @param string $fasp_id    FASP ID.
	 * @param string $identifier Capability identifier.
	 * @param int    $version    Capability version.
	 * @return bool True if capability is enabled, false otherwise.
	 */
	public static function is_capability_enabled( $fasp_id, $identifier, $version ) {
		$capabilities   = get_option( 'activitypub_fasp_capabilities', array() );
		$capability_key = $fasp_id . '_' . $identifier . '_v' . $version;

		return isset( $capabilities[ $capability_key ] ) && $capabilities[ $capability_key ]['enabled'];
	}
}
