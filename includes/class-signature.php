<?php
/**
 * Signature class file.
 *
 * @package Activitypub
 */

namespace Activitypub;

use Activitypub\Collection\Actors;
use Activitypub\Signature\Draft_Cavage_Signature;
use Activitypub\Signature\Http_Message_Signature;

/**
 * ActivityPub Signature Class.
 *
 * @author Matthias Pfefferle
 * @author Django Doucet
 */
class Signature {

	/**
	 * Return the public key for a given user.
	 *
	 * @deprecated unreleased Use {@see Actors::get_public_key()}.
	 *
	 * @param int  $user_id The WordPress User ID.
	 * @param bool $force   Optional. Force the generation of a new key pair. Default false.
	 *
	 * @return string The public key.
	 */
	public static function get_public_key_for( $user_id, $force = false ) {
		\_deprecated_function( __METHOD__, 'unreleased', 'Activitypub\Collection\Actors::get_public_key' );

		return Actors::get_public_key( $user_id, $force );
	}

	/**
	 * Return the private key for a given user.
	 *
	 * @deprecated unreleased Use {@see Actors::get_private_key()}.
	 *
	 * @param int  $user_id The WordPress User ID.
	 * @param bool $force   Optional. Force the generation of a new key pair. Default false.
	 *
	 * @return string The private key.
	 */
	public static function get_private_key_for( $user_id, $force = false ) {
		\_deprecated_function( __METHOD__, 'unreleased', 'Activitypub\Collection\Actors::get_private_key' );

		return Actors::get_private_key( $user_id, $force );
	}

	/**
	 * Return the key pair for a given user.
	 *
	 * @deprecated unreleased Use {@see Actors::get_keypair()}.
	 *
	 * @param int $user_id The WordPress User ID.
	 *
	 * @return array The key pair.
	 */
	public static function get_keypair_for( $user_id ) {
		\_deprecated_function( __METHOD__, 'unreleased', 'Activitypub\Collection\Actors::get_keypair' );

		return Actors::get_keypair( $user_id );
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

		$args = \wp_parse_args(
			$args,
			array(
				'method'  => 'GET',
				'headers' => array(
					'Date' => \gmdate( 'D, d M Y H:i:s T' ),
				),
			)
		);

		if ( '1' === \get_option( 'activitypub_rfc9421_signature' ) ) {
			$signature = new Http_Message_Signature();
			\add_filter( 'http_response', array( self::class, 'maybe_double_knock' ), 10, 3 );
		} else {
			$signature = new Draft_Cavage_Signature();
		}

		return $signature->sign( $args, $url );
	}

	/**
	 * Generates the Signature for an HTTP Request.
	 *
	 * @deprecated unreleased Use {@see Signature::sign_request()}.
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
		\_deprecated_function( __METHOD__, 'unreleased', self::class . '::sign_request()' );

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
			$request                        = self::format_server_request( $request );
			$headers                        = $request['headers']; // $_SERVER array
			$headers['(request-target)'][0] = strtolower( $headers['request_method'][0] ) . ' ' . $headers['request_uri'][0];
		}

		$signature = isset( $headers['signature_input'] ) ? new Http_Message_Signature() : new Draft_Cavage_Signature();

		return $signature->verify( $headers, $body ?? null );
	}

	/**
	 * Get public key from key_id.
	 *
	 * @deprecated unreleased Use {@see Actors::get_remote_key()}.
	 *
	 * @param string $key_id The URL to the public key.
	 *
	 * @return resource|\WP_Error The public key resource or WP_Error.
	 */
	public static function get_remote_key( $key_id ) {
		\_deprecated_function( __METHOD__, 'unreleased', Actors::class . '::get_remote_key()' );

		return Actors::get_remote_key( $key_id );
	}

	/**
	 * If a request with RFC-9421 signature fails, we try again with the Draft Cavage signature.
	 *
	 * @param array  $response    HTTP response.
	 * @param array  $parsed_args HTTP request arguments.
	 * @param string $url         The request URL.
	 *
	 * @return array The HTTP response.
	 */
	public static function maybe_double_knock( $response, $parsed_args, $url ) {
		// Remove this filter to prevent infinite recursion.
		\remove_filter( 'http_response', array( self::class, 'maybe_double_knock' ) );

		if ( 401 === wp_remote_retrieve_response_code( $response ) ) {
			unset( $parsed_args['headers']['Signature'], $parsed_args['headers']['Signature-Input'], $parsed_args['headers']['Content-Digest'] );

			$parsed_args = ( new Draft_Cavage_Signature() )->sign( $parsed_args, $url );
			$response    = \wp_remote_request( $url, $parsed_args );
		}

		return $response;
	}

	/**
	 * Gets the signature algorithm from the signature header.
	 *
	 * @param array $signature_block The signature block.
	 *
	 * @return string|bool The signature algorithm or false if not found.
	 */
	public static function get_signature_algorithm( $signature_block ) { // phpcs:ignore
		_deprecated_function( __METHOD__, 'unreleased', self::class . '::verify' );

		return false;
	}

	/**
	 * Parses the Signature header.
	 *
	 * @param string $signature The signature header.
	 *
	 * @return array Signature parts.
	 */
	public static function parse_signature_header( $signature ) { // phpcs:ignore
		_deprecated_function( __METHOD__, 'unreleased', self::class . '::verify' );

		return array();
	}

	/**
	 * Gets the header data from the included pseudo headers.
	 *
	 * @param array $signed_headers  The signed headers.
	 * @param array $signature_block The signature block.
	 * @param array $headers         The HTTP headers.
	 *
	 * @return string signed headers for comparison
	 */
	public static function get_signed_data( $signed_headers, $signature_block, $headers ) { // phpcs:ignore
		_deprecated_function( __METHOD__, 'unreleased', self::class . '::verify' );

		return '';
	}

	/**
	 * Generates the digest for an HTTP Request.
	 *
	 * @deprecated unreleased Use {@see Signature::sign_request()}.
	 *
	 * @param string $body The body of the request.
	 *
	 * @return string The digest.
	 */
	public static function generate_digest( $body ) {
		\_deprecated_function( __METHOD__, 'unreleased', self::class . '::sign_request' );

		$digest = \base64_encode( \hash( 'sha256', $body, true ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		return "SHA-256=$digest";
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
		$request = array();
		foreach ( $server as $param_key => $param_val ) {
			$req_param = strtolower( $param_key );
			if ( 'REQUEST_URI' === $req_param ) {
				$request['headers']['route'][] = $param_val;
			} else {
				$header_key                          = str_replace(
					'http_',
					'',
					$req_param
				);
				$request['headers'][ $header_key ][] = \wp_unslash( $param_val );
			}
		}
		return $request;
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
}
