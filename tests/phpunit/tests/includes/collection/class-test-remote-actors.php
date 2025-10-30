<?php
/**
 * Test file for Activitypub Remote Actors Collection.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Collection;

use Activitypub\Collection\Remote_Actors;
use Activitypub\Mention;

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

		// Test 1: Should find the actor locally.
		$post = Remote_Actors::fetch_by_uri( 'https://remote.example.com/actor/bob' );
		$this->assertInstanceOf( 'WP_Post', $post );
		$this->assertEquals( 'https://remote.example.com/actor/bob', $post->guid );
		$this->assertEquals( $id, $post->ID );

		// Test 2: Delete local post, mock remote fetch.
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

		// Test 3: Should return WP_Error for empty URI.
		$empty_uri = Remote_Actors::fetch_by_uri( '' );
		$this->assertWPError( $empty_uri );

		// Test 4: Should return WP_Error when remote fetch fails.
		add_filter(
			'activitypub_pre_http_get_remote_object',
			function () {
				return new \WP_Error( 'http_request_failed', 'Request failed' );
			},
			10,
			2
		);

		$failed_fetch = Remote_Actors::fetch_by_uri( 'https://nonexistent.example.com/actor/missing' );
		$this->assertWPError( $failed_fetch );

		// Test 5: Should return WP_Error when remote object is not an actor.
		add_filter(
			'activitypub_pre_http_get_remote_object',
			function () {
				return array(
					'id'      => 'https://remote.example.com/note/123',
					'type'    => 'Note',
					'content' => 'This is not an actor',
				);
			},
			10,
			2
		);

		$not_actor = Remote_Actors::fetch_by_uri( 'https://remote.example.com/note/123' );
		$this->assertWPError( $not_actor );
		$this->assertEquals( 'activitypub_no_actor', $not_actor->get_error_code() );

		remove_all_filters( 'activitypub_pre_http_get_remote_object' );
	}

	/**
	 * Test fetch_by_various method.
	 *
	 * @covers ::fetch_by_various
	 */
	public function test_fetch_by_various() {
		// Test 1: Valid URL should call fetch_by_uri.
		$actor = array(
			'id'                => 'https://remote.example.com/actor/charlie',
			'type'              => 'Person',
			'url'               => 'https://remote.example.com/actor/charlie',
			'inbox'             => 'https://remote.example.com/actor/charlie/inbox',
			'name'              => 'Charlie',
			'preferredUsername' => 'charlie',
			'endpoints'         => array(
				'sharedInbox' => 'https://remote.example.com/inbox',
			),
		);

		$id = Remote_Actors::create( $actor );
		$this->assertNotWPError( $id );

		$post = Remote_Actors::fetch_by_various( 'https://remote.example.com/actor/charlie' );
		$this->assertInstanceOf( 'WP_Post', $post );
		$this->assertEquals( 'https://remote.example.com/actor/charlie', $post->guid );

		\wp_delete_post( $id );

		// Test 2: Acct identifier should call fetch_by_acct.
		// Mock webfinger resolution.
		add_filter(
			'pre_http_request',
			function ( $preempt, $parsed_args, $url ) {
				if ( strpos( $url, '.well-known/webfinger' ) !== false ) {
					return array(
						'response' => array( 'code' => 200 ),
						'body'     => wp_json_encode(
							array(
								'subject' => 'acct:charlie@remote.example.com',
								'links'   => array(
									array(
										'rel'  => 'self',
										'type' => 'application/activity+json',
										'href' => 'https://remote.example.com/actor/charlie',
									),
								),
							)
						),
					);
				}
				return $preempt;
			},
			10,
			3
		);

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

		$post = Remote_Actors::fetch_by_various( 'charlie@remote.example.com' );
		$this->assertInstanceOf( 'WP_Post', $post );
		$this->assertEquals( 'https://remote.example.com/actor/charlie', $post->guid );

		// Verify acct meta was stored.
		$stored_acct = \get_post_meta( $post->ID, '_activitypub_acct', true );
		$this->assertEquals( 'charlie@remote.example.com', $stored_acct );

		remove_all_filters( 'pre_http_request' );
		remove_all_filters( 'activitypub_pre_http_get_remote_object' );
		\wp_delete_post( $post->ID );

		// Test 3: Invalid input returns WP_Error.
		$invalid = Remote_Actors::fetch_by_various( '' );
		$this->assertWPError( $invalid );

		// Test 4: URL without scheme.
		$no_scheme = Remote_Actors::fetch_by_various( 'example.com/actor/test' );
		$this->assertWPError( $no_scheme );

		// Test 5: Malformed acct.
		$malformed_acct = Remote_Actors::fetch_by_various( 'not-an-email-or-url' );
		$this->assertWPError( $malformed_acct );
	}

	/**
	 * Test fetch_by_acct method.
	 *
	 * @covers ::fetch_by_acct
	 */
	public function test_fetch_by_acct() {
		// Test 1: Find existing actor by acct meta.
		$actor = array(
			'id'                => 'https://remote.example.com/actor/diana',
			'type'              => 'Person',
			'url'               => 'https://remote.example.com/actor/diana',
			'inbox'             => 'https://remote.example.com/actor/diana/inbox',
			'name'              => 'Diana',
			'preferredUsername' => 'diana',
			'endpoints'         => array(
				'sharedInbox' => 'https://remote.example.com/inbox',
			),
		);

		$id = Remote_Actors::create( $actor );
		$this->assertNotWPError( $id );
		\update_post_meta( $id, '_activitypub_acct', 'diana@remote.example.com' );

		$post = Remote_Actors::fetch_by_acct( 'diana@remote.example.com' );
		$this->assertInstanceOf( 'WP_Post', $post );
		$this->assertEquals( $id, $post->ID );

		\wp_delete_post( $id );

		// Test 2: Webfinger resolution and remote fetch.
		add_filter(
			'pre_http_request',
			function ( $preempt, $parsed_args, $url ) {
				if ( strpos( $url, '.well-known/webfinger' ) !== false ) {
					return array(
						'response' => array( 'code' => 200 ),
						'body'     => wp_json_encode(
							array(
								'subject' => 'acct:diana@remote.example.com',
								'links'   => array(
									array(
										'rel'  => 'self',
										'type' => 'application/activity+json',
										'href' => 'https://remote.example.com/actor/diana',
									),
								),
							)
						),
					);
				}
				return $preempt;
			},
			10,
			3
		);

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

		$post = Remote_Actors::fetch_by_acct( 'diana@remote.example.com' );
		$this->assertInstanceOf( 'WP_Post', $post );
		$this->assertEquals( 'https://remote.example.com/actor/diana', $post->guid );

		// Verify acct meta was stored after remote fetch.
		$stored_acct = \get_post_meta( $post->ID, '_activitypub_acct', true );
		$this->assertEquals( 'diana@remote.example.com', $stored_acct );

		\wp_delete_post( $post->ID );
		remove_all_filters( 'pre_http_request' );
		remove_all_filters( 'activitypub_pre_http_get_remote_object' );

		// Test 3: Webfinger resolution failure.
		add_filter(
			'pre_http_request',
			function ( $preempt, $parsed_args, $url ) {
				if ( strpos( $url, '.well-known/webfinger' ) !== false ) {
					return array(
						'response' => array( 'code' => 404 ),
						'body'     => 'Not Found',
					);
				}
				return $preempt;
			},
			10,
			3
		);

		$not_found = Remote_Actors::fetch_by_acct( 'notfound@example.com' );
		$this->assertWPError( $not_found );

		remove_all_filters( 'pre_http_request' );

		// Test 4: Invalid acct format.
		$invalid = Remote_Actors::fetch_by_acct( 'invalid-acct-format' );
		$this->assertWPError( $invalid );

		// Test 5: Empty acct.
		$empty = Remote_Actors::fetch_by_acct( '' );
		$this->assertWPError( $empty );

		// Test 6: Acct sanitization (with @acct: prefix).
		add_filter(
			'pre_http_request',
			function ( $preempt, $parsed_args, $url ) {
				if ( strpos( $url, '.well-known/webfinger' ) !== false ) {
					return array(
						'response' => array( 'code' => 200 ),
						'body'     => wp_json_encode(
							array(
								'subject' => 'acct:diana@remote.example.com',
								'links'   => array(
									array(
										'rel'  => 'self',
										'type' => 'application/activity+json',
										'href' => 'https://remote.example.com/actor/diana',
									),
								),
							)
						),
					);
				}
				return $preempt;
			},
			10,
			3
		);

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

		// Test with @acct: prefix - should be sanitized.
		$post = Remote_Actors::fetch_by_acct( '@acct:diana@remote.example.com' );
		$this->assertInstanceOf( 'WP_Post', $post );

		// Verify acct was properly sanitized and stored.
		$stored_acct = \get_post_meta( $post->ID, '_activitypub_acct', true );
		$this->assertEquals( 'diana@remote.example.com', $stored_acct );

		\wp_delete_post( $post->ID );
		remove_all_filters( 'pre_http_request' );
		remove_all_filters( 'activitypub_pre_http_get_remote_object' );

		// Test 7: Webfinger succeeds but remote fetch fails.
		add_filter(
			'pre_http_request',
			function ( $preempt, $parsed_args, $url ) {
				if ( strpos( $url, '.well-known/webfinger' ) !== false ) {
					return array(
						'response' => array( 'code' => 200 ),
						'body'     => wp_json_encode(
							array(
								'subject' => 'acct:broken@remote.example.com',
								'links'   => array(
									array(
										'rel'  => 'self',
										'type' => 'application/activity+json',
										'href' => 'https://broken.example.com/actor/broken',
									),
								),
							)
						),
					);
				}
				return $preempt;
			},
			10,
			3
		);

		add_filter(
			'activitypub_pre_http_get_remote_object',
			function () {
				return new \WP_Error( 'http_request_failed', 'Actor fetch failed' );
			},
			10,
			2
		);

		$fetch_failed = Remote_Actors::fetch_by_acct( 'broken@remote.example.com' );
		$this->assertWPError( $fetch_failed );

		remove_all_filters( 'pre_http_request' );
		remove_all_filters( 'activitypub_pre_http_get_remote_object' );
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

		\add_filter( 'activitypub_pre_http_get_remote_object', array( $this, 'pre_http_get_remote_object' ), 10, 2 );

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

		// Test GoToSocial-style /main-key path suffix is stripped correctly.
		$result       = Remote_Actors::get_public_key( 'https://example.com/author/x509/main-key' );
		$key_resource = \openssl_pkey_get_details( $result );
		$this->assertSame( $this->x509_key, $key_resource['key'] );

		\remove_filter( 'activitypub_pre_http_get_remote_object', array( $this, 'pre_http_get_remote_object' ) );
	}

	/**
	 * Test the get_acct method.
	 *
	 * @covers ::get_acct
	 */
	public function test_get_acct() {
		// Test 1: Return cached acct from post meta.
		$actor   = array(
			'id'                => 'https://remote.example.com/actor/cached-user',
			'type'              => 'Person',
			'url'               => 'https://remote.example.com/actor/cached-user',
			'inbox'             => 'https://remote.example.com/actor/cached-user/inbox',
			'name'              => 'Cached User',
			'preferredUsername' => 'cached',
		);
		$post_id = Remote_Actors::create( $actor );
		\update_post_meta( $post_id, '_activitypub_acct', 'cached@example.com' );

		$result = Remote_Actors::get_acct( $post_id );
		$this->assertEquals( 'cached@example.com', $result );

		// Clean up.
		\wp_delete_post( $post_id, true );

		// Test 2: Return empty string for non-existent post.
		$result = Remote_Actors::get_acct( 99999 );
		$this->assertEquals( '', $result );

		// Test 3: Successful uri_to_acct conversion.
		$actor2   = array(
			'id'                => 'https://remote.example.com/actor/webfinger-user',
			'type'              => 'Person',
			'url'               => 'https://remote.example.com/actor/webfinger-user',
			'inbox'             => 'https://remote.example.com/actor/webfinger-user/inbox',
			'name'              => 'Webfinger User',
			'preferredUsername' => 'webfinger',
		);
		$post_id2 = Remote_Actors::create( $actor2 );

		// Mock successful Webfinger::uri_to_acct.
		\add_filter(
			'pre_http_request',
			function ( $preempt, $parsed_args, $url ) {
				if ( strpos( $url, '.well-known/webfinger' ) !== false ) {
					return array(
						'response' => array( 'code' => 200 ),
						'body'     => wp_json_encode(
							array(
								'subject' => 'acct:webfinger@remote.example.com',
								'links'   => array(
									array(
										'rel'  => 'self',
										'type' => 'application/activity+json',
										'href' => 'https://remote.example.com/actor/webfinger-user',
									),
								),
							)
						),
					);
				}
				return $preempt;
			},
			10,
			3
		);

		$result = Remote_Actors::get_acct( $post_id2 );
		$this->assertEquals( 'webfinger@remote.example.com', $result );

		// Verify it was cached.
		$cached_acct = \get_post_meta( $post_id2, '_activitypub_acct', true );
		$this->assertEquals( 'webfinger@remote.example.com', $cached_acct );

		\remove_all_filters( 'pre_http_request' );
		\wp_delete_post( $post_id2, true );

		// Test 4: Fallback to Webfinger::guess when uri_to_acct fails.
		$actor3   = array(
			'id'                => 'https://remote.example.com/actor/guess-user',
			'type'              => 'Person',
			'url'               => 'https://remote.example.com/actor/guess-user',
			'inbox'             => 'https://remote.example.com/actor/guess-user/inbox',
			'name'              => 'Guess User',
			'preferredUsername' => 'guess',
		);
		$post_id3 = Remote_Actors::create( $actor3 );

		// Mock failed Webfinger::uri_to_acct (returns WP_Error).
		\add_filter(
			'pre_http_request',
			function ( $preempt, $parsed_args, $url ) {
				if ( strpos( $url, '.well-known/webfinger' ) !== false ) {
					return array(
						'response' => array( 'code' => 404 ),
						'body'     => 'Not Found',
					);
				}
				return $preempt;
			},
			10,
			3
		);

		$result = Remote_Actors::get_acct( $post_id3 );
		$this->assertEquals( 'guess@remote.example.com', $result );

		// Verify it was cached (without acct: prefix).
		$cached_acct = \get_post_meta( $post_id3, '_activitypub_acct', true );
		$this->assertEquals( 'guess@remote.example.com', $cached_acct );

		\remove_all_filters( 'pre_http_request' );
		\remove_all_filters( 'pre_get_remote_metadata_by_actor' );
		\wp_delete_post( $post_id3, true );

		// Test 5: Handle acct: prefix removal.
		$actor4   = array(
			'id'                => 'https://remote.example.com/actor/acct-prefix-user',
			'type'              => 'Person',
			'url'               => 'https://remote.example.com/actor/acct-prefix-user',
			'inbox'             => 'https://remote.example.com/actor/acct-prefix-user/inbox',
			'name'              => 'Acct Prefix User',
			'preferredUsername' => 'acctprefix',
		);
		$post_id4 = Remote_Actors::create( $actor4 );

		// Mock Webfinger::uri_to_acct returning acct: prefixed result.
		\add_filter(
			'pre_http_request',
			function ( $preempt, $parsed_args, $url ) {
				if ( strpos( $url, '.well-known/webfinger' ) !== false ) {
					return array(
						'response' => array( 'code' => 200 ),
						'body'     => wp_json_encode(
							array(
								'subject' => 'acct:acctprefix@remote.example.com',
								'links'   => array(
									array(
										'rel'  => 'self',
										'type' => 'application/activity+json',
										'href' => 'https://remote.example.com/actor/acct-prefix-user',
									),
								),
							)
						),
					);
				}
				return $preempt;
			},
			10,
			3
		);

		$result = Remote_Actors::get_acct( $post_id4 );
		$this->assertEquals( 'acctprefix@remote.example.com', $result );

		// Verify cached value has acct: prefix removed.
		$cached_acct = \get_post_meta( $post_id4, '_activitypub_acct', true );
		$this->assertEquals( 'acctprefix@remote.example.com', $cached_acct );

		\remove_all_filters( 'pre_http_request' );
		\wp_delete_post( $post_id4, true );
	}

	/**
	 * Test that saving a remote actor with a self-mention doesn't cause infinite recursion.
	 *
	 * @covers ::create
	 * @covers ::prepare_custom_post_type
	 */
	public function test_create_actor_with_self_mention_no_recursion() {
		// Ensure the Mention filter is active to test for recursion.
		Mention::init();

		// Create an actor with a self-mention in their summary.
		$actor = array(
			'id'                => 'https://remote.example.com/actor/self-mention',
			'type'              => 'Person',
			'url'               => 'https://remote.example.com/actor/self-mention',
			'inbox'             => 'https://remote.example.com/actor/self-mention/inbox',
			'name'              => 'Self Mention User',
			'preferredUsername' => 'selfmention',
			'summary'           => 'Hello, I am @selfmention@remote.example.com and I like to mention myself!',
			'endpoints'         => array(
				'sharedInbox' => 'https://remote.example.com/inbox',
			),
		);

		// Mock webfinger to resolve the mention.
		$webfinger_callback = function ( $preempt, $parsed_args, $url ) {
			if ( strpos( $url, '.well-known/webfinger' ) !== false ) {
				return array(
					'response' => array( 'code' => 200 ),
					'body'     => wp_json_encode(
						array(
							'subject' => 'acct:selfmention@remote.example.com',
							'links'   => array(
								array(
									'rel'  => 'self',
									'type' => 'application/activity+json',
									'href' => 'https://remote.example.com/actor/self-mention',
								),
							),
						)
					),
				);
			}

			return $preempt;
		};
		\add_filter( 'pre_http_request', $webfinger_callback, 10, 3 );

		// Mock remote actor fetch to return the same actor (creating potential recursion).
		$actor_fetch_callback = function ( $pre, $url_or_object ) use ( $actor ) {
			if ( $url_or_object === $actor['id'] ) {
				return $actor;
			}

			return $pre;
		};
		\add_filter( 'activitypub_pre_http_get_remote_object', $actor_fetch_callback, 10, 2 );

		// This should not cause infinite recursion.
		$post_id = Remote_Actors::create( $actor );

		$this->assertIsInt( $post_id );
		$this->assertGreaterThan( 0, $post_id );

		$post = \get_post( $post_id );
		$this->assertInstanceOf( '\WP_Post', $post );
		$this->assertEquals( 'https://remote.example.com/actor/self-mention', $post->guid );

		// Verify the summary was stored correctly (without being processed for mentions).
		$this->assertStringContainsString( '@selfmention@remote.example.com', $post->post_excerpt );

		// Clean up - remove only the specific filters we added.
		\remove_filter( 'pre_http_request', $webfinger_callback );
		\remove_filter( 'activitypub_pre_http_get_remote_object', $actor_fetch_callback );
		\remove_filter( 'activitypub_activity_object_array', array( 'Activitypub\Mention', 'filter_activity_object' ), 99 );
		\remove_filter( 'activitypub_activity_object_array', array( 'Activitypub\Hashtag', 'filter_activity_object' ), 99 );
		\remove_filter( 'activitypub_activity_object_array', array( 'Activitypub\Link', 'filter_activity_object' ), 99 );
		\wp_delete_post( $post_id, true );
	}

	/**
	 * Test that saving a remote actor with mentions of other actors doesn't cause recursion.
	 *
	 * @covers ::create
	 * @covers ::prepare_custom_post_type
	 */
	public function test_create_actor_with_cross_mentions_no_recursion() {
		// Ensure the Mention filter is active to test for recursion.
		Mention::init();

		// Create two actors that mention each other in their bios.
		$actor_a = array(
			'id'                => 'https://remote.example.com/actor/alice-cross',
			'type'              => 'Person',
			'url'               => 'https://remote.example.com/actor/alice-cross',
			'inbox'             => 'https://remote.example.com/actor/alice-cross/inbox',
			'name'              => 'Alice',
			'preferredUsername' => 'alice',
			'summary'           => 'Best friends with @bob@remote.example.com',
			'endpoints'         => array(
				'sharedInbox' => 'https://remote.example.com/inbox',
			),
		);

		$actor_b = array(
			'id'                => 'https://remote.example.com/actor/bob-cross',
			'type'              => 'Person',
			'url'               => 'https://remote.example.com/actor/bob-cross',
			'inbox'             => 'https://remote.example.com/actor/bob-cross/inbox',
			'name'              => 'Bob',
			'preferredUsername' => 'bob',
			'summary'           => 'Best friends with @alice@remote.example.com',
			'endpoints'         => array(
				'sharedInbox' => 'https://remote.example.com/inbox',
			),
		);

		// Mock webfinger to resolve the mentions.
		$webfinger_callback = function ( $preempt, $parsed_args, $url ) {
			if ( strpos( $url, '.well-known/webfinger' ) !== false ) {
				if ( strpos( $url, 'bob@remote.example.com' ) !== false ) {
					return array(
						'response' => array( 'code' => 200 ),
						'body'     => wp_json_encode(
							array(
								'subject' => 'acct:bob@remote.example.com',
								'links'   => array(
									array(
										'rel'  => 'self',
										'type' => 'application/activity+json',
										'href' => 'https://remote.example.com/actor/bob-cross',
									),
								),
							)
						),
					);
				} elseif ( strpos( $url, 'alice@remote.example.com' ) !== false ) {
					return array(
						'response' => array( 'code' => 200 ),
						'body'     => wp_json_encode(
							array(
								'subject' => 'acct:alice@remote.example.com',
								'links'   => array(
									array(
										'rel'  => 'self',
										'type' => 'application/activity+json',
										'href' => 'https://remote.example.com/actor/alice-cross',
									),
								),
							)
						),
					);
				}
			}

			return $preempt;
		};
		\add_filter( 'pre_http_request', $webfinger_callback, 10, 3 );

		// Mock the remote fetch to return the cross-mentioned actors.
		$actor_fetch_callback = function ( $pre, $url_or_object ) use ( $actor_a, $actor_b ) {
			if ( $url_or_object === $actor_a['id'] ) {
				return $actor_a;
			}
			if ( $url_or_object === $actor_b['id'] ) {
				return $actor_b;
			}

			return $pre;
		};
		\add_filter( 'activitypub_pre_http_get_remote_object', $actor_fetch_callback, 10, 2 );

		// This should not cause infinite recursion when creating both actors.
		$post_id_a = Remote_Actors::create( $actor_a );
		$this->assertIsInt( $post_id_a );

		$post_id_b = Remote_Actors::create( $actor_b );
		$this->assertIsInt( $post_id_b );

		// Verify both were created successfully.
		$this->assertGreaterThan( 0, $post_id_a );
		$this->assertGreaterThan( 0, $post_id_b );

		// Clean up - remove only the specific filters we added.
		\remove_filter( 'pre_http_request', $webfinger_callback );
		\remove_filter( 'activitypub_pre_http_get_remote_object', $actor_fetch_callback );
		\remove_filter( 'activitypub_activity_object_array', array( 'Activitypub\Mention', 'filter_activity_object' ), 99 );
		\remove_filter( 'activitypub_activity_object_array', array( 'Activitypub\Hashtag', 'filter_activity_object' ), 99 );
		\remove_filter( 'activitypub_activity_object_array', array( 'Activitypub\Link', 'filter_activity_object' ), 99 );
		\wp_delete_post( $post_id_a, true );
		\wp_delete_post( $post_id_b, true );
	}

	/**
	 * Pre get remote metadata by actor.
	 *
	 * @param mixed  $value The value.
	 * @param string $url   The URL.
	 * @return array|\WP_Error
	 */
	public function pre_get_remote_metadata_by_actor( $value, $url ) {
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

	/**
	 * Pre http get remote object.
	 *
	 * @param mixed  $pre           The preempted value.
	 * @param string $url_or_object The URL or object.
	 * @return array|\WP_Error
	 */
	public function pre_http_get_remote_object( $pre, $url_or_object ) {

		if ( 'https://example.com/author/x509' === $url_or_object ) {
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

		if ( 'https://example.com/author/pkcs1' === $url_or_object ) {
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

		if ( 'https://example.com/author/ec' === $url_or_object ) {
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

		if ( 'https://example.com/author/pkcs8' === $url_or_object ) {
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

		if ( 'https://example.com/author/x509/main-key' === $url_or_object ) {
			return array(
				'id'        => 'https://example.com/author/x509',
				'type'      => 'Person',
				'publicKey' => array(
					'id'           => 'https://example.com/author#main-key',
					'owner'        => 'https://example.com/author',
					'publicKeyPem' => $this->x509_key,
				),
			);
		}

		return $pre;
	}

	/**
	 * Test get_avatar_url with avatar in meta.
	 *
	 * @covers ::get_avatar_url
	 */
	public function test_get_avatar_url_from_meta() {
		// Create a remote actor with avatar in meta.
		$actor_data = array(
			'id'                => 'https://example.com/users/avatar-test',
			'type'              => 'Person',
			'preferredUsername' => 'avatartest',
			'name'              => 'Avatar Test',
			'icon'              => array(
				'type' => 'Image',
				'url'  => 'https://example.com/avatar-test.jpg',
			),
			'inbox'             => 'https://example.com/inbox-avatar',
		);

		$remote_actor_id = Remote_Actors::upsert( $actor_data );
		$this->assertIsInt( $remote_actor_id );

		// Verify avatar URL is stored in meta.
		$avatar_url = get_post_meta( $remote_actor_id, '_activitypub_avatar_url', true );
		$this->assertEquals( 'https://example.com/avatar-test.jpg', $avatar_url );

		// Test get_avatar_url retrieves from meta.
		$retrieved_avatar = Remote_Actors::get_avatar_url( $remote_actor_id );
		$this->assertEquals( 'https://example.com/avatar-test.jpg', $retrieved_avatar );

		// Clean up.
		wp_delete_post( $remote_actor_id, true );
	}

	/**
	 * Test get_avatar_url fallback to JSON when meta is empty.
	 *
	 * @covers ::get_avatar_url
	 */
	public function test_get_avatar_url_fallback_to_json() {
		// Create a remote actor.
		$actor_data = array(
			'id'                => 'https://example.com/users/json-avatar',
			'type'              => 'Person',
			'preferredUsername' => 'jsonavatar',
			'name'              => 'JSON Avatar',
			'icon'              => array(
				'type' => 'Image',
				'url'  => 'https://example.com/json-avatar.jpg',
			),
			'inbox'             => 'https://example.com/inbox-json',
		);

		$remote_actor_id = Remote_Actors::upsert( $actor_data );
		$this->assertIsInt( $remote_actor_id );

		// Delete the avatar meta to simulate old data.
		delete_post_meta( $remote_actor_id, '_activitypub_avatar_url' );

		// Verify meta is empty.
		$avatar_meta = get_post_meta( $remote_actor_id, '_activitypub_avatar_url', true );
		$this->assertEmpty( $avatar_meta );

		// Test get_avatar_url extracts from JSON and caches it.
		$retrieved_avatar = Remote_Actors::get_avatar_url( $remote_actor_id );
		$this->assertEquals( 'https://example.com/json-avatar.jpg', $retrieved_avatar );

		// Verify it was cached in meta.
		$cached_avatar = get_post_meta( $remote_actor_id, '_activitypub_avatar_url', true );
		$this->assertEquals( 'https://example.com/json-avatar.jpg', $cached_avatar );

		// Clean up.
		wp_delete_post( $remote_actor_id, true );
	}

	/**
	 * Test get_avatar_url with array of URLs.
	 *
	 * @covers ::get_avatar_url
	 */
	public function test_get_avatar_url_with_array() {
		// Create a remote actor with array of avatar URLs.
		$actor_data = array(
			'id'                => 'https://example.com/users/array-avatar',
			'type'              => 'Person',
			'preferredUsername' => 'arrayavatar',
			'name'              => 'Array Avatar',
			'icon'              => array(
				'type' => 'Image',
				'url'  => array(
					'https://example.com/avatar1.jpg',
					'https://example.com/avatar2.jpg',
				),
			),
			'inbox'             => 'https://example.com/inbox-array',
		);

		$remote_actor_id = Remote_Actors::upsert( $actor_data );
		$this->assertIsInt( $remote_actor_id );

		// Test get_avatar_url retrieves first URL from array.
		$retrieved_avatar = Remote_Actors::get_avatar_url( $remote_actor_id );
		$this->assertEquals( 'https://example.com/avatar1.jpg', $retrieved_avatar );

		// Clean up.
		wp_delete_post( $remote_actor_id, true );
	}

	/**
	 * Test get_avatar_url with no avatar returns default.
	 *
	 * @covers ::get_avatar_url
	 */
	public function test_get_avatar_url_empty() {
		// Create a remote actor without avatar.
		$actor_data = array(
			'id'                => 'https://example.com/users/no-avatar',
			'type'              => 'Person',
			'preferredUsername' => 'noavatar',
			'name'              => 'No Avatar',
			'inbox'             => 'https://example.com/inbox-no-avatar',
		);

		$remote_actor_id = Remote_Actors::upsert( $actor_data );
		$this->assertIsInt( $remote_actor_id );

		// Test get_avatar_url returns default avatar URL.
		$retrieved_avatar = Remote_Actors::get_avatar_url( $remote_actor_id );
		$this->assertNotEmpty( $retrieved_avatar );
		$this->assertStringContainsString( 'assets/img/mp.jpg', $retrieved_avatar );

		// Clean up.
		wp_delete_post( $remote_actor_id, true );
	}
}
