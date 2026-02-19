<?php
/**
 * OAuth 2.0 REST Controller for ActivityPub C2S.
 *
 * @package Activitypub
 */

namespace Activitypub\Rest;

use Activitypub\OAuth\Authorization_Code;
use Activitypub\OAuth\Client;
use Activitypub\OAuth\DPoP;
use Activitypub\OAuth\Scope;
use Activitypub\OAuth\Server as OAuth_Server;
use Activitypub\OAuth\Token;

/**
 * OAuth_Controller class for handling OAuth 2.0 endpoints.
 *
 * Implements:
 * - Authorization endpoint (GET/POST /oauth/authorize)
 * - Token endpoint (POST /oauth/token)
 * - Revocation endpoint (POST /oauth/revoke)
 * - Dynamic client registration (POST /oauth/clients)
 */
class OAuth_Controller extends \WP_REST_Controller {
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
		// Authorization endpoint - GET displays consent form, POST handles approval.
		\register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/authorize',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'authorize' ),
					'permission_callback' => '__return_true',
					'args'                => $this->get_authorize_args(),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'authorize_submit' ),
					'permission_callback' => array( $this, 'authorize_submit_permissions_check' ),
					'args'                => array_merge(
						$this->get_authorize_args(),
						array(
							'approve'  => array(
								'description' => 'Whether the user approved the authorization.',
								'type'        => 'boolean',
								'required'    => true,
							),
							'_wpnonce' => array(
								'description' => 'WordPress nonce for CSRF protection.',
								'type'        => 'string',
								'required'    => true,
							),
						)
					),
				),
			)
		);

		// Token endpoint.
		\register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/token',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'token' ),
					'permission_callback' => '__return_true',
					'args'                => $this->get_token_args(),
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

		// Dynamic client registration (RFC 7591).
		\register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/clients',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'register_client' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'client_name'   => array(
							'description' => 'Human-readable name of the client.',
							'type'        => 'string',
							'required'    => true,
						),
						'redirect_uris' => array(
							'description' => 'Array of redirect URIs.',
							'type'        => 'array',
							'items'       => array(
								'type'   => 'string',
								'format' => 'uri',
							),
							'required'    => true,
						),
						'client_uri'    => array(
							'description' => 'URL of the client homepage.',
							'type'        => 'string',
							'format'      => 'uri',
						),
						'scope'         => array(
							'description' => 'Space-separated list of requested scopes.',
							'type'        => 'string',
						),
					),
				),
			)
		);

		// Authorization Server Metadata (RFC 8414).
		\register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/authorization-server-metadata',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_metadata' ),
					'permission_callback' => '__return_true',
				),
			)
		);
	}

	/**
	 * Handle authorization request (GET /oauth/authorize).
	 *
	 * Validates request parameters and redirects to wp-admin consent page.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function authorize( \WP_REST_Request $request ) {
		$client_id     = $request->get_param( 'client_id' );
		$redirect_uri  = $request->get_param( 'redirect_uri' );
		$response_type = $request->get_param( 'response_type' );
		$scope         = $request->get_param( 'scope' );
		$state         = $request->get_param( 'state' );

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

		// Only support 'code' response type.
		if ( 'code' !== $response_type ) {
			return $this->redirect_with_error(
				$redirect_uri,
				'unsupported_response_type',
				'Only authorization code flow is supported.',
				$state
			);
		}

		// Check for PKCE (recommended but optional for compatibility).
		$code_challenge = $request->get_param( 'code_challenge' );

		/*
		 * Redirect to wp-login.php with action=activitypub_authorize.
		 * This uses WordPress's login_form_{action} hook for proper cookie auth.
		 */
		$login_url = \wp_login_url();
		$login_url = \add_query_arg(
			array(
				'action'                => 'activitypub_authorize',
				'client_id'             => $client_id,
				'redirect_uri'          => $redirect_uri,
				'response_type'         => $response_type,
				'scope'                 => $scope,
				'state'                 => $state,
				'code_challenge'        => $code_challenge,
				'code_challenge_method' => $request->get_param( 'code_challenge_method' ) ?: 'S256',
			),
			$login_url
		);

		return new \WP_REST_Response(
			null,
			302,
			array( 'Location' => $login_url )
		);
	}

	/**
	 * Handle authorization approval (POST /oauth/authorize).
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function authorize_submit( \WP_REST_Request $request ) {
		$client_id             = $request->get_param( 'client_id' );
		$redirect_uri          = $request->get_param( 'redirect_uri' );
		$scope                 = $request->get_param( 'scope' );
		$state                 = $request->get_param( 'state' );
		$code_challenge        = $request->get_param( 'code_challenge' );
		$code_challenge_method = $request->get_param( 'code_challenge_method' ) ?: 'S256';
		$approve               = $request->get_param( 'approve' );

		// Re-validate client and redirect URI (form fields could be tampered with).
		$client = Client::get( $client_id );
		if ( \is_wp_error( $client ) ) {
			return $client;
		}

		if ( ! $client->is_valid_redirect_uri( $redirect_uri ) ) {
			return new \WP_Error(
				'activitypub_invalid_redirect_uri',
				\__( 'Invalid redirect URI for this client.', 'activitypub' ),
				array( 'status' => 400 )
			);
		}

		// User denied authorization.
		if ( ! $approve ) {
			return $this->redirect_with_error(
				$redirect_uri,
				'access_denied',
				'The user denied the authorization request.',
				$state
			);
		}

		// Create authorization code.
		$scopes = Scope::validate( Scope::parse( $scope ) );
		$code   = Authorization_Code::create(
			\get_current_user_id(),
			$client_id,
			$redirect_uri,
			$scopes,
			$code_challenge,
			$code_challenge_method
		);

		if ( \is_wp_error( $code ) ) {
			return $this->redirect_with_error(
				$redirect_uri,
				'server_error',
				$code->get_error_message(),
				$state
			);
		}

		// Redirect back to client with code.
		$redirect_url = \add_query_arg(
			array(
				'code'  => $code,
				'state' => $state,
			),
			$redirect_uri
		);

		return new \WP_REST_Response(
			null,
			302,
			array( 'Location' => $redirect_url )
		);
	}

	/**
	 * Permission check for authorization submission.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return bool|\WP_Error True if allowed, error otherwise.
	 */
	public function authorize_submit_permissions_check( \WP_REST_Request $request ) {
		if ( ! \is_user_logged_in() ) {
			return new \WP_Error(
				'activitypub_not_logged_in',
				\__( 'You must be logged in to authorize applications.', 'activitypub' ),
				array( 'status' => 401 )
			);
		}

		// Verify nonce.
		$nonce = $request->get_param( '_wpnonce' );
		if ( ! \wp_verify_nonce( $nonce, 'activitypub_oauth_authorize' ) ) {
			return new \WP_Error(
				'activitypub_invalid_nonce',
				\__( 'Invalid security token. Please try again.', 'activitypub' ),
				array( 'status' => 403 )
			);
		}

		return true;
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
	 * Handle token request (POST /oauth/token).
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function token( \WP_REST_Request $request ) {
		// Rate-limit token requests to prevent brute-force attacks (max 20 per minute per IP).
		$ip            = isset( $_SERVER['REMOTE_ADDR'] ) ? \sanitize_text_field( \wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown'; // phpcs:ignore WordPressVIPMinimum.Variables.ServerVariables.UserControlledHeaders
		$transient_key = 'ap_oauth_tok_' . \md5( $ip );
		$count         = (int) \get_transient( $transient_key );

		if ( $count >= 20 ) {
			return $this->token_error( 'invalid_request', 'Too many token requests. Please try again later.' );
		}

		\set_transient( $transient_key, $count + 1, MINUTE_IN_SECONDS );

		$grant_type = $request->get_param( 'grant_type' );
		$client_id  = $request->get_param( 'client_id' );

		// Validate client.
		$client = Client::get( $client_id );
		if ( \is_wp_error( $client ) ) {
			return $this->token_error( 'invalid_client', 'Unknown client.' );
		}

		// Validate client credentials if confidential.
		if ( ! $client->is_public() ) {
			$client_secret = $request->get_param( 'client_secret' );
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

		// Check for DPoP proof (RFC 9449) — opt-in by client.
		$dpop_jkt = $this->validate_dpop_for_token_request();

		if ( \is_wp_error( $dpop_jkt ) ) {
			return $this->token_error( 'invalid_dpop_proof', $dpop_jkt->get_error_message() );
		}

		$result = Authorization_Code::exchange( $code, $client_id, $redirect_uri, $code_verifier, $dpop_jkt );

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

		// Check for DPoP proof (RFC 9449) — opt-in by client.
		$dpop_jkt = $this->validate_dpop_for_token_request();

		if ( \is_wp_error( $dpop_jkt ) ) {
			return $this->token_error( 'invalid_dpop_proof', $dpop_jkt->get_error_message() );
		}

		$result = Token::refresh( $refresh_token, $client_id );

		if ( \is_wp_error( $result ) ) {
			return $this->token_error( 'invalid_grant', $result->get_error_message() );
		}

		/*
		 * If the refreshed token was DPoP-bound, verify the refresh request
		 * used the same key. Token::refresh() preserves the original dpop_jkt.
		 */
		if ( 'DPoP' === $result['token_type'] ) {
			if ( ! $dpop_jkt ) {
				Token::revoke( $result['access_token'] );
				return $this->token_error( 'invalid_dpop_proof', 'DPoP proof required when refreshing a DPoP-bound token.' );
			}

			// Verify the proof was signed with the same key that was originally bound.
			$new_token = Token::validate( $result['access_token'] );

			if ( ! \is_wp_error( $new_token ) && ! hash_equals( $new_token->get_dpop_jkt(), $dpop_jkt ) ) {
				Token::revoke( $result['access_token'] );
				return $this->token_error( 'invalid_dpop_proof', 'DPoP key does not match the original token binding.' );
			}
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

		// Per RFC 7009, always return 200 even if token doesn't exist.
		Token::revoke( $token );

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
	 * Handle dynamic client registration (POST /oauth/clients).
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function register_client( \WP_REST_Request $request ) {
		/**
		 * Filters whether RFC 7591 dynamic client registration is allowed.
		 *
		 * Enabled by default so C2S clients can register on the fly.
		 * Return false to restrict registration to pre-configured clients only.
		 *
		 * @param bool $allowed Whether dynamic registration is allowed. Default true.
		 */
		if ( ! \apply_filters( 'activitypub_allow_dynamic_client_registration', true ) ) {
			return new \WP_Error(
				'activitypub_registration_disabled',
				\__( 'Dynamic client registration is not allowed.', 'activitypub' ),
				array( 'status' => 403 )
			);
		}

		$client_name   = $request->get_param( 'client_name' );
		$redirect_uris = $request->get_param( 'redirect_uris' );
		$client_uri    = $request->get_param( 'client_uri' );
		$scope         = $request->get_param( 'scope' );

		$result = Client::register(
			array(
				'name'          => $client_name,
				'redirect_uris' => $redirect_uris,
				'description'   => $client_uri ?? '',
				'is_public'     => true, // Dynamic clients are always public.
				'scopes'        => $scope ? Scope::parse( $scope ) : Scope::ALL,
			)
		);

		if ( \is_wp_error( $result ) ) {
			return $result;
		}

		// RFC 7591 response format.
		$response = array(
			'client_id'                  => $result['client_id'],
			'client_name'                => $client_name,
			'redirect_uris'              => $redirect_uris,
			'token_endpoint_auth_method' => 'none',
		);

		if ( isset( $result['client_secret'] ) ) {
			$response['client_secret'] = $result['client_secret'];
		}

		return new \WP_REST_Response( $response, 201 );
	}

	/**
	 * Get OAuth server metadata.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_metadata() {
		return new \WP_REST_Response(
			OAuth_Server::get_metadata(),
			200,
			array( 'Content-Type' => 'application/json' )
		);
	}

	/**
	 * Get arguments for authorize endpoint.
	 *
	 * @return array Validation schema.
	 */
	private function get_authorize_args() {
		return array(
			'response_type'         => array(
				'description' => 'OAuth response type (must be "code").',
				'type'        => 'string',
				'required'    => true,
				'enum'        => array( 'code' ),
			),
			'client_id'             => array(
				'description' => 'The OAuth client identifier.',
				'type'        => 'string',
				'required'    => true,
			),
			'redirect_uri'          => array(
				'description' => 'The URI to redirect to after authorization.',
				'type'        => 'string',
				'format'      => 'uri',
				'required'    => true,
			),
			'scope'                 => array(
				'description' => 'Space-separated list of requested scopes.',
				'type'        => 'string',
			),
			'state'                 => array(
				'description' => 'Opaque value for CSRF protection.',
				'type'        => 'string',
			),
			'code_challenge'        => array(
				'description' => 'PKCE code challenge (recommended).',
				'type'        => 'string',
			),
			'code_challenge_method' => array(
				'description' => 'PKCE code challenge method.',
				'type'        => 'string',
				'enum'        => array( 'S256', 'plain' ),
				'default'     => 'S256',
			),
		);
	}

	/**
	 * Get arguments for token endpoint.
	 *
	 * @return array Validation schema.
	 */
	private function get_token_args() {
		return array(
			'grant_type'    => array(
				'description' => 'The grant type.',
				'type'        => 'string',
				'required'    => true,
				'enum'        => array( 'authorization_code', 'refresh_token' ),
			),
			'client_id'     => array(
				'description' => 'The OAuth client identifier.',
				'type'        => 'string',
				'required'    => true,
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
				'description' => 'The redirect URI (must match original for authorization_code grant).',
				'type'        => 'string',
				'format'      => 'uri',
			),
			'code_verifier' => array(
				'description' => 'PKCE code verifier.',
				'type'        => 'string',
			),
			'refresh_token' => array(
				'description' => 'The refresh token (for refresh_token grant).',
				'type'        => 'string',
			),
		);
	}

	/**
	 * Create a token error response.
	 *
	 * @param string $error             Error code.
	 * @param string $error_description Error description.
	 * @return \WP_REST_Response
	 */
	private function token_error( $error, $error_description ) {
		return new \WP_REST_Response(
			array(
				'error'             => $error,
				'error_description' => $error_description,
			),
			400,
			array( 'Content-Type' => 'application/json' )
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

	/**
	 * Validate a DPoP proof for a token endpoint request (RFC 9449).
	 *
	 * If no DPoP header is present, returns null (opt-in by client).
	 * If present and valid, returns the JWK thumbprint string.
	 * If present and invalid, returns a WP_Error.
	 *
	 * @since unreleased
	 *
	 * @return string|null|\WP_Error JWK thumbprint, null if no DPoP, or WP_Error.
	 */
	private function validate_dpop_for_token_request() {
		$dpop_proof = DPoP::get_proof_from_request();

		if ( ! $dpop_proof ) {
			return null;
		}

		$result = DPoP::validate_proof(
			$dpop_proof,
			DPoP::get_request_method(),
			DPoP::get_request_uri()
		);

		if ( \is_wp_error( $result ) ) {
			return $result;
		}

		return $result['jkt'];
	}

	/**
	 * Redirect with an OAuth error.
	 *
	 * @param string $redirect_uri The redirect URI.
	 * @param string $error        Error code.
	 * @param string $description  Error description.
	 * @param string $state        The state parameter.
	 * @return \WP_REST_Response
	 */
	private function redirect_with_error( $redirect_uri, $error, $description, $state = null ) {
		$params = array(
			'error'             => $error,
			'error_description' => $description,
		);

		if ( $state ) {
			$params['state'] = $state;
		}

		$redirect_url = \add_query_arg( $params, $redirect_uri );

		return new \WP_REST_Response(
			null,
			302,
			array( 'Location' => $redirect_url )
		);
	}
}
