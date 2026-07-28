<?php
/**
 * Test WP REST Cache integration.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Integration;

use Activitypub\Integration\WP_Rest_Cache;

/**
 * Test WP REST Cache integration.
 *
 * @group integration
 * @coversDefaultClass \Activitypub\Integration\WP_Rest_Cache
 */
class Test_WP_Rest_Cache extends \WP_UnitTestCase {
	/**
	 * Test that the actor tree is not offered for caching.
	 *
	 * @covers ::add_activitypub_endpoints
	 */
	public function test_actor_routes_are_not_cacheable() {
		$allowed = WP_Rest_Cache::add_activitypub_endpoints( array() )[ ACTIVITYPUB_REST_NAMESPACE ];

		$this->assertNotContains( 'actors', $allowed );
		$this->assertNotContains( 'users', $allowed );
	}

	/**
	 * Test that the allowed endpoints do not disturb other namespaces.
	 *
	 * @covers ::add_activitypub_endpoints
	 */
	public function test_other_namespaces_are_untouched() {
		$endpoints = WP_Rest_Cache::add_activitypub_endpoints( array( 'wp/v2' => array( 'posts' ) ) );

		$this->assertSame( array( 'posts' ), $endpoints['wp/v2'] );
	}

	/**
	 * Test that every allowed endpoint matches at least one registered route.
	 *
	 * An entry that matches nothing is not harmful, but it reads as coverage the cache
	 * does not actually have, which is how a stale list goes unnoticed.
	 *
	 * @covers ::add_activitypub_endpoints
	 */
	public function test_allowed_endpoints_match_registered_routes() {
		$allowed = WP_Rest_Cache::add_activitypub_endpoints( array() )[ ACTIVITYPUB_REST_NAMESPACE ];
		$routes  = $this->get_example_uris();

		foreach ( $allowed as $endpoint ) {
			$needle = ACTIVITYPUB_REST_NAMESPACE . '/' . $endpoint;
			$match  = false;

			foreach ( $routes as $uri ) {
				if ( \str_contains( $uri, $needle ) ) {
					$match = true;
					break;
				}
			}

			$this->assertTrue( $match, \sprintf( 'Allowed endpoint "%s" matches no registered route.', $endpoint ) );
		}
	}

	/**
	 * Test that no route requiring authentication can be reached through an allowed endpoint.
	 *
	 * Allowed endpoints are matched as plain substrings of the request URI, so listing a
	 * prefix opts in every route beneath it. A route that answers callers differently
	 * must therefore never sit below a listed prefix, and adding one is easy to miss,
	 * since it happens in a controller rather than in the list itself.
	 *
	 * @covers ::add_activitypub_endpoints
	 */
	public function test_no_authenticated_route_is_cacheable() {
		$allowed = WP_Rest_Cache::add_activitypub_endpoints( array() )[ ACTIVITYPUB_REST_NAMESPACE ];
		$routes  = $this->get_example_uris( true );

		$this->assertNotEmpty( $routes, 'Expected at least one authenticated read route to guard.' );

		foreach ( $routes as $uri ) {
			foreach ( $allowed as $endpoint ) {
				$this->assertStringNotContainsString(
					ACTIVITYPUB_REST_NAMESPACE . '/' . $endpoint,
					$uri,
					\sprintf( 'Route %1$s requires authentication but is cacheable via the "%2$s" endpoint.', $uri, $endpoint )
				);
			}
		}
	}

	/**
	 * Collect example URIs for registered ActivityPub read routes.
	 *
	 * @param bool $authenticated_only Optional. Whether to return only routes whose read
	 *                                 handler requires authentication. Default false.
	 *
	 * @return string[] Example URIs.
	 */
	private function get_example_uris( $authenticated_only = false ) {
		$uris = array();

		foreach ( \rest_get_server()->get_routes( ACTIVITYPUB_REST_NAMESPACE ) as $route => $handlers ) {
			foreach ( $handlers as $handler ) {
				if ( empty( $handler['methods']['GET'] ) ) {
					continue;
				}

				if ( $authenticated_only && ! $this->is_authenticated( $handler ) ) {
					continue;
				}

				$uris[] = $this->route_to_uri( $route );
			}
		}

		return \array_unique( $uris );
	}

	/**
	 * Determine whether a route handler requires authentication.
	 *
	 * @param array $handler The route handler.
	 *
	 * @return bool Whether the handler requires authentication.
	 */
	private function is_authenticated( $handler ) {
		// Any gate other than the public `__return_true` makes the response caller-specific.
		return '__return_true' !== ( $handler['permission_callback'] ?? null );
	}

	/**
	 * Turn a registered route pattern into an example URI.
	 *
	 * @param string $route The route pattern.
	 *
	 * @return string An example URI for that route.
	 */
	private function route_to_uri( $route ) {
		// The actor base is registered as an alternation, so pick one spelling.
		$uri = \str_replace( '(?:users|actors)', 'actors', $route );

		// Any remaining capture group stands in for an identifier.
		return \preg_replace( '@\(\?P<[^>]+>.*?\)@', '1', $uri );
	}
}
