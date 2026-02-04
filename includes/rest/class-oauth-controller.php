<?php
/**
 * OAuth 2.0 REST Controller for ActivityPub C2S.
 *
 * @package Activitypub
 */

namespace Activitypub\Rest;

use Activitypub\OAuth\Authorization_Code;
use Activitypub\OAuth\Client;
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

		// Revocation endpoint.
		\register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/revoke',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'revoke' ),
					'permission_callback' => '__return_true',
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
					'permission_callback' => '__return_true',
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

		// Check for PKCE (required).
		$code_challenge = $request->get_param( 'code_challenge' );
		if ( empty( $code_challenge ) ) {
			return $this->redirect_with_error(
				$redirect_uri,
				'invalid_request',
				'PKCE code_challenge is required.',
				$state
			);
		}

		// Redirect to wp-login.php with action=activitypub_authorize.
		// This uses WordPress's login_form_{action} hook for proper cookie auth.
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
	 * Handle token request (POST /oauth/token).
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function token( \WP_REST_Request $request ) {
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

		if ( empty( $code_verifier ) ) {
			return $this->token_error( 'invalid_request', 'PKCE code_verifier is required.' );
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

		return new \WP_REST_Response( $response, 200 );
	}

	/**
	 * Handle dynamic client registration (POST /oauth/clients).
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function register_client( \WP_REST_Request $request ) {
		// Check if dynamic registration is allowed.
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
				'description' => 'PKCE code challenge.',
				'type'        => 'string',
				'required'    => true,
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

	/**
	 * Render the consent page HTML.
	 *
	 * @param Client   $client  The OAuth client.
	 * @param array    $scopes  Requested scopes.
	 * @param \WP_User $user    The current user.
	 * @param array    $params  Request parameters.
	 * @param string   $nonce   Security nonce.
	 * @return string HTML content.
	 */
	private function render_consent_page( $client, $scopes, $user, $params, $nonce ) {
		$action_url = \rest_url( $this->namespace . '/' . $this->rest_base . '/authorize' );
		$site_name  = \get_bloginfo( 'name' );

		ob_start();
		?>
		<!DOCTYPE html>
		<html <?php language_attributes(); ?>>
		<head>
			<meta charset="<?php bloginfo( 'charset' ); ?>">
			<meta name="viewport" content="width=device-width, initial-scale=1">
			<title><?php esc_html_e( 'Authorize Application', 'activitypub' ); ?> - <?php echo esc_html( $site_name ); ?></title>
			<style>
				body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; background: #f0f0f1; margin: 0; padding: 20px; }
				.oauth-container { max-width: 400px; margin: 50px auto; background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.13); }
				h1 { font-size: 24px; margin: 0 0 20px; }
				.client-name { font-weight: 600; color: #1d2327; }
				.user-info { background: #f6f7f7; padding: 15px; border-radius: 4px; margin: 20px 0; }
				.scopes { margin: 20px 0; }
				.scopes h3 { font-size: 14px; margin: 0 0 10px; }
				.scopes ul { margin: 0; padding: 0 0 0 20px; }
				.scopes li { margin: 5px 0; color: #50575e; }
				.buttons { display: flex; gap: 10px; margin-top: 30px; }
				.button { flex: 1; padding: 12px 20px; border: none; border-radius: 4px; font-size: 14px; cursor: pointer; text-align: center; }
				.button-primary { background: #2271b1; color: #fff; }
				.button-primary:hover { background: #135e96; }
				.button-secondary { background: #f0f0f1; color: #50575e; border: 1px solid #c3c4c7; }
				.button-secondary:hover { background: #e0e0e0; }
			</style>
		</head>
		<body>
			<div class="oauth-container">
				<h1><?php esc_html_e( 'Authorize Application', 'activitypub' ); ?></h1>

				<p>
					<?php
					printf(
						/* translators: %s: client application name */
						esc_html__( '%s would like to access your account.', 'activitypub' ),
						'<span class="client-name">' . esc_html( $client->get_name() ) . '</span>'
					);
					?>
				</p>

				<div class="user-info">
					<?php
					printf(
						/* translators: %s: username */
						esc_html__( 'Logged in as: %s', 'activitypub' ),
						'<strong>' . esc_html( $user->display_name ) . '</strong>'
					);
					?>
				</div>

				<?php if ( ! empty( $scopes ) ) : ?>
				<div class="scopes">
					<h3><?php esc_html_e( 'This application will be able to:', 'activitypub' ); ?></h3>
					<ul>
						<?php foreach ( $scopes as $scope ) : ?>
							<li><?php echo esc_html( Scope::get_description( $scope ) ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
				<?php endif; ?>

				<form method="post" action="<?php echo esc_url( $action_url ); ?>">
					<?php foreach ( $params as $key => $value ) : ?>
						<?php if ( ! in_array( $key, array( 'approve', '_wpnonce' ), true ) ) : ?>
							<input type="hidden" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ); ?>">
						<?php endif; ?>
					<?php endforeach; ?>
					<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( $nonce ); ?>">

					<div class="buttons">
						<button type="submit" name="approve" value="0" class="button button-secondary">
							<?php esc_html_e( 'Deny', 'activitypub' ); ?>
						</button>
						<button type="submit" name="approve" value="1" class="button button-primary">
							<?php esc_html_e( 'Authorize', 'activitypub' ); ?>
						</button>
					</div>
				</form>
			</div>
		</body>
		</html>
		<?php
		return ob_get_clean();
	}
}
