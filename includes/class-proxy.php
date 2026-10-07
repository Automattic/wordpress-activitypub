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
 * Entries are transients, so the backing store is the site's persistent object cache where
 * it has one and the options table where it has not. These cache writes stay out of the
 * database on a site with Redis or memcached; existing actor and post storage is separate.
 * The database fallback uses expiring rows that are not autoloaded. WordPress deletes expired
 * rows on lookup and in its scheduled cleanup.
 * `get_transient()` and `set_transient()` pick the store themselves, which is why nothing
 * here asks `wp_using_ext_object_cache()`: a host changes the backend by dropping in an
 * object cache, and this class does not change at all.
 *
 * An object is stored once, under its declared id. Any other URL it was requested by
 * gets an alias, a bare string holding that id, so dropping the id drops every spelling.
 * A remote document is always an array and an error always an object, so a string can
 * never be mistaken for either.
 *
 * @since unreleased
 */
class Proxy {
	/**
	 * Get a remote object.
	 *
	 * @since unreleased
	 *
	 * An identifier that is not a string, or that hides its authority behind `user@host`, is
	 * refused; see the checks in the body.
	 *
	 * @param string|array $id   The ActivityPub id, an acct identifier, or an object with an id.
	 * @param array        $args {
	 *     Optional. Arguments.
	 *
	 *     @type bool $cached Whether to answer from the cache. A fetched object is stored either way. Default true.
	 *     @type int  $ttl    Seconds to cache a fetched object. Default one hour.
	 * }
	 *
	 * @return array|\WP_Error The object, or an error.
	 */
	public static function get( $id, $args = array() ) {
		$args = \wp_parse_args(
			$args,
			array(
				'cached' => true,
				'ttl'    => HOUR_IN_SECONDS,
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

		/*
		 * An id that hides its authority behind userinfo is refused rather than cleaned up:
		 * RFC 9110 asks a recipient to treat `user@host` in an http(s) URI as an error, and
		 * `wp_http_validate_url()` rejects it, which the normalization below would otherwise
		 * hide by handing validation a URL the sender never sent.
		 */
		if ( ! \is_string( $url ) || null !== \wp_parse_url( $url, PHP_URL_USER ) || null !== \wp_parse_url( $url, PHP_URL_PASS ) ) {
			return new \WP_Error(
				'activitypub_no_valid_object_url',
				\__( 'The "object" is/has no valid URL', 'activitypub' ),
				array(
					'status' => 400,
					'object' => $url,
				)
			);
		}

		// A fragment never reaches the server, and a key id like `…#main-key` must share the actor's entry.
		$url = \strip_fragment_from_url( $url );

		if ( $args['cached'] ) {
			$entry = self::cache_get( $url );
			if ( null !== $entry ) {
				return $entry;
			}
		}

		$first_url = '';
		$final_url = '';
		$object    = self::fetch_verified( $url, $final_url, $first_url );

		/*
		 * Nothing is cached unless the response named the URL it came from. `effective_url()`
		 * cannot always tell, most often because another plugin answered `pre_http_request`,
		 * and a document whose origin is unknown must not be filed under the id it claims: an
		 * open redirect on the requested host would otherwise be enough to put a document, and
		 * its public key, under a name belonging to someone else.
		 */
		$origin_known = '' !== $final_url;

		/*
		 * Never file under the requested URL what another host served: a one-off open
		 * redirect on the requested host would otherwise let that host's key carry the
		 * other host's document, or its outage, for the whole lifetime of the entry.
		 */
		$same_host = $origin_known && is_same_host( $url, $final_url );

		if ( \is_wp_error( $object ) ) {
			// A call that bypassed the cache must not leave a failure behind for the others.
			if ( $same_host && $args['cached'] ) {
				$status = (int) ( $object->get_error_data()['status'] ?? 0 );
				self::cache_set( $url, $object, Http::failure_cache_duration( $status ) );
			}

			return $object;
		}

		/**
		 * Filters how long a fetched object stays in the cache.
		 *
		 * @param int    $ttl    Seconds. Default one hour, or what the caller asked for.
		 * @param string $url    The URL the object was fetched from.
		 * @param array  $object The object.
		 */
		$ttl = (int) \apply_filters( 'activitypub_proxy_cache_ttl', (int) $args['ttl'], $url, $object );

		/*
		 * The declared id confirmed itself, so it is the canonical entry. Its fragment goes the
		 * way the requested URL's went: reads and `delete()` look the entry up without one, so an
		 * id like `…#main-key` would otherwise be written under a name nothing asks for and left
		 * behind when the actor is retired.
		 */
		$canonical = ! empty( $object['id'] ) && \is_string( $object['id'] ) ? \strip_fragment_from_url( $object['id'] ) : '';

		if ( $origin_known && '' !== $canonical ) {
			self::cache_set( $canonical, $object, $ttl );
		}

		// Confirmation must not hide a cross-host or unknown-origin first response.
		if ( $same_host && '' !== $first_url && is_same_host( $url, $first_url ) && $canonical !== $url ) {
			// Without an id, the requested URL is the only name the document has.
			self::cache_set( $url, '' !== $canonical ? $canonical : $object, $ttl );
		}

		return $object;
	}

	/**
	 * Retire what an activity says is gone or has changed.
	 *
	 * The actor is required, and only an actor on the object's own host retires the entry: a
	 * Delete or an Update from a third host would otherwise be able to drop another host's
	 * cached copy and make the site fetch it again at will. The check and the eviction share
	 * one identifier, so the two can never be handed different hosts. Dropping an id also
	 * retires every alias that points at it.
	 *
	 * For evictions the site decides on itself, see {@see Proxy::purge()}.
	 *
	 * @since unreleased
	 *
	 * @param string|array|null $id    The ActivityPub id, or an object with an id.
	 * @param string|array      $actor The actor the activity came from.
	 *
	 * @return bool Whether an entry was removed.
	 */
	public static function delete( $id, $actor ) {
		$url = self::entry_url( $id );

		if ( '' === $url || ! is_same_host( $actor, $url ) ) {
			return false;
		}

		return self::cache_delete( $url );
	}

	/**
	 * Remove an entry because the site decided to.
	 *
	 * For a deletion the site has confirmed itself, or plain housekeeping. Everything an
	 * activity asks for goes through {@see Proxy::delete()}, which requires an actor.
	 *
	 * @since unreleased
	 *
	 * @param string|array|null $id The ActivityPub id, or an object with an id.
	 *
	 * @return bool Whether an entry was removed.
	 */
	public static function purge( $id ) {
		$url = self::entry_url( $id );

		return '' === $url ? false : self::cache_delete( $url );
	}

	/**
	 * The name an entry is stored under, for an id, a URL, or an object carrying one.
	 *
	 * Shared by the two evictions so that an authorization check and the eviction it guards
	 * can never derive the entry from different fields.
	 *
	 * @param string|array|null $id The ActivityPub id, or an object with an id.
	 *
	 * @return string The name, or an empty string when there is none.
	 */
	private static function entry_url( $id ) {
		if ( \is_array( $id ) ) {
			$id = $id['id'] ?? null;
		}

		$url = object_to_uri( $id );

		return \is_string( $url ) && '' !== $url ? \strip_fragment_from_url( $url ) : '';
	}

	/**
	 * Build the transient name for an identifier.
	 *
	 * The identifier is hashed: ActivityPub ids are URLs of any length, and a transient
	 * name may not exceed 191 characters.
	 *
	 * @param string $id The ActivityPub id.
	 *
	 * @return string The transient name.
	 */
	private static function cache_key( $id ) {
		return 'activitypub_object_' . \hash( 'sha256', $id );
	}

	/**
	 * Get an entry from the cache, following one alias.
	 *
	 * @param string $id           The ActivityPub id.
	 * @param bool   $follow_alias Whether an alias is resolved. Default true.
	 *
	 * @return array|\WP_Error|null The entry, or null when there is none.
	 */
	private static function cache_get( $id, $follow_alias = true ) {
		$value = \get_transient( self::cache_key( $id ) );

		if ( false === $value ) {
			return null;
		}

		if ( \is_string( $value ) ) {
			// A dangling alias, or an alias of an alias, is a miss.
			return $follow_alias ? self::cache_get( $value, false ) : null;
		}

		return $value;
	}

	/**
	 * Store an entry in the cache.
	 *
	 * Always with a lifetime: a transient without one becomes an autoloaded option.
	 *
	 * @param string                 $id    The ActivityPub id.
	 * @param array|\WP_Error|string $value The entry, or the id it is an alias of.
	 * @param int                    $ttl   Seconds to keep it. Nothing is written for zero.
	 *
	 * @return void
	 */
	private static function cache_set( $id, $value, $ttl ) {
		if ( $ttl <= 0 ) {
			return;
		}

		\set_transient( self::cache_key( $id ), $value, $ttl );
	}

	/**
	 * Remove an entry from the cache.
	 *
	 * @param string $id The ActivityPub id.
	 *
	 * @return bool Whether an entry was removed.
	 */
	private static function cache_delete( $id ) {
		return (bool) \delete_transient( self::cache_key( $id ) );
	}

	/**
	 * Fetch an object and require it to be served under its own id.
	 *
	 * An object served from a URL other than its id is fetched once more from the id
	 * it declares, which has to confirm it. One hop only.
	 *
	 * @param string $url       The URL to fetch.
	 * @param string $final_url Set to the URL the object was served from, empty when the response
	 *                          does not name it. For a failure, the URL that served the document
	 *                          which failed to confirm, or the last URL attempted on a transport error.
	 * @param string $first_url Set to the first response's URL, empty when its origin is unknown.
	 *
	 * @return array|\WP_Error The object, or an error.
	 */
	private static function fetch_verified( $url, &$final_url, &$first_url ) {
		$object    = self::fetch( $url, $final_url );
		$first_url = $final_url;

		if ( \is_wp_error( $object ) ) {
			return $object;
		}

		// Trust the document when it is served under its own id (after redirects).
		if ( id_matches_url( $object, '' !== $final_url ? $final_url : $url ) ) {
			return $object;
		}

		$declared_id = isset( $object['id'] ) && \is_string( $object['id'] ) ? $object['id'] : '';

		if ( '' === $declared_id ) {
			return $object;
		}

		$object = self::fetch( $declared_id, $final_url );

		if ( \is_wp_error( $object ) ) {
			$final_url = $first_url;

			return $object;
		}

		if ( ! id_matches_url( $object, '' !== $final_url ? $final_url : $declared_id ) ) {
			$final_url = $first_url;

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
	 * @param string $final_url Set to the URL the response was served from, after redirects, and
	 *                          left empty when the response does not name it. A transport error names
	 *                          the last URL attempted, so a redirect's failure belongs to its target.
	 *
	 * @return array|\WP_Error The decoded object, or an error.
	 */
	private static function fetch( $url, &$final_url ) {
		$final_url = '';

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

		/*
		 * Transport errors carry no response URL. Observe redirects so their failure can back off
		 * on the requested host without mistaking a different host's outage for its own.
		 */
		$failure_url    = $url;
		$track_redirect = static function ( $location, $headers, $data, $options, $redirect_response ) use ( &$failure_url ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- The redirect response is the fifth Requests argument.
			if ( $failure_url === $redirect_response->url ) {
				$failure_url = $location;
			}
		};
		\add_action( 'requests-requests.before_redirect', $track_redirect, PHP_INT_MAX, 5 );

		// The proxy owns the caching, so the transport must not cache on its own.
		$response = Http::get( $url, array(), false );
		\remove_action( 'requests-requests.before_redirect', $track_redirect, PHP_INT_MAX );

		if ( \is_wp_error( $response ) ) {
			$data = $response->get_error_data();
			if ( ! empty( $data['effective_url'] ) ) {
				$final_url = $data['effective_url'];
			} elseif ( 0 === (int) ( $data['status'] ?? 0 ) ) {
				$final_url = $failure_url;
			}

			return $response;
		}

		$effective_url = Http::effective_url( $response );
		if ( $effective_url ) {
			$final_url = $effective_url;
		}

		$data = \json_decode( \wp_remote_retrieve_body( $response ), true );

		if ( ! \is_array( $data ) || ! $data || \array_is_list( $data ) ) {
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
}
