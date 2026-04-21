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

use function Activitypub\object_to_uri;
use function Activitypub\use_authorized_fetch;

/**
 * Verification Trait.
 *
 * Provides methods for verifying HTTP Signatures (S2S) and OAuth (C2S).
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
	 * @param \WP_REST_Request $request         The request object.
	 * @param bool             $force_signature Optional. When true, GET requests also require a
	 *                                          valid signature even with Authorized Fetch
	 *                                          disabled. Use for endpoints that are peer-only
	 *                                          (e.g. FEP-8fcf's `/followers/sync`). Default false.
	 * @return bool|\WP_Error True if authorized, WP_Error otherwise.
	 */
	public function verify_signature( $request, $force_signature = false ) {
		if ( 'HEAD' === $request->get_method() ) {
			return true;
		}

		/**
		 * Filter to defer signature verification.
		 *
		 * Skip signature verification for debugging purposes or to reduce load for
		 * certain Activity-Types, like "Delete". Callers that want to preserve
		 * mandatory signing for endpoints passing `$force_signature = true`
		 * (e.g. FEP-8fcf's `/followers/sync`) should inspect the third argument
		 * and return `false` in that case.
		 *
		 * @param bool             $defer           Whether to defer signature verification.
		 * @param \WP_REST_Request $request         The request used to generate the response.
		 * @param bool             $force_signature Whether the caller has forced signature
		 *                                          verification for this endpoint.
		 * @return bool Whether to defer signature verification.
		 */
		$defer = \apply_filters( 'activitypub_defer_signature_verification', false, $request, $force_signature );

		if ( $defer ) {
			return true;
		}

		// POST-Requests always have to be signed, GET-Requests only require a signature in secure mode or when forced.
		if ( 'GET' !== $request->get_method() || use_authorized_fetch() || $force_signature ) {
			$verified_request = Signature::verify_http_signature( $request );
			if ( \is_wp_error( $verified_request ) ) {
				return new \WP_Error(
					'activitypub_signature_verification',
					$verified_request->get_error_message(),
					array( 'status' => 401 )
				);
			}

			// Verify the signing key's host matches the activity actor's host.
			$key_id_check = $this->verify_key_id( $request );
			if ( \is_wp_error( $key_id_check ) ) {
				return $key_id_check;
			}
		}

		return true;
	}

	/**
	 * Check that the signature keyId and activity actor share the same host.
	 *
	 * @since 8.1.0
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return true|\WP_Error True if valid, WP_Error on mismatch.
	 */
	private function verify_key_id( $request ) {
		$sig = $request->get_header( 'signature' );
		if ( ! $sig || ! \preg_match( '/keyId="([^"]+)"/i', $sig, $m ) ) {
			// RFC 9421 Signature-Input.
			$sig = $request->get_header( 'signature-input' );
			if ( ! $sig || ! \preg_match( '/keyid="([^"]+)"/i', $sig, $m ) ) {
				return true;
			}
		}

		$key_host = \strtolower( (string) \wp_parse_url( $m[1], \PHP_URL_HOST ) );
		$json     = $request->get_json_params();
		$actor    = isset( $json['actor'] ) ? object_to_uri( $json['actor'] ) : null;

		if ( ! $actor || ! $key_host ) {
			return true;
		}

		$actor_host = \strtolower( (string) \wp_parse_url( $actor, \PHP_URL_HOST ) );

		if ( ! $actor_host || $key_host !== $actor_host ) {
			return new \WP_Error(
				'activitypub_key_actor_mismatch',
				\__( 'Signing key and activity actor must be on the same host.', 'activitypub' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Verify user authentication via OAuth.
	 *
	 * Automatically determines the required scope based on the HTTP method:
	 * - GET, HEAD: read scope
	 * - POST, PUT, PATCH, DELETE: write scope
	 *
	 * If the request has a user_id parameter, also verifies that the
	 * authenticated user matches that actor.
	 *
	 * Application Passwords are not accepted directly on C2S endpoints.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return bool|\WP_Error True if authorized, WP_Error otherwise.
	 */
	public function verify_authentication( $request ) {
		// Determine scope based on HTTP method.
		$method       = $request->get_method();
		$read_methods = array( 'GET', 'HEAD' );
		$scope        = \in_array( $method, $read_methods, true ) ? Scope::READ : Scope::WRITE;

		$result = OAuth_Server::check_oauth_permission( $request, $scope );
		if ( true === $result ) {
			return $this->maybe_verify_owner( $request );
		}

		return $result;
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
	 * Checks that the user_id parameter matches the authenticated user.
	 * Works with both OAuth tokens and WordPress session auth (wp-login.php flow).
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

		if ( \get_current_user_id() === (int) $user_id ) {
			return true;
		}

		return new \WP_Error(
			'activitypub_forbidden',
			\__( 'You can only access your own resources.', 'activitypub' ),
			array( 'status' => 403 )
		);
	}

	/**
	 * Check if the social graph should be shown for this request.
	 *
	 * Returns true if the social graph setting allows public display,
	 * or if the request is authenticated by the resource owner.
	 *
	 * @since 8.1.0
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return bool True if the social graph should be shown.
	 */
	protected function show_social_graph( $request ) {
		$user_id = $request->get_param( 'user_id' );

		return Actors::show_social_graph( $user_id ) || true === $this->verify_owner( $request );
	}
}
