<?php
/**
 * Server REST-Class file.
 *
 * @package Activitypub
 */

namespace Activitypub\Rest;

/**
 * ActivityPub Server REST-Class.
 *
 * @author Django Doucet
 *
 * @see https://www.w3.org/TR/activitypub/#security-verification
 */
class Server {
	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		\add_filter( 'rest_pre_dispatch', array( self::class, 'maybe_add_actor_from_signature' ), 10, 3 );
		\add_filter( 'rest_request_before_callbacks', array( self::class, 'validate_requests' ), 9, 3 );
		\add_filter( 'rest_request_parameter_order', array( self::class, 'request_parameter_order' ), 10, 2 );

		\add_filter( 'rest_post_dispatch', array( self::class, 'filter_output' ), 10, 3 );
		\add_filter( 'rest_post_dispatch', array( self::class, 'add_cors_headers' ), 10, 3 );
		\add_filter( 'rest_allowed_cors_headers', array( self::class, 'allow_cors_headers' ), 10, 2 );
	}

	/**
	 * Callback function to validate incoming ActivityPub requests
	 *
	 * @param \WP_REST_Response|\WP_HTTP_Response|\WP_Error|mixed $response Result to send to the client.
	 *                                                                      Usually a WP_REST_Response or WP_Error.
	 * @param array                                               $handler  Route handler used for the request.
	 * @param \WP_REST_Request                                    $request  Request used to generate the response.
	 *
	 * @return mixed|\WP_Error The response, error, or modified response.
	 */
	public static function validate_requests( $response, $handler, $request ) {
		if ( 'HEAD' === $request->get_method() ) {
			return $response;
		}

		$route = $request->get_route();

		if (
			\is_wp_error( $response ) ||
			! \str_starts_with( $route, '/' . ACTIVITYPUB_REST_NAMESPACE )
		) {
			return $response;
		}

		$params = $request->get_json_params();

		// Type is required for ActivityPub requests, so it fail later in the process.
		if ( ! isset( $params['type'] ) ) {
			return $response;
		}

		if (
			ACTIVITYPUB_DISABLE_INCOMING_INTERACTIONS &&
			in_array( $params['type'], array( 'Create', 'Like', 'Announce' ), true )
		) {
			return new \WP_Error(
				'activitypub_server_does_not_accept_incoming_interactions',
				\__( 'This server does not accept incoming interactions.', 'activitypub' ),
				// We have to use a 2XX status code here, because otherwise the response will be
				// treated as an error and Mastodon might block this WordPress instance.
				array( 'status' => 202 )
			);
		}

		return $response;
	}

	/**
	 * Modify the parameter priority order for a REST API request.
	 *
	 * @param string[]         $order   Array of types to check, in order of priority.
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @return string[] The modified order of types to check.
	 */
	public static function request_parameter_order( $order, $request ) {
		$route = $request->get_route();

		// Check if it is an activitypub request and exclude webfinger and nodeinfo endpoints.
		if ( ! \str_starts_with( $route, '/' . ACTIVITYPUB_REST_NAMESPACE ) ) {
			return $order;
		}

		$method = $request->get_method();

		if ( \WP_REST_Server::CREATABLE !== $method ) {
			return $order;
		}

		return array(
			'JSON',
			'POST',
			'URL',
			'defaults',
		);
	}

	/**
	 * Backfill a missing `actor` on incoming inbox activities from the HTTP signature.
	 *
	 * Some implementations convey the sending actor only through the HTTP signature
	 * and omit the `actor` property from the activity body. Mastodon does this for
	 * FeatureRequest activities (FEP-7aa9), for example. Because our inbox routes
	 * require `actor`, those activities are rejected during parameter validation
	 * before they can be stored or handed to a handler.
	 *
	 * The signature is the authoritative sender identity anyway, so derive the actor
	 * from the keyId and inject it into the request body. Validation, signature
	 * verification, and the handlers then see a normal `actor`. This is purely
	 * additive: a well-formed activity already carries an `actor` and is left
	 * untouched, and the injected value still has to survive signature verification.
	 *
	 * Runs on `rest_pre_dispatch` because that is the only hook that fires before
	 * required-parameter validation.
	 *
	 * @since unreleased
	 *
	 * @param mixed            $result  Response to replace the request with, or null to continue.
	 * @param \WP_REST_Server  $server  Server instance.
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @return mixed The unmodified `$result`.
	 */
	public static function maybe_add_actor_from_signature( $result, $server, $request ) {
		// Respect an earlier short-circuit.
		if ( null !== $result ) {
			return $result;
		}

		if ( \WP_REST_Server::CREATABLE !== $request->get_method() ) {
			return $result;
		}

		$route = $request->get_route();
		if (
			! \str_starts_with( $route, '/' . ACTIVITYPUB_REST_NAMESPACE ) ||
			! \str_ends_with( $route, '/inbox' )
		) {
			return $result;
		}

		$json = $request->get_json_params();
		if ( ! \is_array( $json ) || ! empty( $json['actor'] ) ) {
			return $result;
		}

		$key_id = self::get_key_id_from_request( $request );
		if ( ! $key_id ) {
			return $result;
		}

		$json['actor'] = \strip_fragment_from_url( $key_id );
		$request->set_body( \wp_json_encode( $json ) );

		return $result;
	}

	/**
	 * Extract the signing keyId from a request's HTTP Signature headers.
	 *
	 * Supports both the draft HTTP Signatures `Signature` header and the RFC 9421
	 * `Signature-Input` header, matching the keyId the same way the signature
	 * verifier does.
	 *
	 * @since unreleased
	 *
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @return string|null The keyId, or null when no signature is present.
	 */
	private static function get_key_id_from_request( $request ) {
		$signature = $request->get_header( 'signature' );
		if ( $signature && \preg_match( '/keyId="([^"]+)"/i', $signature, $matches ) ) {
			return $matches[1];
		}

		// RFC 9421: keyid is a `;`-delimited parameter whose value may be quoted or unquoted.
		$signature_input = $request->get_header( 'signature-input' );
		if ( $signature_input && \preg_match( '/(?:^|;)\s*keyid="?([^";,\s]+)/i', $signature_input, $matches ) ) {
			return $matches[1];
		}

		return null;
	}

	/**
	 * Filters the REST API response to properly handle the ActivityPub error formatting.
	 *
	 * @see https://codeberg.org/fediverse/fep/src/branch/main/fep/c180/fep-c180.md
	 *
	 * @param \WP_HTTP_Response $response Result to send to the client. Usually a `WP_REST_Response`.
	 * @param \WP_REST_Server   $server   Server instance.
	 * @param \WP_REST_Request  $request  Request used to generate the response.
	 *
	 * @return \WP_HTTP_Response The filtered response.
	 */
	public static function filter_output( $response, $server, $request ) {
		$route = $request->get_route();

		// Check if it is an activitypub request and exclude webfinger and nodeinfo endpoints.
		if ( ! \str_starts_with( $route, '/' . ACTIVITYPUB_REST_NAMESPACE ) ) {
			return $response;
		}

		// Exclude OAuth endpoints - they have their own error format per RFC 6749.
		if ( \str_starts_with( $route, '/' . ACTIVITYPUB_REST_NAMESPACE . '/oauth' ) ) {
			return $response;
		}

		// Only alter responses that return an error status code.
		if ( $response->get_status() < 400 ) {
			return $response;
		}

		$data = $response->get_data();

		// Ensure that `$data` was already converted to a response.
		if ( \is_wp_error( $data ) ) {
			$response = \rest_convert_error_to_response( $data );
			$data     = $response->get_data();
		}

		$error = array(
			'type'     => 'about:blank',
			'title'    => $data['code'] ?? '',
			'detail'   => $data['message'] ?? '',
			'status'   => $response->get_status(),

			/*
			 * Provides the unstructured error data.
			 *
			 * @see https://nodeinfo.diaspora.software/schema.html#metadata.
			 */
			'metadata' => $data,
		);

		$response->set_data( $error );

		return $response;
	}

	/**
	 * Add CORS headers to ActivityPub REST responses.
	 *
	 * @param \WP_REST_Response $response The REST response.
	 * @param \WP_REST_Server   $server   The REST server instance.
	 * @param \WP_REST_Request  $request  The request object.
	 *
	 * @return \WP_REST_Response The modified response.
	 */
	public static function add_cors_headers( $response, $server, $request ) {
		$route     = $request->get_route();
		$namespace = '/' . ACTIVITYPUB_REST_NAMESPACE;

		// Only add CORS to ActivityPub endpoints, except the interactive OAuth authorize endpoint.
		if ( ! \str_starts_with( $route, $namespace ) || \str_starts_with( $route, $namespace . '/oauth/authorize' ) ) {
			return $response;
		}

		/*
		 * ActivityPub data is meant to be publicly readable by federation peers
		 * and browser-side clients. We do not enable credentialed cross-origin
		 * access: cookie auth would still be rejected by WordPress core's
		 * REST nonce check, and OAuth Bearer tokens travel in the
		 * Authorization header — which is permitted via Allow-Headers and
		 * does not require Allow-Credentials.
		 *
		 * Allow-Headers is contributed by core (which already lists `X-WP-Nonce`,
		 * `Authorization`, `Content-Type`, `Content-Disposition`, and `Content-MD5`)
		 * and extended for ActivityPub via the `rest_allowed_cors_headers` filter
		 * in self::allow_cors_headers().
		 */
		$response->header( 'Access-Control-Allow-Origin', '*' );
		$response->header( 'Access-Control-Allow-Methods', 'GET, POST, OPTIONS' );

		return $response;
	}

	/**
	 * Extend the CORS Allow-Headers list for ActivityPub REST endpoints.
	 *
	 * Adds the headers ActivityPub clients need on top of WordPress core's
	 * defaults: `Accept` for content negotiation and `Last-Event-ID` for
	 * Server-Sent Events resume.
	 *
	 * @since 8.3.0
	 *
	 * @param string[]         $allow_headers Headers core currently permits in CORS requests.
	 * @param \WP_REST_Request $request       The current REST request.
	 *
	 * @return string[] The (possibly extended) list of allowed headers.
	 */
	public static function allow_cors_headers( $allow_headers, $request ) {
		$route     = $request->get_route();
		$namespace = '/' . ACTIVITYPUB_REST_NAMESPACE;

		if ( ! \str_starts_with( $route, $namespace ) || \str_starts_with( $route, $namespace . '/oauth/authorize' ) ) {
			return $allow_headers;
		}

		return \array_values( \array_unique( \array_merge( (array) $allow_headers, array( 'Accept', 'Last-Event-ID' ) ) ) );
	}

	/**
	 * Send CORS headers directly via header().
	 *
	 * Use this for endpoints that bypass the REST response flow
	 * (e.g. SSE streams that call exit() instead of returning a WP_REST_Response).
	 *
	 * @since 8.1.0
	 */
	public static function send_cors_headers() {
		\header( 'Access-Control-Allow-Origin: *' );
		\header( 'Access-Control-Allow-Methods: GET, POST, OPTIONS' );
		\header( 'Access-Control-Allow-Headers: Authorization, X-WP-Nonce, Content-Disposition, Content-MD5, Content-Type, Accept, Last-Event-ID' );
	}
}
