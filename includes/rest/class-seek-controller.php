<?php
/**
 * Seek_Controller file.
 *
 * @package Activitypub
 */

namespace Activitypub\Rest;

use Activitypub\OAuth\Server as OAuth_Server;

/**
 * ActivityPub Seek_Controller class.
 *
 * Implements the seekItem collection extension: resolves the collection page containing a
 * given item and redirects to it. The endpoint dispatches an internal REST request to the
 * collection itself with the `item` parameter set, so the page resolution lives with each
 * collection and every current and future collection is reachable through one endpoint.
 *
 * @see https://swicg.github.io/activitypub-api/seekitem
 *
 * @since unreleased
 */
class Seek_Controller extends \WP_REST_Controller {
	use Verification;

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
	protected $rest_base = 'seek';

	/**
	 * Register routes.
	 */
	public function register_routes() {
		\register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
					'args'                => array(
						'collection' => array(
							'description' => 'The ID of the collection to seek in.',
							'type'        => 'string',
							'format'      => 'uri',
							'required'    => true,
						),
						'item'       => array(
							'description' => 'The ActivityPub object ID of the item to seek.',
							'type'        => 'string',
							'format'      => 'uri',
							'required'    => true,
						),
					),
				),
			)
		);
	}

	/**
	 * Check permissions for a seek request.
	 *
	 * Supports both authentication methods commonly used with ActivityPub: a request carrying
	 * an OAuth 2.0 Bearer token goes through the same verify_authentication() used by the
	 * Client-to-Server endpoints; all other requests go through HTTP Signature verification,
	 * which allows anonymous reads unless Authorized Fetch is enabled. The dispatched
	 * collection additionally applies its own permission check, so a seek can never see more
	 * than the collection itself would reveal.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return bool|\WP_Error True if authorized, WP_Error otherwise.
	 */
	public function get_item_permissions_check( $request ) {
		if ( OAuth_Server::is_oauth_request() ) {
			return $this->verify_authentication( $request );
		}

		return $this->verify_signature( $request );
	}

	/**
	 * Seek an item in a collection.
	 *
	 * Dispatches an internal request to the collection endpoint with the `item` parameter and
	 * passes its redirect through. A missing-authentication failure surfaces as 401 to tell the
	 * client to authenticate. Unknown collections, foreign URLs, items that are not part of the
	 * collection, and authenticated-but-not-authorized requests all produce the same 404, so
	 * collection membership is not leaked.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return \WP_REST_Response|\WP_Error Redirect on success, or 401/404 WP_Error on failure.
	 */
	public function get_item( $request ) {
		$not_found = new \WP_Error(
			'activitypub_item_not_found',
			\__( 'The requested item could not be found in this collection.', 'activitypub' ),
			array( 'status' => 404 )
		);

		// Resolves only URLs served by this site's REST API, so no remote request is ever made.
		$collection_request = \WP_REST_Request::from_url( $request->get_param( 'collection' ) );

		if ( ! $collection_request || ! \str_starts_with( $collection_request->get_route(), '/' . ACTIVITYPUB_REST_NAMESPACE . '/' ) ) {
			return $not_found;
		}

		$collection_request->set_param( 'item', $request->get_param( 'item' ) );
		// Carry the original headers over, so a Bearer token reaches the collection's own permission check.
		$collection_request->set_headers( $request->get_headers() );

		/*
		 * The outer request's signature was verified for the /seek route and cannot be replayed
		 * against the inner route, so defer signature verification for the dispatch. Endpoints that
		 * force signatures (FEP-8fcf's /followers/sync) are deliberately not deferred: their
		 * mandatory verification must still run, and it will fail for a seek since the signature was
		 * never computed over their route. get_item() maps that failure to the same 404 as any other
		 * non-seekable collection, so no membership is leaked.
		 */
		$defer = static function ( $deferred, $inner_request, $force_signature ) {
			// Decide deterministically: defer for the dispatch, except on forced-signature routes.
			return ! $force_signature;
		};
		// Latest priority, so a global defer filter (e.g. a local-dev __return_true) cannot reopen a forced route.
		\add_filter( 'activitypub_defer_signature_verification', $defer, \PHP_INT_MAX, 3 );
		$response = \rest_do_request( $collection_request );
		\remove_filter( 'activitypub_defer_signature_verification', $defer, \PHP_INT_MAX );

		/*
		 * A redirect is the sought page. A missing-authentication failure (401) is a property of the
		 * request, not the item, so it is surfaced as is (it discloses no membership) to tell the
		 * client to authenticate. Everything else — an authenticated-but-not-authorized request, or
		 * the collection's own item-not-found — collapses to a single 404, per the seekItem spec.
		 */
		if ( \in_array( $response->get_status(), array( 307, 308, 401 ), true ) ) {
			return $response;
		}

		return $not_found;
	}
}
