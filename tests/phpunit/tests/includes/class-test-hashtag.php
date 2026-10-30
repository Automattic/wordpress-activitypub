<?php
/**
 * Test file for Activitypub Hashtag.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

use Activitypub\Collection\Remote_Actors;

/**
 * Test class for Activitypub Hashtag.
 *
 * @coversDefaultClass \Activitypub\Hashtag
 */
class Test_Hashtag extends \WP_UnitTestCase {
	/**
	 * Test the content.
	 *
	 * @dataProvider the_content_provider
	 * @covers ::the_content
	 *
	 * @param string $content The content.
	 * @param string $content_with_hashtag The content with hashtag.
	 */
	public function test_the_content( $content, $content_with_hashtag ) {
		\wp_create_term( 'object', 'post_tag' );
		\wp_create_term( 'touch', 'post_tag' );
		\wp_create_term( 'ccc', 'post_tag' );
		$object = \get_term_by( 'name', 'object', 'post_tag' );
		$link   = \get_term_link( $object, 'post_tag' );

		$content = \Activitypub\Hashtag::the_content( $content );

		$this->assertEquals( sprintf( $content_with_hashtag, $link ), $content );
	}

	/**
	 * The content provider.
	 *
	 * @return array[] The content.
	 */
	public function the_content_provider() {
		$code     = '<code>text with some #object and <a> tag inside</code>';
		$style    = <<<'ENDSTYLE'
<style type="text/css">
<![CDATA[
color: #ccc;
]]>
</style>
ENDSTYLE;
		$pre      = <<<'ENDPRE'
<pre>
Please don't #touch
  this.
</pre>
ENDPRE;
		$textarea = '<textarea name="test" rows="20">color: #ccc</textarea>';
		return array(
			array( 'test', 'test' ),
			array( '#test', '#test' ),
			array( 'hallo #test test', 'hallo #test test' ),
			array( 'hallo #object test', 'hallo <a rel="tag" class="hashtag u-tag u-category" href="%s">#object</a> test' ),
			array( '#object test', '<a rel="tag" class="hashtag u-tag u-category" href="%s">#object</a> test' ),
			array( 'hallo <a href="http://test.test/#object">test</a> test', 'hallo <a href="http://test.test/#object">test</a> test' ),
			array( 'hallo <a href="http://test.test/#object">#test</a> test', 'hallo <a href="http://test.test/#object">#test</a> test' ),
			array( '<div>hallo #object test</div>', '<div>hallo <a rel="tag" class="hashtag u-tag u-category" href="%s">#object</a> test</div>' ),
			array( '<div>hallo #object</div>', '<div>hallo <a rel="tag" class="hashtag u-tag u-category" href="%s">#object</a></div>' ),
			array( '<div>#object</div>', '<div><a rel="tag" class="hashtag u-tag u-category" href="%s">#object</a></div>' ),
			array( '<a>#object</a>', '<a>#object</a>' ),
			array( '<!-- #object -->', '<!-- #object -->' ),
			array( '<div style="color: #ccc;">object</a>', '<div style="color: #ccc;">object</a>' ),
			array( $code, $code ),
			array( $style, $style ),
			array( $textarea, $textarea ),
			array( $pre, $pre ),
		);
	}

	/**
	 * Tests auto-converting hashtags to tags.
	 *
	 * @see https://github.com/Automattic/wordpress-activitypub/issues/955
	 * @dataProvider hashtag_provider
	 * @covers ::insert_post
	 *
	 * @param string   $content       The post content.
	 * @param string   $excerpt       The post excerpt.
	 * @param string[] $expected_tags The expected tags.
	 * @param string   $message       The error message.
	 */
	public function test_hashtag_conversion( $content, $excerpt, $expected_tags, $message ) {
		$post_id = self::factory()->post->create(
			array(
				'post_content' => $content,
				'post_excerpt' => $excerpt,
				'post_author'  => 1,
			)
		);

		\Activitypub\Hashtag::insert_post( $post_id, get_post( $post_id ) );
		$tags = wp_get_post_tags( $post_id, array( 'fields' => 'names' ) );

		foreach ( $expected_tags as $tag ) {
			$this->assertContains( $tag, $tags, $message );
		}
	}

	/**
	 * Test no hashtags for unsupported post types.
	 *
	 * @covers ::insert_post
	 */
	public function test_no_hashtags_for_unsupported_post_types() {
		$post_id = self::factory()->post->create(
			array(
				'post_content' => 'Testing #php and #programming',
				'post_type'    => Remote_Actors::POST_TYPE,
			)
		);

		\Activitypub\Hashtag::insert_post( $post_id, get_post( $post_id ) );
		$tags = wp_get_post_tags( $post_id, array( 'fields' => 'names' ) );

		$this->assertEmpty( $tags, 'Should not add hashtags to unsupported post types' );
	}

	/**
	 * Test that hashtags inside protected HTML elements are not extracted.
	 *
	 * This ensures insert_post behavior matches enrich_content_data by skipping
	 * hashtags inside <a>, <pre>, <code>, <textarea>, and HTML comments.
	 *
	 * Note: WordPress strips <style> and <script> tags (but keeps content) via wp_kses,
	 * so we don't test those here as they're handled differently.
	 *
	 * @covers ::insert_post
	 */
	public function test_hashtags_in_protected_elements_ignored() {
		// First, remove the hook to prevent double-processing.
		\remove_action( 'wp_insert_post', array( \Activitypub\Hashtag::class, 'insert_post' ) );

		$content = '
			<a href="/issues/1">#linktext</a>
			<pre>#precode</pre>
			<code>#inlinecode</code>
			<!-- #commenttag -->
			<p>#validtagp</p>
			<div>#validtagdiv</div>
			#validtag
		';

		$post_id = self::factory()->post->create(
			array(
				'post_content' => $content,
				'post_author'  => 1,
			)
		);

		\Activitypub\Hashtag::insert_post( $post_id, get_post( $post_id ) );
		$tags = wp_get_post_tags( $post_id, array( 'fields' => 'names' ) );

		// Should contain hashtags from non-protected elements.
		$this->assertContains( 'validtag', $tags, 'Should extract hashtags outside HTML elements' );
		$this->assertContains( 'validtagp', $tags, 'Should extract hashtags from <p> tags' );
		$this->assertContains( 'validtagdiv', $tags, 'Should extract hashtags from <div> tags' );

		// Should NOT contain hashtags from protected elements.
		$this->assertNotContains( 'linktext', $tags, 'Should not extract hashtags from <a> tags' );
		$this->assertNotContains( 'precode', $tags, 'Should not extract hashtags from <pre> tags' );
		$this->assertNotContains( 'inlinecode', $tags, 'Should not extract hashtags from <code> tags' );
		$this->assertNotContains( 'commenttag', $tags, 'Should not extract hashtags from HTML comments' );

		// Re-add the hook to restore global state for subsequent tests.
		\add_action( 'wp_insert_post', array( \Activitypub\Hashtag::class, 'insert_post' ), 10, 2 );
	}

	/**
	 * Test that numeric hashtags in links are not extracted.
	 *
	 * This is a specific test for issue #2715 where #1, #2 tags were being
	 * added from content like <a href="#">#1</a>.
	 *
	 * @see https://github.com/Automattic/wordpress-activitypub/issues/2715
	 * @covers ::insert_post
	 */
	public function test_numeric_hashtags_in_links_ignored() {
		// First, remove the hook to prevent double-processing.
		\remove_action( 'wp_insert_post', array( \Activitypub\Hashtag::class, 'insert_post' ) );

		$content = '
			<ol>
				<li><a href="#1">#1</a> First item</li>
				<li><a href="#2">#2</a> Second item</li>
			</ol>
			#validhashtag
		';

		$post_id = self::factory()->post->create(
			array(
				'post_content' => $content,
				'post_author'  => 1,
			)
		);

		\Activitypub\Hashtag::insert_post( $post_id, get_post( $post_id ) );
		$tags = wp_get_post_tags( $post_id, array( 'fields' => 'names' ) );

		// Should contain the valid hashtag.
		$this->assertContains( 'validhashtag', $tags, 'Should extract valid hashtags' );

		// Should NOT contain numeric hashtags from links.
		$this->assertNotContains( '1', $tags, 'Should not extract #1 from links' );
		$this->assertNotContains( '2', $tags, 'Should not extract #2 from links' );

		// Re-add the hook to restore global state for subsequent tests.
		\add_action( 'wp_insert_post', array( \Activitypub\Hashtag::class, 'insert_post' ), 10, 2 );
	}

	/**
	 * Data provider for hashtag tests.
	 *
	 * @return array[] The data.
	 */
	public function hashtag_provider() {
		return array(
			'basic_hashtags'         => array(
				'Testing #php and #programming',
				'',
				array( 'php', 'programming' ),
				'Basic hashtags should be converted',
			),
			'hashtags_in_attributes' => array(
				'<div style="color: #fff">#validtag</div>',
				'',
				array( 'validtag' ),
				'Hashtags in HTML attributes should be ignored',
			),
			'mixed_content'          => array(
				'Color is #red <span style="color: #ff0000">#valid</span> #blue',
				'',
				array( 'red', 'blue', 'valid' ),
				'Should handle mixed content correctly',
			),
			'hex_in_text'            => array(
				'<span style="color: #ff0000">#f00</span> #fff #000000',
				'',
				array( 'f00', 'fff', '000000' ),
				'Hex colors in text should be converted',
			),
			'excerpt_tags'           => array(
				'',
				'Testing #excerpt with #tags',
				array( 'excerpt', 'tags' ),
				'Should process excerpt hashtags',
			),
			'multiple_attributes'    => array(
				'<div data-color="#123" style="border: 1px solid #456">#valid</div>',
				'',
				array( 'valid' ),
				'Should ignore multiple attribute hashtags',
			),
			'quotes_in_content'      => array(
				'Here is a "#quoted" #tag',
				'',
				array( 'tag' ),
				'Should handle quotes in content correctly',
			),
		);
	}

	/**
	 * Test that hashtag filters are added when the setting is enabled.
	 *
	 * @covers ::init
	 */
	public function test_init_adds_filters_when_enabled() {
		// Remove any existing filters first.
		\remove_action( 'wp_insert_post', array( \Activitypub\Hashtag::class, 'insert_post' ) );
		\remove_filter( 'the_content', array( \Activitypub\Hashtag::class, 'the_content' ) );
		\remove_filter( 'activitypub_activity_object_array', array( \Activitypub\Hashtag::class, 'filter_activity_object' ), 99 );

		// Enable the hashtag setting.
		\update_option( 'activitypub_use_hashtags', '1' );

		// Call init.
		\Activitypub\Hashtag::init();

		// Verify filters were added.
		$this->assertNotFalse(
			\has_action( 'wp_insert_post', array( \Activitypub\Hashtag::class, 'insert_post' ) ),
			'insert_post action should be added when hashtags are enabled'
		);
		$this->assertNotFalse(
			\has_filter( 'the_content', array( \Activitypub\Hashtag::class, 'the_content' ) ),
			'the_content filter should be added when hashtags are enabled'
		);
		$this->assertNotFalse(
			\has_filter( 'activitypub_activity_object_array', array( \Activitypub\Hashtag::class, 'filter_activity_object' ) ),
			'filter_activity_object filter should be added when hashtags are enabled'
		);

		// Clean up.
		\delete_option( 'activitypub_use_hashtags' );
	}

	/**
	 * Test that hashtag filters are not added when the setting is disabled.
	 *
	 * @covers ::init
	 */
	public function test_init_does_not_add_filters_when_disabled() {
		// Remove any existing filters first.
		\remove_action( 'wp_insert_post', array( \Activitypub\Hashtag::class, 'insert_post' ) );
		\remove_filter( 'the_content', array( \Activitypub\Hashtag::class, 'the_content' ) );
		\remove_filter( 'activitypub_activity_object_array', array( \Activitypub\Hashtag::class, 'filter_activity_object' ), 99 );

		// Disable the hashtag setting (default).
		\update_option( 'activitypub_use_hashtags', '0' );

		// Call init.
		\Activitypub\Hashtag::init();

		// Verify filters were NOT added.
		$this->assertFalse(
			\has_action( 'wp_insert_post', array( \Activitypub\Hashtag::class, 'insert_post' ) ),
			'insert_post action should not be added when hashtags are disabled'
		);
		$this->assertFalse(
			\has_filter( 'the_content', array( \Activitypub\Hashtag::class, 'the_content' ) ),
			'the_content filter should not be added when hashtags are disabled'
		);
		$this->assertFalse(
			\has_filter( 'activitypub_activity_object_array', array( \Activitypub\Hashtag::class, 'filter_activity_object' ) ),
			'filter_activity_object filter should not be added when hashtags are disabled'
		);

		// Clean up.
		\delete_option( 'activitypub_use_hashtags' );
	}

	/**
	 * Test that hashtag filters are enabled when the option is the string '1'.
	 *
	 * WordPress checkboxes can store values like '1', 1, true, or '0', 0, false, ''.
	 * The current implementation treats only the string '1' as enabling hashtags,
	 * and this test verifies that behavior.
	 *
	 * @covers ::init
	 */
	public function test_init_with_string_option_value() {
		// Remove any existing filters first.
		\remove_action( 'wp_insert_post', array( \Activitypub\Hashtag::class, 'insert_post' ) );
		\remove_filter( 'the_content', array( \Activitypub\Hashtag::class, 'the_content' ) );
		\remove_filter( 'activitypub_activity_object_array', array( \Activitypub\Hashtag::class, 'filter_activity_object' ), 99 );

		// Test with string '1' (typical checkbox value).
		\update_option( 'activitypub_use_hashtags', '1' );
		\Activitypub\Hashtag::init();

		$this->assertNotFalse(
			\has_filter( 'the_content', array( \Activitypub\Hashtag::class, 'the_content' ) ),
			'Hashtag feature should be enabled with string "1"'
		);

		// Clean up.
		\remove_action( 'wp_insert_post', array( \Activitypub\Hashtag::class, 'insert_post' ) );
		\remove_filter( 'the_content', array( \Activitypub\Hashtag::class, 'the_content' ) );
		\remove_filter( 'activitypub_activity_object_array', array( \Activitypub\Hashtag::class, 'filter_activity_object' ), 99 );
		\delete_option( 'activitypub_use_hashtags' );
	}

	/**
	 * Core's excerpt strips the hashtag link and keeps the hashtag as text, like before.
	 *
	 * @covers ::the_content
	 */
	public function test_core_excerpt_keeps_hashtag_as_text() {
		$post = $this->create_hashtag_post();

		$this->enable_hashtags();
		$excerpt = \get_the_excerpt( $post );
		$this->disable_hashtags();

		$this->assertSame( 'hallo #object test', $excerpt );
	}

	/**
	 * An excerpt that keeps its HTML, like a theme builds it from the content, keeps the hashtag link.
	 *
	 * @covers ::the_content
	 */
	public function test_html_excerpt_keeps_hashtag_link() {
		$post = $this->create_hashtag_post();
		$link = \get_term_link( \get_term_by( 'name', 'object', 'post_tag' ), 'post_tag' );

		$html_excerpt = static function ( $text, $post ) {
			return \strip_tags( \apply_filters( 'the_content', $post->post_content ), '<a>,<p>' );
		};
		\remove_filter( 'get_the_excerpt', 'wp_trim_excerpt' );
		\add_filter( 'get_the_excerpt', $html_excerpt, 10, 2 );
		$this->enable_hashtags();

		$excerpt = \get_the_excerpt( $post );

		$this->disable_hashtags();
		\remove_filter( 'get_the_excerpt', $html_excerpt );
		\add_filter( 'get_the_excerpt', 'wp_trim_excerpt', 10, 2 );

		// WordPress trunk serializes the attributes of rendered blocks in its own order.
		$processor = new \WP_HTML_Tag_Processor( $excerpt );
		$this->assertTrue( $processor->next_tag( array( 'class_name' => 'hashtag' ) ) );
		$this->assertSame( 'A', $processor->get_tag() );
		$this->assertSame( 'tag', $processor->get_attribute( 'rel' ) );
		$this->assertSame( \esc_url( $link ), $processor->get_attribute( 'href' ) );
		$this->assertStringContainsString( '#object</a> test', $excerpt );
	}

	/**
	 * The summary of a post keeps hashtags as text, the content links them, like before.
	 *
	 * @covers ::filter_activity_object
	 */
	public function test_post_summary_keeps_hashtag_as_text() {
		$post = $this->create_hashtag_post();
		$link = \get_term_link( \get_term_by( 'name', 'object', 'post_tag' ), 'post_tag' );

		$this->enable_hashtags();
		$object = \Activitypub\Transformer\Factory::get_transformer( $post )->to_object()->to_array();
		$this->disable_hashtags();

		$this->assertSame( 'hallo #object test', $object['summary'] );
		$this->assertStringContainsString( \sprintf( '<a rel="tag" class="hashtag u-tag u-category" href="%s">#object</a>', \esc_url( $link ) ), $object['content'] );
	}

	/**
	 * Create a post with a hashtag of an existing tag.
	 *
	 * @return \WP_Post The post.
	 */
	private function create_hashtag_post() {
		\wp_create_term( 'object', 'post_tag' );

		return \get_post(
			self::factory()->post->create(
				array(
					'post_author'  => self::factory()->user->create( array( 'role' => 'author' ) ),
					'post_title'   => 'Title',
					'post_excerpt' => '',
					'post_content' => '<!-- wp:paragraph --><p>hallo #object test</p><!-- /wp:paragraph -->',
				)
			)
		);
	}

	/**
	 * Register the hashtag filters, like the enabled setting does.
	 */
	private function enable_hashtags() {
		\add_filter( 'the_content', array( \Activitypub\Hashtag::class, 'the_content' ) );
		\add_filter( 'activitypub_activity_object_array', array( \Activitypub\Hashtag::class, 'filter_activity_object' ), 99 );
	}

	/**
	 * Remove the hashtag filters again.
	 */
	private function disable_hashtags() {
		\remove_filter( 'the_content', array( \Activitypub\Hashtag::class, 'the_content' ) );
		\remove_filter( 'activitypub_activity_object_array', array( \Activitypub\Hashtag::class, 'filter_activity_object' ), 99 );
	}
}
