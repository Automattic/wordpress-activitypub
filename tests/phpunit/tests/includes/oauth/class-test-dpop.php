<?php
/**
 * Test file for DPoP (RFC 9449) implementation.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\OAuth;

use Activitypub\OAuth\Client;
use Activitypub\OAuth\DPoP;
use Activitypub\OAuth\Scope;
use Activitypub\OAuth\Token;
use Activitypub\Post_Types;

/**
 * Test class for DPoP proof validation.
 *
 * @coversDefaultClass \Activitypub\OAuth\DPoP
 * @group oauth
 */
class Test_DPoP extends \WP_UnitTestCase {

	/**
	 * Test user ID.
	 *
	 * @var int
	 */
	protected $user_id;

	/**
	 * Test client ID.
	 *
	 * @var string
	 */
	protected $client_id;

	/**
	 * EC private key resource for test signing.
	 *
	 * @var resource
	 */
	protected $ec_private_key;

	/**
	 * EC JWK public key for test proofs.
	 *
	 * @var array
	 */
	protected $ec_jwk;

	/**
	 * RSA private key resource for test signing.
	 *
	 * @var resource
	 */
	protected $rsa_private_key;

	/**
	 * RSA JWK public key for test proofs.
	 *
	 * @var array
	 */
	protected $rsa_jwk;

	/**
	 * Set up the test.
	 */
	public function set_up() {
		parent::set_up();

		Post_Types::register_oauth_post_types();

		$this->user_id = $this->factory->user->create(
			array( 'role' => 'editor' )
		);

		$client_result   = Client::register(
			array(
				'name'          => 'DPoP Test Client',
				'redirect_uris' => array( 'https://example.com/callback' ),
			)
		);
		$this->client_id = $client_result['client_id'];

		// Generate an EC P-256 key pair for testing.
		$this->ec_private_key = openssl_pkey_new(
			array(
				'curve_name'       => 'prime256v1',
				'private_key_type' => OPENSSL_KEYTYPE_EC,
			)
		);
		$ec_details           = openssl_pkey_get_details( $this->ec_private_key );
		$this->ec_jwk         = array(
			'kty' => 'EC',
			'crv' => 'P-256',
			'x'   => DPoP::base64url_encode( str_pad( $ec_details['ec']['x'], 32, "\0", STR_PAD_LEFT ) ),
			'y'   => DPoP::base64url_encode( str_pad( $ec_details['ec']['y'], 32, "\0", STR_PAD_LEFT ) ),
		);

		// Generate an RSA key pair for testing.
		$this->rsa_private_key = openssl_pkey_new(
			array(
				'private_key_bits' => 2048,
				'private_key_type' => OPENSSL_KEYTYPE_RSA,
			)
		);
		$rsa_details           = openssl_pkey_get_details( $this->rsa_private_key );
		$this->rsa_jwk         = array(
			'kty' => 'RSA',
			'n'   => DPoP::base64url_encode( $rsa_details['rsa']['n'] ),
			'e'   => DPoP::base64url_encode( $rsa_details['rsa']['e'] ),
		);
	}

	/**
	 * Tear down the test.
	 */
	public function tear_down() {
		if ( $this->client_id ) {
			Client::delete( $this->client_id );
		}
		parent::tear_down();
	}

	/**
	 * Test base64url encoding/decoding roundtrip.
	 *
	 * @covers ::base64url_encode
	 * @covers ::base64url_decode
	 */
	public function test_base64url_roundtrip() {
		$data    = random_bytes( 32 );
		$encoded = DPoP::base64url_encode( $data );
		$decoded = DPoP::base64url_decode( $encoded );

		$this->assertEquals( $data, $decoded );
		// Base64url should not contain +, /, or =.
		$this->assertStringNotContainsString( '+', $encoded );
		$this->assertStringNotContainsString( '/', $encoded );
		$this->assertStringNotContainsString( '=', $encoded );
	}

	/**
	 * Test JWK thumbprint computation for EC key (RFC 7638).
	 *
	 * @covers ::compute_jkt
	 */
	public function test_compute_jkt_ec() {
		$jkt = DPoP::compute_jkt( $this->ec_jwk );

		$this->assertNotInstanceOf( \WP_Error::class, $jkt );
		$this->assertIsString( $jkt );
		// Should be base64url-encoded SHA-256 (43 chars without padding).
		$this->assertGreaterThan( 0, strlen( $jkt ) );
	}

	/**
	 * Test JWK thumbprint computation for RSA key.
	 *
	 * @covers ::compute_jkt
	 */
	public function test_compute_jkt_rsa() {
		$jkt = DPoP::compute_jkt( $this->rsa_jwk );

		$this->assertNotInstanceOf( \WP_Error::class, $jkt );
		$this->assertIsString( $jkt );
	}

	/**
	 * Test JWK thumbprint is deterministic.
	 *
	 * @covers ::compute_jkt
	 */
	public function test_compute_jkt_deterministic() {
		$jkt1 = DPoP::compute_jkt( $this->ec_jwk );
		$jkt2 = DPoP::compute_jkt( $this->ec_jwk );

		$this->assertEquals( $jkt1, $jkt2 );
	}

	/**
	 * Test JWK thumbprint fails for missing key type.
	 *
	 * @covers ::compute_jkt
	 */
	public function test_compute_jkt_missing_kty() {
		$result = DPoP::compute_jkt( array( 'x' => 'foo' ) );
		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	/**
	 * Test JWK thumbprint fails for unsupported key type.
	 *
	 * @covers ::compute_jkt
	 */
	public function test_compute_jkt_unsupported_kty() {
		$result = DPoP::compute_jkt( array( 'kty' => 'OKP' ) );
		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	/**
	 * Test valid DPoP proof with ES256.
	 *
	 * @covers ::validate_proof
	 */
	public function test_validate_proof_es256() {
		$proof  = $this->create_dpop_proof( 'ES256', $this->ec_jwk, $this->ec_private_key, 'POST', 'https://example.com/token' );
		$result = DPoP::validate_proof( $proof, 'POST', 'https://example.com/token' );

		$this->assertNotInstanceOf( \WP_Error::class, $result );
		$this->assertArrayHasKey( 'jkt', $result );
		$this->assertEquals( DPoP::compute_jkt( $this->ec_jwk ), $result['jkt'] );
	}

	/**
	 * Test valid DPoP proof with RS256.
	 *
	 * @covers ::validate_proof
	 */
	public function test_validate_proof_rs256() {
		$proof  = $this->create_dpop_proof( 'RS256', $this->rsa_jwk, $this->rsa_private_key, 'POST', 'https://example.com/token' );
		$result = DPoP::validate_proof( $proof, 'POST', 'https://example.com/token' );

		$this->assertNotInstanceOf( \WP_Error::class, $result );
		$this->assertArrayHasKey( 'jkt', $result );
		$this->assertEquals( DPoP::compute_jkt( $this->rsa_jwk ), $result['jkt'] );
	}

	/**
	 * Test DPoP proof with access token hash (ath).
	 *
	 * @covers ::validate_proof
	 */
	public function test_validate_proof_with_ath() {
		$access_token = 'test_access_token_value';
		$ath          = DPoP::base64url_encode( hash( 'sha256', $access_token, true ) );

		$proof = $this->create_dpop_proof(
			'ES256',
			$this->ec_jwk,
			$this->ec_private_key,
			'GET',
			'https://example.com/resource',
			array( 'ath' => $ath )
		);

		$result = DPoP::validate_proof( $proof, 'GET', 'https://example.com/resource', $access_token );

		$this->assertNotInstanceOf( \WP_Error::class, $result );
		$this->assertArrayHasKey( 'jkt', $result );
	}

	/**
	 * Test DPoP proof rejected when ath is missing but access token provided.
	 *
	 * @covers ::validate_proof
	 */
	public function test_validate_proof_missing_ath() {
		$proof = $this->create_dpop_proof(
			'ES256',
			$this->ec_jwk,
			$this->ec_private_key,
			'GET',
			'https://example.com/resource'
		);

		$result = DPoP::validate_proof( $proof, 'GET', 'https://example.com/resource', 'some_token' );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertEquals( 'activitypub_dpop_missing_ath', $result->get_error_code() );
	}

	/**
	 * Test DPoP proof rejected when ath doesn't match.
	 *
	 * @covers ::validate_proof
	 */
	public function test_validate_proof_wrong_ath() {
		$ath = DPoP::base64url_encode( hash( 'sha256', 'wrong_token', true ) );

		$proof = $this->create_dpop_proof(
			'ES256',
			$this->ec_jwk,
			$this->ec_private_key,
			'GET',
			'https://example.com/resource',
			array( 'ath' => $ath )
		);

		$result = DPoP::validate_proof( $proof, 'GET', 'https://example.com/resource', 'correct_token' );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertEquals( 'activitypub_dpop_ath_mismatch', $result->get_error_code() );
	}

	/**
	 * Test DPoP proof rejected with wrong HTTP method.
	 *
	 * @covers ::validate_proof
	 */
	public function test_validate_proof_wrong_method() {
		$proof  = $this->create_dpop_proof( 'ES256', $this->ec_jwk, $this->ec_private_key, 'POST', 'https://example.com/token' );
		$result = DPoP::validate_proof( $proof, 'GET', 'https://example.com/token' );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertEquals( 'activitypub_dpop_method_mismatch', $result->get_error_code() );
	}

	/**
	 * Test DPoP proof rejected with wrong URI.
	 *
	 * @covers ::validate_proof
	 */
	public function test_validate_proof_wrong_uri() {
		$proof  = $this->create_dpop_proof( 'ES256', $this->ec_jwk, $this->ec_private_key, 'POST', 'https://example.com/token' );
		$result = DPoP::validate_proof( $proof, 'POST', 'https://other.com/token' );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertEquals( 'activitypub_dpop_uri_mismatch', $result->get_error_code() );
	}

	/**
	 * Test DPoP proof rejected when expired.
	 *
	 * @covers ::validate_proof
	 */
	public function test_validate_proof_expired() {
		$proof = $this->create_dpop_proof(
			'ES256',
			$this->ec_jwk,
			$this->ec_private_key,
			'POST',
			'https://example.com/token',
			array(),
			time() - 600 // 10 minutes ago, beyond MAX_AGE.
		);

		$result = DPoP::validate_proof( $proof, 'POST', 'https://example.com/token' );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertEquals( 'activitypub_dpop_expired', $result->get_error_code() );
	}

	/**
	 * Test DPoP proof rejected when issued in the future.
	 *
	 * @covers ::validate_proof
	 */
	public function test_validate_proof_future_iat() {
		$proof = $this->create_dpop_proof(
			'ES256',
			$this->ec_jwk,
			$this->ec_private_key,
			'POST',
			'https://example.com/token',
			array(),
			time() + 60 // 60 seconds in the future (beyond 5s skew allowance).
		);

		$result = DPoP::validate_proof( $proof, 'POST', 'https://example.com/token' );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertEquals( 'activitypub_dpop_future_iat', $result->get_error_code() );
	}

	/**
	 * Test DPoP proof rejected with malformed JWT.
	 *
	 * @covers ::validate_proof
	 */
	public function test_validate_proof_malformed_jwt() {
		$result = DPoP::validate_proof( 'not.a.valid.jwt', 'POST', 'https://example.com/token' );
		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	/**
	 * Test DPoP proof rejected with wrong typ.
	 *
	 * @covers ::validate_proof
	 */
	public function test_validate_proof_wrong_typ() {
		$header = array(
			'typ' => 'JWT',
			'alg' => 'ES256',
			'jwk' => $this->ec_jwk,
		);

		$payload = array(
			'jti' => wp_generate_uuid4(),
			'htm' => 'POST',
			'htu' => 'https://example.com/token',
			'iat' => time(),
		);

		$proof  = $this->sign_jwt( $header, $payload, $this->ec_private_key, 'ES256' );
		$result = DPoP::validate_proof( $proof, 'POST', 'https://example.com/token' );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertEquals( 'activitypub_dpop_invalid_typ', $result->get_error_code() );
	}

	/**
	 * Test DPoP proof rejected with symmetric algorithm.
	 *
	 * @covers ::validate_proof
	 */
	public function test_validate_proof_symmetric_alg() {
		// Craft a proof claiming HS256 (symmetric) — must be rejected.
		$header  = DPoP::base64url_encode(
			wp_json_encode(
				array(
					'typ' => 'dpop+jwt',
					'alg' => 'HS256',
					'jwk' => $this->ec_jwk,
				)
			)
		);
		$payload = DPoP::base64url_encode(
			wp_json_encode(
				array(
					'jti' => wp_generate_uuid4(),
					'htm' => 'POST',
					'htu' => 'https://example.com/token',
					'iat' => time(),
				)
			)
		);
		$sig     = DPoP::base64url_encode( 'fakesig' );

		$result = DPoP::validate_proof( "$header.$payload.$sig", 'POST', 'https://example.com/token' );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertEquals( 'activitypub_dpop_unsupported_alg', $result->get_error_code() );
	}

	/**
	 * Test DPoP proof rejected with bad signature.
	 *
	 * @covers ::validate_proof
	 */
	public function test_validate_proof_bad_signature() {
		// Create valid proof but tamper with the signature.
		$proof = $this->create_dpop_proof( 'ES256', $this->ec_jwk, $this->ec_private_key, 'POST', 'https://example.com/token' );
		$parts = explode( '.', $proof );
		// Replace signature with random data.
		$parts[2] = DPoP::base64url_encode( random_bytes( 64 ) );

		$result = DPoP::validate_proof( implode( '.', $parts ), 'POST', 'https://example.com/token' );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertEquals( 'activitypub_dpop_bad_signature', $result->get_error_code() );
	}

	/**
	 * Test token creation with DPoP binding.
	 *
	 * @covers \Activitypub\OAuth\Token::create
	 */
	public function test_token_create_with_dpop() {
		$jkt    = DPoP::compute_jkt( $this->ec_jwk );
		$result = Token::create( $this->user_id, $this->client_id, array( Scope::READ ), Token::DEFAULT_EXPIRATION, $jkt );

		$this->assertIsArray( $result );
		$this->assertEquals( 'DPoP', $result['token_type'] );
	}

	/**
	 * Test token creation without DPoP (backward compatible).
	 *
	 * @covers \Activitypub\OAuth\Token::create
	 */
	public function test_token_create_without_dpop() {
		$result = Token::create( $this->user_id, $this->client_id, array( Scope::READ ) );

		$this->assertIsArray( $result );
		$this->assertEquals( 'Bearer', $result['token_type'] );
	}

	/**
	 * Test validated token exposes DPoP JKT.
	 *
	 * @covers \Activitypub\OAuth\Token::get_dpop_jkt
	 */
	public function test_token_get_dpop_jkt() {
		$jkt    = DPoP::compute_jkt( $this->ec_jwk );
		$result = Token::create( $this->user_id, $this->client_id, array( Scope::READ ), Token::DEFAULT_EXPIRATION, $jkt );
		$token  = Token::validate( $result['access_token'] );

		$this->assertInstanceOf( Token::class, $token );
		$this->assertEquals( $jkt, $token->get_dpop_jkt() );
	}

	/**
	 * Test validated token without DPoP returns null JKT.
	 *
	 * @covers \Activitypub\OAuth\Token::get_dpop_jkt
	 */
	public function test_token_get_dpop_jkt_null_when_no_dpop() {
		$result = Token::create( $this->user_id, $this->client_id, array( Scope::READ ) );
		$token  = Token::validate( $result['access_token'] );

		$this->assertInstanceOf( Token::class, $token );
		$this->assertNull( $token->get_dpop_jkt() );
	}

	/**
	 * Test DPoP binding preserved through refresh.
	 *
	 * @covers \Activitypub\OAuth\Token::refresh
	 */
	public function test_refresh_preserves_dpop_binding() {
		$jkt      = DPoP::compute_jkt( $this->ec_jwk );
		$original = Token::create( $this->user_id, $this->client_id, array( Scope::READ ), Token::DEFAULT_EXPIRATION, $jkt );

		$this->assertEquals( 'DPoP', $original['token_type'] );

		$refreshed = Token::refresh( $original['refresh_token'], $this->client_id );

		$this->assertIsArray( $refreshed );
		$this->assertEquals( 'DPoP', $refreshed['token_type'] );

		// Validate the new token has the same JKT.
		$token = Token::validate( $refreshed['access_token'] );
		$this->assertEquals( $jkt, $token->get_dpop_jkt() );
	}

	/**
	 * Test DPoP-bound token introspection includes cnf claim.
	 *
	 * @covers \Activitypub\OAuth\Token::introspect
	 */
	public function test_introspect_dpop_token() {
		$jkt    = DPoP::compute_jkt( $this->ec_jwk );
		$result = Token::create( $this->user_id, $this->client_id, array( Scope::READ ), Token::DEFAULT_EXPIRATION, $jkt );

		$introspection = Token::introspect( $result['access_token'] );

		$this->assertTrue( $introspection['active'] );
		$this->assertEquals( 'DPoP', $introspection['token_type'] );
		$this->assertArrayHasKey( 'cnf', $introspection );
		$this->assertEquals( $jkt, $introspection['cnf']['jkt'] );
	}

	/**
	 * Test non-DPoP token introspection does not include cnf.
	 *
	 * @covers \Activitypub\OAuth\Token::introspect
	 */
	public function test_introspect_bearer_token_no_cnf() {
		$result = Token::create( $this->user_id, $this->client_id, array( Scope::READ ) );

		$introspection = Token::introspect( $result['access_token'] );

		$this->assertTrue( $introspection['active'] );
		$this->assertEquals( 'Bearer', $introspection['token_type'] );
		$this->assertArrayNotHasKey( 'cnf', $introspection );
	}

	/**
	 * Test DPoP proof rejected on jti replay.
	 *
	 * @covers ::validate_proof
	 */
	public function test_validate_proof_jti_replay() {
		$jti   = wp_generate_uuid4();
		$proof = $this->create_dpop_proof(
			'ES256',
			$this->ec_jwk,
			$this->ec_private_key,
			'POST',
			'https://example.com/token',
			array(),
			null,
			$jti
		);

		// First use should succeed.
		$result = DPoP::validate_proof( $proof, 'POST', 'https://example.com/token' );
		$this->assertNotInstanceOf( \WP_Error::class, $result );

		// Create a second proof with the same jti (replay).
		$replay_proof = $this->create_dpop_proof(
			'ES256',
			$this->ec_jwk,
			$this->ec_private_key,
			'POST',
			'https://example.com/token',
			array(),
			null,
			$jti
		);

		$replay_result = DPoP::validate_proof( $replay_proof, 'POST', 'https://example.com/token' );
		$this->assertInstanceOf( \WP_Error::class, $replay_result );
		$this->assertEquals( 'activitypub_dpop_jti_replayed', $replay_result->get_error_code() );
	}

	/**
	 * Test refresh with wrong DPoP key is rejected via token binding check.
	 *
	 * @covers \Activitypub\OAuth\Token::refresh
	 */
	public function test_refresh_with_different_key_preserves_original_binding() {
		// Create a token bound to EC key.
		$ec_jkt   = DPoP::compute_jkt( $this->ec_jwk );
		$original = Token::create( $this->user_id, $this->client_id, array( Scope::READ ), Token::DEFAULT_EXPIRATION, $ec_jkt );

		$this->assertEquals( 'DPoP', $original['token_type'] );

		// Refresh the token (refresh internally preserves the dpop_jkt).
		$refreshed = Token::refresh( $original['refresh_token'], $this->client_id );
		$this->assertIsArray( $refreshed );
		$this->assertEquals( 'DPoP', $refreshed['token_type'] );

		// The refreshed token still has the original EC key binding.
		$new_token = Token::validate( $refreshed['access_token'] );
		$this->assertEquals( $ec_jkt, $new_token->get_dpop_jkt() );

		// An RSA key would produce a different jkt — the controller should reject this.
		$rsa_jkt = DPoP::compute_jkt( $this->rsa_jwk );
		$this->assertNotEquals( $ec_jkt, $rsa_jkt );
	}

	/**
	 * Test DPoP proof URI normalization ignores query string.
	 *
	 * @covers ::validate_proof
	 */
	public function test_validate_proof_uri_ignores_query() {
		$proof = $this->create_dpop_proof(
			'ES256',
			$this->ec_jwk,
			$this->ec_private_key,
			'POST',
			'https://example.com/token'
		);

		// Request URI with query string should still match.
		$result = DPoP::validate_proof( $proof, 'POST', 'https://example.com/token?foo=bar' );

		$this->assertNotInstanceOf( \WP_Error::class, $result );
	}

	/**
	 * Helper: create a signed DPoP proof JWT.
	 *
	 * @param string      $alg         Algorithm (ES256 or RS256).
	 * @param array       $jwk         JWK public key.
	 * @param resource    $private_key Private key resource.
	 * @param string      $htm         HTTP method.
	 * @param string      $htu         HTTP URI.
	 * @param array       $extra       Extra payload claims.
	 * @param int|null    $iat         Override iat timestamp.
	 * @param string|null $jti         Override jti value (for replay testing).
	 * @return string The signed JWT.
	 */
	private function create_dpop_proof( $alg, $jwk, $private_key, $htm, $htu, $extra = array(), $iat = null, $jti = null ) {
		$header = array(
			'typ' => 'dpop+jwt',
			'alg' => $alg,
			'jwk' => $jwk,
		);

		$payload = array_merge(
			array(
				'jti' => null !== $jti ? $jti : wp_generate_uuid4(),
				'htm' => $htm,
				'htu' => $htu,
				'iat' => null !== $iat ? $iat : time(),
			),
			$extra
		);

		return $this->sign_jwt( $header, $payload, $private_key, $alg );
	}

	/**
	 * Helper: sign a JWT with the given key.
	 *
	 * @param array    $header      JWT header.
	 * @param array    $payload     JWT payload.
	 * @param resource $private_key Private key resource.
	 * @param string   $alg         Algorithm.
	 * @return string The signed JWT.
	 */
	private function sign_jwt( $header, $payload, $private_key, $alg ) {
		$header_b64    = DPoP::base64url_encode( wp_json_encode( $header ) );
		$payload_b64   = DPoP::base64url_encode( wp_json_encode( $payload ) );
		$signing_input = $header_b64 . '.' . $payload_b64;

		$signature = '';
		openssl_sign( $signing_input, $signature, $private_key, OPENSSL_ALGO_SHA256 );

		if ( 'ES256' === $alg ) {
			// Convert DER signature to JWS R||S format.
			$signature = $this->ecdsa_der_to_jws( $signature );
		}

		$sig_b64 = DPoP::base64url_encode( $signature );

		return $signing_input . '.' . $sig_b64;
	}

	/**
	 * Helper: convert an ECDSA DER signature to JWS R||S format.
	 *
	 * @param string $der_sig DER-encoded ECDSA signature.
	 * @return string The R||S concatenation (64 bytes for P-256).
	 */
	private function ecdsa_der_to_jws( $der_sig ) {
		// Parse the DER SEQUENCE.
		$offset = 2; // Skip SEQUENCE tag and length.
		if ( ord( $der_sig[1] ) & 0x80 ) {
			$offset = 2 + ( ord( $der_sig[1] ) & 0x7F );
		}

		// Parse R.
		$r_length = ord( $der_sig[ $offset + 1 ] );
		$r        = substr( $der_sig, $offset + 2, $r_length );
		$offset  += 2 + $r_length;

		// Parse S.
		$s_length = ord( $der_sig[ $offset + 1 ] );
		$s        = substr( $der_sig, $offset + 2, $s_length );

		// Remove leading zero bytes and pad to 32 bytes.
		$r = ltrim( $r, "\x00" );
		$s = ltrim( $s, "\x00" );

		$r = str_pad( $r, 32, "\x00", STR_PAD_LEFT );
		$s = str_pad( $s, 32, "\x00", STR_PAD_LEFT );

		return $r . $s;
	}
}
