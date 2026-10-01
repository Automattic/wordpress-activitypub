<?php
/**
 * Test Polylang integration.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Integration;

use Activitypub\Integration\Polylang;
use Activitypub\Transformer\Comment;
use Activitypub\Transformer\Post;

/**
 * Test the Polylang integration.
 *
 * @group integration
 * @coversDefaultClass \Activitypub\Integration\Polylang
 */
class Test_Polylang extends \WP_UnitTestCase {
	/**
	 * Set up.
	 */
	public function set_up() {
		parent::set_up();

		require_once AP_TESTS_DIR . '/data/mocks/polylang-api.php';

		Polylang::init();
	}

	/**
	 * Tear down.
	 */
	public function tear_down() {
		\remove_filter( 'activitypub_locale', array( Polylang::class, 'get_post_locale' ) );
		\remove_filter( 'mastodon_api_status_language', array( Polylang::class, 'get_post_locale' ) );
		\remove_filter( 'mastodon_api_pre_save_status_language', array( Polylang::class, 'save_status_language' ) );

		parent::tear_down();
	}

	/**
	 * A post's content map is keyed by the language Polylang assigned to it.
	 *
	 * @covers ::get_post_locale
	 */
	public function test_post_uses_its_polylang_language() {
		$post_id = self::factory()->post->create( array( 'post_content' => 'Hallo Welt' ) );
		\update_post_meta( $post_id, '_test_pll_language', 'de' );

		$object = Post::transform( \get_post( $post_id ) )->to_object();

		$this->assertSame( array( 'de' ), \array_keys( $object->get_content_map() ) );
	}

	/**
	 * A comment takes the language of the post it belongs to.
	 *
	 * @covers ::get_post_locale
	 */
	public function test_comment_uses_the_language_of_its_post() {
		$post_id    = self::factory()->post->create();
		$comment_id = self::factory()->comment->create( array( 'comment_post_ID' => $post_id ) );
		\update_post_meta( $post_id, '_test_pll_language', 'fr' );

		$object = Comment::transform( \get_comment( $comment_id ) )->to_object();

		$this->assertSame( array( 'fr' ), \array_keys( $object->get_content_map() ) );
	}

	/**
	 * A post without a Polylang language keeps the site locale.
	 *
	 * @covers ::get_post_locale
	 */
	public function test_post_without_language_keeps_the_site_locale() {
		$post_id = self::factory()->post->create();

		$object = Post::transform( \get_post( $post_id ) )->to_object();

		$this->assertSame( array( 'en' ), \array_keys( $object->get_content_map() ) );
	}

	/**
	 * A Mastodon app sees the language Polylang assigned to the post.
	 *
	 * @covers ::get_post_locale
	 */
	public function test_status_uses_the_polylang_language() {
		$post_id = self::factory()->post->create();
		\update_post_meta( $post_id, '_test_pll_language', 'de' );

		$this->assertSame( 'de', \apply_filters( 'mastodon_api_status_language', null, \get_post( $post_id ) ) );
	}

	/**
	 * A post without a Polylang language keeps the language the app stored.
	 *
	 * @covers ::get_post_locale
	 */
	public function test_status_without_polylang_language_keeps_the_stored_language() {
		$post_id = self::factory()->post->create();

		$this->assertSame( 'de', \apply_filters( 'mastodon_api_status_language', 'de', \get_post( $post_id ) ) );
	}

	/**
	 * The language a Mastodon app sets is stored with Polylang instead of as post meta.
	 *
	 * @covers ::save_status_language
	 */
	public function test_app_language_is_stored_with_polylang() {
		$post_id = self::factory()->post->create();

		$stored = \apply_filters( 'mastodon_api_pre_save_status_language', false, $post_id, 'de' );

		$this->assertTrue( $stored );
		$this->assertSame( 'de', \pll_get_post_language( $post_id ) );
	}

	/**
	 * A language Polylang does not know is left to the post meta fallback.
	 *
	 * @covers ::save_status_language
	 */
	public function test_unknown_app_language_is_left_to_the_post_meta() {
		$post_id = self::factory()->post->create();

		$stored = \apply_filters( 'mastodon_api_pre_save_status_language', false, $post_id, 'xx' );

		$this->assertFalse( $stored );
		$this->assertFalse( \pll_get_post_language( $post_id ) );
	}
}
