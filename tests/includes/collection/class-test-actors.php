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
	 * The public key in PKCS#1 format.
	 *
	 * @var string
	 */
	private $pkcs1_key = '-----BEGIN RSA PUBLIC KEY-----
MIIBCgKCAQEAtAVnFFbWG+6NBFKhMZdt59Gx2/vKxWxbxOAYyi/ypZ/9aDY6C/UB
Rei8SqnhKcKXQaiSwme/wpqgCdkrf53H85OioBitCEvKNA6uDxkCtcdgtQ3X55QD
XmatWd32ln6elRmKG45U9R386j82OHzff8Ju65QxGL1LlyCKQ/XFx/pgvblF3cGj
shk0dhNcyGAztODN5HFp9Qzf9d7+gi+xdKeGNhXBAulXoaDzx8FvLEXNfPJb3jUM
1Ug0STFsiICcf7VxmQow6N6d0+HtWxrdtjUBdXrPxz998Ns/cu9jjg06d+XV3TcS
U+AOldmGLJuB/AWV/+F9c9DlczqmnXqd1QIDAQAB
-----END RSA PUBLIC KEY-----
';

	/**
	 * The public key in X.509 format.
	 *
	 * @var string
	 */
	private $x509_key = '-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA19218d19uYisOYUZ3oqN
wSRyixAX8V1JHJSngbjAjZr1vYcwMte8CPqqELbNwtQWAMy42UnQpyIqgvLpOaVr
vQWjUuR+7i8wETrVNJq8JQNNCiQ+8+I4TPcGyZDBclHkLtKiCoBtjUH0itVh4Sg0
KQLSb8ZHu9lGh8TJMcLXVUdVkvkUjqHl6I5BoftMVDSKQF+V4X8Qyk7qP7wU8mpE
+O6RuhUpZ3QXM+dBIalyey8NKLf2yN6CmKyW1220wdNupOYHbc8DSYEq6NDQZfZb
yP2KLHN3rdNwsnlAP02Ws1qroBivHSV71KLebQUDU2KpDLKQF2Ix6X47IBFOXnb9
FwIDAQAB
-----END PUBLIC KEY-----
';

	/**
	 * The public key in EC format.
	 *
	 * @var string
	 */
	private $ec_key = '-----BEGIN PUBLIC KEY-----
MFkwEwYHKoZIzj0CAQYIKoZIzj0DAQcDQgAE/jw3kftaHGIB2OTKTYFUTTqyzDs0
eWKe+6k1Kh6HSrinXriBLbIhMPY9pQsvqkeT6wW975NDn7+8awb8kHRmIg==
-----END PUBLIC KEY-----
';

	/**
	 * The public key in PKCS#8 format.
	 *
	 * @var string
	 */
	private $pkcs8_key = '-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAy8dfWmTltr09m49uyESj
x6UnQ9G/iVq+3dJbUdCdVEPR256UD6DLHE8uM4DgXhtoLVrBcvTAl9h0nRGX4uVN
5jE+pTh47B9IUim0bVw2sOBNwPCTUuKbMVx3Cso/6UxJsot41q7+FHIxcAurDxfR
xfJkf+1ecYSb5czoeOG+NUcTEQv1LQntAOJ1ngrmjKyL4UlKZgcs2TfueqlK1v2t
Gw4ylFOQYRx1Nj5YttQAuXc+VpGfztyRK90R74WkE/N6miOoDHcvc+7AeW4zyWsh
ZfLXCbngI45TVhUr3ljxWs1Ykc8d4Xt3JrtcUzltbc6nWS0vstcUmxTLTRURn3SX
4wIDAQAB
-----END PUBLIC KEY-----
';

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
	 * Test the create() method for remote actors.
	 *
	 * @covers ::create
	 */
	public function test_create_actor() {
		$actor   = array(
			'id'                => 'https://remote.example.com/actor/jane-create',
			'type'              => 'Person',
			'url'               => 'https://remote.example.com/actor/jane-create',
			'inbox'             => 'https://remote.example.com/actor/jane-create/inbox',
			'name'              => 'Jane',
			'preferredUsername' => 'jane',
			'endpoints'         => array(
				'sharedInbox' => 'https://remote.example.com/inbox',
			),
		);
		$post_id = Actors::create( $actor );
		$this->assertIsInt( $post_id );
		$this->assertGreaterThan( 0, $post_id );
		$post = \get_post( $post_id );
		$this->assertInstanceOf( '\WP_Post', $post );
		$this->assertEquals( 'https://remote.example.com/actor/jane-create', $post->guid );
		// Clean up.
		\wp_delete_post( $post_id, true );
	}

	/**
	 * Test the update() method for remote actors.
	 *
	 * @covers ::update
	 */
	public function test_update_actor() {
		$actor   = array(
			'id'                => 'https://remote.example.com/actor/jane-update',
			'type'              => 'Person',
			'url'               => 'https://remote.example.com/actor/jane-update',
			'inbox'             => 'https://remote.example.com/actor/jane-update/inbox',
			'name'              => 'Jane',
			'preferredUsername' => 'jane',
			'endpoints'         => array(
				'sharedInbox' => 'https://remote.example.com/inbox',
			),
		);
		$post_id = Actors::create( $actor );
		$this->assertIsInt( $post_id );
		$updated_actor         = $actor;
		$updated_actor['name'] = 'Jane Doe';
		$update_result         = Actors::update( $post_id, $updated_actor );
		$this->assertEquals( $post_id, $update_result );
		$updated_post = \get_post( $post_id );
		$this->assertInstanceOf( '\WP_Post', $updated_post );
		$actor_obj = Actors::get_actor( $updated_post );
		$this->assertEquals( 'Jane Doe', $actor_obj->get_name() );
		// Clean up.
		\wp_delete_post( $post_id, true );
	}

	/**
	 * Test the delete (wp_delete_post) operation for remote actors.
	 *
	 * @covers ::delete
	 */
	public function test_delete_actor() {
		$actor   = array(
			'id'                => 'https://remote.example.com/actor/jane-delete',
			'type'              => 'Person',
			'url'               => 'https://remote.example.com/actor/jane-delete',
			'inbox'             => 'https://remote.example.com/actor/jane-delete/inbox',
			'name'              => 'Jane',
			'preferredUsername' => 'jane',
			'endpoints'         => array(
				'sharedInbox' => 'https://remote.example.com/inbox',
			),
		);
		$post_id = Actors::create( $actor );
		$this->assertIsInt( $post_id );
		$delete_result = \wp_delete_post( $post_id, true );
		$this->assertInstanceOf( '\WP_Post', $delete_result );
		$deleted_post = \get_post( $post_id );
		$this->assertNull( $deleted_post );
	}

	/**
	 * Test fetch_remote_by_uri.
	 *
	 * @covers ::fetch_remote_by_uri
	 */
	public function test_fetch_remote_by_uri() {
		// Create a remote actor.
		$actor = array(
			'id'                => 'https://remote.example.com/actor/bob',
			'type'              => 'Person',
			'url'               => 'https://remote.example.com/actor/bob',
			'inbox'             => 'https://remote.example.com/actor/bob/inbox',
			'name'              => 'Bob',
			'preferredUsername' => 'bob',
			'endpoints'         => array(
				'sharedInbox' => 'https://remote.example.com/inbox',
			),
		);

		$id = Actors::create( $actor );
		$this->assertNotWPError( $id );

		// Should find the actor locally.
		$post = Actors::fetch_remote_by_uri( 'https://remote.example.com/actor/bob' );

		$this->assertInstanceOf( 'WP_Post', $post );
		$this->assertEquals( 'https://remote.example.com/actor/bob', $post->guid );

		// Delete local post, mock remote fetch.
		\wp_delete_post( $id );

		add_filter(
			'activitypub_pre_http_get_remote_object',
			function ( $pre, $url_or_object ) use ( $actor ) {
				if ( $url_or_object === $actor['id'] ) {
					return $actor;
				}
				return $pre;
			},
			10,
			2
		);

		$post = Actors::fetch_remote_by_uri( 'https://remote.example.com/actor/bob' );

		$this->assertInstanceOf( 'WP_Post', $post );
		$this->assertEquals( 'https://remote.example.com/actor/bob', $post->guid );

		remove_all_filters( 'activitypub_pre_http_get_remote_object' );
		\wp_delete_post( $post->ID );

		// Should return WP_Error for invalid URI.
		$not_found = Actors::fetch_remote_by_uri( '' );

		$this->assertWPError( $not_found );
	}

	/**
	 * Test get_remote_by_uri.
	 *
	 * @covers ::get_remote_by_uri
	 */
	public function test_get_remote_by_uri() {
		// Create a remote actor.
		$actor = array(
			'id'                => 'https://remote.example.com/actor/alice',
			'type'              => 'Person',
			'url'               => 'https://remote.example.com/actor/alice',
			'inbox'             => 'https://remote.example.com/actor/alice/inbox',
			'name'              => 'Alice',
			'preferredUsername' => 'alice',
			'endpoints'         => array(
				'sharedInbox' => 'https://remote.example.com/inbox',
			),
		);

		$id = Actors::create( $actor );
		$this->assertNotWPError( $id );

		// Should find the actor by guid.
		$post = Actors::get_remote_by_uri( 'https://remote.example.com/actor/alice' );
		$this->assertInstanceOf( 'WP_Post', $post );
		$this->assertEquals( 'https://remote.example.com/actor/alice', $post->guid );

		// Should return WP_Error for non-existent URI.
		$not_found = Actors::get_remote_by_uri( 'https://remote.example.com/actor/doesnotexist' );
		$this->assertWPError( $not_found );

		// Should return WP_Error for empty URI.
		$empty = Actors::get_remote_by_uri( '' );
		$this->assertWPError( $empty );

		// Clean up.
		\wp_delete_post( $id );
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

	/**
	 * Tests clear_errors.
	 *
	 * @covers ::clear_errors
	 */
	public function test_clear_errors() {
		$actor = array(
			'id'                => 'https://example.com/author/jon',
			'type'              => 'Person',
			'url'               => 'https://example.com/author/jon',
			'inbox'             => 'https://example.com/author/jon/inbox',
			'name'              => 'jon',
			'preferredUsername' => 'jon',
			'endpoints'         => array(
				'sharedInbox' => 'https://example.com/inbox',
			),
		);

		$id = Actors::upsert( $actor );
		$this->assertNotWPError( $id );

		// Add some errors.
		Actors::add_error( $id, 'Test error 1' );
		Actors::add_error( $id, 'Test error 2' );

		// Verify errors were added.
		$errors = \get_post_meta( $id, '_activitypub_errors', false );
		$this->assertCount( 2, $errors );

		// Clear errors.
		$cleared = Actors::clear_errors( $id );
		$this->assertTrue( $cleared );

		// Verify errors were cleared.
		$errors = \get_post_meta( $id, '_activitypub_errors', false );
		$this->assertEmpty( $errors );

		\wp_delete_post( $id );
	}

	/**
	 * Tests clear_errors with no errors.
	 *
	 * @covers ::clear_errors
	 */
	public function test_clear_errors_no_errors() {
		$actor = array(
			'type'              => 'Person',
			'id'                => 'https://example.com/author/jon',
			'url'               => 'https://example.com/author/jon',
			'inbox'             => 'https://example.com/author/jon/inbox',
			'name'              => 'jon',
			'preferredUsername' => 'jon',
		);

		$id = Actors::upsert( $actor );
		$this->assertNotWPError( $id );

		// Clear errors when none exist.
		$cleared = Actors::clear_errors( $id );
		$this->assertFalse( $cleared );

		// Verify no errors exist.
		$errors = \get_post_meta( $id, '_activitypub_errors', false );
		$this->assertEmpty( $errors );
	}

	/**
	 * Tests clear_errors with invalid follower ID.
	 *
	 * @covers ::clear_errors
	 */
	public function test_clear_errors_invalid_id() {
		// Try to clear errors for non-existent follower.
		$cleared = Actors::clear_errors( 99999 );
		$this->assertFalse( $cleared );
	}

	/**
	 * Test handling of different public key formats.
	 *
	 * @covers ::get_remote_key
	 */
	public function test_key_format_handling() {
		$expected = '-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAtAVnFFbWG+6NBFKhMZdt
59Gx2/vKxWxbxOAYyi/ypZ/9aDY6C/UBRei8SqnhKcKXQaiSwme/wpqgCdkrf53H
85OioBitCEvKNA6uDxkCtcdgtQ3X55QDXmatWd32ln6elRmKG45U9R386j82OHzf
f8Ju65QxGL1LlyCKQ/XFx/pgvblF3cGjshk0dhNcyGAztODN5HFp9Qzf9d7+gi+x
dKeGNhXBAulXoaDzx8FvLEXNfPJb3jUM1Ug0STFsiICcf7VxmQow6N6d0+HtWxrd
tjUBdXrPxz998Ns/cu9jjg06d+XV3TcSU+AOldmGLJuB/AWV/+F9c9DlczqmnXqd
1QIDAQAB
-----END PUBLIC KEY-----
';

		\add_filter( 'pre_get_remote_metadata_by_actor', array( $this, 'pre_get_remote_metadata_by_actor' ), 10, 2 );

		// X.509 key should remain unchanged.
		$result       = Actors::get_remote_key( 'https://example.com/author/x509' );
		$key_resource = \openssl_pkey_get_details( $result );
		$this->assertNotFalse( $key_resource );
		$this->assertSame( $this->x509_key, $key_resource['key'] );

		// PKCS#1 key should be converted to X.509 format.
		$result       = Actors::get_remote_key( 'https://example.com/author/pkcs1' );
		$key_resource = \openssl_pkey_get_details( $result );
		$this->assertNotFalse( $key_resource );
		$this->assertSame( $expected, $key_resource['key'] );

		// EC key should be handled correctly.
		$result       = Actors::get_remote_key( 'https://example.com/author/ec' );
		$key_resource = \openssl_pkey_get_details( $result );
		$this->assertNotFalse( $key_resource );

		// PKCS#8 key should be handled correctly.
		$result       = Actors::get_remote_key( 'https://example.com/author/pkcs8' );
		$key_resource = \openssl_pkey_get_details( $result );
		$this->assertNotFalse( $key_resource );

		// Test with invalid key.
		$result = Actors::get_remote_key( 'https://example.com/author/invalid' );
		$this->assertWPError( $result );

		\remove_filter( 'pre_get_remote_metadata_by_actor', array( $this, 'pre_get_remote_metadata_by_actor' ) );
	}

	/**
	 * Pre get remote metadata by actor.
	 *
	 * @param mixed  $value The value.
	 * @param string $url   The URL.
	 * @return array|\WP_Error
	 */
	public function pre_get_remote_metadata_by_actor( $value, $url ) {
		if ( 'https://example.com/author/x509' === $url ) {
			return array(
				'name'      => 'Test Actor',
				'url'       => 'https://example.com/author/x509',
				'publicKey' => array(
					'id'           => 'https://example.com/author#main-key',
					'owner'        => 'https://example.com/author',
					'publicKeyPem' => $this->x509_key,
				),
			);
		}

		if ( 'https://example.com/author/pkcs1' === $url ) {
			return array(
				'name'      => 'Test Actor',
				'url'       => 'https://example.com/author/pkcs1',
				'publicKey' => array(
					'id'           => 'https://example.com/author#main-key',
					'owner'        => 'https://example.com/author',
					'publicKeyPem' => $this->pkcs1_key,
				),
			);
		}

		if ( 'https://example.com/author/ec' === $url ) {
			return array(
				'name'      => 'Test Actor',
				'url'       => 'https://example.com/author/ec',
				'publicKey' => array(
					'id'           => 'https://example.com/author#main-key',
					'owner'        => 'https://example.com/author',
					'publicKeyPem' => $this->ec_key,
				),
			);
		}

		if ( 'https://example.com/author/pkcs8' === $url ) {
			return array(
				'name'      => 'Test Actor',
				'url'       => 'https://example.com/author/pkcs8',
				'publicKey' => array(
					'id'           => 'https://example.com/author#main-key',
					'owner'        => 'https://example.com/author',
					'publicKeyPem' => $this->pkcs8_key,
				),
			);
		}

		if ( 'https://example.com/author/invalid' === $url ) {
			return array(
				'name'      => 'Test Actor',
				'url'       => 'https://example.com/author/invalid',
				'publicKey' => array(
					'id'           => 'https://example.com/author#main-key',
					'owner'        => 'https://example.com/author',
					'publicKeyPem' => 'INVALID KEY DATA',
				),
			);
		}

		return new \WP_Error( 'invalid_url', $url );
	}
}
