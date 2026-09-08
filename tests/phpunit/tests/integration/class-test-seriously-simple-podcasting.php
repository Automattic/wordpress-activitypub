<?php
/**
 * Test file for Seriously Simple Podcasting integration.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Integration;

/**
 * Test class for Seriously Simple Podcasting integration.
 *
 * @group integration
 * @coversDefaultClass \Activitypub\Integration\Seriously_Simple_Podcasting
 */
class Test_Seriously_Simple_Podcasting extends \WP_UnitTestCase {

	/**
	 * Set up the test.
	 */
	public function set_up() {
		parent::set_up();

		if ( ! \defined( 'SSP_VERSION' ) ) {
			\define( 'SSP_VERSION', '1.0.0' );
		}

		\do_action( 'plugins_loaded' );
		\add_filter( 'activitypub_attachments', array( get_called_class(), 'mock_attachments' ) );
	}

	/**
	 * Test ssp.
	 */
	public function test_ssp() {
		$post = \wp_insert_post(
			array(
				'post_author'  => 1,
				'post_content' => 'content',
				'post_title'   => 'title',
				'post_status'  => 'publish',
			)
		);
		$post = \get_post( $post );

		$transformer = \Activitypub\Transformer\Factory::get_transformer( $post );
		$object      = $transformer->to_object();

		$this->assertEquals( 2, count( $object->get_attachment() ) );

		foreach ( $object->get_attachment() as $attachment ) {
			$this->assertEquals( 'Image', $attachment['type'] );
		}

		\update_post_meta( $post->ID, 'episode_type', 'audio' );
		\update_post_meta( $post->ID, 'audio_file', 'https://example.com/audio.mp3' );
		\update_post_meta( $post->ID, 'cover_image', 'https://example.com/cover.jpg' );

		// Clear post cache.
		\clean_post_cache( $post->ID );

		$transformer = \Activitypub\Transformer\Factory::get_transformer( $post );
		$object      = $transformer->to_object();

		$this->assertEquals( 1, count( $object->get_attachment() ) );

		foreach ( $object->get_attachment() as $attachment ) {
			$this->assertEquals( 'Audio', $attachment['type'] );
		}
	}

	/**
	 * An episode without its own cover art falls back to the transformer's poster image.
	 *
	 * This is the branch {@see \Activitypub\Transformer\Post::get_media_icon()} exists for, and
	 * nothing covered it, so renaming that method broke this integration without a test noticing.
	 *
	 * @covers ::get_attachment
	 */
	public function test_episode_without_cover_image_falls_back_to_the_featured_image() {
		$post_id = self::factory()->post->create(
			array(
				'post_author'  => 1,
				'post_content' => 'content',
				'post_title'   => 'title',
				'post_status'  => 'publish',
			)
		);

		$attachment_id = self::factory()->attachment->create_upload_object( AP_TESTS_DIR . '/data/assets/test.jpg' );
		\set_post_thumbnail( $post_id, $attachment_id );

		\update_post_meta( $post_id, 'episode_type', 'audio' );
		\update_post_meta( $post_id, 'audio_file', 'https://example.com/audio.mp3' );
		// Deliberately no `cover_image`, so the fallback runs.

		\clean_post_cache( $post_id );

		$transformer = \Activitypub\Transformer\Factory::get_transformer( \get_post( $post_id ) );
		$attachments = $transformer->to_object()->get_attachment();

		$this->assertCount( 1, $attachments );
		$this->assertEquals( 'Audio', $attachments[0]['type'] );
		$this->assertNotEmpty( $attachments[0]['icon'], 'The featured image has to stand in as the cover art.' );
	}

	/**
	 * Mock attachments.
	 *
	 * @param array $attachments Attachments.
	 * @return array
	 */
	public static function mock_attachments( $attachments ) {
		$attachments[] = array(
			'type' => 'Image',
			'url'  => 'https://example.com/cover.jpg',
			'name' => 'Image 1',
		);

		$attachments[] = array(
			'type' => 'Image',
			'url'  => 'https://example.org/cover.jpg',
			'name' => 'Image 2',
		);

		return $attachments;
	}
}
