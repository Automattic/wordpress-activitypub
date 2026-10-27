<?php
/**
 * Tests for ActivityPub Search Enhancement
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

use Activitypub\Search;

/**
 * Test class for ActivityPub Search Enhancement
 */
class Test_Search extends \WP_UnitTestCase {
	/**
	 * Test search enhancement doesn't interfere with regular searches.
	 */
	public function test_regular_search_unchanged() {
		// Create a test post.
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Test Post About Cats',
				'post_content' => 'This is a test post about cats and dogs.',
				'post_status'  => 'publish',
			)
		);

		// Perform a regular search.
		$query = new \WP_Query(
			array(
				's'         => 'cats',
				'post_type' => 'post',
			)
		);

		// Should find the test post.
		$this->assertTrue( $query->have_posts() );
		$this->assertEquals( 1, $query->found_posts );
		$this->assertEquals( $post_id, $query->posts[0]->ID );
	}

	/**
	 * Test search enhancement initialization.
	 */
	public function test_search_init() {
		$this->assertEquals( 10, has_filter( 'pre_get_posts', array( Search::class, 'enhance_public_search' ) ) );
	}

	/**
	 * Test search enhancement only affects main search queries.
	 */
	public function test_search_enhancement_conditions() {
		// Test admin query (should not be enhanced).
		$admin_query                = new \WP_Query();
		$admin_query->is_search     = true;
		$admin_query->is_main_query = true;

		// Simulate admin context.
		\set_current_screen( 'edit-post' );

		$result = Search::enhance_public_search( $admin_query );
		$this->assertEquals( $admin_query, $result );

		// Reset screen.
		\set_current_screen( 'front' );

		// Test non-search query (should not be enhanced).
		$non_search_query                = new \WP_Query();
		$non_search_query->is_search     = false;
		$non_search_query->is_main_query = true;

		$result = Search::enhance_public_search( $non_search_query );
		$this->assertEquals( $non_search_query, $result );

		// Test non-main query (should not be enhanced).
		$non_main_query                = new \WP_Query();
		$non_main_query->is_search     = true;
		$non_main_query->is_main_query = false;

		$result = Search::enhance_public_search( $non_main_query );
		$this->assertEquals( $non_main_query, $result );
	}

	/**
	 * Searching for a comment's URL leads to it, but leaves its moderation status alone.
	 *
	 * @covers ::enhance_admin_comment_search
	 */
	public function test_search_keeps_comment_status() {
		$user = \get_user_by( 'id', self::factory()->user->create( array( 'role' => 'author' ) ) );
		$user->add_cap( 'activitypub' );
		\wp_set_current_user( $user->ID );

		// A public IP, so wp_http_validate_url() accepts the URL without a DNS lookup.
		$url        = 'https://1.1.1.1/notes/1';
		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::factory()->post->create(),
				'comment_approved' => '0',
			)
		);
		\add_comment_meta( $comment_id, 'source_id', $url );

		$redirect = function ( $location ) {
			throw new \Exception( \esc_url_raw( $location ) );
		};
		\add_filter( 'wp_redirect', $redirect );

		$_GET['s']  = $url;
		$redirected = '';
		try {
			Search::enhance_admin_comment_search();
		} catch ( \Exception $e ) {
			$redirected = $e->getMessage();
		}
		unset( $_GET['s'] );

		\remove_filter( 'wp_redirect', $redirect );

		$this->assertNotEmpty( $redirected, 'The search should still redirect after finding the comment.' );
		$this->assertSame( '0', \get_comment( $comment_id )->comment_approved, 'The search must not approve the comment.' );
	}

	/**
	 * Helper method to access private try_import_activitypub_object method via reflection.
	 *
	 * @param string $url The URL to import.
	 *
	 * @return int|false The imported comment ID or false on failure.
	 */
	private function try_import_activitypub_object_accessible( $url ) {
		$reflection = new \ReflectionClass( 'Activitypub\Search' );
		$method     = $reflection->getMethod( 'try_import_activitypub_object' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		return $method->invoke( null, $url );
	}
}
