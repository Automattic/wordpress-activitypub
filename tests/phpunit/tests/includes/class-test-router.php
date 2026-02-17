<?php
/**
 * Test file for Router.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

use Activitypub\Collection\Outbox;
use Activitypub\Query;
use Activitypub\Router;

/**
 * Test class for Router.
 *
 * @coversDefaultClass \Activitypub\Router
 */
class Test_Router extends \WP_UnitTestCase {
	/**
	 * Test user ID.
	 *
	 * @var int
	 */
	protected static $user_id;

	/**
	 * Create fake data before tests run.
	 *
	 * @param \WP_UnitTest_Factory $factory Helper that creates fake data.
	 */
	public static function wpSetUpBeforeClass( $factory ) {
		\add_post_type_support( 'post', 'activitypub' );

		self::$user_id = $factory->user->create(
			array(
				'role' => 'author',
			)
		);
	}

	/**
	 * Set up test environment.
	 */
	public function setUp(): void {
		parent::setUp();
		Router::init();
	}

	/**
	 * Tear down test environment.
	 */
	public function tear_down(): void {
		// Clean up common state that may be left by tests.
		unset( $_SERVER['HTTP_ACCEPT'] );
		\set_query_var( 'preview', null );
		\set_query_var( 'term_id', null );
		Query::get_instance()->__destruct();

		parent::tear_down();
	}

	/**
	 * Test that ActivityPub requests for custom post types return 200.
	 *
	 * @covers ::render_activitypub_template
	 */
	public function test_custom_post_type_returns_200() {
		// Register a custom post type.
		register_post_type(
			'test_cpt',
			array(
				'public' => true,
				'label'  => 'Test CPT',
			)
		);

		\add_post_type_support( 'test_cpt', 'activitypub' );

		// Create a post with the custom post type.
		$post_id = self::factory()->post->create(
			array(
				'post_type'   => 'test_cpt',
				'post_status' => 'publish',
				'post_author' => self::$user_id,
			)
		);

		global $wp_query;

		// Mock the Accept header.
		$_SERVER['HTTP_ACCEPT'] = 'application/activity+json';

		// Use the ugly post-url instead.
		$this->go_to( '/?p=' . $post_id );

		// Test the template response.
		$template = Router::render_activitypub_template( 'index.php' );
		$this->assertStringContainsString( 'activitypub-json.php', $template );
		$this->assertFalse( $wp_query->is_404 );

		// Clean up.
		unset( $_SERVER['HTTP_ACCEPT'] );
		_unregister_post_type( 'test_cpt' );
	}

	/**
	 * Test that ActivityPub requests for custom post types with built-in ActivityPub support return 200.
	 *
	 * This specifically tests custom post types registered with 'supports' => array( 'activitypub' ).
	 *
	 * @covers ::render_activitypub_template
	 */
	public function test_custom_post_type_with_support_returns_200() {
		// Register a custom post type with ActivityPub support.
		register_post_type(
			'test_cpt_supported',
			array(
				'public'   => true,
				'label'    => 'Test CPT Supported',
				'supports' => array( 'activitypub' ),
			)
		);

		// Create a post with the custom post type.
		$post_id = self::factory()->post->create(
			array(
				'post_type'   => 'test_cpt_supported',
				'post_status' => 'publish',
				'post_author' => self::$user_id,
			)
		);

		global $wp_query;

		// Mock the Accept header.
		$_SERVER['HTTP_ACCEPT'] = 'application/activity+json';

		// Set up the query for the custom post type.
		$this->go_to( '/?p=' . $post_id );

		// Test the template response.
		$template = Router::render_activitypub_template( 'index.php' );
		$this->assertStringContainsString( 'activitypub-json.php', $template );
		$this->assertFalse( $wp_query->is_404 );

		// Clean up.
		unset( $_SERVER['HTTP_ACCEPT'] );
		_unregister_post_type( 'test_cpt_supported' );
	}

	/**
	 * Test 406/404 response for non-ActivityPub requests to Outbox post type.
	 *
	 * @covers ::render_activitypub_template
	 */
	public function test_outbox_post_type_non_activitypub_request_returns_406() {
		$data    = array(
			'@context' => 'https://www.w3.org/ns/activitystreams',
			'id'       => 'https://example.com/' . self::$user_id,
			'type'     => 'Note',
			'content'  => '<p>This is a note</p>',
		);
		$post_id = \Activitypub\add_to_outbox( $data, 'Create', self::$user_id );

		$_SERVER['HTTP_ACCEPT'] = 'application/activity+json';
		$this->go_to( '/?p=' . $post_id );
		$template = Router::render_activitypub_template( 'index.php' );
		$this->assertStringContainsString( 'activitypub-json.php', $template );

		Query::get_instance()->__destruct();

		$status = null;
		add_filter(
			'status_header',
			function ( $status_header ) use ( &$status ) {
				$status = $status_header;
				return $status_header;
			},
			100
		);

		unset( $_SERVER['HTTP_ACCEPT'] );
		$this->go_to( '/?p=' . $post_id );
		$template = Router::render_activitypub_template( 'index.php' );
		$this->assertStringContainsString( 'index.php', $template );
		$this->assertStringContainsString( '406', $status );
	}

	/**
	 * Test no_trailing_redirect method.
	 *
	 * @covers ::no_trailing_redirect
	 */
	public function test_no_trailing_redirect() {
		// Test case 1: When actor query var is set, it should return the requested URL.
		set_query_var( 'actor', 'testuser' );
		$requested_url = 'https://example.org/@testuser';
		$redirect_url  = 'https://example.org/@testuser/';

		$result = Router::no_trailing_redirect( $redirect_url, $requested_url );
		$this->assertEquals( $requested_url, $result, 'Should return requested URL when actor query var is set.' );

		// Test case 2: When actor query var is not set, it should return the redirect URL.
		set_query_var( 'actor', '' );
		$requested_url = 'https://example.org/some-page';
		$redirect_url  = 'https://example.org/some-page/';

		$result = Router::no_trailing_redirect( $redirect_url, $requested_url );
		$this->assertEquals( $redirect_url, $result, 'Should return redirect URL when actor query var is not set.' );

		// Clean up.
		set_query_var( 'actor', null );
	}

	/**
	 * Test activitypub_preview_template filter.
	 *
	 * @covers ::render_activitypub_template
	 */
	public function test_preview_template_filter() {
		Query::get_instance()->__destruct();

		// Create a test post.
		$post_id = self::factory()->post->create(
			array(
				'post_author' => 1,
			)
		);
		$this->go_to( get_permalink( $post_id ) );

		// Simulate ActivityPub request and preview mode.
		$_SERVER['HTTP_ACCEPT'] = 'application/activity+json';
		\set_query_var( 'preview', true );

		// Save callback to variable for proper removal.
		$preview_template_callback = function () {
			return '/custom/template.php';
		};

		// Add filter before testing.
		\add_filter( 'activitypub_preview_template', $preview_template_callback );

		// Test that the filter is applied.
		$template = Router::render_activitypub_template( 'original.php' );
		$this->assertEquals( '/custom/template.php', $template, 'Custom preview template should be used when filter is applied.' );

		// Clean up.
		\remove_filter( 'activitypub_preview_template', $preview_template_callback );
	}

	/**
	 * Test that the activitypub_supported_taxonomies filter has correct defaults.
	 *
	 * @covers ::template_redirect
	 */
	public function test_supported_taxonomies_filter_defaults() {
		$supported = \apply_filters( 'activitypub_supported_taxonomies', array( 'category', 'post_tag' ) );

		$this->assertContains( 'category', $supported, 'Category should be a supported taxonomy by default.' );
		$this->assertContains( 'post_tag', $supported, 'Post tag should be a supported taxonomy by default.' );
		$this->assertCount( 2, $supported, 'Should have exactly 2 default supported taxonomies.' );
	}

	/**
	 * Test that the activitypub_supported_taxonomies filter can be modified.
	 *
	 * @covers ::template_redirect
	 */
	public function test_supported_taxonomies_filter_can_be_modified() {
		\add_filter(
			'activitypub_supported_taxonomies',
			function ( $taxonomies ) {
				$taxonomies[] = 'custom_taxonomy';
				return $taxonomies;
			}
		);

		$supported = \apply_filters( 'activitypub_supported_taxonomies', array( 'category', 'post_tag' ) );

		$this->assertContains( 'custom_taxonomy', $supported, 'Custom taxonomy should be added via filter.' );
		$this->assertCount( 3, $supported, 'Should have 3 taxonomies after adding custom one.' );

		// Clean up.
		\remove_all_filters( 'activitypub_supported_taxonomies' );
	}

	/**
	 * Test that unsupported taxonomy terms don't trigger redirects.
	 *
	 * This test verifies the fix for #2730 (Polylang conflict) and #2725 (posts page redirect).
	 * When a term_id belongs to an unsupported taxonomy, the router should return early
	 * without redirecting. The unsupported taxonomy check happens before the ActivityPub
	 * request check, so no HTTP_ACCEPT header is needed.
	 *
	 * @covers ::template_redirect
	 */
	public function test_unsupported_taxonomy_does_not_redirect() {
		// Register a custom taxonomy (simulating Polylang's language taxonomy).
		\register_taxonomy(
			'language',
			'post',
			array(
				'public' => true,
				'label'  => 'Language',
			)
		);

		// Create a term in the custom taxonomy.
		$term = \wp_insert_term( 'English', 'language' );
		$this->assertNotWPError( $term, 'Term creation should succeed.' );

		$term_id = $term['term_id'];

		// Set the term_id query var (simulating what might happen with Polylang).
		\set_query_var( 'term_id', $term_id );

		global $wp_query;

		// Call template_redirect - it should return early for unsupported taxonomy.
		// Note: No HTTP_ACCEPT header needed because the taxonomy check happens first.
		Router::template_redirect();

		// The query should not be set to 404 for valid but unsupported taxonomy terms.
		$this->assertFalse( $wp_query->is_404(), 'Should not set 404 for valid unsupported taxonomy terms.' );

		// Clean up.
		\set_query_var( 'term_id', null );
		\wp_delete_term( $term_id, 'language' );
		\unregister_taxonomy( 'language' );
	}

	/**
	 * Test that supported taxonomy terms are handled correctly for ActivityPub requests.
	 *
	 * @covers ::template_redirect
	 */
	public function test_supported_taxonomy_activitypub_request_no_redirect() {
		// Create a category term.
		$term = \wp_insert_term( 'Test Category', 'category' );
		$this->assertNotWPError( $term, 'Term creation should succeed.' );

		$term_id = $term['term_id'];

		// Set the term_id query var.
		\set_query_var( 'term_id', $term_id );

		// Simulate an ActivityPub request - should return early without redirect.
		$_SERVER['HTTP_ACCEPT'] = 'application/activity+json';

		global $wp_query;

		// Call template_redirect - it should return early for ActivityPub requests.
		Router::template_redirect();

		// The query should not be set to 404 for valid category terms.
		$this->assertFalse( $wp_query->is_404(), 'Should not set 404 for valid category terms.' );

		// Clean up.
		unset( $_SERVER['HTTP_ACCEPT'] );
		\set_query_var( 'term_id', null );
		\wp_delete_term( $term_id, 'category' );
	}

	/**
	 * Test that invalid term_id sets 404.
	 *
	 * @covers ::template_redirect
	 */
	public function test_invalid_term_id_sets_404() {
		// Set an invalid term_id query var.
		\set_query_var( 'term_id', 999999 );

		global $wp_query;

		// Call template_redirect - it should set 404 for invalid term.
		Router::template_redirect();

		$this->assertTrue( $wp_query->is_404(), 'Should set 404 for invalid term_id.' );

		// Clean up.
		\set_query_var( 'term_id', null );
		$wp_query->is_404 = false;
	}

	/**
	 * Test that supported taxonomy terms trigger redirects for non-ActivityPub requests.
	 *
	 * This verifies the core redirect functionality still works after the taxonomy filtering fix.
	 * Uses an exception in the wp_redirect filter to intercept before exit() is called.
	 *
	 * @covers ::template_redirect
	 *
	 * @throws \Exception If a non-redirect exception is caught during template_redirect.
	 */
	public function test_supported_taxonomy_triggers_redirect() {
		// Create a category term.
		$term = \wp_insert_term( 'Redirect Test Category', 'category' );
		$this->assertNotWPError( $term, 'Term creation should succeed.' );

		$term_id   = $term['term_id'];
		$term_link = \get_term_link( $term_id, 'category' );

		// Set the term_id query var.
		\set_query_var( 'term_id', $term_id );

		// Save callback to variable for proper removal.
		$redirect_callback = function ( $location ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new \Exception( 'REDIRECT:' . $location );
		};

		// Use exception to intercept redirect before exit() is called.
		\add_filter( 'wp_redirect', $redirect_callback );

		$redirect_location = null;
		try {
			Router::template_redirect();
		} catch ( \Exception $e ) {
			if ( 0 === strpos( $e->getMessage(), 'REDIRECT:' ) ) {
				$redirect_location = substr( $e->getMessage(), 9 );
			} else {
				throw $e;
			}
		}

		// Verify redirect was attempted to the correct term link.
		$this->assertNotNull( $redirect_location, 'Should attempt redirect for supported taxonomy term.' );
		$this->assertEquals( $term_link, $redirect_location, 'Should redirect to the term link.' );

		// Clean up.
		\remove_filter( 'wp_redirect', $redirect_callback );
		\wp_delete_term( $term_id, 'category' );
	}

	/**
	 * Test that the authorize_interaction rewrite rule is registered.
	 *
	 * @covers ::add_rewrite_rules
	 */
	public function test_authorize_interaction_rewrite_rule() {
		$this->set_permalink_structure( '/%postname%/' );

		Router::add_rewrite_rules();
		\flush_rewrite_rules();

		$rules = get_option( 'rewrite_rules' );

		$this->assertArrayHasKey( '^authorize_interaction/?$', $rules );
		$this->assertSame(
			'index.php?rest_route=/' . ACTIVITYPUB_REST_NAMESPACE . '/interactions',
			$rules['^authorize_interaction/?$']
		);
	}

	/**
	 * Test that the webfinger rewrite rule is registered when no Webfinger plugin is active.
	 *
	 * @covers ::add_rewrite_rules
	 */
	public function test_webfinger_rewrite_rule() {
		$this->set_permalink_structure( '/%postname%/' );

		Router::add_rewrite_rules();
		\flush_rewrite_rules();

		$rules = get_option( 'rewrite_rules' );

		$this->assertArrayHasKey( '^.well-known/webfinger', $rules );
		$this->assertSame(
			'index.php?rest_route=/' . ACTIVITYPUB_REST_NAMESPACE . '/webfinger',
			$rules['^.well-known/webfinger']
		);
	}

	/**
	 * Test that the nodeinfo rewrite rule is registered when blog is public.
	 *
	 * @covers ::add_rewrite_rules
	 */
	public function test_nodeinfo_rewrite_rule() {
		$this->set_permalink_structure( '/%postname%/' );
		\update_option( 'blog_public', 1 );

		Router::add_rewrite_rules();
		\flush_rewrite_rules();

		$rules = get_option( 'rewrite_rules' );

		$this->assertArrayHasKey( '^.well-known/nodeinfo', $rules );
		$this->assertSame(
			'index.php?rest_route=/' . ACTIVITYPUB_REST_NAMESPACE . '/nodeinfo',
			$rules['^.well-known/nodeinfo']
		);
	}

	/**
	 * Test that the actor rewrite rule is registered.
	 *
	 * @covers ::add_rewrite_rules
	 */
	public function test_actor_rewrite_rule() {
		$this->set_permalink_structure( '/%postname%/' );

		Router::add_rewrite_rules();
		\flush_rewrite_rules();

		$rules = get_option( 'rewrite_rules' );

		$this->assertArrayHasKey( '^@([\w\-\.]+)\/?$', $rules );
		$this->assertSame( 'index.php?actor=$matches[1]', $rules['^@([\w\-\.]+)\/?$'] );
	}

	/**
	 * Test that the activitypub_supported_taxonomies filter is actually used by the Router.
	 *
	 * This verifies that adding a custom taxonomy via the filter allows redirects for that taxonomy.
	 *
	 * @covers ::template_redirect
	 *
	 * @throws \Exception If a non-redirect exception is caught during template_redirect.
	 */
	public function test_filter_adds_custom_taxonomy_to_redirects() {
		// Register a custom taxonomy.
		\register_taxonomy(
			'custom_tax',
			'post',
			array(
				'public' => true,
				'label'  => 'Custom Tax',
			)
		);

		// Create a term in the custom taxonomy.
		$term = \wp_insert_term( 'Custom Term', 'custom_tax' );
		$this->assertNotWPError( $term, 'Term creation should succeed.' );

		$term_id   = $term['term_id'];
		$term_link = \get_term_link( $term_id, 'custom_tax' );

		// Set the term_id query var.
		\set_query_var( 'term_id', $term_id );

		// Save callbacks to variables for proper removal.
		$taxonomy_callback = function ( $taxonomies ) {
			$taxonomies[] = 'custom_tax';
			return $taxonomies;
		};
		$redirect_callback = function ( $location ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new \Exception( 'REDIRECT:' . $location );
		};

		// Add custom taxonomy to supported list via filter.
		\add_filter( 'activitypub_supported_taxonomies', $taxonomy_callback );

		// Use exception to intercept redirect before exit() is called.
		\add_filter( 'wp_redirect', $redirect_callback );

		$redirect_location = null;
		try {
			Router::template_redirect();
		} catch ( \Exception $e ) {
			if ( 0 === strpos( $e->getMessage(), 'REDIRECT:' ) ) {
				$redirect_location = substr( $e->getMessage(), 9 );
			} else {
				throw $e;
			}
		}

		// Verify redirect was attempted.
		$this->assertNotNull( $redirect_location, 'Should attempt redirect for custom taxonomy added via filter.' );
		$this->assertEquals( $term_link, $redirect_location, 'Should redirect to the custom taxonomy term link.' );

		// Clean up.
		\remove_filter( 'wp_redirect', $redirect_callback );
		\remove_filter( 'activitypub_supported_taxonomies', $taxonomy_callback );
		\wp_delete_term( $term_id, 'custom_tax' );
		\unregister_taxonomy( 'custom_tax' );
	}
}
