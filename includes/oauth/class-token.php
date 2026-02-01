<?php
/**
 * OAuth 2.0 Token model for ActivityPub C2S.
 *
 * @package Activitypub
 */

namespace Activitypub\OAuth;

/**
 * Token class for managing OAuth 2.0 access and refresh tokens.
 *
 * Tokens are stored as user metadata with hashed values for security.
 * This follows the IndieAuth pattern for efficient token management.
 */
class Token {
	/**
	 * User meta key prefix for OAuth tokens.
	 */
	const META_PREFIX = '_activitypub_oauth_token_';

	/**
	 * Option key for tracking users with tokens (for cleanup).
	 */
	const USERS_OPTION = 'activitypub_oauth_token_users';

	/**
	 * Default access token expiration in seconds (1 hour).
	 */
	const DEFAULT_EXPIRATION = 3600;

	/**
	 * Refresh token expiration in seconds (30 days).
	 */
	const REFRESH_EXPIRATION = 2592000;

	/**
	 * The token data array.
	 *
	 * @var array
	 */
	private $data;

	/**
	 * The user ID this token belongs to.
	 *
	 * @var int
	 */
	private $user_id;

	/**
	 * The token key (hash) used for storage.
	 *
	 * @var string
	 */
	private $token_key;

	/**
	 * Constructor.
	 *
	 * @param int    $user_id   The user ID.
	 * @param string $token_key The token key (hash).
	 * @param array  $data      The token data.
	 */
	public function __construct( $user_id, $token_key, $data ) {
		$this->user_id   = $user_id;
		$this->token_key = $token_key;
		$this->data      = $data;
	}

	/**
	 * Create a new access token.
	 *
	 * @param int    $user_id   WordPress user ID.
	 * @param string $client_id OAuth client ID.
	 * @param array  $scopes    Granted scopes.
	 * @param int    $expires   Expiration time in seconds.
	 * @return array|\WP_Error Token data or error.
	 */
	public static function create( $user_id, $client_id, $scopes, $expires = self::DEFAULT_EXPIRATION ) {
		// Generate tokens.
		$access_token  = self::generate_token();
		$refresh_token = self::generate_token();

		// Calculate expirations.
		$access_expires_at  = time() + $expires;
		$refresh_expires_at = time() + self::REFRESH_EXPIRATION;

		// Create token data.
		$token_data = array(
			'access_token_hash'  => self::hash_token( $access_token ),
			'refresh_token_hash' => self::hash_token( $refresh_token ),
			'client_id'          => $client_id,
			'scopes'             => Scope::validate( $scopes ),
			'expires_at'         => $access_expires_at,
			'refresh_expires_at' => $refresh_expires_at,
			'created_at'         => time(),
			'last_used_at'       => null,
		);

		// Store in user meta with access token hash as key.
		$meta_key = self::META_PREFIX . self::hash_token( $access_token );
		$result   = \update_user_meta( $user_id, $meta_key, $token_data );

		if ( false === $result ) {
			return new \WP_Error(
				'activitypub_token_storage_failed',
				\__( 'Failed to store access token.', 'activitypub' ),
				array( 'status' => 500 )
			);
		}

		// Track user for cleanup.
		self::track_user( $user_id );

		return array(
			'access_token'  => $access_token,
			'token_type'    => 'Bearer',
			'expires_in'    => $expires,
			'refresh_token' => $refresh_token,
			'scope'         => Scope::to_string( $scopes ),
		);
	}

	/**
	 * Validate an access token.
	 *
	 * @param string $token The access token to validate.
	 * @return Token|\WP_Error The token object or error.
	 */
	public static function validate( $token ) {
		$token_hash = self::hash_token( $token );
		$meta_key   = self::META_PREFIX . $token_hash;

		// Search for the token across all users with tokens.
		$users = self::get_tracked_users();

		foreach ( $users as $user_id ) {
			$token_data = \get_user_meta( $user_id, $meta_key, true );

			if ( ! empty( $token_data ) && is_array( $token_data ) ) {
				// Verify hash matches.
				if ( isset( $token_data['access_token_hash'] ) &&
					hash_equals( $token_data['access_token_hash'], $token_hash ) ) {

					// Check expiration.
					if ( isset( $token_data['expires_at'] ) && $token_data['expires_at'] < time() ) {
						return new \WP_Error(
							'activitypub_token_expired',
							\__( 'Access token has expired.', 'activitypub' ),
							array( 'status' => 401 )
						);
					}

					// Update last used timestamp.
					$token_data['last_used_at'] = time();
					\update_user_meta( $user_id, $meta_key, $token_data );

					return new self( $user_id, $token_hash, $token_data );
				}
			}
		}

		return new \WP_Error(
			'activitypub_invalid_token',
			\__( 'Invalid access token.', 'activitypub' ),
			array( 'status' => 401 )
		);
	}

	/**
	 * Refresh an access token using a refresh token.
	 *
	 * @param string $refresh_token The refresh token.
	 * @param string $client_id     The client ID (must match original).
	 * @return array|\WP_Error New token data or error.
	 */
	public static function refresh( $refresh_token, $client_id ) {
		$refresh_hash = self::hash_token( $refresh_token );
		$users        = self::get_tracked_users();

		foreach ( $users as $user_id ) {
			// Get all token meta for this user.
			$all_meta = \get_user_meta( $user_id );

			foreach ( $all_meta as $meta_key => $meta_values ) {
				if ( 0 !== strpos( $meta_key, self::META_PREFIX ) ) {
					continue;
				}

				$token_data = maybe_unserialize( $meta_values[0] );

				if ( ! is_array( $token_data ) ) {
					continue;
				}

				// Check if this is our refresh token.
				if ( isset( $token_data['refresh_token_hash'] ) &&
					hash_equals( $token_data['refresh_token_hash'], $refresh_hash ) ) {

					// Verify client ID matches.
					if ( $token_data['client_id'] !== $client_id ) {
						return new \WP_Error(
							'activitypub_client_mismatch',
							\__( 'Client ID does not match.', 'activitypub' ),
							array( 'status' => 400 )
						);
					}

					// Check refresh token expiration.
					if ( isset( $token_data['refresh_expires_at'] ) &&
						$token_data['refresh_expires_at'] < time() ) {
						// Delete the expired token.
						\delete_user_meta( $user_id, $meta_key );

						return new \WP_Error(
							'activitypub_refresh_token_expired',
							\__( 'Refresh token has expired.', 'activitypub' ),
							array( 'status' => 401 )
						);
					}

					// Delete the old token.
					\delete_user_meta( $user_id, $meta_key );

					// Create a new token.
					return self::create( $user_id, $client_id, $token_data['scopes'] );
				}
			}
		}

		return new \WP_Error(
			'activitypub_invalid_refresh_token',
			\__( 'Invalid refresh token.', 'activitypub' ),
			array( 'status' => 401 )
		);
	}

	/**
	 * Revoke a token.
	 *
	 * @param string $token The token to revoke (access or refresh).
	 * @return bool True on success (always returns true per RFC 7009).
	 */
	public static function revoke( $token ) {
		$token_hash = self::hash_token( $token );
		$users      = self::get_tracked_users();

		foreach ( $users as $user_id ) {
			$all_meta = \get_user_meta( $user_id );

			foreach ( $all_meta as $meta_key => $meta_values ) {
				if ( 0 !== strpos( $meta_key, self::META_PREFIX ) ) {
					continue;
				}

				$token_data = maybe_unserialize( $meta_values[0] );

				if ( ! is_array( $token_data ) ) {
					continue;
				}

				// Check both access and refresh token hashes.
				if ( ( isset( $token_data['access_token_hash'] ) &&
						hash_equals( $token_data['access_token_hash'], $token_hash ) ) ||
					( isset( $token_data['refresh_token_hash'] ) &&
						hash_equals( $token_data['refresh_token_hash'], $token_hash ) ) ) {

					\delete_user_meta( $user_id, $meta_key );
					return true;
				}
			}
		}

		// Token doesn't exist or already revoked - that's fine per RFC 7009.
		return true;
	}

	/**
	 * Revoke all tokens for a user.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return int Number of tokens revoked.
	 */
	public static function revoke_all_for_user( $user_id ) {
		$all_meta = \get_user_meta( $user_id );
		$count    = 0;

		foreach ( $all_meta as $meta_key => $meta_values ) {
			if ( 0 === strpos( $meta_key, self::META_PREFIX ) ) {
				\delete_user_meta( $user_id, $meta_key );
				++$count;
			}
		}

		// Remove user from tracking if no more tokens.
		if ( $count > 0 ) {
			self::untrack_user( $user_id );
		}

		return $count;
	}

	/**
	 * Revoke all tokens for a specific client.
	 *
	 * @param string $client_id OAuth client ID.
	 * @return int Number of tokens revoked.
	 */
	public static function revoke_for_client( $client_id ) {
		$users = self::get_tracked_users();
		$count = 0;

		foreach ( $users as $user_id ) {
			$all_meta    = \get_user_meta( $user_id );
			$user_tokens = 0;

			foreach ( $all_meta as $meta_key => $meta_values ) {
				if ( 0 !== strpos( $meta_key, self::META_PREFIX ) ) {
					continue;
				}

				$token_data = maybe_unserialize( $meta_values[0] );

				if ( ! is_array( $token_data ) ) {
					continue;
				}

				// Check if this token belongs to the client.
				if ( isset( $token_data['client_id'] ) && $token_data['client_id'] === $client_id ) {
					\delete_user_meta( $user_id, $meta_key );
					++$count;
				} else {
					++$user_tokens;
				}
			}

			// Untrack user if no more tokens.
			if ( 0 === $user_tokens ) {
				self::untrack_user( $user_id );
			}
		}

		return $count;
	}

	/**
	 * Get all tokens for a user.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return array Array of token data.
	 */
	public static function get_all_for_user( $user_id ) {
		$all_meta = \get_user_meta( $user_id );
		$tokens   = array();

		foreach ( $all_meta as $meta_key => $meta_values ) {
			if ( 0 !== strpos( $meta_key, self::META_PREFIX ) ) {
				continue;
			}

			$token_data = maybe_unserialize( $meta_values[0] );

			if ( is_array( $token_data ) ) {
				// Don't expose hashes.
				unset( $token_data['access_token_hash'], $token_data['refresh_token_hash'] );
				$token_data['meta_key'] = $meta_key; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Not a DB query, just array key.
				$tokens[]               = $token_data;
			}
		}

		return $tokens;
	}

	/**
	 * Check if token has a specific scope.
	 *
	 * @param string $scope The scope to check.
	 * @return bool True if token has scope.
	 */
	public function has_scope( $scope ) {
		$scopes = $this->get_scopes();
		return Scope::contains( $scopes, $scope );
	}

	/**
	 * Get the user ID associated with this token.
	 *
	 * @return int The WordPress user ID.
	 */
	public function get_user_id() {
		return $this->user_id;
	}

	/**
	 * Get the client ID associated with this token.
	 *
	 * @return string The OAuth client ID.
	 */
	public function get_client_id() {
		return $this->data['client_id'] ?? '';
	}

	/**
	 * Get the scopes for this token.
	 *
	 * @return array The granted scopes.
	 */
	public function get_scopes() {
		return $this->data['scopes'] ?? array();
	}

	/**
	 * Get the expiration timestamp.
	 *
	 * @return int Unix timestamp.
	 */
	public function get_expires_at() {
		return $this->data['expires_at'] ?? 0;
	}

	/**
	 * Check if the token is expired.
	 *
	 * @return bool True if expired.
	 */
	public function is_expired() {
		return $this->get_expires_at() < time();
	}

	/**
	 * Get the creation timestamp.
	 *
	 * @return int Unix timestamp.
	 */
	public function get_created_at() {
		return $this->data['created_at'] ?? 0;
	}

	/**
	 * Get the last used timestamp.
	 *
	 * @return int|null Unix timestamp or null if never used.
	 */
	public function get_last_used_at() {
		return $this->data['last_used_at'] ?? null;
	}

	/**
	 * Generate a cryptographically secure random token.
	 *
	 * @param int $length The length of the token in bytes (default 32 = 64 hex chars).
	 * @return string The random token as a hex string.
	 */
	public static function generate_token( $length = 32 ) {
		return bin2hex( random_bytes( $length ) );
	}

	/**
	 * Hash a token for secure storage.
	 *
	 * @param string $token The token to hash.
	 * @return string The SHA-256 hash.
	 */
	public static function hash_token( $token ) {
		return hash( 'sha256', $token );
	}

	/**
	 * Track a user as having tokens.
	 *
	 * @param int $user_id The user ID.
	 */
	private static function track_user( $user_id ) {
		$users = self::get_tracked_users();
		if ( ! in_array( $user_id, $users, true ) ) {
			$users[] = $user_id;
			\update_option( self::USERS_OPTION, $users, false );
		}
	}

	/**
	 * Untrack a user (when they have no more tokens).
	 *
	 * @param int $user_id The user ID.
	 */
	private static function untrack_user( $user_id ) {
		$users = self::get_tracked_users();
		$key   = array_search( $user_id, $users, true );
		if ( false !== $key ) {
			unset( $users[ $key ] );
			\update_option( self::USERS_OPTION, array_values( $users ), false );
		}
	}

	/**
	 * Get all tracked users with tokens.
	 *
	 * @return array User IDs.
	 */
	private static function get_tracked_users() {
		$users = \get_option( self::USERS_OPTION, array() );
		return is_array( $users ) ? $users : array();
	}

	/**
	 * Clean up expired tokens.
	 *
	 * Should be called periodically via cron.
	 *
	 * @return int Number of tokens deleted.
	 */
	public static function cleanup_expired() {
		$users = self::get_tracked_users();
		$count = 0;

		foreach ( $users as $user_id ) {
			$all_meta    = \get_user_meta( $user_id );
			$user_tokens = 0;

			foreach ( $all_meta as $meta_key => $meta_values ) {
				if ( 0 !== strpos( $meta_key, self::META_PREFIX ) ) {
					continue;
				}

				$token_data = maybe_unserialize( $meta_values[0] );

				if ( ! is_array( $token_data ) ) {
					\delete_user_meta( $user_id, $meta_key );
					++$count;
					continue;
				}

				// Check if both access and refresh tokens are expired.
				$access_expired  = isset( $token_data['expires_at'] ) &&
					$token_data['expires_at'] < time() - DAY_IN_SECONDS;
				$refresh_expired = isset( $token_data['refresh_expires_at'] ) &&
					$token_data['refresh_expires_at'] < time();

				if ( $access_expired && $refresh_expired ) {
					\delete_user_meta( $user_id, $meta_key );
					++$count;
				} else {
					++$user_tokens;
				}
			}

			// Untrack user if no more tokens.
			if ( 0 === $user_tokens ) {
				self::untrack_user( $user_id );
			}
		}

		return $count;
	}

	/**
	 * Introspect a token (RFC 7662).
	 *
	 * @param string $token The token to introspect.
	 * @return array Token introspection response.
	 */
	public static function introspect( $token ) {
		$validated = self::validate( $token );

		if ( \is_wp_error( $validated ) ) {
			// Return inactive for invalid/expired tokens.
			return array( 'active' => false );
		}

		$user = \get_userdata( $validated->get_user_id() );

		return array(
			'active'     => true,
			'scope'      => Scope::to_string( $validated->get_scopes() ),
			'client_id'  => $validated->get_client_id(),
			'username'   => $user ? $user->user_login : null,
			'token_type' => 'Bearer',
			'exp'        => $validated->get_expires_at(),
			'iat'        => $validated->get_created_at(),
			'sub'        => (string) $validated->get_user_id(),
		);
	}
}
