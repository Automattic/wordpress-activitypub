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

use function Activitypub\is_same_host;
use function Activitypub\object_to_uri;
use function Activitypub\use_authorized_fetch;
use function Activitypub\user_can_act_as_blog;

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
	 * HEAD requests are bypassed by default so caches and link-checkers
	 * can probe public endpoints; callers that pass `$force_signature`
	 * (e.g. FEP-8fcf's `/followers/sync`) require signatures on HEAD too.
	 *
	 * @see https://www.w3.org/wiki/SocialCG/ActivityPub/Primer/Authentication_Authorization#Authorized_fetch
	 * @see https://swicg.github.io/activitypub-http-signature/#authorized-fetch
	 *
	 * @param \WP_REST_Request $request         The request object.
	 * @param bool             $force_signature Optional. When true, GET and HEAD requests also
	 *                                          require a valid signature even with Authorized
	 *                                          Fetch disabled. Use for endpoints that are
	 *                                          peer-only (e.g. FEP-8fcf's `/followers/sync`).
	 *                                          Default false.
	 * @return bool|\WP_Error True if authorized, WP_Error otherwise.
	 */
	public function verify_signature( $request, $force_signature = false ) {
		if ( 'HEAD' === $request->get_method() && ! $force_signature ) {
			return true;
		}

		/*
		 * Runs before the deferral and before any key work: it costs a URL parse, while everything
		 * below it can go to the network, either to fetch the signing key or, on the deferred
		 * Delete path, to check the named object for a tombstone.
		 */
		$activity_id_check = $this->verify_activity_id( $request );
		if ( \is_wp_error( $activity_id_check ) ) {
			return $activity_id_check;
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
			$verified_key_id = Signature::verify_http_signature( $request );
			if ( \is_wp_error( $verified_key_id ) ) {
				return new \WP_Error(
					'activitypub_signature_verification',
					$verified_key_id->get_error_message(),
					array( 'status' => 401 )
				);
			}

			// Verify the signing key's host matches the activity actor's host.
			$key_id_check = $this->verify_key_id( $request, $verified_key_id );
			if ( \is_wp_error( $key_id_check ) ) {
				return $key_id_check;
			}
		}

		return true;
	}

	/**
	 * Check that the activity id and the activity actor share the same host.
	 *
	 * Incoming activities are stored and looked up under their own `id`, so an activity whose id
	 * points at another host can claim an entry that host owns: a later delivery of the genuine
	 * activity is then folded into the impostor's entry instead of creating its own, and the
	 * recipients of either one end up attached to the wrong actor's payload.
	 *
	 * The HTTP signature binds the signing key to the `actor`, never to the `id`, so a valid
	 * signer can otherwise put any host's id in the body. This is the only place the two are tied
	 * together. {@see \Activitypub\Collection\Interactions::add_reaction()} and
	 * {@see \Activitypub\Collection\Remote_Posts::add()} apply the same rule further in, to the
	 * reaction id and the object id.
	 *
	 * Deliberately runs ahead of `activitypub_defer_signature_verification` rather than beside the
	 * key binding. It needs nothing from the signature, so there is no reason to pay for one first,
	 * and the deferred `Delete` path reaches {@see \Activitypub\Tombstone::exists()}, which fetches
	 * the named object. A body that cannot even name itself consistently should not buy a request
	 * to another host.
	 *
	 * Passes when the body carries no id or no actor. An authorized-fetch GET has neither, and a
	 * body missing either field is rejected by the route's own argument validation, which runs
	 * after the permission callback.
	 *
	 * @since 9.3.0
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return true|\WP_Error True if valid, WP_Error on mismatch.
	 */
	private function verify_activity_id( $request ) {
		$json  = $request->get_json_params();
		$id    = isset( $json['id'] ) ? object_to_uri( $json['id'] ) : null;
		$actor = isset( $json['actor'] ) ? object_to_uri( $json['actor'] ) : null;

		if ( ! $id || ! $actor ) {
			return true;
		}

		if ( ! is_same_host( $id, $actor ) ) {
			return new \WP_Error(
				'activitypub_activity_id_mismatch',
				\__( 'Activity id and activity actor must be on the same host.', 'activitypub' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Check that the signature keyId and activity actor share the same host.
	 *
	 * Binds against the keyId that {@see Signature::verify_http_signature()} actually
	 * verified, passed in by the caller. Re-parsing the headers here would be unsafe: a
	 * request can present several signature labels (or a draft and an RFC 9421 header) with
	 * different keyIds, and only the verifier knows which one validated.
	 *
	 * Fails closed when either side has no parsable host. A non-URL keyId such as
	 * `acct:mallory@attacker.example` resolves to a real key through WebFinger yet yields no host,
	 * so treating "no host" as "nothing to check" would drop the only tie between the signing key
	 * and the claimed actor. Both hosts must be present and equal.
	 *
	 * @since 8.1.0
	 * @since 9.0.0 Added the `$key_id` parameter; binds against the verified keyId.
	 * @since 9.2.1 Reject a keyId or actor with no parsable host instead of allowing it.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @param string|null      $key_id  The keyId that verified the signature.
	 * @return true|\WP_Error True if valid, WP_Error on mismatch.
	 */
	private function verify_key_id( $request, $key_id ) {
		$json  = $request->get_json_params();
		$actor = isset( $json['actor'] ) ? object_to_uri( $json['actor'] ) : null;

		// A signed request without a body actor (e.g. an authorized-fetch GET) has nothing to bind.
		if ( ! $actor ) {
			return true;
		}

		// The keyId and the actor must resolve to the same host; an identifier with no parsable
		// host (e.g. an `acct:` keyId) is unbindable and therefore not an authorized one.
		if ( ! is_same_host( $key_id, $actor ) ) {
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
	 * Determines the required scope from the HTTP method unless the caller names one:
	 * - GET, HEAD: read scope
	 * - POST, PUT, PATCH, DELETE: write scope
	 *
	 * A route whose method does not describe what it does passes its own scope. The proxy is a
	 * POST because the target URL travels in the body, but it only reads.
	 *
	 * If the request has a user_id parameter, also verifies that the
	 * authenticated user matches that actor.
	 *
	 * Application Passwords are not accepted directly on C2S endpoints.
	 *
	 * Security: `check_oauth_permission()` requires a valid Bearer token via
	 * `is_oauth_request()`. Cookie-authenticated sessions never satisfy that
	 * check, so a wp-admin session in another browser tab cannot be hijacked
	 * to drive C2S writes on behalf of the user (no CSRF path on this surface).
	 *
	 * @since 9.3.0 Added the `$scope` parameter.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @param string|null      $scope   Optional. Scope to require instead of the method default. Default null.
	 * @return bool|\WP_Error True if authorized, WP_Error otherwise.
	 */
	public function verify_authentication( $request, $scope = null ) {
		if ( null === $scope ) {
			// Determine scope based on HTTP method.
			$method       = $request->get_method();
			$read_methods = array( 'GET', 'HEAD' );
			$scope        = \in_array( $method, $read_methods, true ) ? Scope::READ : Scope::WRITE;
		}

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

		/*
		 * Require an authenticated session before the identity-equality check below.
		 * Without this guard, anonymous requests with `user_id = 0` (blog actor)
		 * would match because `\get_current_user_id()` also returns `0`, exposing
		 * owner-only behaviors such as the hidden social graph for the blog actor.
		 */
		if ( ! \is_user_logged_in() ) {
			return new \WP_Error(
				'activitypub_forbidden',
				\__( 'You can only access your own resources.', 'activitypub' ),
				array( 'status' => 403 )
			);
		}

		if ( \get_current_user_id() === (int) $user_id ) {
			return true;
		}

		// The blog actor has no `wp_users` row, so the identity-equality check above
		// cannot match for a logged-in user. Delegate to the capability helper.
		if ( Actors::BLOG_USER_ID === (int) $user_id && user_can_act_as_blog() ) {
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

		if ( Actors::show_social_graph( $user_id ) ) {
			return true;
		}

		// Ownership answers who the caller is; the scope answers what the caller was allowed to do with that identity.
		return true === $this->verify_owner( $request ) && OAuth_Server::permits_scope( Scope::READ );
	}
}
