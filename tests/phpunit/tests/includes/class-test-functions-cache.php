<?php
/**
 * Test file for the cache functions.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

use function Activitypub\cache_delete;
use function Activitypub\cache_get;
use function Activitypub\cache_get_multiple;
use function Activitypub\cache_set;

/**
 * Test class for the cache functions.
 *
 * @group functions
 */
class Test_Functions_Cache extends \WP_UnitTestCase {
	/**
	 * Tear down.
	 */
	public function tear_down() {
		\wp_using_ext_object_cache( false );

		parent::tear_down();
	}

	/**
	 * A miss is null, whatever the backend.
	 *
	 * @covers ::cache_get
	 */
	public function test_miss_is_null() {
		$this->assertNull( cache_get( 'object', 'https://example.com/missing' ) );
	}

	/**
	 * Without a persistent object cache the value goes through a transient.
	 *
	 * @covers ::cache_set
	 * @covers ::cache_get
	 */
	public function test_set_and_get_through_transients() {
		$id = 'https://example.com/users/alice';

		$this->assertTrue(
			cache_set(
				'actor',
				$id,
				array(
					'id'   => $id,
					'name' => 'Alice',
				),
				HOUR_IN_SECONDS
			)
		);
		$this->assertSame(
			array(
				'id'   => $id,
				'name' => 'Alice',
			),
			cache_get( 'actor', $id )
		);
		$this->assertNotFalse( \get_transient( 'activitypub_actor:' . \hash( 'sha256', $id ) ) );
	}

	/**
	 * With a persistent object cache the value goes through wp_cache and no transient is written.
	 *
	 * @covers ::cache_set
	 * @covers ::cache_get
	 */
	public function test_set_and_get_through_the_object_cache() {
		\wp_using_ext_object_cache( true );
		$id = 'https://example.com/users/alice';

		$this->assertTrue( cache_set( 'actor', $id, array( 'id' => $id ), HOUR_IN_SECONDS ) );
		$this->assertSame( array( 'id' => $id ), cache_get( 'actor', $id ) );
		$this->assertNotFalse( \wp_cache_get( 'actor:' . \hash( 'sha256', $id ), 'activitypub' ) );
		$this->assertFalse( \get_option( '_transient_activitypub_actor:' . \hash( 'sha256', $id ) ) );
	}

	/**
	 * Namespaces keep the same id apart.
	 *
	 * @covers ::cache_get
	 */
	public function test_namespaces_are_separate() {
		$id = 'https://example.com/thing';
		cache_set( 'actor', $id, array( 'kind' => 'actor' ), HOUR_IN_SECONDS );

		$this->assertNull( cache_get( 'object', $id ) );
	}

	/**
	 * Deleting removes the entry and fires the action.
	 *
	 * @covers ::cache_delete
	 */
	public function test_delete() {
		$id = 'https://example.com/users/alice';
		cache_set( 'actor', $id, array( 'id' => $id ), HOUR_IN_SECONDS );

		$this->assertTrue( cache_delete( 'actor', $id ) );
		$this->assertNull( cache_get( 'actor', $id ) );
		$this->assertSame( 1, \did_action( 'activitypub_cache_delete' ) );
	}

	/**
	 * Setting fires the action with the stored arguments.
	 *
	 * @covers ::cache_set
	 */
	public function test_set_fires_the_action() {
		$seen = array();
		\add_action(
			'activitypub_cache_set',
			function ( $kind, $id, $value, $ttl ) use ( &$seen ) {
				$seen = array( $kind, $id, $value, $ttl );
			},
			10,
			4
		);

		cache_set( 'object', 'https://example.com/1', array( 'id' => 'https://example.com/1' ), 60 );

		$this->assertSame( array( 'object', 'https://example.com/1', array( 'id' => 'https://example.com/1' ), 60 ), $seen );
	}

	/**
	 * The pre filter serves the value from elsewhere without touching the backend.
	 *
	 * @covers ::cache_get
	 */
	public function test_pre_filter_short_circuits() {
		\add_filter(
			'activitypub_pre_cache_get',
			function ( $pre, $kind, $id ) {
				return 'actor' === $kind ? array(
					'id'   => $id,
					'from' => 'elsewhere',
				) : $pre;
			},
			10,
			3
		);

		$this->assertSame(
			array(
				'id'   => 'https://example.com/x',
				'from' => 'elsewhere',
			),
			cache_get( 'actor', 'https://example.com/x' )
		);
		$this->assertNull( cache_get( 'object', 'https://example.com/x' ) );
	}

	/**
	 * Multiple ids come back keyed by id, misses as null.
	 *
	 * @covers ::cache_get_multiple
	 */
	public function test_get_multiple() {
		cache_set( 'object', 'https://example.com/1', array( 'id' => 'https://example.com/1' ), HOUR_IN_SECONDS );
		cache_set( 'object', 'https://example.com/3', array( 'id' => 'https://example.com/3' ), HOUR_IN_SECONDS );

		$result = cache_get_multiple( 'object', array( 'https://example.com/1', 'https://example.com/2', 'https://example.com/3' ) );

		$this->assertSame( array( 'https://example.com/1', 'https://example.com/2', 'https://example.com/3' ), \array_keys( $result ) );
		$this->assertSame( array( 'id' => 'https://example.com/1' ), $result['https://example.com/1'] );
		$this->assertNull( $result['https://example.com/2'] );
	}
}
