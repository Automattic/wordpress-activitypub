<?php
/**
 * Verification Trait file.
 *
 * @package Activitypub
 */

namespace Activitypub\Rest;

use Activitypub\Collection\Actors;
use Activitypub\OAuth\Scope;
use Activitypub\OAuth\Server as OAuth_Server;
use Activitypub\Signature;

use function Activitypub\use_authorized_fetch;

/**
 * Verification Trait.
 *
 * Provides methods for verifying HTTP Signatures (S2S) and OAuth/Application Passwords (C2S).
 * Controllers can use this trait for permission callbacks.
 */
trait Verification {
	/**
	 * Verify HTTP Signature for server-to-server requests.
	 *
	 * Verifies the signature of POST, PUT, PATCH, and DELETE requests,
	 * as well as GET requests when authorized fetch is enabled.
	 * HEAD requests are always bypassed.
	 *
	 * @see https://www.w3.org/wiki/SocialCG/ActivityPub/Primer/Authentication_Authorization#Authorized_fetch
	 * @see https://swicg.github.io/activitypub-http-signature/#authorized-fetch
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return bool|\WP_Error True if authorized, WP_Error otherwise.
	 */
	public function verify_signature( $request ) {
		if ( 'HEAD' === $request->get_method() ) {
			return true;
		}

		/**
		 * Filter to defer signature verification.
		 *
		 * Skip signature verification for debugging purposes or to reduce load for
		 * certain Activity-Types, like "Delete".
		 *
		 * @param bool             $defer   Whether to defer signature verification.
		 * @param \WP_REST_Request $request The request used to generate the response.
		 * @return bool Whether to defer signature verification.
		 */
		$defer = \apply_filters( 'activitypub_defer_signature_verification', false, $request );

		if ( $defer ) {
			return true;
		}

		// POST-Requests always have to be signed, GET-Requests only require a signature in secure mode.
		if ( 'GET' !== $request->get_method() || use_authorized_fetch() ) {
			$verified_request = Signature::verify_http_signature( $request );
			if ( \is_wp_error( $verified_request ) ) {
				return new \WP_Error(
					'activitypub_signature_verification',
					$verified_request->get_error_message(),
					array( 'status' => 401 )
				);
			}
		}

		return true;
	}

	/**
	 * Verify Application Passwords authentication.
	 *
	 * Uses WordPress core Application Passwords via Basic Auth.
	 *
	 * @see https://make.wordpress.org/core/2020/11/05/application-passwords-integration-guide/
	 *
	 * @return bool|\WP_Error True if authorized, WP_Error otherwise.
	 */
	public function verify_application_password() {
		if ( \is_user_logged_in() ) {
			return true;
		}

		return new \WP_Error(
			'activitypub_unauthorized',
			\__( 'Authentication required.', 'activitypub' ),
			array( 'status' => 401 )
		);
	}

	/**
	 * Verify user authentication via OAuth or Application Passwords.
	 *
	 * Automatically determines the required scope based on the HTTP method:
	 * - GET, HEAD: read scope
	 * - POST, PUT, PATCH, DELETE: write scope
	 *
	 * If the request has a user_id parameter, also verifies that the
	 * authenticated user matches that actor.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return bool|\WP_Error True if authorized, WP_Error otherwise.
	 */
	public function verify_authentication( $request ) {
		// Determine scope based on HTTP method.
		$method       = $request->get_method();
		$read_methods = array( 'GET', 'HEAD' );
		$scope        = \in_array( $method, $read_methods, true ) ? Scope::READ : Scope::WRITE;

		// Try OAuth first.
		$oauth_result = OAuth_Server::check_oauth_permission( $request, $scope );
		if ( true === $oauth_result ) {
			return $this->maybe_verify_owner( $request );
		}

		// If OAuth was attempted (Bearer token present), don't fall back to Application Passwords.
		// This prevents scope bypass when OAuth auth succeeds but scope check fails.
		if ( \is_wp_error( $oauth_result ) && OAuth_Server::is_oauth_request() ) {
			return $oauth_result;
		}

		// Fall back to Application Passwords only when no OAuth token was used.
		$result = $this->verify_application_password();
		if ( \is_wp_error( $result ) ) {
			return $result;
		}

		return $this->maybe_verify_owner( $request );
	}

	/**
	 * Verify owner if user_id parameter is present.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return bool|\WP_Error True if authorized, WP_Error otherwise.
	 */
	private function maybe_verify_owner( $request ) {
		$user_id = $request->get_param( 'user_id' );

		if ( null === $user_id ) {
			return true;
		}

		return $this->verify_owner( $request );
	}

	/**
	 * Verify that the authenticated user matches the actor specified in the request.
	 *
	 * Checks that the user_id parameter matches the OAuth token's user
	 * or the WordPress authenticated user (via Application Passwords).
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return bool|\WP_Error True if the user matches, WP_Error otherwise.
	 */
	public function verify_owner( $request ) {
		$user_id = $request->get_param( 'user_id' );

		// Validate the user exists.
		$user = Actors::get_by_id( $user_id );
		if ( \is_wp_error( $user ) ) {
			return $user;
		}

		// Try OAuth token first.
		$token = OAuth_Server::get_current_token();
		if ( $token && $token->get_user_id() === \absint( $user_id ) ) {
			return true;
		}

		// Fall back to WordPress authenticated user (Application Passwords).
		if ( \is_user_logged_in() && \get_current_user_id() === \absint( $user_id ) ) {
			return true;
		}

		return new \WP_Error(
			'activitypub_forbidden',
			\__( 'You can only access your own resources.', 'activitypub' ),
			array( 'status' => 403 )
		);
	}
}
