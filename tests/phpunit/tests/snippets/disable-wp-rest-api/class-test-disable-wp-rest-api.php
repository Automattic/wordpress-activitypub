<?php
/**
 * Tests for the Disable WP REST API snippet.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Snippets;

use function Activitypub\Snippets\allow_activitypub_rest_requests;

/**
 * Test the opt-in REST API allowlist.
 */
class Test_Disable_Wp_Rest_Api extends \WP_UnitTestCase {
	/**
	 * Saved request URI.
	 *
	 * @var string|null
	 */
	private $request_uri;

	/**
	 * Saved query variables.
	 *
	 * @var array
	 */
	private $query_vars;

	/**
	 * Load the snippet and save request state.
	 */
	public function set_up() {
		parent::set_up();
		global $wp;
		$this->request_uri = $_SERVER['REQUEST_URI'] ?? null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Preserve the original server value for teardown.
		$this->query_vars  = $wp->query_vars;
		require_once ACTIVITYPUB_PLUGIN_DIR . 'snippets/disable-wp-rest-api/disable-wp-rest-api.php';
		\add_filter( 'disable_wp_rest_api_server_var', 'Activitypub\Snippets\allow_activitypub_rest_requests' );
	}

	/**
	 * Restore request state and remove the opt-in filter.
	 */
	public function tear_down() {
		global $wp;
		$wp->query_vars = $this->query_vars;
		if ( null === $this->request_uri ) {
			unset( $_SERVER['REQUEST_URI'] );
		} else {
			$_SERVER['REQUEST_URI'] = $this->request_uri;
		}
		\remove_filter( 'disable_wp_rest_api_server_var', 'Activitypub\Snippets\allow_activitypub_rest_requests' );
		parent::tear_down();
	}

	/**
	 * Only resolved ActivityPub routes are allowlisted.
	 *
	 * @dataProvider route_provider
	 * @param mixed  $route   Resolved REST route.
	 * @param string $uri     Request URI.
	 * @param bool   $allowed Whether to allow the request.
	 */
	public function test_resolved_routes( $route, $uri, $allowed ) {
		global $wp;
		$wp->query_vars['rest_route'] = $route;
		$_SERVER['REQUEST_URI']       = $uri;
		$this->assertSame( $allowed ? array( $uri ) : false, \apply_filters( 'disable_wp_rest_api_server_var', false ) );
	}

	/**
	 * Routes and URL formats, including misleading namespace matches.
	 *
	 * @return array Test cases.
	 */
	public function route_provider() {
		return array(
			'namespace'          => array( '/activitypub/1.0', '/wp-json/activitypub/1.0', true ),
			'actor'              => array( '/activitypub/1.0/actors/1', '/wp-json/activitypub/1.0/actors/1', true ),
			'inbox'              => array( '/activitypub/1.0/inbox', '/wp-json/activitypub/1.0/inbox', true ),
			'plain permalinks'   => array( '/activitypub/1.0/actors/1', '/?rest_route=%2Factivitypub%2F1.0%2Factors%2F1', true ),
			'no leading slash'   => array( 'activitypub/1.0/inbox', '/?rest_route=activitypub/1.0/inbox', true ),
			'webfinger'          => array( '/activitypub/1.0/webfinger', '/.well-known/webfinger?resource=acct:alice@example.org', true ),
			'nodeinfo'           => array( '/activitypub/1.0/nodeinfo', '/.well-known/nodeinfo', true ),
			'subdirectory'       => array( '/activitypub/1.0/inbox', '/blog/wp-json/activitypub/1.0/inbox', true ),
			'core route'         => array( '/wp/v2/users', '/wp-json/wp/v2/users', false ),
			'query substring'    => array( '/wp/v2/users', '/wp-json/wp/v2/users?search=/activitypub/1.0/inbox', false ),
			'namespace suffix'   => array( '/activitypub/1.0-other/inbox', '/wp-json/activitypub/1.0-other/inbox', false ),
			'version suffix'     => array( '/activitypub/1.00/inbox', '/wp-json/activitypub/1.00/inbox', false ),
			'namespace prefix'   => array( '/other/activitypub/1.0/inbox', '/wp-json/other/activitypub/1.0/inbox', false ),
			'separate webfinger' => array( '/webfinger/1.0', '/.well-known/webfinger', false ),
			'no route'           => array( null, '/wp-json/activitypub/1.0/inbox', false ),
			'malformed route'    => array( array( '/activitypub/1.0/inbox' ), '/wp-json/activitypub/1.0/inbox', false ),
		);
	}

	/**
	 * Existing allowlist entries remain intact.
	 */
	public function test_preserves_existing_allowlist() {
		global $wp;
		$wp->query_vars['rest_route'] = '/activitypub/1.0/inbox';
		$_SERVER['REQUEST_URI']       = '/wp-json/activitypub/1.0/inbox';
		foreach ( array( '/existing', array( '/existing', '/another' ) ) as $allowed ) {
			$this->assertSame( \array_merge( (array) $allowed, array( '/wp-json/activitypub/1.0/inbox' ) ), allow_activitypub_rest_requests( $allowed ) );
		}
		$wp->query_vars['rest_route'] = '/wp/v2/users';
		$this->assertSame( '/existing', allow_activitypub_rest_requests( '/existing' ) );
	}

	/**
	 * CLI and other requests without a URI cannot add an exception.
	 */
	public function test_missing_request_uri() {
		global $wp;
		$wp->query_vars['rest_route'] = '/activitypub/1.0/inbox';
		unset( $_SERVER['REQUEST_URI'] );
		$this->assertFalse( allow_activitypub_rest_requests( false ) );
	}
}
