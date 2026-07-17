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
	 * Maximum number of pending registrations kept at a time.
	 *
	 * The registration endpoint is unauthenticated by design, so the pending
	 * queue must be bounded. When it is full, the oldest pending entry is
	 * evicted to make room rather than rejecting new requests, so junk can
	 * never lock a legitimate provider out.
	 *
	 * @var int
	 */
	const MAX_PENDING = 25;

	/**
	 * How long an unactioned pending or rejected registration is kept, in seconds.
	 *
	 * @var int
	 */
	const PENDING_TTL = MONTH_IN_SECONDS;

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
		return self::set_status( $fasp_id, 'approved', $user_id );
	}

	/**
	 * Reject a registration request.
	 *
	 * @param string $fasp_id FASP ID.
	 * @param int    $user_id User ID who rejected.
	 * @return bool True on success, false on failure.
	 */
	public static function reject( $fasp_id, $user_id ) {
		return self::set_status( $fasp_id, 'rejected', $user_id );
	}

	/**
	 * Transition a registration to a new status, recording who did it and when.
	 *
	 * @param string $fasp_id FASP ID.
	 * @param string $status  The new status ('approved' or 'rejected').
	 * @param int    $user_id User ID who performed the transition.
	 * @return bool True on success, false on failure.
	 */
	private static function set_status( $fasp_id, $status, $user_id ) {
		$registrations = self::get_registrations_store();

		if ( ! isset( $registrations[ $fasp_id ] ) ) {
			return false;
		}

		$registrations[ $fasp_id ]['status']          = $status;
		$registrations[ $fasp_id ][ $status . '_at' ] = \current_time( 'mysql', true );
		$registrations[ $fasp_id ][ $status . '_by' ] = $user_id;

		return \update_option( self::OPTION_REGISTRATIONS, $registrations, false );
	}

	/**
	 * Delete a registration and everything it owns.
	 *
	 * Capability state lives inside the registration record, so removing the
	 * record removes its capabilities too.
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

		return \update_option( self::OPTION_REGISTRATIONS, $registrations, false );
	}

	/**
	 * Store the provider info of a FASP in its registration record.
	 *
	 * Render paths read this persisted copy instead of fetching, so a slow or
	 * unreachable provider never blocks a page load and the capability list
	 * never disappears from the settings tab.
	 *
	 * @param string $fasp_id       FASP ID.
	 * @param array  $provider_info The decoded provider info.
	 * @return bool True on success, false on failure.
	 */
	public static function set_provider_info( $fasp_id, $provider_info ) {
		$registrations = self::get_registrations_store();

		if ( ! isset( $registrations[ $fasp_id ] ) ) {
			return false;
		}

		$registrations[ $fasp_id ]['provider_info'] = $provider_info;

		return \update_option( self::OPTION_REGISTRATIONS, $registrations, false );
	}

	/**
	 * Prune stale pending and rejected registrations older than the TTL.
	 *
	 * Approved registrations are never pruned. A pending or rejected record
	 * whose timestamp is missing or unparseable is also pruned, so a corrupt
	 * entry cannot occupy a queue slot indefinitely.
	 *
	 * @return int The number of pruned registrations.
	 */
	public static function prune_stale() {
		$registrations = self::get_registrations_store();
		$cutoff        = \time() - self::PENDING_TTL;
		$pruned        = 0;

		foreach ( $registrations as $fasp_id => $registration ) {
			$status = $registration['status'] ?? '';
			if ( 'pending' !== $status && 'rejected' !== $status ) {
				continue;
			}

			$requested_at = $registration['requested_at'] ?? '';
			// `requested_at` is stored as GMT, so pin the parse to UTC.
			$timestamp = '' === $requested_at ? false : \strtotime( $requested_at . ' +0000' );

			if ( false === $timestamp || $timestamp < $cutoff ) {
				unset( $registrations[ $fasp_id ] );
				++$pruned;
			}
		}

		if ( $pruned ) {
			\update_option( self::OPTION_REGISTRATIONS, $registrations, false );
		}

		return $pruned;
	}

	/**
	 * Evict the oldest pending registrations until the queue is below the cap.
	 *
	 * Keeps the unauthenticated queue bounded without ever rejecting a new
	 * request: at worst an attacker's junk evicts other junk. Approved and
	 * rejected records are never evicted.
	 *
	 * @param int $limit The maximum number of pending records to keep.
	 * @return int The number of evicted registrations.
	 */
	public static function evict_oldest_pending( $limit ) {
		// get_by_status() sorts newest first, so the oldest are at the end.
		$pending = self::get_by_status( 'pending' );
		$evicted = 0;
		$count   = \count( $pending );

		while ( $count >= $limit ) {
			$oldest = \array_pop( $pending );
			self::delete( $oldest['fasp_id'] );
			++$evicted;
			--$count;
		}

		return $evicted;
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
	 * Check if a FASP has a specific capability enabled.
	 *
	 * @param string $fasp_id    FASP ID.
	 * @param string $identifier Capability identifier.
	 * @param string $version    Capability version.
	 * @return bool True if capability is enabled, false otherwise.
	 */
	public static function is_capability_enabled( $fasp_id, $identifier, $version ) {
		$registration = self::get( $fasp_id );
		$key          = self::capability_key( $identifier, $version );

		return ! empty( $registration['capabilities'][ $key ]['enabled'] );
	}

	/**
	 * Record whether a capability is enabled for a FASP.
	 *
	 * This only records local state inside the registration record. Callers are
	 * responsible for notifying the FASP first, see {@see Client::activate_capability()}.
	 *
	 * @param string $fasp_id    FASP ID.
	 * @param string $identifier Capability identifier.
	 * @param string $version    Capability version.
	 * @param bool   $enabled    Whether the capability is enabled.
	 * @return bool True on success, false on failure.
	 */
	public static function set_capability_enabled( $fasp_id, $identifier, $version, $enabled ) {
		$registrations = self::get_registrations_store();

		if ( ! isset( $registrations[ $fasp_id ] ) ) {
			return false;
		}

		$registrations[ $fasp_id ]['capabilities'][ self::capability_key( $identifier, $version ) ] = array(
			'identifier' => $identifier,
			'version'    => $version,
			'enabled'    => (bool) $enabled,
			'updated_at' => \current_time( 'mysql', true ),
		);

		return \update_option( self::OPTION_REGISTRATIONS, $registrations, false );
	}

	/**
	 * Build the per-record key for a capability.
	 *
	 * @param string $identifier Capability identifier.
	 * @param string $version    Capability version.
	 * @return string The capability key.
	 */
	private static function capability_key( $identifier, $version ) {
		return $identifier . '@v' . $version;
	}

	/**
	 * Retrieve the registrations store, ensuring the option exists and is non-autoloaded.
	 *
	 * @return array
	 */
	private static function get_registrations_store() {
		// Every mutator writes with autoload disabled, so the option is created non-autoloaded on
		// first write; a read before any write simply falls back to the empty default.
		$registrations = \get_option( self::OPTION_REGISTRATIONS, array() );

		return \is_array( $registrations ) ? $registrations : array();
	}
}
