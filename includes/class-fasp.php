<?php
/**
 * FASP integration class file.
 *
 * @package Activitypub
 */

namespace Activitypub;

/**
 * FASP integration class.
 *
 * Handles FASP-related integrations that aren't part of the REST API,
 * and provides registration management functionality.
 */
class Fasp {

	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		\add_filter( 'activitypub_pre_get_public_key', array( __CLASS__, 'get_public_key_for_server_id' ), 10, 2 );
	}

	/**
	 * Provide public key for FASP serverId lookups.
	 *
	 * This filter integrates FASP signature verification with the existing
	 * ActivityPub signature system. When a signature's keyId matches a
	 * registered FASP's serverId, we return the stored public key.
	 *
	 * FASP uses Ed25519 keys, so we return an array with type information
	 * that the signature verification system can use.
	 *
	 * @param resource|string|array|\WP_Error|null $public_key The current public key (null to continue lookup).
	 * @param string                               $key_id     The key ID from the signature.
	 * @return resource|string|array|\WP_Error|null The public key or null to continue default lookup.
	 */
	public static function get_public_key_for_server_id( $public_key, $key_id ) {
		// If another filter already provided a key, don't override.
		if ( null !== $public_key ) {
			return $public_key;
		}

		// Try to find a FASP registration matching this serverId.
		$registration = self::get_registration_by_server_id( $key_id );

		if ( ! $registration ) {
			return null; // Not a FASP serverId, continue with default lookup.
		}

		// Check if FASP is approved.
		if ( 'approved' !== $registration['status'] ) {
			return new \WP_Error(
				'fasp_not_approved',
				'FASP registration is not approved',
				array( 'status' => 403 )
			);
		}

		// Return the stored public key.
		if ( empty( $registration['fasp_public_key'] ) ) {
			return new \WP_Error(
				'fasp_no_public_key',
				'FASP registration does not have a public key',
				array( 'status' => 401 )
			);
		}

		/*
		 * FASP uses Ed25519 keys stored as base64.
		 * Decode and return as Ed25519 key array for signature verification.
		 */
		$raw_key = base64_decode( $registration['fasp_public_key'] ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

		if ( false === $raw_key ) {
			return new \WP_Error(
				'fasp_invalid_key',
				'FASP public key is not valid base64',
				array( 'status' => 401 )
			);
		}

		return array(
			'type' => 'ed25519',
			'key'  => $raw_key,
		);
	}

	/**
	 * Get registration by server ID.
	 *
	 * @param string $server_id The server ID from the FASP.
	 * @return array|null Registration data or null if not found.
	 */
	public static function get_registration_by_server_id( $server_id ) {
		$registrations = self::get_registrations_store();

		foreach ( $registrations as $registration ) {
			if ( isset( $registration['server_id'] ) && $registration['server_id'] === $server_id ) {
				return $registration;
			}
		}

		return null;
	}


	/**
	 * Get all pending registration requests.
	 *
	 * @return array Array of registration requests.
	 */
	public static function get_pending_registrations() {
		$registrations = self::get_registrations_store();
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
		$registrations = self::get_registrations_store();
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
				return ( $b['approved_at'] ?? '' ) <=> ( $a['approved_at'] ?? '' );
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
		$registrations = self::get_registrations_store();

		if ( ! isset( $registrations[ $fasp_id ] ) ) {
			return false;
		}

		$registrations[ $fasp_id ]['status']      = 'approved';
		$registrations[ $fasp_id ]['approved_at'] = current_time( 'mysql', true );
		$registrations[ $fasp_id ]['approved_by'] = $user_id;

		return update_option( 'activitypub_fasp_registrations', $registrations, false );
	}

	/**
	 * Reject a registration request.
	 *
	 * @param string $fasp_id FASP ID.
	 * @param int    $user_id User ID who rejected.
	 * @return bool True on success, false on failure.
	 */
	public static function reject_registration( $fasp_id, $user_id ) {
		$registrations = self::get_registrations_store();

		if ( ! isset( $registrations[ $fasp_id ] ) ) {
			return false;
		}

		$registrations[ $fasp_id ]['status']      = 'rejected';
		$registrations[ $fasp_id ]['rejected_at'] = current_time( 'mysql', true );
		$registrations[ $fasp_id ]['rejected_by'] = $user_id;

		return update_option( 'activitypub_fasp_registrations', $registrations, false );
	}

	/**
	 * Get registration by FASP ID.
	 *
	 * @param string $fasp_id FASP ID.
	 * @return array|null Registration data or null if not found.
	 */
	public static function get_registration( $fasp_id ) {
		$registrations = self::get_registrations_store();

		return isset( $registrations[ $fasp_id ] ) ? $registrations[ $fasp_id ] : null;
	}

	/**
	 * Delete a registration request.
	 *
	 * @param string $fasp_id FASP ID.
	 * @return bool True on success, false on failure.
	 */
	public static function delete_registration( $fasp_id ) {
		$registrations = self::get_registrations_store();

		if ( ! isset( $registrations[ $fasp_id ] ) ) {
			return false;
		}

		unset( $registrations[ $fasp_id ] );

		return update_option( 'activitypub_fasp_registrations', $registrations, false );
	}

	/**
	 * Store a new registration request.
	 *
	 * @param array $data Registration data including fasp_id.
	 * @return bool True on success, false on failure.
	 */
	public static function store_registration( $data ) {
		$registrations = self::get_registrations_store();

		// Add new registration.
		$registrations[ $data['fasp_id'] ] = $data;

		// Store updated registrations without autoloading.
		return update_option( 'activitypub_fasp_registrations', $registrations, false );
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
		$capabilities = self::get_capabilities_store();
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
	 * @param string $version    Capability version.
	 * @return bool True if capability is enabled, false otherwise.
	 */
	public static function is_capability_enabled( $fasp_id, $identifier, $version ) {
		$capabilities   = self::get_capabilities_store();
		$capability_key = $fasp_id . '_' . $identifier . '_v' . $version;

		return isset( $capabilities[ $capability_key ] ) && $capabilities[ $capability_key ]['enabled'];
	}

	/**
	 * Retrieve registrations, ensuring the option exists, is non-autoloaded, and sanitized.
	 *
	 * @return array
	 */
	private static function get_registrations_store() {
		$registrations = get_option( 'activitypub_fasp_registrations', null );

		if ( null === $registrations ) {
			add_option( 'activitypub_fasp_registrations', array(), '', 'no' );
			return array();
		}

		if ( ! is_array( $registrations ) ) {
			$registrations = array();
		}

		return self::sanitize_registration_records( $registrations );
	}

	/**
	 * Remove sensitive data from stored registrations.
	 *
	 * @param array $registrations Registration data.
	 * @return array Sanitized registrations.
	 */
	private static function sanitize_registration_records( array $registrations ) {
		$modified = false;

		foreach ( $registrations as $fasp_id => $registration ) {
			if ( isset( $registration['server_private_key'] ) ) {
				unset( $registration['server_private_key'] );
				$registrations[ $fasp_id ] = $registration;
				$modified                  = true;
			}

			if ( isset( $registration['fasp_public_key'] ) && empty( $registration['fasp_public_key_fingerprint'] ) ) {
				$registration['fasp_public_key_fingerprint'] = self::get_public_key_fingerprint( $registration['fasp_public_key'] );
				$registrations[ $fasp_id ]                   = $registration;
				$modified                                    = true;
			}
		}

		if ( $modified ) {
			update_option( 'activitypub_fasp_registrations', $registrations, false );
		}

		return $registrations;
	}

	/**
	 * Retrieve capabilities store ensuring the option exists and is non-autoloaded.
	 *
	 * @return array
	 */
	private static function get_capabilities_store() {
		$capabilities = get_option( 'activitypub_fasp_capabilities', null );

		if ( null === $capabilities ) {
			add_option( 'activitypub_fasp_capabilities', array(), '', 'no' );
			return array();
		}

		if ( ! is_array( $capabilities ) ) {
			return array();
		}

		return $capabilities;
	}
}
