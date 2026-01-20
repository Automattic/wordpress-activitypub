<?php
/**
 * Test file for Activitypub Signature.
 *
 * @package Activitypub
 */

// phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode, WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

namespace Activitypub\Tests;

use Activitypub\Collection\Actors;
use Activitypub\Http;
use Activitypub\Signature;
use Activitypub\Signature\Http_Message_Signature;

/**
 * Test class for Signature.
 *
 * @coversDefaultClass \Activitypub\Signature
 */
class Test_Signature extends \WP_UnitTestCase {
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
		self::$test_keys = \json_decode( \file_get_contents( AP_TESTS_DIR . '/data/fixtures/http-signature-keys.json' ), true );
	}

	/**
	 * Tear down.
	 */
	public function tear_down() {
		$this->reset__SERVER();

		parent::tear_down();
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
		$mock_remote_key_retrieval = function () use ( $public_key ) {
			return array(
				'name'      => 'Test User',
				'url'       => 'https://example.com/users/test',
				'publicKey' => array(
					'id'           => 'https://example.com/users/test#main-key',
					'owner'        => 'https://example.com/users/test',
					'publicKeyPem' => $public_key,
				),
			);
		};
		\add_filter( 'activitypub_pre_http_get_remote_object', $mock_remote_key_retrieval );

		$this->assertTrue( Signature::verify_http_signature( $request ), "Valid hs2019 signature for curve {$curve} should verify" );
		\remove_filter( 'activitypub_pre_http_get_remote_object', $mock_remote_key_retrieval );
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

		$mock_remote_key_retrieval = function () use ( $public_key ) {
			return array(
				'name'      => 'Test User',
				'url'       => 'https://example.com/users/test',
				'publicKey' => array(
					'id'           => 'https://example.com/users/test#main-key',
					'owner'        => 'https://example.com/users/test',
					'publicKeyPem' => $public_key,
				),
			);
		};
		\add_filter( 'activitypub_pre_http_get_remote_object', $mock_remote_key_retrieval );
		$this->assertWPError( Signature::verify_http_signature( $request ), 'Invalid hs2019 signature for curve prime256v1 should fail' );
		\remove_filter( 'activitypub_pre_http_get_remote_object', $mock_remote_key_retrieval );
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

		$mock_remote_key_retrieval = function () use ( $public_key ) {
			return array(
				'name'      => 'Test User',
				'url'       => 'https://example.com/users/test',
				'publicKey' => array(
					'id'           => 'https://example.com/users/test#main-key',
					'owner'        => 'https://example.com/users/test',
					'publicKeyPem' => $public_key,
				),
			);
		};
		\add_filter( 'activitypub_pre_http_get_remote_object', $mock_remote_key_retrieval );
		$this->assertTrue( Signature::verify_http_signature( $request ), "Valid hs2019 signature for RSA {$bits} bits should verify" );
		\remove_filter( 'activitypub_pre_http_get_remote_object', $mock_remote_key_retrieval );
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

		$mock_remote_key_retrieval = function () use ( $public_key ) {
			return array(
				'name'      => 'Test User',
				'url'       => 'https://example.com/users/test',
				'publicKey' => array(
					'id'           => 'https://example.com/users/test#main-key',
					'owner'        => 'https://example.com/users/test',
					'publicKeyPem' => $public_key,
				),
			);
		};
		\add_filter( 'activitypub_pre_http_get_remote_object', $mock_remote_key_retrieval );
		$this->assertWPError( Signature::verify_http_signature( $request ), 'Invalid hs2019 signature for RSA 2048 bits should fail' );
		\remove_filter( 'activitypub_pre_http_get_remote_object', $mock_remote_key_retrieval );
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

		$mock_remote_key_retrieval = function () use ( $public_key ) {
			return array(
				'name'      => 'Test User',
				'url'       => 'https://example.com/users/test',
				'publicKey' => array(
					'id'           => 'https://example.com/users/test#main-key',
					'owner'        => 'https://example.com/users/test',
					'publicKeyPem' => $public_key,
				),
			);
		};
		\add_filter( 'activitypub_pre_http_get_remote_object', $mock_remote_key_retrieval );

		$this->assertWPError( Signature::verify_http_signature( $request ), 'Unsupported EC curve secp256k1 should fail' );

		\remove_filter( 'activitypub_pre_http_get_remote_object', $mock_remote_key_retrieval );
	}

	/**
	 * Test HTTP signature verification with digest.
	 *
	 * @covers ::verify_http_signature
	 */
	public function test_verify_http_signature_with_digest() {
		// Create a user and get their keypair.
		$keys = Actors::get_keypair( 1 );

		$mock_remote_key_retrieval = function () use ( $keys ) {
			return array(
				'name'      => 'Admin',
				'url'       => 'https://example.org/author/admin',
				'publicKey' => array(
					'id'           => 'https://example.org/author/admin#main-key',
					'owner'        => 'https://example.org/author/admin',
					'publicKeyPem' => $keys['public_key'],
				),
			);
		};
		\add_filter( 'activitypub_pre_http_get_remote_object', $mock_remote_key_retrieval );

		$args = \apply_filters(
			'http_request_args',
			array(
				'method'      => 'POST',
				'body'        => '{"type":"Create","actor":"https://example.org/author/admin","object":{"type":"Note","content":"Test content."}}',
				'key_id'      => 'https://example.org/author/admin#main-key',
				'private_key' => Actors::get_private_key( 1 ),
				'user_id'     => 1,
				'headers'     => array(
					'Content-Type' => 'application/activity+json',
					'Date'         => \gmdate( 'D, d M Y H:i:s T' ),
					'Host'         => 'example.org',
				),
			),
			'https://example.org/wp-json/activitypub/1.0/inbox'
		);

		$request = new \WP_REST_Request( 'POST', ACTIVITYPUB_REST_NAMESPACE . '/inbox' );
		$request->set_body( $args['body'] );
		$request->set_headers( $args['headers'] );

		$this->assertTrue( Signature::verify_http_signature( $request ) );

		// Create a request with a modified body but the original digest.
		$request->set_body( '{"type":"Create","actor":"https://example.org/author/admin","object":{"type":"Note","content":"Modified content."}}' );

		// The verification should fail with a WP_Error.
		$result = Signature::verify_http_signature( $request );
		$this->assertWPError( $result );
		$this->assertEquals( 'digest_mismatch', $result->get_error_code() );

		// Request array without body.
		$request = array(
			'REQUEST_METHOD' => 'POST',
			'REQUEST_URI'    => '/wp-json/activitypub/1.0/inbox',
			'HTTP_HOST'      => 'example.org',
			'HTTP_DATE'      => $args['headers']['Date'],
			'HTTP_DIGEST'    => $args['headers']['Digest'],
			'HTTP_SIGNATURE' => $args['headers']['Signature'],
		);

		$this->assertTrue( Signature::verify_http_signature( $request ) );

		\remove_filter( 'activitypub_pre_http_get_remote_object', $mock_remote_key_retrieval );
	}

	/**
	 * Test HTTP signature verification with RFC-9421 compliant signatures.
	 *
	 * @covers ::verify_http_signature
	 * @covers \Activitypub\Signature\Http_Message_Signature::verify
	 * @covers \Activitypub\Signature\Http_Message_Signature::parse_signature_labels
	 * @covers \Activitypub\Signature\Http_Message_Signature::verify_signature_label
	 * @covers \Activitypub\Signature\Http_Message_Signature::verify_content_digest
	 * @covers \Activitypub\Signature\Http_Message_Signature::get_signature_base_string
	 */
	public function test_verify_http_signature_rfc9421() {
		\update_option( 'activitypub_rfc9421_signature', '1' );
		$keys = self::$test_keys['rsa']['4096'];

		$mock_remote_key_retrieval = function () use ( $keys ) {
			return array(
				'name'      => 'Admin',
				'url'       => 'https://example.org/author/admin',
				'publicKey' => array(
					'id'           => 'https://example.org/author/admin#main-key',
					'owner'        => 'https://example.org/author/admin',
					'publicKeyPem' => $keys['public_key'],
				),
			);
		};
		\add_filter( 'activitypub_pre_http_get_remote_object', $mock_remote_key_retrieval );

		$args = \apply_filters(
			'http_request_args',
			array(
				'method'      => 'POST',
				'body'        => '{"type":"Create","actor":"https://example.org/author/admin","object":{"type":"Note","content":"Test content."}}',
				'headers'     => array(
					'Date' => \gmdate( 'D, d M Y H:i:s T' ),
					'Host' => 'example.org',
				),
				'key_id'      => 'https://example.org/author/admin#main-key',
				'private_key' => \openssl_pkey_get_private( $keys['private_key'] ),
				'user_id'     => 1,
			),
			'https://example.org/wp-json/activitypub/1.0/inbox'
		);

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['REQUEST_URI']    = '/wp-json/activitypub/1.0/inbox';
		$_SERVER['HTTP_HOST']      = 'example.org';
		$_SERVER['HTTPS']          = 'on';

		// Create a REST request with RFC-9421 signature headers.
		$request = new \WP_REST_Request( 'POST', ACTIVITYPUB_REST_NAMESPACE . '/inbox' );
		$request->set_body( $args['body'] );
		$request->set_headers( $args['headers'] );

		// The verification should succeed.
		$this->assertTrue( Signature::verify_http_signature( $request ) );

		// Create a request with a modified body but the original digest.
		$request->set_body( '{"type":"Create","actor":"https://example.org/author/admin","object":{"type":"Note","content":"Modified content."}}' );

		// The verification should fail with a WP_Error.
		$result = Signature::verify_http_signature( $request );
		$this->assertWPError( $result );
		$this->assertEquals( 'digest_mismatch', $result->get_error_code() );

		// Request array without body.
		$request = array(
			'REQUEST_METHOD'       => 'POST',
			'REQUEST_URI'          => '/' . \rest_get_url_prefix() . '/' . ACTIVITYPUB_REST_NAMESPACE . '/inbox',
			'HTTP_HOST'            => 'example.org',
			'HTTP_DATE'            => $args['headers']['Date'],
			'HTTP_CONTENT_DIGEST'  => $args['headers']['Content-Digest'],
			'HTTP_SIGNATURE_INPUT' => $args['headers']['Signature-Input'],
			'HTTP_SIGNATURE'       => $args['headers']['Signature'],
		);

		// The verification should succeed.
		$this->assertTrue( Signature::verify_http_signature( $request ) );

		\remove_filter( 'activitypub_pre_http_get_remote_object', $mock_remote_key_retrieval );
		\delete_option( 'activitypub_rfc9421_signature' );
	}

	/**
	 * Test double knock with unrelated requests.
	 *
	 * @covers ::maybe_double_knock
	 */
	public function test_double_knock_with_unrelated_requests() {
		\update_option( 'activitypub_rfc9421_signature', '1' );

		add_filter(
			'pre_http_request',
			function ( $response, $parsed_args, $url ) {
				if ( 'https://example.org/wp-json/activitypub/1.0/inbox' === $url ) {
					\wp_safe_remote_get( 'https://example.org/wp-json/activitypub/1.0/actors/0/inbox' );
				}

				$response = array(
					'headers'  => array(),
					'body'     => '',
					'response' => array(
						'code'    => 401,
						'message' => 'Unauthorized',
					),
				);

				return apply_filters( 'http_response', $response, $parsed_args, $url );
			},
			10,
			3
		);

		// This should not throw an error.
		$this->expectNotToPerformAssertions();
		Http::get( 'https://example.org/wp-json/activitypub/1.0/inbox' );

		\delete_option( 'activitypub_rfc9421_signature' );
	}

	/**
	 * Test HTTP signature verification with RFC-9421 compliant signatures using GET requests.
	 *
	 * @covers ::verify_http_signature
	 * @covers \Activitypub\Signature\Http_Message_Signature::verify
	 * @covers \Activitypub\Signature\Http_Message_Signature::parse_signature_labels
	 * @covers \Activitypub\Signature\Http_Message_Signature::verify_signature_label
	 * @covers \Activitypub\Signature\Http_Message_Signature::verify_content_digest
	 * @covers \Activitypub\Signature\Http_Message_Signature::get_signature_base_string
	 */
	public function test_verify_http_signature_rfc9421_get_request() {
		$keys = self::$test_keys['rsa']['2048'];

		$mock_remote_key_retrieval = function () use ( $keys ) {
			return array(
				'name'      => 'Admin',
				'url'       => 'https://example.org/author/admin',
				'publicKey' => array(
					'id'           => 'https://example.org/author/admin#main-key',
					'owner'        => 'https://example.org/author/admin',
					'publicKeyPem' => $keys['public_key'],
				),
			);
		};
		\add_filter( 'activitypub_pre_http_get_remote_object', $mock_remote_key_retrieval );

		// Create a date for the request.
		$date = \gmdate( 'D, d M Y H:i:s T' );

		// Create the signature input components.
		$components    = array( '@method', '@target-uri', '@authority', '@query-param";name="per_page', '@query-param";name="page', '@query-param";name="context', 'date' );
		$params_string = \sprintf(
			'(%s);created=%d;keyid="https://example.org/author/admin#main-key";alg="rsa-v1_5-sha256"',
			'"' . \implode( '" "', $components ) . '"',
			\time()
		);

		// Create the signature input header value (includes the label).
		$signature_input = "get-query=$params_string";

		// Generate a signature using the RFC-9421 format.
		$signature_base  = "\"@method\": GET\n";
		$signature_base .= "\"@target-uri\": https://example.org/wp-json/activitypub/1.0/actors/1/outbox?per_page=1&page=2&context=\n";
		$signature_base .= "\"@authority\": example.org\n";
		$signature_base .= "\"@query-param\";name=\"per_page\": 1\n";
		$signature_base .= "\"@query-param\";name=\"page\": 2\n";
		$signature_base .= "\"@query-param\";name=\"context\": \n"; // Empty parameter.
		$signature_base .= "\"date\": $date\n";
		$signature_base .= "\"@signature-params\": $params_string";

		// Sign the signature base.
		$private_key     = \openssl_pkey_get_private( $keys['private_key'] );
		$signature_value = '';
		\openssl_sign( $signature_base, $signature_value, $private_key, \OPENSSL_ALGO_SHA256 );
		$signature_value = \base64_encode( $signature_value );

		// Create the signature header.
		$signature_header = "get-query=:$signature_value:";

		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_SERVER['REQUEST_URI']    = '/' . \rest_get_url_prefix() . '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/1/outbox?per_page=1&page=2&context=';
		$_SERVER['HTTP_HOST']      = 'example.org';
		$_SERVER['HTTPS']          = 'on';

		// Create a REST request with RFC-9421 signature headers.
		$request = new \WP_REST_Request( 'GET', ACTIVITYPUB_REST_NAMESPACE . '/actors/1/outbox?per_page=1&page=2&context=' );
		$request->set_header( 'Date', $date );
		$request->set_header( 'Host', 'example.org' );
		$request->set_header( 'Signature-Input', $signature_input );
		$request->set_header( 'Signature', $signature_header );

		// The verification should succeed.
		$this->assertTrue( Signature::verify_http_signature( $request ) );

		\remove_filter( 'activitypub_pre_http_get_remote_object', $mock_remote_key_retrieval );
	}

	/**
	 * Test HTTP signature verification with RFC-9421 compliant signatures using different algorithms.
	 *
	 * @covers ::verify_http_signature
	 * @covers \Activitypub\Signature\Http_Message_Signature::verify
	 * @covers \Activitypub\Signature\Http_Message_Signature::parse_signature_labels
	 * @covers \Activitypub\Signature\Http_Message_Signature::verify_signature_label
	 * @covers \Activitypub\Signature\Http_Message_Signature::verify_content_digest
	 * @covers \Activitypub\Signature\Http_Message_Signature::get_signature_base_string
	 */
	public function test_verify_http_signature_rfc9421_algorithms() {
		// Test with RSA keys.
		$rsa_keys = self::$test_keys['rsa']['2048'];
		$this->verify_rfc9421_signature_with_keys( $rsa_keys, 'rsa-v1_5-sha256' );

		$rsa_keys = self::$test_keys['rsa']['2048'];
		$this->verify_rfc9421_signature_with_keys( $rsa_keys, '' );

		// Test with EC keys.
		$ec_keys = self::$test_keys['ec']['prime256v1'];
		$this->verify_rfc9421_signature_with_keys( $ec_keys, 'ecdsa-p256-sha256' );
	}

	/**
	 * Helper method to verify RFC-9421 signatures with different key types.
	 *
	 * @param array  $keys      The keypair to use for signing.
	 * @param string $algorithm The signature algorithm to use.
	 */
	private function verify_rfc9421_signature_with_keys( $keys, $algorithm ) {
		$mock_remote_key_retrieval = function () use ( $keys ) {
			return array(
				'name'      => 'Admin',
				'url'       => 'https://example.org/author/admin',
				'publicKey' => array(
					'id'           => 'https://example.org/author/admin#main-key',
					'owner'        => 'https://example.org/author/admin',
					'publicKeyPem' => $keys['public_key'],
				),
			);
		};
		\add_filter( 'activitypub_pre_http_get_remote_object', $mock_remote_key_retrieval );

		// Create a request body.
		$body = '{"type":"Create","actor":"https://example.org/author/admin","object":{"type":"Note","content":"Test content."}}';

		// Generate a digest for the body.
		$digest = 'sha-256=:' . \base64_encode( \hash( 'sha256', $body, true ) ) . ':';

		// Create a date for the request.
		$date = \gmdate( 'D, d M Y H:i:s T' );

		// Create the signature input components.
		$components    = array( '@method', '@target-uri', '@authority', 'content-digest', 'date' );
		$params_string = \sprintf(
			'(%s);created=%d;keyid="https://example.org/author/admin#main-key"',
			'"' . \implode( '" "', $components ) . '"',
			\time()
		);

		if ( ! empty( $algorithm ) ) {
			$params_string .= ';alg="' . $algorithm . '"';
		}

		// Create the signature input header value (includes the label).
		$signature_input = "sig1=$params_string";

		// Generate a signature using the RFC-9421 format.
		$signature_base  = "\"@method\": POST\n";
		$signature_base .= "\"@target-uri\": https://example.org/wp-json/activitypub/1.0/inbox\n";
		$signature_base .= "\"@authority\": example.org\n";
		$signature_base .= "\"content-digest\": $digest\n";
		$signature_base .= "\"date\": $date\n";
		$signature_base .= "\"@signature-params\": $params_string";

		// Sign the signature base.
		$private_key     = \openssl_pkey_get_private( $keys['private_key'] );
		$signature_value = '';
		$openssl_algo    = OPENSSL_ALGO_SHA256;
		\openssl_sign( $signature_base, $signature_value, $private_key, $openssl_algo );
		$signature_value = \base64_encode( $signature_value );

		// Create the signature header.
		$signature_header = "sig1=:$signature_value:";

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['REQUEST_URI']    = '/' . \rest_get_url_prefix() . '/' . ACTIVITYPUB_REST_NAMESPACE . '/inbox';
		$_SERVER['HTTP_HOST']      = 'example.org';
		$_SERVER['HTTPS']          = 'on';

		// Create a REST request with RFC-9421 signature headers.
		$request = new \WP_REST_Request( 'POST', ACTIVITYPUB_REST_NAMESPACE . '/inbox' );
		$request->set_body( $body );
		$request->set_header( 'Date', $date );
		$request->set_header( 'Content-Digest', $digest );
		$request->set_header( 'Host', 'example.org' );
		$request->set_header( 'Signature-Input', $signature_input );
		$request->set_header( 'Signature', $signature_header );

		// The verification should succeed.
		$this->assertTrue( Signature::verify_http_signature( $request ) );

		\remove_filter( 'activitypub_pre_http_get_remote_object', $mock_remote_key_retrieval );
	}

	/**
	 * Test RFC-9421 signature verification when it is unsupported.
	 *
	 * @covers ::could_support_rfc9421
	 */
	public function test_rfc9421_is_unsupported() {
		\add_option( 'activitypub_rfc9421_unsupported', array( 'sub.www.example.org' => \time() + MINUTE_IN_SECONDS ), '', false );
		\update_option( 'activitypub_rfc9421_signature', '1' );

		$test = function ( $args ) {
			$this->assertFalse( isset( $args['headers']['Signature-Input'] ) );
			$this->assertStringContainsString( 'headers="(request-target) host date digest"', $args['headers']['Signature'] );

			return $args;
		};

		\add_filter( 'pre_http_request', '__return_null' );
		\add_filter( 'http_request_args', $test );

		Http::post( 'https://sub.www.example.org/wp-json/activitypub/1.0/inbox', '{"type":"Create","actor":"https://example.org/author/admin","object":{"type":"Note","content":"Test content."}}', 1 );

		// Expired timestamp results in another try.
		\update_option( 'activitypub_rfc9421_unsupported', array( 'sub.www.example.org' => \time() - MINUTE_IN_SECONDS ), '', false );
		\remove_filter( 'http_request_args', $test );

		$test = function ( $args ) {
			$this->assertTrue( isset( $args['headers']['Signature-Input'] ) );
			$this->assertStringStartsWith( 'wp=:', $args['headers']['Signature'] );

			return $args;
		};
		\add_filter( 'http_request_args', $test );

		Http::post( 'https://sub.www.example.org/wp-json/activitypub/1.0/inbox', '{"type":"Create","actor":"https://example.org/author/admin","object":{"type":"Note","content":"Test content."}}', 1 );

		$this->assertEmpty( \get_option( 'activitypub_rfc9421_unsupported' ) );

		// Cleanup.
		\delete_option( 'activitypub_rfc9421_unsupported' );
		\delete_option( 'activitypub_rfc9421_signature' );
		\remove_filter( 'pre_http_request', '__return_null' );
		\remove_filter( 'http_request_args', $test );
	}

	/**
	 * Test RFC-9421 signature verification when it is unsupported.
	 *
	 * @covers ::rfc9421_add_unsupported_host
	 */
	public function test_set_rfc9421_unsupported() {
		\update_option( 'activitypub_rfc9421_signature', '1' );
		$url = 'https://example.org/wp-json/activitypub/1.0/inbox';

		// Test domain is not unsupported.
		$could_support_rfc9421 = new \ReflectionMethod( Signature::class, 'could_support_rfc9421' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$could_support_rfc9421->setAccessible( true );
		}
		$this->assertTrue( $could_support_rfc9421->invoke( null, $url ) );

		$mock_callback = function ( $response, $args, $url ) {
			$response = array(
				'headers'  => array(),
				'body'     => '',
				'response' => array(
					'code'    => 200,
					'message' => 'OK',
				),
			);

			if ( isset( $args['headers']['Signature-Input'] ) ) {
				$response['response'] = array(
					'code'    => 401,
					'message' => 'Unauthorized',
				);
			}

			return \apply_filters( 'http_response', $response, $args, $url );
		};
		\add_filter( 'pre_http_request', $mock_callback, 10, 3 );

		Http::post( $url, '{"type":"Create","actor":"https://example.org/author/admin","object":{"type":"Note","content":"Test content."}}', 1 );

		// Domain is set as unsupported.
		$this->assertFalse( $could_support_rfc9421->invoke( null, $url ) );

		// Cleanup.
		\delete_option( 'activitypub_rfc9421_signature' );
		\remove_filter( 'pre_http_request', $mock_callback );
	}

	/**
	 * Test Ed25519 signature verification via activitypub_pre_get_public_key filter.
	 *
	 * @covers ::verify_http_signature
	 * @covers \Activitypub\Signature\Http_Message_Signature::verify_ed25519_signature
	 */
	public function test_ed25519_signature_verification() {
		// Generate Ed25519 keypair.
		$keypair     = \sodium_crypto_sign_keypair();
		$public_key  = \sodium_crypto_sign_publickey( $keypair );
		$private_key = \sodium_crypto_sign_secretkey( $keypair );

		// Create signature base string.
		$date            = \gmdate( 'D, d M Y H:i:s T' );
		$created         = \time();
		$params_string   = \sprintf(
			'("@method" "@target-uri" "date");created=%d;keyid="test-fasp-server-id"',
			$created
		);
		$signature_base  = "\"@method\": POST\n";
		$signature_base .= "\"@target-uri\": https://example.org/wp-json/activitypub/1.0/fasp/capabilities/test/1/activation\n";
		$signature_base .= "\"date\": $date\n";
		$signature_base .= "\"@signature-params\": $params_string";

		// Sign with Ed25519.
		$signature = \sodium_crypto_sign_detached( $signature_base, $private_key );

		// Create signature headers.
		$signature_input  = "sig=$params_string";
		$signature_header = 'sig=:' . \base64_encode( $signature ) . ':';

		// Mock the public key retrieval to return Ed25519 key.
		$mock_ed25519_key = function ( $key, $key_id ) use ( $public_key ) {
			if ( 'test-fasp-server-id' === $key_id ) {
				return array(
					'type' => 'ed25519',
					'key'  => $public_key,
				);
			}
			return $key;
		};
		\add_filter( 'activitypub_pre_get_public_key', $mock_ed25519_key, 10, 2 );

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['REQUEST_URI']    = '/' . \rest_get_url_prefix() . '/' . ACTIVITYPUB_REST_NAMESPACE . '/fasp/capabilities/test/1/activation';
		$_SERVER['HTTP_HOST']      = 'example.org';
		$_SERVER['HTTPS']          = 'on';

		// Create REST request with Ed25519 signature.
		$request = new \WP_REST_Request( 'POST', ACTIVITYPUB_REST_NAMESPACE . '/fasp/capabilities/test/1/activation' );
		$request->set_header( 'Date', $date );
		$request->set_header( 'Host', 'example.org' );
		$request->set_header( 'Signature-Input', $signature_input );
		$request->set_header( 'Signature', $signature_header );

		// Verification should succeed.
		$result = Signature::verify_http_signature( $request );
		$this->assertTrue( $result, 'Valid Ed25519 signature should verify' );

		\remove_filter( 'activitypub_pre_get_public_key', $mock_ed25519_key );
	}

	/**
	 * Test Ed25519 signature verification fails with invalid signature.
	 *
	 * @covers ::verify_http_signature
	 * @covers \Activitypub\Signature\Http_Message_Signature::verify_ed25519_signature
	 */
	public function test_ed25519_invalid_signature_fails() {
		// Generate Ed25519 keypair.
		$keypair    = \sodium_crypto_sign_keypair();
		$public_key = \sodium_crypto_sign_publickey( $keypair );

		// Create a different keypair to sign with (simulates wrong key).
		$wrong_keypair    = \sodium_crypto_sign_keypair();
		$wrong_secret_key = \sodium_crypto_sign_secretkey( $wrong_keypair );

		// Create signature base string.
		$date            = \gmdate( 'D, d M Y H:i:s T' );
		$created         = \time();
		$params_string   = \sprintf(
			'("@method" "@target-uri" "date");created=%d;keyid="test-fasp-server-id"',
			$created
		);
		$signature_base  = "\"@method\": POST\n";
		$signature_base .= "\"@target-uri\": https://example.org/wp-json/activitypub/1.0/fasp/capabilities/test/1/activation\n";
		$signature_base .= "\"date\": $date\n";
		$signature_base .= "\"@signature-params\": $params_string";

		// Sign with WRONG key.
		$signature = \sodium_crypto_sign_detached( $signature_base, $wrong_secret_key );

		// Create signature headers.
		$signature_input  = "sig=$params_string";
		$signature_header = 'sig=:' . \base64_encode( $signature ) . ':';

		// Mock the public key retrieval to return the CORRECT public key.
		$mock_ed25519_key = function ( $key, $key_id ) use ( $public_key ) {
			if ( 'test-fasp-server-id' === $key_id ) {
				return array(
					'type' => 'ed25519',
					'key'  => $public_key,
				);
			}
			return $key;
		};
		\add_filter( 'activitypub_pre_get_public_key', $mock_ed25519_key, 10, 2 );

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['REQUEST_URI']    = '/' . \rest_get_url_prefix() . '/' . ACTIVITYPUB_REST_NAMESPACE . '/fasp/capabilities/test/1/activation';
		$_SERVER['HTTP_HOST']      = 'example.org';
		$_SERVER['HTTPS']          = 'on';

		// Create REST request with Ed25519 signature (signed with wrong key).
		$request = new \WP_REST_Request( 'POST', ACTIVITYPUB_REST_NAMESPACE . '/fasp/capabilities/test/1/activation' );
		$request->set_header( 'Date', $date );
		$request->set_header( 'Host', 'example.org' );
		$request->set_header( 'Signature-Input', $signature_input );
		$request->set_header( 'Signature', $signature_header );

		// Verification should fail.
		$result = Signature::verify_http_signature( $request );
		$this->assertWPError( $result, 'Invalid Ed25519 signature should fail verification' );
		$this->assertEquals( 'activitypub_signature', $result->get_error_code() );

		\remove_filter( 'activitypub_pre_get_public_key', $mock_ed25519_key );
	}

	/**
	 * Test Ed25519 signature verification fails with invalid key length.
	 *
	 * @covers ::verify_http_signature
	 * @covers \Activitypub\Signature\Http_Message_Signature::verify_ed25519_signature
	 */
	public function test_ed25519_invalid_key_length_fails() {
		// Create signature headers with dummy values.
		$date             = \gmdate( 'D, d M Y H:i:s T' );
		$created          = \time();
		$params_string    = \sprintf(
			'("@method" "@target-uri" "date");created=%d;keyid="test-fasp-server-id"',
			$created
		);
		$signature_input  = "sig=$params_string";
		$signature_header = 'sig=:' . \base64_encode( \str_repeat( 'x', 64 ) ) . ':'; // 64 bytes for signature.

		// Mock the public key retrieval to return an invalid length key.
		$mock_invalid_key = function ( $key, $key_id ) {
			if ( 'test-fasp-server-id' === $key_id ) {
				return array(
					'type' => 'ed25519',
					'key'  => 'too-short', // Invalid key length.
				);
			}
			return $key;
		};
		\add_filter( 'activitypub_pre_get_public_key', $mock_invalid_key, 10, 2 );

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['REQUEST_URI']    = '/' . \rest_get_url_prefix() . '/' . ACTIVITYPUB_REST_NAMESPACE . '/fasp/capabilities/test/1/activation';
		$_SERVER['HTTP_HOST']      = 'example.org';
		$_SERVER['HTTPS']          = 'on';

		// Create REST request.
		$request = new \WP_REST_Request( 'POST', ACTIVITYPUB_REST_NAMESPACE . '/fasp/capabilities/test/1/activation' );
		$request->set_header( 'Date', $date );
		$request->set_header( 'Host', 'example.org' );
		$request->set_header( 'Signature-Input', $signature_input );
		$request->set_header( 'Signature', $signature_header );

		// Verification should fail due to invalid key length.
		$result = Signature::verify_http_signature( $request );
		$this->assertWPError( $result, 'Invalid Ed25519 key length should fail verification' );
		$this->assertEquals( 'invalid_key_length', $result->get_error_code() );

		\remove_filter( 'activitypub_pre_get_public_key', $mock_invalid_key );
	}

	/**
	 * Test server Ed25519 keypair generation and retrieval.
	 *
	 * @covers Activitypub\Signature::get_server_ed25519_keypair
	 * @covers Activitypub\Signature::get_server_ed25519_public_key
	 */
	public function test_server_ed25519_keypair() {
		// Clear any existing keypair.
		\delete_option( 'activitypub_server_ed25519_keypair' );

		// Get keypair should generate one.
		$keypair = Signature::get_server_ed25519_keypair();

		$this->assertIsArray( $keypair );
		$this->assertArrayHasKey( 'public', $keypair );
		$this->assertArrayHasKey( 'private', $keypair );

		// Verify key lengths (Ed25519: 32 bytes public, 64 bytes private).
		$this->assertEquals( SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES, \strlen( $keypair['public'] ) );
		$this->assertEquals( SODIUM_CRYPTO_SIGN_SECRETKEYBYTES, \strlen( $keypair['private'] ) );

		// Get public key should return base64.
		$public_key_b64 = Signature::get_server_ed25519_public_key();
		$this->assertIsString( $public_key_b64 );
		$this->assertEquals( $keypair['public'], \base64_decode( $public_key_b64 ) );

		// Subsequent calls should return the same keypair.
		$keypair2 = Signature::get_server_ed25519_keypair();
		$this->assertEquals( $keypair['public'], $keypair2['public'] );
		$this->assertEquals( $keypair['private'], $keypair2['private'] );
	}

	/**
	 * Test Ed25519 response signing.
	 *
	 * @covers Activitypub\Signature\Http_Message_Signature::sign_response_ed25519
	 */
	public function test_ed25519_response_signing() {
		// Generate keypair.
		$keypair     = \sodium_crypto_sign_keypair();
		$public_key  = \sodium_crypto_sign_publickey( $keypair );
		$private_key = \sodium_crypto_sign_secretkey( $keypair );

		// Create a response.
		$response = new \WP_REST_Response( array( 'test' => 'data' ), 200 );
		$content  = \wp_json_encode( array( 'test' => 'data' ) );

		// Add content-digest header.
		$signature_helper = new Http_Message_Signature();
		$digest           = $signature_helper->generate_digest( $content );
		$response->header( 'Content-Digest', $digest );

		// Sign the response.
		$signature_helper->sign_response_ed25519(
			$response,
			$private_key,
			'test-key-id',
			'sig'
		);

		// Verify headers were added.
		$headers = $response->get_headers();
		$this->assertArrayHasKey( 'Signature-Input', $headers );
		$this->assertArrayHasKey( 'Signature', $headers );

		// Verify signature format.
		$this->assertStringContainsString( 'sig=', $headers['Signature-Input'] );
		$this->assertStringContainsString( '"@status"', $headers['Signature-Input'] );
		$this->assertStringContainsString( 'alg="ed25519"', $headers['Signature-Input'] );
		$this->assertStringContainsString( 'keyid="test-key-id"', $headers['Signature-Input'] );
		$this->assertStringStartsWith( 'sig=:', $headers['Signature'] );

		// Extract and verify the signature.
		\preg_match( '/sig=:([^:]+):/', $headers['Signature'], $matches );
		$signature = \base64_decode( $matches[1] );
		$this->assertEquals( SODIUM_CRYPTO_SIGN_BYTES, \strlen( $signature ) );

		/*
		 * Verify the signature is valid by reconstructing the signature base.
		 * Extract created timestamp from Signature-Input.
		 */
		\preg_match( '/created=(\d+)/', $headers['Signature-Input'], $created_matches );
		$created = $created_matches[1];

		$signature_base  = "\"@status\": 200\n";
		$signature_base .= "\"content-digest\": {$digest}\n";
		$signature_base .= "\"@signature-params\": (\"@status\" \"content-digest\");created={$created};keyid=\"test-key-id\";alg=\"ed25519\"";

		$this->assertTrue(
			\sodium_crypto_sign_verify_detached( $signature, $signature_base, $public_key ),
			'Ed25519 response signature should be valid'
		);
	}
}
