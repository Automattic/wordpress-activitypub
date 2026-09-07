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

	/**
	 * Test that the Application resolves at the pre-migration host after a site move.
	 *
	 * @covers ::is_application_resource
	 */
	public function test_is_application_resource_matches_old_host() {
		\update_option( 'activitypub_old_host', 'old-domain.example' );
		$this->assertTrue( Application::is_application_resource( 'acct:application@old-domain.example' ) );

		// A www-prefixed old host is stored raw but must still match after normalization.
		\update_option( 'activitypub_old_host', 'www.old-domain.example' );
		$this->assertTrue( Application::is_application_resource( 'acct:application@old-domain.example' ) );

		\delete_option( 'activitypub_old_host' );
	}

	/**
	 * Test that host matching is case-insensitive on both sides.
	 *
	 * The requested host is whatever the caller typed and the stored hosts are whatever an admin
	 * saved, so folding only one side would stop the Application resolving on a mixed-case site.
	 *
	 * @covers ::is_application_resource
	 */
	public function test_is_application_resource_folds_host_case() {
		$this->assertTrue( Application::is_application_resource( 'acct:application@' . \strtoupper( home_host() ) ) );

		\update_option( 'activitypub_old_host', 'OLD-Domain.example' );
		$this->assertTrue( Application::is_application_resource( 'acct:application@old-domain.example' ) );
		$this->assertTrue( Application::is_application_resource( 'acct:application@OLD-domain.example.' ) );

		\delete_option( 'activitypub_old_host' );
	}

	/**
	 * Test that a host which folds away to nothing is rejected.
	 *
	 * `.` and `[]` fold to an empty string, and so does an unset `activitypub_old_host`, so
	 * without an explicit bail the two empties would compare equal and match any site.
	 *
	 * @covers ::is_application_resource
	 */
	public function test_is_application_resource_rejects_empty_folded_host() {
		\delete_option( 'activitypub_old_host' );

		$this->assertFalse( Application::is_application_resource( 'application@.' ) );
		$this->assertFalse( Application::is_application_resource( 'acct:application@[]' ) );
	}

	/**
	 * Test that an already resolved WebFinger profile is not overwritten.
	 *
	 * @covers ::add_webfinger_discovery
	 */
	public function test_add_webfinger_discovery_respects_resolved_profiles() {
		$jrd = array( 'subject' => 'acct:blog@' . home_host() );

		$this->assertSame( $jrd, Application::add_webfinger_discovery( $jrd, 'acct:application@' . home_host() ) );
	}

	/**
	 * Test that unresolved lookups get the Application profile.
	 *
	 * @covers ::add_webfinger_discovery
	 */
	public function test_add_webfinger_discovery_resolves_application() {
		$error = new \WP_Error( 'activitypub_user_not_found', 'Actor not found' );
		$data  = Application::add_webfinger_discovery( $error, 'acct:application@' . home_host() );

		$this->assertIsArray( $data );
		$this->assertSame( 'acct:' . Application::get_webfinger(), $data['subject'] );
	}
}
