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
	 * The `REMOTE_ADDR` the process had before the test, or null when it had none.
	 *
	 * @var string|null
	 */
	private $remote_addr;

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

		$this->remote_addr      = $_SERVER['REMOTE_ADDR'] ?? null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Remembered only to be put back.
		$_SERVER['REMOTE_ADDR'] = '203.0.113.60';
	}

	/**
	 * Tear down.
	 *
	 * The hook callbacks are restored by the WordPress test case. The trait's memo is a static and the
	 * address is process state, neither is restored for us, so both are put back here to keep each
	 * test, and every test class after this one, to its own requests.
	 */
	public function tear_down() {
		$this->counted()->setValue( null, array() );

		if ( null === $this->remote_addr ) {
			unset( $_SERVER['REMOTE_ADDR'] );
		} else {
			$_SERVER['REMOTE_ADDR'] = $this->remote_addr;
		}

		\wp_set_current_user( 0 );

		parent::tear_down();
	}

	/**
	 * The trait's memo, which is private.
	 *
	 * @return \ReflectionProperty The property; `getValue()` reads it.
	 */
	private function counted() {
		$counted = new \ReflectionProperty( \get_class( $this->instance ), 'counted' );
		$counted->setAccessible( true );

		return $counted;
	}

	/**
	 * How many callbacks sit on `rest_post_dispatch` at the trait's priority.
	 *
	 * @return int The count.
	 */
	private function post_dispatch_callbacks() {
		global $wp_filter;

		return \count( $wp_filter['rest_post_dispatch']->callbacks[10] ?? array() );
	}

	/**
	 * The transient the trait writes for a caller in the current window.
	 *
	 * The window is part of the name, so a test that reads the transient must not straddle a minute
	 * boundary; callers check `window()` before and after the counted request.
	 *
	 * @param string $caller The caller as the trait keys it: an address, or `user-<id>`.
	 *
	 * @return string The transient name.
	 */
	private function transient_key( $caller ) {
		return \sprintf( 'activitypub_rate_%s_%s_%d', 'test_bucket', \md5( $caller ), $this->window() );
	}

	/**
	 * The current minute, the way the trait counts it.
	 *
	 * @return int The window number.
	 */
	private function window() {
		return (int) \floor( \time() / MINUTE_IN_SECONDS );
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
	 * One request spends one unit, however often it is asked about.
	 *
	 * Core asks a permission callback again after dispatch, in `rest_send_allow_header()`, so without
	 * this the sixth request of an allowance of ten was refused.
	 *
	 * @covers ::rate_limit
	 */
	public function test_rate_limit_counts_one_request_once() {
		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/interactions' );
		$window  = $this->window();

		$this->assertTrue( $this->instance->count( 'test_bucket', 3, $request ) );
		$this->assertTrue( $this->instance->count( 'test_bucket', 3, $request ), 'The same request is allowed again.' );

		if ( $window !== $this->window() ) {
			$this->markTestSkipped( 'The minute turned over during the test, so the transient has a different name.' );
		}

		$this->assertSame( 1, (int) \get_transient( $this->transient_key( '203.0.113.60' ) ), 'And it was only counted once.' );
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
		$this->assertFalse( \get_transient( $this->transient_key( '' ) ), 'Unidentifiable callers share no bucket.' );
	}

	/**
	 * The window ends by itself instead of being pushed forward by every request.
	 *
	 * @covers ::rate_limit
	 */
	public function test_rate_limit_uses_a_fixed_window() {
		$window = $this->window();

		$this->instance->count( 'test_bucket', 5 );

		if ( $window !== $this->window() ) {
			$this->markTestSkipped( 'The minute turned over during the test, so the transient has a different name.' );
		}

		$key     = $this->transient_key( '203.0.113.60' );
		$timeout = (int) \get_option( '_transient_timeout_' . $key );

		$this->assertSame( 1, (int) \get_transient( $key ), 'The window holds the count.' );
		$this->assertLessThanOrEqual( \time() + MINUTE_IN_SECONDS, $timeout, 'It expires within the window.' );
	}

	/**
	 * An answer that carries one caller's allowance is not stored by a shared cache.
	 *
	 * @covers ::rate_limit
	 */
	public function test_rate_limit_keeps_its_answer_out_of_shared_caches() {
		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/interactions' );

		$this->instance->count( 'test_bucket', 5, $request );

		$response = \apply_filters( 'rest_post_dispatch', new \WP_REST_Response( array(), 200 ), \rest_get_server(), $request );
		$headers  = $response->get_headers();

		$this->assertStringContainsString( 'no-store', $headers['Cache-Control'] );

		// An endpoint that already said how it may be cached keeps its own directive.
		$own     = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/oauth/token' );
		$carries = new \WP_REST_Response( array(), 429 );
		$carries->header( 'Cache-Control', 'no-store' );

		$this->instance->count( 'other_bucket', 5, $own );
		$answer = \apply_filters( 'rest_post_dispatch', $carries, \rest_get_server(), $own );

		$this->assertSame( 'no-store', $answer->get_headers()['Cache-Control'] );
	}

	/**
	 * Counting adds one callback for the class, however many requests are counted.
	 *
	 * Core re-asks the permission callback for the `Allow` header, and a process may count many
	 * requests; neither may leave anything behind on the hook.
	 *
	 * @covers ::rate_limit
	 */
	public function test_rate_limit_registers_one_callback_for_the_class() {
		$first  = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/interactions' );
		$second = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/interactions' );
		$before = $this->post_dispatch_callbacks();

		$this->instance->count( 'test_bucket', 0, $first );
		$this->instance->count( 'test_bucket', 0, $first );
		$this->instance->count( 'test_bucket', 0, $second );

		$this->assertSame( $before + 1, $this->post_dispatch_callbacks(), 'One callback, for the class.' );

		\apply_filters( 'rest_post_dispatch', new \WP_REST_Response( array(), 429 ), \rest_get_server(), $first );
		\apply_filters( 'rest_post_dispatch', new \WP_REST_Response( array(), 429 ), \rest_get_server(), $second );

		$this->assertSame( $before + 1, $this->post_dispatch_callbacks(), 'It stays; there is nothing per request to remove.' );
		$this->assertSame( array(), $this->counted()->getValue(), 'Both refusals are freed with their responses.' );
	}

	/**
	 * A request is forgotten once its response carries the allowance.
	 *
	 * The answer is kept only so core's second permission check cannot charge one request twice, so
	 * holding it past the response would keep the request object for the rest of the process.
	 *
	 * @covers ::rate_limit
	 */
	public function test_rate_limit_forgets_a_request_once_its_response_is_stamped() {
		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/interactions' );
		$memo    = 'test_bucket:' . \spl_object_id( $request );

		$this->instance->count( 'test_bucket', 5, $request );

		$this->assertArrayHasKey( $memo, $this->counted()->getValue(), 'The answer is kept until the response is stamped.' );

		\apply_filters( 'rest_post_dispatch', new \WP_REST_Response( array(), 200 ), \rest_get_server(), $request );

		$this->assertArrayNotHasKey( $memo, $this->counted()->getValue(), 'And dropped once it is.' );
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
