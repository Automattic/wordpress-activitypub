<?php
/**
 * Test file for Activitypub integrations.
 *
 * @package Activitypub
 */

/**
 * Test class for Activitypub integrations.
 *
 * @coversDefaultClass \Activitypub\Integrations
 */
class Test_Activitypub_Integrations extends WP_UnitTestCase {

	/**
	 * Set up the test.
	 */
	public function set_up() {
		parent::set_up();

		\define( 'SSP_VERSION', '1.0.0' );

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
