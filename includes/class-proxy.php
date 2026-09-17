<?php
/**
 * Proxy class file.
 *
 * @package Activitypub
 */

namespace Activitypub;

/**
 * The one way to get a remote ActivityPub object.
 *
 * Resolves an acct through WebFinger, answers from the cache, and on a miss fetches the
 * object with a signed request, checks that it is served under its own id, and stores it.
 * The REST `proxyUrl` endpoint and the older fetch helpers go through here.
 *
 * @since unreleased
 */
class Proxy {
	/**
	 * The cache namespace for remote objects.
	 *
	 * @var string
	 */
	const CACHE_NAMESPACE = 'object';

	/**
	 * Get a remote object.
	 *
	 * @param string|array $id   The ActivityPub id, an acct identifier, or an object with an id.
	 * @param array        $args {
	 *     Optional. Arguments.
	 *
	 *     @type bool     $cached Whether to read from and write to the cache. Default true.
	 *     @type int|null $ttl    Seconds to cache a fetched object. Default one hour.
	 * }
	 *
	 * @since unreleased
	 *
	 * @return array|\WP_Error The object, or an error.
	 */
	public static function get( $id, $args = array() ) {
		$args = \wp_parse_args(
			$args,
			array(
				'cached' => true,
				'ttl'    => null,
			)
		);

		/**
		 * Filters the remote object before the proxy resolves it.
		 *
		 * Return an array to serve it without a cache lookup or a fetch.
		 *
		 * @param array|null   $object The object, or null to let the proxy resolve it.
		 * @param string|array $id     The ActivityPub id, acct, or object the caller asked for.
		 */
		$pre = \apply_filters( 'activitypub_pre_http_get_remote_object', null, $id );
		if ( null !== $pre ) {
			return $pre;
		}

		$url = object_to_uri( $id );

		if ( Webfinger::is_acct( $url ) ) {
			$url = Webfinger::resolve( $url );
		}

		if ( ! $url ) {
			return new \WP_Error(
				'activitypub_no_valid_actor_identifier',
				\__( 'The "actor" identifier is not valid', 'activitypub' ),
				array(
					'status' => 404,
					'object' => $url,
				)
			);
		}

		if ( \is_wp_error( $url ) ) {
			return $url;
		}

		if ( $args['cached'] ) {
			$entry = cache_get( self::CACHE_NAMESPACE, $url );
			if ( null !== $entry ) {
				return self::unwrap( $entry );
			}
		}

		$final_url = '';
		$object    = self::fetch_verified( $url, $final_url );

		if ( ! $args['cached'] ) {
			return $object;
		}

		/*
		 * Never cache under the requested URL what another host served: a one-off open
		 * redirect on the requested host would otherwise let that host's key carry the
		 * other host's document, or its outage, for the whole lifetime of the entry.
		 */
		$same_host = ! $final_url || is_same_host( $url, $final_url );

		if ( \is_wp_error( $object ) ) {
			if ( $same_host ) {
				cache_set( self::CACHE_NAMESPACE, $url, self::wrap_error( $object ), self::failure_ttl( $object ) );
			}

			return $object;
		}

		$ttl = $args['ttl'] ? (int) $args['ttl'] : HOUR_IN_SECONDS;

		/**
		 * Filters how long a fetched object stays in the cache.
		 *
		 * @param int    $ttl    Seconds. Default one hour, or what the caller asked for.
		 * @param string $url    The URL the object was fetched from.
		 * @param array  $object The object.
		 */
		$ttl = (int) \apply_filters( 'activitypub_proxy_cache_ttl', $ttl, $url, $object );

		if ( $same_host ) {
			cache_set( self::CACHE_NAMESPACE, $url, $object, $ttl );
		}

		// The declared id confirmed itself, so a request by it must hit the same entry.
		if ( ! empty( $object['id'] ) && \is_string( $object['id'] ) && $object['id'] !== $url ) {
			cache_set( self::CACHE_NAMESPACE, $object['id'], $object, $ttl );
		}

		return $object;
	}

	/**
	 * Remove a remote object from the cache.
	 *
	 * @param string|array $id The ActivityPub id, or an object with an id.
	 *
	 * @since unreleased
	 *
	 * @return bool Whether an entry was removed.
	 */
	public static function delete( $id ) {
		$url = object_to_uri( $id );

		return $url ? cache_delete( self::CACHE_NAMESPACE, $url ) : false;
	}

	/**
	 * Fetch a remote object again, replacing the cached one.
	 *
	 * @param string|array $id The ActivityPub id, or an object with an id.
	 *
	 * @since unreleased
	 *
	 * @return array|\WP_Error The object, or an error.
	 */
	public static function refresh( $id ) {
		self::delete( $id );

		return self::get( $id );
	}

	/**
	 * Fetch an object and require it to be served under its own id.
	 *
	 * An object served from a URL other than its id is fetched once more from the id
	 * it declares, which has to confirm it. One hop only.
	 *
	 * @param string $url       The URL to fetch.
	 * @param string $final_url Set to the URL the object, or the failure, was served from.
	 *
	 * @return array|\WP_Error The object, or an error.
	 */
	private static function fetch_verified( $url, &$final_url ) {
		$object = self::fetch( $url, $final_url );

		if ( \is_wp_error( $object ) ) {
			return $object;
		}

		// Trust the document when it is served under its own id (after redirects).
		if ( id_matches_url( $object, $final_url ) ) {
			return $object;
		}

		$declared_id = isset( $object['id'] ) && \is_string( $object['id'] ) ? $object['id'] : '';

		if ( '' === $declared_id ) {
			return $object;
		}

		$object = self::fetch( $declared_id, $final_url );

		if ( \is_wp_error( $object ) ) {
			return $object;
		}

		if ( ! id_matches_url( $object, $final_url ) ) {
			return new \WP_Error(
				'activitypub_object_id_mismatch',
				\__( 'The object id does not match the URL it was served from', 'activitypub' ),
				array( 'status' => 400 )
			);
		}

		return $object;
	}

	/**
	 * Fetch a URL and decode the JSON it serves.
	 *
	 * @param string $url       The URL to fetch.
	 * @param string $final_url Set to the URL the response was served from, after redirects.
	 *
	 * @return array|\WP_Error The decoded object, or an error.
	 */
	private static function fetch( $url, &$final_url ) {
		$final_url = $url;

		if ( ! \wp_http_validate_url( $url ) ) {
			return new \WP_Error(
				'activitypub_no_valid_object_url',
				\__( 'The "object" is/has no valid URL', 'activitypub' ),
				array(
					'status' => 400,
					'object' => $url,
				)
			);
		}

		// The proxy owns the caching, so the transport must not cache on its own.
		$response = Http::get( $url, array(), false );

		if ( \is_wp_error( $response ) ) {
			$data = $response->get_error_data();
			if ( ! empty( $data['effective_url'] ) ) {
				$final_url = $data['effective_url'];
			}

			return $response;
		}

		$effective_url = Http::effective_url( $response );
		if ( $effective_url ) {
			$final_url = $effective_url;
		}

		$data = \json_decode( \wp_remote_retrieve_body( $response ), true );

		if ( ! $data ) {
			return new \WP_Error(
				'activitypub_invalid_json',
				\__( 'No valid JSON data', 'activitypub' ),
				array(
					'status' => 400,
					'object' => $url,
				)
			);
		}

		return $data;
	}

	/**
	 * How long a failed fetch is remembered.
	 *
	 * Short for errors worth retrying and for connection failures, longer for the rest.
	 *
	 * @param \WP_Error $error The error.
	 *
	 * @return int Seconds.
	 */
	private static function failure_ttl( $error ) {
		$code = (int) $error->get_error_code();

		if ( 0 === $code || \in_array( $code, ACTIVITYPUB_RETRY_ERROR_CODES, true ) ) {
			return MINUTE_IN_SECONDS;
		}

		return 15 * MINUTE_IN_SECONDS;
	}

	/**
	 * Turn an error into a cacheable entry.
	 *
	 * @param \WP_Error $error The error.
	 *
	 * @return array The entry.
	 */
	private static function wrap_error( $error ) {
		return array(
			'__error' => array(
				'code'    => $error->get_error_code(),
				'message' => $error->get_error_message(),
				'data'    => $error->get_error_data(),
			),
		);
	}

	/**
	 * Turn a cache entry back into an object or the error it stands for.
	 *
	 * @param array $entry The entry.
	 *
	 * @return array|\WP_Error The object, or the remembered error.
	 */
	private static function unwrap( $entry ) {
		if ( isset( $entry['__error'] ) ) {
			return new \WP_Error( $entry['__error']['code'], $entry['__error']['message'], $entry['__error']['data'] );
		}

		return $entry;
	}
}
