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
	 * @param int  $user_id The WordPress User ID.
	 * @param bool $force   Optional. Force the generation of a new key pair. Default false.
	 *
	 * @return mixed The public key.
	 */
	public static function get_public_key_for( $user_id, $force = false ) {
		if ( $force ) {
			self::generate_key_pair_for( $user_id );
		}

		$key_pair = self::get_keypair_for( $user_id );

		return $key_pair['public_key'];
	}

	/**
	 * Return the private key for a given user.
	 *
	 * @param int  $user_id The WordPress User ID.
	 * @param bool $force   Optional. Force the generation of a new key pair. Default false.
	 *
	 * @return mixed The private key.
	 */
	public static function get_private_key_for( $user_id, $force = false ) {
		if ( $force ) {
			self::generate_key_pair_for( $user_id );
		}

		$key_pair = self::get_keypair_for( $user_id );

		return $key_pair['private_key'];
	}

	/**
	 * Return the key pair for a given user.
	 *
	 * @param int $user_id The WordPress User ID.
	 *
	 * @return array The key pair.
	 */
	public static function get_keypair_for( $user_id ) {
		$option_key = self::get_signature_options_key_for( $user_id );
		$key_pair   = \get_option( $option_key );

		if ( ! $key_pair ) {
			$key_pair = self::generate_key_pair_for( $user_id );
		}

		return $key_pair;
	}

	/**
	 * Generates the pair keys
	 *
	 * @param int $user_id The WordPress User ID.
	 *
	 * @return array The key pair.
	 */
	protected static function generate_key_pair_for( $user_id ) {
		$option_key = self::get_signature_options_key_for( $user_id );
		$key_pair   = self::check_legacy_key_pair_for( $user_id );

		if ( $key_pair ) {
			\add_option( $option_key, $key_pair );

			return $key_pair;
		}

		$config = array(
			'digest_alg'       => 'sha512',
			'private_key_bits' => 2048,
			'private_key_type' => \OPENSSL_KEYTYPE_RSA,
		);

		$key         = \openssl_pkey_new( $config );
		$private_key = null;
		$detail      = array();
		if ( $key ) {
			\openssl_pkey_export( $key, $private_key );

			$detail = \openssl_pkey_get_details( $key );
		}

		// Check if keys are valid.
		if (
			empty( $private_key ) || ! is_string( $private_key ) ||
			! isset( $detail['key'] ) || ! is_string( $detail['key'] )
		) {
			return array(
				'private_key' => null,
				'public_key'  => null,
			);
		}

		$key_pair = array(
			'private_key' => $private_key,
			'public_key'  => $detail['key'],
		);

		// Persist keys.
		\add_option( $option_key, $key_pair );

		return $key_pair;
	}

	/**
	 * Return the option key for a given user.
	 *
	 * @param int $user_id The WordPress User ID.
	 *
	 * @return string The option key.
	 */
	protected static function get_signature_options_key_for( $user_id ) {
		$id = $user_id;

		if ( $user_id > 0 ) {
			$user = \get_userdata( $user_id );
			// Sanitize username because it could include spaces and special chars.
			$id = sanitize_title( $user->user_login );
		}

		return 'activitypub_keypair_for_' . $id;
	}

	/**
	 * Check if there is a legacy key pair
	 *
	 * @param int $user_id The WordPress User ID.
	 *
	 * @return array|bool The key pair or false.
	 */
	protected static function check_legacy_key_pair_for( $user_id ) {
		switch ( $user_id ) {
			case 0:
				$public_key  = \get_option( 'activitypub_blog_user_public_key' );
				$private_key = \get_option( 'activitypub_blog_user_private_key' );
				break;
			case -1:
				$public_key  = \get_option( 'activitypub_application_user_public_key' );
				$private_key = \get_option( 'activitypub_application_user_private_key' );
				break;
			default:
				$public_key  = \get_user_meta( $user_id, 'magic_sig_public_key', true );
				$private_key = \get_user_meta( $user_id, 'magic_sig_private_key', true );
				break;
		}

		if ( ! empty( $public_key ) && is_string( $public_key ) && ! empty( $private_key ) && is_string( $private_key ) ) {
			return array(
				'private_key' => $private_key,
				'public_key'  => $public_key,
			);
		}

		return false;
	}

	/**
	 * Generates the Signature for an HTTP Request.
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
		$user = Actors::get_by_id( $user_id );
		$key  = self::get_private_key_for( $user->get__id() );

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
	 * @param string $key_id The URL to the public key.
	 *
	 * @return resource|\WP_Error The public key resource or WP_Error.
	 */
	public static function get_remote_key( $key_id ) {
		$actor = get_remote_metadata_by_actor( strip_fragment_from_url( $key_id ) );
		if ( \is_wp_error( $actor ) ) {
			return new \WP_Error(
				'activitypub_no_remote_profile_found',
				__( 'No Profile found or Profile not accessible', 'activitypub' ),
				array( 'status' => 401 )
			);
		}

		if ( isset( $actor['publicKey']['publicKeyPem'] ) ) {
			$key_resource = \openssl_pkey_get_public( \rtrim( $actor['publicKey']['publicKeyPem'] ) );
			if ( $key_resource ) {
				return $key_resource;
			}
		}

		return new \WP_Error(
			'activitypub_no_remote_key_found',
			__( 'No Public-Key found', 'activitypub' ),
			array( 'status' => 401 )
		);
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
	 * @param string $body The body of the request.
	 *
	 * @return string The digest.
	 */
	public static function generate_digest( $body ) {
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
