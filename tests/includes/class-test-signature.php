<?php
/**
 * Test file for Activitypub Signature.
 *
 * @package Activitypub
 */

// phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode

namespace Activitypub\Tests;

use Activitypub\Signature;
use Activitypub\Collection\Actors;

/**
 * Test class for Signature.
 *
 * @coversDefaultClass \Activitypub\Signature
 */
class Test_Signature extends \WP_UnitTestCase {

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
	 * Store test keys for HTTP signatures.
	 *
	 * @var array
	 */
	private static $test_keys = array();

	/**
	 * Set up before class.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		self::$test_keys = \json_decode( \file_get_contents( \dirname( __DIR__ ) . '/fixtures/http-signature-keys.json' ), true );
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
	 * Test signature creation.
	 *
	 * @covers ::get_keypair_for
	 * @covers ::get_public_key_for
	 * @covers ::get_private_key_for
	 */
	public function test_signature_creation() {
		$user = Actors::get_by_id( 1 );

		$key_pair    = Signature::get_keypair_for( $user->get__id() );
		$public_key  = Signature::get_public_key_for( $user->get__id() );
		$private_key = Signature::get_private_key_for( $user->get__id() );

		$this->assertNotEmpty( $key_pair );
		$this->assertEquals( $key_pair['public_key'], $public_key );
		$this->assertEquals( $key_pair['private_key'], $private_key );
	}

	/**
	 * Test signature legacy.
	 *
	 * @covers ::get_keypair_for
	 */
	public function test_signature_legacy() {
		// Check user.
		$user = Actors::get_by_id( 1 );

		\delete_option( 'activitypub_keypair_for_admin' );

		$public_key  = 'public key ' . $user->get__id();
		$private_key = 'private key ' . $user->get__id();

		update_user_meta( $user->get__id(), 'magic_sig_public_key', $public_key );
		update_user_meta( $user->get__id(), 'magic_sig_private_key', $private_key );

		$key_pair = Signature::get_keypair_for( $user->get__id() );

		$this->assertNotEmpty( $key_pair );
		$this->assertEquals( $key_pair['public_key'], $public_key );
		$this->assertEquals( $key_pair['private_key'], $private_key );

		// Check application user.
		$user = Actors::get_by_id( Actors::APPLICATION_USER_ID );

		\delete_option( 'activitypub_keypair_for_-1' );

		$public_key  = 'public key ' . $user->get__id();
		$private_key = 'private key ' . $user->get__id();

		\add_option( 'activitypub_application_user_public_key', $public_key );
		\add_option( 'activitypub_application_user_private_key', $private_key );

		$key_pair = Signature::get_keypair_for( $user->get__id() );

		$this->assertNotEmpty( $key_pair );
		$this->assertEquals( $key_pair['public_key'], $public_key );
		$this->assertEquals( $key_pair['private_key'], $private_key );

		// Check blog user.
		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_AND_BLOG_MODE );
		$user = Actors::get_by_id( Actors::BLOG_USER_ID );
		\delete_option( 'activitypub_actor_mode' );

		$public_key  = 'public key ' . $user->get__id();
		$private_key = 'private key ' . $user->get__id();

		\delete_option( 'activitypub_keypair_for_0' );

		\add_option( 'activitypub_blog_user_public_key', $public_key );
		\add_option( 'activitypub_blog_user_private_key', $private_key );

		$key_pair = Signature::get_keypair_for( $user->get__id() );

		$this->assertNotEmpty( $key_pair );
		$this->assertEquals( $key_pair['public_key'], $public_key );
		$this->assertEquals( $key_pair['private_key'], $private_key );
	}

	/**
	 * Test signature consistency.
	 *
	 * @covers ::get_keypair_for
	 */
	public function test_signature_consistency() {
		// Check user.
		$user = Actors::get_by_id( 1 );

		$public_key  = 'public key ' . $user->get__id();
		$private_key = 'private key ' . $user->get__id();

		update_user_meta( $user->get__id(), 'magic_sig_public_key', $public_key );
		update_user_meta( $user->get__id(), 'magic_sig_private_key', $private_key );

		$key_pair = Signature::get_keypair_for( $user->get__id() );

		$this->assertNotEmpty( $key_pair );
		$this->assertEquals( $key_pair['public_key'], $public_key );
		$this->assertEquals( $key_pair['private_key'], $private_key );

		update_user_meta( $user->get__id(), 'magic_sig_public_key', $public_key . '-update' );
		update_user_meta( $user->get__id(), 'magic_sig_private_key', $private_key . '-update' );

		$key_pair = Signature::get_keypair_for( $user->get__id() );

		$this->assertNotEmpty( $key_pair );
		$this->assertEquals( $key_pair['public_key'], $public_key );
		$this->assertEquals( $key_pair['private_key'], $private_key );
	}

	/**
	 * Test signature consistancy 2.
	 *
	 * @covers ::get_keypair_for
	 */
	public function test_signature_consistency2() {
		$user = Actors::get_by_id( 1 );

		$key_pair    = Signature::get_keypair_for( $user->get__id() );
		$public_key  = Signature::get_public_key_for( $user->get__id() );
		$private_key = Signature::get_private_key_for( $user->get__id() );

		$this->assertNotEmpty( $key_pair );
		$this->assertEquals( $key_pair['public_key'], $public_key );
		$this->assertEquals( $key_pair['private_key'], $private_key );

		update_user_meta( $user->get__id(), 'magic_sig_public_key', 'test' );
		update_user_meta( $user->get__id(), 'magic_sig_private_key', 'test' );

		$key_pair = Signature::get_keypair_for( $user->get__id() );

		$this->assertNotEmpty( $key_pair );
		$this->assertEquals( $key_pair['public_key'], $public_key );
		$this->assertEquals( $key_pair['private_key'], $private_key );
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
		$result       = Signature::get_remote_key( 'https://example.com/author/x509' );
		$key_resource = \openssl_pkey_get_details( $result );
		$this->assertNotFalse( $key_resource );
		$this->assertSame( $this->x509_key, $key_resource['key'] );

		// PKCS#1 key should be converted to X.509 format.
		$result       = Signature::get_remote_key( 'https://example.com/author/pkcs1' );
		$key_resource = \openssl_pkey_get_details( $result );
		$this->assertNotFalse( $key_resource );
		$this->assertSame( $expected, $key_resource['key'] );

		// EC key should be handled correctly.
		$result       = Signature::get_remote_key( 'https://example.com/author/ec' );
		$key_resource = \openssl_pkey_get_details( $result );
		$this->assertNotFalse( $key_resource );

		// PKCS#8 key should be handled correctly.
		$result       = Signature::get_remote_key( 'https://example.com/author/pkcs8' );
		$key_resource = \openssl_pkey_get_details( $result );
		$this->assertNotFalse( $key_resource );

		// Test with invalid key.
		$result = Signature::get_remote_key( 'https://example.com/author/invalid' );
		$this->assertWPError( $result );

		\remove_filter( 'pre_get_remote_metadata_by_actor', array( $this, 'pre_get_remote_metadata_by_actor' ) );
	}

	/**
	 * Data provider for signature algorithm tests.
	 *
	 * @return string[][] Test data.
	 */
	public function signature_algorithm_provider() {
		$error = new \WP_Error( 'unsupported_key_type', 'Unsupported signature algorithm (only rsa-sha256, rsa-sha512, and hs2019 are supported).', array( 'status' => 401 ) );

		return array(
			'rsa-sha256 algorithm'  => array(
				array( 'algorithm' => 'rsa-sha256' ),
				\OPENSSL_ALGO_SHA256,
				'rsa-sha256 algorithm should return sha256.',
			),
			'unknown algorithm'     => array(
				array( 'algorithm' => 'unknown-algorithm' ),
				\OPENSSL_ALGO_SHA256,
				'Unknown algorithm should return sha256.',
			),
			'empty algorithm'       => array(
				array( 'algorithm' => '' ),
				$error,
				'Empty algorithm should return WP_Error.',
			),
			'missing algorithm key' => array(
				array(),
				$error,
				'Missing algorithm key should return WP_Error.',
			),
		);
	}

	/**
	 * Test signature algorithm detection.
	 *
	 * @covers ::get_signature_algorithm
	 * @dataProvider signature_algorithm_provider
	 *
	 * @param array        $signature_block The signature block to test.
	 * @param string|false $expected        The expected result.
	 * @param string       $message         The assertion message.
	 */
	public function test_get_signature_algorithm( $signature_block, $expected, $message ) {
		$this->assertEquals( $expected, Signature::get_signature_algorithm( $signature_block, \openssl_pkey_get_public( '' ) ), $message );
	}

	/**
	 * Data provider for EC curve tests.
	 *
	 * @return array[][] Test data.
	 */
	public function provide_ec_curves() {
		return array(
			'prime256v1' => array( 'prime256v1', \OPENSSL_ALGO_SHA256 ), // aka secp256r1.
			'secp384r1'  => array( 'secp384r1', \OPENSSL_ALGO_SHA384 ),
			'secp521r1'  => array( 'secp521r1', \OPENSSL_ALGO_SHA512 ),
		);
	}

	/**
	 * Test valid hs2019 signatures for EC curves.
	 *
	 * @dataProvider provide_ec_curves
	 * @param string $curve The EC curve name.
	 * @param int    $algo  The OpenSSL algorithm constant.
	 */
	public function test_valid_hs2019_signatures_for_ec_curves( $curve, $algo ) {
		$public_key  = self::$test_keys['ec'][ $curve ]['public_key'];
		$private_key = \openssl_pkey_get_private( self::$test_keys['ec'][ $curve ]['private_key'] );

		$date           = \gmdate( 'D, d M Y H:i:s T' );
		$string_to_sign = "(request-target): post /wp-json/activitypub/1.0/inbox\nhost: example.org\ndate: {$date}";

		$signature = '';
		\openssl_sign( $string_to_sign, $signature, $private_key, $algo );

		$request = array(
			'REQUEST_METHOD' => 'POST',
			'REQUEST_URI'    => '/wp-json/activitypub/1.0/inbox',
			'HTTP_HOST'      => 'example.org',
			'HTTP_DATE'      => $date,
			'HTTP_SIGNATURE' => \sprintf(
				'keyId="https://example.com/users/test#main-key",algorithm="hs2019",headers="(request-target) host date",signature="%s"',
				\base64_encode( $signature )
			),
		);

		// Mock the remote key retrieval for this curve.
		\add_filter(
			'pre_get_remote_metadata_by_actor',
			function () use ( $public_key ) {
				return array(
					'name'      => 'Test User',
					'url'       => 'https://example.com/users/test',
					'publicKey' => array(
						'id'           => 'https://example.com/users/test#main-key',
						'owner'        => 'https://example.com/users/test',
						'publicKeyPem' => $public_key,
					),
				);
			}
		);

		$this->assertTrue( Signature::verify_http_signature( $request ), "Valid hs2019 signature for curve {$curve} should verify" );
		\remove_all_filters( 'pre_get_remote_metadata_by_actor' );
	}

	/**
	 * Test invalid hs2019 signatures for EC curves.
	 */
	public function test_invalid_hs2019_signatures_for_ec_curves() {
		$public_key  = self::$test_keys['ec']['prime256v1']['public_key'];
		$private_key = \openssl_pkey_get_private( self::$test_keys['ec']['prime256v1']['private_key'] );

		$date           = \gmdate( 'D, d M Y H:i:s T' );
		$string_to_sign = "(request-target): post /wp-json/activitypub/1.0/inbox\nhost: example.org\ndate: {$date}";

		$signature = '';
		\openssl_sign( $string_to_sign, $signature, $private_key, \OPENSSL_ALGO_SHA256 );

		// Create request with invalid signature (reversed).
		$request = array(
			'REQUEST_METHOD' => 'POST',
			'REQUEST_URI'    => '/wp-json/activitypub/1.0/inbox',
			'HTTP_HOST'      => 'example.org',
			'HTTP_DATE'      => $date,
			'HTTP_SIGNATURE' => \sprintf(
				'keyId="https://example.com/users/test#main-key",algorithm="hs2019",headers="(request-target) host date",signature="%s"',
				\base64_encode( \strrev( $signature ) )
			),
		);

		\add_filter(
			'pre_get_remote_metadata_by_actor',
			function () use ( $public_key ) {
				return array(
					'name'      => 'Test User',
					'url'       => 'https://example.com/users/test',
					'publicKey' => array(
						'id'           => 'https://example.com/users/test#main-key',
						'owner'        => 'https://example.com/users/test',
						'publicKeyPem' => $public_key,
					),
				);
			}
		);
		$this->assertWPError( Signature::verify_http_signature( $request ), 'Invalid hs2019 signature for curve prime256v1 should fail' );
		\remove_all_filters( 'pre_get_remote_metadata_by_actor' );
	}

	/**
	 * Data provider for RSA key sizes.
	 *
	 * @return array[][] Test data.
	 */
	public function provide_rsa_sizes() {
		return array(
			'RSA 2048' => array( 2048, \OPENSSL_ALGO_SHA256 ),
			'RSA 3072' => array( 3072, \OPENSSL_ALGO_SHA384 ),
			'RSA 4096' => array( 4096, \OPENSSL_ALGO_SHA512 ),
		);
	}

	/**
	 * Test valid hs2019 signatures for RSA keys.
	 *
	 * @dataProvider provide_rsa_sizes
	 * @param int $bits The RSA key size in bits.
	 * @param int $algo The OpenSSL algorithm constant.
	 */
	public function test_valid_hs2019_signatures_for_rsa_sizes( $bits, $algo ) {
		$public_key  = self::$test_keys['rsa'][ $bits ]['public_key'];
		$private_key = \openssl_pkey_get_private( self::$test_keys['rsa'][ $bits ]['private_key'] );

		$date           = \gmdate( 'D, d M Y H:i:s T' );
		$string_to_sign = "(request-target): post /wp-json/activitypub/1.0/inbox\nhost: example.org\ndate: {$date}";

		$signature = '';
		\openssl_sign( $string_to_sign, $signature, $private_key, $algo );

		$request = array(
			'REQUEST_METHOD' => 'POST',
			'REQUEST_URI'    => '/wp-json/activitypub/1.0/inbox',
			'HTTP_HOST'      => 'example.org',
			'HTTP_DATE'      => $date,
			'HTTP_SIGNATURE' => \sprintf(
				'keyId="https://example.com/users/test#main-key",algorithm="hs2019",headers="(request-target) host date",signature="%s"',
				\base64_encode( $signature )
			),
		);

		\add_filter(
			'pre_get_remote_metadata_by_actor',
			function () use ( $public_key ) {
				return array(
					'name'      => 'Test User',
					'url'       => 'https://example.com/users/test',
					'publicKey' => array(
						'id'           => 'https://example.com/users/test#main-key',
						'owner'        => 'https://example.com/users/test',
						'publicKeyPem' => $public_key,
					),
				);
			}
		);
		$this->assertTrue( Signature::verify_http_signature( $request ), "Valid hs2019 signature for RSA {$bits} bits should verify" );
		\remove_all_filters( 'pre_get_remote_metadata_by_actor' );
	}

	/**
	 * Test invalid hs2019 signatures for RSA keys.
	 */
	public function test_invalid_hs2019_signatures_for_rsa_sizes() {
		$public_key  = self::$test_keys['rsa'][2048]['public_key'];
		$private_key = \openssl_pkey_get_private( self::$test_keys['rsa'][2048]['private_key'] );

		$date           = \gmdate( 'D, d M Y H:i:s T' );
		$string_to_sign = "(request-target): post /wp-json/activitypub/1.0/inbox\nhost: example.org\ndate: {$date}";

		$signature = '';
		\openssl_sign( $string_to_sign, $signature, $private_key, \OPENSSL_ALGO_SHA256 );

		// Create request with invalid signature (reversed).
		$request = array(
			'REQUEST_METHOD' => 'POST',
			'REQUEST_URI'    => '/wp-json/activitypub/1.0/inbox',
			'HTTP_HOST'      => 'example.org',
			'HTTP_DATE'      => $date,
			'HTTP_SIGNATURE' => \sprintf(
				'keyId="https://example.com/users/test#main-key",algorithm="hs2019",headers="(request-target) host date",signature="%s"',
				\base64_encode( \strrev( $signature ) )
			),
		);

		\add_filter(
			'pre_get_remote_metadata_by_actor',
			function () use ( $public_key ) {
				return array(
					'name'      => 'Test User',
					'url'       => 'https://example.com/users/test',
					'publicKey' => array(
						'id'           => 'https://example.com/users/test#main-key',
						'owner'        => 'https://example.com/users/test',
						'publicKeyPem' => $public_key,
					),
				);
			}
		);
		$this->assertWPError( Signature::verify_http_signature( $request ), 'Invalid hs2019 signature for RSA 2048 bits should fail' );
		\remove_all_filters( 'pre_get_remote_metadata_by_actor' );
	}

	/**
	 * Test unsupported EC curve for hs2019.
	 */
	public function test_unsupported_ec_curve_for_hs2019() {
		$public_key  = self::$test_keys['ec']['secp256k1']['public_key'];
		$private_key = \openssl_pkey_get_private( self::$test_keys['ec']['secp256k1']['private_key'] );
		$algo        = self::$test_keys['ec']['secp256k1']['algo'];

		$date           = \gmdate( 'D, d M Y H:i:s T' );
		$string_to_sign = "(request-target): post /wp-json/activitypub/1.0/inbox\nhost: example.org\ndate: {$date}";

		$signature = '';
		\openssl_sign( $string_to_sign, $signature, $private_key, $algo );

		$request = array(
			'REQUEST_METHOD' => 'POST',
			'REQUEST_URI'    => '/wp-json/activitypub/1.0/inbox',
			'HTTP_HOST'      => 'example.org',
			'HTTP_DATE'      => $date,
			'HTTP_SIGNATURE' => \sprintf(
				'keyId="https://example.com/users/test#main-key",algorithm="hs2019",headers="(request-target) host date",signature="%s"',
				\base64_encode( $signature )
			),
		);
		\add_filter(
			'pre_get_remote_metadata_by_actor',
			function () use ( $public_key ) {
				return array(
					'name'      => 'Test User',
					'url'       => 'https://example.com/users/test',
					'publicKey' => array(
						'id'           => 'https://example.com/users/test#main-key',
						'owner'        => 'https://example.com/users/test',
						'publicKeyPem' => $public_key,
					),
				);
			}
		);
		$this->assertWPError( Signature::verify_http_signature( $request ), 'Unsupported EC curve secp256k1 should fail' );
		\remove_all_filters( 'pre_get_remote_metadata_by_actor' );
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
