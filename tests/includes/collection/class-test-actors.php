<?php
/**
 * Test file for Activitypub Actors Collection.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Collection;

use Activitypub\Collection\Actors;

/**
 * Class Test_Actors
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
	 * Tear down.
	 */
	public function tear_down() {
		parent::tear_down();

		\delete_option( 'activitypub_keypair_for_0' );
		\delete_option( 'activitypub_keypair_for_-1' );
		\delete_option( 'activitypub_keypair_for_admin' );
		\delete_option( 'activitypub_blog_user_public_key' );
		\delete_option( 'activitypub_blog_user_private_key' );
		\delete_option( 'activitypub_application_user_public_key' );
		\delete_option( 'activitypub_application_user_private_key' );
		\delete_option( 'activitypub_actor_mode' );
		\delete_user_meta( 1, 'magic_sig_public_key' );
		\delete_user_meta( 1, 'magic_sig_private_key' );
	}

	/**
	 * Test get_by_id.
	 *
	 * @covers ::get_by_id
	 */
	public function test_get_by_id() {
		// External user.
		$user_id = 'obenland@mastodon.social';

		$actor = Actors::get_by_id( $user_id );
		$this->assertWPError( $actor );
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
	 * Resource provider.
	 *
	 * @return array[]
	 */
	public function the_resource_provider() {
		$home_url       = \home_url();
		$home_host      = \wp_parse_url( $home_url, PHP_URL_HOST );
		$https_home_url = \str_replace( 'http://', 'https://', $home_url );

		return array(
			array( $home_url . '/?author=1', 'Activitypub\Model\User' ),
			array( $https_home_url . '/?author=1', 'Activitypub\Model\User' ),
			array( \rtrim( $https_home_url, '/' ) . '?author=1', 'Activitypub\Model\User' ),
			array( $home_url . '/?author=7', 'WP_Error' ),
			array( 'acct:admin@' . $home_host, 'Activitypub\Model\User' ),
			array( 'acct:blog@' . $home_host, 'Activitypub\Model\Blog' ),
			array( 'acct:*@' . $home_host, 'Activitypub\Model\Blog' ),
			array( 'acct:_@' . $home_host, 'Activitypub\Model\Blog' ),
			array( 'acct:aksd@' . $home_host, 'WP_Error' ),
			array( 'admin@' . $home_host, 'Activitypub\Model\User' ),
			array( 'acct:application@' . $home_host, 'Activitypub\Model\Application' ),
			array( $home_url . '/@admin', 'Activitypub\Model\User' ),
			array( $home_url . '/@blog', 'Activitypub\Model\Blog' ),
			array( $https_home_url . '/@blog', 'Activitypub\Model\Blog' ),
			array( $home_url . '/@blog/', 'Activitypub\Model\Blog' ),
			array( $home_url . '/blog/@blog', 'Activitypub\Model\Blog' ),
			array( $home_url . '/blog/@blog/', 'Activitypub\Model\Blog' ),
			array( $home_url . '/error/@blog', 'WP_Error' ),
			array( $home_url . '/error/@blog/', 'WP_Error' ),
			array( $home_url . '/', 'Activitypub\Model\Blog' ),
			array( \rtrim( $home_url, '/' ), 'Activitypub\Model\Blog' ),
			array( $https_home_url . '/', 'Activitypub\Model\Blog' ),
			array( \rtrim( $https_home_url, '/' ), 'Activitypub\Model\Blog' ),
			array( $home_url . '/@blog/s', 'WP_Error' ),
			array( $home_url . '/@blogs/', 'WP_Error' ),
		);
	}

	/**
	 * Test get_type_by_id()
	 *
	 * @covers ::get_type_by_id
	 */
	public function test_get_type_by_id() {
		$this->assertSame( 'application', Actors::get_type_by_id( Actors::APPLICATION_USER_ID ) );
		$this->assertSame( 'blog', Actors::get_type_by_id( Actors::BLOG_USER_ID ) );
		$this->assertSame( 'user', Actors::get_type_by_id( 1 ) );
		$this->assertSame( 'user', Actors::get_type_by_id( 2 ) );
	}

	/**
	 * Test if Actor mode will be respected properly
	 *
	 * @covers ::get_type_by_id
	 */
	public function test_disabled_blog_profile() {
		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_AND_BLOG_MODE );

		$resource = 'http://example.org/@blog';

		$this->assertEquals( 'Activitypub\Model\Blog', get_class( Actors::get_by_resource( $resource ) ) );

		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_MODE );

		$this->assertWPError( Actors::get_by_resource( $resource ) );
	}

	/**
	 * Test signature creation.
	 *
	 * @covers ::get_keypair
	 * @covers ::get_public_key
	 * @covers ::get_private_key
	 */
	public function test_signature_creation() {
		$key_pair    = Actors::get_keypair( 1 );
		$public_key  = Actors::get_public_key( 1 );
		$private_key = Actors::get_private_key( 1 );

		$this->assertNotEmpty( $key_pair );
		$this->assertEquals( $key_pair['public_key'], $public_key );
		$this->assertEquals( $key_pair['private_key'], $private_key );
	}

	/**
	 * Test signature legacy.
	 *
	 * @covers ::get_keypair
	 */
	public function test_signature_legacy() {
		$public_key  = 'public key 1';
		$private_key = 'private key 1';

		\update_user_meta( 1, 'magic_sig_public_key', $public_key );
		\update_user_meta( 1, 'magic_sig_private_key', $private_key );

		$key_pair = Actors::get_keypair( 1 );

		$this->assertNotEmpty( $key_pair );
		$this->assertEquals( $key_pair['public_key'], $public_key );
		$this->assertEquals( $key_pair['private_key'], $private_key );

		// Check application user.
		\delete_option( 'activitypub_keypair_for_-1' );

		$public_key  = 'public key ' . Actors::APPLICATION_USER_ID;
		$private_key = 'private key ' . Actors::APPLICATION_USER_ID;

		\add_option( 'activitypub_application_user_public_key', $public_key );
		\add_option( 'activitypub_application_user_private_key', $private_key );

		$key_pair = Actors::get_keypair( Actors::APPLICATION_USER_ID );

		$this->assertNotEmpty( $key_pair );
		$this->assertEquals( $key_pair['public_key'], $public_key );
		$this->assertEquals( $key_pair['private_key'], $private_key );

		// Check blog user.
		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_AND_BLOG_MODE );
		\delete_option( 'activitypub_actor_mode' );

		$public_key  = 'public key ' . Actors::BLOG_USER_ID;
		$private_key = 'private key ' . Actors::BLOG_USER_ID;

		\delete_option( 'activitypub_keypair_for_0' );

		\add_option( 'activitypub_blog_user_public_key', $public_key );
		\add_option( 'activitypub_blog_user_private_key', $private_key );

		$key_pair = Actors::get_keypair( Actors::BLOG_USER_ID );

		$this->assertNotEmpty( $key_pair );
		$this->assertEquals( $key_pair['public_key'], $public_key );
		$this->assertEquals( $key_pair['private_key'], $private_key );
	}

	/**
	 * Test signature consistency.
	 *
	 * @covers ::get_keypair
	 */
	public function test_signature_consistency() {
		$public_key  = 'public key 1';
		$private_key = 'private key 1';

		\update_user_meta( 1, 'magic_sig_public_key', $public_key );
		\update_user_meta( 1, 'magic_sig_private_key', $private_key );

		$key_pair = Actors::get_keypair( 1 );

		$this->assertNotEmpty( $key_pair );
		$this->assertEquals( $key_pair['public_key'], $public_key );
		$this->assertEquals( $key_pair['private_key'], $private_key );

		\update_user_meta( 1, 'magic_sig_public_key', $public_key . '-update' );
		\update_user_meta( 1, 'magic_sig_private_key', $private_key . '-update' );

		$key_pair = Actors::get_keypair( 1 );

		$this->assertNotEmpty( $key_pair );
		$this->assertEquals( $key_pair['public_key'], $public_key );
		$this->assertEquals( $key_pair['private_key'], $private_key );
	}

	/**
	 * Test signature consistency 2.
	 *
	 * @covers ::get_keypair
	 */
	public function test_signature_consistency2() {
		$key_pair    = Actors::get_keypair( 1 );
		$public_key  = Actors::get_public_key( 1 );
		$private_key = Actors::get_private_key( 1 );

		$this->assertNotEmpty( $key_pair );
		$this->assertEquals( $key_pair['public_key'], $public_key );
		$this->assertEquals( $key_pair['private_key'], $private_key );

		\update_user_meta( 1, 'magic_sig_public_key', 'test' );
		\update_user_meta( 1, 'magic_sig_private_key', 'test' );

		$key_pair = Actors::get_keypair( 1 );

		$this->assertNotEmpty( $key_pair );
		$this->assertEquals( $key_pair['public_key'], $public_key );
		$this->assertEquals( $key_pair['private_key'], $private_key );
	}
}
