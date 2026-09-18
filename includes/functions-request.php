<?php
/**
 * Request functions.
 *
 * Functions for HTTP requests and remote communication.
 *
 * @package Activitypub
 */

namespace Activitypub;

use Activitypub\Collection\Remote_Actors;

/**
 * Send a POST request to a remote server.
 *
 * @param string $url     The URL endpoint.
 * @param string $body    The Post Body.
 * @param int    $user_id The WordPress user ID.
 *
 * @return array|\WP_Error The POST Response or an WP_Error.
 */
function safe_remote_post( $url, $body, $user_id ) {
	return Http::post( $url, $body, $user_id );
}

/**
 * Send a GET request to a remote server.
 *
 * @param string $url The URL endpoint.
 *
 * @return array|\WP_Error The GET Response or an WP_Error.
 */
function safe_remote_get( $url ) {
	return Http::get( $url );
}

/**
 * Check if Authorized-Fetch is enabled.
 *
 * @see https://docs.joinmastodon.org/admin/config/#authorized_fetch
 *
 * @return boolean True if Authorized-Fetch is enabled, false otherwise.
 */
function use_authorized_fetch() {
	$use = (bool) \get_option( 'activitypub_authorized_fetch' );

	/**
	 * Filters whether to use Authorized-Fetch.
	 *
	 * @param boolean $use_authorized_fetch True if Authorized-Fetch is enabled, false otherwise.
	 */
	return \apply_filters( 'activitypub_use_authorized_fetch', $use );
}

/**
 * Check whether the current request should be answered with ActivityPub (JSON).
 *
 * This is the full, plugin-facing check and the one normal plugin code should use. It honors the
 * `?activitypub` query var, an Accept header that prefers ActivityPub (via accept_prefers_activitypub()), and the
 * `activitypub_is_activitypub_request` filter.
 *
 * It depends on Activitypub\Query, the main `$wp_query`, and the plugin being fully loaded, so it
 * must NOT be called from code that runs earlier than that, e.g. a page-cache drop-in deciding a
 * cache key on the serve path. Such code has only the Accept header to go on and must call
 * accept_prefers_activitypub() directly instead.
 *
 * @return bool False by default.
 */
function is_activitypub_request() {
	return Query::get_instance()->is_activitypub_request();
}

/**
 * Check if content negotiation is allowed for a request.
 *
 * @return bool True if content negotiation is allowed, false otherwise.
 */
function should_negotiate_content() {
	return Query::get_instance()->should_negotiate_content();
}

/**
 * Whether the client's most-preferred acceptable media type is ActivityPub.
 *
 * This is only the Accept-header half of content negotiation. Normal plugin code wants
 * is_activitypub_request() instead, which also honors the `?activitypub` query var and the
 * `activitypub_is_activitypub_request` filter; this function ignores both. Its narrow job is to be
 * the single, dependency-free definition of "does this request prefer ActivityPub" that
 * is_activitypub_request() and the Surge cache drop-in (integration/surge-cache-config.php) both
 * use, so the representation the plugin serves and the one the cache keys on can never disagree. The
 * drop-in runs before the plugin loads and cannot call is_activitypub_request(), which is why this
 * half lives on its own.
 *
 * It ranks the listed media types by quality (`;q=`), highest first, breaking ties by the order they
 * appear, and reports whether the winner is an ActivityPub type. That is `application/activity+json`,
 * or `application/ld+json` carrying the ActivityStreams 2.0 profile
 * (`profile="https://www.w3.org/ns/activitystreams"`); plain `application/json` and bare
 * `application/ld+json` are not ActivityPub. This respects the client's preference rather than
 * demanding an ActivityPub-only header: Mastodon sends
 * `application/ld+json;profile="…activitystreams", application/activity+json, text/html;q=0.1`,
 * prefers ActivityPub 10:1, and must get it; a browser lists `text/html` at q=1 and gets HTML. A
 * media type with no `q` defaults to 1.0; a `q=0` refuses the type and is ignored.
 *
 * Pass the RAW, unslashed header; both callers must hand it identical bytes but reach that raw form
 * differently. The plugin runs after wp_magic_quotes() has addslashed $_SERVER, so it wp_unslash()es
 * before calling; the Surge drop-in runs before wp_magic_quotes() and passes its already-raw value as
 * is. This function deliberately does NOT unslash or sanitize: stripslashes() here would strip the
 * drop-in's genuine backslashes (which the plugin's wp_unslash() preserves), and sanitize_text_field()
 * (which the drop-in can't call anyway) would drop bytes such as a `%00`; either would let the two
 * paths disagree. Keep it free of side effects and of any PHP 8 polyfill (str_ends_with()), since the
 * drop-in runs before the polyfills may be loaded.
 *
 * @param string $accept The raw (unslashed) Accept header value.
 *
 * @return bool True when the highest-priority acceptable media type is ActivityPub.
 */
function accept_prefers_activitypub( $accept ) {
	$winner_quality = 0.0;
	$winner_is_ap   = false;

	foreach ( \explode( ',', (string) $accept ) as $part ) {
		$segments   = \explode( ';', $part );
		$media_type = \strtolower( \trim( (string) \array_shift( $segments ) ) );

		if ( '' === $media_type ) {
			continue;
		}

		// Read the quality (default 1.0) and profile parameters, in any order.
		$quality = 1.0;
		$profile = '';
		foreach ( $segments as $param ) {
			$param = \trim( $param );
			if ( 0 === \stripos( $param, 'q=' ) ) {
				// Only a valid number sets the quality; a malformed `q=` keeps the 1.0 default.
				$q_value = \trim( \substr( $param, 2 ) );
				if ( \is_numeric( $q_value ) ) {
					$quality = (float) $q_value;
				}
			} elseif ( 0 === \stripos( $param, 'profile=' ) ) {
				$profile = \strtolower( \trim( \substr( $param, 8 ), '"' ) );
			}
		}

		// A `q=0` means the client refuses this type; ignore it entirely.
		if ( $quality <= 0 ) {
			continue;
		}

		// Highest quality wins; on a tie the earlier type in the header keeps the lead.
		if ( $quality > $winner_quality ) {
			$winner_quality = $quality;

			// ActivityPub is `application/activity+json`, or `application/ld+json` with the AS2 profile
			// (matched without the scheme so both the http and https profile URIs are accepted).
			$winner_is_ap = 'application/activity+json' === $media_type
				|| ( 'application/ld+json' === $media_type && false !== \strpos( $profile, '://www.w3.org/ns/activitystreams' ) );
		}
	}

	return $winner_is_ap;
}

/**
 * Mark a REST response non-shareable, on the response object and as a raw HTTP header.
 *
 * The raw header matters because the REST `_envelope=1` parameter makes WordPress move the response
 * headers into the JSON body and serve an outer response without them, so the response-object
 * Cache-Control would not reach a page cache or CDN. WordPress core sends its own CORS `Vary: Origin`
 * the same raw way for the same reason. The raw header is skipped once the headers are already sent.
 *
 * @param \WP_REST_Response $response The response to mark.
 */
function maybe_set_no_store( $response ) {
	$response->header( 'Cache-Control', 'private, no-store, max-age=0' );

	if ( ! \headers_sent() ) {
		\header( 'Cache-Control: private, no-store, max-age=0' );
	}
}

/**
 * Requests the Meta-Data from the Actors profile.
 *
 * @param array|string $actor  The Actor array or URL.
 * @param bool         $cached Optional. Whether the result should be cached. Default true.
 *
 * @return array|\WP_Error The Actor profile as array or WP_Error on failure.
 */
function get_remote_metadata_by_actor( $actor, $cached = true ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable, Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	/**
	 * Filters the metadata before it is retrieved from a remote actor.
	 *
	 * Passing a non-false value will effectively short-circuit the remote request,
	 * returning that value instead.
	 *
	 * @param mixed  $pre   The value to return instead of the remote metadata.
	 *                      Default false to continue with the remote request.
	 * @param string $actor The actor URL.
	 */
	$pre = \apply_filters( 'pre_get_remote_metadata_by_actor', false, $actor );
	if ( $pre ) {
		return $pre;
	}

	$remote_actor = Remote_Actors::fetch_by_various( $actor );

	if ( \is_wp_error( $remote_actor ) ) {
		return $remote_actor;
	}

	return \json_decode( $remote_actor->post_content, true );
}

/**
 * Resolve a hostname or IP literal to a public IP address.
 *
 * Used as an SSRF guard before opening connections to user-supplied URLs.
 * `wp_safe_remote_get()` ultimately calls `wp_http_validate_url()`, which has
 * a same-host carve-out that lets local/private addresses through when the
 * WordPress site itself is hosted on one. This helper performs an explicit
 * resolve-and-validate without that carve-out, and returns the resolved IP so
 * callers can pin the connection to it (defends against DNS rebinding).
 *
 * Both IPv4 and IPv6 literals are accepted (bracketed IPv6 like `[::1]` is
 * normalised first). For hostnames, A records are looked up via
 * `gethostbynamel()` and AAAA records via `dns_get_record()` when available.
 * Every returned address is validated against private/reserved ranges; a
 * single bad address fails the whole resolution, defending against
 * split-horizon DNS that returns a public answer to one resolver and a
 * private one to another. IPv4 addresses are preferred over IPv6 when both
 * exist, mirroring `wp_safe_remote_get()`'s default.
 *
 * @param string $host The hostname or IP literal to resolve.
 *
 * @return string|false A safe public IP, or false when no safe address is available.
 */
function resolve_public_host( $host ) {
	if ( ! \is_string( $host ) || '' === $host ) {
		return false;
	}

	// Normalise bracketed IPv6 literals (parse_url returns "[::1]").
	$host = \trim( $host, '[]' );

	/**
	 * Filters whether a non-public host may be used.
	 *
	 * Returning true skips this function's private/reserved-range validation and returns the resolved
	 * address as is, for sites that federate over a private network or intranet. A host that does not
	 * resolve at all is still rejected.
	 *
	 * Note: Callers that fetch through WordPress' safe HTTP APIs (wp_safe_remote_get()/post())
	 * are still subject to core's own loopback/RFC1918 rejection outside its same-host exception.
	 * Re-enabling those ranges additionally requires filtering WordPress core (e.g.
	 * http_request_reject_unsafe_urls).
	 *
	 * @param bool   $allow Whether to allow the non-public host. Default false.
	 * @param string $host  The host being resolved.
	 */
	$allow_non_public = \apply_filters( 'activitypub_allow_non_public_host', false, $host );

	// Already an IP literal — validate directly. Accepts IPv4 and IPv6.
	if ( \filter_var( $host, FILTER_VALIDATE_IP ) ) {
		if ( $allow_non_public ) {
			return $host;
		}

		if ( is_unsafe_ipv6_literal( $host ) ) {
			return false;
		}

		return \filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE )
			? $host
			: false;
	}

	/**
	 * Filters the resolved addresses for a hostname before validation.
	 *
	 * Returning a non-null array of `array{ipv4: string[], ipv6: string[]}` skips
	 * the DNS lookup. Tests use this to exercise the validation/preference logic
	 * without making real DNS queries; production code should leave it null.
	 *
	 * @param array{ipv4: string[], ipv6: string[]}|null $pre  Pre-resolved addresses, or null to perform DNS lookup.
	 * @param string                                     $host The hostname being resolved.
	 */
	$pre = \apply_filters( 'activitypub_pre_resolve_public_host', null, $host );

	if ( \is_array( $pre ) ) {
		$ipv4 = isset( $pre['ipv4'] ) && \is_array( $pre['ipv4'] ) ? $pre['ipv4'] : array();
		$ipv6 = isset( $pre['ipv6'] ) && \is_array( $pre['ipv6'] ) ? $pre['ipv6'] : array();
	} else {
		$ipv4 = \gethostbynamel( $host ) ?: array();
		$ipv6 = array();

		if ( \function_exists( 'dns_get_record' ) ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- dns_get_record() emits a warning on lookup failure; we already handle the empty case.
			$aaaa = @\dns_get_record( $host, DNS_AAAA );
			if ( \is_array( $aaaa ) ) {
				foreach ( $aaaa as $record ) {
					if ( ! empty( $record['ipv6'] ) ) {
						$ipv6[] = $record['ipv6'];
					}
				}
			}
		}
	}

	if ( ! $ipv4 && ! $ipv6 ) {
		return false;
	}

	// A host that resolves may be used as is when non-public hosts are explicitly allowed.
	if ( $allow_non_public ) {
		return $ipv4[0] ?? $ipv6[0];
	}

	foreach ( $ipv4 as $ip ) {
		if ( ! \filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
			return false;
		}
	}

	foreach ( $ipv6 as $ip ) {
		if ( is_unsafe_ipv6_literal( $ip ) ) {
			return false;
		}
		if ( ! \filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
			return false;
		}
	}

	return $ipv4[0] ?? $ipv6[0];
}

/**
 * Detect IPv4-mapped IPv6 literals (`::ffff:0:0/96`).
 *
 * PHP's FILTER_FLAG_NO_RES_RANGE catches this range on some builds but not
 * others. These forms serve no legitimate purpose for the SSRF-guard callers,
 * so reject the entire range explicitly via packed-byte comparison.
 *
 * @param string $ip An IP literal.
 *
 * @return bool True if the value is an IPv4-mapped IPv6 address.
 */
function is_ipv4_mapped_ipv6( $ip ) {
	// Short-circuit before inet_pton() so it doesn't emit a warning for non-IP input.
	if ( ! \is_string( $ip ) || ! \filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
		return false;
	}

	$packed = \inet_pton( $ip );

	return false !== $packed
		&& 16 === \strlen( $packed )
		&& "\0\0\0\0\0\0\0\0\0\0\xff\xff" === \substr( $packed, 0, 12 );
}

/**
 * Detect IPv6 literals in transitional / special-use ranges that PHP's
 * FILTER_FLAG_NO_RES_RANGE doesn't reliably block.
 *
 * Covers, in addition to the IPv4-mapped range handled by
 * {@see is_ipv4_mapped_ipv6()}:
 *
 * - `2002::/16` — 6to4 (RFC 3056). Embeds an IPv4 address in the next 32 bits,
 *   so e.g. `2002:7f00:0001::1` routes back to `127.0.0.1` on a host with 6to4.
 * - `2001:0000::/32` — Teredo tunneling (RFC 4380). The check matches the
 *   exact 32-bit prefix `2001:0000`, so legitimate `2001::/16` global unicast
 *   allocations (e.g. Google DNS `2001:4860::/32`) are unaffected. The
 *   `2001:db8::/32` documentation range is also blocked, by its own entry
 *   below — they're separate `2001::/16` sub-allocations.
 * - `2001:db8::/32` — Documentation prefix (RFC 3849); should never be routed.
 * - `64:ff9b::/96` — NAT64 well-known prefix (RFC 6052).
 * - `64:ff9b:1::/48` — NAT64 local-use prefix (RFC 8215).
 * - `100::/64` — Discard prefix (RFC 6666).
 *
 * Returns false for IPv4 literals, hostnames, and IPv6 literals outside the
 * listed ranges.
 *
 * @param string $ip An IP literal.
 *
 * @return bool True if the value is an unsafe IPv6 literal.
 */
function is_unsafe_ipv6_literal( $ip ) {
	if ( ! \is_string( $ip ) || ! \filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
		return false;
	}

	$packed = \inet_pton( $ip );
	if ( false === $packed || 16 !== \strlen( $packed ) ) {
		return false;
	}

	// IPv4-mapped IPv6 prefix.
	if ( "\0\0\0\0\0\0\0\0\0\0\xff\xff" === \substr( $packed, 0, 12 ) ) {
		return true;
	}

	// 6to4 prefix.
	if ( "\x20\x02" === \substr( $packed, 0, 2 ) ) {
		return true;
	}

	// Teredo prefix.
	if ( "\x20\x01\x00\x00" === \substr( $packed, 0, 4 ) ) {
		return true;
	}

	// Documentation prefix.
	if ( "\x20\x01\x0d\xb8" === \substr( $packed, 0, 4 ) ) {
		return true;
	}

	// NAT64 well-known prefix.
	if ( "\x00\x64\xff\x9b\x00\x00\x00\x00\x00\x00\x00\x00" === \substr( $packed, 0, 12 ) ) {
		return true;
	}

	// NAT64 local-use prefix.
	if ( "\x00\x64\xff\x9b\x00\x01" === \substr( $packed, 0, 6 ) ) {
		return true;
	}

	// Discard prefix.
	if ( "\x01\x00\x00\x00\x00\x00\x00\x00" === \substr( $packed, 0, 8 ) ) {
		return true;
	}

	return false;
}
