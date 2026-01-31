<?php
/**
 * OAuth 2.0 Server for ActivityPub C2S.
 *
 * @package Activitypub
 */

namespace Activitypub\OAuth;

/**
 * Server class for OAuth 2.0 authentication and PKCE verification.
 *
 * Integrates with WordPress REST API authentication system.
 */
class Server {
	/**
	 * The current validated token for this request.
	 *
	 * @var Token|null
	 */
	private static $current_token = null;

	/**
	 * Initialize the OAuth server.
	 *
	 * Hooks into WordPress REST API authentication.
	 */
	public static function init() {
		// Hook into REST authentication - priority 20 to run after default auth.
		\add_filter( 'rest_authentication_errors', array( self::class, 'authenticate_oauth' ), 20 );

		// Schedule cleanup cron.
		if ( ! \wp_next_scheduled( 'activitypub_oauth_cleanup' ) ) {
			\wp_schedule_event( time(), 'daily', 'activitypub_oauth_cleanup' );
		}
		\add_action( 'activitypub_oauth_cleanup', array( self::class, 'cleanup' ) );
	}

	/**
	 * Authenticate OAuth Bearer token for REST API requests.
	 *
	 * @param \WP_Error|null|bool $result Authentication result from previous filters.
	 * @return \WP_Error|null|bool Authentication result.
	 */
	public static function authenticate_oauth( $result ) {
		// If another authentication method already succeeded, use that.
		if ( true === $result || \is_user_logged_in() ) {
			return $result;
		}

		// Check for Bearer token.
		$token = self::get_bearer_token();

		if ( ! $token ) {
			// No Bearer token present - let other auth methods handle it.
			return $result;
		}

		// Validate the token.
		$validated = Token::validate( $token );

		if ( \is_wp_error( $validated ) ) {
			return $validated;
		}

		// Store the validated token for later use.
		self::$current_token = $validated;

		// Set the current user.
		\wp_set_current_user( $validated->get_user_id() );

		return true;
	}

	/**
	 * Get the current OAuth token from the request.
	 *
	 * @return Token|null The validated token or null.
	 */
	public static function get_current_token() {
		return self::$current_token;
	}

	/**
	 * Check if the current request is authenticated via OAuth.
	 *
	 * @return bool True if OAuth authenticated.
	 */
	public static function is_oauth_request() {
		return null !== self::$current_token;
	}

	/**
	 * Check if the current token has a specific scope.
	 *
	 * @param string $scope The scope to check.
	 * @return bool True if the current token has the scope.
	 */
	public static function has_scope( $scope ) {
		if ( ! self::$current_token ) {
			return false;
		}

		return self::$current_token->has_scope( $scope );
	}

	/**
	 * Extract Bearer token from Authorization header.
	 *
	 * @return string|null The token string or null.
	 */
	public static function get_bearer_token() {
		$auth_header = self::get_authorization_header();

		if ( ! $auth_header ) {
			return null;
		}

		// Check for Bearer token.
		if ( 0 !== strpos( $auth_header, 'Bearer ' ) ) {
			return null;
		}

		return substr( $auth_header, 7 );
	}

	/**
	 * Get the Authorization header.
	 *
	 * @return string|null The authorization header value or null.
	 */
	private static function get_authorization_header() {
		// Check for standard header.
		if ( ! empty( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['HTTP_AUTHORIZATION'] ) );
		}

		// Check for redirect header (some servers use this).
		if ( ! empty( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) );
		}

		// Try to get from Apache.
		if ( function_exists( 'apache_request_headers' ) ) {
			$headers = apache_request_headers();
			if ( isset( $headers['Authorization'] ) ) {
				return sanitize_text_field( $headers['Authorization'] );
			}
			// Check case-insensitive.
			foreach ( $headers as $key => $value ) {
				if ( 'authorization' === strtolower( $key ) ) {
					return sanitize_text_field( $value );
				}
			}
		}

		return null;
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
		return Authorization_Code::verify_pkce( $code_verifier, $code_challenge, $method );
	}

	/**
	 * Generate a cryptographically secure random string.
	 *
	 * @param int $length The length of the string in bytes.
	 * @return string The random string as hex.
	 */
	public static function generate_token( $length = 32 ) {
		return Token::generate_token( $length );
	}

	/**
	 * Permission callback for OAuth-protected endpoints.
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @param string           $scope   Required scope (optional).
	 * @return bool|\WP_Error True if authorized, error otherwise.
	 */
	public static function check_oauth_permission( $request, $scope = null ) {
		// Must be authenticated via OAuth.
		if ( ! self::is_oauth_request() ) {
			return new \WP_Error(
				'activitypub_oauth_required',
				\__( 'OAuth authentication required.', 'activitypub' ),
				array( 'status' => 401 )
			);
		}

		// Check scope if specified.
		if ( $scope && ! self::has_scope( $scope ) ) {
			return new \WP_Error(
				'activitypub_insufficient_scope',
				/* translators: %s: The required scope */
				sprintf( \__( 'This action requires the "%s" scope.', 'activitypub' ), $scope ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Check if C2S (Client-to-Server) is enabled.
	 *
	 * @return bool True if C2S is enabled.
	 */
	public static function is_c2s_enabled() {
		return (bool) \get_option( 'activitypub_enable_c2s', false );
	}

	/**
	 * Run cleanup tasks for OAuth data.
	 */
	public static function cleanup() {
		// Clean up expired tokens.
		Token::cleanup_expired();

		// Clean up expired authorization codes.
		Authorization_Code::cleanup();
	}

	/**
	 * Get OAuth server metadata for discovery.
	 *
	 * @return array OAuth server metadata.
	 */
	public static function get_metadata() {
		$base_url = \trailingslashit( \get_rest_url( null, ACTIVITYPUB_REST_NAMESPACE ) );

		return array(
			'issuer'                                => \home_url(),
			'authorization_endpoint'                => $base_url . 'oauth/authorize',
			'token_endpoint'                        => $base_url . 'oauth/token',
			'revocation_endpoint'                   => $base_url . 'oauth/revoke',
			'registration_endpoint'                 => $base_url . 'oauth/clients',
			'scopes_supported'                      => Scope::ALL,
			'response_types_supported'              => array( 'code' ),
			'response_modes_supported'              => array( 'query' ),
			'grant_types_supported'                 => array( 'authorization_code', 'refresh_token' ),
			'token_endpoint_auth_methods_supported' => array( 'none', 'client_secret_post' ),
			'code_challenge_methods_supported'      => array( 'S256', 'plain' ),
			'service_documentation'                 => 'https://github.com/swicg/activitypub-api',
		);
	}
}
