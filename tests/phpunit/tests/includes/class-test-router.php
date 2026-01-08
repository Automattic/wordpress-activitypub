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

		// Add filter before testing.
		\add_filter(
			'activitypub_preview_template',
			function () {
				return '/custom/template.php';
			}
		);

		// Test that the filter is applied.
		$template = Router::render_activitypub_template( 'original.php' );
		$this->assertEquals( '/custom/template.php', $template, 'Custom preview template should be used when filter is applied.' );

		// Clean up.
		unset( $_SERVER['HTTP_ACCEPT'] );
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
}
