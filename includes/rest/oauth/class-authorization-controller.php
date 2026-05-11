<?php
/**
 * OAuth 2.0 Authorization REST Controller.
 *
 * @package Activitypub
 */

namespace Activitypub\Rest\OAuth;

use Activitypub\OAuth\Authorization_Code;
use Activitypub\OAuth\Client;
use Activitypub\OAuth\Scope;

use function Activitypub\get_client_ip;

/**
 * Authorization_Controller class for handling the OAuth 2.0 authorization endpoint.
 *
 * Implements:
 * - Authorization endpoint (GET/POST /oauth/authorize)
 *
 * @since 8.1.0
 */
class Authorization_Controller extends \WP_REST_Controller {
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
					'args'                => array(
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
							'description' => 'The URI to redirect to after authorization. Supports custom URI schemes for native apps.',
							'type'        => 'string',
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
							'enum'        => array( 'S256' ),
							'default'     => 'S256',
						),
					),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'authorize_submit' ),
					'permission_callback' => array( $this, 'authorize_submit_permissions_check' ),
					'args'                => array(
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
							'description' => 'The URI to redirect to after authorization. Supports custom URI schemes for native apps.',
							'type'        => 'string',
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
							'enum'        => array( 'S256' ),
							'default'     => 'S256',
						),
						'approve'               => array(
							'description' => 'Whether the user approved the authorization.',
							'type'        => 'boolean',
							'required'    => true,
						),
						'_wpnonce'              => array(
							'description' => 'WordPress nonce for CSRF protection.',
							'type'        => 'string',
							'required'    => true,
						),
					),
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
		// Rate-limit authorization requests to prevent abuse (max 20 per minute per IP).
		$ip = get_client_ip();
		if ( '' === $ip ) {
			return new \WP_Error(
				'activitypub_rate_limit',
				\__( 'Too many authorization requests. Please try again later.', 'activitypub' ),
				array( 'status' => 429 )
			);
		}
		$transient_key = 'ap_oauth_auth_' . \md5( $ip );
		$count         = (int) \get_transient( $transient_key );

		if ( $count >= 20 ) {
			return new \WP_Error(
				'activitypub_rate_limit',
				\__( 'Too many authorization requests. Please try again later.', 'activitypub' ),
				array( 'status' => 429 )
			);
		}

		\set_transient( $transient_key, $count + 1, MINUTE_IN_SECONDS );

		$client_id     = $request->get_param( 'client_id' );
		$redirect_uri  = $request->get_param( 'redirect_uri' );
		$response_type = $request->get_param( 'response_type' );
		$scope         = $request->get_param( 'scope' );
		$state         = $request->get_param( 'state' );

		// Validate client.
		$client = Client::get( $client_id );
		if ( \is_wp_error( $client ) ) {
			return $this->error_page( $client );
		}

		// Validate redirect URI.
		if ( ! $client->is_valid_redirect_uri( $redirect_uri ) ) {
			return $this->error_page(
				new \WP_Error(
					'activitypub_invalid_redirect_uri',
					\__( 'Invalid redirect URI for this client.', 'activitypub' ),
					array( 'status' => 400 )
				)
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
			return $this->error_page( $client );
		}

		if ( ! $client->is_valid_redirect_uri( $redirect_uri ) ) {
			return $this->error_page(
				new \WP_Error(
					'activitypub_invalid_redirect_uri',
					\__( 'Invalid redirect URI for this client.', 'activitypub' ),
					array( 'status' => 400 )
				)
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
	 * Redirect to wp-login.php with a styled error message.
	 *
	 * These errors occur before a valid redirect URI is confirmed, so we
	 * cannot safely redirect back to the client. Instead, redirect to
	 * wp-login.php where the error is rendered using login_header/login_footer
	 * for a consistent, user-friendly appearance.
	 *
	 * The error message is stored in a short-lived transient (5 minutes)
	 * keyed by a random token. Only the opaque token is passed in the URL,
	 * preventing social-engineering attacks where an attacker crafts a URL
	 * with arbitrary error text displayed inside WordPress login chrome.
	 *
	 * @since 8.1.0
	 *
	 * @param \WP_Error $error The error to display.
	 * @return \WP_REST_Response Redirect response to wp-login.php.
	 */
	private function error_page( $error ) {
		$token = \wp_generate_password( 20, false );
		\set_transient( 'ap_oauth_err_' . $token, $error->get_error_message(), 5 * MINUTE_IN_SECONDS );

		$login_url = \add_query_arg(
			array(
				'action'     => 'activitypub_authorize',
				'auth_error' => $token,
			),
			\wp_login_url()
		);

		return new \WP_REST_Response(
			null,
			302,
			array( 'Location' => $login_url )
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
}
