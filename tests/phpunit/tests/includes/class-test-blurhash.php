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
	 * Temp fixture files created during a test, deleted in tear_down.
	 *
	 * @var array<int, string>
	 */
	private $fixture_files = array();

	/**
	 * `get_attached_file` callbacks registered during a test, removed in
	 * tear_down so they don't leak across tests (post IDs can repeat once
	 * the per-test transaction rolls back, and a stale closure matching a
	 * reused attachment ID would return the wrong path).
	 *
	 * @var array<int, callable>
	 */
	private $attached_file_filters = array();

	/**
	 * Clean up scheduled cron events, fixture filters, and temp fixtures
	 * after each test.
	 */
	public function tear_down() {
		\wp_unschedule_hook( Blurhash::CRON_HOOK );
		foreach ( $this->attached_file_filters as $callback ) {
			\remove_filter( 'get_attached_file', $callback, 10 );
		}
		$this->attached_file_filters = array();
		foreach ( $this->fixture_files as $file ) {
			if ( \file_exists( $file ) ) {
				\wp_delete_file( $file );
			}
		}
		$this->fixture_files = array();
		parent::tear_down();
	}

	/**
	 * Create a bare image attachment whose file resolves to $path via the
	 * `get_attached_file` filter. Avoids the media pipeline so the encoder
	 * reads exactly the crafted fixture (no thumbnail intermediate).
	 *
	 * @param string $path Absolute path to the fixture file.
	 * @param string $mime Attachment mime type.
	 * @return int Attachment post ID.
	 */
	private function create_image_attachment_at( $path, $mime = 'image/png' ) {
		$attachment_id = self::factory()->post->create(
			array(
				'post_type'      => 'attachment',
				'post_mime_type' => $mime,
			)
		);

		$callback = function ( $file, $id ) use ( $attachment_id, $path ) {
			return ( (int) $id === $attachment_id ) ? $path : $file;
		};
		\add_filter( 'get_attached_file', $callback, 10, 2 );
		$this->attached_file_filters[] = $callback;

		return $attachment_id;
	}

	/**
	 * Write a tiny but well-formed PNG header stream whose IHDR declares
	 * the given dimensions. Only a few dozen bytes (passes the byte cap)
	 * with no pixel data — a stand-in for a decompression bomb.
	 *
	 * @param int $width  Declared width.
	 * @param int $height Declared height.
	 * @return string Absolute file path.
	 */
	private function generate_png_with_declared_dimensions( $width, $height ) {
		$signature = "\x89PNG\r\n\x1a\n";

		// IHDR: width, height, bit depth 8, color type 2 (truecolor),
		// compression 0, filter 0, interlace 0.
		$ihdr_data = \pack( 'NNCCCCC', $width, $height, 8, 2, 0, 0, 0 );
		$ihdr      = \pack( 'N', \strlen( $ihdr_data ) )
			. 'IHDR' . $ihdr_data
			. \pack( 'N', \crc32( 'IHDR' . $ihdr_data ) );

		// Empty IEND chunk so the stream is well-formed enough for
		// header parsers.
		$iend = \pack( 'N', 0 ) . 'IEND' . \pack( 'N', \crc32( 'IEND' ) );

		// Use the tempnam() path as-is. The encoder reads bytes
		// (getimagesizefromstring/imagecreatefromstring ignore the
		// extension) and the mime is set on the attachment, so a `.png`
		// suffix would only orphan the un-suffixed temp file tempnam()
		// already created.
		$path                  = \tempnam( \sys_get_temp_dir(), 'ap-blurhash-dim-' );
		$this->fixture_files[] = $path;

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- test fixture write to tempdir.
		\file_put_contents( $path, $signature . $ihdr . $iend );
		return $path;
	}

	/**
	 * Write a 16×16 PNG that is either fully transparent (alpha 127 over
	 * black RGB — the worst case for an alpha-unaware sampler) or solid
	 * opaque white. The pair drives the transparency flattening test.
	 *
	 * @param bool $transparent True for fully transparent, false for solid white.
	 * @return string Absolute file path.
	 */
	private function generate_alpha_fixture_png( $transparent ) {
		// tempnam() path used directly — imagepng() writes a PNG stream
		// regardless of extension, so appending `.png` would only leave
		// the original temp file orphaned.
		$path                  = \tempnam( \sys_get_temp_dir(), 'ap-blurhash-alpha-' );
		$this->fixture_files[] = $path;

		$image = \imagecreatetruecolor( 16, 16 );
		if ( $transparent ) {
			\imagealphablending( $image, false );
			$color = \imagecolorallocatealpha( $image, 0, 0, 0, 127 );
			\imagefilledrectangle( $image, 0, 0, 15, 15, $color );
			\imagesavealpha( $image, true );
		} else {
			$color = \imagecolorallocate( $image, 255, 255, 255 );
			\imagefilledrectangle( $image, 0, 0, 15, 15, $color );
		}
		\imagepng( $image, $path );
		\imagedestroy( $image );
		return $path;
	}

	/**
	 * Write a 16×16 solid opaque PNG of the given color. Used as a hash
	 * reference (e.g. all-black) the transparency test asserts against.
	 *
	 * @param int $red   Red channel (0-255).
	 * @param int $green Green channel (0-255).
	 * @param int $blue  Blue channel (0-255).
	 * @return string Absolute file path.
	 */
	private function generate_solid_color_png( $red, $green, $blue ) {
		$path                  = \tempnam( \sys_get_temp_dir(), 'ap-blurhash-solid-' );
		$this->fixture_files[] = $path;

		$image = \imagecreatetruecolor( 16, 16 );
		$color = \imagecolorallocate( $image, $red, $green, $blue );
		\imagefilledrectangle( $image, 0, 0, 15, 15, $color );
		\imagepng( $image, $path );
		\imagedestroy( $image );
		return $path;
	}

	/**
	 * Write a 16×16 PNG with an opaque saturated-red square on a fully
	 * transparent background. After the encoder flattens transparency to
	 * white, both the preserved red and the white background show up in the
	 * hash — so it matches neither an all-white nor an all-black reference.
	 *
	 * @return string Absolute file path.
	 */
	private function generate_colored_shape_on_transparent_png() {
		$path                  = \tempnam( \sys_get_temp_dir(), 'ap-blurhash-shape-' );
		$this->fixture_files[] = $path;

		$image = \imagecreatetruecolor( 16, 16 );
		\imagealphablending( $image, false );
		$transparent = \imagecolorallocatealpha( $image, 0, 0, 0, 127 );
		\imagefilledrectangle( $image, 0, 0, 15, 15, $transparent );

		\imagealphablending( $image, true );
		$red = \imagecolorallocate( $image, 255, 0, 0 );
		\imagefilledrectangle( $image, 4, 4, 11, 11, $red );

		\imagesavealpha( $image, true );
		\imagepng( $image, $path );
		\imagedestroy( $image );
		return $path;
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

	/**
	 * Test that a file declaring dimensions past MAX_ENCODE_PIXELS is rejected
	 * before `imagecreatefromstring()` runs, with the `false` policy-skip
	 * sentinel. The fixture has no pixel data, so if the gate didn't fire
	 * first, decode would fail and produce `null` (failure bucket) instead.
	 *
	 * @covers ::encode_from_attachment
	 */
	public function test_encode_skips_decode_bomb_dimensions_before_decode() {
		if ( ! Blurhash::is_encoder_runnable() ) {
			$this->markTestSkipped( 'GD is not available.' );
		}

		$path          = $this->generate_png_with_declared_dimensions( 30000, 30000 );
		$attachment_id = $this->create_image_attachment_at( $path );

		$this->assertFalse( Blurhash::encode_from_attachment( $attachment_id ) );
	}

	/**
	 * Test that a non-encodable attachment (here a PDF) returns the `false`
	 * policy-skip sentinel rather than the `null` failure sentinel, so direct
	 * callers can tell a deliberate skip from an unexpected error.
	 *
	 * @covers ::encode_from_attachment
	 */
	public function test_encode_from_attachment_returns_false_for_non_encodable() {
		$attachment_id = self::factory()->post->create(
			array(
				'post_type'      => 'attachment',
				'post_mime_type' => 'application/pdf',
			)
		);

		$this->assertFalse( Blurhash::encode_from_attachment( $attachment_id ) );
	}

	/**
	 * Test that the cron callback routes the decode-bomb policy skip to the
	 * silent path: no `activitypub_blurhash_encode_failed` action, no meta.
	 *
	 * @covers ::run_encode
	 */
	public function test_run_encode_treats_decode_bomb_as_silent_skip() {
		if ( ! Blurhash::is_encoder_runnable() ) {
			$this->markTestSkipped( 'GD is not available.' );
		}

		$path          = $this->generate_png_with_declared_dimensions( 30000, 30000 );
		$attachment_id = $this->create_image_attachment_at( $path );

		$captured = array();
		$callback = static function ( $failed_id ) use ( &$captured ) {
			$captured[] = (int) $failed_id;
		};
		\add_action( 'activitypub_blurhash_encode_failed', $callback );

		try {
			Blurhash::run_encode( $attachment_id );
		} finally {
			\remove_action( 'activitypub_blurhash_encode_failed', $callback );
		}

		$this->assertNull( Blurhash::get( $attachment_id ) );
		$this->assertSame( array(), $captured, 'Policy skip must not fire the encode-failed action.' );
	}

	/**
	 * Test that a fully transparent PNG encodes to the same hash as a solid
	 * white image of identical dimensions — the encoder composites onto an
	 * opaque white canvas before sampling. Without the composite,
	 * `imagecolorsforindex()` reports the raw black RGB of the transparent
	 * pixels and the hash comes out near-black.
	 *
	 * @covers ::encode_from_attachment
	 */
	public function test_encode_flattens_transparency_to_white() {
		if ( ! Blurhash::is_encoder_runnable() || ! \function_exists( 'imagepng' ) || ! \function_exists( 'imagecolorallocatealpha' ) ) {
			$this->markTestSkipped( 'GD (with PNG write support) is not available.' );
		}

		$transparent_id = $this->create_image_attachment_at( $this->generate_alpha_fixture_png( true ) );
		$white_id       = $this->create_image_attachment_at( $this->generate_alpha_fixture_png( false ) );

		$transparent_hash = Blurhash::encode_from_attachment( $transparent_id );
		$white_hash       = Blurhash::encode_from_attachment( $white_id );

		$this->assertIsString( $transparent_hash );
		$this->assertSame( $white_hash, $transparent_hash );
	}

	/**
	 * Test that visible color survives the transparency composite. A
	 * `fully-transparent === solid-white` check alone would also pass an
	 * implementation that flattened *every* image to white (or dropped
	 * color); an opaque colored shape on a transparent background pins down
	 * both halves: transparent regions flatten to white *and* real color is
	 * preserved, so the hash matches neither an all-white nor an all-black
	 * reference.
	 *
	 * @covers ::encode_from_attachment
	 */
	public function test_encode_preserves_color_over_transparency() {
		if ( ! Blurhash::is_encoder_runnable() || ! \function_exists( 'imagepng' ) || ! \function_exists( 'imagecolorallocatealpha' ) ) {
			$this->markTestSkipped( 'GD (with PNG write support) is not available.' );
		}

		$white_hash = Blurhash::encode_from_attachment(
			$this->create_image_attachment_at( $this->generate_alpha_fixture_png( false ) )
		);
		$black_hash = Blurhash::encode_from_attachment(
			$this->create_image_attachment_at( $this->generate_solid_color_png( 0, 0, 0 ) )
		);
		$shape_hash = Blurhash::encode_from_attachment(
			$this->create_image_attachment_at( $this->generate_colored_shape_on_transparent_png() )
		);

		$this->assertIsString( $shape_hash );
		$this->assertNotSame( $white_hash, $shape_hash, 'Visible color must survive the composite, not flatten to white.' );
		$this->assertNotSame( $black_hash, $shape_hash, 'Transparent regions must flatten to white, not black.' );
	}

	/**
	 * Test that schedule_encode defers the cron event instead of firing at
	 * `time()`. The filter runs before the caller persists the metadata, so
	 * an immediate cron spawn could race the metadata write and encode the
	 * full-size original instead of the thumbnail.
	 *
	 * @covers ::schedule_encode
	 */
	public function test_schedule_encode_defers_cron_event() {
		if ( ! Blurhash::is_encoder_runnable() ) {
			$this->markTestSkipped( 'GD is not available.' );
		}

		$path          = $this->generate_alpha_fixture_png( false );
		$attachment_id = $this->create_image_attachment_at( $path );

		$before = \time();
		Blurhash::schedule_encode( array(), $attachment_id );

		$timestamp = \wp_next_scheduled( Blurhash::CRON_HOOK, array( $attachment_id ) );
		$this->assertNotFalse( $timestamp );
		$this->assertGreaterThanOrEqual( $before + MINUTE_IN_SECONDS, $timestamp );
	}
}
