<?php
/**
 * FASP Registrations class file.
 *
 * @package Activitypub
 */

namespace Activitypub\Fasp;

/**
 * FASP Registrations class.
 *
 * Stores and manages registrations of Fediverse Auxiliary Service Providers
 * (FASP) with this site, including the per-registration Ed25519 keypair the
 * site uses to authenticate against the provider, and the local record of
 * which provider capabilities the administrator has enabled.
 *
 * @see https://github.com/mastodon/fediverse_auxiliary_service_provider_specifications/blob/main/general/v0.1/registration.md
 *
 * @since unreleased
 */
class Registrations {

	/**
	 * Option name for the registrations store.
	 *
	 * @var string
	 */
	const OPTION_REGISTRATIONS = 'activitypub_fasp_registrations';

	/**
	 * Option name for the capabilities store.
	 *
	 * @var string
	 */
	const OPTION_CAPABILITIES = 'activitypub_fasp_capabilities';

	/**
	 * Create a new (pending) registration.
	 *
	 * Generates the unique FASP ID and the Ed25519 keypair this site uses to
	 * authenticate against this provider, as required by the FASP spec.
	 *
	 * @param array $data {
	 *     Registration request data.
	 *
	 *     @type string $name            The name of the FASP.
	 *     @type string $base_url        The base URL of the FASP.
	 *     @type string $server_id       The identifier the FASP generated for this server.
	 *     @type string $fasp_public_key The FASP public key, base64 encoded.
	 * }
	 * @return array|false The stored registration record, or false on failure.
	 */
	public static function create( $data ) {
		$keypair     = \sodium_crypto_sign_keypair();
		$public_key  = \sodium_crypto_sign_publickey( $keypair );
		$private_key = \sodium_crypto_sign_secretkey( $keypair );

		$registration = array(
			'fasp_id'                     => \wp_generate_uuid4(),
			'name'                        => $data['name'],
			'base_url'                    => \untrailingslashit( $data['base_url'] ),
			'server_id'                   => $data['server_id'],
			'fasp_public_key'             => $data['fasp_public_key'],
			'fasp_public_key_fingerprint' => self::get_public_key_fingerprint( $data['fasp_public_key'] ),
			'server_public_key'           => \base64_encode( $public_key ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			'server_private_key'          => \base64_encode( $private_key ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			'status'                      => 'pending',
			'requested_at'                => \current_time( 'mysql', true ),
		);

		$registrations = self::get_registrations_store();

		$registrations[ $registration['fasp_id'] ] = $registration;

		if ( ! \update_option( self::OPTION_REGISTRATIONS, $registrations, false ) ) {
			return false;
		}

		return $registration;
	}

	/**
	 * Get registration by FASP ID.
	 *
	 * @param string $fasp_id FASP ID.
	 * @return array|null Registration data or null if not found.
	 */
	public static function get( $fasp_id ) {
		$registrations = self::get_registrations_store();

		return isset( $registrations[ $fasp_id ] ) ? $registrations[ $fasp_id ] : null;
	}

	/**
	 * Get registration by server ID.
	 *
	 * @param string $server_id The server ID the FASP generated for this site.
	 * @return array|null Registration data or null if not found.
	 */
	public static function get_by_server_id( $server_id ) {
		foreach ( self::get_registrations_store() as $registration ) {
			if ( isset( $registration['server_id'] ) && $registration['server_id'] === $server_id ) {
				return $registration;
			}
		}

		return null;
	}

	/**
	 * Get registrations filtered by status.
	 *
	 * @param string $status The status to filter by ('pending', 'approved', 'rejected').
	 * @return array Array of matching registrations, sorted newest first.
	 */
	public static function get_by_status( $status ) {
		$filtered = array();

		foreach ( self::get_registrations_store() as $registration ) {
			if ( $status === $registration['status'] ) {
				$filtered[] = $registration;
			}
		}

		\usort(
			$filtered,
			function ( $a, $b ) use ( $status ) {
				$key = 'approved' === $status ? 'approved_at' : 'requested_at';
				return ( $b[ $key ] ?? '' ) <=> ( $a[ $key ] ?? '' );
			}
		);

		return $filtered;
	}

	/**
	 * Approve a registration request.
	 *
	 * @param string $fasp_id FASP ID.
	 * @param int    $user_id User ID who approved.
	 * @return bool True on success, false on failure.
	 */
	public static function approve( $fasp_id, $user_id ) {
		$registrations = self::get_registrations_store();

		if ( ! isset( $registrations[ $fasp_id ] ) ) {
			return false;
		}

		$registrations[ $fasp_id ]['status']      = 'approved';
		$registrations[ $fasp_id ]['approved_at'] = \current_time( 'mysql', true );
		$registrations[ $fasp_id ]['approved_by'] = $user_id;

		return \update_option( self::OPTION_REGISTRATIONS, $registrations, false );
	}

	/**
	 * Reject a registration request.
	 *
	 * @param string $fasp_id FASP ID.
	 * @param int    $user_id User ID who rejected.
	 * @return bool True on success, false on failure.
	 */
	public static function reject( $fasp_id, $user_id ) {
		$registrations = self::get_registrations_store();

		if ( ! isset( $registrations[ $fasp_id ] ) ) {
			return false;
		}

		$registrations[ $fasp_id ]['status']      = 'rejected';
		$registrations[ $fasp_id ]['rejected_at'] = \current_time( 'mysql', true );
		$registrations[ $fasp_id ]['rejected_by'] = $user_id;

		return \update_option( self::OPTION_REGISTRATIONS, $registrations, false );
	}

	/**
	 * Delete a registration and its capability state.
	 *
	 * @param string $fasp_id FASP ID.
	 * @return bool True on success, false on failure.
	 */
	public static function delete( $fasp_id ) {
		$registrations = self::get_registrations_store();

		if ( ! isset( $registrations[ $fasp_id ] ) ) {
			return false;
		}

		unset( $registrations[ $fasp_id ] );

		$capabilities = self::get_capabilities_store();
		foreach ( \array_keys( $capabilities ) as $key ) {
			if ( ( $capabilities[ $key ]['fasp_id'] ?? null ) === $fasp_id ) {
				unset( $capabilities[ $key ] );
			}
		}
		\update_option( self::OPTION_CAPABILITIES, $capabilities, false );

		\delete_transient( 'activitypub_fasp_provider_info_' . $fasp_id );

		return \update_option( self::OPTION_REGISTRATIONS, $registrations, false );
	}

	/**
	 * Generate public key fingerprint.
	 *
	 * The fingerprint is the base64 encoded SHA-256 hash of the (raw) public
	 * key, as defined by the FASP registration spec.
	 *
	 * @param string $public_key Base64 encoded public key.
	 * @return string SHA-256 fingerprint, base64 encoded.
	 */
	public static function get_public_key_fingerprint( $public_key ) {
		$decoded_key = \base64_decode( $public_key ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		$hash        = \hash( 'sha256', $decoded_key, true );

		return \base64_encode( $hash ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Get enabled capabilities for a FASP.
	 *
	 * @param string $fasp_id FASP ID.
	 * @return array Array of enabled capabilities.
	 */
	public static function get_enabled_capabilities( $fasp_id ) {
		$enabled = array();

		foreach ( self::get_capabilities_store() as $capability ) {
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
	 * Mark a capability as enabled for a FASP.
	 *
	 * This only records local state. Callers are responsible for notifying
	 * the FASP first, see {@see Client::activate_capability()}.
	 *
	 * @param string $fasp_id    FASP ID.
	 * @param string $identifier Capability identifier.
	 * @param string $version    Capability version.
	 * @return bool True on success, false on failure.
	 */
	public static function enable_capability( $fasp_id, $identifier, $version ) {
		$capabilities   = self::get_capabilities_store();
		$capability_key = $fasp_id . '_' . $identifier . '_v' . $version;

		$capabilities[ $capability_key ] = array(
			'fasp_id'    => $fasp_id,
			'identifier' => $identifier,
			'version'    => $version,
			'enabled'    => true,
			'updated_at' => \current_time( 'mysql', true ),
		);

		return \update_option( self::OPTION_CAPABILITIES, $capabilities, false );
	}

	/**
	 * Mark a capability as disabled for a FASP.
	 *
	 * This only records local state. Callers are responsible for notifying
	 * the FASP first, see {@see Client::deactivate_capability()}.
	 *
	 * @param string $fasp_id    FASP ID.
	 * @param string $identifier Capability identifier.
	 * @param string $version    Capability version.
	 * @return bool True on success, false on failure.
	 */
	public static function disable_capability( $fasp_id, $identifier, $version ) {
		$capabilities   = self::get_capabilities_store();
		$capability_key = $fasp_id . '_' . $identifier . '_v' . $version;

		if ( isset( $capabilities[ $capability_key ] ) ) {
			$capabilities[ $capability_key ]['enabled']    = false;
			$capabilities[ $capability_key ]['updated_at'] = \current_time( 'mysql', true );
		}

		return \update_option( self::OPTION_CAPABILITIES, $capabilities, false );
	}

	/**
	 * Retrieve registrations, ensuring the option exists and is non-autoloaded.
	 *
	 * @return array
	 */
	private static function get_registrations_store() {
		$registrations = \get_option( self::OPTION_REGISTRATIONS, null );

		if ( null === $registrations ) {
			\add_option( self::OPTION_REGISTRATIONS, array(), '', false );
			return array();
		}

		return \is_array( $registrations ) ? $registrations : array();
	}

	/**
	 * Retrieve capabilities store ensuring the option exists and is non-autoloaded.
	 *
	 * @return array
	 */
	private static function get_capabilities_store() {
		$capabilities = \get_option( self::OPTION_CAPABILITIES, null );

		if ( null === $capabilities ) {
			\add_option( self::OPTION_CAPABILITIES, array(), '', false );
			return array();
		}

		return \is_array( $capabilities ) ? $capabilities : array();
	}
}
