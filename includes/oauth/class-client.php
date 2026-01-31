<?php
/**
 * OAuth 2.0 Client model for ActivityPub C2S.
 *
 * @package Activitypub
 */

namespace Activitypub\OAuth;

/**
 * Client class for managing OAuth 2.0 client registrations.
 *
 * Supports both manual registration and RFC 7591 dynamic client registration.
 */
class Client {
	/**
	 * Post type for OAuth clients.
	 */
	const POST_TYPE = 'ap_oauth_client';

	/**
	 * The post ID of the client.
	 *
	 * @var int
	 */
	private $post_id;

	/**
	 * Constructor.
	 *
	 * @param int $post_id The post ID of the client.
	 */
	public function __construct( $post_id ) {
		$this->post_id = $post_id;
	}

	/**
	 * Register a new OAuth client.
	 *
	 * @param array $data Client registration data.
	 *                    - name: Client name (required).
	 *                    - redirect_uris: Array of redirect URIs (required).
	 *                    - description: Client description (optional).
	 *                    - is_public: Whether client is public/PKCE-only (default true).
	 *                    - scopes: Allowed scopes (optional, defaults to all).
	 * @return array|\WP_Error Client credentials or error.
	 */
	public static function register( $data ) {
		$name          = $data['name'] ?? '';
		$redirect_uris = $data['redirect_uris'] ?? array();
		$description   = $data['description'] ?? '';
		$is_public     = $data['is_public'] ?? true;
		$scopes        = $data['scopes'] ?? Scope::ALL;

		// Validate required fields.
		if ( empty( $name ) ) {
			return new \WP_Error(
				'activitypub_missing_client_name',
				\__( 'Client name is required.', 'activitypub' ),
				array( 'status' => 400 )
			);
		}

		if ( empty( $redirect_uris ) ) {
			return new \WP_Error(
				'activitypub_missing_redirect_uri',
				\__( 'At least one redirect URI is required.', 'activitypub' ),
				array( 'status' => 400 )
			);
		}

		// Validate redirect URIs.
		foreach ( $redirect_uris as $uri ) {
			if ( ! self::validate_uri_format( $uri ) ) {
				return new \WP_Error(
					'activitypub_invalid_redirect_uri',
					/* translators: %s: The invalid redirect URI */
					sprintf( \__( 'Invalid redirect URI: %s', 'activitypub' ), $uri ),
					array( 'status' => 400 )
				);
			}
		}

		// Generate client credentials.
		$client_id     = self::generate_client_id();
		$client_secret = null;

		if ( ! $is_public ) {
			$client_secret = self::generate_client_secret();
		}

		// Create the client post.
		$post_id = \wp_insert_post(
			array(
				'post_type'    => self::POST_TYPE,
				'post_status'  => 'publish',
				'post_title'   => $name,
				'post_content' => $description,
				'meta_input'   => array(
					'_activitypub_client_id'          => $client_id,
					'_activitypub_client_secret_hash' => $client_secret ? Token::hash_token( $client_secret ) : '',
					'_activitypub_redirect_uris'      => array_map( 'sanitize_url', $redirect_uris ),
					'_activitypub_allowed_scopes'     => Scope::validate( $scopes ),
					'_activitypub_is_public'          => (bool) $is_public,
				),
			),
			true
		);

		if ( \is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$result = array(
			'client_id' => $client_id,
		);

		if ( $client_secret ) {
			$result['client_secret'] = $client_secret;
		}

		return $result;
	}

	/**
	 * Get client by client_id.
	 *
	 * @param string $client_id The client ID.
	 * @return Client|\WP_Error The client or error.
	 */
	public static function get( $client_id ) {
		// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Client lookup by ID is necessary.
		$posts = \get_posts(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => 'publish',
				'meta_key'    => '_activitypub_client_id',
				'meta_value'  => $client_id,
				'numberposts' => 1,
			)
		);
		// phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value

		if ( empty( $posts ) ) {
			return new \WP_Error(
				'activitypub_client_not_found',
				\__( 'OAuth client not found.', 'activitypub' ),
				array( 'status' => 404 )
			);
		}

		return new self( $posts[0]->ID );
	}

	/**
	 * Validate client credentials.
	 *
	 * @param string      $client_id     The client ID.
	 * @param string|null $client_secret The client secret (optional for public clients).
	 * @return bool True if valid.
	 */
	public static function validate( $client_id, $client_secret = null ) {
		$client = self::get( $client_id );

		if ( \is_wp_error( $client ) ) {
			return false;
		}

		// Public clients don't need secret validation.
		if ( $client->is_public() ) {
			return true;
		}

		// Confidential clients require a valid secret.
		if ( empty( $client_secret ) ) {
			return false;
		}

		$stored_hash = \get_post_meta( $client->post_id, '_activitypub_client_secret_hash', true );

		return hash_equals( $stored_hash, Token::hash_token( $client_secret ) );
	}

	/**
	 * Check if redirect URI is valid for this client.
	 *
	 * @param string $redirect_uri The redirect URI to validate.
	 * @return bool True if valid.
	 */
	public function is_valid_redirect_uri( $redirect_uri ) {
		$allowed_uris = $this->get_redirect_uris();

		// Exact match required.
		return in_array( $redirect_uri, $allowed_uris, true );
	}

	/**
	 * Get client name.
	 *
	 * @return string The client name.
	 */
	public function get_name() {
		$post = \get_post( $this->post_id );
		return $post ? $post->post_title : '';
	}

	/**
	 * Get client description.
	 *
	 * @return string The client description.
	 */
	public function get_description() {
		$post = \get_post( $this->post_id );
		return $post ? $post->post_content : '';
	}

	/**
	 * Get client ID.
	 *
	 * @return string The client ID.
	 */
	public function get_client_id() {
		return \get_post_meta( $this->post_id, '_activitypub_client_id', true );
	}

	/**
	 * Get allowed redirect URIs.
	 *
	 * @return array The redirect URIs.
	 */
	public function get_redirect_uris() {
		$uris = \get_post_meta( $this->post_id, '_activitypub_redirect_uris', true );
		return is_array( $uris ) ? $uris : array();
	}

	/**
	 * Get allowed scopes for this client.
	 *
	 * @return array The allowed scopes.
	 */
	public function get_allowed_scopes() {
		$scopes = \get_post_meta( $this->post_id, '_activitypub_allowed_scopes', true );
		return is_array( $scopes ) ? $scopes : Scope::ALL;
	}

	/**
	 * Check if this is a public client.
	 *
	 * @return bool True if public.
	 */
	public function is_public() {
		return (bool) \get_post_meta( $this->post_id, '_activitypub_is_public', true );
	}

	/**
	 * Filter requested scopes to only those allowed for this client.
	 *
	 * @param array $requested_scopes The requested scopes.
	 * @return array Filtered scopes.
	 */
	public function filter_scopes( $requested_scopes ) {
		$allowed = $this->get_allowed_scopes();
		return array_values( array_intersect( $requested_scopes, $allowed ) );
	}

	/**
	 * Generate a unique client ID.
	 *
	 * @return string UUID v4.
	 */
	public static function generate_client_id() {
		// Generate UUID v4.
		$data    = random_bytes( 16 );
		$data[6] = chr( ord( $data[6] ) & 0x0f | 0x40 ); // Version 4.
		$data[8] = chr( ord( $data[8] ) & 0x3f | 0x80 ); // Variant.

		return vsprintf( '%s%s-%s-%s-%s-%s%s%s', str_split( bin2hex( $data ), 4 ) );
	}

	/**
	 * Generate a client secret.
	 *
	 * @return string The client secret.
	 */
	public static function generate_client_secret() {
		return Token::generate_token( 32 );
	}

	/**
	 * Validate a redirect URI format.
	 *
	 * @param string $uri The URI to validate.
	 * @return bool True if valid.
	 */
	private static function validate_uri_format( $uri ) {
		$parsed = wp_parse_url( $uri );

		if ( ! $parsed || empty( $parsed['scheme'] ) || empty( $parsed['host'] ) ) {
			return false;
		}

		// Allow http for localhost development.
		if ( 'http' === $parsed['scheme'] ) {
			$localhost_hosts = array( 'localhost', '127.0.0.1', '[::1]' );
			if ( ! in_array( $parsed['host'], $localhost_hosts, true ) ) {
				return false;
			}
		} elseif ( 'https' !== $parsed['scheme'] ) {
			// Only allow https for production.
			return false;
		}

		return true;
	}

	/**
	 * Delete a client and all its tokens.
	 *
	 * @param string $client_id The client ID to delete.
	 * @return bool True on success.
	 */
	public static function delete( $client_id ) {
		$client = self::get( $client_id );

		if ( \is_wp_error( $client ) ) {
			return false;
		}

		// Delete all tokens for this client.
		// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Token cleanup by client ID is necessary.
		$tokens = \get_posts(
			array(
				'post_type'   => Token::POST_TYPE,
				'meta_key'    => '_activitypub_client_id',
				'meta_value'  => $client_id,
				'numberposts' => -1,
				'fields'      => 'ids',
			)
		);
		// phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value

		foreach ( $tokens as $token_id ) {
			\wp_delete_post( $token_id, true );
		}

		// Delete the client.
		return (bool) \wp_delete_post( $client->post_id, true );
	}
}
