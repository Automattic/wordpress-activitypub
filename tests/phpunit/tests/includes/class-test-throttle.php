<?php
/**
 * Test file for Throttle.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

use Activitypub\Throttle;

/**
 * Test class for Throttle.
 *
 * @coversDefaultClass \Activitypub\Throttle
 */
class Test_Throttle extends \WP_UnitTestCase {
	/**
	 * A caller's allowance runs out, other callers keep theirs.
	 *
	 * @covers ::count
	 */
	public function test_count_runs_out_per_caller() {
		$this->assertTrue( Throttle::count( 'test', 'a', 2 )['allowed'] );

		$second = Throttle::count( 'test', 'a', 2 );
		$this->assertTrue( $second['allowed'] );
		$this->assertSame( 0, $second['remaining'] );

		$this->assertFalse( Throttle::count( 'test', 'a', 2 )['allowed'] );
		$this->assertTrue( Throttle::count( 'test', 'b', 2 )['allowed'], 'Another caller has its own allowance.' );
		$this->assertTrue( Throttle::count( 'other', 'a', 2 )['allowed'], 'Another bucket has its own allowance.' );
	}

	/**
	 * Without a caller there is nothing to count, so the use is refused.
	 *
	 * @covers ::count
	 */
	public function test_count_refuses_an_empty_caller() {
		$this->assertFalse( Throttle::count( 'test', '', 10 )['allowed'] );
	}

	/**
	 * The window decides when the allowance starts again.
	 *
	 * @covers ::count
	 */
	public function test_count_reports_the_end_of_its_window() {
		$entry = Throttle::count( 'test', 'a', 10, HOUR_IN_SECONDS );

		$this->assertSame( 0, $entry['reset'] % HOUR_IN_SECONDS );
		$this->assertGreaterThan( \time(), $entry['reset'] );
		$this->assertLessThanOrEqual( \time() + HOUR_IN_SECONDS, $entry['reset'] );
	}

	/**
	 * The `activitypub_rate_limit` filter changes the allowance of a bucket.
	 *
	 * @covers ::count
	 */
	public function test_count_limit_is_filterable() {
		$filter = static function ( $limit, $bucket ) {
			return 'test' === $bucket ? 1 : $limit;
		};
		\add_filter( 'activitypub_rate_limit', $filter, 10, 2 );

		$first  = Throttle::count( 'test', 'a', 10 );
		$second = Throttle::count( 'test', 'a', 10 );

		\remove_filter( 'activitypub_rate_limit', $filter, 10 );

		$this->assertTrue( $first['allowed'] );
		$this->assertFalse( $second['allowed'] );
	}
}
