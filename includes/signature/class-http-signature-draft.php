<?php
/**
 * ActivityPub Draft Cavage Signature Standard.
 *
 * This class implements the Draft Cavage signature standard for verifying HTTP signatures.
 *
 * @package Activitypub\Signature
 */

// phpcs:disable WordPress.PHP.DiscouragedPHPFunctions

namespace Activitypub\Signature;

use Activitypub\Collection\Remote_Actors;

/**
 * Class Http_Signature_Draft.
 *
 * Implements the Draft Cavage signature standard for verifying HTTP signatures.
 *
 * @see https://tools.ietf.org/html/draft-cavage-http-signatures-12
 */
class Http_Signature_Draft implements Http_Signature {

	/**
	 * Generate Signature headers for an outgoing HTTP request.
	 *
	 * @param array  $args The request arguments.
	 * @param string $url  The request URL.
	 *
	 * @return array Request arguments with signature headers.
	 */
	public function sign( $args, $url ) {
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

		$http_method = \strtolower( $args['method'] );
		$date        = $args['headers']['Date'];

		$signed_parts = array(
			sprintf( '(request-target): %s %s', $http_method, $path ),
			sprintf( 'host: %s', $host ),
			sprintf( 'date: %s', $date ),
		);
		$headers_list = array( '(request-target)', 'host', 'date' );

		if ( isset( $args['body'] ) ) {
			$args['headers']['Digest'] = $this->generate_digest( $args['body'] );
			$signed_parts[]            = sprintf( 'digest: %s', $args['headers']['Digest'] );
			$headers_list[]            = 'digest';
		}

		if ( isset( $args['headers']['Collection-Synchronization'] ) ) {
			$signed_parts[] = sprintf( 'collection-synchronization: %s', $args['headers']['Collection-Synchronization'] );
			$headers_list[] = 'collection-synchronization';
		}

		$signed_string = implode( "\n", $signed_parts );
		$headers_list  = implode( ' ', $headers_list );

		$signature = null;
		\openssl_sign( $signed_string, $signature, $args['private_key'], \OPENSSL_ALGO_SHA256 );
		$signature = \base64_encode( $signature );

		$args['headers']['Signature'] = \sprintf(
			'keyId="%s",algorithm="rsa-sha256",headers="%s",signature="%s"',
			$args['key_id'],
			$headers_list,
			$signature
		);

		return $args;
	}

	/**
	 * Verify the HTTP Signature against a request.
	 *
	 * @param array       $headers The HTTP headers.
	 * @param string|null $body    The request body, if applicable.
	 * @return bool|\WP_Error True, if the signature is valid, WP_Error on failure.
	 */
	public function verify( array $headers, $body = null ) {
		if ( ! isset( $headers['signature'] ) && ! isset( $headers['authorization'] ) ) {
			return new \WP_Error( 'missing_signature', 'No Signature or Authorization header present.' );
		}

		$header = $headers['signature'] ?? $headers['authorization'];
		$parsed = $this->parse_signature_header( $header[0] );

		if ( empty( $parsed['keyId'] ) ) {
			return new \WP_Error( 'activitypub_signature', 'No Key ID present.' );
		}

		$public_key = Remote_Actors::get_public_key( $parsed['keyId'] );
		if ( \is_wp_error( $public_key ) ) {
			return $public_key;
		}

		$signed_data = $this->get_signed_data( $parsed['headers'], $parsed, $headers );
		if ( ! $signed_data ) {
			return new \WP_Error( 'invalid_signed_data', 'Signed data is invalid or expired.' );
		}

		$algorithm = $this->get_signature_algorithm( $parsed, $public_key );
		if ( \is_wp_error( $algorithm ) ) {
			return $algorithm;
		}

		// Digest verification.
		$result = $this->verify_content_digest( $headers, $body );
		if ( \is_wp_error( $result ) ) {
			return $result;
		}

		$verified = \openssl_verify( $signed_data, $parsed['signature'], $public_key, $algorithm ) > 0;
		if ( ! $verified ) {
			return new \WP_Error( 'activitypub_signature', 'Invalid signature', array( 'status' => 401 ) );
		}

		return true;
	}

	/**
	 * Generates the digest for an HTTP Request.
	 *
	 * @param string $body The body of the request.
	 *
	 * @return string The digest.
	 */
	public function generate_digest( $body ) {
		return 'SHA-256=' . \base64_encode( \hash( 'sha256', $body, true ) );
	}

	/**
	 * Gets the signature algorithm from the signature header.
	 *
	 * @param array    $signature_block The signature block.
	 * @param resource $public_key      The public key resource.
	 *
	 * @return int|\WP_Error The signature algorithm or WP_Error if not found.
	 */
	private function get_signature_algorithm( $signature_block, $public_key ) {
		if ( ! empty( $signature_block['algorithm'] ) ) {
			switch ( $signature_block['algorithm'] ) {
				case 'hs2019':
					$details = \openssl_pkey_get_details( $public_key );

					switch ( $details['type'] ?? 0 ) {
						case \OPENSSL_KEYTYPE_RSA:
							$bits = $details['bits'] ?? 2048;

							if ( $bits >= 4 * KB_IN_BYTES ) {
								return \OPENSSL_ALGO_SHA512;
							} elseif ( $bits >= 3 * KB_IN_BYTES ) {
								return \OPENSSL_ALGO_SHA384;
							} else {
								return \OPENSSL_ALGO_SHA256;
							}

						case \OPENSSL_KEYTYPE_EC:
							$curve_name = $details['ec']['curve_name'] ?? '';

							// 3 levels switch statements are fine, right?
							switch ( $curve_name ) {
								case 'prime256v1':
								case 'secp256r1':
									return \OPENSSL_ALGO_SHA256;
								case 'secp384r1':
									return \OPENSSL_ALGO_SHA384;
								case 'secp521r1':
									return \OPENSSL_ALGO_SHA512;
							}
					}

					return new \WP_Error( 'unsupported_key_type', 'Unsupported key type (only RSA and EC keys are supported).', array( 'status' => 401 ) );

				case 'rsa-sha512':
					return \OPENSSL_ALGO_SHA512;
				default:
					return \OPENSSL_ALGO_SHA256;
			}
		}

		return new \WP_Error( 'unsupported_key_type', 'Unsupported signature algorithm (only rsa-sha256, rsa-sha512, and hs2019 are supported).', array( 'status' => 401 ) );
	}

	/**
	 * Verify the Content-Digest header against the request body.
	 *
	 * @param array       $headers The HTTP headers.
	 * @param string|null $body    The request body, if applicable.
	 * @return bool|\WP_Error True, if the signature is valid, WP_Error on failure.
	 */
	private function verify_content_digest( $headers, $body ) {
		if ( ! isset( $headers['digest'][0] ) || null === $body ) {
			return true;
		}

		list( $alg, $digest ) = \explode( '=', $headers['digest'][0], 2 );
		$alg                  = \strtolower( $alg );
		$map                  = array(
			'sha-256' => 'sha256',
			'sha-512' => 'sha512',
		);

		if ( ! isset( $map[ $alg ] ) ) {
			return new \WP_Error( 'unsupported_digest', 'WordPress supports SHA-256 and SHA-512 in Digest header. Offered algorithm: ' . $alg, array( 'status' => 401 ) );
		}

		if ( \hash_equals( $digest, \base64_encode( \hash( $map[ $alg ], $body, true ) ) ) ) {
			return true;
		}

		return new \WP_Error( 'digest_mismatch', 'Digest header value does not match body.', array( 'status' => 401 ) );
	}

	/**
	 * Parses the Signature header.
	 *
	 * @param string $signature The signature header.
	 *
	 * @return array Signature parts.
	 */
	private function parse_signature_header( $signature ) {
		$parsed_header = array();
		$matches       = array();

		if ( \preg_match( '/keyId="(.*?)"/ism', $signature, $matches ) ) {
			$parsed_header['keyId'] = trim( $matches[1] );
		}
		if ( \preg_match( '/created=["|\']*([0-9]*)["|\']*/im', $signature, $matches ) ) {
			$parsed_header['(created)'] = trim( $matches[1] );
		}
		if ( \preg_match( '/expires=["|\']*([0-9]*)["|\']*/im', $signature, $matches ) ) {
			$parsed_header['(expires)'] = trim( $matches[1] );
		}
		if ( \preg_match( '/algorithm="(.*?)"/ism', $signature, $matches ) ) {
			$parsed_header['algorithm'] = trim( $matches[1] );
		}
		if ( \preg_match( '/headers="(.*?)"/ism', $signature, $matches ) ) {
			$parsed_header['headers'] = \explode( ' ', trim( $matches[1] ) );
		}
		if ( \preg_match( '/signature="(.*?)"/ism', $signature, $matches ) ) {
			$parsed_header['signature'] = \base64_decode( \preg_replace( '/\s+/', '', \trim( $matches[1] ) ) );
		}

		if ( empty( $parsed_header['headers'] ) ) {
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
	private function get_signed_data( $signed_headers, $signature_block, $headers ) {
		$signed_data       = '';
		$has_time_anchor   = false;
		$now               = \time();
		$max_future_skew   = $now + ( 5 * MINUTE_IN_SECONDS );
		$min_past_skew     = $now - HOUR_IN_SECONDS;
		$max_expires_drift = $now + DAY_IN_SECONDS;

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
			if ( \str_contains( $header, '-' ) ) {
				$signed_data .= $header . ': ' . $headers[ \str_replace( '-', '_', $header ) ][0] . "\n";
				continue;
			}
			if ( '(created)' === $header ) {
				if ( empty( $signature_block['(created)'] ) ) {
					// (created) listed in signed headers but the signature omitted the value.
					return false;
				}

				$created = \intval( $signature_block['(created)'] );
				if ( $created <= 0 || $created > $max_future_skew || $created < $min_past_skew ) {
					// Created is zero or out of the asymmetric window.
					return false;
				}
				$has_time_anchor = true;

				if ( ! \array_key_exists( '(created)', $headers ) ) {
					$signed_data .= $header . ': ' . $signature_block['(created)'] . "\n";
					continue;
				}
			}
			if ( '(expires)' === $header ) {
				if ( empty( $signature_block['(expires)'] ) ) {
					// (expires) listed in signed headers but the signature omitted the value.
					return false;
				}

				$expires = \intval( $signature_block['(expires)'] );

				/*
				 * Reject signatures that have already expired, and also
				 * reject absurdly-far-future expiries that a malicious
				 * sender could use to neuter replay protection.
				 */
				if ( $expires < $now || $expires > $max_expires_drift ) {
					return false;
				}

				/*
				 * A validated (expires) bounds the signature's lifetime
				 * to at most one day in the future, so it's a legitimate
				 * freshness signal on its own.
				 */
				$has_time_anchor = true;

				if ( ! \array_key_exists( '(expires)', $headers ) ) {
					$signed_data .= $header . ': ' . $signature_block['(expires)'] . "\n";
					continue;
				}
			}
			if ( 'date' === $header ) {
				$has_time_anchor = true;
				if ( empty( $headers['date'][0] ) ) {
					// Date is in the signed headers list but missing from the request.
					return false;
				}

				$date = \date_create( $headers['date'][0] );
				if ( ! $date ) {
					// Malformed Date header — refuse rather than fatal on setTimeZone().
					return false;
				}
				$date->setTimeZone( \timezone_open( 'UTC' ) );
				$date = $date->format( 'U' );

				/*
				 * Asymmetric skew tolerance.
				 *
				 * Future-dated signatures are tolerated by up to five minutes
				 * of clock drift; anything further is either a misconfigured
				 * peer or a forged replay envelope.
				 *
				 * Past-dated signatures are tolerated for up to an hour so
				 * that retried / queued federation traffic from peers with
				 * backed-up outboxes still verifies.
				 */
				if ( $date > $max_future_skew || $date < $min_past_skew ) {
					// Time out of range.
					return false;
				}
			}

			if ( ! empty( $headers[ $header ][0] ) ) {
				$signed_data .= $header . ': ' . $headers[ $header ][0] . "\n";
			}
		}

		/*
		 * Require a signed time anchor (Date or (created)). Without one,
		 * a captured signed request could be replayed indefinitely because
		 * no field inside the signed base string bounds its freshness.
		 */
		if ( ! $has_time_anchor ) {
			return false;
		}

		return \rtrim( $signed_data, "\n" );
	}
}
