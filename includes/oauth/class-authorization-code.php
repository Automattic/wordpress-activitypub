<?php
/**
 * OAuth 2.0 Authorization Code model for ActivityPub C2S.
 *
 * @package Activitypub
 */

namespace Activitypub\OAuth;

use Activitypub\Sanitize;

/**
 * Authorization_Code class for managing OAuth 2.0 authorization codes.
 *
 * Authorization codes are short-lived (10 minutes) and stored as transients.
 * This is more efficient than CPT for temporary data.
 */
class Authorization_Code {
	/**
	 * Transient prefix for authorization codes.
	 */
	const TRANSIENT_PREFIX = 'activitypub_oauth_code_';

	/**
	 * Authorization code expiration in seconds (10 minutes).
	 */
	const EXPIRATION = 600;

	/**
	 * Create a new authorization code.
	 *
	 * @param int    $user_id               WordPress user ID.
	 * @param string $client_id             OAuth client ID.
	 * @param string $redirect_uri          The redirect URI.
	 * @param array  $scopes                Requested scopes.
	 * @param string $code_challenge        PKCE code challenge.
	 * @param string $code_challenge_method PKCE method (only S256 is supported).
	 * @return string|\WP_Error The authorization code or error.
	 */
	public static function create(
		$user_id,
		$client_id,
		$redirect_uri,
		$scopes,
		$code_challenge,
		$code_challenge_method = 'S256'
	) {
		$redirect_uri = Sanitize::redirect_uri( $redirect_uri );

		// Validate client.
		$client = Client::get( $client_id );
		if ( \is_wp_error( $client ) ) {
			return $client;
		}

		// Validate redirect URI.
		if ( ! $client->is_valid_redirect_uri( $redirect_uri ) ) {
			return new \WP_Error(
				'activitypub_invalid_redirect_uri',
				\__( 'Invalid redirect URI for this client.', 'activitypub' ),
				array( 'status' => 400 )
			);
		}

		/*
		 * PKCE is strongly recommended for public clients (RFC 7636) and
		 * mandatory in the OAuth 2.1 draft. By default, it is not enforced
		 * to maintain compatibility with existing C2S clients, but site
		 * operators can require it via filter.
		 */
		if ( empty( $code_challenge ) && $client->is_public() ) {
			/**
			 * Filter whether PKCE is required for public OAuth clients.
			 *
			 * Return true to enforce PKCE (recommended per OAuth 2.1).
			 * Default false for backward compatibility with older clients.
			 *
			 * @since 8.1.0
			 *
			 * @param bool   $require    Whether to require PKCE. Default false.
			 * @param string $client_id  The OAuth client ID.
			 */
			if ( \apply_filters( 'activitypub_oauth_require_pkce', false, $client_id ) ) {
				return new \WP_Error(
					'activitypub_pkce_required',
					\__( 'PKCE is required for public clients. Please include a code_challenge parameter.', 'activitypub' ),
					array( 'status' => 400 )
				);
			}
		}

		// Filter scopes to only allowed ones.
		$filtered_scopes = $client->filter_scopes( Scope::validate( $scopes ) );

		// Generate the code.
		$code       = self::generate_code();
		$code_hash  = self::hash_code( $code );
		$expires_at = time() + self::EXPIRATION;

		// Store code data in transient.
		$code_data = array(
			'user_id'               => $user_id,
			'client_id'             => $client_id,
			'redirect_uri'          => $redirect_uri,
			'scopes'                => $filtered_scopes,
			'code_challenge'        => $code_challenge,
			'code_challenge_method' => $code_challenge_method,
			'expires_at'            => $expires_at,
			'created_at'            => time(),
		);

		$stored = \set_transient(
			self::TRANSIENT_PREFIX . $code_hash,
			$code_data,
			self::EXPIRATION
		);

		if ( ! $stored ) {
			return new \WP_Error(
				'activitypub_code_storage_failed',
				\__( 'Failed to store authorization code.', 'activitypub' ),
				array( 'status' => 500 )
			);
		}

		return $code;
	}

	/**
	 * Exchange authorization code for tokens.
	 *
	 * @param string $code          The authorization code.
	 * @param string $client_id     The client ID.
	 * @param string $redirect_uri  The redirect URI (must match original).
	 * @param string $code_verifier The PKCE code verifier.
	 * @return array|\WP_Error Token data or error.
	 */
	public static function exchange( $code, $client_id, $redirect_uri, $code_verifier ) {
		$redirect_uri = Sanitize::redirect_uri( $redirect_uri );
		$code_hash    = self::hash_code( $code );
		$transient    = self::TRANSIENT_PREFIX . $code_hash;
		$code_data    = \get_transient( $transient );

		if ( false === $code_data ) {
			return new \WP_Error(
				'activitypub_invalid_code',
				\__( 'Invalid or expired authorization code.', 'activitypub' ),
				array( 'status' => 400 )
			);
		}

		// Immediately delete the code (single use).
		\delete_transient( $transient );

		// Check expiration (belt and suspenders - transient should auto-expire).
		if ( isset( $code_data['expires_at'] ) && $code_data['expires_at'] < time() ) {
			return new \WP_Error(
				'activitypub_code_expired',
				\__( 'Authorization code has expired.', 'activitypub' ),
				array( 'status' => 400 )
			);
		}

		// Verify client ID matches.
		if ( $code_data['client_id'] !== $client_id ) {
			return new \WP_Error(
				'activitypub_client_mismatch',
				\__( 'Client ID does not match.', 'activitypub' ),
				array( 'status' => 400 )
			);
		}

		// Verify redirect URI matches.
		if ( $code_data['redirect_uri'] !== $redirect_uri ) {
			return new \WP_Error(
				'activitypub_redirect_uri_mismatch',
				\__( 'Redirect URI does not match.', 'activitypub' ),
				array( 'status' => 400 )
			);
		}

		// Verify PKCE.
		$code_challenge        = $code_data['code_challenge'] ?? '';
		$code_challenge_method = $code_data['code_challenge_method'] ?? 'S256';

		if ( ! self::verify_pkce( $code_verifier, $code_challenge, $code_challenge_method ) ) {
			return new \WP_Error(
				'activitypub_invalid_pkce',
				\__( 'Invalid PKCE code verifier.', 'activitypub' ),
				array( 'status' => 400 )
			);
		}

		// Create and return the tokens.
		return Token::create(
			$code_data['user_id'],
			$client_id,
			$code_data['scopes']
		);
	}

	/**
	 * Verify PKCE code_verifier against code_challenge.
	 *
	 * @param string $code_verifier  The PKCE code verifier.
	 * @param string $code_challenge The stored code challenge.
	 * @param string $method         The challenge method (only S256 is supported).
	 * @return bool True if valid.
	 */
	public static function verify_pkce( $code_verifier, $code_challenge, $method = 'S256' ) {
		// If PKCE wasn't used during authorization (no challenge stored), skip verification.
		if ( empty( $code_challenge ) ) {
			return true;
		}

		// If challenge was provided but verifier is missing, fail.
		if ( empty( $code_verifier ) ) {
			return false;
		}

		// Only S256 is supported; reject anything else.
		if ( 'S256' !== $method ) {
			return false;
		}

		// S256: BASE64URL(SHA256(code_verifier)) == code_challenge.
		$computed = self::compute_code_challenge( $code_verifier );

		return hash_equals( $code_challenge, $computed );
	}

	/**
	 * Compute a PKCE code challenge from a code verifier.
	 *
	 * @param string $code_verifier The code verifier.
	 * @return string The code challenge (BASE64URL encoded SHA256 hash).
	 */
	public static function compute_code_challenge( $code_verifier ) {
		$hash = hash( 'sha256', $code_verifier, true );
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Required for PKCE BASE64URL encoding per RFC 7636.
		return rtrim( strtr( base64_encode( $hash ), '+/', '-_' ), '=' );
	}

	/**
	 * Generate a random authorization code.
	 *
	 * @return string The authorization code.
	 */
	public static function generate_code() {
		return bin2hex( random_bytes( 32 ) );
	}

	/**
	 * Hash an authorization code for storage lookup.
	 *
	 * @param string $code The authorization code.
	 * @return string The SHA-256 hash.
	 */
	public static function hash_code( $code ) {
		return hash( 'sha256', $code );
	}

	/**
	 * Clean up expired authorization codes.
	 *
	 * Only deletes transients that have actually expired, to avoid breaking
	 * in-progress authorization flows.
	 *
	 * Note: Transients auto-expire, but this cleans up any orphaned ones.
	 * Should be called periodically via cron.
	 *
	 * @return int Number of codes deleted.
	 */
	public static function cleanup() {
		global $wpdb;

		/*
		 * When an external object cache is active, transients are stored in
		 * the cache backend (Redis, Memcached, etc.) and auto-expire there.
		 * The direct SQL below only targets the options table, so skip it.
		 */
		if ( \wp_using_ext_object_cache() ) {
			return 0;
		}

		$timeout_prefix = '_transient_timeout_' . self::TRANSIENT_PREFIX;
		$now            = time();

		// Find expired timeout rows for this prefix.
		$timeout_option_names = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options}
				WHERE option_name LIKE %s
				AND option_value < %d",
				$wpdb->esc_like( $timeout_prefix ) . '%',
				$now
			)
		);

		if ( empty( $timeout_option_names ) ) {
			return 0;
		}

		// Build list of timeout and corresponding value option names to delete.
		$option_names_to_delete = array();
		foreach ( $timeout_option_names as $timeout_name ) {
			$option_names_to_delete[] = $timeout_name;
			$option_names_to_delete[] = str_replace( '_transient_timeout_', '_transient_', $timeout_name );
		}

		$placeholders = implode( ', ', array_fill( 0, count( $option_names_to_delete ), '%s' ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery
		$count = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name IN ( {$placeholders} )",
				$option_names_to_delete
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery

		// Each transient has 2 rows (value + timeout).
		return $count ? (int) ( $count / 2 ) : 0;
	}
}
