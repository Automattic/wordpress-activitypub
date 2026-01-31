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
 * Tokens are stored as Custom Post Types with hashed values for security.
 */
class Token {
	/**
	 * Post type for OAuth tokens.
	 */
	const POST_TYPE = 'ap_oauth_token';

	/**
	 * Post status for active tokens.
	 */
	const STATUS_ACTIVE = 'publish';

	/**
	 * Post status for revoked tokens.
	 */
	const STATUS_REVOKED = 'draft';

	/**
	 * Default access token expiration in seconds (1 hour).
	 */
	const DEFAULT_EXPIRATION = 3600;

	/**
	 * The post ID of the token.
	 *
	 * @var int
	 */
	private $post_id;

	/**
	 * Constructor.
	 *
	 * @param int $post_id The post ID of the token.
	 */
	public function __construct( $post_id ) {
		$this->post_id = $post_id;
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

		// Calculate expiration.
		$expires_at = time() + $expires;

		// Create the token post.
		$post_id = \wp_insert_post(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => self::STATUS_ACTIVE,
				'post_author' => $user_id,
				'post_title'  => sprintf(
					/* translators: %1$s: client ID, %2$s: user login */
					\__( 'Token for %1$s (%2$s)', 'activitypub' ),
					$client_id,
					\get_userdata( $user_id )->user_login ?? $user_id
				),
				'meta_input'  => array(
					'_activitypub_access_token_hash'  => self::hash_token( $access_token ),
					'_activitypub_refresh_token_hash' => self::hash_token( $refresh_token ),
					'_activitypub_client_id'          => $client_id,
					'_activitypub_scopes'             => Scope::validate( $scopes ),
					'_activitypub_expires_at'         => $expires_at,
				),
			),
			true
		);

		if ( \is_wp_error( $post_id ) ) {
			return $post_id;
		}

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
		$hash = self::hash_token( $token );

		// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Token lookup by hash is necessary.
		$posts = \get_posts(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => self::STATUS_ACTIVE,
				'meta_key'    => '_activitypub_access_token_hash',
				'meta_value'  => $hash,
				'numberposts' => 1,
			)
		);
		// phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value

		if ( empty( $posts ) ) {
			return new \WP_Error(
				'activitypub_invalid_token',
				\__( 'Invalid or expired access token.', 'activitypub' ),
				array( 'status' => 401 )
			);
		}

		$post       = $posts[0];
		$expires_at = (int) \get_post_meta( $post->ID, '_activitypub_expires_at', true );

		if ( $expires_at < time() ) {
			return new \WP_Error(
				'activitypub_token_expired',
				\__( 'Access token has expired.', 'activitypub' ),
				array( 'status' => 401 )
			);
		}

		return new self( $post->ID );
	}

	/**
	 * Refresh an access token using a refresh token.
	 *
	 * @param string $refresh_token The refresh token.
	 * @param string $client_id     The client ID (must match original).
	 * @return array|\WP_Error New token data or error.
	 */
	public static function refresh( $refresh_token, $client_id ) {
		$hash = self::hash_token( $refresh_token );

		$posts = \get_posts(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => self::STATUS_ACTIVE,
				'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'relation' => 'AND',
					array(
						'key'   => '_activitypub_refresh_token_hash',
						'value' => $hash,
					),
					array(
						'key'   => '_activitypub_client_id',
						'value' => $client_id,
					),
				),
				'numberposts' => 1,
			)
		);

		if ( empty( $posts ) ) {
			return new \WP_Error(
				'activitypub_invalid_refresh_token',
				\__( 'Invalid refresh token.', 'activitypub' ),
				array( 'status' => 401 )
			);
		}

		$post = $posts[0];

		// Get existing data.
		$user_id = $post->post_author;
		$scopes  = \get_post_meta( $post->ID, '_activitypub_scopes', true );

		// Revoke the old token.
		\wp_update_post(
			array(
				'ID'          => $post->ID,
				'post_status' => self::STATUS_REVOKED,
			)
		);

		// Create a new token.
		return self::create( $user_id, $client_id, $scopes );
	}

	/**
	 * Revoke a token.
	 *
	 * @param string $token The token to revoke (access or refresh).
	 * @return bool True on success.
	 */
	public static function revoke( $token ) {
		$hash = self::hash_token( $token );

		// Check both access and refresh token hashes.
		$posts = \get_posts(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => self::STATUS_ACTIVE,
				'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'relation' => 'OR',
					array(
						'key'   => '_activitypub_access_token_hash',
						'value' => $hash,
					),
					array(
						'key'   => '_activitypub_refresh_token_hash',
						'value' => $hash,
					),
				),
				'numberposts' => 1,
			)
		);

		if ( empty( $posts ) ) {
			// Token doesn't exist or already revoked - that's fine per RFC 7009.
			return true;
		}

		$result = \wp_update_post(
			array(
				'ID'          => $posts[0]->ID,
				'post_status' => self::STATUS_REVOKED,
			)
		);

		return ! \is_wp_error( $result );
	}

	/**
	 * Revoke all tokens for a user.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return int Number of tokens revoked.
	 */
	public static function revoke_all_for_user( $user_id ) {
		$posts = \get_posts(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => self::STATUS_ACTIVE,
				'author'      => $user_id,
				'numberposts' => -1,
			)
		);

		$count = 0;
		foreach ( $posts as $post ) {
			$result = \wp_update_post(
				array(
					'ID'          => $post->ID,
					'post_status' => self::STATUS_REVOKED,
				)
			);
			if ( ! \is_wp_error( $result ) ) {
				++$count;
			}
		}

		return $count;
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
		$post = \get_post( $this->post_id );
		return $post ? (int) $post->post_author : 0;
	}

	/**
	 * Get the client ID associated with this token.
	 *
	 * @return string The OAuth client ID.
	 */
	public function get_client_id() {
		return \get_post_meta( $this->post_id, '_activitypub_client_id', true );
	}

	/**
	 * Get the scopes for this token.
	 *
	 * @return array The granted scopes.
	 */
	public function get_scopes() {
		$scopes = \get_post_meta( $this->post_id, '_activitypub_scopes', true );
		return is_array( $scopes ) ? $scopes : array();
	}

	/**
	 * Get the expiration timestamp.
	 *
	 * @return int Unix timestamp.
	 */
	public function get_expires_at() {
		return (int) \get_post_meta( $this->post_id, '_activitypub_expires_at', true );
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
	 * Clean up expired tokens.
	 *
	 * Should be called periodically via cron.
	 *
	 * @return int Number of tokens deleted.
	 */
	public static function cleanup_expired() {
		global $wpdb;

		$expired_ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
				WHERE p.post_type = %s
				AND pm.meta_key = '_activitypub_expires_at'
				AND pm.meta_value < %d",
				self::POST_TYPE,
				time() - DAY_IN_SECONDS // Grace period of 1 day.
			)
		);

		$count = 0;
		foreach ( $expired_ids as $post_id ) {
			if ( \wp_delete_post( $post_id, true ) ) {
				++$count;
			}
		}

		return $count;
	}
}
