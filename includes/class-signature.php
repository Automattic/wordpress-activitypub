<?php
namespace Activitypub;

use WP_Error;
use DateTime;
use DateTimeZone;
use Activitypub\Model\User;

/**
 * ActivityPub Signature Class
 *
 * @author Matthias Pfefferle
 * @author Django Doucet
 */
class Signature {

	/**
	 * Return the public key for a given user.
	 *
	 * @param int  $user_id The WordPress User ID.
	 * @param bool $force   Force the generation of a new key pair.
	 *
	 * @return mixed The public key.
	 */
	public static function get_public_key( $user_id, $force = false ) {
		if ( $force ) {
			self::generate_key_pair( $user_id );
		}

		if ( User::APPLICATION_USER_ID === $user_id ) {
			$key = \get_option( 'activitypub_magic_sig_public_key' );
		} else {
			$key = \get_user_meta( $user_id, 'magic_sig_public_key', true );
		}

		if ( ! $key ) {
			return self::get_public_key( $user_id, true );
		}

		return $key;
	}

	/**
	 * Return the private key for a given user.
	 *
	 * @param int  $user_id The WordPress User ID.
	 * @param bool $force   Force the generation of a new key pair.
	 *
	 * @return mixed The private key.
	 */
	public static function get_private_key( $user_id, $force = false ) {
		if ( $force ) {
			self::generate_key_pair( $user_id );
		}

		if ( User::APPLICATION_USER_ID === $user_id ) {
			$key = \get_option( 'activitypub_magic_sig_private_key' );
		} else {
			$key = \get_user_meta( $user_id, 'magic_sig_private_key', true );
		}

		if ( ! $key ) {
			return self::get_private_key( $user_id, true );
		}

		return $key;
	}

	/**
	 * Generates the pair keys
	 *
	 * @param int $user_id The WordPress User ID.
	 *
	 * @return void
	 */
	public static function generate_key_pair( $user_id ) {
		$config = array(
			'digest_alg' => 'sha512',
			'private_key_bits' => 2048,
			'private_key_type' => \OPENSSL_KEYTYPE_RSA,
		);

		$key = \openssl_pkey_new( $config );
		$priv_key = null;

		\openssl_pkey_export( $key, $priv_key );
		$detail = \openssl_pkey_get_details( $key );

		if ( User::APPLICATION_USER_ID === $user_id ) {
			// private key
			\update_option( 'activitypub_magic_sig_private_key', $priv_key );

			// public key
			\update_option( 'activitypub_magic_sig_public_key', $detail['key'] );

		} else {
			// private key
			\update_user_meta( $user_id, 'magic_sig_private_key', $priv_key );

			// public key
			\update_user_meta( $user_id, 'magic_sig_public_key', $detail['key'] );
		}
	}

	/**
	 * Generates the Signature for a HTTP Request
	 *
	 * @param int    $user_id     The WordPress User ID.
	 * @param string $http_method The HTTP method.
	 * @param string $url         The URL to send the request to.
	 * @param string $date        The date the request is sent.
	 * @param string $digest      The digest of the request body.
	 *
	 * @return string The signature.
	 */
	public static function generate_signature( $user_id, $http_method, $url, $date, $digest = null ) {
		$key = self::get_private_key( $user_id );

		$url_parts = \wp_parse_url( $url );

		$host = $url_parts['host'];
		$path = '/';

		// add path
		if ( ! empty( $url_parts['path'] ) ) {
			$path = $url_parts['path'];
		}

		// add query
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
		$signature = \base64_encode( $signature ); // phpcs:ignore

		if ( User::APPLICATION_USER_ID === $user_id ) {
			$key_id = \get_rest_url( null, 'activitypub/1.0/application#main-key' );
		} else {
			$key_id = \get_author_posts_url( $user_id ) . '#main-key';
		}

		if ( ! empty( $digest ) ) {
			return \sprintf( 'keyId="%s",algorithm="rsa-sha256",headers="(request-target) host date digest",signature="%s"', $key_id, $signature );
		} else {
			return \sprintf( 'keyId="%s",algorithm="rsa-sha256",headers="(request-target) host date",signature="%s"', $key_id, $signature );
		}
	}

	/**
	 * Verifies the http signatures
	 *
	 * @param WP_REQUEST|array $request The request object or $_SERVER array.
	 *
	 * @return mixed A boolean or WP_Error.
	 */
	public static function verify_http_signature( $request ) {
		if ( is_object( $request ) ) { // REST Request object
			// check if route starts with "index.php"
			if ( str_starts_with( $request->get_route(), '/index.php' ) ) {
				$route = $request->get_route();
			} else {
				$route = '/' . rest_get_url_prefix() . '/' . ltrim( $request->get_route(), '/' );
			}
			$headers = $request->get_headers();
			$actor = isset( json_decode( $request->get_body() )->actor ) ? json_decode( $request->get_body() )->actor : '';
			$headers['(request-target)'][0] = strtolower( $request->get_method() ) . ' ' . $route;
		} else {
			$request = self::format_server_request( $request );
			$headers = $request['headers']; // $_SERVER array
			$actor = null;
			$headers['(request-target)'][0] = strtolower( $headers['request_method'][0] ) . ' ' . $headers['request_uri'][0];
		}

		if ( ! isset( $headers['signature'] ) ) {
			return new WP_Error( 'activitypub_signature', 'Request not signed', array( 'status' => 403 ) );
		}

		if ( array_key_exists( 'signature', $headers ) ) {
			$signature_block = self::parse_signature_header( $headers['signature'] );
		} elseif ( array_key_exists( 'authorization', $headers ) ) {
			$signature_block = self::parse_signature_header( $headers['authorization'] );
		}

		if ( ! isset( $signature_block ) || ! $signature_block ) {
			return new WP_Error( 'activitypub_signature', 'Incompatible request signature. keyId and signature are required', array( 'status' => 403 ) );
		}

		$signed_headers = $signature_block['headers'];
		if ( ! $signed_headers ) {
			$signed_headers = array( 'date' );
		}

		$signed_data = self::get_signed_data( $signed_headers, $signature_block, $headers );
		if ( ! $signed_data ) {
			return new WP_Error( 'activitypub_signature', 'Signed request date outside acceptable time window', array( 'status' => 403 ) );
		}

		$algorithm = self::get_signature_algorithm( $signature_block );
		if ( ! $algorithm ) {
			return new WP_Error( 'activitypub_signature', 'Unsupported signature algorithm (only rsa-sha256 and hs2019 are supported)', array( 'status' => 403 ) );
		}

		if ( \in_array( 'digest', $signed_headers, true ) && isset( $body ) ) {
			if ( is_array( $headers['digest'] ) ) {
				$headers['digest'] = $headers['digest'][0];
			}
			$digest = explode( '=', $headers['digest'], 2 );
			if ( 'SHA-256' === $digest[0] ) {
				$hashalg = 'sha256';
			}
			if ( 'SHA-512' === $digest[0] ) {
				$hashalg = 'sha512';
			}

			if ( \base64_encode( \hash( $hashalg, $body, true ) ) !== $digest[1] ) { // phpcs:ignore
				return new WP_Error( 'activitypub_signature', 'Invalid Digest header', array( 'status' => 403 ) );
			}
		}

		if ( $actor ) {
			$public_key = self::get_remote_key( $actor );
		} else {
			$public_key = self::get_remote_key( $signature_block['keyId'] );
		}
		if ( \is_wp_error( $public_key ) ) {
			return $public_key;
		}

		$verified = \openssl_verify( $signed_data, $signature_block['signature'], $public_key, $algorithm ) > 0;

		if ( ! $verified ) {
			return new WP_Error( 'activitypub_signature', 'Invalid signature', array( 'status' => 403 ) );
		}
		return $verified;
	}

	/**
	 * Get public key from key_id
	 *
	 * @param string $key_id
	 *
	 * @return string $publicKeyPem
	 */
	public static function get_remote_key( $key_id ) { // phpcs:ignore
		$actor = \Activitypub\get_remote_metadata_by_actor( strtok( strip_fragment_from_url( $key_id ), '?' ) ); // phpcs:ignore
		if ( \is_wp_error( $actor ) ) {
			return $actor;
		}
		if ( isset( $actor['publicKey']['publicKeyPem'] ) ) {
			return \rtrim( $actor['publicKey']['publicKeyPem'] ); // phpcs:ignore
		}
		return null;
	}

	/**
	 * Gets the signature algorithm from the signature header
	 *
	 * @param array $signature_block
	 *
	 * @return string algorithm
	 */
	public static function get_signature_algorithm( $signature_block ) {
		if ( $signature_block['algorithm'] ) {
			switch ( $signature_block['algorithm'] ) {
				case 'rsa-sha-512':
					return 'sha512'; //hs2019 https://datatracker.ietf.org/doc/html/draft-cavage-http-signatures-12
				default:
					return 'sha256';
			}
		}
		return false;
	}

	/**
	 * Parses the Signature header
	 *
	 * @param array $header
	 *
	 * @return array signature parts
	 */
	public static function parse_signature_header( $header ) {
		$ret = array();
		$matches = array();
		$h_string = \implode( ',', (array) $header[0] );

		if ( \preg_match( '/keyId="(.*?)"/ism', $h_string, $matches ) ) {
			$ret['keyId'] = $matches[1];
		}
		if ( \preg_match( '/created=([0-9]*)/ism', $h_string, $matches ) ) {
			$ret['(created)'] = $matches[1];
		}
		if ( \preg_match( '/expires=([0-9]*)/ism', $h_string, $matches ) ) {
			$ret['(expires)'] = $matches[1];
		}
		if ( \preg_match( '/algorithm="(.*?)"/ism', $h_string, $matches ) ) {
			$ret['algorithm'] = $matches[1];
		}
		if ( \preg_match( '/headers="(.*?)"/ism', $h_string, $matches ) ) {
			$ret['headers'] = \explode( ' ', $matches[1] );
		}
		if ( \preg_match( '/signature="(.*?)"/ism', $h_string, $matches ) ) {
			$ret['signature'] = \base64_decode( preg_replace( '/\s+/', '', $matches[1] ) ); // phpcs:ignore
		}

		if ( ( $ret['signature'] ) && ( $ret['algorithm'] ) && ( ! $ret['headers'] ) ) {
			$ret['headers'] = array( 'date' );
		}

		return $ret;
	}

	/**
	 * Gets the header data from the included pseudo headers
	 *
	 * @param array $signed_headers
	 * @param array $signature_block (pseudo-headers)
	 * @param array $headers         (http headers)
	 *
	 * @return signed headers for comparison
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
					// created in future
					return false;
				}
			}
			if ( '(expires)' === $header ) {
				if ( ! empty( $signature_block['(expires)'] ) && \intval( $signature_block['(expires)'] ) < \time() ) {
					// expired in past
					return false;
				}
			}
			if ( 'date' === $header ) {
				// allow a bit of leeway for misconfigured clocks.
				$d = new DateTime( $headers[ $header ][0] );
				$d->setTimeZone( new DateTimeZone( 'UTC' ) );
				$c = $d->format( 'U' );

				$dplus = time() + ( 3 * HOUR_IN_SECONDS );
				$dminus = time() - ( 3 * HOUR_IN_SECONDS );

				if ( $c > $dplus || $c < $dminus ) {
					// time out of range
					return false;
				}
			}
			$signed_data .= $header . ': ' . $headers[ $header ][0] . "\n";
		}
		return \rtrim( $signed_data, "\n" );
	}

	public static function generate_digest( $body ) {
		$digest = \base64_encode( \hash( 'sha256', $body, true ) ); // phpcs:ignore
		return "SHA-256=$digest";
	}

	/**
	 * Formats the $_SERVER to resemble the WP_REST_REQUEST array,
	 * for use with verify_http_signature()
	 *
	 * @param array $_SERVER
	 *
	 * @return array $request
	 */
	public static function format_server_request( $server ) {
		$request = array();
		foreach ( $server as $param_key => $param_val ) {
			$req_param = strtolower( $param_key );
			if ( 'REQUEST_URI' === $req_param ) {
				$request['headers']['route'][] = $param_val;
			} else {
				$header_key = str_replace(
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
