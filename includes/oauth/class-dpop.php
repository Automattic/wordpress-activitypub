<?php
/**
 * DPoP (RFC 9449) proof validation for OAuth 2.0.
 *
 * Implements Demonstrating Proof of Possession (DPoP) which cryptographically
 * binds access tokens to a client's key pair, preventing token theft and replay.
 *
 * @package Activitypub
 * @since unreleased
 * @see https://datatracker.ietf.org/doc/html/rfc9449
 */

namespace Activitypub\OAuth;

/**
 * DPoP class for validating DPoP proof JWTs and computing JWK thumbprints.
 *
 * Supports ES256 (ECDSA P-256) and RS256 (RSA) signing algorithms.
 * No external JWT library required — uses PHP's OpenSSL extension directly.
 *
 * @since unreleased
 */
class DPoP {
	/**
	 * Allowed signing algorithms (asymmetric only).
	 *
	 * @var array
	 */
	const SUPPORTED_ALGORITHMS = array( 'ES256', 'RS256' );

	/**
	 * Maximum allowed age for a DPoP proof in seconds.
	 *
	 * @var int
	 */
	const MAX_AGE = 300;

	/**
	 * Validate a DPoP proof JWT.
	 *
	 * @since unreleased
	 *
	 * @param string      $proof        The DPoP proof JWT from the DPoP header.
	 * @param string      $http_method  The HTTP method of the request (e.g. 'POST').
	 * @param string      $http_uri     The HTTP URI of the request (scheme + host + path, no query).
	 * @param string|null $access_token The access token to verify binding against (for resource requests).
	 * @return array|\WP_Error Array with 'jkt' (JWK thumbprint) on success, or WP_Error.
	 */
	public static function validate_proof( $proof, $http_method, $http_uri, $access_token = null ) {
		// Decode the JWT.
		$parts = explode( '.', $proof );
		if ( 3 !== count( $parts ) ) {
			return new \WP_Error(
				'activitypub_dpop_invalid_jwt',
				\__( 'Invalid DPoP proof: malformed JWT.', 'activitypub' ),
				array( 'status' => 401 )
			);
		}

		$header  = self::json_decode_base64url( $parts[0] );
		$payload = self::json_decode_base64url( $parts[1] );

		if ( ! $header || ! $payload ) {
			return new \WP_Error(
				'activitypub_dpop_invalid_jwt',
				\__( 'Invalid DPoP proof: cannot decode header or payload.', 'activitypub' ),
				array( 'status' => 401 )
			);
		}

		// Validate typ.
		if ( ! isset( $header['typ'] ) || 'dpop+jwt' !== $header['typ'] ) {
			return new \WP_Error(
				'activitypub_dpop_invalid_typ',
				\__( 'Invalid DPoP proof: typ must be "dpop+jwt".', 'activitypub' ),
				array( 'status' => 401 )
			);
		}

		// Validate alg is asymmetric and supported.
		if ( ! isset( $header['alg'] ) || ! in_array( $header['alg'], self::SUPPORTED_ALGORITHMS, true ) ) {
			return new \WP_Error(
				'activitypub_dpop_unsupported_alg',
				\__( 'Invalid DPoP proof: unsupported algorithm.', 'activitypub' ),
				array( 'status' => 401 )
			);
		}

		// Validate jwk is present.
		if ( ! isset( $header['jwk'] ) || ! is_array( $header['jwk'] ) ) {
			return new \WP_Error(
				'activitypub_dpop_missing_jwk',
				\__( 'Invalid DPoP proof: missing JWK in header.', 'activitypub' ),
				array( 'status' => 401 )
			);
		}

		// Verify the signature.
		$signature_valid = self::verify_signature(
			$parts[0] . '.' . $parts[1],
			self::base64url_decode( $parts[2] ),
			$header['alg'],
			$header['jwk']
		);

		if ( \is_wp_error( $signature_valid ) ) {
			return $signature_valid;
		}

		// Validate required payload claims.
		$required_claims = array( 'jti', 'htm', 'htu', 'iat' );
		foreach ( $required_claims as $claim ) {
			if ( ! isset( $payload[ $claim ] ) ) {
				return new \WP_Error(
					'activitypub_dpop_missing_claim',
					/* translators: %s: The missing claim name */
					sprintf( \__( 'Invalid DPoP proof: missing required claim "%s".', 'activitypub' ), $claim ),
					array( 'status' => 401 )
				);
			}
		}

		// Validate htm (HTTP method).
		if ( strtoupper( $payload['htm'] ) !== strtoupper( $http_method ) ) {
			return new \WP_Error(
				'activitypub_dpop_method_mismatch',
				\__( 'Invalid DPoP proof: HTTP method mismatch.', 'activitypub' ),
				array( 'status' => 401 )
			);
		}

		// Validate htu (HTTP URI) — compare without query string and fragment.
		$proof_uri   = self::normalize_uri( $payload['htu'] );
		$request_uri = self::normalize_uri( $http_uri );

		if ( $proof_uri !== $request_uri ) {
			return new \WP_Error(
				'activitypub_dpop_uri_mismatch',
				\__( 'Invalid DPoP proof: HTTP URI mismatch.', 'activitypub' ),
				array( 'status' => 401 )
			);
		}

		// Validate iat (freshness).
		$now = time();
		$iat = (int) $payload['iat'];

		if ( $iat > $now + 5 ) {
			// Allow 5 seconds clock skew into the future.
			return new \WP_Error(
				'activitypub_dpop_future_iat',
				\__( 'Invalid DPoP proof: issued in the future.', 'activitypub' ),
				array( 'status' => 401 )
			);
		}

		if ( ( $now - $iat ) > self::MAX_AGE ) {
			return new \WP_Error(
				'activitypub_dpop_expired',
				\__( 'Invalid DPoP proof: proof has expired.', 'activitypub' ),
				array( 'status' => 401 )
			);
		}

		// Prevent jti replay (RFC 9449 Section 11.1).
		$jti_cache_key = 'activitypub_dpop_jti_' . md5( $payload['jti'] );

		if ( false !== \get_transient( $jti_cache_key ) ) {
			return new \WP_Error(
				'activitypub_dpop_jti_replayed',
				\__( 'Invalid DPoP proof: replayed jti.', 'activitypub' ),
				array( 'status' => 401 )
			);
		}

		\set_transient( $jti_cache_key, 1, self::MAX_AGE );

		// If access token provided, verify ath claim.
		if ( null !== $access_token ) {
			if ( ! isset( $payload['ath'] ) ) {
				return new \WP_Error(
					'activitypub_dpop_missing_ath',
					\__( 'Invalid DPoP proof: missing access token hash (ath).', 'activitypub' ),
					array( 'status' => 401 )
				);
			}

			$expected_ath = self::base64url_encode( hash( 'sha256', $access_token, true ) );

			if ( ! hash_equals( $expected_ath, $payload['ath'] ) ) {
				return new \WP_Error(
					'activitypub_dpop_ath_mismatch',
					\__( 'Invalid DPoP proof: access token hash mismatch.', 'activitypub' ),
					array( 'status' => 401 )
				);
			}
		}

		// Compute JWK thumbprint.
		$jkt = self::compute_jkt( $header['jwk'] );

		if ( \is_wp_error( $jkt ) ) {
			return $jkt;
		}

		return array( 'jkt' => $jkt );
	}

	/**
	 * Compute the JWK Thumbprint (RFC 7638) of a JWK.
	 *
	 * @since unreleased
	 *
	 * @param array $jwk The JWK as an associative array.
	 * @return string|\WP_Error The base64url-encoded thumbprint, or WP_Error.
	 */
	public static function compute_jkt( $jwk ) {
		if ( ! isset( $jwk['kty'] ) ) {
			return new \WP_Error(
				'activitypub_dpop_invalid_jwk',
				\__( 'Invalid JWK: missing key type.', 'activitypub' ),
				array( 'status' => 401 )
			);
		}

		// Build canonical JSON with required members in lexicographic order.
		switch ( $jwk['kty'] ) {
			case 'EC':
				if ( ! isset( $jwk['crv'], $jwk['x'], $jwk['y'] ) ) {
					return new \WP_Error(
						'activitypub_dpop_invalid_jwk',
						\__( 'Invalid EC JWK: missing required parameters.', 'activitypub' ),
						array( 'status' => 401 )
					);
				}
				// RFC 7638: lexicographic order of required members.
				$canonical = array(
					'crv' => $jwk['crv'],
					'kty' => $jwk['kty'],
					'x'   => $jwk['x'],
					'y'   => $jwk['y'],
				);
				break;

			case 'RSA':
				if ( ! isset( $jwk['e'], $jwk['n'] ) ) {
					return new \WP_Error(
						'activitypub_dpop_invalid_jwk',
						\__( 'Invalid RSA JWK: missing required parameters.', 'activitypub' ),
						array( 'status' => 401 )
					);
				}
				// RFC 7638: lexicographic order of required members.
				$canonical = array(
					'e'   => $jwk['e'],
					'kty' => $jwk['kty'],
					'n'   => $jwk['n'],
				);
				break;

			default:
				return new \WP_Error(
					'activitypub_dpop_unsupported_kty',
					\__( 'Unsupported JWK key type.', 'activitypub' ),
					array( 'status' => 401 )
				);
		}

		$json = wp_json_encode( $canonical, JSON_UNESCAPED_SLASHES );

		return self::base64url_encode( hash( 'sha256', $json, true ) );
	}

	/**
	 * Extract the DPoP proof from the request headers.
	 *
	 * @since unreleased
	 *
	 * @return string|null The DPoP proof JWT or null if not present.
	 */
	public static function get_proof_from_request() {
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Opaque JWT token, must not be altered.
		if ( ! empty( $_SERVER['HTTP_DPOP'] ) ) {
			return \wp_unslash( $_SERVER['HTTP_DPOP'] );
		}
		// phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		// Fallback: Apache headers.
		if ( function_exists( 'apache_request_headers' ) ) {
			$headers = apache_request_headers();
			foreach ( $headers as $key => $value ) {
				if ( 'dpop' === strtolower( $key ) ) {
					return $value;
				}
			}
		}

		return null;
	}

	/**
	 * Get the HTTP URI for the current request.
	 *
	 * Returns scheme + host + path (no query string or fragment) as required by RFC 9449.
	 *
	 * @since unreleased
	 *
	 * @return string The HTTP URI.
	 */
	public static function get_request_uri() {
		$scheme = \is_ssl() ? 'https' : 'http';
		$host   = isset( $_SERVER['HTTP_HOST'] ) ? \sanitize_text_field( \wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
		$path   = isset( $_SERVER['REQUEST_URI'] ) ? \sanitize_text_field( \wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';

		// Remove query string from path.
		$path = strtok( $path, '?' );

		return $scheme . '://' . $host . $path;
	}

	/**
	 * Get the HTTP method for the current request.
	 *
	 * @since unreleased
	 *
	 * @return string The HTTP method.
	 */
	public static function get_request_method() {
		return isset( $_SERVER['REQUEST_METHOD'] ) ? \sanitize_text_field( \wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : 'GET';
	}

	/**
	 * Verify a JWT signature using the JWK from the header.
	 *
	 * @param string $signing_input The header.payload string to verify.
	 * @param string $signature     The raw signature bytes.
	 * @param string $alg           The algorithm (ES256 or RS256).
	 * @param array  $jwk           The JWK public key.
	 * @return true|\WP_Error True on success, WP_Error on failure.
	 */
	private static function verify_signature( $signing_input, $signature, $alg, $jwk ) {
		$pem = self::jwk_to_pem( $jwk, $alg );

		if ( \is_wp_error( $pem ) ) {
			return $pem;
		}

		$public_key = openssl_pkey_get_public( $pem );

		if ( false === $public_key ) {
			return new \WP_Error(
				'activitypub_dpop_invalid_key',
				\__( 'Invalid DPoP proof: cannot parse public key.', 'activitypub' ),
				array( 'status' => 401 )
			);
		}

		if ( 'ES256' === $alg ) {
			// Convert from JWS format (R || S) to DER format for OpenSSL.
			$signature   = self::ecdsa_signature_to_der( $signature );
			$openssl_alg = OPENSSL_ALGO_SHA256;
		} else {
			// RS256.
			$openssl_alg = OPENSSL_ALGO_SHA256;
		}

		$result = openssl_verify( $signing_input, $signature, $public_key, $openssl_alg );

		if ( 1 !== $result ) {
			return new \WP_Error(
				'activitypub_dpop_bad_signature',
				\__( 'Invalid DPoP proof: signature verification failed.', 'activitypub' ),
				array( 'status' => 401 )
			);
		}

		return true;
	}

	/**
	 * Convert a JWK to PEM format.
	 *
	 * @param array  $jwk The JWK.
	 * @param string $alg The algorithm.
	 * @return string|\WP_Error PEM string or WP_Error.
	 */
	private static function jwk_to_pem( $jwk, $alg ) {
		if ( ! isset( $jwk['kty'] ) ) {
			return new \WP_Error(
				'activitypub_dpop_invalid_jwk',
				\__( 'Invalid JWK: missing key type.', 'activitypub' ),
				array( 'status' => 401 )
			);
		}

		if ( 'RS256' === $alg && 'RSA' === $jwk['kty'] ) {
			return self::rsa_jwk_to_pem( $jwk );
		}

		if ( 'ES256' === $alg && 'EC' === $jwk['kty'] ) {
			return self::ec_jwk_to_pem( $jwk );
		}

		return new \WP_Error(
			'activitypub_dpop_unsupported_key',
			\__( 'Unsupported JWK key type for algorithm.', 'activitypub' ),
			array( 'status' => 401 )
		);
	}

	/**
	 * Convert an RSA JWK to PEM format.
	 *
	 * @param array $jwk The RSA JWK with 'n' and 'e' parameters.
	 * @return string|\WP_Error PEM string or WP_Error.
	 */
	private static function rsa_jwk_to_pem( $jwk ) {
		if ( ! isset( $jwk['n'], $jwk['e'] ) ) {
			return new \WP_Error(
				'activitypub_dpop_invalid_jwk',
				\__( 'Invalid RSA JWK: missing n or e.', 'activitypub' ),
				array( 'status' => 401 )
			);
		}

		$n = self::base64url_decode( $jwk['n'] );
		$e = self::base64url_decode( $jwk['e'] );

		// Build DER-encoded RSA public key.
		$n_der = self::unsigned_int_to_der( $n );
		$e_der = self::unsigned_int_to_der( $e );

		$rsa_sequence    = self::der_sequence( $n_der . $e_der );
		$rsa_bitstring   = self::der_bitstring( $rsa_sequence );
		$algorithm_id    = self::rsa_algorithm_identifier();
		$public_key_info = self::der_sequence( $algorithm_id . $rsa_bitstring );

		// phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Required for PEM encoding.
		$pem = "-----BEGIN PUBLIC KEY-----\n"
			. chunk_split( base64_encode( $public_key_info ), 64, "\n" )
			. '-----END PUBLIC KEY-----';
		// phpcs:enable WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode

		return $pem;
	}

	/**
	 * Convert an EC (P-256) JWK to PEM format.
	 *
	 * @param array $jwk The EC JWK with 'x', 'y', and 'crv' parameters.
	 * @return string|\WP_Error PEM string or WP_Error.
	 */
	private static function ec_jwk_to_pem( $jwk ) {
		if ( ! isset( $jwk['x'], $jwk['y'], $jwk['crv'] ) ) {
			return new \WP_Error(
				'activitypub_dpop_invalid_jwk',
				\__( 'Invalid EC JWK: missing x, y, or crv.', 'activitypub' ),
				array( 'status' => 401 )
			);
		}

		if ( 'P-256' !== $jwk['crv'] ) {
			return new \WP_Error(
				'activitypub_dpop_unsupported_curve',
				\__( 'Only P-256 curve is supported for ES256.', 'activitypub' ),
				array( 'status' => 401 )
			);
		}

		$x = str_pad( self::base64url_decode( $jwk['x'] ), 32, "\0", STR_PAD_LEFT );
		$y = str_pad( self::base64url_decode( $jwk['y'] ), 32, "\0", STR_PAD_LEFT );

		// Uncompressed point: 0x04 || x || y.
		$point = "\x04" . $x . $y;

		// OID for P-256 (1.2.840.10045.3.1.7) = 06 08 2a 86 48 ce 3d 03 01 07.
		// OID for EC public key (1.2.840.10045.2.1) = 06 07 2a 86 48 ce 3d 02 01.
		// Algorithm identifier: SEQUENCE { OID ecPublicKey, OID P-256 }.
		$algorithm_id = "\x30\x13\x06\x07\x2a\x86\x48\xce\x3d\x02\x01\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07";

		$bitstring       = self::der_bitstring( $point );
		$public_key_info = self::der_sequence( $algorithm_id . $bitstring );

		// phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Required for PEM encoding.
		$pem = "-----BEGIN PUBLIC KEY-----\n"
			. chunk_split( base64_encode( $public_key_info ), 64, "\n" )
			. '-----END PUBLIC KEY-----';
		// phpcs:enable WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode

		return $pem;
	}

	/**
	 * Convert an ECDSA JWS signature (R || S concatenation) to DER format.
	 *
	 * OpenSSL expects DER-encoded signatures, but JWS uses raw R||S concatenation.
	 *
	 * @param string $signature The raw R||S signature (64 bytes for P-256).
	 * @return string The DER-encoded signature.
	 */
	private static function ecdsa_signature_to_der( $signature ) {
		$length = strlen( $signature );
		$half   = (int) ( $length / 2 );

		$r = substr( $signature, 0, $half );
		$s = substr( $signature, $half );

		$r_der = self::unsigned_int_to_der( $r );
		$s_der = self::unsigned_int_to_der( $s );

		return self::der_sequence( $r_der . $s_der );
	}

	/**
	 * Encode a raw unsigned integer as a DER INTEGER.
	 *
	 * @param string $raw The raw bytes.
	 * @return string The DER-encoded INTEGER.
	 */
	private static function unsigned_int_to_der( $raw ) {
		// Remove leading zero bytes.
		$raw = ltrim( $raw, "\x00" );

		if ( '' === $raw ) {
			$raw = "\x00";
		}

		// If high bit is set, prepend a zero byte.
		if ( ord( $raw[0] ) & 0x80 ) {
			$raw = "\x00" . $raw;
		}

		return "\x02" . self::der_length( strlen( $raw ) ) . $raw;
	}

	/**
	 * Encode a DER SEQUENCE.
	 *
	 * @param string $contents The sequence contents.
	 * @return string The DER-encoded SEQUENCE.
	 */
	private static function der_sequence( $contents ) {
		return "\x30" . self::der_length( strlen( $contents ) ) . $contents;
	}

	/**
	 * Encode a DER BIT STRING (with zero unused bits).
	 *
	 * @param string $contents The bit string contents.
	 * @return string The DER-encoded BIT STRING.
	 */
	private static function der_bitstring( $contents ) {
		// Prepend 0x00 for "zero unused bits".
		$contents = "\x00" . $contents;
		return "\x03" . self::der_length( strlen( $contents ) ) . $contents;
	}

	/**
	 * Encode a DER length value.
	 *
	 * @param int $length The length.
	 * @return string The DER-encoded length.
	 */
	private static function der_length( $length ) {
		if ( $length < 0x80 ) {
			return chr( $length );
		}

		$temp   = '';
		$number = $length;
		while ( $number > 0 ) {
			$temp   = chr( $number & 0xFF ) . $temp;
			$number = $number >> 8;
		}

		return chr( 0x80 | strlen( $temp ) ) . $temp;
	}

	/**
	 * Get the RSA algorithm identifier for SubjectPublicKeyInfo.
	 *
	 * @return string DER-encoded AlgorithmIdentifier for RSA.
	 */
	private static function rsa_algorithm_identifier() {
		// DER-encoded SEQUENCE containing OID for rsaEncryption and NULL params.
		return "\x30\x0d\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00";
	}

	/**
	 * Base64url-decode a string.
	 *
	 * @param string $data The base64url-encoded string.
	 * @return string The decoded data.
	 */
	public static function base64url_decode( $data ) {
		$remainder = strlen( $data ) % 4;
		if ( $remainder ) {
			$data .= str_repeat( '=', 4 - $remainder );
		}
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Required for JWT/JWK decoding per RFC 7515.
		return base64_decode( strtr( $data, '-_', '+/' ) );
	}

	/**
	 * Base64url-encode a string.
	 *
	 * @param string $data The raw data.
	 * @return string The base64url-encoded string.
	 */
	public static function base64url_encode( $data ) {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Required for JWT/JWK encoding per RFC 7515.
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
	}

	/**
	 * JSON-decode a base64url-encoded string.
	 *
	 * @param string $data The base64url-encoded JSON string.
	 * @return array|null The decoded array or null on failure.
	 */
	private static function json_decode_base64url( $data ) {
		$decoded = self::base64url_decode( $data );
		$json    = json_decode( $decoded, true );

		return is_array( $json ) ? $json : null;
	}

	/**
	 * Normalize a URI for DPoP htu comparison.
	 *
	 * Strips query string and fragment, lowercases scheme and host.
	 *
	 * @param string $uri The URI to normalize.
	 * @return string The normalized URI.
	 */
	private static function normalize_uri( $uri ) {
		$parts = \wp_parse_url( $uri );

		if ( ! $parts ) {
			return $uri;
		}

		$scheme = isset( $parts['scheme'] ) ? strtolower( $parts['scheme'] ) : 'https';
		$host   = isset( $parts['host'] ) ? strtolower( $parts['host'] ) : '';
		$port   = isset( $parts['port'] ) ? ':' . $parts['port'] : '';
		$path   = isset( $parts['path'] ) ? $parts['path'] : '/';

		// Omit default ports.
		if ( ( 'https' === $scheme && ':443' === $port ) || ( 'http' === $scheme && ':80' === $port ) ) {
			$port = '';
		}

		return $scheme . '://' . $host . $port . $path;
	}
}
