<?php
/**
 * Signature class file.
 *
 * @package Activitypub
 */

namespace Activitypub;

use WP_Error;
use DateTime;
use DateTimeZone;
use WP_REST_Request;
use Activitypub\Collection\Actors;

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

		$key      = \openssl_pkey_new( $config );
		$priv_key = null;
		$detail   = array();
		if ( $key ) {
			\openssl_pkey_export( $key, $priv_key );

			$detail = \openssl_pkey_get_details( $key );
		}

		// Check if keys are valid.
		if (
			empty( $priv_key ) || ! is_string( $priv_key ) ||
			! isset( $detail['key'] ) || ! is_string( $detail['key'] )
		) {
			return array(
				'private_key' => null,
				'public_key'  => null,
			);
		}

		$key_pair = array(
			'private_key' => $priv_key,
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
	 * @param WP_REST_Request|array $request The request object or $_SERVER array.
	 *
	 * @return bool|WP_Error A boolean or WP_Error.
	 */
	public static function verify_http_signature( $request ) {
		if ( is_object( $request ) ) { // REST Request object.
			// Check if route starts with "index.php".
			if ( str_starts_with( $request->get_route(), '/index.php' ) || ! rest_get_url_prefix() ) {
				$route = $request->get_route();
			} else {
				$route = '/' . rest_get_url_prefix() . '/' . ltrim( $request->get_route(), '/' );
			}

			// Fix route for subdirectory installs.
			$path = \wp_parse_url( \get_home_url(), PHP_URL_PATH );

			if ( \is_string( $path ) ) {
				$path = trim( $path, '/' );
			}

			if ( $path ) {
				$route = '/' . $path . $route;
			}

			$headers                        = $request->get_headers();
			$headers['(request-target)'][0] = strtolower( $request->get_method() ) . ' ' . $route;
		} else {
			$request                        = self::format_server_request( $request );
			$headers                        = $request['headers']; // $_SERVER array
			$headers['(request-target)'][0] = strtolower( $headers['request_method'][0] ) . ' ' . $headers['request_uri'][0];
		}

		if ( ! isset( $headers['signature'] ) ) {
			return new WP_Error( 'activitypub_signature', __( 'Request not signed', 'activitypub' ), array( 'status' => 401 ) );
		}

		if ( array_key_exists( 'signature', $headers ) ) {
			$signature_block = self::parse_signature_header( $headers['signature'][0] );
		} elseif ( array_key_exists( 'authorization', $headers ) ) {
			$signature_block = self::parse_signature_header( $headers['authorization'][0] );
		}

		if ( ! isset( $signature_block ) || ! $signature_block ) {
			return new WP_Error( 'activitypub_signature', __( 'Incompatible request signature. keyId and signature are required', 'activitypub' ), array( 'status' => 401 ) );
		}

		$signed_headers = $signature_block['headers'];
		if ( ! $signed_headers ) {
			$signed_headers = array( 'date' );
		}

		$signed_data = self::get_signed_data( $signed_headers, $signature_block, $headers );
		if ( ! $signed_data ) {
			return new WP_Error( 'activitypub_signature', __( 'Signed request date outside acceptable time window', 'activitypub' ), array( 'status' => 401 ) );
		}

		$algorithm = self::get_signature_algorithm( $signature_block );
		if ( ! $algorithm ) {
			return new WP_Error( 'activitypub_signature', __( 'Unsupported signature algorithm (only rsa-sha256 and hs2019 are supported)', 'activitypub' ), array( 'status' => 401 ) );
		}

		if ( \in_array( 'digest', $signed_headers, true ) && isset( $body ) ) {
			if ( is_array( $headers['digest'] ) ) {
				$headers['digest'] = $headers['digest'][0];
			}
			$hashalg = 'sha256';
			$digest  = explode( '=', $headers['digest'], 2 );
			if ( 'SHA-256' === $digest[0] ) {
				$hashalg = 'sha256';
			}
			if ( 'SHA-512' === $digest[0] ) {
				$hashalg = 'sha512';
			}

			if ( \base64_encode( \hash( $hashalg, $body, true ) ) !== $digest[1] ) { // phpcs:ignore
				return new WP_Error( 'activitypub_signature', __( 'Invalid Digest header', 'activitypub' ), array( 'status' => 401 ) );
			}
		}

		$public_key = self::get_remote_key( $signature_block['keyId'] );

		if ( \is_wp_error( $public_key ) ) {
			return $public_key;
		}

		$verified = \openssl_verify( $signed_data, $signature_block['signature'], $public_key, $algorithm ) > 0;
		if ( ! $verified ) {
			return new WP_Error( 'activitypub_signature', __( 'Invalid signature', 'activitypub' ), array( 'status' => 401 ) );
		}
		return $verified;
	}

	/**
	 * Get public key from key_id.
	 *
	 * @param string $key_id The URL to the public key.
	 *
	 * @return WP_Error|string The public key or WP_Error.
	 */
	public static function get_remote_key( $key_id ) {
		$actor = get_remote_metadata_by_actor( strip_fragment_from_url( $key_id ) );
		if ( \is_wp_error( $actor ) ) {
			return new WP_Error(
				'activitypub_no_remote_profile_found',
				__( 'No Profile found or Profile not accessible', 'activitypub' ),
				array( 'status' => 401 )
			);
		}
		if ( isset( $actor['publicKey']['publicKeyPem'] ) ) {
			return \rtrim( $actor['publicKey']['publicKeyPem'] );
		}
		return new WP_Error(
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
	 * @return string The signature algorithm.
	 */
	public static function get_signature_algorithm( $signature_block ) {
		if ( $signature_block['algorithm'] ) {
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
	 * @param string $signature The signature header.
	 *
	 * @return array Signature parts.
	 */
	public static function parse_signature_header( $signature ) {
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

		if (
			! empty( $parsed_header['signature'] ) &&
			! empty( $parsed_header['algorithm'] ) &&
			empty( $parsed_header['headers'] )
		) {
			$parsed_header['headers'] = array( 'date' );
		}

		return $parsed_header;
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
	public static function get_signed_data( $signed_headers, $signature_block, $headers ) {
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
				$d = new DateTime( $headers[ $header ][0] );
				$d->setTimeZone( new DateTimeZone( 'UTC' ) );
				$c = $d->format( 'U' );

				$dplus  = time() + ( 3 * HOUR_IN_SECONDS );
				$dminus = time() - ( 3 * HOUR_IN_SECONDS );

				if ( $c > $dplus || $c < $dminus ) {
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
}
