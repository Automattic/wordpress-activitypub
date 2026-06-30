<?php
/**
 * Test file for Activitypub Signature.
 *
 * @package Activitypub
 */

// phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode

namespace Activitypub\Tests;

use Activitypub\Collection\Actors;
use Activitypub\Http;
use Activitypub\Signature;

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

		$this->assertNotWPError( Signature::verify_http_signature( $request ), "Valid hs2019 signature for curve {$curve} should verify" );
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
		$this->assertNotWPError( Signature::verify_http_signature( $request ), "Valid hs2019 signature for RSA {$bits} bits should verify" );
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

		$this->assertNotWPError( Signature::verify_http_signature( $request ) );

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

		$this->assertNotWPError( Signature::verify_http_signature( $request ) );

		\remove_filter( 'activitypub_pre_http_get_remote_object', $mock_remote_key_retrieval );
	}

	/**
	 * Signatures whose Date is outside the clock-skew tolerance must be rejected.
	 *
	 * Asymmetric: up to 5 minutes into the future, up to 1 hour into the past.
	 * Values comfortably outside the window must fail, values comfortably
	 * inside it must verify.
	 *
	 * @covers ::verify_http_signature
	 * @covers \Activitypub\Signature\Http_Signature_Draft::get_signed_data
	 */
	public function test_verify_http_signature_rejects_out_of_window_date() {
		$keys = Actors::get_keypair( 1 );

		// Force Cavage signing regardless of any leaked option from an earlier test.
		$force_cavage = '__return_zero';
		\add_filter( 'pre_option_activitypub_rfc9421_signature', $force_cavage );

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

		$sign = static function ( $date ) {
			$args = \apply_filters(
				'http_request_args',
				array(
					'method'      => 'POST',
					'body'        => '{"type":"Create","actor":"https://example.org/author/admin","object":{"type":"Note","content":"x"}}',
					'key_id'      => 'https://example.org/author/admin#main-key',
					'private_key' => Actors::get_private_key( 1 ),
					'user_id'     => 1,
					'headers'     => array(
						'Content-Type' => 'application/activity+json',
						'Date'         => $date,
						'Host'         => 'example.org',
					),
				),
				'https://example.org/wp-json/activitypub/1.0/inbox'
			);

			$request = new \WP_REST_Request( 'POST', ACTIVITYPUB_REST_NAMESPACE . '/inbox' );
			$request->set_body( $args['body'] );
			$request->set_headers( $args['headers'] );

			return Signature::verify_http_signature( $request );
		};

		$far_past   = \gmdate( 'D, d M Y H:i:s T', \time() - ( 2 * HOUR_IN_SECONDS ) );
		$far_future = \gmdate( 'D, d M Y H:i:s T', \time() + ( 10 * MINUTE_IN_SECONDS ) );
		$within     = \gmdate( 'D, d M Y H:i:s T', \time() - ( 30 * MINUTE_IN_SECONDS ) );

		$this->assertWPError( $sign( $far_past ), 'Signatures more than an hour old must be rejected.' );
		$this->assertWPError( $sign( $far_future ), 'Signatures more than five minutes in the future must be rejected.' );
		$this->assertNotWPError( $sign( $within ), 'Signatures within the skew window must verify.' );

		\remove_filter( 'activitypub_pre_http_get_remote_object', $mock_remote_key_retrieval );
		\remove_filter( 'pre_option_activitypub_rfc9421_signature', $force_cavage );
	}

	/**
	 * A malformed Date header must reject the request gracefully rather than
	 * fatal on `setTimeZone()` of a `false` date object.
	 *
	 * @covers ::verify_http_signature
	 * @covers \Activitypub\Signature\Http_Signature_Draft::get_signed_data
	 */
	public function test_verify_http_signature_rejects_malformed_date() {
		$keys = Actors::get_keypair( 1 );

		$force_cavage = '__return_zero';
		\add_filter( 'pre_option_activitypub_rfc9421_signature', $force_cavage );

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

		/*
		 * Sign with an unparseable Date string. The signing helper drops the
		 * literal value into the signed string without validating it, so the
		 * signature itself stays valid; the verifier then has to handle
		 * date_create() returning false on the same value.
		 */
		$args = \apply_filters(
			'http_request_args',
			array(
				'method'      => 'POST',
				'body'        => '{"type":"Create","actor":"https://example.org/author/admin","object":{"type":"Note","content":"x"}}',
				'key_id'      => 'https://example.org/author/admin#main-key',
				'private_key' => Actors::get_private_key( 1 ),
				'user_id'     => 1,
				'headers'     => array(
					'Content-Type' => 'application/activity+json',
					'Date'         => 'not a real date',
					'Host'         => 'example.org',
				),
			),
			'https://example.org/wp-json/activitypub/1.0/inbox'
		);

		$request = new \WP_REST_Request( 'POST', ACTIVITYPUB_REST_NAMESPACE . '/inbox' );
		$request->set_body( $args['body'] );
		$request->set_headers( $args['headers'] );

		$result = Signature::verify_http_signature( $request );
		$this->assertWPError( $result, 'Malformed Date header must produce a WP_Error, not a fatal.' );
		$this->assertSame( 'invalid_signed_data', $result->get_error_code() );

		\remove_filter( 'activitypub_pre_http_get_remote_object', $mock_remote_key_retrieval );
		\remove_filter( 'pre_option_activitypub_rfc9421_signature', $force_cavage );
	}

	/**
	 * A signed GET request whose URL contains a query string must verify.
	 *
	 * Draft Cavage peers (e.g. Mastodon) sign the full request-target
	 * including the query string. The REST branch of the verifier has to
	 * reconstruct the same value, otherwise endpoints that require query
	 * parameters — like FEP-8fcf's `/followers/sync?authority=…` — always
	 * fail with a 401.
	 *
	 * @covers ::verify_http_signature
	 */
	public function test_verify_http_signature_get_with_query_string() {
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

		/*
		 * Sign the way Mastodon does: the request-target includes the query
		 * string. Forcing Cavage mode is not needed here, the verifier picks
		 * the draft verifier based on the plain `Signature` header.
		 */
		$date           = \gmdate( 'D, d M Y H:i:s T' );
		$target         = '/wp-json/activitypub/1.0/actors/0/followers/sync?authority=https://mastodon.example';
		$string_to_sign = "(request-target): get {$target}\nhost: example.org\ndate: {$date}";

		$signature = '';
		\openssl_sign( $string_to_sign, $signature, Actors::get_private_key( 1 ), \OPENSSL_ALGO_SHA256 );

		$_SERVER['REQUEST_URI'] = $target;

		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/0/followers/sync' );
		$request->set_query_params( array( 'authority' => 'https://mastodon.example' ) );
		$request->set_headers(
			array(
				'Host'      => 'example.org',
				'Date'      => $date,
				'Signature' => \sprintf(
					'keyId="https://example.org/author/admin#main-key",algorithm="rsa-sha256",headers="(request-target) host date",signature="%s"',
					\base64_encode( $signature )
				),
			)
		);

		$this->assertNotWPError( Signature::verify_http_signature( $request ), 'Signed GET requests with a query string must verify.' );

		\remove_filter( 'activitypub_pre_http_get_remote_object', $mock_remote_key_retrieval );
	}

	/**
	 * A signature created by the plugin's own signer for a query-string URL
	 * must round-trip through the verifier.
	 *
	 * @covers ::verify_http_signature
	 */
	public function test_verify_http_signature_round_trip_with_query_string() {
		$keys = Actors::get_keypair( 1 );

		$force_cavage = '__return_zero';
		\add_filter( 'pre_option_activitypub_rfc9421_signature', $force_cavage );

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
				'method'      => 'GET',
				'key_id'      => 'https://example.org/author/admin#main-key',
				'private_key' => Actors::get_private_key( 1 ),
				'user_id'     => 1,
				'headers'     => array(
					'Date' => \gmdate( 'D, d M Y H:i:s T' ),
					'Host' => 'example.org',
				),
			),
			'https://example.org/wp-json/activitypub/1.0/actors/0/followers/sync?authority=https://mastodon.example'
		);

		// Only the verifier reads REQUEST_URI; the signer above works off the URL.
		$_SERVER['REQUEST_URI'] = '/wp-json/activitypub/1.0/actors/0/followers/sync?authority=https://mastodon.example';

		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/0/followers/sync' );
		$request->set_query_params( array( 'authority' => 'https://mastodon.example' ) );
		$request->set_headers( $args['headers'] );

		$this->assertNotWPError( Signature::verify_http_signature( $request ), 'Signatures created by the plugin for query-string URLs must round-trip.' );

		\remove_filter( 'activitypub_pre_http_get_remote_object', $mock_remote_key_retrieval );
		\remove_filter( 'pre_option_activitypub_rfc9421_signature', $force_cavage );
	}

	/**
	 * A signed GET request without a query string must still verify.
	 *
	 * @covers ::verify_http_signature
	 */
	public function test_verify_http_signature_get_without_query_string() {
		$keys = Actors::get_keypair( 1 );

		$force_cavage = '__return_zero';
		\add_filter( 'pre_option_activitypub_rfc9421_signature', $force_cavage );

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
				'method'      => 'GET',
				'key_id'      => 'https://example.org/author/admin#main-key',
				'private_key' => Actors::get_private_key( 1 ),
				'user_id'     => 1,
				'headers'     => array(
					'Date' => \gmdate( 'D, d M Y H:i:s T' ),
					'Host' => 'example.org',
				),
			),
			'https://example.org/wp-json/activitypub/1.0/actors/0/outbox'
		);

		$_SERVER['REQUEST_URI'] = '/wp-json/activitypub/1.0/actors/0/outbox';

		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/0/outbox' );
		$request->set_headers( $args['headers'] );

		$this->assertNotWPError( Signature::verify_http_signature( $request ), 'Signed GET requests without a query string must still verify.' );

		\remove_filter( 'activitypub_pre_http_get_remote_object', $mock_remote_key_retrieval );
		\remove_filter( 'pre_option_activitypub_rfc9421_signature', $force_cavage );
	}

	/**
	 * Signed headers that include neither Date nor (created) must be rejected.
	 *
	 * A captured signed request with no time anchor can otherwise be
	 * replayed indefinitely because nothing in the signed base string
	 * bounds its freshness.
	 *
	 * @covers \Activitypub\Signature\Http_Signature_Draft::get_signed_data
	 */
	public function test_get_signed_data_requires_time_anchor() {
		$method = new \ReflectionMethod( \Activitypub\Signature\Http_Signature_Draft::class, 'get_signed_data' );
		$method->setAccessible( true );
		$instance = new \Activitypub\Signature\Http_Signature_Draft();

		$result = $method->invoke(
			$instance,
			array( '(request-target)', 'host', 'digest' ),
			array(),
			array(
				'(request-target)' => array( 'post /inbox' ),
				'host'             => array( 'example.org' ),
				'digest'           => array( 'sha-256=abc' ),
			)
		);

		$this->assertFalse( $result, 'Signed headers without Date or (created) must fail verification.' );
	}

	/**
	 * The (created) pseudo-header must observe the same asymmetric window
	 * as the Date header: five minutes ahead, one hour behind.
	 *
	 * @covers \Activitypub\Signature\Http_Signature_Draft::get_signed_data
	 */
	public function test_get_signed_data_enforces_created_window() {
		$method = new \ReflectionMethod( \Activitypub\Signature\Http_Signature_Draft::class, 'get_signed_data' );
		$method->setAccessible( true );
		$instance = new \Activitypub\Signature\Http_Signature_Draft();

		$now        = \time();
		$far_past   = $now - ( 2 * HOUR_IN_SECONDS );
		$far_future = $now + ( 10 * MINUTE_IN_SECONDS );
		$within     = $now - ( 30 * MINUTE_IN_SECONDS );

		$invoke = function ( $created ) use ( $method, $instance ) {
			return $method->invoke(
				$instance,
				array( '(request-target)', '(created)' ),
				array( '(created)' => (string) $created ),
				array(
					'(request-target)' => array( 'post /inbox' ),
				)
			);
		};

		$this->assertFalse( $invoke( $far_past ), '(created) more than an hour old must fail.' );
		$this->assertFalse( $invoke( $far_future ), '(created) more than five minutes in the future must fail.' );
		$this->assertNotFalse( $invoke( $within ), '(created) within the window must be accepted.' );
	}

	/**
	 * An empty or zero (created) value must not satisfy the time-anchor
	 * requirement, because the signed base string would then carry no
	 * freshness information.
	 *
	 * @covers \Activitypub\Signature\Http_Signature_Draft::get_signed_data
	 * @dataProvider empty_created_provider
	 *
	 * @param string $created Raw (created) value as it would be parsed from the signature header.
	 */
	public function test_get_signed_data_rejects_empty_or_zero_created( $created ) {
		$method = new \ReflectionMethod( \Activitypub\Signature\Http_Signature_Draft::class, 'get_signed_data' );
		$method->setAccessible( true );
		$instance = new \Activitypub\Signature\Http_Signature_Draft();

		$result = $method->invoke(
			$instance,
			array( '(request-target)', '(created)' ),
			array( '(created)' => $created ),
			array(
				'(request-target)' => array( 'post /inbox' ),
			)
		);

		$this->assertFalse( $result, 'Empty or zero (created) value must not be treated as a valid time anchor.' );
	}

	/**
	 * Data provider for empty or zero (created) values.
	 *
	 * @return array[]
	 */
	public function empty_created_provider() {
		return array(
			'empty string' => array( '' ),
			'zero string'  => array( '0' ),
			'zero integer' => array( 0 ),
		);
	}

	/**
	 * If (expires) is in the signed headers list but the signature
	 * omitted the value, fail closed rather than accessing an undefined
	 * array key.
	 *
	 * @covers \Activitypub\Signature\Http_Signature_Draft::get_signed_data
	 */
	public function test_get_signed_data_rejects_missing_expires_value() {
		$method = new \ReflectionMethod( \Activitypub\Signature\Http_Signature_Draft::class, 'get_signed_data' );
		$method->setAccessible( true );
		$instance = new \Activitypub\Signature\Http_Signature_Draft();

		$result = $method->invoke(
			$instance,
			array( '(request-target)', 'date', '(expires)' ),
			array(), // Signature header omitted expires=.
			array(
				'(request-target)' => array( 'post /inbox' ),
				'date'             => array( \gmdate( 'D, d M Y H:i:s T' ) ),
			)
		);

		$this->assertFalse( $result, '(expires) listed in signed headers without a value must fail.' );
	}

	/**
	 * The (expires) pseudo-header must reject already-expired values and
	 * absurdly-far-future values that neuter replay protection.
	 *
	 * @covers \Activitypub\Signature\Http_Signature_Draft::get_signed_data
	 */
	public function test_get_signed_data_enforces_expires_window() {
		$method = new \ReflectionMethod( \Activitypub\Signature\Http_Signature_Draft::class, 'get_signed_data' );
		$method->setAccessible( true );
		$instance = new \Activitypub\Signature\Http_Signature_Draft();

		$now = \time();

		$invoke = function ( $expires ) use ( $method, $instance, $now ) {
			return $method->invoke(
				$instance,
				array( '(request-target)', 'date', '(expires)' ),
				array( '(expires)' => (string) $expires ),
				array(
					'(request-target)' => array( 'post /inbox' ),
					'date'             => array( \gmdate( 'D, d M Y H:i:s T', $now ) ),
				)
			);
		};

		$this->assertFalse( $invoke( $now - MINUTE_IN_SECONDS ), 'Already-expired (expires) must fail.' );
		$this->assertFalse( $invoke( $now + ( 7 * DAY_IN_SECONDS ) ), '(expires) absurdly far in the future must fail.' );
		$this->assertNotFalse( $invoke( $now + ( 30 * MINUTE_IN_SECONDS ) ), '(expires) within a day must be accepted.' );
	}

	/**
	 * A validated (expires) caps the signature's lifetime on its own, so
	 * it must satisfy the time-anchor requirement even without Date or
	 * (created).
	 *
	 * @covers \Activitypub\Signature\Http_Signature_Draft::get_signed_data
	 */
	public function test_get_signed_data_accepts_expires_as_time_anchor() {
		$method = new \ReflectionMethod( \Activitypub\Signature\Http_Signature_Draft::class, 'get_signed_data' );
		$method->setAccessible( true );
		$instance = new \Activitypub\Signature\Http_Signature_Draft();

		$result = $method->invoke(
			$instance,
			array( '(request-target)', '(expires)' ),
			array( '(expires)' => (string) ( \time() + ( 30 * MINUTE_IN_SECONDS ) ) ),
			array(
				'(request-target)' => array( 'post /inbox' ),
			)
		);

		$this->assertNotFalse( $result, '(expires) within the cap must satisfy the time-anchor requirement on its own.' );
	}

	/**
	 * RFC-9421 signatures must reject `created` more than one hour in the
	 * past or more than one minute in the future.
	 *
	 * @covers ::verify_http_signature
	 * @covers \Activitypub\Signature\Http_Message_Signature::verify_signature_label
	 */
	public function test_verify_http_signature_rfc9421_rejects_out_of_window_created() {
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

		$sign = static function ( $date_offset_seconds ) use ( $keys ) {
			$date = \gmdate( 'D, d M Y H:i:s T', \time() + $date_offset_seconds );

			$args = \apply_filters(
				'http_request_args',
				array(
					'method'      => 'POST',
					'body'        => '{"type":"Create","actor":"https://example.org/author/admin","object":{"type":"Note","content":"x"}}',
					'headers'     => array(
						'Date' => $date,
						'Host' => 'example.org',
					),
					'key_id'      => 'https://example.org/author/admin#main-key',
					'private_key' => \openssl_pkey_get_private( $keys['private_key'] ),
					'user_id'     => 1,
				),
				'https://example.org/wp-json/activitypub/1.0/inbox'
			);

			$request = new \WP_REST_Request( 'POST', ACTIVITYPUB_REST_NAMESPACE . '/inbox' );
			$request->set_body( $args['body'] );
			$request->set_headers( $args['headers'] );

			return Signature::verify_http_signature( $request );
		};

		$far_past   = $sign( -2 * HOUR_IN_SECONDS );
		$far_future = $sign( 5 * MINUTE_IN_SECONDS );

		$this->assertWPError( $far_past, 'RFC-9421 created more than an hour old must be rejected.' );
		$this->assertSame( 'expired_created', $far_past->get_error_code() );

		$this->assertWPError( $far_future, 'RFC-9421 created more than one minute in the future must be rejected.' );
		$this->assertSame( 'invalid_created', $far_future->get_error_code() );

		\remove_filter( 'activitypub_pre_http_get_remote_object', $mock_remote_key_retrieval );
		\delete_option( 'activitypub_rfc9421_signature' );
	}

	/**
	 * RFC-9421 signatures without `created` or `expires` must be rejected.
	 *
	 * A signature with neither parameter has no freshness bound inside
	 * the signed base string and could be replayed indefinitely.
	 *
	 * @covers \Activitypub\Signature\Http_Message_Signature::verify_signature_label
	 */
	public function test_verify_rfc9421_rejects_missing_time_anchor() {
		$method = new \ReflectionMethod( \Activitypub\Signature\Http_Message_Signature::class, 'verify_signature_label' );
		$method->setAccessible( true );
		$instance = new \Activitypub\Signature\Http_Message_Signature();

		$data = array(
			'components' => array( '"@method"', '"@target-uri"' ),
			'params'     => array(
				'keyid' => 'https://example.org/author/admin#main-key',
				'alg'   => 'rsa-v1_5-sha256',
			),
			'signature'  => '',
		);

		$result = $method->invoke( $instance, $data, array(), null );

		$this->assertWPError( $result, 'Signature without created or expires must be rejected.' );
		$this->assertSame( 'missing_time_anchor', $result->get_error_code() );
	}

	/**
	 * RFC-9421 `expires` values outside the accepted window must be rejected
	 * with distinct error codes.
	 *
	 * @covers \Activitypub\Signature\Http_Message_Signature::verify_signature_label
	 * @dataProvider rfc9421_expires_provider
	 *
	 * @param int    $offset Seconds offset from now for the `expires` value.
	 * @param string $code   Expected WP_Error code.
	 */
	public function test_verify_rfc9421_rejects_out_of_window_expires( $offset, $code ) {
		$method = new \ReflectionMethod( \Activitypub\Signature\Http_Message_Signature::class, 'verify_signature_label' );
		$method->setAccessible( true );
		$instance = new \Activitypub\Signature\Http_Message_Signature();

		$data = array(
			'components' => array( '"@method"', '"@target-uri"' ),
			'params'     => array(
				'expires' => (string) ( \time() + $offset ),
				'keyid'   => 'https://example.org/author/admin#main-key',
				'alg'     => 'rsa-v1_5-sha256',
			),
			'signature'  => '',
		);

		$result = $method->invoke( $instance, $data, array(), null );

		$this->assertWPError( $result );
		$this->assertSame( $code, $result->get_error_code() );
	}

	/**
	 * Data provider for out-of-window `expires` values.
	 *
	 * @return array[]
	 */
	public function rfc9421_expires_provider() {
		return array(
			'already expired'            => array( -1 * MINUTE_IN_SECONDS, 'expired_signature' ),
			'absurdly far in the future' => array( 7 * DAY_IN_SECONDS, 'invalid_expires' ),
		);
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
		$this->assertNotWPError( Signature::verify_http_signature( $request ) );

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
		$this->assertNotWPError( Signature::verify_http_signature( $request ) );

		\remove_filter( 'activitypub_pre_http_get_remote_object', $mock_remote_key_retrieval );
		\delete_option( 'activitypub_rfc9421_signature' );
	}

	/**
	 * RFC 9421: the keyId returned is the label that actually verified, not merely the first.
	 *
	 * A request can carry several signature labels and the verifier accepts whichever validates.
	 * An attacker pairing a victim-keyed invalid label with their own valid label must not get
	 * the victim keyId surfaced, or the actor host-binding could be bypassed.
	 *
	 * @covers ::verify_http_signature
	 * @covers \Activitypub\Signature\Http_Message_Signature::verify
	 */
	public function test_verify_http_signature_rfc9421_returns_verifying_label_key_id() {
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

		/*
		 * Prepend a second label naming a different-host keyId but carrying an invalid signature.
		 * The verifier should skip it and report the keyId of the label that actually validated.
		 */
		$created                    = \time();
		$headers                    = $args['headers'];
		$headers['Signature-Input'] = \sprintf(
			'sig0=("@method");created=%d;keyid="https://victim.example/users/victim#main-key";alg="rsa-v1_5-sha256", ',
			$created
		) . $headers['Signature-Input'];
		$headers['Signature']       = 'sig0=:' . \base64_encode( 'not-a-valid-signature' ) . ':, ' . $headers['Signature'];

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['REQUEST_URI']    = '/wp-json/activitypub/1.0/inbox';
		$_SERVER['HTTP_HOST']      = 'example.org';
		$_SERVER['HTTPS']          = 'on';

		$request = new \WP_REST_Request( 'POST', ACTIVITYPUB_REST_NAMESPACE . '/inbox' );
		$request->set_body( $args['body'] );
		$request->set_headers( $headers );

		$result = Signature::verify_http_signature( $request );

		$this->assertSame(
			'https://example.org/author/admin#main-key',
			$result,
			'The keyId of the label that actually verified must be returned, not the first label.'
		);

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
		$this->assertNotWPError( Signature::verify_http_signature( $request ) );

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
		$this->assertNotWPError( Signature::verify_http_signature( $request ) );

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
	 * Test HTTP signature verification with a standalone key object (top-level publicKeyPem).
	 *
	 * Some servers use top-level ActivityPub objects as keys instead of fragment identifiers.
	 * For example, `https://activitypub.bot/user/ok/publickey` returns a CryptographicKey
	 * object with `publicKeyPem` and `owner` at the top level.
	 *
	 * @covers ::verify_http_signature
	 */
	public function test_verify_http_signature_with_standalone_key_object() {
		$public_key  = self::$test_keys['rsa'][2048]['public_key'];
		$private_key = \openssl_pkey_get_private( self::$test_keys['rsa'][2048]['private_key'] );

		$date           = \gmdate( 'D, d M Y H:i:s T' );
		$string_to_sign = "(request-target): post /wp-json/activitypub/1.0/inbox\nhost: example.org\ndate: {$date}";

		$signature = '';
		\openssl_sign( $string_to_sign, $signature, $private_key, \OPENSSL_ALGO_SHA256 );

		$request = array(
			'REQUEST_METHOD' => 'POST',
			'REQUEST_URI'    => '/wp-json/activitypub/1.0/inbox',
			'HTTP_HOST'      => 'example.org',
			'HTTP_DATE'      => $date,
			'HTTP_SIGNATURE' => \sprintf(
				'keyId="https://activitypub.bot/user/ok/publickey",algorithm="hs2019",headers="(request-target) host date",signature="%s"',
				\base64_encode( $signature )
			),
		);

		// Mock the remote object retrieval to return different objects based on URL.
		$mock_remote_retrieval = function ( $response, $url ) use ( $public_key ) {
			// Standalone key object (returned when fetching the keyId URL).
			if ( 'https://activitypub.bot/user/ok/publickey' === $url ) {
				return array(
					'@context'     => array( 'https://w3id.org/security/v1', 'https://www.w3.org/ns/activitystreams' ),
					'id'           => 'https://activitypub.bot/user/ok/publickey',
					'type'         => 'CryptographicKey',
					'owner'        => 'https://activitypub.bot/user/ok',
					'publicKeyPem' => $public_key,
				);
			}

			// Owner actor (returned when verifying the key relationship).
			if ( 'https://activitypub.bot/user/ok' === $url ) {
				return array(
					'id'        => 'https://activitypub.bot/user/ok',
					'type'      => 'Person',
					'name'      => 'OK Bot',
					'publicKey' => array(
						'id'           => 'https://activitypub.bot/user/ok/publickey',
						'owner'        => 'https://activitypub.bot/user/ok',
						'publicKeyPem' => $public_key,
					),
				);
			}

			return $response;
		};
		\add_filter( 'activitypub_pre_http_get_remote_object', $mock_remote_retrieval, 10, 2 );

		try {
			$this->assertNotWPError( Signature::verify_http_signature( $request ), 'Valid signature with standalone key object should verify' );
		} finally {
			\remove_filter( 'activitypub_pre_http_get_remote_object', $mock_remote_retrieval, 10 );
		}
	}

	/**
	 * Test HTTP signature verification rejects standalone key when owner does not reference it.
	 *
	 * @covers ::verify_http_signature
	 */
	public function test_verify_http_signature_rejects_standalone_key_with_mismatched_owner() {
		$public_key  = self::$test_keys['rsa'][2048]['public_key'];
		$private_key = \openssl_pkey_get_private( self::$test_keys['rsa'][2048]['private_key'] );

		$date           = \gmdate( 'D, d M Y H:i:s T' );
		$string_to_sign = "(request-target): post /wp-json/activitypub/1.0/inbox\nhost: example.org\ndate: {$date}";

		$signature = '';
		\openssl_sign( $string_to_sign, $signature, $private_key, \OPENSSL_ALGO_SHA256 );

		$request = array(
			'REQUEST_METHOD' => 'POST',
			'REQUEST_URI'    => '/wp-json/activitypub/1.0/inbox',
			'HTTP_HOST'      => 'example.org',
			'HTTP_DATE'      => $date,
			'HTTP_SIGNATURE' => \sprintf(
				'keyId="https://evil.example/fake/publickey",algorithm="hs2019",headers="(request-target) host date",signature="%s"',
				\base64_encode( $signature )
			),
		);

		// Mock: standalone key claims to be owned by an actor, but the actor's publicKey.id doesn't match.
		$mock_remote_retrieval = function ( $response, $url ) use ( $public_key ) {
			if ( 'https://evil.example/fake/publickey' === $url ) {
				return array(
					'id'           => 'https://evil.example/fake/publickey',
					'type'         => 'CryptographicKey',
					'owner'        => 'https://activitypub.bot/user/ok',
					'publicKeyPem' => $public_key,
				);
			}

			if ( 'https://activitypub.bot/user/ok' === $url ) {
				return array(
					'id'        => 'https://activitypub.bot/user/ok',
					'type'      => 'Person',
					'name'      => 'OK Bot',
					'publicKey' => array(
						'id'           => 'https://activitypub.bot/user/ok/publickey',
						'owner'        => 'https://activitypub.bot/user/ok',
						'publicKeyPem' => $public_key,
					),
				);
			}

			return $response;
		};
		\add_filter( 'activitypub_pre_http_get_remote_object', $mock_remote_retrieval, 10, 2 );

		try {
			$result = Signature::verify_http_signature( $request );
			$this->assertWPError( $result, 'Standalone key with mismatched owner should be rejected' );
		} finally {
			\remove_filter( 'activitypub_pre_http_get_remote_object', $mock_remote_retrieval, 10 );
		}
	}

	/**
	 * Test HTTP signature verification with standalone key that has a different key ID than owner's.
	 *
	 * Both key and owner are on the same host. The standalone key follows the owner,
	 * and the owner's actual key is used for verification. This is valid because
	 * both are on the same trusted host.
	 *
	 * @covers ::verify_http_signature
	 */
	public function test_verify_http_signature_standalone_key_follows_owner_same_host() {
		$public_key  = self::$test_keys['rsa'][2048]['public_key'];
		$private_key = \openssl_pkey_get_private( self::$test_keys['rsa'][2048]['private_key'] );

		$date           = \gmdate( 'D, d M Y H:i:s T' );
		$string_to_sign = "(request-target): post /wp-json/activitypub/1.0/inbox\nhost: example.org\ndate: {$date}";

		$signature = '';
		\openssl_sign( $string_to_sign, $signature, $private_key, \OPENSSL_ALGO_SHA256 );

		$request = array(
			'REQUEST_METHOD' => 'POST',
			'REQUEST_URI'    => '/wp-json/activitypub/1.0/inbox',
			'HTTP_HOST'      => 'example.org',
			'HTTP_DATE'      => $date,
			'HTTP_SIGNATURE' => \sprintf(
				'keyId="https://activitypub.bot/user/fake/publickey",algorithm="hs2019",headers="(request-target) host date",signature="%s"',
				\base64_encode( $signature )
			),
		);

		// Mock: standalone key follows owner on the same host.
		$mock_remote_retrieval = function ( $response, $url ) use ( $public_key ) {
			if ( 'https://activitypub.bot/user/fake/publickey' === $url ) {
				return array(
					'id'           => 'https://activitypub.bot/user/fake/publickey',
					'type'         => 'CryptographicKey',
					'owner'        => 'https://activitypub.bot/user/ok',
					'publicKeyPem' => $public_key,
				);
			}

			if ( 'https://activitypub.bot/user/ok' === $url ) {
				return array(
					'id'        => 'https://activitypub.bot/user/ok',
					'type'      => 'Person',
					'name'      => 'OK Bot',
					'publicKey' => array(
						'id'           => 'https://activitypub.bot/user/ok/publickey',
						'owner'        => 'https://activitypub.bot/user/ok',
						'publicKeyPem' => $public_key,
					),
				);
			}

			return $response;
		};
		\add_filter( 'activitypub_pre_http_get_remote_object', $mock_remote_retrieval, 10, 2 );

		try {
			$this->assertNotWPError( Signature::verify_http_signature( $request ), 'Same-host standalone key following owner should verify' );
		} finally {
			\remove_filter( 'activitypub_pre_http_get_remote_object', $mock_remote_retrieval, 10 );
		}
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
	 * The keyId is extracted from both the draft Signature header and the RFC 9421
	 * Signature-Input header, and is null when neither is present.
	 *
	 * @covers ::get_key_id
	 *
	 * @dataProvider get_key_id_provider
	 *
	 * @param string|null $signature       The draft Signature header value, or null.
	 * @param string|null $signature_input The RFC 9421 Signature-Input header value, or null.
	 * @param string|null $expected        The expected keyId.
	 */
	public function test_get_key_id( $signature, $signature_input, $expected ) {
		$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/inbox' );
		if ( null !== $signature ) {
			$request->set_header( 'signature', $signature );
		}
		if ( null !== $signature_input ) {
			$request->set_header( 'signature-input', $signature_input );
		}

		$this->assertSame( $expected, Signature::get_key_id( $request ) );
	}

	/**
	 * A draft signature carried in the Authorization header is also recognized, matching the
	 * verifier's `signature ?? authorization` fallback.
	 *
	 * @covers ::get_key_id
	 */
	public function test_get_key_id_reads_authorization_header() {
		$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/inbox' );
		$request->set_header( 'authorization', 'Signature keyId="https://remote.example/users/curator#main-key",algorithm="rsa-sha256",signature="abc"' );

		$this->assertSame( 'https://remote.example/users/curator#main-key', Signature::get_key_id( $request ) );
	}

	/**
	 * An OAuth Bearer token in the Authorization header carries no keyId and must not match.
	 *
	 * @covers ::get_key_id
	 */
	public function test_get_key_id_ignores_bearer_authorization() {
		$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/inbox' );
		$request->set_header( 'authorization', 'Bearer some-oauth-token' );

		$this->assertNull( Signature::get_key_id( $request ) );
	}

	/**
	 * Data provider for test_get_key_id.
	 *
	 * @return array[]
	 */
	public function get_key_id_provider() {
		return array(
			'draft Signature header'           => array(
				'keyId="https://remote.example/users/curator#main-key",algorithm="rsa-sha256",signature="abc"',
				null,
				'https://remote.example/users/curator#main-key',
			),
			'RFC 9421 Signature-Input'         => array(
				null,
				'sig1=("@method" "@target-uri");keyid="https://remote.example/users/curator#main-key";created=1700000000',
				'https://remote.example/users/curator#main-key',
			),
			'no signature headers'             => array( null, null, null ),
			// Signature-Input wins over a draft Signature header, matching the verifier's choice.
			'mixed headers prefer RFC 9421'    => array(
				'keyId="https://victim.example/users/victim#main-key",signature="abc"',
				'sig1=("@method");keyid="https://attacker.example/users/attacker#main-key";created=1700000000',
				'https://attacker.example/users/attacker#main-key',
			),
			// The verifier accepts whichever label validates, so multiple keyIds are ambiguous.
			'ambiguous multiple keyIds'        => array(
				null,
				'sig1=("@method");keyid="https://victim.example/users/victim#main-key", sig2=("@method");keyid="https://attacker.example/users/attacker#main-key";created=1700000000',
				null,
			),
			// RFC 9421 allows an unquoted keyid value.
			'RFC 9421 unquoted keyid'          => array(
				null,
				'sig1=("@method");keyid=https://evil.example/actor#key;alg="rsa-v1_5-sha256"',
				'https://evil.example/actor#key',
			),
			// A `keyid=` smuggled inside another parameter's quoted value must not be picked up.
			'keyid inside other param ignored' => array(
				null,
				'sig1=("@method");tag="keyid=https://victim.example/key";keyid=https://evil.example/key',
				'https://evil.example/key',
			),
		);
	}

	/**
	 * A successful verification returns the keyId it validated against, so callers can bind it.
	 *
	 * @covers ::verify_http_signature
	 */
	public function test_verify_http_signature_returns_verified_key_id() {
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

		$result = Signature::verify_http_signature( $request );

		$this->assertSame( 'https://example.org/author/admin#main-key', $result, 'The verified keyId is returned on success.' );

		\remove_filter( 'activitypub_pre_http_get_remote_object', $mock_remote_key_retrieval );
	}
}
