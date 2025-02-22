<?php
/**
 * ActivityPub Embed Handler.
 *
 * @package Activitypub
 */

namespace Activitypub;

use WP_REST_Response;

/**
 * Class to handle embedding ActivityPub content
 */
class Embed {

	/**
	 * Cache expiration time in seconds (24 hours)
	 */
	const CACHE_EXPIRATION = DAY_IN_SECONDS;

	/**
	 * Initialize the embed handler
	 */
	public static function init() {
		add_filter( 'rest_request_after_callbacks', array( __CLASS__, 'filter_oembed_response' ), 10, 3 );
	}

	/**
	 * Filter the oembed response to handle ActivityPub content
	 *
	 * @param \stdClass|WP_REST_Response $response The response data.
	 * @param array                      $handler  The handler used for the response.
	 * @param \WP_REST_Request           $request  The request object.
	 * @return \stdClass|WP_REST_Response
	 */
	public static function filter_oembed_response( $response, $handler, $request ) {
		// Only process oembed proxy requests.
		if ( '/oembed/1.0/proxy' !== $request->get_route() ) {
			return $response;
		}

		// If we already have a valid response with HTML, return it.
		if ( ! is_wp_error( $response ) ) {
			if ( $response instanceof WP_REST_Response ) {
				$data = $response->get_data();
				if ( ! empty( $data['html'] ) ) {
					return $response;
				}
			}
			if ( $response instanceof \stdClass && ! empty( $response->html ) ) {
				return $response;
			}
		}

		$url = $request->get_param( 'url' );
		if ( ! $url ) {
			return $response;
		}

		// Try to get ActivityPub representation.
		$object = Http::get_remote_object( $url );
		if ( is_wp_error( $object ) ) {
			return $response;
		}
		// Most of the work is in here, and cached.
		$html = get_embed_html( $url, true );
		if ( ! $html ) {
			return $response;
		}

		$author_name = $object['attributedTo'] ?? '';
		$author_url  = $object['icon']['url'] ?? '';
		$title       = $object['name'] ?? '';

		$embed_response = (object) array(
			'version'       => '1.0',
			'provider_name' => 'ActivityPub',
			'provider_url'  => 'https://activitypub.rocks/',
			'author_name'   => $author_name,
			'author_url'    => $author_url,
			'title'         => $title,
			'type'          => 'rich',
			'width'         => 600,
			'height'        => null,
			'html'          => $html,
		);

		return rest_ensure_response( $embed_response );
	}
}
