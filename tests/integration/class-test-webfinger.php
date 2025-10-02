<?php
/**
 * Test file for WebFinger integration.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Integration;

use Activitypub\Collection\Actors;
use Activitypub\Integration\Webfinger;

/**
 * Test class for WebFinger integration.
 *
 * @coversDefaultClass \Activitypub\Integration\Webfinger
 */
class Test_Webfinger extends \WP_UnitTestCase {
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
		self::$user_ids['author'] = $factory->user->create(
			array(
				'role'         => 'author',
				'user_login'   => 'testauthor',
				'display_name' => 'Test Author',
			)
		);

		self::$user_ids['admin'] = $factory->user->create(
			array(
				'role'         => 'administrator',
				'user_login'   => 'testadmin',
				'display_name' => 'Test Admin',
			)
		);

		// Give users activitypub capability.
		$author = get_user_by( 'id', self::$user_ids['author'] );
		$author->add_cap( 'activitypub' );

		$admin = get_user_by( 'id', self::$user_ids['admin'] );
		$admin->add_cap( 'activitypub' );
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
		remove_filter( 'webfinger_user_data', array( Webfinger::class, 'add_user_discovery' ), 1 );
		remove_filter( 'webfinger_data', array( Webfinger::class, 'add_pseudo_user_discovery' ), 1 );
		remove_filter( 'webfinger_user_data', array( Webfinger::class, 'add_interaction_links' ), 1 );
		remove_filter( 'webfinger_data', array( Webfinger::class, 'add_interaction_links' ), 1 );

		parent::tear_down();
	}

	/**
	 * Test init method registers hooks correctly.
	 *
	 * @covers ::init
	 */
	public function test_init_registers_hooks() {
		// Initialize WebFinger integration.
		Webfinger::init();

		// Check that hooks are registered.
		$this->assertNotFalse( has_filter( 'webfinger_user_data', array( Webfinger::class, 'add_user_discovery' ) ) );
		$this->assertNotFalse( has_filter( 'webfinger_data', array( Webfinger::class, 'add_pseudo_user_discovery' ) ) );
		$this->assertNotFalse( has_filter( 'webfinger_data', array( Webfinger::class, 'add_interaction_links' ) ) );
	}

	/**
	 * Test add_user_discovery method.
	 *
	 * @covers ::add_user_discovery
	 */
	public function test_add_user_discovery() {
		$user        = get_user_by( 'id', self::$user_ids['author'] );
		$actor       = Actors::get_by_id( $user->ID );
		$uri         = 'acct:' . $user->user_login . '@' . wp_parse_url( home_url(), PHP_URL_HOST );
		$initial_jrd = array(
			'subject' => $uri,
			'aliases' => array(),
			'links'   => array(),
		);

		$result = Webfinger::add_user_discovery( $initial_jrd, $uri, $user );

		// Check subject is set correctly.
		$this->assertArrayHasKey( 'subject', $result );
		$this->assertStringContainsString( 'acct:', $result['subject'] );
		$this->assertStringContainsString( $user->user_login, $result['subject'] );

		// Check aliases are added.
		$this->assertArrayHasKey( 'aliases', $result );
		$this->assertIsArray( $result['aliases'] );
		$this->assertContains( $actor->get_id(), $result['aliases'] );
		$this->assertContains( $actor->get_url(), $result['aliases'] );

		// Check that aliases are unique.
		$this->assertCount( count( $result['aliases'] ), array_unique( $result['aliases'] ) );

		// Check that ActivityPub self link is added.
		$self_link = null;
		foreach ( $result['links'] as $link ) {
			if ( 'self' === $link['rel'] && 'application/activity+json' === $link['type'] ) {
				$self_link = $link;
				break;
			}
		}
		$this->assertNotNull( $self_link, 'Should have ActivityPub self link' );
		$this->assertEquals( $actor->get_id(), $self_link['href'] );
	}

	/**
	 * Test add_user_discovery with invalid user.
	 *
	 * @covers ::add_user_discovery
	 */
	public function test_add_user_discovery_with_invalid_user() {
		$user = get_user_by( 'id', 99999 ); // Non-existent user.
		if ( ! $user ) {
			$user = new \WP_User();
		}

		$initial_jrd = array(
			'subject' => 'acct:invalid@example.com',
			'aliases' => array(),
			'links'   => array(),
		);

		$result = Webfinger::add_user_discovery( $initial_jrd, 'acct:invalid@example.com', $user );

		// Should return original jrd unchanged.
		$this->assertEquals( $initial_jrd, $result );
	}

	/**
	 * Test add_pseudo_user_discovery method.
	 *
	 * @covers ::add_pseudo_user_discovery
	 */
	public function test_add_pseudo_user_discovery() {
		$user = get_user_by( 'id', self::$user_ids['author'] );
		$uri  = 'acct:' . $user->user_login . '@' . wp_parse_url( home_url(), PHP_URL_HOST );

		$initial_jrd = array(
			'subject' => $uri,
			'aliases' => array(),
			'links'   => array(),
		);

		$result = Webfinger::add_pseudo_user_discovery( $initial_jrd, $uri );

		// Check that result is an array (not WP_Error).
		$this->assertIsArray( $result );

		// Check subject is set.
		$this->assertArrayHasKey( 'subject', $result );
		$this->assertStringContainsString( 'acct:', $result['subject'] );

		// Check aliases are set.
		$this->assertArrayHasKey( 'aliases', $result );
		$this->assertIsArray( $result['aliases'] );
		$this->assertGreaterThan( 0, count( $result['aliases'] ) );

		// Check links are set.
		$this->assertArrayHasKey( 'links', $result );
		$this->assertIsArray( $result['links'] );
		$this->assertGreaterThan( 0, count( $result['links'] ) );

		// Check for ActivityPub self link.
		$has_activitypub_link = false;
		foreach ( $result['links'] as $link ) {
			if ( 'self' === $link['rel'] && 'application/activity+json' === $link['type'] ) {
				$has_activitypub_link = true;
				break;
			}
		}
		$this->assertTrue( $has_activitypub_link, 'Should have ActivityPub self link' );

		// Check for profile page link.
		$has_profile_link = false;
		foreach ( $result['links'] as $link ) {
			if ( 'http://webfinger.net/rel/profile-page' === $link['rel'] ) {
				$has_profile_link = true;
				break;
			}
		}
		$this->assertTrue( $has_profile_link, 'Should have profile page link' );
	}

	/**
	 * Test add_pseudo_user_discovery with invalid resource.
	 *
	 * @covers ::add_pseudo_user_discovery
	 */
	public function test_add_pseudo_user_discovery_with_invalid_resource() {
		$initial_jrd = array(
			'subject' => 'acct:invalid@invalid.example',
			'aliases' => array(),
			'links'   => array(),
		);

		$result = Webfinger::add_pseudo_user_discovery( $initial_jrd, 'acct:invalid@invalid.example' );

		// Should return WP_Error for invalid resource.
		$this->assertInstanceOf( 'WP_Error', $result );
	}

	/**
	 * Test add_interaction_links method.
	 *
	 * @covers ::add_interaction_links
	 */
	public function test_add_interaction_links() {
		$user = get_user_by( 'id', self::$user_ids['author'] );
		$uri  = 'acct:' . $user->user_login . '@' . wp_parse_url( home_url(), PHP_URL_HOST );

		$initial_jrd = array(
			'subject' => $uri,
			'aliases' => array(),
			'links'   => array(),
		);

		$result = Webfinger::add_interaction_links( $initial_jrd );

		// Check that links were added.
		$this->assertArrayHasKey( 'links', $result );
		$this->assertIsArray( $result['links'] );

		// Count interaction links added.
		$interaction_link_count = 0;
		$found_rels             = array();

		foreach ( $result['links'] as $link ) {
			if ( isset( $link['template'] ) ) {
				++$interaction_link_count;
				$found_rels[] = $link['rel'];
			}
		}

		// Should have added 3 interaction links.
		$this->assertEquals( 3, $interaction_link_count );

		// Check for OStatus subscribe link.
		$this->assertContains( 'http://ostatus.org/schema/1.0/subscribe', $found_rels );

		// Check for FEP-3b86 Create link.
		$this->assertContains( 'https://w3id.org/fep/3b86/Create', $found_rels );

		// Check for FEP-3b86 Follow link.
		$this->assertContains( 'https://w3id.org/fep/3b86/Follow', $found_rels );
	}

	/**
	 * Test interaction links have correct templates.
	 *
	 * @covers ::add_interaction_links
	 */
	public function test_add_interaction_links_templates() {
		$initial_jrd = array( 'links' => array() );
		$result      = Webfinger::add_interaction_links( $initial_jrd );

		// Check templates contain required placeholders.
		foreach ( $result['links'] as $link ) {
			if ( ! isset( $link['template'] ) ) {
				continue;
			}

			$this->assertIsString( $link['template'] );
			$this->assertStringContainsString( 'interactions', $link['template'] );

			// Check that template has the right placeholder based on rel.
			if ( 'http://ostatus.org/schema/1.0/subscribe' === $link['rel'] ) {
				$this->assertStringContainsString( '{uri}', $link['template'] );
			} elseif ( 'https://w3id.org/fep/3b86/Create' === $link['rel'] ) {
				$this->assertStringContainsString( '{inReplyTo}', $link['template'] );
				$this->assertStringContainsString( 'intent=create', $link['template'] );
			} elseif ( 'https://w3id.org/fep/3b86/Follow' === $link['rel'] ) {
				$this->assertStringContainsString( '{object}', $link['template'] );
				$this->assertStringContainsString( 'intent=follow', $link['template'] );
			}
		}
	}

	/**
	 * Test that methods are static.
	 *
	 * @covers ::init
	 * @covers ::add_user_discovery
	 * @covers ::add_pseudo_user_discovery
	 * @covers ::add_interaction_links
	 */
	public function test_methods_are_static() {
		$reflection = new \ReflectionClass( Webfinger::class );

		$methods = array( 'init', 'add_user_discovery', 'add_pseudo_user_discovery', 'add_interaction_links' );

		foreach ( $methods as $method_name ) {
			$method = $reflection->getMethod( $method_name );
			$this->assertTrue( $method->isStatic(), "Method {$method_name} should be static" );
		}
	}

	/**
	 * Test integration with actual WordPress hooks.
	 *
	 * @covers ::init
	 * @covers ::add_user_discovery
	 * @covers ::add_pseudo_user_discovery
	 * @covers ::add_interaction_links
	 */
	public function test_integration_with_hooks() {
		// Initialize the integration.
		Webfinger::init();

		$user = get_user_by( 'id', self::$user_ids['author'] );
		$uri  = 'acct:' . $user->user_login . '@' . wp_parse_url( home_url(), PHP_URL_HOST );

		$initial_jrd = array(
			'subject' => $uri,
			'aliases' => array(),
			'links'   => array(),
		);

		// Test webfinger_user_data filter.
		$user_data = apply_filters( 'webfinger_user_data', $initial_jrd, $uri, $user );
		$this->assertArrayHasKey( 'subject', $user_data );
		$this->assertArrayHasKey( 'aliases', $user_data );
		$this->assertArrayHasKey( 'links', $user_data );

		// Test webfinger_data filter.
		$data = apply_filters( 'webfinger_data', $initial_jrd, $uri );
		if ( ! is_wp_error( $data ) ) {
			$this->assertArrayHasKey( 'links', $data );
		}
	}
}
