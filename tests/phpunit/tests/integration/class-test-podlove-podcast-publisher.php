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
	 * Test that the transformer returns Note type.
	 *
	 * @covers ::get_type
	 */
	public function test_get_type_returns_note() {
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

		$this->assertEquals( 'Note', $transformer->get_type() );

		// Clean up.
		\wp_delete_post( $post->ID, true );
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
}
