<?php
/**
 * Test the Blurhash orchestration class.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

use Activitypub\Blurhash;

/**
 * Tests for the Blurhash orchestration class.
 *
 * @coversDefaultClass \Activitypub\Blurhash
 */
class Test_Blurhash extends \WP_UnitTestCase {

	/**
	 * Clean up any scheduled cron events after each test.
	 */
	public function tear_down() {
		\wp_unschedule_hook( Blurhash::CRON_HOOK );
		parent::tear_down();
	}

	/**
	 * Test that get/set/delete round-trip correctly.
	 *
	 * @covers ::get
	 * @covers ::set
	 * @covers ::delete
	 */
	public function test_get_set_delete_roundtrip() {
		$id = self::factory()->post->create( array( 'post_type' => 'attachment' ) );

		$this->assertNull( Blurhash::get( $id ) );

		Blurhash::set( $id, 'LEHV6nWB2yk8pyo0adR*.7kCMdnj' );
		$this->assertSame( 'LEHV6nWB2yk8pyo0adR*.7kCMdnj', Blurhash::get( $id ) );

		Blurhash::delete( $id );
		$this->assertNull( Blurhash::get( $id ) );
	}

	/**
	 * Test that get returns null for a malformed hash stored in postmeta.
	 *
	 * @covers ::get
	 */
	public function test_get_rejects_malformed_hash() {
		$id = self::factory()->post->create( array( 'post_type' => 'attachment' ) );
		\update_post_meta( $id, Blurhash::META_KEY, 'not a valid hash!!' );

		$this->assertNull( Blurhash::get( $id ) );
	}

	/**
	 * Test that inject_blurhash adds the hash to Image-type attachments.
	 *
	 * @covers ::inject_blurhash
	 */
	public function test_inject_adds_blurhash_to_image_attachment() {
		$id = self::factory()->post->create( array( 'post_type' => 'attachment' ) );
		Blurhash::set( $id, 'LEHV6nWB2yk8pyo0adR*.7kCMdnj' );

		$attachment = Blurhash::inject_blurhash( array( 'type' => 'Image' ), $id );

		$this->assertSame( 'LEHV6nWB2yk8pyo0adR*.7kCMdnj', $attachment['blurhash'] );
	}

	/**
	 * Test that inject_blurhash skips non-Image attachment types.
	 *
	 * @covers ::inject_blurhash
	 */
	public function test_inject_skips_non_image_attachment() {
		$id = self::factory()->post->create( array( 'post_type' => 'attachment' ) );
		Blurhash::set( $id, 'LEHV6nWB2yk8pyo0adR*.7kCMdnj' );

		$attachment = Blurhash::inject_blurhash( array( 'type' => 'Document' ), $id );

		$this->assertArrayNotHasKey( 'blurhash', $attachment );
	}

	/**
	 * Test that inject_blurhash skips injection when no hash is stored.
	 *
	 * @covers ::inject_blurhash
	 */
	public function test_inject_skips_when_no_hash_stored() {
		$id         = self::factory()->post->create( array( 'post_type' => 'attachment' ) );
		$attachment = Blurhash::inject_blurhash( array( 'type' => 'Image' ), $id );

		$this->assertArrayNotHasKey( 'blurhash', $attachment );
	}

	/**
	 * Test that init registers all expected WordPress hooks.
	 *
	 * @covers ::init
	 */
	public function test_init_registers_hooks() {
		Blurhash::init();

		$this->assertNotFalse( \has_filter( 'wp_generate_attachment_metadata', array( Blurhash::class, 'schedule_encode' ) ) );
		$this->assertNotFalse( \has_action( Blurhash::CRON_HOOK, array( Blurhash::class, 'run_encode' ) ) );
		$this->assertNotFalse( \has_filter( 'activitypub_attachment', array( Blurhash::class, 'inject_blurhash' ) ) );

		\remove_filter( 'wp_generate_attachment_metadata', array( Blurhash::class, 'schedule_encode' ), 10 );
		\remove_action( Blurhash::CRON_HOOK, array( Blurhash::class, 'run_encode' ), 10 );
		\remove_filter( 'activitypub_attachment', array( Blurhash::class, 'inject_blurhash' ), 10 );
	}

	/**
	 * Test that a real image attachment encodes to a well-formed blurhash.
	 *
	 * @covers ::encode_from_attachment
	 */
	public function test_encode_from_attachment_produces_hash() {
		if ( ! Blurhash::is_encoder_runnable() ) {
			$this->markTestSkipped( 'GD is not available.' );
		}

		// Use a committed fixture so the test only depends on GD decode support
		// (what the encoder needs), not on JPEG write support via imagejpeg().
		$attachment_id = self::factory()->attachment->create_upload_object( AP_TESTS_DIR . '/data/assets/sample-image.jpg' );

		$hash = Blurhash::encode_from_attachment( $attachment_id );

		$this->assertIsString( $hash );
		$this->assertNotSame( '', $hash );
		$this->assertSame( 1, \preg_match( '/\A[0-9A-Za-z#$%*+,\-.:;=?@\[\]\^_{|}~]+\z/', $hash ) );
	}
}
