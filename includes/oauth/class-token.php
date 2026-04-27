<?php
/**
 * OAuth 2.0 Token model for ActivityPub C2S.
 *
 * @package Activitypub
 */

namespace Activitypub\OAuth;

use Activitypub\Collection\Actors;

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
	 * User meta key prefix for refresh token index (maps refresh hash to access hash).
	 */
	const REFRESH_INDEX_PREFIX = '_activitypub_oauth_refresh_';

	/**
	 * Post meta key on OAuth client posts to track users with tokens.
	 *
	 * Stored as non-unique post meta (one row per user) on ap_oauth_client posts,
	 * following the same pattern as _activitypub_following on ap_actor posts.
	 */
	const USER_META_KEY = '_activitypub_user_id';

	/**
	 * Maximum number of active tokens per user.
	 *
	 * When exceeded, the oldest tokens are revoked automatically.
	 *
	 * @since 8.1.0
	 */
	const MAX_TOKENS_PER_USER = 50;

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
		$access_hash = self::hash_token( $access_token );
		$meta_key    = self::META_PREFIX . $access_hash;
		$result      = \update_user_meta( $user_id, $meta_key, $token_data );

		if ( false === $result ) {
			return new \WP_Error(
				'activitypub_token_storage_failed',
				\__( 'Failed to store access token.', 'activitypub' ),
				array( 'status' => 500 )
			);
		}

		// Store refresh token index for O(1) lookup during refresh.
		$refresh_index_key = self::REFRESH_INDEX_PREFIX . self::hash_token( $refresh_token );
		\update_user_meta( $user_id, $refresh_index_key, $access_hash );

		// Track user on the client post for cleanup.
		self::track_user( $user_id, $client_id );

		// Enforce per-user token limit by revoking the oldest tokens.
		self::enforce_token_limit( $user_id );

		/*
		 * Get the actor URI for the 'me' parameter (IndieAuth convention).
		 * Fall back to blog actor when user actors are disabled.
		 */
		$actor = Actors::get_by_id( $user_id );
		if ( \is_wp_error( $actor ) ) {
			$actor = Actors::get_by_id( Actors::BLOG_USER_ID );
		}
		$me = ! \is_wp_error( $actor ) ? $actor->get_id() : null;

		return array(
			'access_token'  => $access_token,
			'token_type'    => 'Bearer',
			'expires_in'    => $expires,
			'refresh_token' => $refresh_token,
			'scope'         => Scope::to_string( $token_data['scopes'] ),
			'me'            => $me,
		);
	}

	/**
	 * Validate an access token.
	 *
	 * @param string $token The access token to validate.
	 * @return Token|\WP_Error The token object or error.
	 */
	public static function validate( $token ) {
		global $wpdb;

		$token_hash = self::hash_token( $token );
		$meta_key   = self::META_PREFIX . $token_hash;

		// Direct DB lookup by meta_key - O(1) instead of O(n) users.
		$user_id = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT user_id FROM $wpdb->usermeta WHERE meta_key = %s LIMIT 1",
				$meta_key
			)
		);

		if ( empty( $user_id ) ) {
			return new \WP_Error(
				'activitypub_invalid_token',
				\__( 'Invalid access token.', 'activitypub' ),
				array( 'status' => 401 )
			);
		}

		$token_data = \get_user_meta( (int) $user_id, $meta_key, true );

		if ( empty( $token_data ) || ! is_array( $token_data ) ) {
			return new \WP_Error(
				'activitypub_invalid_token',
				\__( 'Invalid access token.', 'activitypub' ),
				array( 'status' => 401 )
			);
		}

		// Verify hash matches.
		if ( ! isset( $token_data['access_token_hash'] ) ||
			! hash_equals( $token_data['access_token_hash'], $token_hash ) ) {
			return new \WP_Error(
				'activitypub_invalid_token',
				\__( 'Invalid access token.', 'activitypub' ),
				array( 'status' => 401 )
			);
		}

		// Check expiration.
		if ( isset( $token_data['expires_at'] ) && $token_data['expires_at'] < time() ) {
			return new \WP_Error(
				'activitypub_token_expired',
				\__( 'Access token has expired.', 'activitypub' ),
				array( 'status' => 401 )
			);
		}

		// Throttle last_used_at writes to avoid a DB write on every request.
		$last_used = $token_data['last_used_at'] ?? 0;
		if ( empty( $last_used ) || ( time() - $last_used ) > 5 * MINUTE_IN_SECONDS ) {
			$token_data['last_used_at'] = time();
			\update_user_meta( (int) $user_id, $meta_key, $token_data );
		}

		return new self( (int) $user_id, $token_hash, $token_data );
	}

	/**
	 * Refresh an access token using a refresh token.
	 *
	 * @param string $refresh_token The refresh token.
	 * @param string $client_id     The client ID (must match original).
	 * @return array|\WP_Error New token data or error.
	 */
	public static function refresh( $refresh_token, $client_id ) {
		global $wpdb;

		$refresh_hash      = self::hash_token( $refresh_token );
		$refresh_index_key = self::REFRESH_INDEX_PREFIX . $refresh_hash;

		// Direct DB lookup by refresh token index - O(1) instead of O(n) users.
		$user_id = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT user_id FROM $wpdb->usermeta WHERE meta_key = %s LIMIT 1",
				$refresh_index_key
			)
		);

		if ( empty( $user_id ) ) {
			return new \WP_Error(
				'activitypub_invalid_refresh_token',
				\__( 'Invalid refresh token.', 'activitypub' ),
				array( 'status' => 401 )
			);
		}

		$user_id = (int) $user_id;

		// Get the access token hash from the index.
		$access_hash = \get_user_meta( $user_id, $refresh_index_key, true );
		if ( empty( $access_hash ) ) {
			return new \WP_Error(
				'activitypub_invalid_refresh_token',
				\__( 'Invalid refresh token.', 'activitypub' ),
				array( 'status' => 401 )
			);
		}

		// Get the full token data.
		$meta_key   = self::META_PREFIX . $access_hash;
		$token_data = \get_user_meta( $user_id, $meta_key, true );

		if ( empty( $token_data ) || ! is_array( $token_data ) ) {
			return new \WP_Error(
				'activitypub_invalid_refresh_token',
				\__( 'Invalid refresh token.', 'activitypub' ),
				array( 'status' => 401 )
			);
		}

		// Verify refresh token hash matches.
		if ( ! isset( $token_data['refresh_token_hash'] ) ||
			! hash_equals( $token_data['refresh_token_hash'], $refresh_hash ) ) {
			return new \WP_Error(
				'activitypub_invalid_refresh_token',
				\__( 'Invalid refresh token.', 'activitypub' ),
				array( 'status' => 401 )
			);
		}

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
			// Delete the expired token and index.
			\delete_user_meta( $user_id, $meta_key );
			\delete_user_meta( $user_id, $refresh_index_key );

			return new \WP_Error(
				'activitypub_refresh_token_expired',
				\__( 'Refresh token has expired.', 'activitypub' ),
				array( 'status' => 401 )
			);
		}

		// Delete the old token and index.
		\delete_user_meta( $user_id, $meta_key );
		\delete_user_meta( $user_id, $refresh_index_key );

		// Create a new token.
		return self::create( $user_id, $client_id, $token_data['scopes'] );
	}

	/**
	 * Revoke a token.
	 *
	 * When `$caller_user_id` or `$caller_client_id` is provided, the token
	 * is only deleted if it was issued to that user or that client, per
	 * RFC 7009 Section 2.1. A mismatch is treated as a successful no-op so
	 * the caller cannot probe for token existence belonging to others.
	 *
	 * @since 8.2.0 The `$caller_user_id` and `$caller_client_id` parameters were added.
	 *
	 * @param string      $token            The token to revoke (access or refresh).
	 * @param int|null    $caller_user_id   Optional. User ID of the caller. Null disables the user check.
	 * @param string|null $caller_client_id Optional. OAuth client ID of the caller. Null disables the client check.
	 * @return bool True on success (always returns true per RFC 7009).
	 */
	public static function revoke( $token, $caller_user_id = null, $caller_client_id = null ) {
		global $wpdb;

		$token_hash = self::hash_token( $token );

		// Try as access token first (O(1) lookup).
		$access_meta_key = self::META_PREFIX . $token_hash;
		$user_id         = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT user_id FROM $wpdb->usermeta WHERE meta_key = %s LIMIT 1",
				$access_meta_key
			)
		);

		if ( $user_id ) {
			$user_id    = (int) $user_id;
			$token_data = \get_user_meta( $user_id, $access_meta_key, true );
			$client_id  = is_array( $token_data ) ? ( $token_data['client_id'] ?? '' ) : '';

			if ( ! self::caller_owns_token( $user_id, $client_id, $caller_user_id, $caller_client_id ) ) {
				return true;
			}

			// Delete the token.
			\delete_user_meta( $user_id, $access_meta_key );

			// Also delete the refresh token index if it exists.
			if ( is_array( $token_data ) && isset( $token_data['refresh_token_hash'] ) ) {
				$refresh_index_key = self::REFRESH_INDEX_PREFIX . $token_data['refresh_token_hash'];
				\delete_user_meta( $user_id, $refresh_index_key );
			}

			self::maybe_untrack_user( $user_id, $client_id );
			return true;
		}

		// Try as refresh token (O(1) lookup via index).
		$refresh_index_key = self::REFRESH_INDEX_PREFIX . $token_hash;
		$user_id           = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT user_id FROM $wpdb->usermeta WHERE meta_key = %s LIMIT 1",
				$refresh_index_key
			)
		);

		if ( $user_id ) {
			$user_id     = (int) $user_id;
			$access_hash = \get_user_meta( $user_id, $refresh_index_key, true );
			$client_id   = '';

			if ( $access_hash ) {
				$token_data = \get_user_meta( $user_id, self::META_PREFIX . $access_hash, true );
				$client_id  = is_array( $token_data ) ? ( $token_data['client_id'] ?? '' ) : '';
			}

			if ( ! self::caller_owns_token( $user_id, $client_id, $caller_user_id, $caller_client_id ) ) {
				return true;
			}

			if ( $access_hash ) {
				\delete_user_meta( $user_id, self::META_PREFIX . $access_hash );
			}
			\delete_user_meta( $user_id, $refresh_index_key );

			self::maybe_untrack_user( $user_id, $client_id );
			return true;
		}

		// Token doesn't exist or already revoked - that's fine per RFC 7009.
		return true;
	}

	/**
	 * Decide whether a caller is permitted to revoke a specific token.
	 *
	 * A null caller user and null caller client disable the check entirely,
	 * preserving the pre-RFC-7009-enforcement behavior for internal callers
	 * that already know they have authority (admin unlink, uninstall, etc.).
	 *
	 * When either caller parameter is provided, the token is considered
	 * owned if it matches the caller user OR the caller client. Matching
	 * client alone is enough to let an OAuth client clean up any token it
	 * issued, regardless of which user granted consent.
	 *
	 * @param int         $token_user_id    User ID the token was issued to.
	 * @param string      $token_client_id  OAuth client ID the token was issued to.
	 * @param int|null    $caller_user_id   Caller user ID, or null to skip the user check.
	 * @param string|null $caller_client_id Caller client ID, or null to skip the client check.
	 * @return bool True if the caller may revoke, false otherwise.
	 */
	private static function caller_owns_token( $token_user_id, $token_client_id, $caller_user_id, $caller_client_id ) {
		if ( null === $caller_user_id && null === $caller_client_id ) {
			return true;
		}

		if ( null !== $caller_user_id && $token_user_id === $caller_user_id ) {
			return true;
		}

		/*
		 * Require a real client_id on the token. An empty string on both
		 * sides would otherwise match and let an un-attributed token be
		 * revoked by any caller presenting an empty client claim.
		 */
		if ( null !== $caller_client_id && '' !== $token_client_id && $token_client_id === $caller_client_id ) {
			return true;
		}

		return false;
	}

	/**
	 * Untrack user from a client if they have no remaining tokens for that client.
	 *
	 * @param int    $user_id   The user ID.
	 * @param string $client_id The OAuth client ID.
	 */
	private static function maybe_untrack_user( $user_id, $client_id ) {
		if ( empty( $client_id ) ) {
			return;
		}

		// Check if user has any remaining tokens for this client.
		$tokens = self::get_all_for_user( $user_id );
		foreach ( $tokens as $token_data ) {
			if ( isset( $token_data['client_id'] ) && $token_data['client_id'] === $client_id ) {
				return; // Still has tokens for this client.
			}
		}

		self::untrack_user( $user_id, $client_id );
	}

	/**
	 * Revoke all tokens for a user.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return int Number of tokens revoked.
	 */
	public static function revoke_all_for_user( $user_id ) {
		$all_meta   = \get_user_meta( $user_id );
		$count      = 0;
		$client_ids = array();

		foreach ( $all_meta as $meta_key => $meta_values ) {
			// Delete token entries and collect client IDs.
			if ( 0 === strpos( $meta_key, self::META_PREFIX ) ) {
				$token_data = \maybe_unserialize( $meta_values[0] );
				if ( is_array( $token_data ) && ! empty( $token_data['client_id'] ) ) {
					$client_ids[] = $token_data['client_id'];
				}
				\delete_user_meta( $user_id, $meta_key );
				++$count;
			}
			// Delete refresh token indices.
			if ( 0 === strpos( $meta_key, self::REFRESH_INDEX_PREFIX ) ) {
				\delete_user_meta( $user_id, $meta_key );
			}
		}

		// Remove user from all client tracking.
		foreach ( array_unique( $client_ids ) as $client_id ) {
			self::untrack_user( $user_id, $client_id );
		}

		return $count;
	}

	/**
	 * Revoke all tokens for all users.
	 *
	 * Used during plugin uninstall to clean up all OAuth token data.
	 *
	 * @return int Number of tokens revoked.
	 */
	public static function revoke_all() {
		$user_ids = self::get_all_tracked_users();
		$count    = 0;

		foreach ( $user_ids as $user_id ) {
			$count += self::revoke_all_for_user( $user_id );
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
		$user_ids = self::get_tracked_users( $client_id );
		$count    = 0;

		foreach ( $user_ids as $user_id ) {
			$all_meta = \get_user_meta( $user_id );

			foreach ( $all_meta as $meta_key => $meta_values ) {
				if ( 0 !== strpos( $meta_key, self::META_PREFIX ) ) {
					continue;
				}

				$token_data = \maybe_unserialize( $meta_values[0] );

				if ( ! is_array( $token_data ) ) {
					continue;
				}

				// Only revoke tokens belonging to this client.
				if ( isset( $token_data['client_id'] ) && $token_data['client_id'] === $client_id ) {
					\delete_user_meta( $user_id, $meta_key );
					// Also delete refresh token index.
					if ( isset( $token_data['refresh_token_hash'] ) ) {
						\delete_user_meta( $user_id, self::REFRESH_INDEX_PREFIX . $token_data['refresh_token_hash'] );
					}
					++$count;
				}
			}
		}

		// Remove all user tracking for this client.
		self::untrack_all_users( $client_id );

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

			$token_data = \maybe_unserialize( $meta_values[0] );

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
	 * Track a user as having tokens for a client.
	 *
	 * Stores user ID as non-unique post meta on the client post,
	 * following the same pattern as _activitypub_following on ap_actor posts.
	 *
	 * @param int    $user_id   The user ID.
	 * @param string $client_id The OAuth client ID.
	 */
	private static function track_user( $user_id, $client_id ) {
		$client = Client::get( $client_id );

		if ( \is_wp_error( $client ) ) {
			return;
		}

		$post_id  = $client->get_post_id();
		$existing = \get_post_meta( $post_id, self::USER_META_KEY, false );

		if ( ! in_array( $user_id, array_map( 'intval', $existing ), true ) ) {
			\add_post_meta( $post_id, self::USER_META_KEY, $user_id );
		}
	}

	/**
	 * Enforce per-user token limit by revoking oldest tokens.
	 *
	 * @since 8.1.0
	 *
	 * @param int $user_id The user ID.
	 */
	private static function enforce_token_limit( $user_id ) {
		$all_meta = \get_user_meta( $user_id );
		$tokens   = array();

		foreach ( $all_meta as $meta_key => $meta_values ) {
			if ( 0 !== strpos( $meta_key, self::META_PREFIX ) ) {
				continue;
			}

			$token_data = \maybe_unserialize( $meta_values[0] );

			if ( is_array( $token_data ) ) {
				$tokens[ $meta_key ] = $token_data;
			}
		}

		if ( count( $tokens ) <= self::MAX_TOKENS_PER_USER ) {
			return;
		}

		// Sort by created_at ascending (oldest first).
		uasort(
			$tokens,
			function ( $a, $b ) {
				return ( $a['created_at'] ?? 0 ) - ( $b['created_at'] ?? 0 );
			}
		);

		$to_remove = count( $tokens ) - self::MAX_TOKENS_PER_USER;

		foreach ( $tokens as $meta_key => $token_data ) {
			if ( $to_remove <= 0 ) {
				break;
			}

			\delete_user_meta( $user_id, $meta_key );

			// Also delete the refresh token index.
			if ( isset( $token_data['refresh_token_hash'] ) ) {
				\delete_user_meta( $user_id, self::REFRESH_INDEX_PREFIX . $token_data['refresh_token_hash'] );
			}

			--$to_remove;
		}
	}

	/**
	 * Untrack a user from a specific client.
	 *
	 * @param int    $user_id   The user ID.
	 * @param string $client_id The OAuth client ID.
	 */
	private static function untrack_user( $user_id, $client_id ) {
		$client = Client::get( $client_id );

		if ( \is_wp_error( $client ) ) {
			return;
		}

		\delete_post_meta( $client->get_post_id(), self::USER_META_KEY, $user_id );
	}

	/**
	 * Untrack all users from a specific client.
	 *
	 * @param string $client_id The OAuth client ID.
	 */
	private static function untrack_all_users( $client_id ) {
		$client = Client::get( $client_id );

		if ( \is_wp_error( $client ) ) {
			return;
		}

		\delete_post_meta( $client->get_post_id(), self::USER_META_KEY );
	}

	/**
	 * Get tracked users for a specific client.
	 *
	 * @param string $client_id The OAuth client ID.
	 * @return array User IDs.
	 */
	private static function get_tracked_users( $client_id ) {
		$client = Client::get( $client_id );

		if ( \is_wp_error( $client ) ) {
			return array();
		}

		$user_ids = \get_post_meta( $client->get_post_id(), self::USER_META_KEY, false );

		return array_map( 'intval', $user_ids );
	}

	/**
	 * Get all user IDs with tokens across all clients.
	 *
	 * @return array Unique user IDs.
	 */
	private static function get_all_tracked_users() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$user_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT pm.meta_value FROM $wpdb->postmeta pm
				INNER JOIN $wpdb->posts p ON pm.post_id = p.ID
				WHERE p.post_type = %s AND pm.meta_key = %s",
				Client::POST_TYPE,
				self::USER_META_KEY
			)
		);

		return array_map( 'intval', $user_ids );
	}

	/**
	 * Clean up expired tokens.
	 *
	 * Should be called periodically via cron.
	 *
	 * @return int Number of tokens deleted.
	 */
	public static function cleanup_expired() {
		$user_ids = self::get_all_tracked_users();
		$count    = 0;

		foreach ( $user_ids as $user_id ) {
			$all_meta   = \get_user_meta( $user_id );
			$client_ids = array();

			foreach ( $all_meta as $meta_key => $meta_values ) {
				if ( 0 !== strpos( $meta_key, self::META_PREFIX ) ) {
					continue;
				}

				$token_data = \maybe_unserialize( $meta_values[0] );

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
					// Also delete refresh token index.
					if ( isset( $token_data['refresh_token_hash'] ) ) {
						\delete_user_meta( $user_id, self::REFRESH_INDEX_PREFIX . $token_data['refresh_token_hash'] );
					}
					++$count;

					if ( ! empty( $token_data['client_id'] ) ) {
						$client_ids[] = $token_data['client_id'];
					}
				}
			}

			// Untrack user from clients where all tokens were removed.
			foreach ( array_unique( $client_ids ) as $client_id ) {
				self::maybe_untrack_user( $user_id, $client_id );
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

		$user_id = $validated->get_user_id();
		$user    = \get_userdata( $user_id );

		/*
		 * Get the actor URI for the 'me' parameter (IndieAuth convention).
		 * Fall back to blog actor when user actors are disabled.
		 */
		$actor = Actors::get_by_id( $user_id );
		if ( \is_wp_error( $actor ) ) {
			$actor = Actors::get_by_id( Actors::BLOG_USER_ID );
		}
		$me = ! \is_wp_error( $actor ) ? $actor->get_id() : null;

		return array(
			'active'     => true,
			'scope'      => Scope::to_string( $validated->get_scopes() ),
			'client_id'  => $validated->get_client_id(),
			'username'   => $user ? $user->user_login : null,
			'token_type' => 'Bearer',
			'exp'        => $validated->get_expires_at(),
			'iat'        => $validated->get_created_at(),
			'sub'        => (string) $user_id,
			'me'         => $me,
		);
	}
}
