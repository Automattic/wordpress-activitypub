<?php
/**
 * Interface for HTTP Signature Standards.
 *
 * This interface defines the methods required for verifying HTTP signatures
 * according to various standards, such as Draft Cavage and HTTP Message Signature.
 *
 * @package Activitypub\Signature
 */

namespace Activitypub\Signature;

/**
 * Interface Signature_Standard.
 */
interface Signature_Standard {

	/**
	 * Generate Signature headers for an outgoing HTTP request.
	 *
	 * @param string      $key_id      The keyId for the signature.
	 * @param string      $private_key The private key to sign with.
	 * @param string      $http_method The HTTP method (e.g., 'post').
	 * @param string      $url         The request URL.
	 * @param string      $date        The date header value.
	 * @param string|null $digest      The digest header value (optional).
	 * @return array Array with 'Signature-Input' and 'Signature' headers.
	 */
	public function sign( $key_id, $private_key, $http_method, $url, $date, $digest = null );

	/**
	 * Verify the HTTP Signature against a request.
	 *
	 * @param array       $headers The HTTP headers.
	 * @param string|null $body    The request body, if applicable.
	 * @return bool|\WP_Error
	 */
	public function verify( array $headers, $body = null );

	/**
	 * Generate a digest for the request body.
	 *
	 * @param string $body The request body.
	 *
	 * @return string The digest.
	 */
	public function generate_digest( $body );
}
