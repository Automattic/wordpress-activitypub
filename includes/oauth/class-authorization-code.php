<?php
/**
 * OAuth 2.0 Authorization Code model for ActivityPub C2S.
 *
 * @package Activitypub
 */

namespace Activitypub\OAuth;

/**
 * Authorization_Code class for managing OAuth 2.0 authorization codes.
 *
 * Authorization codes are short-lived (10 minutes) and support PKCE.
 */
class Authorization_Code {
	/**
	 * Post type for OAuth authorization codes.
	 */
	const POST_TYPE = 'ap_oauth_code';

	/**
	 * Post status for pending (unused) codes.
	 */
	const STATUS_PENDING = 'pending';

	/**
	 * Post status for used codes.
	 */
	const STATUS_USED = 'draft';

	/**
	 * Authorization code expiration in seconds (10 minutes).
	 */
	const EXPIRATION = 600;

	/**
	 * The post ID of the authorization code.
	 *
	 * @var int
	 */
	private $post_id;

	/**
	 * Constructor.
	 *
	 * @param int $post_id The post ID of the authorization code.
	 */
	public function __construct( $post_id ) {
		$this->post_id = $post_id;
	}

	/**
	 * Create a new authorization code.
	 *
	 * @param int    $user_id               WordPress user ID.
	 * @param string $client_id             OAuth client ID.
	 * @param string $redirect_uri          The redirect URI.
	 * @param array  $scopes                Requested scopes.
	 * @param string $code_challenge        PKCE code challenge.
	 * @param string $code_challenge_method PKCE method (S256 or plain).
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

		// Filter scopes to only allowed ones.
		$filtered_scopes = $client->filter_scopes( Scope::validate( $scopes ) );

		// Generate the code.
		$code       = self::generate_code();
		$expires_at = time() + self::EXPIRATION;

		// Create the authorization code post.
		$post_id = \wp_insert_post(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => self::STATUS_PENDING,
				'post_author' => $user_id,
				'post_title'  => sprintf(
					/* translators: %1$s: client ID */
					\__( 'Auth code for %1$s', 'activitypub' ),
					$client_id
				),
				'meta_input'  => array(
					'_activitypub_code_hash'             => Token::hash_token( $code ),
					'_activitypub_client_id'             => $client_id,
					'_activitypub_redirect_uri'          => $redirect_uri,
					'_activitypub_scopes'                => $filtered_scopes,
					'_activitypub_code_challenge'        => $code_challenge,
					'_activitypub_code_challenge_method' => $code_challenge_method,
					'_activitypub_expires_at'            => $expires_at,
				),
			),
			true
		);

		if ( \is_wp_error( $post_id ) ) {
			return $post_id;
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
		$hash = Token::hash_token( $code );

		// Find the authorization code.
		$posts = \get_posts(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => self::STATUS_PENDING,
				'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'relation' => 'AND',
					array(
						'key'   => '_activitypub_code_hash',
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
				'activitypub_invalid_code',
				\__( 'Invalid authorization code.', 'activitypub' ),
				array( 'status' => 400 )
			);
		}

		$post = $posts[0];

		// Check expiration.
		$expires_at = (int) \get_post_meta( $post->ID, '_activitypub_expires_at', true );
		if ( $expires_at < time() ) {
			// Mark as used to prevent further attempts.
			\wp_update_post(
				array(
					'ID'          => $post->ID,
					'post_status' => self::STATUS_USED,
				)
			);

			return new \WP_Error(
				'activitypub_code_expired',
				\__( 'Authorization code has expired.', 'activitypub' ),
				array( 'status' => 400 )
			);
		}

		// Verify redirect URI matches.
		$stored_redirect_uri = \get_post_meta( $post->ID, '_activitypub_redirect_uri', true );
		if ( $redirect_uri !== $stored_redirect_uri ) {
			return new \WP_Error(
				'activitypub_redirect_uri_mismatch',
				\__( 'Redirect URI does not match.', 'activitypub' ),
				array( 'status' => 400 )
			);
		}

		// Verify PKCE.
		$code_challenge        = \get_post_meta( $post->ID, '_activitypub_code_challenge', true );
		$code_challenge_method = \get_post_meta( $post->ID, '_activitypub_code_challenge_method', true ) ?: 'S256';

		if ( ! self::verify_pkce( $code_verifier, $code_challenge, $code_challenge_method ) ) {
			return new \WP_Error(
				'activitypub_invalid_pkce',
				\__( 'Invalid PKCE code verifier.', 'activitypub' ),
				array( 'status' => 400 )
			);
		}

		// Mark the code as used (single use).
		\wp_update_post(
			array(
				'ID'          => $post->ID,
				'post_status' => self::STATUS_USED,
			)
		);

		// Get the user and scopes.
		$user_id = $post->post_author;
		$scopes  = \get_post_meta( $post->ID, '_activitypub_scopes', true );

		// Create and return the tokens.
		return Token::create( $user_id, $client_id, $scopes );
	}

	/**
	 * Verify PKCE code_verifier against code_challenge.
	 *
	 * @param string $code_verifier  The PKCE code verifier.
	 * @param string $code_challenge The stored code challenge.
	 * @param string $method         The challenge method (S256 or plain).
	 * @return bool True if valid.
	 */
	public static function verify_pkce( $code_verifier, $code_challenge, $method = 'S256' ) {
		if ( empty( $code_verifier ) || empty( $code_challenge ) ) {
			return false;
		}

		if ( 'plain' === $method ) {
			return hash_equals( $code_challenge, $code_verifier );
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
		return rtrim( strtr( base64_encode( $hash ), '+/', '-_' ), '=' );
	}

	/**
	 * Generate a random authorization code.
	 *
	 * @return string The authorization code.
	 */
	public static function generate_code() {
		return Token::generate_token( 32 );
	}

	/**
	 * Clean up expired authorization codes.
	 *
	 * Should be called periodically via cron.
	 *
	 * @return int Number of codes deleted.
	 */
	public static function cleanup() {
		global $wpdb;

		$expired_ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
				WHERE p.post_type = %s
				AND pm.meta_key = '_activitypub_expires_at'
				AND pm.meta_value < %d",
				self::POST_TYPE,
				time()
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
