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
	 * Test register method allows http for localhost.
	 *
	 * @covers ::register
	 */
	public function test_register_allows_localhost_http() {
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
	 * Test register method allows http for 127.0.0.1.
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
}
