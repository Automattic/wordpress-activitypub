<?php
/**
 * Test file for Classic_Editor integration.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Integration;

use Activitypub\Integration\Classic_Editor;

/**
 * Test class for Classic_Editor integration.
 *
 * @coversDefaultClass \Activitypub\Integration\Classic_Editor
 */
class Test_Classic_Editor extends \WP_UnitTestCase {

	/**
	 * Test post ID.
	 *
	 * @var int
	 */
	protected static $post_id;

	/**
	 * Test user ID.
	 *
	 * @var int
	 */
	protected static $user_id;

	/**
	 * Set up before class.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		self::$user_id = self::factory()->user->create(
			array(
				'role' => 'editor',
			)
		);

		self::$post_id = self::factory()->post->create(
			array(
				'post_author'  => self::$user_id,
				'post_status'  => 'publish',
				'post_content' => 'Test content',
			)
		);

		\add_post_type_support( 'post', 'activitypub' );
	}

	/**
	 * Test filter_attachments_media_markup with empty attachments.
	 *
	 * @covers ::filter_attachments_media_markup
	 */
	public function test_filter_attachments_media_markup_empty() {
		$result = Classic_Editor::filter_attachments_media_markup( '', array() );
		$this->assertSame( '', $result );
	}

	/**
	 * Test filter_attachments_media_markup with single video.
	 *
	 * @covers ::filter_attachments_media_markup
	 */
	public function test_filter_attachments_media_markup_single_video() {
		$video_id = self::factory()->attachment->create_upload_object(
			AP_TESTS_DIR . '/data/assets/sample-video.mp4',
			self::$post_id
		);

		$result = Classic_Editor::filter_attachments_media_markup( '', array( $video_id ) );

		$this->assertStringContainsString( '[video src=', $result );
		$this->assertStringContainsString( wp_get_attachment_url( $video_id ), $result );

		\wp_delete_attachment( $video_id, true );
	}

	/**
	 * Test filter_attachments_media_markup with single audio.
	 *
	 * @covers ::filter_attachments_media_markup
	 */
	public function test_filter_attachments_media_markup_single_audio() {
		$audio_id = self::factory()->attachment->create_upload_object(
			AP_TESTS_DIR . '/data/assets/sample-audio.mp3',
			self::$post_id
		);

		$result = Classic_Editor::filter_attachments_media_markup( '', array( $audio_id ) );

		$this->assertStringContainsString( '[audio src=', $result );
		$this->assertStringContainsString( wp_get_attachment_url( $audio_id ), $result );

		\wp_delete_attachment( $audio_id, true );
	}

	/**
	 * Test filter_attachments_media_markup with multiple images.
	 *
	 * @covers ::filter_attachments_media_markup
	 */
	public function test_filter_attachments_media_markup_multiple_images() {
		$image_id_1 = self::factory()->attachment->create_upload_object(
			AP_TESTS_DIR . '/data/assets/sample-image.jpg',
			self::$post_id
		);
		$image_id_2 = self::factory()->attachment->create_upload_object(
			AP_TESTS_DIR . '/data/assets/sample-image.jpg',
			self::$post_id
		);

		$result = Classic_Editor::filter_attachments_media_markup( '', array( $image_id_1, $image_id_2 ) );

		$this->assertStringContainsString( '[gallery ids="', $result );
		$this->assertStringContainsString( (string) $image_id_1, $result );
		$this->assertStringContainsString( (string) $image_id_2, $result );
		$this->assertStringContainsString( 'link="none"', $result );

		\wp_delete_attachment( $image_id_1, true );
		\wp_delete_attachment( $image_id_2, true );
	}

	/**
	 * Test filter_attachments_media_markup with single image.
	 *
	 * @covers ::filter_attachments_media_markup
	 */
	public function test_filter_attachments_media_markup_single_image() {
		$image_id = self::factory()->attachment->create_upload_object(
			AP_TESTS_DIR . '/data/assets/sample-image.jpg',
			self::$post_id
		);

		$result = Classic_Editor::filter_attachments_media_markup( '', array( $image_id ) );

		// Single image should use gallery shortcode.
		$this->assertStringContainsString( '[gallery ids="', $result );
		$this->assertStringContainsString( (string) $image_id, $result );

		\wp_delete_attachment( $image_id, true );
	}

	/**
	 * Test filter_attached_media_ids returns empty array when no attachments.
	 *
	 * @covers ::filter_attached_media_ids
	 */
	public function test_filter_attached_images_no_attachments() {
		$post   = \get_post( self::$post_id );
		$result = Classic_Editor::filter_attached_media_ids( array(), $post );

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * Test filter_attached_media_ids with attachments.
	 *
	 * @covers ::filter_attached_media_ids
	 */
	public function test_filter_attached_images_with_attachments() {
		// Create image attachments.
		$image_id_1 = self::factory()->attachment->create_upload_object(
			AP_TESTS_DIR . '/data/assets/sample-image.jpg',
			self::$post_id
		);
		$image_id_2 = self::factory()->attachment->create_upload_object(
			AP_TESTS_DIR . '/data/assets/sample-image.jpg',
			self::$post_id
		);

		\set_post_thumbnail( self::$post_id, $image_id_1 );

		$post   = \get_post( self::$post_id );
		$result = Classic_Editor::filter_attached_media_ids( array(), $post );

		$this->assertIsArray( $result );
		$this->assertNotEmpty( $result );
		$this->assertLessThanOrEqual( ACTIVITYPUB_MAX_IMAGE_ATTACHMENTS, count( $result ) );

		// Clean up.
		\delete_post_thumbnail( self::$post_id );
		\wp_delete_attachment( $image_id_1, true );
		\wp_delete_attachment( $image_id_2, true );
	}

	/**
	 * Test filter_attached_media_ids respects max_media limit.
	 *
	 * @covers ::filter_attached_media_ids
	 */
	public function test_filter_attached_images_respects_max_limit() {
		// Create multiple image attachments.
		$image_ids = array();
		for ( $i = 0; $i < 5; $i++ ) {
			$image_ids[] = self::factory()->attachment->create_upload_object(
				AP_TESTS_DIR . '/data/assets/sample-image.jpg',
				self::$post_id
			);
		}

		\update_option( 'activitypub_max_image_attachments', 3 );

		$post   = \get_post( self::$post_id );
		$result = Classic_Editor::filter_attached_media_ids( array(), $post );

		$this->assertIsArray( $result );
		$this->assertLessThanOrEqual( 3, count( $result ) );

		// Clean up.
		\delete_option( 'activitypub_max_image_attachments' );
		foreach ( $image_ids as $image_id ) {
			\wp_delete_attachment( $image_id, true );
		}
	}

	/**
	 * Test filter_attached_media_ids with existing attachments doesn't exceed limit.
	 *
	 * @covers ::filter_attached_media_ids
	 */
	public function test_filter_attached_images_with_existing_attachments() {
		// Create image attachments.
		$image_ids = array();
		for ( $i = 0; $i < 3; $i++ ) {
			$image_ids[] = self::factory()->attachment->create_upload_object(
				AP_TESTS_DIR . '/data/assets/sample-image.jpg',
				self::$post_id
			);
		}

		\update_option( 'activitypub_max_image_attachments', 2 );

		$post = \get_post( self::$post_id );
		// Pass 1 existing attachment.
		$existing = array( 'https://example.com/image.jpg' );
		$result   = Classic_Editor::filter_attached_media_ids( $existing, $post );

		// Should merge and slice to max of 2.
		$this->assertLessThanOrEqual( 2, count( $result ) );

		// Clean up.
		\delete_option( 'activitypub_max_image_attachments' );
		foreach ( $image_ids as $image_id ) {
			\wp_delete_attachment( $image_id, true );
		}
	}

	/**
	 * Test filter_attached_media_ids when already at max doesn't fetch more.
	 *
	 * @covers ::filter_attached_media_ids
	 */
	public function test_filter_attached_images_already_at_max() {
		// Create image attachments.
		$image_ids = array();
		for ( $i = 0; $i < 3; $i++ ) {
			$image_ids[] = self::factory()->attachment->create_upload_object(
				AP_TESTS_DIR . '/data/assets/sample-image.jpg',
				self::$post_id
			);
		}

		\update_option( 'activitypub_max_image_attachments', 2 );

		$post = \get_post( self::$post_id );
		// Pass 2 existing attachments (already at max).
		$existing = array( 'https://example.com/image1.jpg', 'https://example.com/image2.jpg' );
		$result   = Classic_Editor::filter_attached_media_ids( $existing, $post );

		// Should return existing attachments unchanged when at max.
		$this->assertCount( 2, $result );

		// Clean up.
		\delete_option( 'activitypub_max_image_attachments' );
		foreach ( $image_ids as $image_id ) {
			\wp_delete_attachment( $image_id, true );
		}
	}

	/**
	 * Test save_meta_data saves content warning.
	 *
	 * @covers ::save_meta_data
	 */
	public function test_save_meta_data_content_warning() {
		\wp_set_current_user( self::$user_id );

		$_POST['activitypub_meta_box_nonce']  = \wp_create_nonce( 'activitypub_meta_box' );
		$_POST['activitypub_content_warning'] = 'Test warning';

		Classic_Editor::save_meta_data( self::$post_id );

		$this->assertEquals( 'Test warning', \get_post_meta( self::$post_id, 'activitypub_content_warning', true ) );

		// Clean up.
		\delete_post_meta( self::$post_id, 'activitypub_content_warning' );
		unset( $_POST['activitypub_meta_box_nonce'], $_POST['activitypub_content_warning'] );
	}

	/**
	 * Test save_meta_data deletes empty content warning.
	 *
	 * @covers ::save_meta_data
	 */
	public function test_save_meta_data_empty_content_warning() {
		\wp_set_current_user( self::$user_id );

		// Set initial value.
		\update_post_meta( self::$post_id, 'activitypub_content_warning', 'Test' );

		$_POST['activitypub_meta_box_nonce']  = \wp_create_nonce( 'activitypub_meta_box' );
		$_POST['activitypub_content_warning'] = '';

		Classic_Editor::save_meta_data( self::$post_id );

		$this->assertEmpty( \get_post_meta( self::$post_id, 'activitypub_content_warning', true ) );

		// Clean up.
		unset( $_POST['activitypub_meta_box_nonce'], $_POST['activitypub_content_warning'] );
	}

	/**
	 * Test save_meta_data saves max image attachments.
	 *
	 * @covers ::save_meta_data
	 */
	public function test_save_meta_data_max_image_attachments() {
		\wp_set_current_user( self::$user_id );

		$_POST['activitypub_meta_box_nonce']        = \wp_create_nonce( 'activitypub_meta_box' );
		$_POST['activitypub_max_image_attachments'] = '5';

		Classic_Editor::save_meta_data( self::$post_id );

		$this->assertEquals( 5, \get_post_meta( self::$post_id, 'activitypub_max_image_attachments', true ) );

		// Clean up.
		\delete_post_meta( self::$post_id, 'activitypub_max_image_attachments' );
		unset( $_POST['activitypub_meta_box_nonce'], $_POST['activitypub_max_image_attachments'] );
	}

	/**
	 * Test save_meta_data saves content visibility.
	 *
	 * @covers ::save_meta_data
	 */
	public function test_save_meta_data_content_visibility() {
		\wp_set_current_user( self::$user_id );

		$_POST['activitypub_meta_box_nonce']     = \wp_create_nonce( 'activitypub_meta_box' );
		$_POST['activitypub_content_visibility'] = 'local';

		Classic_Editor::save_meta_data( self::$post_id );

		$this->assertEquals( 'local', \get_post_meta( self::$post_id, 'activitypub_content_visibility', true ) );

		// Clean up.
		\delete_post_meta( self::$post_id, 'activitypub_content_visibility' );
		unset( $_POST['activitypub_meta_box_nonce'], $_POST['activitypub_content_visibility'] );
	}

	/**
	 * Test save_meta_data saves quote interaction policy.
	 *
	 * @covers ::save_meta_data
	 */
	public function test_save_meta_data_quote_interaction_policy() {
		\wp_set_current_user( self::$user_id );

		$_POST['activitypub_meta_box_nonce']           = \wp_create_nonce( 'activitypub_meta_box' );
		$_POST['activitypub_interaction_policy_quote'] = 'followers';

		Classic_Editor::save_meta_data( self::$post_id );

		$this->assertEquals( 'followers', \get_post_meta( self::$post_id, 'activitypub_interaction_policy_quote', true ) );

		// Clean up.
		\delete_post_meta( self::$post_id, 'activitypub_interaction_policy_quote' );
		unset( $_POST['activitypub_meta_box_nonce'], $_POST['activitypub_interaction_policy_quote'] );
	}

	/**
	 * Test save_meta_data doesn't save without nonce.
	 *
	 * @covers ::save_meta_data
	 */
	public function test_save_meta_data_without_nonce() {
		\wp_set_current_user( self::$user_id );

		$_POST['activitypub_content_warning'] = 'Should not save';

		Classic_Editor::save_meta_data( self::$post_id );

		$this->assertEmpty( \get_post_meta( self::$post_id, 'activitypub_content_warning', true ) );

		// Clean up.
		unset( $_POST['activitypub_content_warning'] );
	}

	/**
	 * Test save_meta_data doesn't save with invalid nonce.
	 *
	 * @covers ::save_meta_data
	 */
	public function test_save_meta_data_with_invalid_nonce() {
		\wp_set_current_user( self::$user_id );

		$_POST['activitypub_meta_box_nonce']  = 'invalid_nonce';
		$_POST['activitypub_content_warning'] = 'Should not save';

		Classic_Editor::save_meta_data( self::$post_id );

		$this->assertEmpty( \get_post_meta( self::$post_id, 'activitypub_content_warning', true ) );

		// Clean up.
		unset( $_POST['activitypub_meta_box_nonce'], $_POST['activitypub_content_warning'] );
	}

	/**
	 * Test save_meta_data doesn't save without proper permissions.
	 *
	 * @covers ::save_meta_data
	 */
	public function test_save_meta_data_without_permissions() {
		$subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		\wp_set_current_user( $subscriber_id );

		$_POST['activitypub_meta_box_nonce']  = \wp_create_nonce( 'activitypub_meta_box' );
		$_POST['activitypub_content_warning'] = 'Should not save';

		Classic_Editor::save_meta_data( self::$post_id );

		$this->assertEmpty( \get_post_meta( self::$post_id, 'activitypub_content_warning', true ) );

		// Clean up.
		\wp_delete_user( $subscriber_id );
		unset( $_POST['activitypub_meta_box_nonce'], $_POST['activitypub_content_warning'] );
	}

	/**
	 * Test add_meta_box only adds for supported post types.
	 *
	 * @covers ::add_meta_box
	 */
	public function test_add_meta_box_only_for_supported_types() {
		global $wp_meta_boxes;

		// Clear existing meta boxes.
		$wp_meta_boxes = array();

		// Test with supported post type.
		Classic_Editor::add_meta_box( 'post' );
		$this->assertArrayHasKey( 'activitypub-settings', $wp_meta_boxes['post']['side']['default'] );

		// Clear and test with unsupported post type.
		$wp_meta_boxes = array();
		\remove_post_type_support( 'page', 'activitypub' );
		Classic_Editor::add_meta_box( 'page' );
		$this->assertArrayNotHasKey( 'page', $wp_meta_boxes );
	}
}
