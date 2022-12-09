<?php
namespace Activitypub;

/**
 * ActivityPub WebFinger Class
 *
 * @author Matthias Pfefferle
 *
 * @see https://webfinger.net/
 */
class Webfinger {
	/**
	 * Returns a users WebFinger "resource"
	 *
	 * @param int $user_id
	 *
	 * @return string The user-resource
	 */
	public static function get_user_resource( $user_id ) {
		// use WebFinger plugin if installed
		if ( \function_exists( '\get_webfinger_resource' ) ) {
			return \get_webfinger_resource( $user_id, false );
		}

		$user = \get_user_by( 'id', $user_id );

		return $user->user_login . '@' . \wp_parse_url( \home_url(), \PHP_URL_HOST );
	}

	public static function resolve( $account ) {
		if ( ! preg_match( '/^@?[^@]+@((?:[a-z0-9-]+\.)+[a-z]+)$/i', $account, $m ) ) {
			return null;
		}
		$url = \add_query_arg( 'resource', 'acct:' . ltrim( $account, '@' ), 'https://' . $m[1] . '/.well-known/webfinger' );
		if ( ! \wp_http_validate_url( $url ) ) {
			return new \WP_Error( 'invalid_webfinger_url', null, $url );
		}

		// try to access author URL
		$response = \wp_remote_get(
			$url,
			array(
				'headers' => array( 'Accept' => 'application/activity+json' ),
				'redirection' => 0,
			)
		);

		if ( \is_wp_error( $response ) ) {
			return new \WP_Error( 'webfinger_url_not_accessible', null, $url );
		}

		$response_code = \wp_remote_retrieve_response_code( $response );

		$body = \wp_remote_retrieve_body( $response );
		$body = \json_decode( $body, true );

		if ( ! isset( $body['links'] ) ) {
			return new \WP_Error( 'webfinger_url_invalid_response', null, $url );
		}

		foreach ( $body['links'] as $link ) {
			if ( 'self' === $link['rel'] && 'application/activity+json' === $link['type'] ) {
				return $link['href'];
			}
		}

		return new \WP_Error( 'webfinger_url_no_activity_pub', null, $body );
	}
}
