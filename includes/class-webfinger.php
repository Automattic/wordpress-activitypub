<?php
namespace Activitypub;

use WP_Error;
use Activitypub\Collection\Users;

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
		if ( ! $user || is_wp_error( $user ) ) {
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
				'headers' => array( 'Accept' => 'application/jrd+json' ),
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

	/**
	 * Convert a URI string to an identifier and its host.
	 * Automatically adds acct: if it's missing.
	 *
	 * @param string $url The URI (acct:, mailto:, http:, https:)
	 *
	 * @return WP_Error|array Error reaction or array with
	 *                        identifier and host as values
	 */
	public static function get_identifier_and_host( $url ) {
		// remove leading @
		$url = ltrim( $url, '@' );

		if ( ! preg_match( '/^([a-zA-Z+]+):/', $url, $match ) ) {
			$identifier = 'acct:' . $url;
			$scheme = 'acct';
		} else {
			$identifier = $url;
			$scheme = $match[1];
		}

		$host = null;

		switch ( $scheme ) {
			case 'acct':
			case 'mailto':
			case 'xmpp':
				if ( strpos( $identifier, '@' ) !== false ) {
					$host = substr( $identifier, strpos( $identifier, '@' ) + 1 );
				}
				break;
			default:
				$host = wp_parse_url( $identifier, PHP_URL_HOST );
				break;
		}

		if ( empty( $host ) ) {
			return new WP_Error( 'invalid_identifier', __( 'Invalid Identifier', 'activitypub' ) );
		}

		return array( $identifier, $host );
	}

	/**
	 * Get the WebFinger data for a given URI
	 *
	 * @param string $identifier The Identifier: <identifier>@<host>
	 * @param string $host       The Host: <identifier>@<host>
	 *
	 * @return WP_Error|array Error reaction or array with
	 *                        identifier and host as values
	 */
	public static function get_data( $identifier, $host ) {
		$webfinger_url = 'https://' . $host . '/.well-known/webfinger?resource=' . rawurlencode( $identifier );

		$response = wp_safe_remote_get(
			$webfinger_url,
			array(
				'headers' => array( 'Accept' => 'application/jrd+json' ),
				'redirection' => 0,
				'timeout' => 2,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'webfinger_url_not_accessible', null, $webfinger_url );
		}

		$body = wp_remote_retrieve_body( $response );

		return json_decode( $body, true );
	}

	/**
	 * Undocumented function
	 *
	 * @return void
	 */
	public static function get_remote_follow_endpoint( $uri ) {
		$identifier_and_host = self::get_identifier_and_host( $uri );

		if ( is_wp_error( $identifier_and_host ) ) {
			return $identifier_and_host;
		}

		list( $identifier, $host ) = $identifier_and_host;

		$data = self::get_data( $identifier, $host );

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		if ( empty( $data['links'] ) ) {
			return new WP_Error( 'webfinger_url_invalid_response', null, $data );
		}

		foreach ( $data['links'] as $link ) {
			if ( 'http://ostatus.org/schema/1.0/subscribe' === $link['rel'] ) {
				return $link['template'];
			}
		}

		return new WP_Error( 'webfinger_remote_follow_endpoint_invalid', $data, array( 'status' => 417 ) );
	}
}
