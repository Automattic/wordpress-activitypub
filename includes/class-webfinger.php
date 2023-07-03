<?php
namespace Activitypub;

use WP_Error;

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

		$user = Users::get_by_id( $user_id );
		if ( ! $user ) {
			return '';
		}

		return $user->get_resource();
	}

	/**
	 * Resolve a WebFinger resource
	 *
	 * @param string $resource The WebFinger resource
	 *
	 * @return string|WP_Error The URL or WP_Error
	 */
	public static function resolve( $resource ) {
		if ( ! preg_match( '/^@?' . ACTIVITYPUB_USERNAME_REGEXP . '$/i', $resource, $m ) ) {
			return null;
		}
		$transient_key = 'activitypub_resolve_' . ltrim( $resource, '@' );

		$link = \get_transient( $transient_key );
		if ( $link ) {
			return $link;
		}

		$url = \add_query_arg( 'resource', 'acct:' . ltrim( $resource, '@' ), 'https://' . $m[2] . '/.well-known/webfinger' );
		if ( ! \wp_http_validate_url( $url ) ) {
			$response = new WP_Error( 'invalid_webfinger_url', null, $url );
			\set_transient( $transient_key, $response, HOUR_IN_SECONDS ); // Cache the error for a shorter period.
			return $response;
		}

		// try to access author URL
		$response = \wp_remote_get(
			$url,
			array(
				'headers' => array( 'Accept' => 'application/activity+json' ),
				'redirection' => 0,
				'timeout' => 2,
			)
		);

		if ( \is_wp_error( $response ) ) {
			$link = new WP_Error( 'webfinger_url_not_accessible', null, $url );
			\set_transient( $transient_key, $link, HOUR_IN_SECONDS ); // Cache the error for a shorter period.
			return $link;
		}

		$body = \wp_remote_retrieve_body( $response );
		$body = \json_decode( $body, true );

		if ( empty( $body['links'] ) ) {
			$link = new WP_Error( 'webfinger_url_invalid_response', null, $url );
			\set_transient( $transient_key, $link, HOUR_IN_SECONDS ); // Cache the error for a shorter period.
			return $link;
		}

		foreach ( $body['links'] as $link ) {
			if ( 'self' === $link['rel'] && 'application/activity+json' === $link['type'] ) {
				\set_transient( $transient_key, $link['href'], WEEK_IN_SECONDS );
				return $link['href'];
			}
		}

		$link = new WP_Error( 'webfinger_url_no_activitypub', null, $body );
		\set_transient( $transient_key, $link, HOUR_IN_SECONDS ); // Cache the error for a shorter period.
		return $link;
	}
}
