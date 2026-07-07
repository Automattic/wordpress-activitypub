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
		$components = array(
			'"@method"'     => \strtoupper( $args['method'] ),
			'"@target-uri"' => $url,
			'"@authority"'  => \wp_parse_url( $url, PHP_URL_HOST ),
		);

		if ( isset( $args['headers']['Collection-Synchronization'] ) ) {
			$components['"collection-synchronization"'] = $args['headers']['Collection-Synchronization'];
		}

		// Add digest if provided.
		if ( isset( $args['body'] ) ) {
			$components['"content-digest"']    = $this->generate_digest( $args['body'] );
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

		$args['headers'] = \array_merge(
			$args['headers'],
			$this->format_signature_headers( 'wp', $components, $params, \base64_encode( $signature ) )
		);

		return $args;
	}

	/**
	 * Sign an outgoing HTTP request with Ed25519 (RFC-9421 HTTP Message Signatures).
	 *
	 * Covers the derived components `@method` and `@target-uri` and the
	 * `content-digest` header with the parameters `created` and `keyid`,
	 * as required by the FASP specification.
	 *
	 * @see https://github.com/mastodon/fediverse_auxiliary_service_provider_specifications/blob/main/general/v0.1/protocol_basics.md
	 *
	 * @since unreleased
	 *
	 * @param array  $args        The request arguments, as passed to `wp_remote_request()`.
	 * @param string $url         The request URL.
	 * @param string $private_key The Ed25519 private key (raw binary, 64 bytes).
	 * @param string $key_id      The key ID to use in the signature.
	 *
	 * @return array Request arguments with `Content-Digest`, `Signature-Input` and `Signature` headers added.
	 */
	public function sign_request_ed25519( $args, $url, $private_key, $key_id ) {
		// The FASP spec requires a Content-Digest on all requests, even body-less ones.
		$digest = $this->generate_digest( $args['body'] ?? '' );

		$components = array(
			'"@method"'        => \strtoupper( $args['method'] ?? 'GET' ),
			'"@target-uri"'    => $url,
			'"content-digest"' => $digest,
		);

		$params = array(
			'created' => \time(),
			'keyid'   => $key_id,
		);

		$args['headers']['Content-Digest'] = $digest;
		$args['headers']                   = \array_merge( $args['headers'], $this->ed25519_signature_headers( $components, $params, $private_key ) );

		return $args;
	}

	/**
	 * Sign a WP_REST_Response with Ed25519 (RFC-9421 HTTP Message Signatures).
	 *
	 * Response signatures cover the derived component `@status` and the
	 * `content-digest` header, as required by the FASP specification. The
	 * response must already carry a `Content-Digest` header.
	 *
	 * @since unreleased
	 *
	 * @param \WP_REST_Response $response    The response to sign.
	 * @param string            $private_key The Ed25519 private key (raw binary, 64 bytes).
	 * @param string            $key_id      The key ID to use in the signature.
	 *
	 * @return \WP_REST_Response The response with signature headers added.
	 */
	public function sign_response_ed25519( $response, $private_key, $key_id ) {
		$components = array(
			'"@status"'        => (string) $response->get_status(),
			'"content-digest"' => $response->get_headers()['Content-Digest'] ?? '',
		);

		$params = array(
			'created' => \time(),
			'keyid'   => $key_id,
		);

		foreach ( $this->ed25519_signature_headers( $components, $params, $private_key ) as $header => $value ) {
			$response->header( $header, $value );
		}

		return $response;
	}

	/**
	 * Verify an Ed25519-signed HTTP response (RFC-9421 HTTP Message Signatures).
	 *
	 * Verifies a response signature covering the derived component `@status`
	 * and the `content-digest` header against a known public key, as used by
	 * the FASP protocol where the signer's key is exchanged at registration.
	 *
	 * @since unreleased
	 *
	 * @param int    $status     The HTTP response status code.
	 * @param array  $headers    The response headers as a flat name => value array.
	 * @param string $body       The response body.
	 * @param string $public_key The signer's Ed25519 public key (raw binary, 32 bytes).
	 *
	 * @return string|\WP_Error The verified keyId on success, WP_Error on failure.
	 */
	public function verify_response( $status, array $headers, $body, $public_key ) {
		// Normalize headers to the internal `name_with_underscores => array( value )` shape.
		$normalized = array();
		foreach ( $headers as $name => $value ) {
			$normalized[ \str_replace( '-', '_', \strtolower( $name ) ) ] = (array) $value;
		}

		if ( empty( $normalized['signature_input'][0] ) || empty( $normalized['signature'][0] ) ) {
			return new \WP_Error( 'missing_signature', 'The response is not signed.', array( 'status' => 401 ) );
		}

		$parsed = $this->parse_signature_labels( $normalized );
		if ( \is_wp_error( $parsed ) ) {
			return $parsed;
		}

		$digest_check = $this->verify_content_digest( $normalized, $body );
		if ( \is_wp_error( $digest_check ) ) {
			return $digest_check;
		}

		$errors = new \WP_Error();
		foreach ( $parsed as $data ) {
			$result = $this->verify_response_label( $data, $status, $normalized, $public_key );
			if ( true === $result ) {
				return $data['params']['keyid'];
			}

			$errors->add( $result->get_error_code(), $result->get_error_message() );
		}

		$errors->add_data( array( 'status' => 401 ) );

		return $errors;
	}

	/**
	 * Verify a single Ed25519 response signature label.
	 *
	 * @param array  $data       Parsed signature data.
	 * @param int    $status     The HTTP response status code.
	 * @param array  $headers    Normalized response headers.
	 * @param string $public_key The signer's Ed25519 public key (raw binary, 32 bytes).
	 *
	 * @return bool|\WP_Error True if the signature is valid, WP_Error on failure.
	 */
	private function verify_response_label( $data, $status, $headers, $public_key ) {
		$params = $data['params'];

		$result = $this->verify_timestamps( $params );
		if ( \is_wp_error( $result ) ) {
			return $result;
		}

		if ( empty( $params['keyid'] ) ) {
			return new \WP_Error( 'missing_keyid', 'Missing keyId in signature parameters.' );
		}

		// Response signatures must cover @status and content-digest.
		$covered = \array_map(
			function ( $component ) {
				return \strtolower( \trim( $component, '"' ) );
			},
			$data['components']
		);
		if ( ! \in_array( '@status', $covered, true ) || ! \in_array( 'content-digest', $covered, true ) ) {
			return new \WP_Error( 'invalid_components', 'The response signature does not cover @status and content-digest.' );
		}

		$components = array();
		foreach ( $data['components'] as $component ) {
			$key = \strtolower( \trim( $component, '"' ) );

			if ( '@status' === $key ) {
				$components[ $component ] = (string) $status;
			} else {
				$components[ $component ] = \preg_replace( '/\s+/', ' ', \trim( $headers[ \str_replace( '-', '_', $key ) ][0] ?? '' ) );
			}
		}

		$signature_base = $this->get_signature_base_string( $components, $params );

		return $this->verify_ed25519_signature( $signature_base, $data['signature'], $public_key );
	}

	/**
	 * Verify an Ed25519 signature.
	 *
	 * @since unreleased
	 *
	 * @param string $message    The message that was signed.
	 * @param string $signature  The signature to verify.
	 * @param string $public_key The Ed25519 public key (raw binary, 32 bytes).
	 *
	 * @return bool|\WP_Error True if valid, WP_Error on failure.
	 */
	private function verify_ed25519_signature( $message, $signature, $public_key ) {
		if ( \strlen( $signature ) !== SODIUM_CRYPTO_SIGN_BYTES ) {
			return new \WP_Error(
				'invalid_signature_length',
				\sprintf( 'Invalid Ed25519 signature length: expected %d bytes, got %d', SODIUM_CRYPTO_SIGN_BYTES, \strlen( $signature ) )
			);
		}

		if ( \strlen( $public_key ) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES ) {
			return new \WP_Error(
				'invalid_key_length',
				\sprintf( 'Invalid Ed25519 public key length: expected %d bytes, got %d', SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES, \strlen( $public_key ) )
			);
		}

		try {
			$verified = \sodium_crypto_sign_verify_detached( $signature, $message, $public_key );
		} catch ( \Exception $e ) {
			return new \WP_Error( 'ed25519_verification_failed', 'Ed25519 signature verification failed: ' . $e->getMessage() );
		}

		if ( ! $verified ) {
			return new \WP_Error( 'activitypub_signature', 'Invalid Ed25519 signature' );
		}

		return true;
	}

	/**
	 * Verify the `created` and `expires` signature parameters.
	 *
	 * Keep the pre-existing one-minute forward bound (tighter than the
	 * Cavage path's five minutes, appropriate for RFC 9421 where fresh
	 * peers tend to ship with synced clocks) and add one hour of
	 * backward drift. Without the past-side bound, peers that omit
	 * `expires` could present arbitrarily old signatures for replay.
	 *
	 * @since unreleased
	 *
	 * @param array $params Signature parameters.
	 *
	 * @return bool|\WP_Error True if the timestamps are acceptable, WP_Error otherwise.
	 */
	private function verify_timestamps( $params ) {
		$now = \time();
		if ( isset( $params['created'] ) ) {
			$created = (int) $params['created'];
			if ( $created > $now + MINUTE_IN_SECONDS ) {
				return new \WP_Error( 'invalid_created', 'The signature creation time is in the future.' );
			}
			if ( $created < $now - HOUR_IN_SECONDS ) {
				return new \WP_Error( 'expired_created', 'The signature creation time is too far in the past.' );
			}
		}
		if ( isset( $params['expires'] ) ) {
			$expires = (int) $params['expires'];
			if ( $expires < $now ) {
				return new \WP_Error( 'expired_signature', 'The signature has expired.' );
			}
			if ( $expires > $now + DAY_IN_SECONDS ) {
				return new \WP_Error( 'invalid_expires', 'The signature expiry time is too far in the future.' );
			}
		}

		/*
		 * Require a time anchor. Both `created` and `expires` are optional
		 * in RFC-9421; a signature without either has no freshness bound
		 * and could be replayed indefinitely.
		 */
		if ( ! isset( $params['created'] ) && ! isset( $params['expires'] ) ) {
			return new \WP_Error( 'missing_time_anchor', 'The signature is missing a time anchor (created or expires).' );
		}

		return true;
	}

	/**
	 * Verify the HTTP Signature against a request.
	 *
	 * @since 9.0.0 Returns the verified keyId on success instead of `true`.
	 *
	 * @param array       $headers The HTTP headers.
	 * @param string|null $body    The request body, if applicable.
	 * @return string|\WP_Error The verified keyId on success, WP_Error on failure.
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
				/*
				 * Return the keyId of the label that actually verified, so the caller binds
				 * against the real signer instead of guessing among several labels.
				 */
				return $data['params']['keyid'];
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

		$result = $this->verify_timestamps( $params );
		if ( \is_wp_error( $result ) ) {
			return $result;
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
	 * Sign a component set with Ed25519 and return the wire-format headers.
	 *
	 * @param array  $components   The covered components, keyed by their quoted identifier.
	 * @param array  $params       The signature parameters (created, keyid, …).
	 * @param string $private_key  The Ed25519 private key (raw binary, 64 bytes).
	 * @return array The `Signature-Input` and `Signature` headers.
	 */
	private function ed25519_signature_headers( $components, $params, $private_key ) {
		$signature = \sodium_crypto_sign_detached( $this->get_signature_base_string( $components, $params ), $private_key );

		return $this->format_signature_headers( 'sig', $components, $params, \base64_encode( $signature ) );
	}

	/**
	 * Assemble the RFC-9421 `Signature-Input` and `Signature` header values.
	 *
	 * @param string $label      The signature label (e.g. `wp` or `sig`).
	 * @param array  $components  The covered components, keyed by their quoted identifier.
	 * @param array  $params      The signature parameters.
	 * @param string $signature   The base64-encoded raw signature.
	 * @return array The `Signature-Input` and `Signature` headers.
	 */
	private function format_signature_headers( $label, $components, $params, $signature ) {
		return array(
			'Signature-Input' => $label . '=(' . \implode( ' ', \array_keys( $components ) ) . ')' . $this->get_params_string( $params ),
			'Signature'       => $label . '=:' . $signature . ':',
		);
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
