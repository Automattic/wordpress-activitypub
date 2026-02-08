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
use Activitypub\OAuth\Scope;
use Activitypub\OAuth\Server as OAuth_Server;

use function Activitypub\is_actor;

/**
 * Proxy Controller.
 *
 * Provides a bridge between C2S OAuth authentication and S2S HTTP Signature authentication.
 * Allows C2S clients to fetch remote ActivityPub objects through their home server.
 */
class Proxy_Controller extends \WP_REST_Controller {
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
					'permission_callback' => array( $this, 'verify_read_permission' ),
					'args'                => array(
						'id' => array(
							'description'       => 'The URI of the remote ActivityPub object to fetch.',
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_url',
							'validate_callback' => array( $this, 'validate_url' ),
						),
					),
				),
				'schema' => array( $this, 'get_item_schema' ),
			)
		);
	}

	/**
	 * Validate the URL parameter.
	 *
	 * Uses wp_http_validate_url() which blocks local/private IPs and restricts ports.
	 *
	 * @see https://developer.wordpress.org/reference/functions/wp_http_validate_url/
	 *
	 * @param string $url The URL to validate.
	 * @return bool True if valid, false otherwise.
	 */
	public function validate_url( $url ) {
		// Must be HTTPS.
		if ( 'https' !== \wp_parse_url( $url, PHP_URL_SCHEME ) ) {
			return false;
		}

		// Use WordPress built-in validation (blocks local IPs, restricts ports).
		return (bool) \wp_http_validate_url( $url );
	}

	/**
	 * Verify read permission for proxy endpoint.
	 *
	 * The proxy is a read operation (fetching remote objects) even though it uses POST.
	 * This ensures clients with only read scope can use the proxy.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return bool|\WP_Error True if authorized, WP_Error otherwise.
	 */
	public function verify_read_permission( $request ) {
		// Try OAuth with read scope.
		$oauth_result = OAuth_Server::check_oauth_permission( $request, Scope::READ );
		if ( true === $oauth_result ) {
			return true;
		}

		// If OAuth was attempted but failed, don't fall back.
		if ( \is_wp_error( $oauth_result ) && OAuth_Server::is_oauth_request() ) {
			return $oauth_result;
		}

		// Fall back to Application Passwords.
		return $this->verify_application_password();
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
