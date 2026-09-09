<?php
/**
 * Test file for Comment Functions.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

/**
 * Test class for Comment Functions.
 */
class Test_Functions_Comment extends \WP_UnitTestCase {

	/**
	 * Post ID.
	 *
	 * @var int
	 */
	public $post_id;

	/**
	 * Set up the test.
	 */
	public function set_up() {
		parent::set_up();

		$this->post_id = \wp_insert_post(
			array(
				'post_author'  => 1,
				'post_content' => 'test',
			)
		);
	}

	/**
	 * Tear down.
	 */
	public function tear_down() {
		parent::tear_down();

		_delete_all_posts();
	}

	/**
	 * Test object_id_to_comment.
	 *
	 * @covers \Activitypub\object_id_to_comment
	 */
	public function test_object_id_to_comment_basic() {
		$single_comment_source_id = 'https://example.com/single';
		$content                  = 'example comment that has bunch of text';
		$comment_id               = \wp_new_comment(
			array(
				'comment_post_ID'      => $this->post_id,
				'comment_author'       => 'Example User',
				'comment_author_url'   => 'https://example.com/user',
				'comment_content'      => $content,
				'comment_type'         => '',
				'comment_author_email' => '',
				'comment_parent'       => 0,
				'comment_meta'         => array(
					'source_id'  => $single_comment_source_id,
					'source_url' => 'https://example.com/123',
					'avatar_url' => 'https://example.com/icon',
					'protocol'   => 'activitypub',
				),
			),
			true
		);
		$query_result             = \Activitypub\object_id_to_comment( $single_comment_source_id );
		$this->assertInstanceOf( \WP_Comment::class, $query_result );
		$this->assertEquals( $comment_id, $query_result->comment_ID );
		$this->assertEquals( $content, $query_result->comment_content );
	}

	/**
	 * Test object_id_to_comment with invalid source ID.
	 *
	 * @covers \Activitypub\object_id_to_comment
	 */
	public function test_object_id_to_comment_none() {
		$single_comment_source_id = 'https://example.com/none';
		$query_result             = \Activitypub\object_id_to_comment( $single_comment_source_id );
		$this->assertFalse( $query_result );
	}

	/**
	 * Test object_id_to_comment with duplicate source ID.
	 *
	 * @covers \Activitypub\object_id_to_comment
	 */
	public function test_object_id_to_comment_duplicate() {
		$duplicate_comment_source_id = 'https://example.com/duplicate';

		add_filter( 'duplicate_comment_id', '__return_zero', 99 );
		add_filter( 'wp_is_comment_flood', '__return_false', 99 );
		for ( $i = 0; $i < 2; ++$i ) {
			\wp_new_comment(
				array(
					'comment_post_ID'      => $this->post_id,
					'comment_author'       => 'Example User',
					'comment_author_url'   => 'https://example.com/user',
					'comment_content'      => 'example comment',
					'comment_type'         => '',
					'comment_author_email' => '',
					'comment_parent'       => 0,
					'comment_meta'         => array(
						'source_id'  => $duplicate_comment_source_id,
						'source_url' => 'https://example.com/123',
						'avatar_url' => 'https://example.com/icon',
						'protocol'   => 'activitypub',
					),
				),
				true
			);
		}
		remove_filter( 'duplicate_comment_id', '__return_zero', 99 );
		remove_filter( 'wp_is_comment_flood', '__return_false', 99 );

		$query_result = \Activitypub\object_id_to_comment( $duplicate_comment_source_id );
		$this->assertInstanceOf( \WP_Comment::class, $query_result );
	}

	/**
	 * Test get_comment_id returns ActivityPub ID for a comment.
	 *
	 * @covers \Activitypub\get_comment_id
	 */
	public function test_get_comment_id() {
		$comment_id = \wp_insert_comment(
			array(
				'comment_post_ID'      => $this->post_id,
				'comment_content'      => 'Test comment for ID.',
				'comment_author_email' => '',
			)
		);

		$result = \Activitypub\get_comment_id( $comment_id );

		$this->assertIsString( $result );
		$this->assertNotEmpty( $result );
		// Should match Comment::generate_id output.
		$this->assertSame( \Activitypub\Comment::generate_id( $comment_id ), $result );
	}

	/**
	 * Test get_comment_id with a WP_Comment object.
	 *
	 * @covers \Activitypub\get_comment_id
	 */
	public function test_get_comment_id_with_object() {
		$comment_id = \wp_insert_comment(
			array(
				'comment_post_ID'      => $this->post_id,
				'comment_content'      => 'Test comment object.',
				'comment_author_email' => '',
			)
		);

		$comment = \get_comment( $comment_id );
		$result  = \Activitypub\get_comment_id( $comment );

		$this->assertIsString( $result );
		$this->assertNotEmpty( $result );
		$this->assertSame( \Activitypub\Comment::generate_id( $comment ), $result );
	}

	/**
	 * Test get comment ancestors.
	 *
	 * @covers \Activitypub\get_comment_ancestors
	 */
	public function test_get_comment_ancestors() {
		$comment_id = wp_insert_comment(
			array(
				'comment_type'         => 'comment',
				'comment_content'      => 'This is a comment.',
				'comment_author_url'   => 'https://example.com',
				'comment_author_email' => '',
				'comment_meta'         => array(
					'protocol' => 'activitypub',
				),
			)
		);

		$this->assertEquals( array(), \Activitypub\get_comment_ancestors( $comment_id ) );

		$comment_array = get_comment( $comment_id, ARRAY_A );

		$parent_comment_id = wp_insert_comment(
			array(
				'comment_type'         => 'parent comment',
				'comment_content'      => 'This is a parent comment.',
				'comment_author_url'   => 'https://example.com',
				'comment_author_email' => '',
				'comment_meta'         => array(
					'protocol' => 'activitypub',
				),
			)
		);

		$comment_array['comment_parent'] = $parent_comment_id;

		wp_update_comment( $comment_array );

		$this->assertEquals( array( $parent_comment_id ), \Activitypub\get_comment_ancestors( $comment_id ) );
	}

	/**
	 * Test that a reaction author name is cleaned even when written past the filters.
	 *
	 * `wp_insert_comment()` callers bypass core's `pre_comment_author_name` chain, so the
	 * column is not guaranteed tag-free.
	 */
	public function test_get_reaction_author_name() {
		// The factory inserts through wp_insert_comment(), which bypasses the `pre_` filter chain.
		$comment_id = self::factory()->comment->create( array( 'comment_author' => '<img src=x onerror=alert(1)>&amp;friends' ) );

		// The tag goes and the entity is decoded, because the sinks that render this escape it again.
		$this->assertSame( '&friends', \Activitypub\get_reaction_author_name( \get_comment( $comment_id ) ) );
	}

	/**
	 * Test that an entity-encoded pseudo-tag is not revived into a live tag string.
	 *
	 * The name reaches REST consumers and the block's Interactivity state, so an escaped
	 * tag has to stay escaped.
	 */
	public function test_get_reaction_author_name_strips_revived_tags() {
		$comment_id = self::factory()->comment->create( array( 'comment_author' => '&lt;img src=x onerror=alert(1)&gt;friends' ) );

		$name = \Activitypub\get_reaction_author_name( \get_comment( $comment_id ) );

		// Decoding happens first and stripping second, so the revived tag is removed, not returned.
		$this->assertStringNotContainsString( '<img', $name );
		$this->assertSame( 'friends', $name );
	}
}
