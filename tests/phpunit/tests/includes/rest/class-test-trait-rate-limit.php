<?php
/**
 * Test Trait Rate_Limit.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Rest;

use Activitypub\Rest\Rate_Limit;

/**
 * Test Trait Rate_Limit.
 *
 * @group rest
 * @coversDefaultClass \Activitypub\Rest\Rate_Limit
 */
class Test_Trait_Rate_Limit extends \WP_UnitTestCase {

	/**
	 * Test class instance.
	 *
	 * @var object
	 */
	protected $instance;

	/**
	 * Set up.
	 */
	public function set_up() {
		parent::set_up();

		/* Create a test class that uses the trait, with the protected method reachable. */
		$this->instance = new class() {
			use Rate_Limit;

			/**
			 * Count a request, from outside the class.
			 *
			 * @param string                $bucket  What is being limited.
			 * @param int                   $limit   The allowance.
			 * @param \WP_REST_Request|null $request The request to count, or null for a fresh one.
			 *
			 * @return true|\WP_Error
			 */
			public function count( $bucket, $limit, $request = null ) {
				// A fresh request object by default, because one request is only ever counted once.
				return $this->rate_limit( $bucket, $limit, $request ? $request : new \WP_REST_Request() );
			}
		};

		\wp_set_current_user( 0 );
		$_SERVER['REMOTE_ADDR'] = '203.0.113.60';
	}

	/**
	 * A caller may ask up to its allowance, and is refused after that.
	 *
	 * @covers ::rate_limit
	 */
	public function test_rate_limit_refuses_a_caller_that_asks_too_often() {
		for ( $i = 0; $i < 3; $i++ ) {
			$this->assertTrue( $this->instance->count( 'test_bucket', 3 ), "Call $i fits in the allowance." );
		}

		$refused = $this->instance->count( 'test_bucket', 3 );

		$this->assertWPError( $refused );
		$this->assertSame( 'activitypub_rate_limited', $refused->get_error_code() );
		$this->assertSame( 429, $refused->get_error_data()['status'] );
	}

	/**
	 * Allowances are separate per bucket, per address and per account.
	 *
	 * @covers ::rate_limit
	 */
	public function test_rate_limit_counts_per_caller_and_bucket() {
		$this->assertTrue( $this->instance->count( 'test_bucket', 1 ) );
		$this->assertWPError( $this->instance->count( 'test_bucket', 1 ), 'The bucket is spent.' );

		$this->assertTrue( $this->instance->count( 'other_bucket', 1 ), 'Another bucket has its own allowance.' );

		$_SERVER['REMOTE_ADDR'] = '203.0.113.61';
		$this->assertTrue( $this->instance->count( 'test_bucket', 1 ), 'Another address has its own allowance.' );

		\wp_set_current_user( self::factory()->user->create() );
		$this->assertTrue( $this->instance->count( 'test_bucket', 1 ), 'A signed-in caller is counted per account.' );
	}

	/**
	 * A caller that cannot be identified is refused rather than let through unlimited.
	 *
	 * @covers ::rate_limit
	 */
	public function test_rate_limit_refuses_an_unidentifiable_caller() {
		$_SERVER['REMOTE_ADDR'] = '';

		$refused = $this->instance->count( 'test_bucket', 10 );

		$this->assertWPError( $refused );
		$this->assertSame( 429, $refused->get_error_data()['status'] );
	}

	/**
	 * The window ends by itself instead of being pushed forward by every request.
	 *
	 * @covers ::rate_limit
	 */
	public function test_rate_limit_uses_a_fixed_window() {
		$this->instance->count( 'test_bucket', 5 );

		$window  = (int) \floor( \time() / MINUTE_IN_SECONDS );
		$key     = \sprintf( 'activitypub_rate_test_bucket_%s_%d', '203.0.113.60', $window );
		$timeout = (int) \get_option( '_transient_timeout_' . $key );

		$this->assertSame( 1, (int) \get_transient( $key ), 'The window holds the count.' );
		$this->assertLessThanOrEqual( \time() + MINUTE_IN_SECONDS, $timeout, 'It expires within the window.' );
	}

	/**
	 * One request's allowance stays off another request's answer.
	 *
	 * A process can dispatch more than one REST request, `rest_do_request()` being the common case,
	 * so the headers have to follow the request that was counted.
	 *
	 * @covers ::rate_limit
	 */
	public function test_rate_limit_headers_stay_with_their_own_request() {
		$counted = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/interactions' );
		$other   = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors' );

		$this->instance->count( 'test_bucket', 5, $counted );

		$response = \apply_filters( 'rest_post_dispatch', new \WP_REST_Response( array(), 200 ), \rest_get_server(), $other );

		$this->assertArrayNotHasKey( 'RateLimit-Limit', $response->get_headers(), 'A request that was not counted reports no allowance.' );

		$own = \apply_filters( 'rest_post_dispatch', new \WP_REST_Response( array(), 200 ), \rest_get_server(), $counted );

		$this->assertSame( '5', $own->get_headers()['RateLimit-Limit'], 'The counted request still gets its own.' );
	}

	/**
	 * Every answer reports the allowance, and a refusal also says when to come back.
	 *
	 * @covers ::rate_limit
	 */
	public function test_rate_limit_reports_the_allowance_in_the_headers() {
		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/interactions' );

		$this->instance->count( 'test_bucket', 2, $request );
		$allowed = \apply_filters( 'rest_post_dispatch', new \WP_REST_Response( array(), 200 ), \rest_get_server(), $request );
		$headers = $allowed->get_headers();

		$this->assertSame( '2', $headers['RateLimit-Limit'] );
		$this->assertSame( '1', $headers['RateLimit-Remaining'] );
		$this->assertArrayHasKey( 'RateLimit-Reset', $headers );
		$this->assertArrayNotHasKey( 'Retry-After', $headers, 'An allowed request needs no Retry-After.' );

		$this->instance->count( 'test_bucket', 2 );

		$refusal = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/interactions' );
		$this->instance->count( 'test_bucket', 2, $refusal );
		$refused = \apply_filters( 'rest_post_dispatch', new \WP_REST_Response( array(), 429 ), \rest_get_server(), $refusal );
		$headers = $refused->get_headers();

		$this->assertSame( '0', $headers['RateLimit-Remaining'], 'Nothing is left.' );
		$this->assertArrayHasKey( 'Retry-After', $headers, 'A refusal says when to come back.' );
	}
}
