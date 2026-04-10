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
	 * Test creating a post with blog actor (user_id = 0) skips permission check.
	 *
	 * @covers ::create
	 */
	public function test_create_with_blog_actor() {
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
