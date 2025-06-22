<?php
/**
 * ActivityPub HTTP Message Signature Standard.
 *
 * This class implements the HTTP Message Signature standard for verifying HTTP signatures.
 *
 * @package Activitypub\Signature
 */

// phpcs:disable WordPress.Security.ValidatedSanitizedInput, WordPress.PHP.DiscouragedPHPFunctions

namespace Activitypub\Signature;

use Activitypub\Signature;

/**
 * Class Http_Message_Signature.
 *
 * Implements the HTTP Message Signature standard for verifying HTTP signatures.
 *
 * @see https://www.rfc-editor.org/rfc/rfc9421.html
 */
class Http_Message_Signature implements Signature_Standard {

	/**
	 * Verify the HTTP Signature against a request.
	 *
	 * @param array       $headers The HTTP headers.
	 * @param string|null $body    The request body, if applicable.
	 * @return bool|\WP_Error True, if the signature is valid, WP_Error on failure.
	 */
	public function verify( array $headers, $body = null ) {
		$input            = $headers['signature-input'][0];
		$signature_header = $headers['signature'][0];

		if ( ! \preg_match( '/sig1=\\(([^)]*)\\)(.*)/', $input, $matches ) ) {
			return new \WP_Error( 'invalid_signature_input', 'Invalid Signature-Input format.' );
		}

		$components = \preg_split( '/\\s+/', trim( $matches[1] ) );
		$params_str = \trim( $matches[2], '; ' );
		$params     = array();
		foreach ( \explode( ';', $params_str ) as $param ) {
			if ( \preg_match( '/(\w+)=("?)([^";]+)\2/', \trim( $param ), $matches ) ) {
				$params[ \strtolower( $matches[1] ) ] = $matches[3];
			}
		}

		$created = isset( $params['created'] ) ? (int) $params['created'] : null;
		$expires = isset( $params['expires'] ) ? (int) $params['expires'] : null;
		$nonce   = $params['nonce'] ?? null;
		$alg     = \strtolower( $params['alg'] ?? '' );
		$key_id  = $params['keyid'] ?? null;

		if ( strpos( $alg, 'rsa-pss-' ) === 0 && version_compare( PHP_VERSION, '8.1.0', '<' ) ) {
			return new \WP_Error( 'unsupported_pss', 'RSA-PSS algorithms is not supported.' );
		}

		if ( ! $key_id ) {
			return new \WP_Error( 'missing_keyid', 'Missing keyId in signature parameters.' );
		}

		if ( $created && $created > \time() + MINUTE_IN_SECONDS ) {
			return new \WP_Error( 'invalid_created', 'The signature creation time is in the future.' );
		}
		if ( $expires && $expires < \time() ) {
			return new \WP_Error( 'expired_signature', 'The signature has expired.' );
		}

		$signature_string = '';
		foreach ( $components as $component ) {
			$key = \strtolower( \trim( $component, '"' ) );

			switch ( $key ) {
				case '@method':
					$value = \strtolower( $_SERVER['REQUEST_METHOD'] ?? 'get' );
					break;
				case '@target-uri':
					$value = \set_url_scheme( ( $_SERVER['HTTP_HOST'] ?? '' ) . ( $_SERVER['REQUEST_URI'] ?? '/' ) );
					break;
				case '@path':
					$value = \wp_parse_url( $_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH );
					break;
				case '@query':
					$value = \wp_parse_url( $_SERVER['REQUEST_URI'] ?? '/', PHP_URL_QUERY );
					break;
				default:
					$raw   = $headers[ $key ][0] ?? '';
					$value = \preg_replace( '/\s+/', ' ', \trim( $raw ) );
			}

			$signature_string .= $key . ': ' . $value . PHP_EOL;
		}

		$signature_string .= '@signature-params: (' . \implode( ' ', $components ) . ')';
		if ( $created ) {
			$signature_string .= ';created=' . $created;
		}
		if ( $expires ) {
			$signature_string .= ';expires=' . $expires;
		}
		if ( $nonce ) {
			$signature_string .= ';nonce="' . $nonce . '"';
		}
		if ( $alg ) {
			$signature_string .= ';alg=' . $alg;
		}
		$signature_string .= ';keyid="' . $key_id . '"';

		if ( ! \preg_match( '/sig1="([^"]+)"/', $signature_header, $sig_match ) ) {
			return new \WP_Error( 'invalid_signature_value', 'Malformed Signature header.' );
		}
		$signature = \base64_decode( $sig_match[1] );

		$public_key = Signature::get_remote_key( $key_id );
		if ( \is_wp_error( $public_key ) ) {
			return $public_key;
		}

		$algorithm = $this->resolve_algorithm( $alg, $public_key );
		if ( \is_wp_error( $algorithm ) ) {
			return $algorithm;
		}

		// Digest verification.
		if ( isset( $headers['digest'] ) && null !== $body ) {
			$digest_header                   = $headers['digest'][0];
			list( $digest_alg, $digest_val ) = \explode( '=', $digest_header, 2 );
			$calc                            = \base64_encode( \hash( \strtolower( $digest_alg ), $body, true ) );

			if ( $digest_val !== $calc ) {
				return new \WP_Error( 'digest_mismatch', 'The Digest header value does not match the body.' );
			}
		}

		return \openssl_verify( $signature_string, $signature, $public_key, $algorithm ) > 0;
	}

	/**
	 * Resolve and validate the HTTP Signature algorithm from `alg=` parameter and key.
	 *
	 * @param string   $alg_string The alg= parameter value (e.g., 'rsa-pss-sha512').
	 * @param resource $public_key An OpenSSL public key resource.
	 *
	 * @return int|\WP_Error OpenSSL algorithm constant or WP_Error.
	 */
	protected function resolve_algorithm( $alg_string, $public_key ) {
		if ( ! $public_key || ! \is_resource( $public_key ) ) {
			return new \WP_Error( 'invalid_key', 'Invalid public key resource.' );
		}

		$details = \openssl_pkey_get_details( $public_key );
		if ( ! $details || ! isset( $details['type'] ) ) {
			return new \WP_Error( 'invalid_key_details', 'Unable to read public key details.' );
		}

		$key_type   = $details['type'];
		$alg_string = \strtolower( $alg_string );

		$map = array(
			// RSA PKCS#1 v1.5.
			'rsa-v1_5-sha256'   => array(
				'type' => OPENSSL_KEYTYPE_RSA,
				'algo' => OPENSSL_ALGO_SHA256,
			),
			'rsa-v1_5-sha384'   => array(
				'type' => OPENSSL_KEYTYPE_RSA,
				'algo' => OPENSSL_ALGO_SHA384,
			),
			'rsa-v1_5-sha512'   => array(
				'type' => OPENSSL_KEYTYPE_RSA,
				'algo' => OPENSSL_ALGO_SHA512,
			),

			// RSA PSS (note: not supported in openssl_verify() until PHP 8.1).
			'rsa-pss-sha256'    => array(
				'type' => OPENSSL_KEYTYPE_RSA,
				'algo' => OPENSSL_ALGO_SHA256,
			),
			'rsa-pss-sha384'    => array(
				'type' => OPENSSL_KEYTYPE_RSA,
				'algo' => OPENSSL_ALGO_SHA384,
			),
			'rsa-pss-sha512'    => array(
				'type' => OPENSSL_KEYTYPE_RSA,
				'algo' => OPENSSL_ALGO_SHA512,
			),

			// ECDSA.
			'ecdsa-p256-sha256' => array(
				'type' => OPENSSL_KEYTYPE_EC,
				'algo' => OPENSSL_ALGO_SHA256,
			),
			'ecdsa-p384-sha384' => array(
				'type' => OPENSSL_KEYTYPE_EC,
				'algo' => OPENSSL_ALGO_SHA384,
			),
			'ecdsa-p521-sha512' => array(
				'type' => OPENSSL_KEYTYPE_EC,
				'algo' => OPENSSL_ALGO_SHA512,
			),
		);

		if ( ! isset( $map[ $alg_string ] ) ) {
			return new \WP_Error( 'unsupported_alg', 'Unsupported or unknown alg parameter: ' . $alg_string );
		}

		if ( $map[ $alg_string ]['type'] !== $key_type ) {
			return new \WP_Error( 'alg_key_mismatch', 'Algorithm does not match public key type.' );
		}

		return $map[ $alg_string ]['algo'];
	}
}
