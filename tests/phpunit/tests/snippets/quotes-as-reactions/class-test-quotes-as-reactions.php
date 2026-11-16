<?php
/**
 * Tests for the Quotes as Reactions snippet.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Snippets;

use Activitypub\Comment;

use function Activitypub\Snippets\register_quotes_as_reactions;

/**
 * Test the opt-in that shows quotes as reactions again.
 */
class Test_Quotes_As_Reactions extends \WP_UnitTestCase {
	/**
	 * Load the snippet and register quotes as reactions.
	 */
	public function set_up() {
		parent::set_up();
		require_once ACTIVITYPUB_PLUGIN_DIR . 'snippets/quotes-as-reactions/quotes-as-reactions.php';
		register_quotes_as_reactions();
	}

	/**
	 * Restore the plugin's own comment types.
	 */
	public function tear_down() {
		Comment::register_comment_types();
		parent::tear_down();
	}

	/**
	 * A quote is a reaction again and keeps its other arguments.
	 *
	 * @covers \Activitypub\Snippets\register_quotes_as_reactions
	 */
	public function test_quote_is_a_reaction() {
		$this->assertContains( 'quote', Comment::get_comment_type_slugs( array( 'reaction' => true ) ) );
		$this->assertSame( 'quotes', Comment::get_comment_type_attr( 'quote', 'collection' ) );
	}

	/**
	 * A quote is not counted as a comment, like a like.
	 *
	 * The comment list is not checked here: an earlier test in the suite can leave
	 * `ACTIVITYPUB_REQUEST` defined, and the list filter skips ActivityPub requests.
	 *
	 * @covers \Activitypub\Snippets\register_quotes_as_reactions
	 */
	public function test_quote_is_not_counted() {
		$post_id = self::factory()->post->create();

		self::factory()->comment->create(
			array(
				'comment_post_ID'  => $post_id,
				'comment_approved' => '1',
			)
		);
		self::factory()->comment->create(
			array(
				'comment_post_ID'  => $post_id,
				'comment_approved' => '1',
				'comment_type'     => 'quote',
			)
		);

		$this->assertEquals( 1, \get_comments_number( $post_id ) );
	}
}
