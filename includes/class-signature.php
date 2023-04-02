<?php
namespace Activitypub;

use DateTime;
use DateTimeZone;

/**
 * ActivityPub Signature Class
 *
 * @author Matthias Pfefferle
 */
class Signature {

	const DEFAULT_SIGNING_ALGORITHM = 'sha256';

	/**
	 * @param int $user_id
	 *
	 * @return mixed
	 */
	public static function get_public_key( $user_id, $force = false ) {
		$key = \get_user_meta( $user_id, 'magic_sig_public_key' );

		if ( $key && ! $force ) {
			return $key[0];
		}

		self::generate_key_pair( $user_id );
		$key = \get_user_meta( $user_id, 'magic_sig_public_key' );

		return $key[0];
	}

	/**
	 * @param int $user_id
	 *
	 * @return mixed
	 */
	public static function get_private_key( $user_id, $force = false ) {
		$key = \get_user_meta( $user_id, 'magic_sig_private_key' );

		if ( $key && ! $force ) {
			return $key[0];
		}

		self::generate_key_pair( $user_id );
		$key = \get_user_meta( $user_id, 'magic_sig_private_key' );

		return $key[0];
	}

	/**
	 * Generates the pair keys
	 *
	 * @param int $user_id
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

		// private key
		\update_user_meta( $user_id, 'magic_sig_private_key', $priv_key );

		$detail = \openssl_pkey_get_details( $key );

		// public key
		\update_user_meta( $user_id, 'magic_sig_public_key', $detail['key'] );
	}

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

		if ( ! empty( $digest ) ) {
			$signed_string = "(request-target): $http_method $path\nhost: $host\ndate: $date\ndigest: SHA-256=$digest";
		} else {
			$signed_string = "(request-target): $http_method $path\nhost: $host\ndate: $date";
		}

		$signature = null;
		\openssl_sign( $signed_string, $signature, $key, \OPENSSL_ALGO_SHA256 );
		$signature = \base64_encode( $signature ); // phpcs:ignore

		$key_id = \get_author_posts_url( $user_id ) . '#main-key';

		if ( ! empty( $digest ) ) {
			return \sprintf( 'keyId="%s",algorithm="rsa-sha256",headers="(request-target) host date digest",signature="%s"', $key_id, $signature );
		} else {
			return \sprintf( 'keyId="%s",algorithm="rsa-sha256",headers="(request-target) host date",signature="%s"', $key_id, $signature );
		}
	}

	public static function verify_http_signature( $request = null ) {
		$headers = $request->get_headers();
		$headers['(request-target)'][0] = strtolower( $request->get_method() ) . ' /wp-json' . $request->get_route();

		if ( ! $headers ) {
			$headers = self::default_server_headers();
		}
		if ( array_key_exists( 'signature', $headers ) ) {
			$signature_block = self::parse_signature_header( $headers['signature'] );
		} elseif ( array_key_exists( 'authorization', $headers ) ) {
			$signature_block = self::parse_signature_header( $headers['authorization'] );
		}

		if ( ! $signature_block ) {
			return false;
		}

		$signed_headers = $signature_block['headers'];
		if ( ! $signed_headers ) {
			$signed_headers = array( 'date' );
		}

		$signed_data = self::get_signed_data( $signed_headers, $signature_block, $headers );
		if ( ! $signed_data ) {
			return false;
		}

		$algorithm = self::get_signature_algorithm( $signature_block );
		if ( ! $algorithm ) {
			return false;
		}

		if ( \in_array( 'digest', $signed_headers, true ) && isset( $body ) ) {
			$digest = explode( '=', $headers['digest'], 2 );
			if ( 'SHA-256' === $digest[0] ) {
				$hashalg = 'sha256';
			}
			if ( 'SHA-512' === $digest[0] ) {
				$hashalg = 'sha512';
			}

			if ( \base64_encode( \hash( $hashalg, $body, true ) ) !== $digest[1] ) { // phpcs:ignore
				return false;
			}
		}

		$public_key = isset( $key ) ? $key : self::get_key( $signature_block['keyId'] );

		return \openssl_verify( $signed_data, $signature_block['signature'], $public_key, $algorithm ) > 0;

	}

	public static function default_server_headers() {
		$headers = array(
			'(request-target)' => strtolower( $_SERVER['REQUEST_METHOD'] ) . ' ' . $_SERVER['REQUEST_URI'],
			'content-type' => $_SERVER['CONTENT_TYPE'],
			'content-length' => $_SERVER['CONTENT_LENGTH'],
		);
		foreach ( $_SERVER as $k => $v ) {
			if ( \strpos( $k, 'HTTP_' ) === 0 ) {
				$field = \str_replace( '_', '-', \strtolower( \substr( $k, 5 ) ) );
				$headers[ $field ] = $v;
			}
		}
		return $headers;
	}

	public static function get_signature_algorithm( $signature_block ) {
		switch ( $signature_block['algorithm'] ) {
			case 'rsa-sha256':
				return 'sha256';
			case 'rsa-sha-512':
				return 'sha512';
			case 'hs2019':
				return self::DEFAULT_SIGNING_ALGORITHM;
		}
		return false;
	}

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

	public static function get_key( $keyId ) { // phpcs:ignore
		// If there was no key passed to verify, it will find the keyId and call this
		// function to fetch the public key from stored data or a network fetch.
		$actor = \strip_fragment_from_url( $keyId ); // phpcs:ignore
		$publicKeyPem = \Activitypub\get_publickey_by_actor( $actor, $keyId ); // phpcs:ignore
		return \rtrim( $publicKeyPem ); // phpcs:ignore
	}


	public static function get_signed_data( $signed_headers, $signature_block, $headers ) {
		$signed_data = '';
		// This also verifies time-based values by returning false if any of these are out of range.
		foreach ( $signed_headers as $header ) {
			if ( \array_key_exists( $header, $headers ) ) {
				if ( 'host' === $header ) {
					if ( isset( $headers['x_original_host'] ) ) {
						$signed_data .= $header . ': ' . $headers['x_original_host'][0] . "\n";
					} else {
						$signed_data .= $header . ': ' . $headers[ $header ][0] . "\n";
					}
				} else {
					$signed_data .= $header . ': ' . $headers[ $header ][0] . "\n";
				}
			}
			if ( '(created)' === $header ) {
				if ( ! empty( $signature_block['(created)'] ) && \intval( $signature_block['(created)'] ) > \time() ) {
					// created in future
					return false;
				}
				$signed_data .= '(created): ' . $signature_block['(created)'] . "\n";
			}
			if ( '(expires)' === $header ) {
				if ( ! empty( $signature_block['(expires)'] ) && \intval( $signature_block['(expires)'] ) < \time() ) {
					// expired in past
					return false;
				}
				$signed_data .= '(expires): ' . $signature_block['(expires)'] . "\n";
			}
			if ( 'content-type' === $header ) {
				$signed_data .= $header . ': ' . $headers['content_type'][0] . "\n";
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
		}
		return \rtrim( $signed_data, "\n" );
	}

	public static function generate_digest( $body ) {
		$digest = \base64_encode( \hash( 'sha256', $body, true ) ); // phpcs:ignore
		return "$digest";
	}
}
