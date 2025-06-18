<?php
/**
 * ActivityPub HTTP Signature class.
 *
 * This class provides methods to sign and verify HTTP requests using the ActivityPub protocol.
 *
 * @package Activitypub
 */

namespace Activitypub;

use Bakame\Http\StructuredFields\Dictionary;
use Bakame\Http\StructuredFields\InnerList;
use Bakame\Http\StructuredFields\Item;
use Bakame\Http\StructuredFields\OuterList;
use Bakame\Http\StructuredFields\Parameters;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * ActivityPub HTTP Signature class.
 *
 * phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode, WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
 */
class Http_Signature {

	/**
	 * The key ID used for signing.
	 *
	 * @var string
	 */
	private $key_id;

	/**
	 * The private key used for signing.
	 *
	 * @var string
	 */
	private $private_key;

	/**
	 * The public key used for verification.
	 *
	 * @var string
	 */
	private $public_key;

	/**
	 * The algorithm used for signing.
	 *
	 * @var string
	 */
	private $algorithm;

	/**
	 * The signature ID.
	 *
	 * @var string
	 */
	private $signature_id = 'sig1';

	/**
	 * The creation timestamp.
	 *
	 * @var string
	 */
	private $created = '';

	/**
	 * The expiration timestamp.
	 *
	 * @var string
	 */
	private $expires = '';

	/**
	 * The nonce value.
	 *
	 * @var string
	 */
	private $nonce = '';

	/**
	 * The tag value.
	 *
	 * @var string
	 */
	private $tag = '';

	/**
	 * Mapping of HTTP header names to their structured field types.
	 *
	 * @var array
	 */
	private $structured_field_types = array(
		'accept'                           => 'list',
		'accept-encoding'                  => 'list',
		'accept-language'                  => 'list',
		'accept-patch'                     => 'list',
		'accept-post'                      => 'list',
		'accept-ranges'                    => 'list',
		'access-control-allow-credentials' => 'item',
		'access-control-allow-headers'     => 'list',
		'access-control-allow-methods'     => 'list',
		'access-control-allow-origin'      => 'item',
		'access-control-expose-headers'    => 'list',
		'access-control-max-age'           => 'item',
		'access-control-request-headers'   => 'list',
		'access-control-request-method'    => 'item',
		'age'                              => 'item',
		'allow'                            => 'list',
		'alpn'                             => 'list',
		'alt-svc'                          => 'dictionary',
		'alt-used'                         => 'item',
		'cache-control'                    => 'dictionary',
		'cdn-loop'                         => 'list',
		'clear-site-data'                  => 'list',
		'connection'                       => 'list',
		'content-encoding'                 => 'list',
		'content-language'                 => 'list',
		'content-length'                   => 'list',
		'content-location'                 => 'url',
		'content-type'                     => 'item',
		'cookie'                           => 'cookie',
		'cross-origin-resource-policy'     => 'item',
		'date'                             => 'date',
		'dnt'                              => 'item',
		'etag'                             => 'etag',
		'expect'                           => 'dictionary',
		'expect-ct'                        => 'dictionary',
		'expires'                          => 'date',
		'host'                             => 'item',
		'if-match'                         => 'etag',
		'if-modified-since'                => 'date',
		'if-none-match'                    => 'etag',
		'if-unmodified-since'              => 'date',
		'keep-alive'                       => 'dictionary',
		'last-modified'                    => 'date',
		'location'                         => 'url',
		'max-forwards'                     => 'item',
		'origin'                           => 'item',
		'pragma'                           => 'dictionary',
		'prefer'                           => 'dictionary',
		'preference-applied'               => 'dictionary',
		'referer'                          => 'url',
		'retry-after'                      => 'item',
		'sec-websocket-extensions'         => 'list',
		'sec-websocket-protocol'           => 'list',
		'sec-websocket-version'            => 'item',
		'server-timing'                    => 'list',
		'set-cookie'                       => 'cookie',
		'surrogate-control'                => 'dictionary',
		'te'                               => 'list',
		'timing-allow-origin'              => 'list',
		'trailer'                          => 'list',
		'transfer-encoding'                => 'list',
		'upgrade-insecure-requests'        => 'item',
		'vary'                             => 'list',
		'x-content-type-options'           => 'item',
		'x-frame-options'                  => 'item',
		'x-xss-protection'                 => 'list',
	);

	/**
	 * The original request.
	 *
	 * @var RequestInterface
	 */
	private $original_request;


	/**
	 * Get headers from a PSR-7 message interface.
	 *
	 * @param MessageInterface $interface The message interface to extract headers from.
	 * @return array Associative array of headers with lowercase keys.
	 */
	public function get_headers( $interface ): array {
		$headers = array();
		foreach ( $interface->getHeaders() as $name => $values ) {
			$headers[ strtolower( $name ) ] = implode( ', ', $values );
		}
		return $headers;
	}


	/**
	 * Sign a PSR-7 request with HTTP signatures.
	 *
	 * @param string                $covered_fields    Fields to be covered by the signature.
	 * @param MessageInterface      $interface         The message interface to sign.
	 * @param RequestInterface|null $original_request  Optional. Original request for context. Default null.
	 * @return MessageInterface The signed message interface.
	 */
	public function sign_request( string $covered_fields, MessageInterface $interface, RequestInterface $original_request = null ): MessageInterface {
		$headers = $this->get_headers( $interface );
		if ( $original_request ) {
			$this->set_original_request( $original_request );
		}

		$signed_headers = $this->sign(
			$headers,
			$covered_fields,
			$interface
		);

		foreach ( array( 'signature-input', 'signature' ) as $header ) {
			$interface = $interface->withHeader( $header, $signed_headers[ $header ] );
		}

		return $interface;
	}

	/**
	 * Verify a PSR-7 request with HTTP signatures.
	 *
	 * @param MessageInterface $interface         The message interface to verify.
	 * @param RequestInterface $original_request  Optional. Original request for context. Default null.
	 * @return bool Whether the signature is valid.
	 */
	public function verify_request( MessageInterface $interface, RequestInterface $original_request = null ): bool {
		$headers = array();
		if ( $original_request ) {
			$this->set_original_request( $original_request );
		}
		foreach ( $interface->getHeaders() as $name => $values ) {
			$headers[ strtolower( $name ) ] = implode( ', ', $values );
		}

		/* check the body digest if it's present */

		if ( isset( $headers['content-digest'] ) ) {
			$body = (string) $interface->getBody();
			if ( ! $this->is_body_digest_valid( $body, $headers['content-digest'] ) ) {
				return false;
			}
		}

		return $this->verify( $headers, $interface );
	}

	/**
	 * Check if the body digest is valid.
	 *
	 * From https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/Content-Digest
	 * The algorithm used to create a digest of the message content. Only two registered digest algorithms are
	 * considered secure: sha-512 and sha-256. The insecure (legacy) registered digest algorithms
	 * are: md5, sha (SHA-1), unixsum, unixcksum, adler (ADLER32) and crc32c.
	 *
	 * @param string $body        The body content to validate.
	 * @param string $header_value The Content-Digest header value.
	 * @return bool Whether the digest is valid.
	 */
	private function is_body_digest_valid( string $body, string $header_value ): bool {
		if ( ! preg_match( '/sha-(.*?)=:(.*?):/', $header_value, $matches ) ) {
			return false;
		}
		if ( ! in_array( $matches[1], array( '256', '512' ), true ) ) {
			return false;
		}

		$algorithm = 'sha' . $matches[1];

		$expected_digest = base64_decode( $matches[2] );
		$actual_digest   = hash( $algorithm, $body, true );

		return hash_equals( $expected_digest, $actual_digest );
	}

	/**
	 * Sign HTTP headers with the configured algorithm.
	 *
	 * @param array            $headers        The headers to include in the signature.
	 * @param string           $covered_fields The fields to be covered by the signature.
	 * @param MessageInterface $interface     The message interface.
	 * @return array The signature and signature-input headers.
	 */
	public function sign( array $headers, string $covered_fields, MessageInterface $interface ): array {
		$signature_components = array();
		$processed_components = array();

		$dict = $this->parse_structured_dict( $covered_fields );

		if ( $dict->isNotEmpty() ) {
			$covered_structured_fields = $dict->__toString();
			$indices                   = $dict->indices();
			foreach ( $indices as $index ) {
				$member = $dict->getByIndex( $index );
				if ( ! $member ) {
					throw new \Exception( 'Index ' . $index . ' not found' );
				}
				if ( in_array( $member, $processed_components, true ) ) {
					throw new \Exception( 'Duplicate member found' );
				}
				$processed_components[] = $member;
				$signature_components[] = $this->canonicalize_component( $member, $headers, $interface );
			}
		}

		$signature_input = $covered_structured_fields . ';keyid="'
							. $this->key_id . '";alg="' . $this->algorithm . '"'
							. ( ( $this->created ) ? ';created=' . $this->created : '' )
							. ( ( $this->expires ) ? ';expires=' . $this->expires : '' )
							. ( ( $this->nonce ) ? ';nonce="' . $this->nonce . '"' : '' )
							. ( ( $this->tag ) ? ';tag="' . $this->tag . '"' : '' );

		/**
		 * Always include @signature-params in the result.
		 */
		$signature_components[] = '"@signature-params": ' . $signature_input;

		$signature_base = implode( "\n", $signature_components );
		$signature      = $this->create_signature( $signature_base );

		$headers['signature-input'] = "$this->signature_id=$signature_input";
		$headers['signature']       = "$this->signature_id=:$signature:";

		return $headers;
	}

	public function verify( array $headers, $interface ): bool {
		if ( ! isset( $headers['signature-input'], $headers['signature'] ) ) {
			return false;
		}
		$headers[] = 'signature-params';

		$sig_input_dict = $this->parse_structured_dict( $headers['signature-input'] );

		$signature_components = array();

		if ( $sig_input_dict->isNotEmpty() ) {
			$indices = $sig_input_dict->indices();
			foreach ( $indices as $index ) {
				list( $dict_name, $members ) = $sig_input_dict->getByIndex( $index );

				if ( $members instanceof InnerList ) {
					$components    = $members;
					$inner_indices = $members->indices();
					foreach ( $inner_indices as $inner_index ) {
						$member                               = $members->getByIndex( $inner_index );
						$signature_components[ $dict_name ][] = $this->canonicalize_component( $member, $headers, $interface );
					}
				}
			}
			$top_level_params = $this->extract_parameters( $components );
			if ( isset( $top_level_params['expires'] ) ) {
				$expires = (int) $top_level_params['expires'];
				if ( $expires < time() ) {
					return false;
				}
			}
		}

		$sig_dict = $this->parse_structured_dict( $headers['signature'] );
		if ( $sig_dict->isNotEmpty() ) {
			$indices = $sig_dict->indices();
			foreach ( $indices as $index ) {
				list( $dict_name, $members ) = $sig_dict->getByIndex( $index );
				if ( $members instanceof Item ) {
					$signatures[ $dict_name ] = $members->value();
				}
				if ( $members instanceof InnerList ) {
					$inner_indices = $members->indices();
					foreach ( $inner_indices as $inner_index ) {
						$signatures[ $dict_name ][] = $members->getByIndex( $inner_index );
					}
				}
			}
		}

		foreach ( $signature_components as $dict_name => $dict_components ) {
			$named_signature_components   = $signature_components[ $dict_name ];
			$signature_params_str         = $sig_input_dict[ $dict_name ]->toHttpValue();
			$named_signature_components[] = '"@signature-params": ' . $signature_params_str;
			$signature_base               = implode( "\n", $named_signature_components );
			if ( ! isset( $sig_dict[ $dict_name ] ) ) {
				return false;
			}

			$decoded_sig = base64_decode( trim( $sig_dict[ $dict_name ]->__toString(), ':' ) );
			return $this->verify_signature( $signature_base, $decoded_sig, $params['alg'] ?? $this->algorithm );
		}
		return false;
	}

	/**
	 * Extract parameters from a structured field.
	 *
	 * @param object $field The structured field to extract parameters from.
	 * @return array Associative array of parameters.
	 */
	protected function extract_parameters( $field ): array {
		$parameters   = array();
		$field_params = $field->parameters();

		if ( $field_params->isNotEmpty() ) {
			$indices = $field_params->indices();
			foreach ( $indices as $index ) {
				[$name, $item]       = $field_params->getByIndex( $index );
				$parameters[ $name ] = $item->value();
			}
		}
		return $parameters;
	}

	/**
	 * Canonicalize a component for signature calculation.
	 *
	 * @param string           $field     The field name to canonicalize.
	 * @param array            $headers   The headers array.
	 * @param MessageInterface $interface The message interface.
	 * @return string The canonicalized component string.
	 */
	private function canonicalize_component( $field, array $headers, MessageInterface $interface ): string {
		$field_name = $field->value();
		$parameters = $this->extract_parameters( $field );
		if ( isset( $parameters['bs'] ) && isset( $parameters['sf'] ) ) {
			throw new \Exception( 'Cannot use both bs and sf' );
		}

		$which_request = $interface;
		if ( isset( $parameters['req'] ) && $interface instanceof ResponseInterface ) {
			$which_request = $this->get_original_request();
		}
		$which_headers = $headers;

		if ( isset( $parameters['tr'] ) ) {
			$which_headers = $which_request->getTrailers();
		}

		list( $name, $value ) = $this->get_field_value( $field_name, $which_request, $which_headers, $parameters );

		if ( isset( $parameters['bs'] ) ) {
			$result = $name . ';bs: ';
			$values = $which_request->getHeader( $field_name );
			if ( ! $values ) {
				return '';
			}
			if ( ! is_array( $values ) ) {
				$values = array( $values );
			}
			foreach ( $values as $value ) {
				$value   = trim( $value );
				$result .= ':' . base64_encode( $value ) . ':, ';
			}
			return $values ? rtrim( $result, ', ' ) : $result;
		}

		if ( isset( $parameters['sf'] ) ) {
			$value = $this->apply_structured_field( $name, $value );
			return $name . ';sf: ' . $value;
		}
		if ( isset( $parameters['key'] ) ) {
			$child_name = $parameters['key'];
			$value      = $this->apply_single_key_value( $name, $child_name, $value );
			return $name . ';key="' . $child_name . '": ' . $value;
		}
		return $name . ': ' . $value;
	}

	/**
	 * Get the value for a specific field from the message interface.
	 *
	 * @param string           $field_name The name of the field to get the value for.
	 * @param MessageInterface $interface  The message interface to extract values from.
	 * @param array            $headers    The headers array.
	 * @param array            $parameters Additional parameters for field processing.
	 * @return array An array containing the field name and value.
	 */
	private function get_field_value( $field_name, MessageInterface $interface, $headers, $parameters ): array {
		switch ( $field_name ) {
			case '@signature-params':
				$value = array( '', '' );
				break;
			case '@method':
				$value = array( '"@method"', strtoupper( $interface->getMethod() ) );
				break;
			case '@authority':
				$value = array( '"@authority"', $interface->getUri()->getAuthority() );
				break;
			case '@scheme':
				$value = array( '"@scheme"', strtolower( $interface->getUri()->getScheme() ) );
				break;
			case '@target-uri':
				$value = array( '"target-uri"', $interface->getUri()->__toString() );
				break;
			case '@request-target':
				$value = array( '"@request-target"', $interface->getRequestTarget() );
				break;
			case '@path':
				$value = array( '"@path"', $interface->getUri()->getPath() );
				break;
			case '@query':
				$value = array( '"@query"', $interface->getUri()->getQuery() );
				break;
			case '@query-param':
				$query_param_result = $this->get_query_param( $interface, $parameters );
				$value              = null !== $query_param_result ? $query_param_result : array( '', '' );
				break;
			case '@status':
				$value = array( '"@status"', '"@status": ' . $interface->getStatusCode() );
				break;
			default:
				$value = array( '"' . $field_name . '"', trim( $headers[ $field_name ] ?? '' ) );
				break;
		}

		return $value;
	}

	/**
	 * Parse a query string into an associative array.
	 *
	 * Should not use PHP's parse_str function here, as it has some issues with
	 * spaces and dots in parameter names. These are unlikely to occur, but the
	 * following function treats them as opaque strings rather than as variable
	 * names.
	 *
	 * @param string $query The query string to parse.
	 * @return array|null The parsed query parameters or null if query is not set.
	 */
	private function parse_query_string( string $query ) {
		$result = array();

		if ( ! isset( $query ) ) {
			return null;
		}

		$query_params = explode( '&', $query );
		foreach ( $query_params as $param ) {
			// The '=' character is not required and indicates a boolean true value if unset.
			$element                            = explode( '=', $param, 2 );
			$result[ urldecode( $element[0] ) ] = isset( $element[1] ) ? urldecode( $element[1] ) : '';
		}
		return $result;
	}

	/**
	 * Find one query parameter by name from the request.
	 *
	 * Find one query parameter by name (which must supplied as parameters in the
	 * (structured) covered field list).
	 *
	 * @param MessageInterface $which_request The request to extract query parameters from.
	 * @param array            $parameters    The parameters containing the name of the query parameter.
	 * @return array An array containing the field name and value.
	 */
	private function get_query_param( $which_request, array $parameters ): array {
		$query_string = $which_request->getUri()->getQuery();
		if ( $query_string ) {
			$query_params = $this->parse_query_string( $query_string );
			$field_name   = $parameters['name'];
			if ( $field_name ) {
				return array( '"_' . $field_name . '_"', $query_params[ $field_name ] ? '"' . $query_params[ $field_name ] . '"' : '' );
			}
		}
		throw new \Exception( 'Query string named parameter not set' );
	}

	/**
	 * Apply structured field formatting based on the field type.
	 *
	 * Processes different HTTP header field types according to their structured format
	 * as defined in the structured_field_types property.
	 *
	 * @param string $name        The name of the field.
	 * @param string $field_value The value of the field to process.
	 * @return string The processed field value.
	 */
	private function apply_structured_field( string $name, string $field_value ): string {
		$type = $this->structured_field_types[ trim( $name, '"' ) ];
		switch ( $type ) {
			case 'list':
				$field = OuterList::fromHttpValue( $field_value );
				break;
			case 'innerlist':
				$field = InnerList::fromHttpValue( $field_value );
				break;
			case 'parameters':
				$field = Parameters::fromHttpValue( $field_value );
				break;
			case 'dictionary':
				$field = Dictionary::fromHttpValue( $field_value );
				break;
			case 'item':
				$field = Item::fromHttpValue( $field_value );
				break;
			case 'url':
				return '"' . $field_value . '"';
			case 'date':
				return '@' . strtotime( $field_value );
			case 'etag':
				$result = '';
				$list   = explode( ',', $field_value );
				foreach ( $list as $item ) {
					if ( str_starts_with( trim( $item ), 'W/' ) ) {
						$result .= substr( trim( $item ), 2 ) . '; w, ';
					} else {
						$result .= trim( $item ) . ', ';
					}
				}
				return rtrim( $result, ', ' );
			case 'cookie':
				// @TODO
			default:
				break;
		}
		if ( ! $field ) {
			return '';
		}
		return $field->toHttpValue();
	}

	/**
	 * Apply a single key value from a dictionary field.
	 *
	 * Extracts a specific key's value from a dictionary-type structured field.
	 *
	 * @param string $name        The name of the field.
	 * @param string $key         The key to extract from the dictionary.
	 * @param string $field_value The field value containing the dictionary.
	 * @return string The extracted value or empty string if not found.
	 */
	private function apply_single_key_value( string $name, string $key, string $field_value ): string {
		$type = $this->structured_field_types[ trim( $name, '"' ) ];
		if ( empty( $type ) || 'dictionary' === $type ) {
			$dictionary = Dictionary::fromHttpValue( $field_value );
			if ( $dictionary->isNotEmpty() && isset( $dictionary[ $key ] ) ) {
				return $dictionary[ $key ]->toHttpValue();
			}
		}
		return '';
	}

	/**
	 * Create a signature for the provided data using the configured algorithm.
	 *
	 * @param string $data The data to sign.
	 * @return string The base64-encoded signature.
	 * @throws \RuntimeException When an unsupported algorithm is specified.
	 */
	private function create_signature( string $data ): string {
		switch ( $this->algorithm ) {
			case 'rsa-sha256':
				return $this->rsa_sign( $data );
			case 'ed25519':
				return $this->ed25519_sign( $data );
			case 'hmac-sha256':
				return base64_encode( hash_hmac( 'sha256', $data, $this->private_key, true ) );
			default:
				throw new \RuntimeException( "Unsupported algorithm: $this->algorithm" );
		}
	}

	/**
	 * Verify a signature against the provided data using the specified algorithm.
	 *
	 * @param string $data      The data that was signed.
	 * @param string $signature The signature to verify.
	 * @param string $alg       The algorithm used for signing.
	 * @return bool Whether the signature is valid.
	 */
	private function verify_signature( string $data, string $signature, string $alg ): bool {
		switch ( $alg ) {
			case 'rsa-sha256':
				return openssl_verify( $data, $signature, $this->public_key, OPENSSL_ALGO_SHA256 ) === 1;
			case 'ed25519':
				return openssl_verify( $data, $signature, $this->public_key, 'Ed25519' ) === 1;
			case 'hmac-sha256':
				return hash_equals(
					base64_encode( hash_hmac( 'sha256', $data, $this->private_key, true ) ),
					base64_encode( $signature )
				);
			default:
				return false;
		}
	}

	/**
	 * Sign data using RSA-SHA256 algorithm.
	 *
	 * @param string $data The data to sign.
	 * @return string The base64-encoded signature.
	 * @throws \RuntimeException When RSA signing fails.
	 */
	private function rsa_sign( string $data ): string {
		if ( ! openssl_sign( $data, $signature, $this->private_key, OPENSSL_ALGO_SHA256 ) ) {
			throw new \RuntimeException( 'RSA signing failed' );
		}
		return base64_encode( $signature );
	}

	/**
	 * Sign data using Ed25519 algorithm.
	 *
	 * @param string $data The data to sign.
	 * @return string The base64-encoded signature.
	 * @throws \RuntimeException When Ed25519 signing fails.
	 */
	private function ed25519_sign( string $data ): string {
		if ( ! openssl_sign( $data, $signature, $this->private_key, 'Ed25519' ) ) {
			throw new \RuntimeException( 'Ed25519 signing failed' );
		}
		return base64_encode( $signature );
	}

	/**
	 * Parse a structured dictionary from a header value.
	 *
	 * @param string $header_value The header value to parse.
	 * @return InnerList|Dictionary The parsed structured field.
	 */
	private function parse_structured_dict( string $header_value ) {
		if ( str_starts_with( trim( $header_value ), '(' ) ) {
			return InnerList::fromHttpValue( $header_value );
		} else {
			return Dictionary::fromHttpValue( $header_value );
		}
	}

	/*
	Recommended to calculate the digest of the body and add it to
		covered headers and sign, but not required. Convenience function
		to calculate the digest.

		ex:

		$digest = $signer->create_content_digest_header($body);
		$request = $request->withHeader('Content-Digest', $digest);
	*/

	/**
	 * From https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/Content-Digest
	 * The algorithm used to create a digest of the message content. Only two registered digest algorithms are
	 * considered secure: sha-512 and sha-256. The insecure (legacy) registered digest algorithms
	 * are: md5, sha (SHA-1), unixsum, unixcksum, adler (ADLER32) and crc32c.
	 *
	 * @param string $body     The body content to create a digest for.
	 * @param string $algorithm The algorithm to use for the digest. Default 'sha-256'.
	 * @return string The Content-Digest header value.
	 */
	public function create_content_digest_header( string $body, string $algorithm = 'sha-256' ): string {
		$supported_algorithms = array(
			'sha256' => 'sha-256',
			'sha512' => 'sha-512',
		);

		if ( isset( $supported_algorithms[ $algorithm ] ) ) {
			$algorithm_header_string = $supported_algorithms[ $algorithm ];
		} else {
			throw new \RuntimeException( "Unsupported algorithm: $algorithm" );
		}

		$digest = hash( $algorithm, $body, true );

		// Output as structured field.
		return $algorithm_header_string . '=:' . base64_encode( $digest ) . ':';
	}

	/**
	 * Parse an HTTP message into its components.
	 *
	 * Convenience function, probably want to use a robust PSR7 solution instead.
	 *
	 * @param string $raw The raw HTTP message.
	 * @return array An array containing the parsed HTTP message components.
	 */
	public static function parse_http_message( string $raw ): array {
		list($header_part, $body) = explode( "\r\n\r\n", $raw, 2 );
		$lines                    = explode( "\r\n", $header_part );
		$request_line             = array_shift( $lines );
		list($method, $path)      = explode( ' ', $request_line );

		$headers = array();
		foreach ( $lines as $line ) {
			[$name, $value]                         = explode( ':', $line, 2 );
			$headers[ strtolower( trim( $name ) ) ] = trim( $value );
		}

		return array(
			'method'  => $method,
			'path'    => $path,
			'headers' => $headers,
			'body'    => $body,
		);
	}

	/**
	 * Get the original request.
	 *
	 * Retrieves the original request object.
	 *
	 * @return RequestInterface The original request object.
	 */
	public function get_original_request() {
		return $this->original_request;
	}

	/**
	 * Set the original request.
	 *
	 * Sets the original request object.
	 *
	 * @param RequestInterface $original_request The original request object.
	 * @return Http_Signature The current instance for method chaining.
	 */
	public function set_original_request( $original_request ) {
		$this->original_request = $original_request;
		return $this;
	}

	/**
	 * Get the key ID.
	 *
	 * Retrieves the key ID.
	 *
	 * @return string The key ID.
	 */
	public function get_key_id(): string {
		return $this->key_id;
	}

	/**
	 * Set the key ID.
	 *
	 * Sets the key ID.
	 *
	 * @param string $key_id The key ID to set.
	 * @return Http_Signature The current instance for method chaining.
	 */
	public function set_key_id( string $key_id ): Http_Signature {
		$this->key_id = $key_id;
		return $this;
	}

	/**
	 * Get the private key.
	 *
	 * Retrieves the private key.
	 *
	 * @return string The private key.
	 */
	public function get_private_key(): string {
		return $this->private_key;
	}

	/**
	 * Set the private key.
	 *
	 * Sets the private key.
	 *
	 * @param string $private_key The private key to set.
	 * @return Http_Signature The current instance for method chaining.
	 */
	public function set_private_key( string $private_key ): Http_Signature {
		$this->private_key = $private_key;
		return $this;
	}

	/**
	 * Get the public key.
	 *
	 * Retrieves the public key.
	 *
	 * @return string The public key.
	 */
	public function get_public_key(): string {
		return $this->public_key;
	}

	/**
	 * Set the public key.
	 *
	 * Sets the public key.
	 *
	 * @param string $public_key The public key to set.
	 * @return Http_Signature The current instance for method chaining.
	 */
	public function set_public_key( string $public_key ): Http_Signature {
		$this->public_key = $public_key;
		return $this;
	}

	/**
	 * Get the algorithm.
	 *
	 * Retrieves the algorithm.
	 *
	 * @return string The algorithm.
	 */
	public function get_algorithm(): string {
		return $this->algorithm;
	}

	/**
	 * Set the algorithm.
	 *
	 * Sets the algorithm.
	 *
	 * @param string $algorithm The algorithm to set.
	 * @return Http_Signature The current instance for method chaining.
	 */
	public function set_algorithm( string $algorithm ): Http_Signature {
		$this->algorithm = $algorithm;
		return $this;
	}

	/**
	 * Get the signature ID.
	 *
	 * Retrieves the signature ID.
	 *
	 * @return string The signature ID.
	 */
	public function get_signature_id(): string {
		return $this->signature_id;
	}

	/**
	 * Set the signature ID.
	 *
	 * Sets the signature ID.
	 *
	 * @param string $signature_id The signature ID to set.
	 * @return Http_Signature The current instance for method chaining.
	 */
	public function set_signature_id( string $signature_id ): Http_Signature {
		$this->signature_id = $signature_id;
		return $this;
	}

	/**
	 * Get the creation timestamp.
	 *
	 * Retrieves the creation timestamp.
	 *
	 * @return string The creation timestamp.
	 */
	public function get_created(): string {
		return $this->created;
	}

	/**
	 * Set the creation timestamp.
	 *
	 * Sets the creation timestamp.
	 *
	 * @param string $created The creation timestamp to set.
	 * @return Http_Signature The current instance for method chaining.
	 */
	public function set_created( string $created ): Http_Signature {
		$this->created = $created;
		return $this;
	}

	/**
	 * Get the expiration timestamp.
	 *
	 * Retrieves the expiration timestamp.
	 *
	 * @return string The expiration timestamp.
	 */
	public function get_expires(): string {
		return $this->expires;
	}

	/**
	 * Set the expiration timestamp.
	 *
	 * Sets the expiration timestamp.
	 *
	 * @param string $expires The expiration timestamp to set.
	 * @return Http_Signature The current instance for method chaining.
	 */
	public function set_expires( string $expires ): Http_Signature {
		$this->expires = $expires;
		return $this;
	}

	/**
	 * Get the nonce value.
	 *
	 * Retrieves the nonce value.
	 *
	 * @return string The nonce value.
	 */
	public function get_nonce(): string {
		return $this->nonce;
	}

	/**
	 * Set the nonce value.
	 *
	 * Sets the nonce value.
	 *
	 * @param string $nonce The nonce value to set.
	 * @return Http_Signature The current instance for method chaining.
	 */
	public function set_nonce( string $nonce ): Http_Signature {
		$this->nonce = $nonce;
		return $this;
	}

	/**
	 * Get the tag value.
	 *
	 * Retrieves the tag value.
	 *
	 * @return string The tag value.
	 */
	public function get_tag(): string {
		return $this->tag;
	}

	/**
	 * Set the tag value.
	 *
	 * Sets the tag value.
	 *
	 * @param string $tag The tag value to set.
	 * @return Http_Signature The current instance for method chaining.
	 */
	public function set_tag( string $tag ): Http_Signature {
		$this->tag = $tag;
		return $this;
	}

	/**
	 * Calculate the signature base string without signing it.
	 *
	 * Can be used as a development function to peek into the internals of the exact things
	 * that were signed and/or test the internal results of data normalisation.
	 * This matches the sign() function except that it just returns the serialised string
	 * and does not sign it.
	 *
	 * @param array            $headers        The headers to include in the signature.
	 * @param string           $covered_fields The fields to be covered by the signature.
	 * @param MessageInterface $interface     The message interface.
	 * @return string The signature base string.
	 */
	public function calculate_signature_base( array $headers, string $covered_fields, $interface ) {
		$signature_components = array();
		$processed_components = array();
		$dict                 = $this->parse_structured_dict( $covered_fields );

		if ( $dict->isNotEmpty() ) {
			$covered_structured_fields = $dict->__toString();
			$indices                   = $dict->indices();
			foreach ( $indices as $index ) {
				$member = $dict->getByIndex( $index );
				if ( ! $member ) {
					throw new \Exception( 'Index ' . $index . ' not found' );
				}
				if ( in_array( $member, $processed_components, true ) ) {
					throw new \Exception( 'Duplicate member found' );
				}
				$processed_components[] = $member;
				$signature_components[] = $this->canonicalize_component( $member, $headers, $interface );
			}
		}

		$signature_input = $covered_structured_fields . ';keyid="' . $this->key_id . '";alg="' . $this->algorithm . '"';

		if ( $this->created ) {
			$signature_input .= ';created=' . $this->created;
		}
		if ( $this->expires ) {
			$signature_input .= ';expires=' . $this->expires;
		}
		if ( $this->nonce ) {
			$signature_input .= ';nonce="' . $this->nonce . '"';
		}
		if ( $this->tag ) {
			$signature_input .= ';tag="' . $this->tag . '"';
		}

		/**
		 * Always include @signature-params in the result.
		 */
		$signature_components[] = '"@signature-params": ' . $signature_input;

		return implode( "\n", $signature_components );
	}
}
