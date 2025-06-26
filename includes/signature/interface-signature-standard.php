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
	 * Verify the HTTP Signature against a request.
	 *
	 * @param array       $headers The HTTP headers.
	 * @param string|null $body    The request body, if applicable.
	 * @return bool|\WP_Error
	 */
	public function verify( array $headers, $body = null );
}
