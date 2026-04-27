<?php
/**
 * OAuth 2.0 Token REST Controller.
 *
 * @package Activitypub
 */

namespace Activitypub\Rest\OAuth;

use Activitypub\OAuth\Authorization_Code;
use Activitypub\OAuth\Client;
use Activitypub\OAuth\Scope;
use Activitypub\OAuth\Server as OAuth_Server;
use Activitypub\OAuth\Token;

use function Activitypub\get_client_ip;

/**
 * Token_Controller class for handling OAuth 2.0 token endpoints.
 *
 * Implements:
 * - Token endpoint (POST /oauth/token)
 * - Revocation endpoint (POST /oauth/revoke)
 * - Token introspection endpoint (POST /oauth/introspect)
 *
 * @since 8.1.0
 */
class Token_Controller extends \WP_REST_Controller {
	/**
	 * The namespace of this controller's route.
	 *
	 * @var string
	 */
	protected $namespace = ACTIVITYPUB_REST_NAMESPACE;

	/**
	 * The base of this controller's route.
	 *
	 * @var string
	 */
	protected $rest_base = 'oauth';

	/**
	 * Register routes.
	 */
	public function register_routes() {
		// Token endpoint.
		\register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/token',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'token' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'grant_type'    => array(
							'description' => 'The grant type.',
							'type'        => 'string',
							'required'    => true,
							'enum'        => array( 'authorization_code', 'refresh_token' ),
						),
						'client_id'     => array(
							'description' => 'The OAuth client identifier.',
							'type'        => 'string',
						),
						'client_secret' => array(
							'description' => 'The OAuth client secret (for confidential clients).',
							'type'        => 'string',
						),
						'code'          => array(
							'description' => 'The authorization code (for authorization_code grant).',
							'type'        => 'string',
						),
						'redirect_uri'  => array(
							'description' => 'The redirect URI (must match original for authorization_code grant). Supports custom URI schemes for native apps.',
							'type'        => 'string',
						),
						'code_verifier' => array(
							'description' => 'PKCE code verifier.',
							'type'        => 'string',
						),
						'refresh_token' => array(
							'description' => 'The refresh token (for refresh_token grant).',
							'type'        => 'string',
						),
						'scope'         => array(
							'description' => 'Space-separated list of requested scopes.',
							'type'        => 'string',
						),
					),
				),
			)
		);

		// Revocation endpoint (RFC 7009 — requires authentication).
		\register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/revoke',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'revoke' ),
					'permission_callback' => array( $this, 'revoke_permissions_check' ),
					'args'                => array(
						'token'           => array(
							'description' => 'The token to revoke.',
							'type'        => 'string',
							'required'    => true,
						),
						'token_type_hint' => array(
							'description' => 'Hint about the token type.',
							'type'        => 'string',
							'enum'        => array( 'access_token', 'refresh_token' ),
						),
					),
				),
			)
		);

		// Token introspection endpoint (RFC 7662).
		\register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/introspect',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'introspect' ),
					'permission_callback' => array( $this, 'introspect_permissions_check' ),
					'args'                => array(
						'token'           => array(
							'description' => 'The token to introspect.',
							'type'        => 'string',
							'required'    => true,
						),
						'token_type_hint' => array(
							'description' => 'Hint about the token type.',
							'type'        => 'string',
							'enum'        => array( 'access_token', 'refresh_token' ),
						),
					),
				),
			)
		);
	}

	/**
	 * Handle token request (POST /oauth/token).
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function token( \WP_REST_Request $request ) {
		// Rate-limit token requests to prevent brute-force attacks (max 20 per minute per IP).
		$ip = get_client_ip();
		if ( '' === $ip ) {
			return $this->token_error( 'rate_limited', 'Too many token requests. Please try again later.', 429 );
		}
		$transient_key = 'ap_oauth_tok_' . \md5( $ip );
		$count         = (int) \get_transient( $transient_key );

		if ( $count >= 20 ) {
			return $this->token_error( 'rate_limited', 'Too many token requests. Please try again later.', 429 );
		}

		\set_transient( $transient_key, $count + 1, MINUTE_IN_SECONDS );

		$grant_type = $request->get_param( 'grant_type' );

		/*
		 * Extract client credentials from either:
		 * - client_secret_basic: HTTP Basic Auth header (RFC 6749 Section 2.3.1)
		 * - client_secret_post: POST body parameters
		 */
		$client_id     = null;
		$client_secret = null;
		$auth_header   = $request->get_header( 'Authorization' );

		if ( $auth_header && 0 === \strpos( $auth_header, 'Basic ' ) ) {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Required by OAuth spec.
			$decoded = \base64_decode( \substr( $auth_header, 6 ), true );
			if ( $decoded && false !== \strpos( $decoded, ':' ) ) {
				list( $client_id, $client_secret ) = \explode( ':', $decoded, 2 );
				$client_id                         = \urldecode( $client_id );
				$client_secret                     = \urldecode( $client_secret );
			}
		}

		// Fall back to POST body parameters (client_secret_post).
		if ( ! $client_id ) {
			$client_id     = $request->get_param( 'client_id' );
			$client_secret = $request->get_param( 'client_secret' );
		}

		// Validate client.
		$client = Client::get( $client_id );
		if ( \is_wp_error( $client ) ) {
			return $this->token_error( 'invalid_client', 'Unknown client.' );
		}

		// Validate client credentials if confidential.
		if ( ! $client->is_public() ) {
			if ( ! Client::validate( $client_id, $client_secret ) ) {
				return $this->token_error( 'invalid_client', 'Invalid client credentials.' );
			}
		}

		switch ( $grant_type ) {
			case 'authorization_code':
				return $this->handle_authorization_code_grant( $request, $client_id );

			case 'refresh_token':
				return $this->handle_refresh_token_grant( $request, $client_id );

			default:
				return $this->token_error( 'unsupported_grant_type', 'Grant type not supported.' );
		}
	}

	/**
	 * Handle authorization code grant.
	 *
	 * @param \WP_REST_Request $request   The request object.
	 * @param string           $client_id The client ID.
	 * @return \WP_REST_Response|\WP_Error
	 */
	private function handle_authorization_code_grant( \WP_REST_Request $request, $client_id ) {
		$code          = $request->get_param( 'code' );
		$redirect_uri  = $request->get_param( 'redirect_uri' );
		$code_verifier = $request->get_param( 'code_verifier' );

		if ( empty( $code ) ) {
			return $this->token_error( 'invalid_request', 'Authorization code is required.' );
		}

		$result = Authorization_Code::exchange( $code, $client_id, $redirect_uri, $code_verifier );

		if ( \is_wp_error( $result ) ) {
			return $this->token_error( 'invalid_grant', $result->get_error_message() );
		}

		return $this->token_response( $result );
	}

	/**
	 * Handle refresh token grant.
	 *
	 * @param \WP_REST_Request $request   The request object.
	 * @param string           $client_id The client ID.
	 * @return \WP_REST_Response|\WP_Error
	 */
	private function handle_refresh_token_grant( \WP_REST_Request $request, $client_id ) {
		$refresh_token = $request->get_param( 'refresh_token' );

		if ( empty( $refresh_token ) ) {
			return $this->token_error( 'invalid_request', 'Refresh token is required.' );
		}

		$result = Token::refresh( $refresh_token, $client_id );

		if ( \is_wp_error( $result ) ) {
			return $this->token_error( 'invalid_grant', $result->get_error_message() );
		}

		return $this->token_response( $result );
	}

	/**
	 * Handle token revocation (POST /oauth/revoke).
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response
	 */
	public function revoke( \WP_REST_Request $request ) {
		$token = $request->get_param( 'token' );

		if ( \current_user_can( 'manage_options' ) ) {
			// Site admins may revoke any token. Null-null disables the ownership check.
			Token::revoke( $token );
		} else {
			/*
			 * RFC 7009 §2.1: the server must verify the token was issued to
			 * the requesting client. When the caller authenticated with a
			 * bearer token we know the calling client, so require a client
			 * match and ignore the user — otherwise a low-trust client
			 * could revoke tokens the user had granted to a different
			 * client. For pure cookie-authenticated callers there is no
			 * client context, so user match is the only available check.
			 */
			$caller_token = OAuth_Server::get_current_token();
			if ( $caller_token ) {
				Token::revoke( $token, null, $caller_token->get_client_id() );
			} else {
				Token::revoke( $token, \get_current_user_id(), null );
			}
		}

		// Per RFC 7009, always return 200 even if the token doesn't exist or was not owned.
		return new \WP_REST_Response( null, 200 );
	}

	/**
	 * Handle token introspection (POST /oauth/introspect).
	 *
	 * Implements RFC 7662 Token Introspection.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response
	 */
	public function introspect( \WP_REST_Request $request ) {
		$token = $request->get_param( 'token' );

		// Introspect the token.
		$response = Token::introspect( $token );

		// Scope introspection to same client: non-admin users can only
		// introspect tokens belonging to the same client as their own.
		if ( $response['active'] && ! \current_user_can( 'manage_options' ) ) {
			$current_token = OAuth_Server::get_current_token();
			if ( $current_token && $current_token->get_client_id() !== $response['client_id'] ) {
				$response = array( 'active' => false );
			}
		}

		return new \WP_REST_Response( $response, 200 );
	}

	/**
	 * Permission check for token revocation.
	 *
	 * Per RFC 7009, the revocation endpoint must be protected.
	 * Requires either a logged-in user or a valid Bearer token.
	 *
	 * @return bool|\WP_Error True if allowed, error otherwise.
	 */
	public function revoke_permissions_check() {
		if ( \is_user_logged_in() ) {
			return true;
		}

		$token = OAuth_Server::get_bearer_token();

		if ( $token ) {
			$validated = Token::validate( $token );

			if ( ! \is_wp_error( $validated ) ) {
				\wp_set_current_user( $validated->get_user_id() );
				return true;
			}
		}

		return new \WP_Error(
			'activitypub_unauthorized',
			\__( 'Authentication required.', 'activitypub' ),
			array( 'status' => 401 )
		);
	}

	/**
	 * Permission check for token introspection.
	 *
	 * Per RFC 7662, the introspection endpoint must be protected.
	 *
	 * @return bool|\WP_Error True if allowed, error otherwise.
	 */
	public function introspect_permissions_check() {
		if ( \is_user_logged_in() ) {
			return true;
		}

		// Support Bearer token auth for public OAuth clients.
		$token = OAuth_Server::get_bearer_token();

		if ( $token ) {
			$validated = Token::validate( $token );

			if ( ! \is_wp_error( $validated ) ) {
				\wp_set_current_user( $validated->get_user_id() );
				return true;
			}
		}

		return new \WP_Error(
			'activitypub_unauthorized',
			\__( 'Authentication required.', 'activitypub' ),
			array( 'status' => 401 )
		);
	}

	/**
	 * Create a token error response.
	 *
	 * @param string $error             Error code.
	 * @param string $error_description Error description.
	 * @param int    $status            Optional. HTTP status code. Defaults to 400 per RFC 6749 §5.2;
	 *                                  callers should pass 429 for rate-limit responses (RFC 6585).
	 * @return \WP_REST_Response
	 */
	private function token_error( $error, $error_description, $status = 400 ) {
		return new \WP_REST_Response(
			array(
				'error'             => $error,
				'error_description' => $error_description,
			),
			$status,
			array(
				'Content-Type'  => 'application/json',
				// RFC 6749 §5.1 requires the same no-cache headers on error responses as on success responses.
				'Cache-Control' => 'no-store',
				'Pragma'        => 'no-cache',
			)
		);
	}

	/**
	 * Create a token success response.
	 *
	 * @param array $token_data Token data.
	 * @return \WP_REST_Response
	 */
	private function token_response( $token_data ) {
		return new \WP_REST_Response(
			$token_data,
			200,
			array(
				'Content-Type'  => 'application/json',
				'Cache-Control' => 'no-store',
				'Pragma'        => 'no-cache',
			)
		);
	}
}
