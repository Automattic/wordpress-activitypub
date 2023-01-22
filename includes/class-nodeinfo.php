<?php
namespace Activitypub;

/**
 * ActivityPub WebFinger Class
 *
 * @author Matthias Pfefferle & Greg Ross
 *
 * @see http://nodeinfo.diaspora.software/
 */
class Nodeinfo {
	/**
	 * Returns a server's nodeinfo data
	 *
	 * @param string $server    Name of the server
	 *
	 * @return WP_Error|array   A WP_Error object if an error occures, or an array of the decoded json nodeinfo
	 */
	public static function resolve( $server ) {
		// First setup the url to grab the the location of the nodeinfo from well-known.
		$url = 'https://' . $server . '/.well-known/nodeinfo';
		if ( ! \wp_http_validate_url( $url ) ) {
			return new \WP_Error( 'invalid_nodeinfo', null, $url );
		}

		// Retrieve the well-known data.
		$response = \wp_remote_get(
			$url,
			array(
				'headers' => array( 'Accept' => 'application/activity+json' ),
				'redirection' => 0,
			)
		);

		if ( \is_wp_error( $response ) ) {
			return new \WP_Error( 'nodeinfo_url_not_accessible', null, $url );
		}

		$response_code = \wp_remote_retrieve_response_code( $response );

		// Get the body and decode it.
		$body = \wp_remote_retrieve_body( $response );
		$body = \json_decode( $body, true );

		if ( ! isset( $body['links'] ) ) {
			return new \WP_Error( 'nodeinfo_url_invalid_response', null, $url );
		}

		// Now look for the real nodeinfo url in the well-known data.
		$nodeinfo_url = false;
		foreach ( $body['links'] as $link ) {
			if ( array_key_exists( 'href', $link ) ) {
				$nodeinfo_url = $link['href'];
			}
		}

		if( $nodeinfo_url === false ) {
			return new \WP_Error( 'nodeinfo_url_no_schema', null, $body );
		}

		// If we found a nodeinfo url, validate it.
		if ( ! \wp_http_validate_url( $nodeinfo_url ) ) {
			return new \WP_Error( 'invalid_nodeinfo_url', null, $nodeinfo_url );
		}

		// Retrieve the real nodeinfo data.
		$response = \wp_remote_get(
			$nodeinfo_url,
			array(
				'headers' => array( 'Accept' => 'application/activity+json' ),
				'redirection' => 0,
			)
		);

		if ( \is_wp_error( $response ) ) {
			return new \WP_Error( 'nodeinfo_url_not_accessible', null, $nodeinfo_url );
		}

		$response_code = \wp_remote_retrieve_response_code( $response );

		// Get the body and decode it.
		$body = \wp_remote_retrieve_body( $response );
		$body = \json_decode( $body, true );

		if ( ! isset( $body['version'] ) ) {
			return new \WP_Error( 'nodeinfo_url_invalid_response', null, $nodeinfo_url );
		}

		// Finally return the result.
		return $body;
	}
}
