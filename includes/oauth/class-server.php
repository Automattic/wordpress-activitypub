<?php
/**
 * OAuth 2.0 Server for ActivityPub C2S.
 *
 * @package Activitypub
 */

namespace Activitypub\OAuth;

use Activitypub\Sanitize;

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
		/*
		 * Reset OAuth state at the start of each authentication to prevent
		 * leaking state between multiple REST dispatches in the same process.
		 */
		self::$current_token = null;

		$token = self::get_bearer_token();

		if ( ! $token ) {
			// No Bearer token — respect errors from earlier auth filters.
			return $result;
		}

		$validated = Token::validate( $token );

		if ( \is_wp_error( $validated ) ) {
			return $validated;
		}

		self::$current_token = $validated;
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
		/*
		 * Only wp_unslash() is used here — sanitize_text_field() could
		 * corrupt opaque bearer tokens by stripping characters.
		 */

		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Opaque auth token, must not be altered.
		if ( ! empty( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
			return \wp_unslash( $_SERVER['HTTP_AUTHORIZATION'] );
		}

		if ( ! empty( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ) {
			return \wp_unslash( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] );
		}
		// phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		// Fallback: read from Apache's own header API (case-insensitive).
		if ( ! function_exists( 'apache_request_headers' ) ) {
			return null;
		}

		$headers = apache_request_headers();

		foreach ( $headers as $key => $value ) {
			if ( 'authorization' === strtolower( $key ) ) {
				return $value;
			}
		}

		return null;
	}

	/**
	 * Verify PKCE code_verifier against code_challenge.
	 *
	 * @param string $code_verifier  The PKCE code verifier.
	 * @param string $code_challenge The stored code challenge.
	 * @param string $method         The challenge method (only S256 is supported).
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

		if ( ! self::is_oauth_request() ) {
			return new \WP_Error(
				'activitypub_oauth_required',
				\__( 'OAuth authentication required.', 'activitypub' ),
				array( 'status' => 401 )
			);
		}

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
			'token_endpoint_auth_methods_supported' => array( 'none', 'client_secret_post', 'client_secret_basic' ),
			'introspection_endpoint_auth_methods_supported' => array( 'bearer' ),
			'code_challenge_methods_supported'      => array( 'S256' ),
			'service_documentation'                 => 'https://github.com/swicg/activitypub-api',
			'client_id_metadata_document_supported' => true,
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

		$request_method = isset( $_SERVER['REQUEST_METHOD'] ) ? \sanitize_text_field( \wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';

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

		// Check for error token (redirected from REST authorization endpoint).
		if ( isset( $_GET['auth_error'] ) ) {
			$token         = \sanitize_text_field( \wp_unslash( $_GET['auth_error'] ) );
			$error_message = \get_transient( 'ap_oauth_err_' . $token ); // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- Used in template.
			\delete_transient( 'ap_oauth_err_' . $token );

			if ( ! $error_message ) {
				$error_message = \__( 'An authorization error occurred. Please try again.', 'activitypub' ); // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- Used in template.
			}

			include ACTIVITYPUB_PLUGIN_DIR . 'templates/oauth-error.php';
			return;
		}

		$authorize_params = array(
			'client_id'             => isset( $_GET['client_id'] ) ? \sanitize_text_field( \wp_unslash( $_GET['client_id'] ) ) : '',
			'redirect_uri'          => isset( $_GET['redirect_uri'] ) ? Sanitize::redirect_uri( \wp_unslash( $_GET['redirect_uri'] ) ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized via Sanitize::redirect_uri().
			'scope'                 => isset( $_GET['scope'] ) ? \sanitize_text_field( \wp_unslash( $_GET['scope'] ) ) : '',
			'state'                 => isset( $_GET['state'] ) ? \wp_unslash( $_GET['state'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- OAuth state is opaque; must be round-tripped exactly.
			'code_challenge'        => isset( $_GET['code_challenge'] ) ? \sanitize_text_field( \wp_unslash( $_GET['code_challenge'] ) ) : '',
			'code_challenge_method' => isset( $_GET['code_challenge_method'] ) ? \sanitize_text_field( \wp_unslash( $_GET['code_challenge_method'] ) ) : 'S256',
		);
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		// Validate client.
		$client = Client::get( $authorize_params['client_id'] );
		if ( \is_wp_error( $client ) ) {
			$error_message = $client->get_error_message(); // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- Used in template.
			include ACTIVITYPUB_PLUGIN_DIR . 'templates/oauth-error.php';
			return;
		}

		// Validate redirect URI.
		if ( ! $client->is_valid_redirect_uri( $authorize_params['redirect_uri'] ) ) {
			$error_message = \__( 'Invalid redirect URI for this client.', 'activitypub' ); // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- Used in template.
			include ACTIVITYPUB_PLUGIN_DIR . 'templates/oauth-error.php';
			return;
		}

		// Use the canonical client ID (may differ from the raw input for discovered clients).
		$authorize_params['client_id'] = $client->get_client_id();

		// These variables are used in the template.
		$current_user = \wp_get_current_user(); // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		$scopes       = Scope::validate( Scope::parse( $authorize_params['scope'] ) ); // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable

		// Build form action URL.
		// phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		$form_url = \add_query_arg(
			array_merge( array( 'action' => 'activitypub_authorize' ), $authorize_params ),
			\wp_login_url()
		);

		// Include the template.
		include ACTIVITYPUB_PLUGIN_DIR . 'templates/oauth-authorize.php'; // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- $authorize_params used in template.
	}

	/**
	 * Process the OAuth authorization consent form submission.
	 */
	private static function process_authorize_form() {
		// Verify nonce.
		if ( ! isset( $_POST['_wpnonce'] ) || ! \wp_verify_nonce( \sanitize_text_field( \wp_unslash( $_POST['_wpnonce'] ) ), 'activitypub_oauth_authorize' ) ) {
			$error_message = \__( 'Security check failed. Please try again.', 'activitypub' ); // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- Used in template.
			include ACTIVITYPUB_PLUGIN_DIR . 'templates/oauth-error.php';
			exit;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified above.
		$client_id             = isset( $_POST['client_id'] ) ? \sanitize_text_field( \wp_unslash( $_POST['client_id'] ) ) : '';
		$redirect_uri          = isset( $_POST['redirect_uri'] ) ? Sanitize::redirect_uri( \wp_unslash( $_POST['redirect_uri'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized via Sanitize::redirect_uri().
		$scope                 = isset( $_POST['scope'] ) ? \sanitize_text_field( \wp_unslash( $_POST['scope'] ) ) : '';
		$state                 = isset( $_POST['state'] ) ? \wp_unslash( $_POST['state'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- OAuth state is opaque; must be round-tripped exactly.
		$code_challenge        = isset( $_POST['code_challenge'] ) ? \sanitize_text_field( \wp_unslash( $_POST['code_challenge'] ) ) : '';
		$code_challenge_method = isset( $_POST['code_challenge_method'] ) ? \sanitize_text_field( \wp_unslash( $_POST['code_challenge_method'] ) ) : 'S256';
		$approve               = isset( $_POST['approve'] );
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		// Only S256 is supported; normalize empty/missing values and reject anything else.
		if ( empty( $code_challenge_method ) ) {
			$code_challenge_method = 'S256';
		} elseif ( 'S256' !== $code_challenge_method ) {
			$error_message = \__( 'Only S256 is supported as PKCE code challenge method.', 'activitypub' ); // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- Used in template.
			include ACTIVITYPUB_PLUGIN_DIR . 'templates/oauth-error.php';
			exit;
		}

		// Re-validate client and redirect URI (form fields could be tampered with).
		$client = Client::get( $client_id );

		if ( \is_wp_error( $client ) ) {
			$error_message = $client->get_error_message(); // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- Used in template.
			include ACTIVITYPUB_PLUGIN_DIR . 'templates/oauth-error.php';
			exit;
		}

		if ( ! $client->is_valid_redirect_uri( $redirect_uri ) ) {
			$error_message = \__( 'Invalid redirect URI for this client.', 'activitypub' ); // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- Used in template.
			include ACTIVITYPUB_PLUGIN_DIR . 'templates/oauth-error.php';
			exit;
		}

		// User denied authorization.
		if ( ! $approve ) {
			self::redirect_to_client(
				$redirect_uri,
				array(
					'error'             => 'access_denied',
					'error_description' => 'The user denied the authorization request.',
					'state'             => $state,
				)
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
			self::redirect_to_client(
				$redirect_uri,
				array(
					'error'             => 'server_error',
					'error_description' => $code->get_error_message(),
					'state'             => $state,
				)
			);
		}

		self::redirect_to_client(
			$redirect_uri,
			array(
				'code'  => $code,
				'state' => $state,
			)
		);
	}

	/**
	 * Redirect to an OAuth client's redirect URI with query parameters.
	 *
	 * Uses a manual Location header because wp_redirect() strips custom
	 * URI schemes used by native/mobile apps (RFC 8252 Section 7.1).
	 * The URI is pre-validated against the registered client's redirect_uris
	 * before this method is called.
	 *
	 * @param string $redirect_uri The client's redirect URI.
	 * @param array  $params       Query parameters to append.
	 */
	private static function redirect_to_client( $redirect_uri, $params ) {
		$url = Sanitize::redirect_uri( \add_query_arg( $params, $redirect_uri ) );

		\nocache_headers();
		header( 'Location: ' . $url, true, 303 );
		exit;
	}
}
