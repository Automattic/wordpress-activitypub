<?php
/**
 * Test file for OAuth Scope class.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\OAuth;

use Activitypub\OAuth\Scope;

/**
 * Test class for OAuth Scope.
 *
 * @coversDefaultClass \Activitypub\OAuth\Scope
 *
 * @group activitypub
 * @group oauth
 */
class Test_Scope extends \WP_UnitTestCase {

	/**
	 * Test that all scope constants are defined.
	 */
	public function test_scope_constants_defined() {
		$this->assertEquals( 'read', Scope::READ );
		$this->assertEquals( 'write', Scope::WRITE );
		$this->assertEquals( 'follow', Scope::FOLLOW );
		$this->assertEquals( 'push', Scope::PUSH );
		$this->assertEquals( 'profile', Scope::PROFILE );
	}

	/**
	 * Test ALL constant contains all scopes.
	 */
	public function test_all_scopes_constant() {
		$this->assertContains( Scope::READ, Scope::ALL );
		$this->assertContains( Scope::WRITE, Scope::ALL );
		$this->assertContains( Scope::FOLLOW, Scope::ALL );
		$this->assertContains( Scope::PUSH, Scope::ALL );
		$this->assertContains( Scope::PROFILE, Scope::ALL );
		$this->assertCount( 5, Scope::ALL );
	}

	/**
	 * Test parse method with space-separated string.
	 *
	 * @covers ::parse
	 */
	public function test_parse_space_separated() {
		$result = Scope::parse( 'read write follow' );
		$this->assertEquals( array( 'read', 'write', 'follow' ), $result );
	}

	/**
	 * Test parse method with single scope.
	 *
	 * @covers ::parse
	 */
	public function test_parse_single_scope() {
		$result = Scope::parse( 'read' );
		$this->assertEquals( array( 'read' ), $result );
	}

	/**
	 * Test parse method with empty string.
	 *
	 * @covers ::parse
	 */
	public function test_parse_empty_string() {
		$result = Scope::parse( '' );
		$this->assertEquals( array(), $result );
	}

	/**
	 * Test parse method with null.
	 *
	 * @covers ::parse
	 */
	public function test_parse_null() {
		$result = Scope::parse( null );
		$this->assertEquals( array(), $result );
	}

	/**
	 * Test parse method with extra whitespace.
	 *
	 * @covers ::parse
	 */
	public function test_parse_extra_whitespace() {
		$result = Scope::parse( '  read   write  ' );
		$this->assertEquals( array( 'read', 'write' ), $result );
	}

	/**
	 * Test validate method with valid scopes array.
	 *
	 * @covers ::validate
	 */
	public function test_validate_valid_array() {
		$result = Scope::validate( array( 'read', 'write' ) );
		$this->assertEquals( array( 'read', 'write' ), $result );
	}

	/**
	 * Test validate method with string input.
	 *
	 * @covers ::validate
	 */
	public function test_validate_string_input() {
		$result = Scope::validate( 'read write follow' );
		$this->assertEquals( array( 'read', 'write', 'follow' ), $result );
	}

	/**
	 * Test validate method filters out invalid scopes.
	 *
	 * @covers ::validate
	 */
	public function test_validate_filters_invalid() {
		$result = Scope::validate( array( 'read', 'invalid', 'write' ) );
		$this->assertEquals( array( 'read', 'write' ), $result );
	}

	/**
	 * Test validate method returns defaults for empty input.
	 *
	 * @covers ::validate
	 */
	public function test_validate_empty_returns_defaults() {
		$result = Scope::validate( array() );
		$this->assertEquals( Scope::DEFAULT_SCOPES, $result );
	}

	/**
	 * Test validate method returns defaults for all-invalid input.
	 *
	 * @covers ::validate
	 */
	public function test_validate_all_invalid_returns_defaults() {
		$result = Scope::validate( array( 'invalid1', 'invalid2' ) );
		$this->assertEquals( Scope::DEFAULT_SCOPES, $result );
	}

	/**
	 * Test validate method with non-array input.
	 *
	 * @covers ::validate
	 */
	public function test_validate_non_array_returns_defaults() {
		$result = Scope::validate( 123 );
		$this->assertEquals( Scope::DEFAULT_SCOPES, $result );
	}

	/**
	 * Test to_string method.
	 *
	 * @covers ::to_string
	 */
	public function test_to_string() {
		$result = Scope::to_string( array( 'read', 'write', 'follow' ) );
		$this->assertEquals( 'read write follow', $result );
	}

	/**
	 * Test to_string method with empty array.
	 *
	 * @covers ::to_string
	 */
	public function test_to_string_empty() {
		$result = Scope::to_string( array() );
		$this->assertEquals( '', $result );
	}

	/**
	 * Test to_string method with non-array.
	 *
	 * @covers ::to_string
	 */
	public function test_to_string_non_array() {
		$result = Scope::to_string( 'not an array' );
		$this->assertEquals( '', $result );
	}

	/**
	 * Test is_valid method with valid scope.
	 *
	 * @covers ::is_valid
	 */
	public function test_is_valid_true() {
		$this->assertTrue( Scope::is_valid( 'read' ) );
		$this->assertTrue( Scope::is_valid( 'write' ) );
		$this->assertTrue( Scope::is_valid( 'follow' ) );
		$this->assertTrue( Scope::is_valid( 'push' ) );
		$this->assertTrue( Scope::is_valid( 'profile' ) );
	}

	/**
	 * Test is_valid method with invalid scope.
	 *
	 * @covers ::is_valid
	 */
	public function test_is_valid_false() {
		$this->assertFalse( Scope::is_valid( 'invalid' ) );
		$this->assertFalse( Scope::is_valid( '' ) );
		$this->assertFalse( Scope::is_valid( 'READ' ) ); // Case sensitive.
	}

	/**
	 * Test get_description method.
	 *
	 * @covers ::get_description
	 */
	public function test_get_description() {
		$this->assertNotEmpty( Scope::get_description( 'read' ) );
		$this->assertNotEmpty( Scope::get_description( 'write' ) );
	}

	/**
	 * Test get_description method with invalid scope.
	 *
	 * @covers ::get_description
	 */
	public function test_get_description_invalid() {
		$this->assertEquals( '', Scope::get_description( 'invalid' ) );
	}

	/**
	 * Test get_all_with_descriptions method.
	 *
	 * @covers ::get_all_with_descriptions
	 */
	public function test_get_all_with_descriptions() {
		$result = Scope::get_all_with_descriptions();
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'read', $result );
		$this->assertArrayHasKey( 'write', $result );
		$this->assertArrayHasKey( 'follow', $result );
		$this->assertArrayHasKey( 'push', $result );
		$this->assertArrayHasKey( 'profile', $result );
	}

	/**
	 * Test contains method with scope present.
	 *
	 * @covers ::contains
	 */
	public function test_contains_true() {
		$scopes = array( 'read', 'write' );
		$this->assertTrue( Scope::contains( $scopes, 'read' ) );
		$this->assertTrue( Scope::contains( $scopes, 'write' ) );
	}

	/**
	 * Test contains method with scope not present.
	 *
	 * @covers ::contains
	 */
	public function test_contains_false() {
		$scopes = array( 'read', 'write' );
		$this->assertFalse( Scope::contains( $scopes, 'follow' ) );
	}

	/**
	 * Test contains method with non-array.
	 *
	 * @covers ::contains
	 */
	public function test_contains_non_array() {
		$this->assertFalse( Scope::contains( 'not an array', 'read' ) );
	}

	/**
	 * Test sanitize method with string.
	 *
	 * @covers ::sanitize
	 */
	public function test_sanitize_string() {
		$result = Scope::sanitize( 'read write invalid' );
		$this->assertEquals( array( 'read', 'write' ), $result );
	}

	/**
	 * Test sanitize method with array.
	 *
	 * @covers ::sanitize
	 */
	public function test_sanitize_array() {
		$result = Scope::sanitize( array( 'read', 'invalid', 'write' ) );
		$this->assertEquals( array( 'read', 'write' ), $result );
	}

	/**
	 * Test sanitize method with non-array/non-string.
	 *
	 * @covers ::sanitize
	 */
	public function test_sanitize_invalid_type() {
		$result = Scope::sanitize( 123 );
		$this->assertEquals( array(), $result );
	}

	/**
	 * Canonical SWICG Basic Profile read scopes collapse to the internal `read` scope.
	 *
	 * @covers ::validate
	 * @covers ::normalize
	 *
	 * @dataProvider data_canonical_read_aliases
	 *
	 * @param string $canonical Canonical Basic Profile scope identifier.
	 */
	public function test_validate_normalizes_canonical_read_aliases( $canonical ) {
		$this->assertEquals( array( Scope::READ ), Scope::validate( $canonical ) );
	}

	/**
	 * Data provider for canonical read aliases.
	 *
	 * @return array<string, array{0:string}>
	 */
	public function data_canonical_read_aliases() {
		return array(
			'umbrella'  => array( 'activitypub:read:all' ),
			'inbox'     => array( 'activitypub:read:me:inbox' ),
			'outbox'    => array( 'activitypub:read:me:outbox' ),
			'followers' => array( 'activitypub:read:me:followers' ),
		);
	}

	/**
	 * Canonical SWICG Basic Profile write scopes collapse to the internal `write` scope.
	 *
	 * @covers ::validate
	 * @covers ::normalize
	 *
	 * @dataProvider data_canonical_write_aliases
	 *
	 * @param string $canonical Canonical Basic Profile scope identifier.
	 */
	public function test_validate_normalizes_canonical_write_aliases( $canonical ) {
		$this->assertEquals( array( Scope::WRITE ), Scope::validate( $canonical ) );
	}

	/**
	 * Data provider for canonical write aliases.
	 *
	 * @return array<string, array{0:string}>
	 */
	public function data_canonical_write_aliases() {
		return array(
			'umbrella' => array( 'activitypub:write:all' ),
			'create'   => array( 'activitypub:write:create' ),
			'follow'   => array( 'activitypub:write:follow' ),
			'like'     => array( 'activitypub:write:like' ),
		);
	}

	/**
	 * Mixed legacy + canonical names dedupe to a single read/write pair.
	 *
	 * @covers ::validate
	 */
	public function test_validate_dedupes_mixed_canonical_and_legacy_aliases() {
		$result = Scope::validate( 'read activitypub:read:me:inbox write activitypub:write:all' );
		$this->assertEquals( array( Scope::READ, Scope::WRITE ), $result );
	}

	/**
	 * Supported() advertises both internal scopes and Basic Profile canonical aliases.
	 *
	 * @covers ::supported
	 */
	public function test_supported_includes_canonical_aliases() {
		$supported = Scope::supported();

		// Internal scopes still advertised for backwards-compatible clients.
		$this->assertContains( Scope::READ, $supported );
		$this->assertContains( Scope::WRITE, $supported );

		// Basic Profile canonical aliases are now discoverable.
		$this->assertContains( 'activitypub:read:all', $supported );
		$this->assertContains( 'activitypub:write:all', $supported );
	}
}
