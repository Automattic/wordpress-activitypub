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

use Activitypub\Collection\Remote_Actors;

/**
 * Class Http_Message_Signature.
 *
 * Implements the HTTP Message Signature standard for verifying HTTP signatures.
 *
 * @see https://www.rfc-editor.org/rfc/rfc9421.html
 */
class Http_Message_Signature implements Http_Signature {

	/**
	 * Signature algorithms.
	 *
	 * @var int[][]
	 */
	private $algorithms = array(
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

	/**
	 * Digest algorithms.
	 *
	 * @var string[]
	 */
	private $digest_algorithms = array(
		'sha-256' => 'sha256',
		'sha-512' => 'sha512',
	);

	/**
	 * Generate RFC-9421 compliant Signature-Input and Signature headers for an outgoing HTTP request.
	 *
	 * @param array  $args The request arguments.
	 * @param string $url  The request URL.
	 *
	 * @return array Request arguments with signature headers.
	 */
	public function sign( $args, $url ) {
		// Standard components to sign.
		$components  = array(
			'"@method"'     => \strtoupper( $args['method'] ),
			'"@target-uri"' => $url,
			'"@authority"'  => \wp_parse_url( $url, PHP_URL_HOST ),
		);
		$identifiers = \array_keys( $components );

		// Add digest if provided.
		if ( isset( $args['body'] ) ) {
			$components['"content-digest"'] = $this->generate_digest( $args['body'] );
			$identifiers                    = \array_keys( $components );

			$args['headers']['Content-Digest'] = $components['"content-digest"'];
		}

		$params = array(
			'created' => \strtotime( $args['headers']['Date'] ),
			'keyid'   => $args['key_id'],
			'alg'     => 'rsa-v1_5-sha256',
		);

		// Build the signature base string as per RFC-9421.
		$signature_base = $this->get_signature_base_string( $components, $params );

		$signature = null;
		\openssl_sign( $signature_base, $signature, $args['private_key'], \OPENSSL_ALGO_SHA256 );
		$signature = \base64_encode( $signature );

		$args['headers']['Signature-Input'] = 'wp=(' . \implode( ' ', $identifiers ) . ')' . $this->get_params_string( $params );
		$args['headers']['Signature']       = 'wp=:' . $signature . ':';

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
		$parsed = $this->parse_signature_labels( $headers );
		if ( \is_wp_error( $parsed ) ) {
			return $parsed;
		}

		$errors = new \WP_Error();
		foreach ( $parsed as $data ) {
			$result = $this->verify_signature_label( $data, $headers, $body );
			if ( true === $result ) {
				return true;
			}

			if ( \is_wp_error( $result ) ) {
				$errors->add( $result->get_error_code(), $result->get_error_message() );
			}
		}

		// No valid signature found.
		$errors->add_data( array( 'status' => 401 ) );

		return $errors;
	}

	/**
	 * Generate a digest for the request body.
	 *
	 * @param string $body The request body.
	 *
	 * @return string The digest.
	 */
	public function generate_digest( $body ) {
		return 'sha-256=:' . \base64_encode( \hash( 'sha256', $body, true ) ) . ':';
	}

	/**
	 * Parse the Signature-Input and Signature headers.
	 *
	 * @param array $headers The HTTP headers.
	 * @return array|\WP_Error Parsed signature labels or WP_Error on failure.
	 */
	private function parse_signature_labels( array $headers ) {
		$parsed_inputs = array();
		\preg_match_all( '/(?P<label>\w+)=\((?P<components>[^)]*)\)(?P<params>[^,]*)/', $headers['signature_input'][0], $matches, PREG_SET_ORDER );

		foreach ( $matches as $match ) {
			$label      = $match['label'];
			$components = \preg_split( '/\s+/', \trim( $match['components'] ) );
			$param_str  = \trim( $match['params'], '; ' );
			$params     = array();

			foreach ( \explode( ';', $param_str ) as $param ) {
				if ( \preg_match( '/(\w+)=("?)([^";]+)\2/', \trim( $param ), $m ) ) {
					$params[ \strtolower( $m[1] ) ] = $m[3];
				}
			}

			if ( \preg_match( '/' . \preg_quote( $label, '/' ) . '=:([^:]+):/', $headers['signature'][0], $sig_match ) ) {
				$parsed_inputs[ $label ] = array(
					'components' => $components,
					'params'     => $params,
					'signature'  => \base64_decode( $sig_match[1] ),
				);
			}
		}

		if ( empty( $parsed_inputs ) ) {
			return new \WP_Error( 'no_valid_labels', 'No valid signature labels found.' );
		}

		return $parsed_inputs;
	}

	/**
	 * Verify a single signature label.
	 *
	 * @param array       $data     Parsed signature data.
	 * @param array       $headers  HTTP headers.
	 * @param string|null $body     Request body, if applicable.
	 * @return bool|\WP_Error True, if the signature is valid, WP_Error on failure.
	 */
	private function verify_signature_label( $data, $headers, $body ) {
		$params = $data['params'];

		// Timestamp verification.
		if ( isset( $params['created'] ) && (int) $params['created'] > \time() + MINUTE_IN_SECONDS ) {
			return new \WP_Error( 'invalid_created', 'The signature creation time is in the future.' );
		}
		if ( isset( $params['expires'] ) && (int) $params['expires'] < \time() ) {
			return new \WP_Error( 'expired_signature', 'The signature has expired.' );
		}

		// KeyId verification.
		if ( empty( $params['keyid'] ) ) {
			return new \WP_Error( 'missing_keyid', 'Missing keyId in signature parameters.' );
		}

		$public_key = Remote_Actors::get_public_key( $params['keyid'] );
		if ( \is_wp_error( $public_key ) ) {
			return $public_key;
		}

		// Algorithm verification.
		$algorithm = $this->verify_algorithm( $params['alg'] ?? '', $public_key );
		if ( \is_wp_error( $algorithm ) ) {
			return $algorithm;
		}

		// Digest verification.
		$result = $this->verify_content_digest( $headers, $body );
		if ( \is_wp_error( $result ) ) {
			return $result;
		}

		$components     = $this->get_component_values( $data['components'], $headers );
		$signature_base = $this->get_signature_base_string( $components, $params );

		$verified = \openssl_verify( $signature_base, $data['signature'], $public_key, $algorithm ) > 0;
		if ( ! $verified ) {
			return new \WP_Error( 'activitypub_signature', 'Invalid signature' );
		}

		return true;
	}

	/**
	 * Verify the Content-Digest header against the request body.
	 *
	 * @param array       $headers The HTTP headers.
	 * @param string|null $body    The request body, if applicable.
	 * @return bool|\WP_Error True, if the signature is valid, WP_Error on failure.
	 */
	private function verify_content_digest( $headers, $body ) {
		if ( ! isset( $headers['content_digest'][0] ) || null === $body ) {
			return true;
		}

		$digests = \array_map( 'trim', \explode( ',', $headers['content_digest'][0] ) );

		foreach ( $digests as $digest ) {
			if ( \preg_match( '/^([a-z0-9-]+)=:(.+):$/i', $digest, $matches ) ) {
				list( , $alg, $encoded ) = $matches;

				if ( ! isset( $this->digest_algorithms[ $alg ] ) ) {
					return new \WP_Error( 'unsupported_digest', 'WordPress supports sha-256 and sha-512 in Digest header. Offered algorithm: ' . $alg );
				}

				if ( \hash_equals( $encoded, \base64_encode( \hash( $this->digest_algorithms[ $alg ], $body, true ) ) ) ) {
					return true;
				}
			}
		}

		return new \WP_Error( 'digest_mismatch', 'Content-Digest header value does not match body.' );
	}

	/**
	 * Resolve and validate the HTTP Signature algorithm from `alg=` parameter and key.
	 *
	 * @param string   $alg_string The alg= parameter value (e.g., 'rsa-pss-sha512').
	 * @param resource $public_key An OpenSSL public key resource.
	 *
	 * @return int|\WP_Error OpenSSL algorithm constant or WP_Error.
	 */
	private function verify_algorithm( $alg_string, $public_key ) {
		$details = \openssl_pkey_get_details( $public_key );
		if ( ! isset( $details['type'] ) ) {
			return new \WP_Error( 'invalid_key_details', 'Unable to read public key details.' );
		}

		// If alg_string is empty, determine algorithm based on public key.
		if ( empty( $alg_string ) ) {
			switch ( $details['type'] ) {
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
					switch ( $details['ec']['curve_name'] ?? '' ) {
						case 'prime256v1':
						case 'secp256r1':
							return \OPENSSL_ALGO_SHA256;
						case 'secp384r1':
							return \OPENSSL_ALGO_SHA384;
						case 'secp521r1':
							return \OPENSSL_ALGO_SHA512;
					}
			}
		}

		$alg_string = \strtolower( $alg_string );
		if ( \strpos( $alg_string, 'rsa-pss-' ) === 0 && \version_compare( PHP_VERSION, '8.1.0', '<' ) ) {
			return new \WP_Error( 'unsupported_pss', 'RSA-PSS algorithms are not supported.' );
		}

		if ( ! isset( $this->algorithms[ $alg_string ] ) ) {
			return new \WP_Error( 'unsupported_alg', 'Unsupported or unknown alg parameter: ' . $alg_string );
		}

		if ( $this->algorithms[ $alg_string ]['type'] !== $details['type'] ) {
			return new \WP_Error( 'alg_key_mismatch', 'Algorithm does not match public key type.' );
		}

		return $this->algorithms[ $alg_string ]['algo'];
	}

	/**
	 * Returns the base strings to compare the incoming signature with.
	 *
	 * @param array $components Signature components.
	 * @param array $params     Signature params.
	 *
	 * @return string Base string to compare signature with.
	 */
	private function get_signature_base_string( $components, $params ) {
		$signature_base = '';

		foreach ( $components as $component => $value ) {
			$signature_base .= $component . ': ' . $value . "\n";
		}

		$signature_base .= '"@signature-params": (' . \implode( ' ', \array_keys( $components ) ) . ')';
		$signature_base .= $this->get_params_string( $params );

		return $signature_base;
	}

	/**
	 * Returns the signature params in a string format.
	 *
	 * @param array $params Signature params.
	 *
	 * @return string Signature params.
	 */
	private function get_params_string( $params ) {
		$signature_params = '';

		foreach ( $params as $key => $value ) {
			if ( \is_numeric( $value ) ) {
				$signature_params .= ';' . $key . '=' . $value; // No quotes.
			} else {
				// Escape backslashes and double quotes per RFC-9421.
				$value             = \str_replace( array( '\\', '"' ), array( '\\\\', '\\"' ), $value );
				$signature_params .= ';' . $key . '="' . $value . '"'; // Double quotes.
			}
		}

		return $signature_params;
	}

	/**
	 * Generate signature components.
	 *
	 * @param array $components Signature component names.
	 * @param array $headers    HTTP headers.
	 *
	 * @return array Signature components.
	 */
	private function get_component_values( $components, $headers ) {
		$signature_components = array();

		foreach ( $components as $component ) {
			$key = \strtok( $component, ';' ); // See https://www.rfc-editor.org/rfc/rfc9421.html#name-query-parameters.
			$key = \strtolower( \trim( $key, '"' ) );

			switch ( $key ) {
				case '@method':
					$value = $_SERVER['REQUEST_METHOD'] ?? 'GET';
					break;

				case '@target-uri':
					$value = \set_url_scheme( '//' . ( $_SERVER['HTTP_HOST'] ?? '' ) . ( $_SERVER['REQUEST_URI'] ?? '/' ) );
					break;

				case '@authority':
					$value = $_SERVER['HTTP_HOST'] ?? '';
					break;

				case '@scheme':
					$value = \is_ssl() ? 'https' : 'http';
					break;

				case '@request-target':
					$value = $_SERVER['REQUEST_URI'] ?? '/';
					break;

				case '@path':
					$value = \wp_parse_url( $_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH );
					break;

				case '@query':
					$value = \wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY );
					$value = $value ? '?' . $value : '';
					break;

				case '@query-param':
					$value = '';
					if ( \preg_match( '/"@query-param";name="(?P<name>[^"]+)"/', $component, $matches ) ) {
						$query = \wp_parse_args( \wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY ) );
						$value = $query[ $matches['name'] ] ?? '';
					}
					break;

				default:
					/** Canonicalize header names. {@see WP_REST_Request::canonicalize_header_name()} */
					$key   = \str_replace( '-', '_', $key );
					$value = \preg_replace( '/\s+/', ' ', \trim( $headers[ $key ][0] ?? '' ) );
			}

			$signature_components[ $component ] = $value;
		}

		return $signature_components;
	}
}
