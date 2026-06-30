<?php
/**
 * Application utility class test file.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

use Activitypub\Application;

use function Activitypub\home_host;

/**
 * Tests for the Application utility class.
 *
 * @coversDefaultClass \Activitypub\Application
 */
class Test_Application extends \WP_UnitTestCase {

	/**
	 * Data provider for resources that refer to the Application actor.
	 *
	 * @return array[] Test data.
	 */
	public function data_application_resources() {
		return array(
			'acct URI'      => array( 'acct:application@' . home_host() ),
			'bare handle'   => array( 'application@' . home_host() ),
			'pretty @-path' => array( \home_url( '/@application' ) ),
			'REST actor ID' => array( Application::get_id() ),
		);
	}

	/**
	 * Test that Application resources are recognized.
	 *
	 * @covers ::is_application_resource
	 * @dataProvider data_application_resources
	 *
	 * @param string $uri The resource URI.
	 */
	public function test_is_application_resource_matches( $uri ) {
		$this->assertTrue( Application::is_application_resource( $uri ), $uri );
	}

	/**
	 * Data provider for resources that do not refer to the Application actor.
	 *
	 * @return array[] Test data.
	 */
	public function data_non_application_resources() {
		return array(
			'other user acct' => array( 'acct:alice@' . home_host() ),
			'wrong host'      => array( 'acct:application@example.net' ),
			'unrelated URL'   => array( 'https://example.net/@application' ),
			'random path'     => array( \home_url( '/@alice' ) ),
		);
	}

	/**
	 * Test that non-Application resources are rejected.
	 *
	 * @covers ::is_application_resource
	 * @dataProvider data_non_application_resources
	 *
	 * @param string $uri The resource URI.
	 */
	public function test_is_application_resource_rejects( $uri ) {
		$this->assertFalse( Application::is_application_resource( $uri ), $uri );
	}
}
