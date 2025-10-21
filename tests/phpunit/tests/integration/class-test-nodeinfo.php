<?php
/**
 * Test file for NodeInfo integration.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Integration;

use Activitypub\Integration\Nodeinfo;

/**
 * Test class for NodeInfo integration.
 *
 * @coversDefaultClass \Activitypub\Integration\Nodeinfo
 */
class Test_Nodeinfo extends \WP_UnitTestCase {
	/**
	 * Test user IDs.
	 *
	 * @var array
	 */
	protected static $user_ids = array();

	/**
	 * Create fake data before tests run.
	 *
	 * @param \WP_UnitTest_Factory $factory Helper that creates fake data.
	 */
	public static function wpSetUpBeforeClass( $factory ) {
		// Create test users with different roles.
		self::$user_ids['admin'] = $factory->user->create(
			array(
				'role' => 'administrator',
			)
		);

		self::$user_ids['author'] = $factory->user->create(
			array(
				'role' => 'author',
			)
		);

		self::$user_ids['subscriber'] = $factory->user->create(
			array(
				'role' => 'subscriber',
			)
		);

		// Give the admin user activitypub capability.
		$admin_user = get_user_by( 'id', self::$user_ids['admin'] );
		$admin_user->add_cap( 'activitypub' );
	}

	/**
	 * Clean up after tests.
	 */
	public static function wpTearDownAfterClass() {
		foreach ( self::$user_ids as $user_id ) {
			wp_delete_user( $user_id );
		}
	}

	/**
	 * Clean up after each test.
	 */
	public function tear_down() {
		// Remove filters that may have been added during tests.
		remove_filter( 'nodeinfo_data', array( Nodeinfo::class, 'add_nodeinfo_data' ), 10 );
		remove_filter( 'nodeinfo2_data', array( Nodeinfo::class, 'add_nodeinfo2_data' ) );
		remove_filter( 'wellknown_nodeinfo_data', array( Nodeinfo::class, 'add_wellknown_nodeinfo_data' ) );

		parent::tear_down();
	}

	/**
	 * Test init method registers hooks correctly.
	 *
	 * @covers ::init
	 */
	public function test_init_registers_hooks() {
		// Initialize NodeInfo integration.
		Nodeinfo::init();

		// Check that hooks are registered.
		$this->assertTrue( has_filter( 'nodeinfo_data' ) );
		$this->assertTrue( has_filter( 'nodeinfo2_data' ) );
		$this->assertTrue( has_filter( 'wellknown_nodeinfo_data' ) );
	}

	/**
	 * Data provider for NodeInfo version testing.
	 *
	 * @return array Test cases with different NodeInfo versions.
	 */
	public function nodeinfo_version_data() {
		return array(
			'version 2.0' => array(
				'version'            => '2.0',
				'expected_protocols' => array( 'activitypub' ),
			),
			'version 2.1' => array(
				'version'            => '2.1',
				'expected_protocols' => array( 'activitypub' ),
			),
		);
	}

	/**
	 * Test add_nodeinfo_data method with different versions.
	 *
	 * @dataProvider nodeinfo_version_data
	 * @covers ::add_nodeinfo_data
	 *
	 * @param string $version           The NodeInfo version.
	 * @param array  $expected_protocols The expected protocol structure.
	 */
	public function test_add_nodeinfo_data_with_versions( $version, $expected_protocols ) {
		$original_nodeinfo = array(
			'version'   => $version,
			'software'  => array(
				'name'    => 'wordpress',
				'version' => get_bloginfo( 'version' ),
			),
			'protocols' => array(),
			'usage'     => array(),
			'metadata'  => array(),
		);

		$result = Nodeinfo::add_nodeinfo_data( $original_nodeinfo, $version );

		// Check protocols are added correctly based on version.
		$this->assertEquals( $expected_protocols, $result['protocols'] );

		// Check usage data is added.
		$this->assertArrayHasKey( 'users', $result['usage'] );
		$this->assertArrayHasKey( 'total', $result['usage']['users'] );
		$this->assertArrayHasKey( 'activeMonth', $result['usage']['users'] );
		$this->assertArrayHasKey( 'activeHalfyear', $result['usage']['users'] );

		// Check metadata is added.
		$this->assertArrayHasKey( 'federation', $result['metadata'] );
		$this->assertArrayHasKey( 'staffAccounts', $result['metadata'] );
		$this->assertTrue( $result['metadata']['federation']['enabled'] );
	}

	/**
	 * Test add_nodeinfo_data preserves existing data.
	 *
	 * @covers ::add_nodeinfo_data
	 */
	public function test_add_nodeinfo_data_preserves_existing_data() {
		$original_nodeinfo = array(
			'version'   => '2.0',
			'software'  => array(
				'name'    => 'wordpress',
				'version' => get_bloginfo( 'version' ),
			),
			'protocols' => array( 'existing-protocol' ),
			'usage'     => array(
				'localPosts' => 10,
			),
			'metadata'  => array(
				'existing' => 'data',
			),
		);

		$result = Nodeinfo::add_nodeinfo_data( $original_nodeinfo, '2.0' );

		// Check that existing data is preserved.
		$this->assertEquals( get_bloginfo( 'version' ), $result['software']['version'] );
		$this->assertEquals( 10, $result['usage']['localPosts'] );
		$this->assertEquals( 'data', $result['metadata']['existing'] );

		// Check that new data is added.
		$this->assertContains( 'existing-protocol', $result['protocols'] );
		$this->assertContains( 'activitypub', $result['protocols'] );
	}

	/**
	 * Test add_nodeinfo2_data method.
	 *
	 * @covers ::add_nodeinfo2_data
	 */
	public function test_add_nodeinfo2_data() {
		$original_nodeinfo = array(
			'version'   => '1.0',
			'server'    => array(
				'baseUrl' => home_url(),
				'name'    => get_bloginfo( 'name' ),
			),
			'protocols' => array(),
			'usage'     => array(),
		);

		$result = Nodeinfo::add_nodeinfo2_data( $original_nodeinfo );

		// Check that activitypub protocol is added.
		$this->assertContains( 'activitypub', $result['protocols'] );

		// Check usage data is added.
		$this->assertArrayHasKey( 'users', $result['usage'] );
		$this->assertArrayHasKey( 'total', $result['usage']['users'] );
		$this->assertArrayHasKey( 'activeMonth', $result['usage']['users'] );
		$this->assertArrayHasKey( 'activeHalfyear', $result['usage']['users'] );

		// Check that original data is preserved.
		$this->assertEquals( home_url(), $result['server']['baseUrl'] );
		$this->assertEquals( get_bloginfo( 'name' ), $result['server']['name'] );
	}

	/**
	 * Test add_nodeinfo2_data preserves existing protocols.
	 *
	 * @covers ::add_nodeinfo2_data
	 */
	public function test_add_nodeinfo2_data_preserves_existing_protocols() {
		$original_nodeinfo = array(
			'protocols' => array( 'existing-protocol' ),
			'usage'     => array(),
		);

		$result = Nodeinfo::add_nodeinfo2_data( $original_nodeinfo );

		// Check that both existing and new protocols are present.
		$this->assertContains( 'existing-protocol', $result['protocols'] );
		$this->assertContains( 'activitypub', $result['protocols'] );
	}

	/**
	 * Test add_wellknown_nodeinfo_data method.
	 *
	 * @covers ::add_wellknown_nodeinfo_data
	 */
	public function test_add_wellknown_nodeinfo_data() {
		$original_data = array(
			'links' => array(
				array(
					'rel'  => 'http://nodeinfo.diaspora.software/ns/schema/2.0',
					'href' => home_url( '/.well-known/nodeinfo/2.0' ),
				),
			),
		);

		$result = Nodeinfo::add_wellknown_nodeinfo_data( $original_data );

		// Check that original links are preserved.
		$this->assertCount( 2, $result['links'] );
		$this->assertEquals( 'http://nodeinfo.diaspora.software/ns/schema/2.0', $result['links'][0]['rel'] );

		// Check that ActivityStreams Application link is added.
		$activitystreams_link = null;
		foreach ( $result['links'] as $link ) {
			if ( 'https://www.w3.org/ns/activitystreams#Application' === $link['rel'] ) {
				$activitystreams_link = $link;
				break;
			}
		}

		$this->assertNotNull( $activitystreams_link, 'ActivityStreams Application link should be added' );
		$this->assertTrue( false !== strpos( $activitystreams_link['href'], '/application' ), 'Link should contain /application' );
	}

	/**
	 * Test add_wellknown_nodeinfo_data handles empty data.
	 *
	 * @covers ::add_wellknown_nodeinfo_data
	 */
	public function test_add_wellknown_nodeinfo_data_handles_empty_data() {
		$original_data = array();

		$result = Nodeinfo::add_wellknown_nodeinfo_data( $original_data );

		// Check that links array is created.
		$this->assertArrayHasKey( 'links', $result );
		$this->assertCount( 1, $result['links'] );

		// Check that ActivityStreams Application link is added.
		$this->assertEquals( 'https://www.w3.org/ns/activitystreams#Application', $result['links'][0]['rel'] );
		$this->assertTrue( false !== strpos( $result['links'][0]['href'], '/application' ), 'Link should contain /application' );
	}

	/**
	 * Test user statistics in NodeInfo data.
	 *
	 * @covers ::add_nodeinfo_data
	 */
	public function test_nodeinfo_user_statistics() {
		$original_nodeinfo = array(
			'protocols' => array(),
			'usage'     => array(),
			'metadata'  => array(),
		);

		$result = Nodeinfo::add_nodeinfo_data( $original_nodeinfo, '2.0' );

		// Check that user statistics are numeric.
		$this->assertIsNumeric( $result['usage']['users']['total'] );
		$this->assertIsNumeric( $result['usage']['users']['activeMonth'] );
		$this->assertIsNumeric( $result['usage']['users']['activeHalfyear'] );

		// Check that the values are reasonable (not negative).
		$this->assertGreaterThanOrEqual( 0, $result['usage']['users']['total'] );
		$this->assertGreaterThanOrEqual( 0, $result['usage']['users']['activeMonth'] );
		$this->assertGreaterThanOrEqual( 0, $result['usage']['users']['activeHalfyear'] );
	}

	/**
	 * Test staff accounts in NodeInfo metadata.
	 *
	 * @covers ::add_nodeinfo_data
	 */
	public function test_nodeinfo_staff_accounts() {
		$original_nodeinfo = array(
			'protocols' => array(),
			'usage'     => array(),
			'metadata'  => array(),
		);

		$result = Nodeinfo::add_nodeinfo_data( $original_nodeinfo, '2.0' );

		// Check that staffAccounts is an array.
		$this->assertIsArray( $result['metadata']['staffAccounts'] );

		// Check that staff accounts contain the admin user we created.
		$this->assertGreaterThanOrEqual( 1, count( $result['metadata']['staffAccounts'] ) );

		// Check that staff account entries look like WebFinger resources.
		foreach ( $result['metadata']['staffAccounts'] as $staff_account ) {
			$this->assertIsString( $staff_account );
			// WebFinger resources typically contain @ symbol.
			$this->assertTrue( false !== strpos( $staff_account, '@' ), 'Staff account should contain @ symbol' );
		}
	}

	/**
	 * Test federation metadata.
	 *
	 * @covers ::add_nodeinfo_data
	 */
	public function test_nodeinfo_federation_metadata() {
		$original_nodeinfo = array(
			'protocols' => array(),
			'usage'     => array(),
			'metadata'  => array(),
		);

		$result = Nodeinfo::add_nodeinfo_data( $original_nodeinfo, '2.0' );

		// Check that federation is enabled.
		$this->assertArrayHasKey( 'federation', $result['metadata'] );
		$this->assertArrayHasKey( 'enabled', $result['metadata']['federation'] );
		$this->assertTrue( $result['metadata']['federation']['enabled'] );
	}

	/**
	 * Test that the class methods are static.
	 *
	 * @covers ::init
	 * @covers ::add_nodeinfo_data
	 * @covers ::add_nodeinfo2_data
	 * @covers ::add_wellknown_nodeinfo_data
	 */
	public function test_methods_are_static() {
		$reflection = new \ReflectionClass( Nodeinfo::class );

		$methods = array( 'init', 'add_nodeinfo_data', 'add_nodeinfo2_data', 'add_wellknown_nodeinfo_data' );

		foreach ( $methods as $method_name ) {
			$method = $reflection->getMethod( $method_name );
			$this->assertTrue( $method->isStatic(), "Method {$method_name} should be static" );
		}
	}

	/**
	 * Test integration with actual WordPress hooks.
	 *
	 * @covers ::init
	 * @covers ::add_nodeinfo_data
	 * @covers ::add_nodeinfo2_data
	 * @covers ::add_wellknown_nodeinfo_data
	 */
	public function test_integration_with_hooks() {
		// Initialize the integration.
		Nodeinfo::init();

		// Test nodeinfo_data filter.
		$nodeinfo_data = apply_filters( 'nodeinfo_data', array( 'protocols' => array() ), '2.0' );
		$this->assertContains( 'activitypub', $nodeinfo_data['protocols'] );

		// Test nodeinfo2_data filter.
		$nodeinfo2_data = apply_filters( 'nodeinfo2_data', array( 'protocols' => array() ) );
		$this->assertContains( 'activitypub', $nodeinfo2_data['protocols'] );

		// Test wellknown_nodeinfo_data filter.
		$wellknown_data = apply_filters( 'wellknown_nodeinfo_data', array() );
		$this->assertArrayHasKey( 'links', $wellknown_data );
	}
}
