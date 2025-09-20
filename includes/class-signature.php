<?php
/**
 * Signature class file.
 *
 * @package Activitypub
 */

namespace Activitypub;

use Activitypub\Collection\Actors;
use Activitypub\Collection\Remote_Actors;
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
	 * @param \WP_REST_Request|array $request The request object or $_SERVER array.
	 *
	 * @return bool|\WP_Error A boolean or WP_Error.
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
			$response = \wp_remote_request( $url, $args );
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
	 * Return the public key for a given user.
	 *
	 * @deprecated 7.0.0 Use {@see Actors::get_public_key()}.
	 *
	 * @param int  $user_id The WordPress User ID.
	 * @param bool $force   Optional. Force the generation of a new key pair. Default false.
	 *
	 * @return string The public key.
	 */
	public static function get_public_key_for( $user_id, $force = false ) {
		\_deprecated_function( __METHOD__, '7.0.0', 'Activitypub\Collection\Actors::get_public_key' );

		return Actors::get_public_key( $user_id, $force );
	}

	/**
	 * Return the private key for a given user.
	 *
	 * @deprecated 7.0.0 Use {@see Actors::get_private_key()}.
	 *
	 * @param int  $user_id The WordPress User ID.
	 * @param bool $force   Optional. Force the generation of a new key pair. Default false.
	 *
	 * @return string The private key.
	 */
	public static function get_private_key_for( $user_id, $force = false ) {
		\_deprecated_function( __METHOD__, '7.0.0', 'Activitypub\Collection\Actors::get_private_key' );

		return Actors::get_private_key( $user_id, $force );
	}

	/**
	 * Return the key pair for a given user.
	 *
	 * @deprecated 7.0.0 Use {@see Actors::get_keypair()}.
	 *
	 * @param int $user_id The WordPress User ID.
	 *
	 * @return array The key pair.
	 */
	public static function get_keypair_for( $user_id ) {
		\_deprecated_function( __METHOD__, '7.0.0', 'Activitypub\Collection\Actors::get_keypair' );

		return Actors::get_keypair( $user_id );
	}

	/**
	 * Get public key from key_id.
	 *
	 * @deprecated 7.4.0 Use {@see Remote_Actors::get_public_key()}.
	 *
	 * @param string $key_id The URL to the public key.
	 *
	 * @return resource|\WP_Error The public key resource or WP_Error.
	 */
	public static function get_remote_key( $key_id ) {
		\_deprecated_function( __METHOD__, '7.4.0', 'Activitypub\Collection\Remote_Actors::get_public_key()' );

		return Remote_Actors::get_public_key( $key_id );
	}

	/**
	 * Generates the Signature for an HTTP Request.
	 *
	 * @deprecated 7.0.0 Use {@see Signature::sign_request()}.
	 *
	 * @param int    $user_id     The WordPress User ID.
	 * @param string $http_method The HTTP method.
	 * @param string $url         The URL to send the request to.
	 * @param string $date        The date the request is sent.
	 * @param string $digest      Optional. The digest of the request body. Default null.
	 *
	 * @return string The signature.
	 */
	public static function generate_signature( $user_id, $http_method, $url, $date, $digest = null ) {
		\_deprecated_function( __METHOD__, '7.0.0', self::class . '::sign_request()' );

		$user = Actors::get_by_id( $user_id );
		$key  = Actors::get_private_key( $user_id );

		$url_parts = \wp_parse_url( $url );

		$host = $url_parts['host'];
		$path = '/';

		// Add path.
		if ( ! empty( $url_parts['path'] ) ) {
			$path = $url_parts['path'];
		}

		// Add query.
		if ( ! empty( $url_parts['query'] ) ) {
			$path .= '?' . $url_parts['query'];
		}

		$http_method = \strtolower( $http_method );

		if ( ! empty( $digest ) ) {
			$signed_string = "(request-target): $http_method $path\nhost: $host\ndate: $date\ndigest: $digest";
		} else {
			$signed_string = "(request-target): $http_method $path\nhost: $host\ndate: $date";
		}

		$signature = null;
		\openssl_sign( $signed_string, $signature, $key, \OPENSSL_ALGO_SHA256 );
		$signature = \base64_encode( $signature ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode

		$key_id = $user->get_id() . '#main-key';

		if ( ! empty( $digest ) ) {
			return \sprintf( 'keyId="%s",algorithm="rsa-sha256",headers="(request-target) host date digest",signature="%s"', $key_id, $signature );
		} else {
			return \sprintf( 'keyId="%s",algorithm="rsa-sha256",headers="(request-target) host date",signature="%s"', $key_id, $signature );
		}
	}

	/**
	 * Gets the signature algorithm from the signature header.
	 *
	 * @deprecated 7.0.0 Use {@see Signature::verify()}.
	 *
	 * @param array $signature_block The signature block.
	 *
	 * @return string|bool The signature algorithm or false if not found.
	 */
	public static function get_signature_algorithm( $signature_block ) { // phpcs:ignore
		\_deprecated_function( __METHOD__, '7.0.0', self::class . '::verify' );

		if ( ! empty( $signature_block['algorithm'] ) ) {
			switch ( $signature_block['algorithm'] ) {
				case 'rsa-sha-512':
					return 'sha512'; // hs2019 https://datatracker.ietf.org/doc/html/draft-cavage-http-signatures-12.
				default:
					return 'sha256';
			}
		}

		return false;
	}

	/**
	 * Parses the Signature header.
	 *
	 * @deprecated 7.0.0 Use {@see Signature::verify()}.
	 *
	 * @param string $signature The signature header.
	 *
	 * @return array Signature parts.
	 */
	public static function parse_signature_header( $signature ) { // phpcs:ignore
		\_deprecated_function( __METHOD__, '7.0.0', self::class . '::verify' );

		$parsed_header = array();
		$matches       = array();

		if ( \preg_match( '/keyId="(.*?)"/ism', $signature, $matches ) ) {
			$parsed_header['keyId'] = trim( $matches[1] );
		}
		if ( \preg_match( '/created=["|\']*([0-9]*)["|\']*/ism', $signature, $matches ) ) {
			$parsed_header['(created)'] = trim( $matches[1] );
		}
		if ( \preg_match( '/expires=["|\']*([0-9]*)["|\']*/ism', $signature, $matches ) ) {
			$parsed_header['(expires)'] = trim( $matches[1] );
		}
		if ( \preg_match( '/algorithm="(.*?)"/ism', $signature, $matches ) ) {
			$parsed_header['algorithm'] = trim( $matches[1] );
		}
		if ( \preg_match( '/headers="(.*?)"/ism', $signature, $matches ) ) {
			$parsed_header['headers'] = \explode( ' ', trim( $matches[1] ) );
		}
		if ( \preg_match( '/signature="(.*?)"/ism', $signature, $matches ) ) {
			$parsed_header['signature'] = \base64_decode( preg_replace( '/\s+/', '', trim( $matches[1] ) ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		}

		if ( empty( $parsed_header['headers'] ) ) {
			$parsed_header['headers'] = array( 'date' );
		}

		return $parsed_header;
	}

	/**
	 * Gets the header data from the included pseudo headers.
	 *
	 * @deprecated 7.0.0 Use {@see Signature::verify()}.
	 *
	 * @param array $signed_headers  The signed headers.
	 * @param array $signature_block The signature block.
	 * @param array $headers         The HTTP headers.
	 *
	 * @return string signed headers for comparison
	 */
	public static function get_signed_data( $signed_headers, $signature_block, $headers ) { // phpcs:ignore
		\_deprecated_function( __METHOD__, '7.0.0', self::class . '::verify' );

		$signed_data = '';

		// This also verifies time-based values by returning false if any of these are out of range.
		foreach ( $signed_headers as $header ) {
			if ( 'host' === $header ) {
				if ( isset( $headers['x_original_host'] ) ) {
					$signed_data .= $header . ': ' . $headers['x_original_host'][0] . "\n";
					continue;
				}
			}
			if ( '(request-target)' === $header ) {
				$signed_data .= $header . ': ' . $headers[ $header ][0] . "\n";
				continue;
			}
			if ( str_contains( $header, '-' ) ) {
				$signed_data .= $header . ': ' . $headers[ str_replace( '-', '_', $header ) ][0] . "\n";
				continue;
			}
			if ( '(created)' === $header ) {
				if ( ! empty( $signature_block['(created)'] ) && \intval( $signature_block['(created)'] ) > \time() ) {
					// Created in the future.
					return false;
				}

				if ( ! array_key_exists( '(created)', $headers ) ) {
					$signed_data .= $header . ': ' . $signature_block['(created)'] . "\n";
					continue;
				}
			}
			if ( '(expires)' === $header ) {
				if ( ! empty( $signature_block['(expires)'] ) && \intval( $signature_block['(expires)'] ) < \time() ) {
					// Expired in the past.
					return false;
				}

				if ( ! array_key_exists( '(expires)', $headers ) ) {
					$signed_data .= $header . ': ' . $signature_block['(expires)'] . "\n";
					continue;
				}
			}
			if ( 'date' === $header ) {
				if ( empty( $headers[ $header ][0] ) ) {
					continue;
				}

				// Allow a bit of leeway for misconfigured clocks.
				$d = new \DateTime( $headers[ $header ][0] );
				$d->setTimeZone( new \DateTimeZone( 'UTC' ) );
				$c = $d->format( 'U' );

				$d_plus  = time() + ( 3 * HOUR_IN_SECONDS );
				$d_minus = time() - ( 3 * HOUR_IN_SECONDS );

				if ( $c > $d_plus || $c < $d_minus ) {
					// Time out of range.
					return false;
				}
			}

			if ( ! empty( $headers[ $header ][0] ) ) {
				$signed_data .= $header . ': ' . $headers[ $header ][0] . "\n";
			}
		}

		return \rtrim( $signed_data, "\n" );
	}

	/**
	 * Generates the digest for an HTTP Request.
	 *
	 * @deprecated 7.0.0 Use {@see Signature::sign_request()}.
	 *
	 * @param string $body The body of the request.
	 *
	 * @return string The digest.
	 */
	public static function generate_digest( $body ) {
		\_deprecated_function( __METHOD__, '7.0.0', self::class . '::sign_request' );

		$digest = \base64_encode( \hash( 'sha256', $body, true ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		return "SHA-256=$digest";
	}
}
