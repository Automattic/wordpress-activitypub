<?php
/**
 * Test file for Activitypub Actors Collection.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Collection;

use Activitypub\Collection\Actors;

/**
 * Test class for Activitypub Actors Collection.
 *
 * @coversDefaultClass \Activitypub\Collection\Actors
 */
class Test_Actors extends \WP_UnitTestCase {

	/**
	 * Set up the test.
	 */
	public function set_up() {
		parent::set_up();

		update_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_AND_BLOG_MODE );
		add_option( 'activitypub_blog_identifier', 'blog' );
		add_user_meta( 1, 'activitypub_user_identifier', 'admin' );
	}

	/**
	 * Test get_by_various.
	 *
	 * @dataProvider the_resource_provider
	 * @covers ::get_by_various
	 *
	 * @param string $item     The resource.
	 * @param string $expected The expected class.
	 */
	public function test_get_by_various( $item, $expected ) {
		$path = wp_parse_url( $item, PHP_URL_PATH ) ?? '';

		if ( str_starts_with( $path, '/blog/' ) ) {
			add_filter(
				'home_url',
				// phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable, Generic.CodeAnalysis.UnusedFunctionParameter.Found
				function ( $url ) {
					return 'http://example.org/blog/';
				}
			);
		}

		$actors = Actors::get_by_resource( $item );
		$this->assertInstanceOf( $expected, $actors );
	}

	/**
	 * Test deprecated get_by_various.
	 *
	 * @dataProvider the_resource_provider
	 * @covers ::get_by_resource
	 * @expectedDeprecated Activitypub\Collection\Users::get_by_resource
	 *
	 * @param string $item     The resource.
	 * @param string $expected The expected class.
	 */
	public function test_deprecated_get_by_various( $item, $expected ) {
		$path = wp_parse_url( $item, PHP_URL_PATH ) ?? '';

		if ( str_starts_with( $path, '/blog/' ) ) {
			add_filter(
				'home_url',
				function () {
					return 'http://example.org/blog/';
				}
			);
		}

		$users = \Activitypub\Collection\Users::get_by_resource( $item );
		$this->assertInstanceOf( $expected, $users );
	}

	/**
	 * Resource provider.
	 *
	 * @return array[]
	 */
	public function the_resource_provider() {
		return array(
			array( 'http://example.org/?author=1', 'Activitypub\Model\User' ),
			array( 'https://example.org/?author=1', 'Activitypub\Model\User' ),
			array( 'https://example.org?author=1', 'Activitypub\Model\User' ),
			array( 'http://example.org/?author=7', 'WP_Error' ),
			array( 'acct:admin@example.org', 'Activitypub\Model\User' ),
			array( 'acct:blog@example.org', 'Activitypub\Model\Blog' ),
			array( 'acct:*@example.org', 'Activitypub\Model\Blog' ),
			array( 'acct:_@example.org', 'Activitypub\Model\Blog' ),
			array( 'acct:aksd@example.org', 'WP_Error' ),
			array( 'admin@example.org', 'Activitypub\Model\User' ),
			array( 'acct:application@example.org', 'Activitypub\Model\Application' ),
			array( 'http://example.org/@admin', 'Activitypub\Model\User' ),
			array( 'http://example.org/@blog', 'Activitypub\Model\Blog' ),
			array( 'https://example.org/@blog', 'Activitypub\Model\Blog' ),
			array( 'http://example.org/@blog/', 'Activitypub\Model\Blog' ),
			array( 'http://example.org/blog/@blog', 'Activitypub\Model\Blog' ),
			array( 'http://example.org/blog/@blog/', 'Activitypub\Model\Blog' ),
			array( 'http://example.org/error/@blog', 'WP_Error' ),
			array( 'http://example.org/error/@blog/', 'WP_Error' ),
			array( 'http://example.org/', 'Activitypub\Model\Blog' ),
			array( 'http://example.org', 'Activitypub\Model\Blog' ),
			array( 'https://example.org/', 'Activitypub\Model\Blog' ),
			array( 'https://example.org', 'Activitypub\Model\Blog' ),
			array( 'http://example.org/@blog/s', 'WP_Error' ),
			array( 'http://example.org/@blogs/', 'WP_Error' ),
		);
	}
}
