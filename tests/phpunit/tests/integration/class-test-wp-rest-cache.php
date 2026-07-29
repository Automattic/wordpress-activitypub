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
	 * Test that a caller-varying actor allow contributed by another filter is dropped.
	 *
	 * The allowed list is owned outright rather than merged: an `actors`/`users` prefix added
	 * elsewhere would otherwise let WP REST Cache store an owner-only response and replay it by URL.
	 *
	 * @covers ::add_activitypub_endpoints
	 */
	public function test_external_actor_allow_is_dropped() {
		$allowed = WP_Rest_Cache::add_activitypub_endpoints(
			array( ACTIVITYPUB_REST_NAMESPACE => array( 'actors', 'users', 'custom/public' ) )
		)[ ACTIVITYPUB_REST_NAMESPACE ];

		$this->assertNotContains( 'actors', $allowed );
		$this->assertNotContains( 'users', $allowed );
		$this->assertNotContains( 'custom/public', $allowed );
		$this->assertContains( 'nodeinfo', $allowed );
	}

	/**
	 * Test that the cache purge runs on the migration event, not on every request, and only when
	 * upgrading from a version that predates the caching restriction.
	 *
	 * @covers ::init
	 * @covers ::purge_on_migrate
	 */
	public function test_purge_runs_on_migration_not_per_request() {
		WP_Rest_Cache::init();

		$has_hook = \has_action( 'activitypub_migrate', array( WP_Rest_Cache::class, 'purge_on_migrate' ) );

		// Detach the cache-plugin-dependent hooks init() registered, so they cannot fire from unrelated
		// tests (the cache plugin class is unavailable in the test environment).
		\remove_action( 'activitypub_migrate', array( WP_Rest_Cache::class, 'purge_on_migrate' ) );
		\remove_action( 'transition_post_status', array( WP_Rest_Cache::class, 'transition_post_status' ), 10 );
		\remove_action( 'transition_comment_status', array( WP_Rest_Cache::class, 'transition_comment_status' ), 10 );

		$this->assertNotFalse( $has_hook, 'The purge must run on upgrade, not on every request.' );

		// The former per-request sentinel option must no longer be created by init().
		$this->assertFalse( \get_option( 'activitypub_rest_cache_actor_purge_done' ), 'The per-request sentinel option must be gone.' );

		// An upgrade from the current version is a no-op: the version gate short-circuits before touching
		// the cache plugin, so this runs cleanly even though that plugin is absent here.
		WP_Rest_Cache::purge_on_migrate( ACTIVITYPUB_PLUGIN_VERSION );
	}

	/**
	 * Test that the owner-only and per-peer routes are always disallowed.
	 *
	 * @covers ::add_disallowed_endpoints
	 */
	public function test_disallowed_endpoints_cover_sensitive_routes() {
		$disallowed = WP_Rest_Cache::add_disallowed_endpoints( array() )[ ACTIVITYPUB_REST_NAMESPACE ];

		$this->assertContains( '(?:users|actors)/[0-9]+/inbox', $disallowed );
		$this->assertContains( '(?:users|actors)/[0-9]+/followers/sync', $disallowed );
	}

	/**
	 * Test that a deny rule another filter added under the ActivityPub namespace is not overwritten.
	 *
	 * An administrator excluding a custom authenticated route under an otherwise allowed prefix must
	 * keep that protection, or the route becomes URL-cached and can leak caller-specific data.
	 *
	 * @covers ::add_disallowed_endpoints
	 */
	public function test_existing_disallowed_entries_are_preserved() {
		$disallowed = WP_Rest_Cache::add_disallowed_endpoints(
			array( ACTIVITYPUB_REST_NAMESPACE => array( 'posts/[0-9]+/private' ) )
		)[ ACTIVITYPUB_REST_NAMESPACE ];

		$this->assertContains( 'posts/[0-9]+/private', $disallowed );
		$this->assertContains( '(?:users|actors)/[0-9]+/inbox', $disallowed );
	}

	/**
	 * Test that the deny list does not grow when its own output is fed back through the filter.
	 *
	 * WP REST Cache passes the persisted option back through this filter on every request, so a
	 * plain merge would append a duplicate set each time.
	 *
	 * @covers ::add_disallowed_endpoints
	 */
	public function test_disallowed_endpoints_do_not_duplicate() {
		$once  = WP_Rest_Cache::add_disallowed_endpoints( array() );
		$twice = WP_Rest_Cache::add_disallowed_endpoints( $once );

		$this->assertSame(
			$once[ ACTIVITYPUB_REST_NAMESPACE ],
			$twice[ ACTIVITYPUB_REST_NAMESPACE ],
			'Feeding the deny list back through the filter must be idempotent.'
		);
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
