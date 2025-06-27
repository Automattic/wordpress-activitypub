<?php
/**
 * Test file for Server.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Rest;

use Activitypub\Rest\Server;

/**
 * Test class for Server.
 *
 * @group rest
 * @coversDefaultClass \Activitypub\Rest\Server
 */
class Test_Server extends \WP_Test_REST_TestCase {
	/**
	 * Test verify_signature method.
	 *
	 * @covers ::verify_signature
	 */
	public function test_verify_signature() {
		// HEAD requests are always bypassed.
		$request = new \WP_REST_Request( 'HEAD', '/' . ACTIVITYPUB_REST_NAMESPACE . '/inbox' );
		$this->assertTrue( Server::verify_signature( $request ) );

		// POST requests require a signature.
		$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/inbox' );
		$this->assertErrorResponse( 'activitypub_signature_verification', Server::verify_signature( $request ) );

		// GET requests with secure mode enabled require a signature.
		\add_filter( 'activitypub_use_authorized_fetch', '__return_true' );

		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/inbox' );
		$this->assertErrorResponse( 'activitypub_signature_verification', Server::verify_signature( $request ) );

		// GET requests with secure mode disabled are bypassed.
		\remove_filter( 'activitypub_use_authorized_fetch', '__return_true' );
		$this->assertTrue( Server::verify_signature( $request ) );
	}
}
