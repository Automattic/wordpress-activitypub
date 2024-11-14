<?php
/**
 * WebFinger class file.
 *
 * @package Activitypub
 */

namespace Activitypub;

use WP_Error;
use Activitypub\Collection\Actors;

/**
 * ActivityPub WebFinger Class.
 *
 * @author Matthias Pfefferle
 *
 * @see https://webfinger.net/
 */
class Webfinger {
	/**
	 * Returns a users WebFinger "resource".
	 *
	 * @param int $user_id The WordPress user id.
	 *
	 * @return string The user-resource.
	 */
	public static function get_user_resource( $user_id ) {
		$user = Actors::get_by_id( $user_id );
		if ( ! $user || is_wp_error( $user ) ) {
			return '';
		}

		return $user->get_webfinger();
	}

	/**
	 * Resolve a WebFinger resource.
	 *
	 * @param string $uri The WebFinger Resource.
	 *
	 * @return string|WP_Error The URL or WP_Error.
	 */
	public static function resolve( $uri ) {
		$data = self::get_data( $uri );

		if ( \is_wp_error( $data ) ) {
			return $data;
		}

		if ( ! is_array( $data ) || empty( $data['links'] ) ) {
			return new WP_Error(
				'webfinger_missing_links',
				__( 'No valid Link elements found.', 'activitypub' ),
				array(
					'status' => 400,
					'data'   => $data,
				)
			);
		}

		foreach ( $data['links'] as $link ) {
			if (
				'self' === $link['rel'] &&
				(
					'application/activity+json' === $link['type'] ||
					'application/ld+json; profile="https://www.w3.org/ns/activitystreams"' === $link['type']
				)
			) {
				return $link['href'];
			}
		}

		return new WP_Error(
			'webfinger_url_no_activitypub',
			__( 'The Site supports WebFinger but not ActivityPub', 'activitypub' ),
			array(
				'status' => 400,
				'data'   => $data,
			)
		);
	}

	/**
	 * Transform a URI to an acct <identifier>@<host>.
	 *
	 * @param string $uri The URI (acct:, mailto:, http:, https:).
	 *
	 * @return string|WP_Error Error or acct URI.
	 */
	public static function uri_to_acct( $uri ) {
		$data = self::get_data( $uri );

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		// Check if subject is an acct URI.
		if (
			isset( $data['subject'] ) &&
			\str_starts_with( $data['subject'], 'acct:' )
		) {
			return $data['subject'];
		}

		// Search for an acct URI in the aliases.
		if ( isset( $data['aliases'] ) ) {
			foreach ( $data['aliases'] as $alias ) {
				if ( \str_starts_with( $alias, 'acct:' ) ) {
					return $alias;
				}
			}
		}

		return new WP_Error(
			'webfinger_url_no_acct',
			__( 'No acct URI found.', 'activitypub' ),
			array(
				'status' => 400,
				'data'   => $data,
			)
		);
	}

	/**
	 * Convert a URI string to an identifier and its host.
	 * Automatically adds acct: if it's missing.
	 *
	 * @param string $url The URI (acct:, mailto:, http:, https:).
	 *
	 * @return WP_Error|array Error reaction or array with identifier and host as values.
	 */
	public static function get_identifier_and_host( $url ) {
		if ( ! $url ) {
			return new WP_Error(
				'webfinger_invalid_identifier',
				__( 'Invalid Identifier', 'activitypub' ),
				array(
					'status' => 400,
					'data'   => $url,
				)
			);
		}

		// Remove leading @.
		$url = ltrim( $url, '@' );

		if ( ! preg_match( '/^([a-zA-Z+]+):/', $url, $match ) ) {
			$identifier = 'acct:' . $url;
			$scheme     = 'acct';
		} else {
			$identifier = $url;
			$scheme     = $match[1];
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
			return new WP_Error(
				'webfinger_invalid_identifier',
				__( 'Invalid Identifier', 'activitypub' ),
				array(
					'status' => 400,
					'data'   => $url,
				)
			);
		}

		return array( $identifier, $host );
	}

	/**
	 * Get the WebFinger data for a given URI.
	 *
	 * @param string $uri The Identifier: <identifier>@<host> or URI.
	 *
	 * @return WP_Error|array Error reaction or array with identifier and host as values.
	 */
	public static function get_data( $uri ) {
		$identifier_and_host = self::get_identifier_and_host( $uri );

		if ( is_wp_error( $identifier_and_host ) ) {
			return $identifier_and_host;
		}

		$transient_key = self::generate_cache_key( $uri );

		list( $identifier, $host ) = $identifier_and_host;

		$data = \get_transient( $transient_key );
		if ( $data ) {
			return $data;
		}

		$webfinger_url = sprintf(
			'https://%s/.well-known/webfinger?resource=%s',
			$host,
			rawurlencode( $identifier )
		);

		$response = wp_safe_remote_get(
			$webfinger_url,
			array(
				'headers' => array( 'Accept' => 'application/jrd+json' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'webfinger_url_not_accessible',
				__( 'The WebFinger Resource is not accessible.', 'activitypub' ),
				array(
					'status' => 400,
					'data'   => $webfinger_url,
				)
			);
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		\set_transient( $transient_key, $data, WEEK_IN_SECONDS );

		return $data;
	}

	/**
	 * Get the Remote-Follow endpoint for a given URI.
	 *
	 * @param string $uri The WebFinger Resource URI.
	 *
	 * @return string|WP_Error Error or the Remote-Follow endpoint URI.
	 */
	public static function get_remote_follow_endpoint( $uri ) {
		$data = self::get_data( $uri );

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		if ( empty( $data['links'] ) ) {
			return new WP_Error(
				'webfinger_missing_links',
				__( 'No valid Link elements found.', 'activitypub' ),
				array(
					'status' => 400,
					'data'   => $data,
				)
			);
		}

		foreach ( $data['links'] as $link ) {
			if ( 'http://ostatus.org/schema/1.0/subscribe' === $link['rel'] ) {
				return $link['template'];
			}
		}

		return new WP_Error(
			'webfinger_missing_remote_follow_endpoint',
			__( 'No valid Remote-Follow endpoint found.', 'activitypub' ),
			array(
				'status' => 400,
				'data'   => $data,
			)
		);
	}

	/**
	 * Generate a cache key for a given URI.
	 *
	 * @param string $uri A WebFinger Resource URI.
	 *
	 * @return string The cache key.
	 */
	public static function generate_cache_key( $uri ) {
		$uri = ltrim( $uri, '@' );

		if ( filter_var( $uri, FILTER_VALIDATE_EMAIL ) ) {
			$uri = 'acct:' . $uri;
		}

		return 'webfinger_' . md5( $uri );
	}
}
