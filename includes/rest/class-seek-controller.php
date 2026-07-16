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
	 * passes its redirect through. Unknown collections, foreign URLs, items that are not part
	 * of the collection, and unauthorized requests all produce the same 404, so collection
	 * membership is not leaked.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return \WP_REST_Response|\WP_Error Redirect response on success, or WP_Error object on failure.
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

		// The signature was verified for this request; the internal dispatch cannot carry it over.
		\add_filter( 'activitypub_defer_signature_verification', '__return_true' );
		$response = \rest_do_request( $collection_request );
		\remove_filter( 'activitypub_defer_signature_verification', '__return_true' );

		if ( \in_array( $response->get_status(), array( 307, 308 ), true ) ) {
			return $response;
		}

		return $not_found;
	}
}
