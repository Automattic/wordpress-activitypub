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
	use Event_Stream;
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
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'verify_authentication' ),
					'args'                => array(
						'id' => array(
							'description'       => 'The URI of the remote ActivityPub object to fetch.',
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => array( $this, 'sanitize_url' ),
							'validate_callback' => array( $this, 'validate_url' ),
						),
					),
				),
				'schema' => array( $this, 'get_item_schema' ),
			)
		);

		\register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/stream',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_stream' ),
					'permission_callback' => array( $this, 'get_stream_permissions_check' ),
					'args'                => array(
						'id' => array(
							'description'       => 'The remote object ID (URI) whose eventStream to proxy.',
							'type'              => 'string',
							'format'            => 'uri',
							'required'          => true,
							'sanitize_callback' => array( $this, 'sanitize_url' ),
							'validate_callback' => array( $this, 'validate_url' ),
						),
					),
				),
			)
		);
	}

	/**
	 * Sanitizes the URL parameter.
	 *
	 * @see https://developer.wordpress.org/reference/functions/sanitize_url/
	 *
	 * @param string $url The urlencoded URL to sanitize.
	 * @return string The sanitized URL.
	 */
	public function sanitize_url( $url ) {
		// Decode and sanitize the URL.
		return sanitize_url( urldecode( $url ) );
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
		// Decode the url.
		$decoded_url = urldecode( $url );

		// Must be HTTPS.
		if ( 'https' !== \wp_parse_url( $decoded_url, PHP_URL_SCHEME ) ) {
			return false;
		}

		// Use WordPress built-in validation (blocks local IPs, restricts ports).
		return (bool) \wp_http_validate_url( $decoded_url );
	}

	/**
	 * Fetch a remote ActivityPub object via the proxy.
	 *
	 * @see https://www.w3.org/wiki/ActivityPub/Primer/proxyUrl_endpoint
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return \WP_REST_Response|\WP_Error Response object on success, WP_Error on failure.
	 */
	public function create_item( $request ) {
		// Rate-limit proxy requests (max 30 per minute per user).
		$user_id       = \get_current_user_id();
		$transient_key = 'ap_proxy_' . $user_id;
		$count         = (int) \get_transient( $transient_key );

		if ( $count >= 30 ) {
			return new \WP_Error(
				'activitypub_rate_limit',
				\__( 'Too many proxy requests. Please try again later.', 'activitypub' ),
				array( 'status' => 429 )
			);
		}

		\set_transient( $transient_key, $count + 1, MINUTE_IN_SECONDS );

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

	/**
	 * Proxy a remote eventStream.
	 *
	 * Fetches the remote object to discover its eventStream URL,
	 * then opens a streaming connection and relays SSE events.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 *
	 * @return \WP_Error|void WP_Error on failure, exits on success.
	 */
	public function get_stream( $request ) {
		$remote_id = $request->get_param( 'id' );

		$object = Http::get_remote_object( $remote_id );

		if ( \is_wp_error( $object ) ) {
			return new \WP_Error(
				'activitypub_proxy_fetch_failed',
				\__( 'Failed to fetch the remote object.', 'activitypub' ),
				array( 'status' => 502 )
			);
		}

		$stream_url = isset( $object['eventStream'] ) ? $object['eventStream'] : null;

		if ( ! $stream_url ) {
			return new \WP_Error(
				'activitypub_no_event_stream',
				\__( 'The remote object does not advertise an eventStream.', 'activitypub' ),
				array( 'status' => 404 )
			);
		}

		if ( ! $this->validate_url( $stream_url ) ) {
			return new \WP_Error(
				'activitypub_invalid_event_stream',
				\__( 'The remote eventStream URL is not valid.', 'activitypub' ),
				array( 'status' => 400 )
			);
		}

		$this->relay_remote_stream( $stream_url );
	}
}
