<?php
/**
 * Test Posts Collection.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Collection;

use Activitypub\Collection\Posts;

/**
 * Posts Collection Test Class.
 *
 * @coversDefaultClass \Activitypub\Collection\Posts
 */
class Test_Posts extends \WP_UnitTestCase {

	/**
	 * Test creating a post from an Article activity.
	 *
	 * @covers ::create
	 */
	public function test_create_article() {
		$user_id  = self::factory()->user->create( array( 'role' => 'editor' ) );
		$activity = array(
			'object' => array(
				'type'    => 'Article',
				'name'    => 'My Article Title',
				'content' => '<p>Article content here.</p>',
				'summary' => 'A short summary.',
			),
		);

		$post = Posts::create( $activity, $user_id );

		$this->assertInstanceOf( '\WP_Post', $post );
		$this->assertEquals( 'My Article Title', $post->post_title );
		$this->assertEquals( 'A short summary.', $post->post_excerpt );
		$this->assertEquals( 'post', $post->post_type );
		$this->assertEquals( 'publish', $post->post_status );
		$this->assertEquals( $user_id, (int) $post->post_author );
	}

	/**
	 * Test creating a post from a Note activity sets status format.
	 *
	 * @covers ::create
	 */
	public function test_create_note_sets_status_format() {
		$user_id  = self::factory()->user->create( array( 'role' => 'editor' ) );
		$activity = array(
			'object' => array(
				'type'    => 'Note',
				'content' => '<p>A short note.</p>',
			),
		);

		$post = Posts::create( $activity, $user_id );

		$this->assertInstanceOf( '\WP_Post', $post );
		$this->assertEquals( 'status', \get_post_format( $post->ID ) );
	}

	/**
	 * Test creating a post generates title from content when name is missing.
	 *
	 * @covers ::create
	 */
	public function test_create_generates_title_from_content() {
		$user_id  = self::factory()->user->create( array( 'role' => 'editor' ) );
		$activity = array(
			'object' => array(
				'type'    => 'Note',
				'content' => '<p>This is a note without a name field so title should be generated.</p>',
			),
		);

		$post = Posts::create( $activity, $user_id );

		$this->assertInstanceOf( '\WP_Post', $post );
		$this->assertNotEmpty( $post->post_title );
	}

	/**
	 * Test creating a post with private visibility.
	 *
	 * @covers ::create
	 */
	public function test_create_private_post() {
		$user_id  = self::factory()->user->create( array( 'role' => 'editor' ) );
		$activity = array(
			'object' => array(
				'type'    => 'Note',
				'content' => '<p>Private note.</p>',
			),
		);

		$post = Posts::create( $activity, $user_id, ACTIVITYPUB_CONTENT_VISIBILITY_PRIVATE );

		$this->assertInstanceOf( '\WP_Post', $post );
		$this->assertEquals( 'private', $post->post_status );
		$this->assertEquals(
			ACTIVITYPUB_CONTENT_VISIBILITY_PRIVATE,
			\get_post_meta( $post->ID, 'activitypub_content_visibility', true )
		);
	}

	/**
	 * Test creating a post with public visibility.
	 *
	 * @covers ::create
	 */
	public function test_create_public_post() {
		$user_id  = self::factory()->user->create( array( 'role' => 'editor' ) );
		$activity = array(
			'object' => array(
				'type'    => 'Note',
				'content' => '<p>Public note.</p>',
			),
		);

		$post = Posts::create( $activity, $user_id, ACTIVITYPUB_CONTENT_VISIBILITY_PUBLIC );

		$this->assertInstanceOf( '\WP_Post', $post );
		$this->assertEquals( 'publish', $post->post_status );
	}

	/**
	 * Test creating a post fails for users without publish_posts capability.
	 *
	 * @covers ::create
	 */
	public function test_create_forbidden_for_subscriber() {
		$user_id  = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$activity = array(
			'object' => array(
				'type'    => 'Note',
				'content' => '<p>Should not be created.</p>',
			),
		);

		$result = Posts::create( $activity, $user_id );

		$this->assertWPError( $result );
		$this->assertEquals( 'activitypub_forbidden', $result->get_error_code() );
	}

	/**
	 * Test creating a post with blog actor (user_id = 0) and no current user keeps post_author = 0.
	 *
	 * Covers the cron/CLI path where no user is loaded.
	 *
	 * @covers ::create
	 */
	public function test_create_with_blog_actor_no_current_user() {
		\wp_set_current_user( 0 );

		$activity = array(
			'object' => array(
				'type'    => 'Note',
				'content' => '<p>Blog actor post.</p>',
			),
		);

		$post = Posts::create( $activity, 0 );

		$this->assertInstanceOf( '\WP_Post', $post );
		$this->assertEquals( 0, (int) $post->post_author );
	}

	/**
	 * Test creating a post with blog actor (user_id = 0) falls back to the current user for the byline.
	 *
	 * Administrators pass `user_can_act_as_blog()` by default (`manage_options`).
	 *
	 * @covers ::create
	 */
	public function test_create_with_blog_actor_uses_current_user() {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		\wp_set_current_user( $user_id );

		$activity = array(
			'object' => array(
				'type'    => 'Note',
				'content' => '<p>Blog actor post from authenticated request.</p>',
			),
		);

		$post = Posts::create( $activity, 0 );

		$this->assertInstanceOf( '\WP_Post', $post );
		$this->assertEquals( $user_id, (int) $post->post_author );

		\wp_set_current_user( 0 );
	}

	/**
	 * Test the blog actor path rejects users who cannot act as the blog.
	 *
	 * Editors do not hold `manage_options`, so `user_can_act_as_blog()` returns
	 * false for them by default and `Posts::create` must 403.
	 *
	 * @covers ::create
	 */
	public function test_create_with_blog_actor_forbidden_without_grant() {
		$user_id = self::factory()->user->create( array( 'role' => 'editor' ) );
		\wp_set_current_user( $user_id );

		$activity = array(
			'object' => array(
				'type'    => 'Note',
				'content' => '<p>Should not be created.</p>',
			),
		);

		$result = Posts::create( $activity, 0 );

		$this->assertWPError( $result );
		$this->assertEquals( 'activitypub_forbidden', $result->get_error_code() );

		\wp_set_current_user( 0 );
	}

	/**
	 * Test the `activitypub_user_can_act_as_blog` filter unlocks the blog actor path.
	 *
	 * @covers ::create
	 */
	public function test_create_with_blog_actor_filter_grants_access() {
		$user_id = self::factory()->user->create( array( 'role' => 'editor' ) );
		\wp_set_current_user( $user_id );
		\add_filter( 'activitypub_user_can_act_as_blog', '__return_true' );

		$activity = array(
			'object' => array(
				'type'    => 'Note',
				'content' => '<p>Filter-granted blog actor post.</p>',
			),
		);

		$post = Posts::create( $activity, 0 );

		\remove_filter( 'activitypub_user_can_act_as_blog', '__return_true' );
		\wp_set_current_user( 0 );

		$this->assertInstanceOf( '\WP_Post', $post );
		$this->assertEquals( $user_id, (int) $post->post_author );
	}

	/**
	 * Test content is processed through prepare_content pipeline.
	 *
	 * @covers ::create
	 * @covers ::prepare_content
	 */
	public function test_create_processes_content() {
		$user_id  = self::factory()->user->create( array( 'role' => 'editor' ) );
		$activity = array(
			'object' => array(
				'type'    => 'Note',
				'content' => '<p>Hello world</p>',
			),
		);

		$post = Posts::create( $activity, $user_id );

		$this->assertInstanceOf( '\WP_Post', $post );
		// Content should be wrapped in block markup.
		$this->assertStringContainsString( '<!-- wp:paragraph -->', $post->post_content );
	}

	/**
	 * Test creating a post with a content warning (sensitive=true + summary).
	 *
	 * @covers ::create
	 */
	public function test_create_with_content_warning() {
		$user_id  = self::factory()->user->create( array( 'role' => 'editor' ) );
		$activity = array(
			'object' => array(
				'type'      => 'Note',
				'content'   => '<p>Spoilery content.</p>',
				'summary'   => 'Spoilers ahead',
				'sensitive' => true,
			),
		);

		$post = Posts::create( $activity, $user_id );

		$this->assertInstanceOf( '\WP_Post', $post );
		$this->assertSame( 'Spoilers ahead', \get_post_meta( $post->ID, 'activitypub_content_warning', true ) );
		$this->assertSame( '', $post->post_excerpt );
	}

	/**
	 * Test that a summary without sensitive=true is treated as a regular excerpt.
	 *
	 * @covers ::create
	 */
	public function test_create_summary_without_sensitive_is_excerpt() {
		$user_id  = self::factory()->user->create( array( 'role' => 'editor' ) );
		$activity = array(
			'object' => array(
				'type'    => 'Article',
				'name'    => 'Title',
				'content' => '<p>Body.</p>',
				'summary' => 'A regular abstract.',
			),
		);

		$post = Posts::create( $activity, $user_id );

		$this->assertInstanceOf( '\WP_Post', $post );
		$this->assertSame( 'A regular abstract.', $post->post_excerpt );
		$this->assertSame( '', \get_post_meta( $post->ID, 'activitypub_content_warning', true ) );
	}

	/**
	 * Test that sensitive=true without a summary is ignored.
	 *
	 * @covers ::create
	 */
	public function test_create_sensitive_without_summary_is_ignored() {
		$user_id  = self::factory()->user->create( array( 'role' => 'editor' ) );
		$activity = array(
			'object' => array(
				'type'      => 'Note',
				'content'   => '<p>No spoilers, just a flag.</p>',
				'sensitive' => true,
			),
		);

		$post = Posts::create( $activity, $user_id );

		$this->assertInstanceOf( '\WP_Post', $post );
		$this->assertSame( '', \get_post_meta( $post->ID, 'activitypub_content_warning', true ) );
		$this->assertSame( '', $post->post_excerpt );
	}

	/**
	 * Test creating a post with sensitive=true and a whitespace-only summary.
	 *
	 * The whitespace becomes empty after sanitize_text_field, so no CW is set
	 * and the post_excerpt is also empty (no whitespace pollution).
	 *
	 * @covers ::create
	 */
	public function test_create_whitespace_summary_with_sensitive_is_ignored() {
		$user_id  = self::factory()->user->create( array( 'role' => 'editor' ) );
		$activity = array(
			'object' => array(
				'type'      => 'Note',
				'content'   => '<p>Content.</p>',
				'summary'   => '   ',
				'sensitive' => true,
			),
		);

		$post = Posts::create( $activity, $user_id );

		$this->assertInstanceOf( '\WP_Post', $post );
		$this->assertSame( '', \get_post_meta( $post->ID, 'activitypub_content_warning', true ) );
		$this->assertSame( '', $post->post_excerpt );
	}

	/**
	 * Test updating a post to add a content warning.
	 *
	 * @covers ::update
	 */
	public function test_update_adds_content_warning() {
		$user_id  = self::factory()->user->create( array( 'role' => 'editor' ) );
		$activity = array(
			'object' => array(
				'type'    => 'Note',
				'content' => '<p>Original.</p>',
				'summary' => 'Plain abstract.',
			),
		);

		$post = Posts::create( $activity, $user_id );
		$this->assertSame( 'Plain abstract.', $post->post_excerpt );

		$update_activity = array(
			'object' => array(
				'type'      => 'Note',
				'content'   => '<p>Now with spoilers.</p>',
				'summary'   => 'Spoilers',
				'sensitive' => true,
			),
		);

		$updated = Posts::update( $post, $update_activity );

		$this->assertInstanceOf( '\WP_Post', $updated );
		$this->assertSame( 'Spoilers', \get_post_meta( $updated->ID, 'activitypub_content_warning', true ) );
		$this->assertSame( '', $updated->post_excerpt );
	}

	/**
	 * Test updating a post to remove a previously set content warning.
	 *
	 * @covers ::update
	 */
	public function test_update_clears_content_warning() {
		$user_id  = self::factory()->user->create( array( 'role' => 'editor' ) );
		$activity = array(
			'object' => array(
				'type'      => 'Note',
				'content'   => '<p>Spoilery.</p>',
				'summary'   => 'Spoilers',
				'sensitive' => true,
			),
		);

		$post = Posts::create( $activity, $user_id );
		$this->assertSame( 'Spoilers', \get_post_meta( $post->ID, 'activitypub_content_warning', true ) );

		$update_activity = array(
			'object' => array(
				'type'    => 'Note',
				'content' => '<p>No longer sensitive.</p>',
			),
		);

		$updated = Posts::update( $post, $update_activity );

		$this->assertInstanceOf( '\WP_Post', $updated );
		$this->assertSame( '', \get_post_meta( $updated->ID, 'activitypub_content_warning', true ) );
		$this->assertSame( '', $updated->post_excerpt );
	}

	/**
	 * Test updating a post.
	 *
	 * @covers ::update
	 */
	public function test_update() {
		$user_id  = self::factory()->user->create( array( 'role' => 'editor' ) );
		$activity = array(
			'object' => array(
				'type'    => 'Article',
				'name'    => 'Original Title',
				'content' => '<p>Original content.</p>',
				'summary' => 'Original summary.',
			),
		);

		$post = Posts::create( $activity, $user_id );
		$this->assertInstanceOf( '\WP_Post', $post );

		$update_activity = array(
			'object' => array(
				'type'    => 'Article',
				'name'    => 'Updated Title',
				'content' => '<p>Updated content.</p>',
				'summary' => 'Updated summary.',
			),
		);

		$updated = Posts::update( $post, $update_activity );

		$this->assertInstanceOf( '\WP_Post', $updated );
		$this->assertEquals( $post->ID, $updated->ID );
		$this->assertEquals( 'Updated Title', $updated->post_title );
		$this->assertEquals( 'Updated summary.', $updated->post_excerpt );
		$this->assertStringContainsString( 'Updated content.', $updated->post_content );
	}

	/**
	 * Test updating a post with visibility.
	 *
	 * @covers ::update
	 */
	public function test_update_with_visibility() {
		$user_id  = self::factory()->user->create( array( 'role' => 'editor' ) );
		$activity = array(
			'object' => array(
				'type'    => 'Note',
				'content' => '<p>Content.</p>',
			),
		);

		$post = Posts::create( $activity, $user_id );

		$update_activity = array(
			'object' => array(
				'type'    => 'Note',
				'content' => '<p>Updated.</p>',
			),
		);

		$updated = Posts::update( $post, $update_activity, ACTIVITYPUB_CONTENT_VISIBILITY_QUIET_PUBLIC );

		$this->assertInstanceOf( '\WP_Post', $updated );
		$this->assertEquals(
			ACTIVITYPUB_CONTENT_VISIBILITY_QUIET_PUBLIC,
			\get_post_meta( $updated->ID, 'activitypub_content_visibility', true )
		);
	}

	/**
	 * Test update processes content through prepare_content pipeline.
	 *
	 * @covers ::update
	 * @covers ::prepare_content
	 */
	public function test_update_processes_content() {
		$user_id  = self::factory()->user->create( array( 'role' => 'editor' ) );
		$activity = array(
			'object' => array(
				'type'    => 'Note',
				'content' => '<p>Original.</p>',
			),
		);

		$post = Posts::create( $activity, $user_id );

		$update_activity = array(
			'object' => array(
				'type'    => 'Note',
				'content' => '<p>Updated paragraph</p>',
			),
		);

		$updated = Posts::update( $post, $update_activity );

		$this->assertInstanceOf( '\WP_Post', $updated );
		$this->assertStringContainsString( '<!-- wp:paragraph -->', $updated->post_content );
	}

	/**
	 * Test deleting (trashing) a post.
	 *
	 * @covers ::delete
	 */
	public function test_delete() {
		$post_id = self::factory()->post->create();

		$result = Posts::delete( $post_id );

		$this->assertInstanceOf( '\WP_Post', $result );

		$post = \get_post( $post_id );
		$this->assertEquals( 'trash', $post->post_status );
	}

	/**
	 * Test deleting a non-existent post.
	 *
	 * @covers ::delete
	 */
	public function test_delete_nonexistent() {
		$result = Posts::delete( 999999 );

		$this->assertNull( $result );
	}

	/**
	 * Data provider for prepare_content tests.
	 *
	 * @return array Test cases.
	 */
	public function data_prepare_content() {
		return array(
			'empty string'            => array(
				'',
				'',
			),
			'plain text gets wpautop' => array(
				'Hello world',
				// wpautop wraps in <p>, then converted to block.
				'<!-- wp:paragraph --><p>Hello world</p><!-- /wp:paragraph -->',
			),
			'existing paragraph'      => array(
				'<p>Already wrapped</p>',
				'<!-- wp:paragraph --><p>Already wrapped</p><!-- /wp:paragraph -->',
			),
			'multiple paragraphs'     => array(
				'<p>First</p><p>Second</p>',
				'<!-- wp:paragraph --><p>First</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Second</p><!-- /wp:paragraph -->',
			),
			'heading preserved'       => array(
				'<h2>A heading</h2>',
				'<!-- wp:heading --><h2>A heading</h2><!-- /wp:heading -->',
			),
			'blockquote'              => array(
				'<blockquote><p>Quote</p></blockquote>',
				'<!-- wp:quote --><blockquote><p>Quote</p></blockquote><!-- /wp:quote -->',
			),
		);
	}

	/**
	 * Test prepare_content pipeline.
	 *
	 * @dataProvider data_prepare_content
	 * @covers ::prepare_content
	 *
	 * @param string $input    The input content.
	 * @param string $expected The expected output.
	 */
	public function test_prepare_content( $input, $expected ) {
		$this->assertSame( $expected, Posts::prepare_content( $input ) );
	}
}
