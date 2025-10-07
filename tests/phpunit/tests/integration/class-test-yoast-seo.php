<?php
/**
 * Test Yoast SEO integration.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Integration;

use Activitypub\Integration\Yoast_Seo;

/**
 * Test the Yoast integration.
 *
 * @group integration
 * @coversDefaultClass \Activitypub\Integration\Yoast_Seo
 */
class Test_Yoast_Seo extends \WP_UnitTestCase {

	/**
	 * Test that Yoast SEO integration adds site health tests when attachment is supported.
	 */
	public function test_yoast_seo_site_health_test_registration_with_attachment_support() {
		// Simulate Yoast being active.
		if ( ! defined( 'WPSEO_VERSION' ) ) {
			define( 'WPSEO_VERSION', '20.0' );
		}

		// Add attachment to supported post types.
		\update_option( 'activitypub_support_post_types', array( 'post', 'attachment' ) );

		// Initialize the Yoast integration.
		Yoast_Seo::init();

		// Get site health tests.
		$tests = \apply_filters( 'site_status_tests', array() );

		// Check if our test is registered.
		$this->assertArrayHasKey( 'direct', $tests );
		$this->assertArrayHasKey( 'activitypub_yoast_seo_media_pages', $tests['direct'] );
		$this->assertEquals( 'Yoast SEO Media Pages Test', $tests['direct']['activitypub_yoast_seo_media_pages']['label'] );
	}

	/**
	 * Test that Yoast SEO integration does not add site health tests when attachment is not supported.
	 */
	public function test_yoast_seo_site_health_test_not_registered_without_attachment_support() {
		// Simulate Yoast being active.
		if ( ! defined( 'WPSEO_VERSION' ) ) {
			define( 'WPSEO_VERSION', '20.0' );
		}

		// Remove attachment from supported post types.
		\update_option( 'activitypub_support_post_types', array( 'post' ) );

		// Initialize the Yoast integration.
		Yoast_Seo::init();

		// Get site health tests.
		$tests = \apply_filters( 'site_status_tests', array( 'direct' => array() ) );

		// Check if our test is NOT registered.
		$this->assertArrayNotHasKey( 'activitypub_yoast_seo_media_pages', $tests['direct'] );
	}

	/**
	 * Test media pages disabled check.
	 */
	public function test_is_media_pages_disabled() {
		// Test with media pages enabled (default WordPress behavior).
		\update_option( 'wpseo_titles', array( 'disable-attachment' => false ) );
		$this->assertFalse( Yoast_Seo::is_media_pages_disabled() );

		// Test with media pages disabled (recommended setting).
		\update_option( 'wpseo_titles', array( 'disable-attachment' => true ) );
		$this->assertTrue( Yoast_Seo::is_media_pages_disabled() );

		// Test with no Yoast options.
		\delete_option( 'wpseo_titles' );
		$this->assertFalse( Yoast_Seo::is_media_pages_disabled() );
	}

	/**
	 * Test the site health test execution.
	 */
	public function test_yoast_media_pages_site_health_test() {
		// Test when media pages are properly enabled (good status for ActivityPub).
		\update_option( 'wpseo_titles', array( 'disable-attachment' => false ) );
		$result = Yoast_Seo::test_yoast_seo_media_pages();

		$this->assertEquals( 'good', $result['status'] );
		$this->assertEquals( 'Yoast SEO media pages are enabled', $result['label'] );
		$this->assertEquals( 'green', $result['badge']['color'] );

		// Test when media pages are disabled (recommended status to enable them).
		\update_option( 'wpseo_titles', array( 'disable-attachment' => true ) );
		$result = Yoast_Seo::test_yoast_seo_media_pages();

		$this->assertEquals( 'recommended', $result['status'] );
		$this->assertEquals( 'Yoast SEO media pages should be enabled', $result['label'] );
		$this->assertEquals( 'orange', $result['badge']['color'] );
		$this->assertStringContainsString( 'Yoast SEO > Settings', $result['actions'] );
	}
}
