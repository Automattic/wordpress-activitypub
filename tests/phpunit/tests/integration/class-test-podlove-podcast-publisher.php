<?php
/**
 * Test file for Podlove Podcast Publisher integration.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Integration;

/**
 * Test class for Podlove Podcast Publisher integration.
 *
 * @group integration
 * @coversDefaultClass \Activitypub\Integration\Podlove_Podcast_Publisher
 */
class Test_Podlove_Podcast_Publisher extends \WP_UnitTestCase {

	/**
	 * Load the Podlove Episode stub before each test.
	 */
	public function set_up() {
		parent::set_up();
		require_once AP_TESTS_DIR . '/includes/class-episode.php';
	}

	/**
	 * Reset the mocked episode after each test.
	 */
	public function tear_down() {
		if ( class_exists( '\Podlove\Model\Episode' ) ) {
			\Podlove\Model\Episode::$mock = null;
		}
		parent::tear_down();
	}

	/**
	 * Test that the transformer respects the configured object type setting.
	 */
	public function test_get_type_respects_setting() {
		$post = \wp_insert_post(
			array(
				'post_author'  => 1,
				'post_content' => 'Episode content',
				'post_title'   => 'Episode title',
				'post_status'  => 'publish',
				'post_type'    => 'post',
			)
		);
		$post = \get_post( $post );

		// Post-format mode: titled post without format = Article.
		\update_option( 'activitypub_object_type', 'wordpress-post-format' );
		$transformer = new \Activitypub\Integration\Podlove_Podcast_Publisher( $post );
		$object      = $transformer->to_object();
		$this->assertEquals( 'Article', $object->get_type() );

		// Explicit Note setting.
		\update_option( 'activitypub_object_type', 'note' );
		$transformer = new \Activitypub\Integration\Podlove_Podcast_Publisher( $post );
		$object      = $transformer->to_object();
		$this->assertEquals( 'Note', $object->get_type() );
	}

	/**
	 * Test that content is generated using the template engine.
	 *
	 * @covers ::get_content
	 */
	public function test_get_content() {
		$post = \wp_insert_post(
			array(
				'post_author'  => 1,
				'post_content' => 'This is the episode content that should be summarized.',
				'post_title'   => 'Episode title',
				'post_status'  => 'publish',
				'post_type'    => 'post',
			)
		);
		$post = \get_post( $post );

		$transformer = new \Activitypub\Integration\Podlove_Podcast_Publisher( $post );
		$object      = $transformer->to_object();

		$this->assertNotEmpty( $object->get_content() );
		$this->assertIsString( $object->get_content() );

		// Clean up.
		\wp_delete_post( $post->ID, true );
	}

	/**
	 * Test that get_attachment returns parent attachments when no episode.
	 *
	 * @covers ::get_attachment
	 */
	public function test_get_attachment_without_episode() {
		$post = \wp_insert_post(
			array(
				'post_author'  => 1,
				'post_content' => 'Episode content',
				'post_title'   => 'Episode title',
				'post_status'  => 'publish',
				'post_type'    => 'post',
			)
		);
		$post = \get_post( $post );

		$transformer = new \Activitypub\Integration\Podlove_Podcast_Publisher( $post );
		$attachments = $transformer->get_attachment();

		// Without Podlove Episode, should return parent's attachments (or empty array).
		$this->assertIsArray( $attachments );

		// Clean up.
		\wp_delete_post( $post->ID, true );
	}

	/**
	 * Test that get_duration returns null when no episode.
	 *
	 * @covers ::get_duration
	 */
	public function test_get_duration_without_episode() {
		$post = \wp_insert_post(
			array(
				'post_author'  => 1,
				'post_content' => 'Episode content',
				'post_title'   => 'Episode title',
				'post_status'  => 'publish',
				'post_type'    => 'post',
			)
		);
		$post = \get_post( $post );

		$transformer = new \Activitypub\Integration\Podlove_Podcast_Publisher( $post );
		$duration    = $transformer->get_duration();

		// Without Podlove Episode, should return null.
		$this->assertNull( $duration );

		// Clean up.
		\wp_delete_post( $post->ID, true );
	}

	/**
	 * Test that the integration class exists and extends Post transformer.
	 */
	public function test_class_exists_and_extends_post() {
		$this->assertTrue( class_exists( '\Activitypub\Integration\Podlove_Podcast_Publisher' ) );
		$this->assertTrue( is_subclass_of( '\Activitypub\Integration\Podlove_Podcast_Publisher', '\Activitypub\Transformer\Post' ) );
	}

	/**
	 * Test that the episode summary is federated as the object summary.
	 *
	 * @covers ::get_summary
	 */
	public function test_get_summary_uses_episode_summary() {
		\update_option( 'activitypub_object_type', 'wordpress-post-format' );

		$post = \get_post(
			\wp_insert_post(
				array(
					'post_author'  => 1,
					'post_title'   => 'Episode title',
					'post_content' => 'Episode content.',
					'post_status'  => 'publish',
					'post_type'    => 'post',
				)
			)
		);

		// Raw user input: tags and HTML entities should be sanitized like the default summary.
		$episode                      = new \Podlove\Model\Episode();
		$episode->summary             = '<p>Episode summary &amp; more</p>';
		\Podlove\Model\Episode::$mock = $episode;

		$object = ( new \Activitypub\Integration\Podlove_Podcast_Publisher( $post ) )->to_object();

		$this->assertSame( 'Episode summary & more', $object->get_summary() );

		\wp_delete_post( $post->ID, true );
	}

	/**
	 * Test that the transformer falls back to the default summary when the episode
	 * summary is empty (including markup/whitespace that sanitizes to nothing).
	 *
	 * @covers ::get_summary
	 */
	public function test_get_summary_falls_back_without_episode_summary() {
		\update_option( 'activitypub_object_type', 'wordpress-post-format' );

		$post = \get_post(
			\wp_insert_post(
				array(
					'post_author'  => 1,
					'post_title'   => 'Episode title',
					'post_content' => 'Episode content that should be summarized.',
					'post_status'  => 'publish',
					'post_type'    => 'post',
				)
			)
		);

		// Markup/whitespace only: sanitizes to an empty string, so the default summary is used.
		$episode                      = new \Podlove\Model\Episode();
		$episode->summary             = '<p>  </p>';
		\Podlove\Model\Episode::$mock = $episode;

		$podlove = ( new \Activitypub\Integration\Podlove_Podcast_Publisher( $post ) )->to_object();
		$default = ( new \Activitypub\Transformer\Post( $post ) )->to_object();

		$this->assertSame( $default->get_summary(), $podlove->get_summary() );

		\wp_delete_post( $post->ID, true );
	}
}
