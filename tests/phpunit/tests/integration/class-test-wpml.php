<?php
/**
 * Test WPML integration.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Integration;

use Activitypub\Integration\WPML;
use Activitypub\Transformer\Comment;

/**
 * Test the WPML integration.
 *
 * @group integration
 * @coversDefaultClass \Activitypub\Integration\WPML
 */
class Test_WPML extends \WP_UnitTestCase {
	/**
	 * The arguments WPML was asked to store, captured from its action.
	 *
	 * @var array|null
	 */
	private $stored_language_details;

	/**
	 * Set up.
	 */
	public function set_up() {
		parent::set_up();

		WPML::init();

		$this->stored_language_details = null;
		\add_filter( 'wpml_active_languages', array( self::class, 'active_languages' ) );
		\add_action( 'wpml_set_element_language_details', array( $this, 'capture_language_details' ) );
	}

	/**
	 * Tear down.
	 */
	public function tear_down() {
		\remove_filter( 'activitypub_locale', array( WPML::class, 'get_wpml_post_locale' ) );
		\remove_filter( 'mastodon_api_status_language', array( WPML::class, 'get_wpml_post_locale' ) );
		\remove_filter( 'mastodon_api_pre_save_status_language', array( WPML::class, 'save_status_language' ) );
		\remove_filter( 'wpml_active_languages', array( self::class, 'active_languages' ) );
		\remove_action( 'wpml_set_element_language_details', array( $this, 'capture_language_details' ) );

		parent::tear_down();
	}

	/**
	 * Stand-in for WPML's `wpml_active_languages` filter.
	 *
	 * @return array[] The site's languages, keyed by code.
	 */
	public static function active_languages() {
		return array(
			'en' => array( 'code' => 'en' ),
			'de' => array( 'code' => 'de' ),
		);
	}

	/**
	 * Stand-in for WPML's `wpml_post_language_details` filter.
	 *
	 * @return array The language details of a German post.
	 */
	public static function german_post() {
		return array( 'language_code' => 'de' );
	}

	/**
	 * Record what WPML was asked to store.
	 *
	 * @param array $args The element language details.
	 */
	public function capture_language_details( $args ) {
		$this->stored_language_details = $args;
	}

	/**
	 * A comment takes the language WPML assigned to the post it belongs to.
	 *
	 * @covers ::get_wpml_post_locale
	 */
	public function test_comment_uses_the_language_of_its_post() {
		$post_id    = self::factory()->post->create();
		$comment_id = self::factory()->comment->create( array( 'comment_post_ID' => $post_id ) );

		\add_filter( 'wpml_post_language_details', array( self::class, 'german_post' ) );
		$object = Comment::transform( \get_comment( $comment_id ) )->to_object();
		\remove_filter( 'wpml_post_language_details', array( self::class, 'german_post' ) );

		$this->assertSame( array( 'de' ), \array_keys( $object->get_content_map() ) );
	}

	/**
	 * A Mastodon app sees the language WPML assigned to the post.
	 *
	 * @covers ::get_wpml_post_locale
	 */
	public function test_status_uses_the_wpml_language() {
		$post_id = self::factory()->post->create();

		\add_filter( 'wpml_post_language_details', array( self::class, 'german_post' ) );
		$language = \apply_filters( 'mastodon_api_status_language', null, \get_post( $post_id ) );
		\remove_filter( 'wpml_post_language_details', array( self::class, 'german_post' ) );

		$this->assertSame( 'de', $language );
	}

	/**
	 * A post without a WPML language keeps the language the app stored.
	 *
	 * @covers ::get_wpml_post_locale
	 */
	public function test_status_without_wpml_language_keeps_the_stored_language() {
		$post_id = self::factory()->post->create();

		$this->assertSame( 'de', \apply_filters( 'mastodon_api_status_language', 'de', \get_post( $post_id ) ) );
	}

	/**
	 * The language a Mastodon app sets is stored with WPML instead of as post meta.
	 *
	 * @covers ::save_status_language
	 */
	public function test_app_language_is_stored_with_wpml() {
		$post_id = self::factory()->post->create();

		$stored = \apply_filters( 'mastodon_api_pre_save_status_language', false, $post_id, 'de' );

		$this->assertTrue( $stored );
		$this->assertSame(
			array(
				'element_id'    => $post_id,
				'element_type'  => 'post_post',
				'language_code' => 'de',
			),
			$this->stored_language_details
		);
	}

	/**
	 * A language WPML does not know is left to the post meta fallback.
	 *
	 * @covers ::save_status_language
	 */
	public function test_unknown_app_language_is_left_to_the_post_meta() {
		$post_id = self::factory()->post->create();

		$stored = \apply_filters( 'mastodon_api_pre_save_status_language', false, $post_id, 'xx' );

		$this->assertFalse( $stored );
		$this->assertNull( $this->stored_language_details );
	}
}
