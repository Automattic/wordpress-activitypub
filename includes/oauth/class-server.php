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

		// Add CORS headers to OAuth endpoints.
		\add_filter( 'rest_post_dispatch', array( self::class, 'add_cors_headers' ), 10, 3 );

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
		/*
		 * Reset OAuth state at the start of each authentication to prevent
		 * leaking state between multiple REST dispatches in the same process.
		 */
		self::$current_token = null;

		// If another authentication method already succeeded, use that.
		if ( true === $result || \is_user_logged_in() ) {
			return $result;
		}

		// If a previous auth filter returned an error, respect it.
		if ( \is_wp_error( $result ) ) {
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
		/**
		 * Filter to override OAuth permission check.
		 *
		 * Useful for testing. Return true to bypass OAuth check, false to continue.
		 *
		 * @param bool|null        $result  The permission result. Null to continue normal check.
		 * @param \WP_REST_Request $request The REST request.
		 * @param string|null      $scope   Required scope.
		 */
		$override = \apply_filters( 'activitypub_oauth_check_permission', null, $request, $scope );
		if ( null !== $override ) {
			return $override;
		}

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
	 * Run cleanup tasks for OAuth data.
	 */
	public static function cleanup() {
		// Clean up expired tokens.
		Token::cleanup_expired();

		// Clean up expired authorization codes.
		Authorization_Code::cleanup();
	}

	/**
	 * Add CORS headers to C2S endpoint responses.
	 *
	 * Enables browser-based C2S clients to interact with OAuth and C2S endpoints.
	 *
	 * @param \WP_REST_Response $response The response object.
	 * @param \WP_REST_Server   $server   The REST server instance.
	 * @param \WP_REST_Request  $request  The request object.
	 * @return \WP_REST_Response The modified response.
	 */
	public static function add_cors_headers( $response, $server, $request ) {
		$route = $request->get_route();

		// Check if route needs CORS headers.
		if ( ! self::route_needs_cors( $route ) ) {
			return $response;
		}

		$response->header( 'Access-Control-Allow-Origin', '*' );
		$response->header( 'Access-Control-Allow-Methods', 'GET, POST, OPTIONS' );
		$response->header( 'Access-Control-Allow-Headers', 'Content-Type, Authorization' );

		return $response;
	}

	/**
	 * Check if a route needs CORS headers.
	 *
	 * @param string $route The REST API route.
	 * @return bool True if the route needs CORS headers.
	 */
	private static function route_needs_cors( $route ) {
		$namespace = '/' . ACTIVITYPUB_REST_NAMESPACE;

		// OAuth endpoints (except authorize which redirects).
		if ( 0 === strpos( $route, $namespace . '/oauth' ) ) {
			return false === strpos( $route, '/oauth/authorize' );
		}

		// Proxy endpoint for fetching remote objects.
		if ( $namespace . '/proxy' === $route ) {
			return true;
		}

		// C2S outbox and inbox endpoints.
		if ( \str_ends_with( $route, '/outbox' ) || \str_ends_with( $route, '/inbox' ) ) {
			return true;
		}

		return false;
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
			'introspection_endpoint'                => $base_url . 'oauth/introspect',
			'registration_endpoint'                 => $base_url . 'oauth/clients',
			'scopes_supported'                      => Scope::ALL,
			'response_types_supported'              => array( 'code' ),
			'response_modes_supported'              => array( 'query' ),
			'grant_types_supported'                 => array( 'authorization_code', 'refresh_token' ),
			'token_endpoint_auth_methods_supported' => array( 'none', 'client_secret_post' ),
			'introspection_endpoint_auth_methods_supported' => array( 'bearer' ),
			'code_challenge_methods_supported'      => array( 'S256', 'plain' ),
			'service_documentation'                 => 'https://github.com/swicg/activitypub-api',
		);
	}

	/**
	 * Handle OAuth authorization consent page via wp-login.php.
	 *
	 * This is triggered by wp-login.php?action=activitypub_authorize
	 */
	public static function login_form_authorize() {
		// Require user to be logged in.
		if ( ! \is_user_logged_in() ) {
			\auth_redirect();
		}

		$request_method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';

		if ( 'GET' === $request_method ) {
			self::render_authorize_form();
		} elseif ( 'POST' === $request_method ) {
			self::process_authorize_form();
		}

		exit;
	}

	/**
	 * Render the OAuth authorization consent form.
	 */
	private static function render_authorize_form() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Initial form display, nonce checked on POST.
		$client_id             = isset( $_GET['client_id'] ) ? \sanitize_text_field( \wp_unslash( $_GET['client_id'] ) ) : '';
		$redirect_uri          = isset( $_GET['redirect_uri'] ) ? \esc_url_raw( \wp_unslash( $_GET['redirect_uri'] ) ) : '';
		$scope                 = isset( $_GET['scope'] ) ? \sanitize_text_field( \wp_unslash( $_GET['scope'] ) ) : '';
		$state                 = isset( $_GET['state'] ) ? \sanitize_text_field( \wp_unslash( $_GET['state'] ) ) : '';
		$code_challenge        = isset( $_GET['code_challenge'] ) ? \sanitize_text_field( \wp_unslash( $_GET['code_challenge'] ) ) : '';
		$code_challenge_method = isset( $_GET['code_challenge_method'] ) ? \sanitize_text_field( \wp_unslash( $_GET['code_challenge_method'] ) ) : 'S256';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		// Validate client.
		$client = Client::get( $client_id );
		if ( \is_wp_error( $client ) ) {
			\wp_die(
				\esc_html( $client->get_error_message() ),
				\esc_html__( 'Authorization Error', 'activitypub' ),
				array( 'response' => 404 )
			);
		}

		// Validate redirect URI.
		if ( ! $client->is_valid_redirect_uri( $redirect_uri ) ) {
			\wp_die(
				\esc_html__( 'Invalid redirect URI for this client.', 'activitypub' ),
				\esc_html__( 'Authorization Error', 'activitypub' ),
				array( 'response' => 400 )
			);
		}

		// These variables are used in the template.
		$current_user = \wp_get_current_user(); // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		$scopes       = Scope::validate( Scope::parse( $scope ) ); // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		$client_name  = $client->get_name(); // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable

		// Build form action URL.
		// phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		$form_url = \add_query_arg(
			array(
				'action'                => 'activitypub_authorize',
				'client_id'             => $client_id,
				'redirect_uri'          => $redirect_uri,
				'scope'                 => $scope,
				'state'                 => $state,
				'code_challenge'        => $code_challenge,
				'code_challenge_method' => $code_challenge_method,
			),
			\wp_login_url()
		);

		// Include the template.
		include ACTIVITYPUB_PLUGIN_DIR . 'templates/oauth-authorize.php';
	}

	/**
	 * Process the OAuth authorization consent form submission.
	 */
	private static function process_authorize_form() {
		// Verify nonce.
		if ( ! isset( $_POST['_wpnonce'] ) || ! \wp_verify_nonce( \sanitize_text_field( \wp_unslash( $_POST['_wpnonce'] ) ), 'activitypub_oauth_authorize' ) ) {
			\wp_die(
				\esc_html__( 'Security check failed. Please try again.', 'activitypub' ),
				\esc_html__( 'Authorization Error', 'activitypub' ),
				array( 'response' => 403 )
			);
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified above.
		$client_id             = isset( $_POST['client_id'] ) ? \sanitize_text_field( \wp_unslash( $_POST['client_id'] ) ) : '';
		$redirect_uri          = isset( $_POST['redirect_uri'] ) ? \esc_url_raw( \wp_unslash( $_POST['redirect_uri'] ) ) : '';
		$scope                 = isset( $_POST['scope'] ) ? \sanitize_text_field( \wp_unslash( $_POST['scope'] ) ) : '';
		$state                 = isset( $_POST['state'] ) ? \sanitize_text_field( \wp_unslash( $_POST['state'] ) ) : '';
		$code_challenge        = isset( $_POST['code_challenge'] ) ? \sanitize_text_field( \wp_unslash( $_POST['code_challenge'] ) ) : '';
		$code_challenge_method = isset( $_POST['code_challenge_method'] ) ? \sanitize_text_field( \wp_unslash( $_POST['code_challenge_method'] ) ) : 'S256';
		$approve               = isset( $_POST['approve'] );
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		// User denied authorization.
		if ( ! $approve ) {
			$error_url = \add_query_arg(
				array(
					'error'             => 'access_denied',
					'error_description' => \rawurlencode( 'The user denied the authorization request.' ),
					'state'             => $state,
				),
				$redirect_uri
			);

			/*
			 * wp_safe_redirect() blocks external domains, but OAuth redirect_uris
			 * are always external. The URI is pre-validated against the registered
			 * client's redirect_uris by render_authorize_form().
			 */
			\wp_redirect( $error_url ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
			exit;
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
			$error_url = \add_query_arg(
				array(
					'error'             => 'server_error',
					'error_description' => \rawurlencode( $code->get_error_message() ),
					'state'             => $state,
				),
				$redirect_uri
			);
			// See comment above regarding wp_redirect vs wp_safe_redirect.
			\wp_redirect( $error_url ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
			exit;
		}

		// Redirect to client with authorization code.
		$success_url = \add_query_arg(
			array(
				'code'  => $code,
				'state' => $state,
			),
			$redirect_uri
		);
		\wp_redirect( $success_url ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- Redirecting to external client.
		exit;
	}
}
