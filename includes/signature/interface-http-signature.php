<?php
/**
 * Interface for HTTP Signature.
 *
 * This interface defines the methods required for verifying HTTP signatures
 * according to various standards, such as Draft Cavage and HTTP Message Signature.
 *
 * @package Activitypub\Signature
 */

namespace Activitypub\Signature;

/**
 * Interface Http_Signature.
 */
interface Http_Signature {

	/**
	 * Generate Signature headers for an outgoing HTTP request.
	 *
	 * @param array  $args The request arguments.
	 * @param string $url  The request URL.
	 *
	 * @return array Request arguments with signature headers.
	 */
	public function sign( $args, $url );

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
