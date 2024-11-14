<?php
/**
 * Tests file the Post transformer.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

use WP_UnitTestCase;
use Activitypub\Transformer\Post;
use ReflectionClass;

/**
 * Test cases for the Post transformer.
 *
 * @coversDefaultClass \Activitypub\Transformer\Post
 */
class Test_Transformer_Post extends WP_UnitTestCase {
	/**
	 * Reflection method for testing protected method.
	 *
	 * @var ReflectionMethod
	 */
	private $reflection_method;

	/**
	 * Set up the test case.
	 */
	public function set_up() {
		parent::set_up();

		update_option( 'activitypub_object_type', 'wordpress-post-format' );

		// Set up reflection method.
		$reflection              = new ReflectionClass( Post::class );
		$this->reflection_method = $reflection->getMethod( 'get_type' );
		$this->reflection_method->setAccessible( true );
	}

	/**
	 * Tear down the test case.
	 */
	public function tear_down() {
		// Reset options after each test.
		delete_option( 'activitypub_object_type' );

		parent::tear_down();
	}

	/**
	 * Test that the get_type method returns the configured type when the option is set.
	 *
	 * @covers ::get_type
	 */
	public function test_get_type_returns_configured_type_when_option_set() {
		update_option( 'activitypub_object_type', 'Article' );

		$post_id = $this->factory->post->create(
			array(
				'post_title'   => 'Test Post',
				'post_content' => 'Test content that is longer than the note length limit',
			)
		);
		$post    = get_post( $post_id );

		$transformer = new Post( $post );
		$type        = $this->reflection_method->invoke( $transformer );

		$this->assertSame( 'Article', $type );
	}

	/**
	 * Test that the get_type method returns note for short content.
	 *
	 * @covers ::get_type
	 */
	public function test_get_type_returns_note_for_short_content() {
		$post_id = $this->factory->post->create(
			array(
				'post_title'   => 'Test Post',
				'post_content' => 'Short content',
			)
		);
		$post    = get_post( $post_id );

		$transformer = new Post( $post );
		$type        = $this->reflection_method->invoke( $transformer );

		$this->assertSame( 'Note', $type );
	}

	/**
	 * Test that the get_type method returns note for posts without title.
	 *
	 * @covers ::get_type
	 */
	public function test_get_type_returns_note_for_posts_without_title() {
		$post_id = $this->factory->post->create(
			array(
				'post_title'   => '',
				'post_content' => str_repeat( 'Long content. ', 100 ),
			)
		);
		$post    = get_post( $post_id );

		$transformer = new Post( $post );
		$type        = $this->reflection_method->invoke( $transformer );

		$this->assertSame( 'Note', $type );
	}

	/**
	 * Test that the get_type method returns article for standard post format.
	 *
	 * @covers ::get_type
	 */
	public function test_get_type_returns_article_for_standard_post_format() {
		$post_id = $this->factory->post->create(
			array(
				'post_title'   => 'Test Post',
				'post_content' => str_repeat( 'Long content. ', 100 ),
				'post_type'    => 'post',
			)
		);
		set_post_format( $post_id, 'standard' );
		$post = get_post( $post_id );

		$transformer = new Post( $post );
		$type        = $this->reflection_method->invoke( $transformer );

		$this->assertSame( 'Article', $type );
	}

	/**
	 * Test that the get_type method returns page for page post type.
	 *
	 * @covers ::get_type
	 */
	public function test_get_type_returns_page_for_page_post_type() {
		$post_id = $this->factory->post->create(
			array(
				'post_title'   => 'Test Page',
				'post_content' => str_repeat( 'Long content. ', 100 ),
				'post_type'    => 'page',
			)
		);
		$post    = get_post( $post_id );

		$transformer = new Post( $post );
		$type        = $this->reflection_method->invoke( $transformer );

		$this->assertSame( 'Page', $type );
	}

	/**
	 * Test that the get_type method returns note for non-standard post format.
	 *
	 * @covers ::get_type
	 */
	public function test_get_type_returns_note_for_non_standard_post_format() {
		$post_id = $this->factory->post->create(
			array(
				'post_title'   => 'Test Post',
				'post_content' => str_repeat( 'Long content. ', 100 ),
				'post_type'    => 'post',
			)
		);
		set_post_format( $post_id, 'aside' );
		$post = get_post( $post_id );

		$transformer = new Post( $post );
		$type        = $this->reflection_method->invoke( $transformer );

		$this->assertSame( 'Note', $type );
	}

	/**
	 * Test that the get_type method returns note for missing post format.
	 *
	 * @covers ::get_type
	 */
	public function test_get_type_handles_missing_post_format() {
		$post_id = $this->factory->post->create(
			array(
				'post_title'   => 'Test Post',
				'post_content' => str_repeat( 'Long content. ', 100 ),
				'post_type'    => 'post',
			)
		);
		$post    = get_post( $post_id );

		$transformer = new Post( $post );
		$type        = $this->reflection_method->invoke( $transformer );

		$this->assertSame( 'Article', $type );
	}

	/**
	 * Test that the get_type method returns note for post type without title support.
	 *
	 * @covers ::get_type
	 */
	public function test_get_type_respects_post_type_title_support() {

		// Create custom post type without title support.
		register_post_type(
			'no_title_type',
			array(
				'public'   => true,
				'supports' => array( 'editor' ), // Explicitly exclude 'title'.
			)
		);

		$post_id = $this->factory->post->create(
			array(
				'post_title'   => 'Test Post',
				'post_content' => str_repeat( 'Long content. ', 100 ),
				'post_type'    => 'no_title_type',
			)
		);
		$post    = get_post( $post_id );

		$transformer = new Post( $post );
		$type        = $this->reflection_method->invoke( $transformer );

		$this->assertSame( 'Note', $type );

		// Clean up.
		unregister_post_type( 'no_title_type' );
	}

	/**
	 * Test that the get_type method returns article for custom post type with post format support.
	 *
	 * @covers ::get_type
	 */
	public function test_get_type_respects_post_format_support() {

		// Create custom post type without title support.
		register_post_type(
			'no_title_type',
			array(
				'public'   => true,
				'supports' => array( 'editor', 'title', 'post-formats' ), // Needs to include 'title'.
			)
		);

		$post_id = $this->factory->post->create(
			array(
				'post_title'   => 'Test Post',
				'post_content' => str_repeat( 'Long content. ', 100 ),
				'post_type'    => 'no_title_type',
			)
		);
		$post    = get_post( $post_id );

		$transformer = new Post( $post );
		$type        = $this->reflection_method->invoke( $transformer );

		$this->assertSame( 'Article', $type );

		// Clean up.
		unregister_post_type( 'no_title_type' );
	}
}
