<?php
/**
 * Test file for OAuth Client class.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\OAuth;

use Activitypub\OAuth\Client;
use Activitypub\OAuth\Scope;
use Activitypub\OAuth\Token;
use Activitypub\Post_Types;

/**
 * Test class for OAuth Client.
 *
 * @coversDefaultClass \Activitypub\OAuth\Client
 *
 * @group activitypub
 * @group oauth
 */
class Test_Client extends \WP_UnitTestCase {

	/**
	 * Created client IDs for cleanup.
	 *
	 * @var array
	 */
	protected $created_clients = array();

	/**
	 * Set up the test.
	 */
	public function set_up() {
		parent::set_up();

		// Register OAuth post types.
		Post_Types::register_oauth_post_types();
	}

	/**
	 * Tear down the test.
	 */
	public function tear_down() {
		// Clean up created clients.
		foreach ( $this->created_clients as $client_id ) {
			Client::delete( $client_id );
		}
		$this->created_clients = array();

		parent::tear_down();
	}

	/**
	 * Helper to create a client and track it for cleanup.
	 *
	 * @param array $data Client registration data.
	 * @return array|WP_Error Client credentials.
	 */
	protected function create_client( $data ) {
		$result = Client::register( $data );
		if ( ! is_wp_error( $result ) ) {
			$this->created_clients[] = $result['client_id'];
		}
		return $result;
	}

	/**
	 * Test generate_client_id produces UUID v4 format.
	 *
	 * @covers ::generate_client_id
	 */
	public function test_generate_client_id() {
		$client_id = Client::generate_client_id();

		// UUID v4 format: xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx.
		$this->assertMatchesRegularExpression(
			'/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
			$client_id
		);
	}

	/**
	 * Test generate_client_secret.
	 *
	 * @covers ::generate_client_secret
	 */
	public function test_generate_client_secret() {
		$secret = Client::generate_client_secret();

		// Should be 64 hex characters (32 bytes).
		$this->assertEquals( 64, strlen( $secret ) );
		$this->assertMatchesRegularExpression( '/^[0-9a-f]+$/', $secret );
	}

	/**
	 * Test register method creates public client.
	 *
	 * @covers ::register
	 */
	public function test_register_public_client() {
		$result = $this->create_client(
			array(
				'name'          => 'Test Public Client',
				'redirect_uris' => array( 'https://example.com/callback' ),
				'is_public'     => true,
			)
		);

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'client_id', $result );
		$this->assertArrayNotHasKey( 'client_secret', $result );
	}

	/**
	 * Test register method creates confidential client.
	 *
	 * @covers ::register
	 */
	public function test_register_confidential_client() {
		$result = $this->create_client(
			array(
				'name'          => 'Test Confidential Client',
				'redirect_uris' => array( 'https://example.com/callback' ),
				'is_public'     => false,
			)
		);

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'client_id', $result );
		$this->assertArrayHasKey( 'client_secret', $result );
	}

	/**
	 * Test register method requires name.
	 *
	 * @covers ::register
	 */
	public function test_register_requires_name() {
		$result = Client::register(
			array(
				'redirect_uris' => array( 'https://example.com/callback' ),
			)
		);

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertEquals( 'activitypub_missing_client_name', $result->get_error_code() );
	}

	/**
	 * Test register method requires redirect_uris.
	 *
	 * @covers ::register
	 */
	public function test_register_requires_redirect_uris() {
		$result = Client::register(
			array(
				'name' => 'Test Client',
			)
		);

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertEquals( 'activitypub_missing_redirect_uri', $result->get_error_code() );
	}

	/**
	 * Test register method validates redirect URI format.
	 *
	 * @covers ::register
	 */
	public function test_register_validates_redirect_uri_https() {
		$result = Client::register(
			array(
				'name'          => 'Test Client',
				'redirect_uris' => array( 'http://example.com/callback' ),
			)
		);

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertEquals( 'activitypub_invalid_redirect_uri', $result->get_error_code() );
	}

	/**
	 * Test register method allows http for loopback IP (RFC 8252).
	 *
	 * @covers ::register
	 */
	public function test_register_allows_loopback_http() {
		$result = $this->create_client(
			array(
				'name'          => 'Loopback Client',
				'redirect_uris' => array( 'http://127.0.0.1:3000/callback' ),
			)
		);

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'client_id', $result );
	}

	/**
	 * Test register method rejects http for non-loopback hosts.
	 *
	 * @covers ::register
	 */
	public function test_register_rejects_http_non_loopback() {
		$result = $this->create_client(
			array(
				'name'          => 'Remote Client',
				'redirect_uris' => array( 'http://example.com/callback' ),
			)
		);

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	/**
	 * Test register method allows http localhost.
	 *
	 * @covers ::register
	 */
	public function test_register_allows_http_localhost() {
		$result = $this->create_client(
			array(
				'name'          => 'Localhost Client',
				'redirect_uris' => array( 'http://localhost:8080/callback' ),
			)
		);

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'client_id', $result );
	}

	/**
	 * Test register method allows http for localhost subdomains (RFC 6761).
	 *
	 * @covers ::register
	 */
	public function test_register_allows_http_localhost_subdomain() {
		$result = $this->create_client(
			array(
				'name'          => 'Localhost Subdomain Client',
				'redirect_uris' => array( 'http://calypso.localhost:3000/callback' ),
			)
		);

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'client_id', $result );
	}

	/**
	 * Test register method accepts every legitimate loopback form.
	 *
	 * @covers ::register
	 * @dataProvider loopback_host_provider
	 *
	 * @param string $host Host portion of the redirect URI.
	 */
	public function test_register_allows_loopback_variants( $host ) {
		$result = $this->create_client(
			array(
				'name'          => 'Loopback Variant Client',
				'redirect_uris' => array( 'http://' . $host . ':3000/callback' ),
			)
		);

		$this->assertIsArray( $result, \sprintf( 'Loopback host %s should be accepted.', $host ) );
		$this->assertArrayHasKey( 'client_id', $result );
	}

	/**
	 * Data provider for loopback hosts that must be treated as loopback.
	 *
	 * @return array[]
	 */
	public function loopback_host_provider() {
		return array(
			'IPv4 loopback literal'       => array( '127.0.0.1' ),
			'IPv4 loopback upper range'   => array( '127.0.0.2' ),
			'IPv4 loopback high address'  => array( '127.255.255.254' ),
			'IPv6 loopback shorthand'     => array( '[::1]' ),
			'IPv6 loopback full form'     => array( '[0:0:0:0:0:0:0:1]' ),
			'IPv6 loopback leading zeros' => array( '[::0001]' ),
			'IPv4-mapped IPv6 loopback'   => array( '[::ffff:127.0.0.1]' ),
		);
	}

	/**
	 * Test register method rejects reserved addresses that are not loopback.
	 *
	 * Guards against treating reserved or otherwise non-loopback hosts as
	 * loopback for the `http://` redirect URI allowance during registration.
	 *
	 * @covers ::register
	 * @dataProvider non_loopback_host_provider
	 *
	 * @param string $host Host portion of the redirect URI.
	 */
	public function test_register_rejects_non_loopback_reserved_hosts( $host ) {
		$result = $this->create_client(
			array(
				'name'          => 'Non-loopback Reserved Client',
				'redirect_uris' => array( 'http://' . $host . ':3000/callback' ),
			)
		);

		$this->assertInstanceOf(
			\WP_Error::class,
			$result,
			\sprintf( 'Host %s must not be treated as loopback.', $host )
		);
	}

	/**
	 * Data provider for reserved or external hosts that must not be loopback.
	 *
	 * @return array[]
	 */
	public function non_loopback_host_provider() {
		return array(
			'unspecified IPv4'           => array( '0.0.0.0' ),
			'link-local cloud metadata'  => array( '169.254.169.254' ),
			'link-local generic'         => array( '169.254.1.1' ),
			'multicast'                  => array( '224.0.0.1' ),
			'reserved future use'        => array( '240.0.0.1' ),
			'TEST-NET-1'                 => array( '192.0.2.1' ),
			'TEST-NET-2'                 => array( '198.51.100.1' ),
			'TEST-NET-3'                 => array( '203.0.113.1' ),
			'private 10/8'               => array( '10.0.0.5' ),
			'private 172.16/12'          => array( '172.20.0.7' ),
			'private 192.168/16'         => array( '192.168.1.1' ),
			'public host'                => array( '8.8.8.8' ),
			'IPv4-mapped public address' => array( '[::ffff:8.8.8.8]' ),
			'IPv4-mapped link-local'     => array( '[::ffff:169.254.169.254]' ),
		);
	}

	/**
	 * Test register method allows http for non-loopback when filter permits.
	 *
	 * @covers ::register
	 */
	public function test_register_allows_http_with_filter() {
		\add_filter( 'activitypub_oauth_allow_http_redirect_uri', '__return_true' );

		$result = $this->create_client(
			array(
				'name'          => 'Localhost Client',
				'redirect_uris' => array( 'http://localhost:8080/callback' ),
			)
		);

		\remove_filter( 'activitypub_oauth_allow_http_redirect_uri', '__return_true' );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'client_id', $result );
	}

	/**
	 * Test get method retrieves client.
	 *
	 * @covers ::get
	 */
	public function test_get_client() {
		$result = $this->create_client(
			array(
				'name'          => 'Test Client',
				'redirect_uris' => array( 'https://example.com/callback' ),
			)
		);

		$client = Client::get( $result['client_id'] );

		$this->assertInstanceOf( Client::class, $client );
		$this->assertEquals( 'Test Client', $client->get_name() );
	}

	/**
	 * Test get method returns error for non-existent client.
	 *
	 * @covers ::get
	 */
	public function test_get_nonexistent_client() {
		$result = Client::get( 'nonexistent-client-id' );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertEquals( 'activitypub_client_not_found', $result->get_error_code() );
	}

	/**
	 * Test validate method for public client.
	 *
	 * @covers ::validate
	 */
	public function test_validate_public_client() {
		$result = $this->create_client(
			array(
				'name'          => 'Public Client',
				'redirect_uris' => array( 'https://example.com/callback' ),
				'is_public'     => true,
			)
		);

		// Public clients don't need a secret.
		$this->assertTrue( Client::validate( $result['client_id'] ) );
		$this->assertTrue( Client::validate( $result['client_id'], null ) );
	}

	/**
	 * Test validate method for confidential client.
	 *
	 * @covers ::validate
	 */
	public function test_validate_confidential_client() {
		$result = $this->create_client(
			array(
				'name'          => 'Confidential Client',
				'redirect_uris' => array( 'https://example.com/callback' ),
				'is_public'     => false,
			)
		);

		// Valid secret should pass.
		$this->assertTrue( Client::validate( $result['client_id'], $result['client_secret'] ) );

		// No secret should fail.
		$this->assertFalse( Client::validate( $result['client_id'] ) );

		// Wrong secret should fail.
		$this->assertFalse( Client::validate( $result['client_id'], 'wrong_secret' ) );
	}

	/**
	 * Test validate method for non-existent client.
	 *
	 * @covers ::validate
	 */
	public function test_validate_nonexistent_client() {
		$this->assertFalse( Client::validate( 'nonexistent-client-id' ) );
	}

	/**
	 * Test is_valid_redirect_uri method.
	 *
	 * @covers ::is_valid_redirect_uri
	 */
	public function test_is_valid_redirect_uri() {
		$result = $this->create_client(
			array(
				'name'          => 'Test Client',
				'redirect_uris' => array(
					'https://example.com/callback',
					'https://example.com/oauth',
				),
			)
		);

		$client = Client::get( $result['client_id'] );

		$this->assertTrue( $client->is_valid_redirect_uri( 'https://example.com/callback' ) );
		$this->assertTrue( $client->is_valid_redirect_uri( 'https://example.com/oauth' ) );
		$this->assertFalse( $client->is_valid_redirect_uri( 'https://example.com/other' ) );
		$this->assertFalse( $client->is_valid_redirect_uri( 'https://other.com/callback' ) );
	}

	/**
	 * Test get_redirect_uris method.
	 *
	 * @covers ::get_redirect_uris
	 */
	public function test_get_redirect_uris() {
		$uris   = array(
			'https://example.com/callback',
			'https://example.com/oauth',
		);
		$result = $this->create_client(
			array(
				'name'          => 'Test Client',
				'redirect_uris' => $uris,
			)
		);

		$client = Client::get( $result['client_id'] );

		$this->assertEquals( $uris, $client->get_redirect_uris() );
	}

	/**
	 * Test get_allowed_scopes method.
	 *
	 * @covers ::get_allowed_scopes
	 */
	public function test_get_allowed_scopes() {
		$scopes = array( Scope::READ, Scope::WRITE );
		$result = $this->create_client(
			array(
				'name'          => 'Test Client',
				'redirect_uris' => array( 'https://example.com/callback' ),
				'scopes'        => $scopes,
			)
		);

		$client = Client::get( $result['client_id'] );

		$this->assertEquals( $scopes, $client->get_allowed_scopes() );
	}

	/**
	 * Test get_allowed_scopes defaults to all scopes.
	 *
	 * @covers ::get_allowed_scopes
	 */
	public function test_get_allowed_scopes_default() {
		$result = $this->create_client(
			array(
				'name'          => 'Test Client',
				'redirect_uris' => array( 'https://example.com/callback' ),
			)
		);

		$client = Client::get( $result['client_id'] );

		$this->assertEquals( Scope::ALL, $client->get_allowed_scopes() );
	}

	/**
	 * Test is_public method.
	 *
	 * @covers ::is_public
	 */
	public function test_is_public() {
		$public_result = $this->create_client(
			array(
				'name'          => 'Public Client',
				'redirect_uris' => array( 'https://example.com/callback' ),
				'is_public'     => true,
			)
		);

		$confidential_result = $this->create_client(
			array(
				'name'          => 'Confidential Client',
				'redirect_uris' => array( 'https://other.com/callback' ),
				'is_public'     => false,
			)
		);

		$public_client       = Client::get( $public_result['client_id'] );
		$confidential_client = Client::get( $confidential_result['client_id'] );

		$this->assertTrue( $public_client->is_public() );
		$this->assertFalse( $confidential_client->is_public() );
	}

	/**
	 * Test filter_scopes method.
	 *
	 * @covers ::filter_scopes
	 */
	public function test_filter_scopes() {
		$result = $this->create_client(
			array(
				'name'          => 'Limited Client',
				'redirect_uris' => array( 'https://example.com/callback' ),
				'scopes'        => array( Scope::READ, Scope::WRITE ),
			)
		);

		$client = Client::get( $result['client_id'] );

		$filtered = $client->filter_scopes( array( Scope::READ, Scope::FOLLOW, Scope::WRITE ) );
		$this->assertEquals( array( Scope::READ, Scope::WRITE ), $filtered );

		$filtered = $client->filter_scopes( array( Scope::FOLLOW, Scope::PUSH ) );
		$this->assertEquals( array(), $filtered );
	}

	/**
	 * Test delete method.
	 *
	 * @covers ::delete
	 */
	public function test_delete() {
		$result    = Client::register(
			array(
				'name'          => 'Delete Test Client',
				'redirect_uris' => array( 'https://example.com/callback' ),
			)
		);
		$client_id = $result['client_id'];

		// Create a token for this client.
		$user_id = $this->factory->user->create();
		Token::create( $user_id, $client_id, array( Scope::READ ) );

		// Delete the client.
		$delete_result = Client::delete( $client_id );
		$this->assertTrue( $delete_result );

		// Client should no longer exist.
		$get_result = Client::get( $client_id );
		$this->assertInstanceOf( \WP_Error::class, $get_result );
	}

	/**
	 * Test delete method with non-existent client.
	 *
	 * @covers ::delete
	 */
	public function test_delete_nonexistent() {
		$result = Client::delete( 'nonexistent-client-id' );
		$this->assertFalse( $result );
	}

	/**
	 * Test get_description method.
	 *
	 * @covers ::get_description
	 */
	public function test_get_description() {
		$result = $this->create_client(
			array(
				'name'          => 'Test Client',
				'redirect_uris' => array( 'https://example.com/callback' ),
				'description'   => 'Test client description',
			)
		);

		$client = Client::get( $result['client_id'] );

		$this->assertEquals( 'Test client description', $client->get_description() );
	}

	/**
	 * Test register method allows custom URI schemes for native apps.
	 *
	 * @covers ::register
	 */
	public function test_register_allows_custom_scheme() {
		$result = $this->create_client(
			array(
				'name'          => 'Native App',
				'redirect_uris' => array( 'com.example.app:/oauth/callback' ),
			)
		);

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'client_id', $result );
	}

	/**
	 * Test register method allows custom URI schemes with double slashes.
	 *
	 * @covers ::register
	 */
	public function test_register_allows_custom_scheme_double_slash() {
		$result = $this->create_client(
			array(
				'name'          => 'Native App',
				'redirect_uris' => array( 'myapp://callback' ),
			)
		);

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'client_id', $result );
	}

	/**
	 * Test register method allows scheme-only redirect URI.
	 *
	 * @covers ::register
	 */
	public function test_register_allows_scheme_only_redirect_uri() {
		$result = $this->create_client(
			array(
				'name'          => 'Native App',
				'redirect_uris' => array( 'activitypress://' ),
			)
		);

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'client_id', $result );

		$client = Client::get( $result['client_id'] );
		$this->assertTrue( $client->is_valid_redirect_uri( 'activitypress://' ) );
	}

	/**
	 * Test register method rejects dangerous schemes.
	 *
	 * @covers ::register
	 */
	public function test_register_rejects_dangerous_schemes() {
		$dangerous = array( 'javascript:alert(1)', 'data:text/html,test', 'vbscript:test' );

		foreach ( $dangerous as $uri ) {
			$result = Client::register(
				array(
					'name'          => 'Bad Client',
					'redirect_uris' => array( $uri ),
				)
			);

			$this->assertInstanceOf( \WP_Error::class, $result, "Should reject: $uri" );
		}
	}

	/**
	 * Test is_valid_redirect_uri works with custom schemes.
	 *
	 * @covers ::is_valid_redirect_uri
	 */
	public function test_is_valid_redirect_uri_custom_scheme() {
		$result = $this->create_client(
			array(
				'name'          => 'Native App',
				'redirect_uris' => array( 'com.example.app:/oauth/callback' ),
			)
		);

		$client = Client::get( $result['client_id'] );

		$this->assertTrue( $client->is_valid_redirect_uri( 'com.example.app:/oauth/callback' ) );
		$this->assertFalse( $client->is_valid_redirect_uri( 'com.other.app:/oauth/callback' ) );
	}


	/**
	 * Test custom scheme redirect URIs are stored and retrieved correctly.
	 *
	 * @covers ::get_redirect_uris
	 */
	public function test_custom_scheme_redirect_uris_roundtrip() {
		$uris   = array(
			'com.example.app:/oauth/callback',
			'https://example.com/callback',
		);
		$result = $this->create_client(
			array(
				'name'          => 'Hybrid App',
				'redirect_uris' => $uris,
			)
		);

		$this->assertIsArray( $result );

		$client = Client::get( $result['client_id'] );
		$stored = $client->get_redirect_uris();

		$this->assertCount( 2, $stored );
		$this->assertContains( 'com.example.app:/oauth/callback', $stored );
		$this->assertContains( 'https://example.com/callback', $stored );
	}

	/**
	 * Test get_client_id method.
	 *
	 * @covers ::get_client_id
	 */
	public function test_get_client_id() {
		$result = $this->create_client(
			array(
				'name'          => 'Test Client',
				'redirect_uris' => array( 'https://example.com/callback' ),
			)
		);

		$client = Client::get( $result['client_id'] );

		$this->assertEquals( $result['client_id'], $client->get_client_id() );
	}

	/**
	 * Test normalize_client_metadata with CIMD format.
	 *
	 * @covers ::normalize_client_metadata
	 */
	public function test_normalize_client_metadata_cimd_format() {
		$data = array(
			'client_id'     => 'https://example.com/client',
			'client_name'   => 'CIMD Client',
			'redirect_uris' => array( 'https://example.com/callback' ),
			'logo_uri'      => 'https://example.com/logo.png',
			'client_uri'    => 'https://example.com/',
		);

		$metadata = $this->call_normalize_client_metadata( $data );

		$this->assertEquals( 'https://example.com/client', $metadata['client_id'] );
		$this->assertEquals( 'CIMD Client', $metadata['client_name'] );
		$this->assertEquals( array( 'https://example.com/callback' ), $metadata['redirect_uris'] );
		$this->assertEquals( 'https://example.com/logo.png', $metadata['logo_uri'] );
		$this->assertEquals( 'https://example.com/', $metadata['client_uri'] );
		$this->assertArrayNotHasKey( 'is_actor', $metadata );
	}

	/**
	 * Test normalize_client_metadata with ActivityStreams vocabulary (no type).
	 *
	 * Client ID Metadata Documents may use ActivityStreams context with
	 * fields like "id", "name", and "redirectURI" instead of CIMD fields.
	 *
	 * @covers ::normalize_client_metadata
	 */
	public function test_normalize_client_metadata_activitystreams_vocabulary() {
		$data = array(
			'id'          => 'https://checkin.example.com/client.jsonld',
			'name'        => 'Checkin Sample App',
			'summary'     => 'A sample client for geosocial activities',
			'redirectURI' => 'https://checkin.example.com/',
		);

		$metadata = $this->call_normalize_client_metadata( $data );

		$this->assertEquals( 'https://checkin.example.com/client.jsonld', $metadata['client_id'] );
		$this->assertEquals( 'Checkin Sample App', $metadata['client_name'] );
		$this->assertEquals( array( 'https://checkin.example.com/' ), $metadata['redirect_uris'] );
		$this->assertArrayNotHasKey( 'is_actor', $metadata );
	}

	/**
	 * Test normalize_client_metadata with ActivityPub actor format.
	 *
	 * @covers ::normalize_client_metadata
	 */
	public function test_normalize_client_metadata_actor_format() {
		$data = array(
			'type'              => 'Application',
			'id'                => 'https://app.example.com/actor',
			'name'              => 'AP App',
			'preferredUsername' => 'app',
			'redirectURI'       => 'https://app.example.com/callback',
			'icon'              => array( 'url' => 'https://app.example.com/icon.png' ),
			'url'               => 'https://app.example.com/',
		);

		$metadata = $this->call_normalize_client_metadata( $data );

		$this->assertEquals( 'https://app.example.com/actor', $metadata['client_id'] );
		$this->assertEquals( 'AP App', $metadata['client_name'] );
		$this->assertEquals( array( 'https://app.example.com/callback' ), $metadata['redirect_uris'] );
		$this->assertEquals( 'https://app.example.com/icon.png', $metadata['logo_uri'] );
		$this->assertEquals( 'https://app.example.com/', $metadata['client_uri'] );
		$this->assertTrue( $metadata['is_actor'] );
	}

	/**
	 * Test CIMD fields take precedence over ActivityStreams fields.
	 *
	 * @covers ::normalize_client_metadata
	 */
	public function test_normalize_client_metadata_cimd_takes_precedence() {
		$data = array(
			'client_id'     => 'https://example.com/cimd-client',
			'client_name'   => 'CIMD Name',
			'redirect_uris' => array( 'https://example.com/cimd-callback' ),
			'id'            => 'https://example.com/as-id',
			'name'          => 'AS Name',
			'redirectURI'   => 'https://example.com/as-callback',
		);

		$metadata = $this->call_normalize_client_metadata( $data );

		$this->assertEquals( 'https://example.com/cimd-client', $metadata['client_id'] );
		$this->assertEquals( 'CIMD Name', $metadata['client_name'] );
		$this->assertEquals( array( 'https://example.com/cimd-callback' ), $metadata['redirect_uris'] );
	}

	/**
	 * Helper to call the private normalize_client_metadata method.
	 *
	 * @param array $data The raw metadata.
	 * @return array Normalized metadata.
	 */
	private function call_normalize_client_metadata( $data ) {
		$method = new \ReflectionMethod( Client::class, 'normalize_client_metadata' );
		$method->setAccessible( true );

		return $method->invoke( null, $data );
	}
}
