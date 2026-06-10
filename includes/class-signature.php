<?php
/**
 * Signature class file.
 *
 * @package Activitypub
 */

namespace Activitypub;

use Activitypub\Signature\Http_Message_Signature;
use Activitypub\Signature\Http_Signature_Draft;

/**
 * ActivityPub Signature Class.
 *
 * @author Matthias Pfefferle
 * @author Django Doucet
 */
class Signature {

	/**
	 * Initialize the class.
	 */
	public static function init() {
		\add_filter( 'http_request_args', array( self::class, 'sign_request' ), 0, 2 ); // Ahead of all other filters, so signature is set.
		\add_filter( 'http_response', array( self::class, 'maybe_double_knock' ), 10, 3 );
	}

	/**
	 * Sign an HTTP Request.
	 *
	 * @param array  $args An array of HTTP request arguments.
	 * @param string $url  The request URL.
	 *
	 * @return array Request arguments with signature headers.
	 */
	public static function sign_request( $args, $url ) {
		// Bail if there's nothing to sign with.
		if ( ! isset( $args['key_id'], $args['private_key'] ) ) {
			return $args;
		}

		if ( '1' === \get_option( 'activitypub_rfc9421_signature' ) && self::could_support_rfc9421( $url ) ) {
			$signature = new Http_Message_Signature();
		} else {
			$signature = new Http_Signature_Draft();
		}

		return $signature->sign( $args, $url );
	}

	/**
	 * Verifies the http signatures
	 *
	 * On success the verified keyId is returned (a truthy string), so callers can bind it to
	 * the activity actor without re-parsing headers, which cannot tell which signature label
	 * actually validated. Pass/fail callers should branch on {@see is_wp_error()} as before.
	 *
	 * @since unreleased Returns the verified keyId on success instead of `true`.
	 *
	 * @param \WP_REST_Request|array $request The request object or $_SERVER array.
	 *
	 * @return string|\WP_Error The verified keyId on success, WP_Error on failure.
	 */
	public static function verify_http_signature( $request ) {
		if ( is_object( $request ) ) { // REST Request object.
			$body                           = $request->get_body();
			$headers                        = $request->get_headers();
			$headers['(request-target)'][0] = strtolower( $request->get_method() ) . ' ' . self::get_route( $request );
		} else {
			$headers                        = self::format_server_request( $request );
			$headers['(request-target)'][0] = strtolower( $headers['request_method'][0] ) . ' ' . $headers['request_uri'][0];
		}

		$signature = isset( $headers['signature_input'] ) ? new Http_Message_Signature() : new Http_Signature_Draft();

		return $signature->verify( $headers, $body ?? null );
	}

	/**
	 * Extract the signing keyId that {@see Signature::verify_http_signature()} would verify against.
	 *
	 * The returned keyId is only trustworthy if it identifies the key the signature is
	 * actually checked with, so this mirrors the verifier's header choice rather than
	 * scanning headers in an arbitrary order:
	 *
	 * - When a `Signature-Input` header is present the RFC 9421 verifier is used, so the
	 *   keyId is taken from there and a draft `Signature` header (which the verifier ignores)
	 *   is not consulted. The RFC 9421 verifier accepts whichever of several signature labels
	 *   validates, so a `Signature-Input` carrying more than one keyId is ambiguous: we cannot
	 *   know in advance which key will verify and must not guess, so `null` is returned.
	 * - Otherwise the draft HTTP Signatures form is used, taking the first `keyId` from the
	 *   `Signature` header or, failing that, the `Authorization` header — matching the draft
	 *   verifier, which reads `signature ?? authorization`.
	 *
	 * @since unreleased
	 *
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @return string|null The keyId, or null when none is present or the choice is ambiguous.
	 */
	public static function get_key_id( $request ) {
		$signature_input = $request->get_header( 'signature-input' );
		if ( $signature_input ) {
			/*
			 * keyid is a `;`-delimited parameter whose value may be quoted or unquoted.
			 * Anchoring on `;` (or string start) avoids matching a `keyid=` substring inside
			 * another parameter's value. Count every label's keyId: more than one is ambiguous.
			 */
			$count = \preg_match_all( '/(?:^|;)\s*keyid="?([^";,\s]+)/i', $signature_input, $matches );

			return 1 === $count ? $matches[1][0] : null;
		}

		// A draft signature may arrive in the Signature header or, less commonly, Authorization.
		$signature = $request->get_header( 'signature' );
		if ( ! $signature ) {
			$signature = $request->get_header( 'authorization' );
		}

		if ( $signature && \preg_match( '/keyId="([^"]+)"/i', $signature, $matches ) ) {
			return $matches[1];
		}

		return null;
	}

	/**
	 * If a request with RFC-9421 signature fails, we try again with the Draft Cavage signature.
	 *
	 * @param array  $response HTTP response.
	 * @param array  $args     HTTP request arguments.
	 * @param string $url      The request URL.
	 *
	 * @return array The HTTP response.
	 */
	public static function maybe_double_knock( $response, $args, $url ) {
		// Bail if it didn't use an RFC-9421 signature or there's nothing to sign with.
		if ( ! isset( $args['key_id'], $args['private_key'], $args['headers']['Signature-Input'] ) ) {
			return $response;
		}

		$response_code = \wp_remote_retrieve_response_code( $response );

		// Fall back to Draft Cavage signature for any 4xx responses.
		if ( $response_code >= 400 && $response_code < 500 ) {
			unset( $args['headers']['Signature'], $args['headers']['Signature-Input'], $args['headers']['Content-Digest'] );
			self::rfc9421_add_unsupported_host( $url );

			$args     = ( new Http_Signature_Draft() )->sign( $args, $url );
			$response = \wp_safe_remote_request( $url, $args );
		}

		return $response;
	}

	/**
	 * Formats the $_SERVER to resemble the WP_REST_REQUEST array,
	 * for use with verify_http_signature().
	 *
	 * @param array $server The $_SERVER array.
	 *
	 * @return array $request The formatted request array.
	 */
	public static function format_server_request( $server ) {
		$headers = array();

		foreach ( $server as $key => $value ) {
			$key               = \str_replace( 'http_', '', \strtolower( $key ) );
			$headers[ $key ][] = \wp_unslash( $value );

		}

		return $headers;
	}

	/**
	 * Returns route.
	 *
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @return string
	 */
	private static function get_route( $request ) {
		// Check if the route starts with "index.php".
		if ( str_starts_with( $request->get_route(), '/index.php' ) || ! rest_get_url_prefix() ) {
			$route = $request->get_route();
		} else {
			$route = '/' . rest_get_url_prefix() . '/' . ltrim( $request->get_route(), '/' );
		}

		// Fix route for subdirectory installations.
		$path = \wp_parse_url( \get_home_url(), PHP_URL_PATH );

		if ( \is_string( $path ) ) {
			$path = trim( $path, '/' );
		}

		if ( $path ) {
			$route = '/' . $path . $route;
		}

		/*
		 * Append the query string. Peers sign the full request-target including
		 * the query (see Http_Signature_Draft::sign()), so the reconstructed
		 * value has to match byte-for-byte. Use the raw REQUEST_URI instead of
		 * re-encoding the parsed query params, re-encoding could change the
		 * percent-encoding or parameter order and break the signature.
		 */
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$query = (string) \wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', \PHP_URL_QUERY );

		if ( '' !== $query ) {
			$route .= '?' . $query;
		}

		return $route;
	}

	/**
	 * Check if RFC-9421 signature could be supported.
	 *
	 * @param string $url The URL to check.
	 *
	 * @return bool True, if RFC-9421 signature could be supported, false otherwise.
	 */
	private static function could_support_rfc9421( $url ) {
		$host = \wp_parse_url( $url, \PHP_URL_HOST );
		$list = \get_option( 'activitypub_rfc9421_unsupported', array() );

		if ( isset( $list[ $host ] ) ) {
			if ( $list[ $host ] > \time() ) {
				return false;
			}

			unset( $list[ $host ] );
			\update_option( 'activitypub_rfc9421_unsupported', $list );
		}

		return true;
	}

	/**
	 * Set RFC-9421 signature unsupported for a given host.
	 *
	 * @param string $url The URL to set.
	 */
	private static function rfc9421_add_unsupported_host( $url ) {
		$list = \get_option( 'activitypub_rfc9421_unsupported', array() );
		$host = \wp_parse_url( $url, \PHP_URL_HOST );

		$list[ $host ] = \time() + MONTH_IN_SECONDS;
		\update_option( 'activitypub_rfc9421_unsupported', $list, false );
	}

	/**
	 * Compute the collection digest for a specific instance.
	 *
	 * Implements FEP-8fcf: Followers collection synchronization.
	 * The digest is created by XORing together the individual SHA256 digests
	 * of each follower's ID.
	 *
	 * @see https://codeberg.org/fediverse/fep/src/branch/main/fep/8fcf/fep-8fcf.md
	 *
	 * @param array $collection The user ID whose followers to compute.
	 *
	 * @return string|false The hex-encoded digest, or false if no followers.
	 */
	public static function get_collection_digest( $collection ) {
		if ( empty( $collection ) || ! is_array( $collection ) ) {
			return false;
		}

		// Initialize with zeros (64 hex chars = 32 bytes = 256 bits).
		$digest = str_repeat( '0', 64 );

		foreach ( $collection as $item ) {
			// Compute SHA256 hash of the follower ID.
			$hash = hash( 'sha256', $item );

			// XOR the hash with the running digest.
			$digest = self::xor_hex_strings( $digest, $hash );
		}

		return $digest;
	}

	/**
	 * XOR two hexadecimal strings.
	 *
	 * Used for FEP-8fcf digest computation.
	 *
	 * @param string $hex1 First hex string.
	 * @param string $hex2 Second hex string.
	 *
	 * @return string The XORed result as a hex string.
	 */
	public static function xor_hex_strings( $hex1, $hex2 ) {
		$result = '';

		// Ensure both strings are the same length (should be 64 chars for SHA256).
		$length = \max( \strlen( $hex1 ), \strlen( $hex2 ) );
		$hex1   = \str_pad( $hex1, $length, '0', STR_PAD_LEFT );
		$hex2   = \str_pad( $hex2, $length, '0', STR_PAD_LEFT );

		// XOR each pair of hex digits.
		for ( $i = 0; $i < $length; $i += 2 ) {
			$byte1   = \hexdec( \substr( $hex1, $i, 2 ) );
			$byte2   = \hexdec( \substr( $hex2, $i, 2 ) );
			$result .= \str_pad( \dechex( $byte1 ^ $byte2 ), 2, '0', STR_PAD_LEFT );
		}

		return $result;
	}

	/**
	 * Parse a Collection-Synchronization header (FEP-8fcf).
	 *
	 * Parses the signature-style format used by the Collection-Synchronization header.
	 *
	 * @see https://codeberg.org/fediverse/fep/src/branch/main/fep/8fcf/fep-8fcf.md
	 *
	 * @param string $header The header value.
	 *
	 * @return array|false Array with parsed parameters (collectionId, url, digest), or false on failure.
	 */
	public static function parse_collection_sync_header( $header ) {
		if ( empty( $header ) ) {
			return false;
		}

		// Parse the signature-style format: key="value", key="value".
		$params = array();

		if ( \preg_match_all( '/(\w+)="([^"]*)"/', $header, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$params[ $match[1] ] = $match[2];
			}
		}

		// Validate required fields for FEP-8fcf.
		if ( empty( $params['collectionId'] ) || empty( $params['url'] ) || empty( $params['digest'] ) ) {
			return false;
		}

		return $params;
	}
}
