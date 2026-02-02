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
 * Provides methods for verifying HTTP Signatures (S2S) and OAuth tokens (C2S).
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
	 * Verify OAuth authentication with 'read' scope.
	 *
	 * Use this for endpoints requiring OAuth read access (C2S).
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return bool|\WP_Error True if authorized, WP_Error otherwise.
	 */
	public function verify_oauth_read( $request ) {
		return OAuth_Server::check_oauth_permission( $request, Scope::READ );
	}

	/**
	 * Verify OAuth authentication with 'write' scope.
	 *
	 * Use this for endpoints requiring OAuth write access (C2S).
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return bool|\WP_Error True if authorized, WP_Error otherwise.
	 */
	public function verify_oauth_write( $request ) {
		return OAuth_Server::check_oauth_permission( $request, Scope::WRITE );
	}

	/**
	 * Verify that the OAuth token belongs to the actor specified in the request.
	 *
	 * This checks that the user_id parameter matches the token's user.
	 * Should be called after verify_oauth_read or verify_oauth_write.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return bool|\WP_Error True if the token user matches, WP_Error otherwise.
	 */
	public function verify_owner( $request ) {
		$user_id = $request->get_param( 'user_id' );

		// Validate the user exists.
		$user = Actors::get_by_id( $user_id );
		if ( \is_wp_error( $user ) ) {
			return $user;
		}

		// Verify the token belongs to this user.
		$token = OAuth_Server::get_current_token();

		if ( ! $token || $token->get_user_id() !== absint( $user_id ) ) {
			return new \WP_Error(
				'activitypub_forbidden',
				\__( 'You can only access your own resources.', 'activitypub' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}
}
