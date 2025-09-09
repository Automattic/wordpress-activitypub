<?php
/**
 * Test file for Activitypub Remote Actors Collection.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Collection;

use Activitypub\Collection\Remote_Actors;

/**
 * Class Test_Remote_Actors
 *
 * @coversDefaultClass \Activitypub\Collection\Remote_Actors
 */
class Test_Remote_Actors extends \WP_UnitTestCase {

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
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAtAVnFFbWG+6NBFKhMZdt
59Gx2/vKxWxbxOAYyi/ypZ/9aDY6C/UBRei8SqnhKcKXQaiSwme/wpqgCdkrf53H
85OioBitCEvKNA6uDxkCtcdgtQ3X55QDXmatWd32ln6elRmKG45U9R386j82OHzf
f8Ju65QxGL1LlyCKQ/XFx/pgvblF3cGjshk0dhNcyGAztODN5HFp9Qzf9d7+gi+x
dKeGNhXBAulXoaDzx8FvLEXNfPJb3jUM1Ug0STFsiICcf7VxmQow6N6d0+HtWxrd
tjUBdXrPxz998Ns/cu9jjg06d+XV3TcSU+AOldmGLJuB/AWV/+F9c9DlczqmnXqd
1QIDAQAB
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
-----END PUBLIC KEY-----';

	/**
	 * The public key in PKCS#8 format.
	 *
	 * @var string
	 */
	private $pkcs8_key = '-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAtAVnFFbWG+6NBFKhMZdt
59Gx2/vKxWxbxOAYyi/ypZ/9aDY6C/UBRei8SqnhKcKXQaiSwme/wpqgCdkrf53H
85OioBitCEvKNA6uDxkCtcdgtQ3X55QDXmatWd32ln6elRmKG45U9R386j82OHzf
f8Ju65QxGL1LlyCKQ/XFx/pgvblF3cGjshk0dhNcyGAztODN5HFp9Qzf9d7+gi+x
dKeGNhXBAulXoaDzx8FvLEXNfPJb3jUM1Ug0STFsiICcf7VxmQow6N6d0+HtWxrd
tjUBdXrPxz998Ns/cu9jjg06d+XV3TcSU+AOldmGLJuB/AWV/+F9c9DlczqmnXqd
1QIDAQAB
-----END PUBLIC KEY-----
';

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
		$post_id = Remote_Actors::create( $actor );
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
		$post_id = Remote_Actors::create( $actor );
		$this->assertIsInt( $post_id );
		$updated_actor         = $actor;
		$updated_actor['name'] = 'Jane Doe';
		$update_result         = Remote_Actors::update( $post_id, $updated_actor );
		$this->assertEquals( $post_id, $update_result );
		$updated_post = \get_post( $post_id );
		$this->assertInstanceOf( '\WP_Post', $updated_post );
		$actor_obj = Remote_Actors::get_actor( $updated_post );
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
		$post_id = Remote_Actors::create( $actor );
		$this->assertIsInt( $post_id );
		$delete_result = \wp_delete_post( $post_id, true );
		$this->assertInstanceOf( '\WP_Post', $delete_result );
		$deleted_post = \get_post( $post_id );
		$this->assertNull( $deleted_post );
	}

	/**
	 * Test fetch_by_uri.
	 *
	 * @covers ::fetch_by_uri
	 */
	public function test_fetch_by_uri() {
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

		$id = Remote_Actors::create( $actor );
		$this->assertNotWPError( $id );

		// Should find the actor locally.
		$post = Remote_Actors::fetch_by_uri( 'https://remote.example.com/actor/bob' );

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

		$post = Remote_Actors::fetch_by_uri( 'https://remote.example.com/actor/bob' );

		$this->assertInstanceOf( 'WP_Post', $post );
		$this->assertEquals( 'https://remote.example.com/actor/bob', $post->guid );

		remove_all_filters( 'activitypub_pre_http_get_remote_object' );
		\wp_delete_post( $post->ID );

		// Should return WP_Error for invalid URI.
		$not_found = Remote_Actors::fetch_by_uri( '' );

		$this->assertWPError( $not_found );
	}

	/**
	 * Test get_by_uri.
	 *
	 * @covers ::get_by_uri
	 */
	public function test_get_by_uri() {
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

		$id = Remote_Actors::create( $actor );
		$this->assertNotWPError( $id );

		// Should find the actor by guid.
		$post = Remote_Actors::get_by_uri( 'https://remote.example.com/actor/alice' );
		$this->assertInstanceOf( 'WP_Post', $post );
		$this->assertEquals( 'https://remote.example.com/actor/alice', $post->guid );

		// Should return WP_Error for non-existent URI.
		$not_found = Remote_Actors::get_by_uri( 'https://remote.example.com/actor/doesnotexist' );
		$this->assertWPError( $not_found );

		// Should return WP_Error for empty URI.
		$empty = Remote_Actors::get_by_uri( '' );
		$this->assertWPError( $empty );

		// Clean up.
		\wp_delete_post( $id );
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

		$id = Remote_Actors::upsert( $actor );
		$this->assertNotWPError( $id );

		// Add some errors.
		Remote_Actors::add_error( $id, 'Test error 1' );
		Remote_Actors::add_error( $id, 'Test error 2' );

		// Verify errors were added.
		$errors = \get_post_meta( $id, '_activitypub_errors', false );
		$this->assertCount( 2, $errors );

		// Clear errors.
		$cleared = Remote_Actors::clear_errors( $id );
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

		$id = Remote_Actors::upsert( $actor );
		$this->assertNotWPError( $id );

		// Clear errors when none exist.
		$cleared = Remote_Actors::clear_errors( $id );
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
		$cleared = Remote_Actors::clear_errors( 99999 );
		$this->assertFalse( $cleared );
	}

	/**
	 * Test handling of different public key formats.
	 *
	 * @covers ::get_public_key
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
		$result       = Remote_Actors::get_public_key( 'https://example.com/author/x509' );
		$key_resource = \openssl_pkey_get_details( $result );
		$this->assertNotFalse( $key_resource );
		$this->assertSame( $this->x509_key, $key_resource['key'] );

		// PKCS#1 key should be converted to X.509 format.
		$result       = Remote_Actors::get_public_key( 'https://example.com/author/pkcs1' );
		$key_resource = \openssl_pkey_get_details( $result );
		$this->assertNotFalse( $key_resource );
		$this->assertSame( $expected, $key_resource['key'] );

		// EC key should be handled correctly.
		$result       = Remote_Actors::get_public_key( 'https://example.com/author/ec' );
		$key_resource = \openssl_pkey_get_details( $result );
		$this->assertNotFalse( $key_resource );

		// PKCS#8 key should be handled correctly.
		$result       = Remote_Actors::get_public_key( 'https://example.com/author/pkcs8' );
		$key_resource = \openssl_pkey_get_details( $result );
		$this->assertNotFalse( $key_resource );

		// Test with invalid key.
		$result = Remote_Actors::get_public_key( 'https://example.com/author/invalid' );
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
