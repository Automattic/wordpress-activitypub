<?php
/**
 * Proxy Controller file.
 *
 * Implements the proxyUrl endpoint for C2S clients to fetch remote ActivityPub objects.
 *
 * @package Activitypub
 * @see https://www.w3.org/wiki/ActivityPub/Primer/proxyUrl_endpoint
 */

namespace Activitypub\Rest;

use Activitypub\Collection\Remote_Actors;
use Activitypub\Http;

use function Activitypub\is_actor;

/**
 * Proxy Controller.
 *
 * Provides a bridge between C2S OAuth authentication and S2S HTTP Signature authentication.
 * Allows C2S clients to fetch remote ActivityPub objects through their home server.
 */
class Proxy_Controller extends \WP_REST_Controller {
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
	protected $rest_base = 'proxy';

	/**
	 * Register routes.
	 */
	public function register_routes() {
		\register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
					'args'                => array(
						'id' => array(
							'description'       => 'The URI of the remote ActivityPub object to fetch.',
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_url',
						),
					),
				),
				'schema' => array( $this, 'get_item_schema' ),
			)
		);
	}

	/**
	 * Check if the request has permission to use the proxy.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return true|\WP_Error True if the request has permission, WP_Error otherwise.
	 */
	public function get_item_permissions_check( $request ) {
		// Verify OAuth with read scope.
		$result = Server::verify_oauth_read( $request );
		if ( \is_wp_error( $result ) ) {
			return $result;
		}

		// Validate the URL to prevent abuse.
		$url = $request->get_param( 'id' );

		// Must be HTTPS.
		if ( 'https' !== \wp_parse_url( $url, PHP_URL_SCHEME ) ) {
			return new \WP_Error(
				'activitypub_invalid_url',
				\__( 'Only HTTPS URLs are allowed.', 'activitypub' ),
				array( 'status' => 400 )
			);
		}

		// Block local/private network addresses.
		$host = \wp_parse_url( $url, PHP_URL_HOST );
		if ( $this->is_private_host( $host ) ) {
			return new \WP_Error(
				'activitypub_invalid_url',
				\__( 'Private network addresses are not allowed.', 'activitypub' ),
				array( 'status' => 400 )
			);
		}

		return true;
	}

	/**
	 * Fetch a remote ActivityPub object via the proxy.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return \WP_REST_Response|\WP_Error Response object on success, WP_Error on failure.
	 */
	public function get_item( $request ) {
		$url = $request->get_param( 'id' );

		// Try to fetch as an actor first using Remote_Actors which handles caching.
		$post = Remote_Actors::fetch_by_various( $url );

		if ( ! \is_wp_error( $post ) ) {
			$actor = Remote_Actors::get_actor( $post );

			if ( ! \is_wp_error( $actor ) ) {
				$response = new \WP_REST_Response( $actor->to_array(), 200 );
				$response->header( 'Content-Type', 'application/activity+json; charset=' . \get_option( 'blog_charset' ) );

				return $response;
			}
		}

		// Fall back to fetching as a generic object.
		$object = Http::get_remote_object( $url );

		if ( \is_wp_error( $object ) ) {
			return new \WP_Error(
				'activitypub_fetch_failed',
				\__( 'Failed to fetch the remote object.', 'activitypub' ),
				array( 'status' => 502 )
			);
		}

		// If it's an actor, store it for future use.
		if ( is_actor( $object ) ) {
			Remote_Actors::upsert( $object );
		}

		$response = new \WP_REST_Response( $object, 200 );
		$response->header( 'Content-Type', 'application/activity+json; charset=' . \get_option( 'blog_charset' ) );

		return $response;
	}

	/**
	 * Check if a host is a private/local network address.
	 *
	 * @param string $host The hostname to check.
	 * @return bool True if the host is private, false otherwise.
	 */
	private function is_private_host( $host ) {
		// Check for localhost.
		if ( 'localhost' === $host || '127.0.0.1' === $host || '::1' === $host ) {
			return true;
		}

		// Check for private IP ranges.
		$ip = \gethostbyname( $host );
		if ( $ip === $host ) {
			// DNS resolution failed, allow it (will fail on fetch anyway).
			return false;
		}

		// Use filter_var to check for private/reserved IPs.
		if ( false === \filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Get the schema for the proxy endpoint.
	 *
	 * @return array Schema array.
	 */
	public function get_item_schema() {
		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'proxy',
			'type'       => 'object',
			'properties' => array(
				'id' => array(
					'description' => \__( 'The URI of the remote ActivityPub object.', 'activitypub' ),
					'type'        => 'string',
					'format'      => 'uri',
					'context'     => array( 'view' ),
				),
			),
		);
	}
}
