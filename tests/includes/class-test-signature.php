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
	 * Test HTTP signature verification with digest.
	 *
	 * @covers ::verify_http_signature
	 * @covers ::generate_digest
	 * @covers ::generate_signature
	 */
	public function test_verify_http_signature_with_digest() {
		// Create a user and get their keypair.
		$keys = Actors::get_keypair( 1 );

		\add_filter(
			'pre_get_remote_metadata_by_actor',
			function () use ( $keys ) {
				return array(
					'name'      => 'Admin',
					'url'       => 'https://example.org/author/admin',
					'publicKey' => array(
						'id'           => 'https://example.org/author/admin#main-key',
						'owner'        => 'https://example.org/author/admin',
						'publicKeyPem' => $keys['public_key'],
					),
				);
			}
		);

		// Create a request body.
		$body = '{"type":"Create","actor":"https://example.org/author/admin","object":{"type":"Note","content":"Test content."}}';

		// Generate a digest for the body.
		$this->setExpectedDeprecated( 'Activitypub\Signature::generate_digest' );
		$digest = Signature::generate_digest( $body );

		// Create a date for the request.
		$date = \gmdate( 'D, d M Y H:i:s T' );

		// Generate a signature that includes the digest.
		$this->setExpectedDeprecated( 'Activitypub\Signature::generate_signature' );
		$signature = Signature::generate_signature( 1, 'POST', 'https://example.org/wp-json/activitypub/1.0/inbox', $date, $digest );

		$request = new \WP_REST_Request( 'POST', ACTIVITYPUB_REST_NAMESPACE . '/inbox' );
		$request->set_body( $body );
		$request->set_header( 'Date', $date );
		$request->set_header( 'Digest', $digest );
		$request->set_header( 'Host', 'example.org' );
		$request->set_header( 'Signature', $signature );
		$request->set_header( 'Content-Type', 'application/activity+json' );

		$this->assertTrue( Signature::verify_http_signature( $request ) );

		// Create a request with a modified body but the original digest.
		$request->set_body( '{"type":"Create","actor":"https://example.org/author/admin","object":{"type":"Note","content":"Modified content."}}' );

		// The verification should fail with a WP_Error.
		$result = Signature::verify_http_signature( $request );
		$this->assertWPError( $result );
		$this->assertEquals( 'activitypub_signature', $result->get_error_code() );
		$this->assertEquals( 'Invalid Digest header', $result->get_error_message() );

		// Request array without body.
		$request = array(
			'REQUEST_METHOD' => 'POST',
			'REQUEST_URI'    => '/wp-json/activitypub/1.0/inbox',
			'HTTP_HOST'      => 'example.org',
			'HTTP_DATE'      => $date,
			'HTTP_DIGEST'    => $digest,
			'HTTP_SIGNATURE' => $signature,
		);

		$this->assertTrue( Signature::verify_http_signature( $request ) );

		\remove_all_filters( 'pre_get_remote_metadata_by_actor' );
	}

	/**
	 * Test HTTP signature verification with RFC-9421 compliant signatures.
	 *
	 * @covers ::verify_http_signature
	 * @covers ::generate_digest
	 * @covers ::generate_signature
	 * @covers \Activitypub\Signature\Http_Message_Signature::verify
	 * @covers \Activitypub\Signature\Http_Message_Signature::parse_signature_labels
	 * @covers \Activitypub\Signature\Http_Message_Signature::verify_signature_label
	 * @covers \Activitypub\Signature\Http_Message_Signature::verify_content_digest
	 * @covers \Activitypub\Signature\Http_Message_Signature::resolve_algorithm
	 * @covers \Activitypub\Signature\Http_Message_Signature::get_signature_base_string
	 */
	public function test_verify_http_signature_rfc9421() {
		$keys = self::$test_keys['rsa']['4096'];

		\add_filter(
			'pre_get_remote_metadata_by_actor',
			function () use ( $keys ) {
				return array(
					'name'      => 'Admin',
					'url'       => 'https://example.org/author/admin',
					'publicKey' => array(
						'id'           => 'https://example.org/author/admin#main-key',
						'owner'        => 'https://example.org/author/admin',
						'publicKeyPem' => $keys['public_key'],
					),
				);
			}
		);

		$signature = new Signature\Http_Message_Signature();
		$args      = $signature->sign(
			array(
				'method'      => 'POST',
				'body'        => '{"type":"Create","actor":"https://example.org/author/admin","object":{"type":"Note","content":"Test content."}}',
				'headers'     => array(
					'Date' => \gmdate( 'D, d M Y H:i:s T' ),
					'Host' => 'example.org',
				),
				'key_id'      => 'https://example.org/author/admin#main-key',
				'private_key' => \openssl_pkey_get_private( $keys['private_key'] ),
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

		\remove_all_filters( 'pre_get_remote_metadata_by_actor' );
	}

	/**
	 * Test HTTP signature verification with RFC-9421 compliant signatures using GET requests.
	 *
	 * @covers ::verify_http_signature
	 * @covers ::generate_digest
	 * @covers ::generate_signature
	 * @covers \Activitypub\Signature\Http_Message_Signature::verify
	 * @covers \Activitypub\Signature\Http_Message_Signature::parse_signature_labels
	 * @covers \Activitypub\Signature\Http_Message_Signature::verify_signature_label
	 * @covers \Activitypub\Signature\Http_Message_Signature::verify_content_digest
	 * @covers \Activitypub\Signature\Http_Message_Signature::resolve_algorithm
	 * @covers \Activitypub\Signature\Http_Message_Signature::get_signature_base_string
	 */
	public function test_verify_http_signature_rfc9421_get_request() {
		$keys = self::$test_keys['rsa']['2048'];

		\add_filter(
			'pre_get_remote_metadata_by_actor',
			function () use ( $keys ) {
				return array(
					'name'      => 'Admin',
					'url'       => 'https://example.org/author/admin',
					'publicKey' => array(
						'id'           => 'https://example.org/author/admin#main-key',
						'owner'        => 'https://example.org/author/admin',
						'publicKeyPem' => $keys['public_key'],
					),
				);
			}
		);

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

		\remove_all_filters( 'pre_get_remote_metadata_by_actor' );
	}

	/**
	 * Test HTTP signature verification with RFC-9421 compliant signatures using different algorithms.
	 *
	 * @covers ::verify_http_signature
	 * @covers ::generate_digest
	 * @covers ::generate_signature
	 * @covers \Activitypub\Signature\Http_Message_Signature::verify
	 * @covers \Activitypub\Signature\Http_Message_Signature::parse_signature_labels
	 * @covers \Activitypub\Signature\Http_Message_Signature::verify_signature_label
	 * @covers \Activitypub\Signature\Http_Message_Signature::verify_content_digest
	 * @covers \Activitypub\Signature\Http_Message_Signature::resolve_algorithm
	 * @covers \Activitypub\Signature\Http_Message_Signature::get_signature_base_string
	 */
	public function test_verify_http_signature_rfc9421_algorithms() {
		// Test with RSA keys.
		$rsa_keys = self::$test_keys['rsa']['2048'];
		$this->verify_rfc9421_signature_with_keys( $rsa_keys, 'rsa-v1_5-sha256' );

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
		\add_filter(
			'pre_get_remote_metadata_by_actor',
			function () use ( $keys ) {
				return array(
					'name'      => 'Admin',
					'url'       => 'https://example.org/author/admin',
					'publicKey' => array(
						'id'           => 'https://example.org/author/admin#main-key',
						'owner'        => 'https://example.org/author/admin',
						'publicKeyPem' => $keys['public_key'],
					),
				);
			}
		);

		// Create a request body.
		$body = '{"type":"Create","actor":"https://example.org/author/admin","object":{"type":"Note","content":"Test content."}}';

		// Generate a digest for the body.
		$digest = 'SHA-256=:' . \base64_encode( \hash( 'sha256', $body, true ) ) . ':';

		// Create a date for the request.
		$date = \gmdate( 'D, d M Y H:i:s T' );

		// Create the signature input components.
		$components    = array( '@method', '@target-uri', '@authority', 'content-digest', 'date' );
		$params_string = \sprintf(
			'(%s);created=%d;keyid="https://example.org/author/admin#main-key";alg="%s"',
			'"' . \implode( '" "', $components ) . '"',
			\time(),
			$algorithm
		);

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

		\remove_all_filters( 'pre_get_remote_metadata_by_actor' );
	}
}
