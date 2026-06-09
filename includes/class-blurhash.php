<?php
/**
 * Blurhash encoder + storage for image attachments.
 *
 * @package Activitypub
 */

namespace Activitypub;

/**
 * Compute, store, and inject Blurhash placeholder strings for image
 * attachments so federated ActivityPub posts emit
 * `attachment[].blurhash` — the colored-blur preview that Pixelfed,
 * Mastodon, and other fediverse clients paint while the full image
 * is still loading. Without it, federated WP photos sit on a grey
 * placeholder where native uploads paint instantly; with it, the
 * loading state matches native.
 *
 * Encoding runs at upload time via `wp_generate_attachment_metadata`,
 * deferred to cron so the upload UI returns immediately. The hash
 * is persisted as attachment postmeta ({@see self::META_KEY}) and
 * read back by the `activitypub_attachment` projector — zero
 * per-publish CPU cost, federation hot path stays cheap.
 *
 * Pure no-op when GD isn't available: the encoder needs an
 * `[r,g,b][][]` pixel array, and GD's `imagecreatefrom*` family is
 * the only host-portable way to build one. Sites running Imagick-only
 * just don't get blurhash placeholders — attachments still federate,
 * minus the field. Same posture for any other failure (unreadable
 * file, encoder exception, deleted attachment): never blocks the
 * upload, never blocks federation.
 *
 * Called from `activitypub.php` on `init`.
 * Adapted from Automattic/FOSSE (https://github.com/Automattic/fosse).
 *
 * @since unreleased
 */
class Blurhash {

	/**
	 * Postmeta key holding the encoded blurhash for an attachment.
	 *
	 * @var string
	 */
	public const META_KEY = '_activitypub_blurhash';

	/**
	 * Cron hook fired for each attachment that needs a hash computed.
	 *
	 * @var string
	 */
	public const CRON_HOOK = 'activitypub_blurhash_compute';

	/**
	 * DCT component count passed to the encoder as BOTH the X and
	 * Y dimensions — kept as a single number because the two
	 * dimensions are always equal in this encoder and splitting
	 * them implies a tuning surface that doesn't exist. Wolt's
	 * reference recommends 4–5 for landscape and lower for
	 * portrait; 4 is the middle ground Mastodon also defaults to.
	 * Hash length grows with the product, so 4×4 keeps the encoded
	 * string short.
	 *
	 * @var int
	 */
	private const COMPONENTS = 4;

	/**
	 * Upper bound on stored blurhash string length, in characters.
	 * A 4×4 component grid produces a 30-character hash; the
	 * theoretical max for the 9×9 component grid the spec supports
	 * is 99 characters. We cap a little above that as a defense
	 * against postmeta poisoning — anyone with `edit_post_meta` on
	 * an attachment could otherwise write arbitrary bytes that we
	 * would then federate straight into the AP envelope.
	 *
	 * @var int
	 */
	private const MAX_HASH_LENGTH = 128;

	/**
	 * Image size used as the encoder's source. Blurhash encoding is
	 * O(N) over pixel count and the output is a few low-frequency DCT
	 * coefficients — feeding it a 12-megapixel original would burn
	 * CPU producing a hash that's perceptually identical to the one
	 * computed off the ~150px thumbnail. WP's `thumbnail` size is the
	 * smallest variant guaranteed to exist for every uploaded image.
	 *
	 * @var string
	 */
	private const ENCODE_SIZE = 'thumbnail';

	/**
	 * Hard upper bound on the longest edge fed to the encoder. Even
	 * when {@see self::resolve_encode_path()} returns the original
	 * (no intermediate, fallback path, misconfigured thumbnail size),
	 * the GD image is downscaled to this max edge before the per-pixel
	 * array is built. Keeps the PHP array allocation bounded — without
	 * this cap, a 4000×3000 original would build a 12M-cell nested
	 * array (~960 MB) and OOM the cron worker.
	 *
	 * @var int
	 */
	private const MAX_ENCODE_EDGE = 64;

	/**
	 * Hard upper bound on the byte size of the source file fed into
	 * GD. Defends against pathological cases where {@see self::resolve_encode_path()}
	 * resolves to a huge original (or, via filterable
	 * `image_get_intermediate_size`, a path that's not actually an
	 * image at all). 8 MiB comfortably accommodates any realistic
	 * web-photo upload at full quality.
	 *
	 * @var int
	 */
	private const MAX_ENCODE_BYTES = 8388608;

	/**
	 * Raster MIME types GD can decode via `imagecreatefromstring`.
	 * Used as the early gate that splits "we don't encode this"
	 * (skip silently) from "encoder failed" (emit warning, count
	 * against exit status). SVG/XML, ICO, and other formats either
	 * GD can't read or aren't raster get filtered out before the
	 * encoder runs.
	 *
	 * @var array<int, string>
	 */
	private const ENCODABLE_MIME_TYPES = array(
		'image/jpeg',
		'image/png',
		'image/gif',
		'image/webp',
		'image/avif',
		'image/bmp',
	);

	/**
	 * Register all hooks. Called from `activitypub.php` on `init`.
	 */
	public static function init(): void {
		\add_filter( 'wp_generate_attachment_metadata', array( self::class, 'schedule_encode' ), 10, 2 );
		\add_action( self::CRON_HOOK, array( self::class, 'run_encode' ), 10, 1 );
		\add_filter( 'activitypub_attachment', array( self::class, 'inject_blurhash' ), 10, 2 );
	}

	/**
	 * Return the stored blurhash for an attachment, or null when no
	 * usable value is stored. Empty/whitespace/non-string values are
	 * treated as absent, AND values that fail
	 * {@see self::is_well_formed_hash()} are too — so postmeta
	 * poisoning (or an old encoder bug that wrote junk) doesn't
	 * leak into the federation envelope AND doesn't permanently
	 * stick the cron `run_encode` short-circuit (which keys off
	 * this returning non-null). Net effect: a malformed row
	 * self-heals on the next `wp_generate_attachment_metadata`
	 * cycle because `get()` reports absent, `run_encode` proceeds,
	 * and `set()` overwrites the malformed value.
	 *
	 * @param int $attachment_id Attachment post ID.
	 * @return string|null
	 */
	public static function get( int $attachment_id ): ?string {
		$value = \get_post_meta( $attachment_id, self::META_KEY, true );
		if ( ! \is_string( $value ) ) {
			return null;
		}
		$value = \trim( $value );
		if ( '' === $value ) {
			return null;
		}
		return self::is_well_formed_hash( $value ) ? $value : null;
	}

	/**
	 * Persist a computed blurhash on the attachment.
	 *
	 * @param int    $attachment_id Attachment post ID.
	 * @param string $hash          Encoded blurhash string.
	 */
	public static function set( int $attachment_id, string $hash ): void {
		\update_post_meta( $attachment_id, self::META_KEY, $hash );
	}

	/**
	 * Delete any stored blurhash for an attachment. Used by the
	 * upload/regen invalidation path ({@see self::schedule_encode()}) so a
	 * replaced image re-encodes against its latest bytes.
	 *
	 * @param int $attachment_id Attachment post ID.
	 */
	public static function delete( int $attachment_id ): void {
		\delete_post_meta( $attachment_id, self::META_KEY );
	}

	/**
	 * Compute (synchronously) the blurhash for an attachment by
	 * loading the configured size's file through GD and feeding the
	 * pixel array to the encoder. Returns null on any failure path
	 * — never throws, never warns. Used by both the cron handler
	 * and the WP-CLI backfill.
	 *
	 * @param int $attachment_id Attachment post ID.
	 * @return string|null
	 */
	public static function encode_from_attachment( int $attachment_id ): ?string {
		if ( ! self::is_encodable_attachment( $attachment_id ) ) {
			return null;
		}

		if ( ! self::is_encoder_runnable() ) {
			return null;
		}

		$path = self::resolve_encode_path( $attachment_id );
		if ( null === $path ) {
			return null;
		}

		// Fast-fail before reading bytes. A pathological source
		// (huge original, non-image file slipped through a filterable
		// metadata path) gets rejected without allocating PHP memory
		// for the read.
		$size = @\filesize( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- stat failure returns false and we handle.
		if ( false === $size || $size < 1 || $size > self::MAX_ENCODE_BYTES ) {
			return null;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents, WordPress.PHP.NoSilencedErrors.Discouraged -- local absolute path read; corrupt/missing file returns false and we handle.
		$bytes = @\file_get_contents( $path );
		if ( false === $bytes || '' === $bytes ) {
			return null;
		}

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- corrupt image returns false and we handle.
		$original = @\imagecreatefromstring( $bytes );
		if ( false === $original ) {
			return null;
		}

		// $scaled holds a second GD resource created by imagescale when
		// the image exceeds MAX_ENCODE_EDGE. It is kept separate from
		// $original so both can be destroyed in finally regardless of
		// which code path ran.
		$scaled = null;

		try {
			$width  = \imagesx( $original );
			$height = \imagesy( $original );
			if ( $width < 1 || $height < 1 ) {
				return null;
			}

			// The image we will actually read pixels from — either the
			// original or a downscaled copy.
			$src_image = $original;

			// Defensive downscale before the per-pixel loop. The
			// nested array grows quadratically with edge length, so
			// any source larger than MAX_ENCODE_EDGE gets sampled
			// down — perceptually identical output, bounded memory.
			//
			// Scale by the LONGER edge so portrait images
			// (`height > width`) don't get upscaled by a
			// fixed-width call to `imagescale`. Target dimensions
			// are computed explicitly so both edges land at or
			// below the cap. If `imagescale` fails we bail rather
			// than fall through to the per-pixel loop with the
			// original (oversized) GD image.
			if ( $width > self::MAX_ENCODE_EDGE || $height > self::MAX_ENCODE_EDGE ) {
				if ( $width >= $height ) {
					$target_width  = self::MAX_ENCODE_EDGE;
					$target_height = (int) \max( 1, \round( $height * ( self::MAX_ENCODE_EDGE / $width ) ) );
				} else {
					$target_height = self::MAX_ENCODE_EDGE;
					$target_width  = (int) \max( 1, \round( $width * ( self::MAX_ENCODE_EDGE / $height ) ) );
				}
				$scaled = \imagescale( $original, $target_width, $target_height );
				if ( false === $scaled ) {
					return null;
				}
				$src_image = $scaled;
				$width     = $target_width;
				$height    = $target_height;
			}

			$pixels = array();
			for ( $y = 0; $y < $height; $y++ ) {
				$row = array();
				for ( $x = 0; $x < $width; $x++ ) {
					$index  = \imagecolorat( $src_image, $x, $y );
					$colors = \imagecolorsforindex( $src_image, $index );
					$row[]  = array( $colors['red'], $colors['green'], $colors['blue'] );
				}
				$pixels[] = $row;
			}

			$hash = Blurhash_Encoder::encode( $pixels, self::COMPONENTS, self::COMPONENTS );
			return \is_string( $hash ) && '' !== $hash ? $hash : null;
		} catch ( \Throwable $e ) {
			return null;
		} finally {
			// Free GD resources. On PHP 7.4 GD images are plain
			// resources that persist until script end; the CLI
			// backfill processes many images in one process and
			// would leak all of them without explicit cleanup.
			\imagedestroy( $original );
			if ( null !== $scaled && false !== $scaled ) {
				\imagedestroy( $scaled );
			}
		}
	}

	/**
	 * Resolve the absolute filesystem path of the size we use as
	 * encoder input, falling back to the original when the
	 * intermediate doesn't exist (small uploads that core didn't
	 * generate downscales for).
	 *
	 * @param int $attachment_id Attachment post ID.
	 * @return string|null Absolute file path, or null when nothing readable resolved.
	 */
	private static function resolve_encode_path( int $attachment_id ): ?string {
		$upload       = \wp_upload_dir();
		$basedir_real = ( \is_array( $upload ) && ! empty( $upload['basedir'] ) )
			? \realpath( $upload['basedir'] )
			: false;

		$sized = \image_get_intermediate_size( $attachment_id, self::ENCODE_SIZE );
		if ( \is_array( $sized ) && ! empty( $sized['path'] ) && false !== $basedir_real ) {
			$candidate = \trailingslashit( $upload['basedir'] ) . $sized['path'];
			$resolved  = self::contain_under_basedir( $candidate, $basedir_real );
			if ( null !== $resolved ) {
				return $resolved;
			}
		}

		$attached = \get_attached_file( $attachment_id );
		if ( \is_string( $attached ) && '' !== $attached ) {
			// The fallback can return paths outside the uploads dir
			// (e.g. shared media), so containment is best-effort: we
			// accept readable real paths but skip the basedir prefix
			// check, while still rejecting unreadable / non-existent
			// targets.
			$resolved = \realpath( $attached );
			if ( false !== $resolved && \is_readable( $resolved ) ) {
				return $resolved;
			}
		}

		return null;
	}

	/**
	 * Realpath-resolve `$candidate` and verify it sits under
	 * `$basedir_real`. Returns the resolved path on success, null on
	 * any failure (unresolvable, traversal outside the basedir,
	 * unreadable). Defends the encoder against filterable
	 * `image_get_intermediate_size` returning a `path` that contains
	 * `..` segments or an absolute symlink target outside uploads.
	 *
	 * @param string $candidate    Path to resolve.
	 * @param string $basedir_real Already-resolved (realpath) basedir.
	 * @return string|null
	 */
	private static function contain_under_basedir( string $candidate, string $basedir_real ): ?string {
		$resolved = \realpath( $candidate );
		if ( false === $resolved ) {
			return null;
		}
		$prefix = \rtrim( $basedir_real, \DIRECTORY_SEPARATOR ) . \DIRECTORY_SEPARATOR;
		if ( 0 !== \strpos( $resolved, $prefix ) ) {
			return null;
		}
		return \is_readable( $resolved ) ? $resolved : null;
	}

	/**
	 * `wp_generate_attachment_metadata` filter callback. Schedules
	 * a single-event cron run to compute the blurhash for image
	 * attachments. The filter return value is the metadata unchanged
	 * — we use the hook purely as an "image is ready" notifier.
	 *
	 * @param array $metadata      Attachment metadata as built by WP.
	 * @param int   $attachment_id Attachment post ID.
	 * @return array
	 */
	public static function schedule_encode( $metadata, $attachment_id ) {
		$attachment_id = (int) $attachment_id;
		if ( $attachment_id < 1 || ! self::is_encodable_attachment( $attachment_id ) ) {
			return $metadata;
		}

		// Skip both the invalidation AND the cron enqueue when the
		// encoder can't actually run on this host. Without the gate,
		// a metadata regen on a GD-less site would wipe a previously
		// stored hash (computed on a different host, restored from
		// backup, etc.) and queue a cron event guaranteed to fail.
		if ( ! self::is_encoder_runnable() ) {
			return $metadata;
		}

		// Invalidate any prior hash so cron will re-encode against
		// the latest bytes. `wp_generate_attachment_metadata` fires
		// on initial upload AND on media-replace / crop / regen, so
		// without this delete a replaced image would keep federating
		// the placeholder for its prior bytes.
		self::delete( $attachment_id );

		self::schedule( $attachment_id );
		return $metadata;
	}

	/**
	 * Whether an attachment is something the encoder will attempt on this host.
	 * True for `wp_attachment_is_image` attachments whose mime is in
	 * {@see self::ENCODABLE_MIME_TYPES} AND that this GD build can actually
	 * decode; false for SVG, non-image media, deleted/nonexistent IDs, and
	 * formats this GD build lacks support for (e.g. WebP/AVIF/BMP on a
	 * stripped-down GD). Shared gate used by the upload-scheduling path, the
	 * CLI backfill (to count "we don't encode this" as a skip rather than a
	 * failure), and the encoder itself.
	 *
	 * The GD-capability check matters because `schedule_encode()` invalidates
	 * any prior hash before queueing the cron encode: without it, a metadata
	 * regen on a host that can't decode the format would wipe a previously
	 * good hash (e.g. migrated from a host with broader GD support) and queue
	 * a cron event guaranteed to fail.
	 *
	 * @param int $attachment_id Attachment post ID.
	 * @return bool
	 */
	public static function is_encodable_attachment( int $attachment_id ): bool {
		if ( $attachment_id < 1 || ! \wp_attachment_is_image( $attachment_id ) ) {
			return false;
		}
		$mime = \get_post_mime_type( $attachment_id );
		return \is_string( $mime )
			&& \in_array( $mime, self::ENCODABLE_MIME_TYPES, true )
			&& self::host_can_decode( $mime );
	}

	/**
	 * Whether this GD build can decode the given image mime type.
	 *
	 * GD support for WebP, AVIF, and BMP is build-dependent, so a mime being
	 * in {@see self::ENCODABLE_MIME_TYPES} is necessary but not sufficient.
	 * `imagetypes()` reports what the running GD can actually handle. The
	 * `IMG_*` flags for WebP/AVIF/BMP are not defined on every PHP version
	 * (`IMG_AVIF` is PHP 8.1+), so guard each with `defined()`.
	 *
	 * @param string $mime The attachment mime type.
	 * @return bool
	 */
	private static function host_can_decode( $mime ) {
		if ( ! \function_exists( 'imagetypes' ) ) {
			return false;
		}

		$supported = \imagetypes();

		switch ( $mime ) {
			case 'image/jpeg':
				return (bool) ( $supported & IMG_JPG );
			case 'image/png':
				return (bool) ( $supported & IMG_PNG );
			case 'image/gif':
				return (bool) ( $supported & IMG_GIF );
			case 'image/webp':
				return \defined( 'IMG_WEBP' ) && (bool) ( $supported & IMG_WEBP );
			case 'image/avif':
				return \defined( 'IMG_AVIF' ) && (bool) ( $supported & IMG_AVIF );
			case 'image/bmp':
				return \defined( 'IMG_BMP' ) && (bool) ( $supported & IMG_BMP );
			default:
				return false;
		}
	}

	/**
	 * Whether the host has the GD primitives the encoder needs to
	 * actually run. Public so the WP-CLI backfill can fail-fast with
	 * one clear error message rather than emit a warning per
	 * attachment, and so the upload/cron paths can short-circuit
	 * without leaving cron noise behind on a GD-less host.
	 *
	 * @return bool
	 */
	public static function is_encoder_runnable(): bool {
		return \function_exists( 'imagecreatefromstring' )
			&& \function_exists( 'imagecreatetruecolor' )
			&& \function_exists( 'imagescale' );
	}

	/**
	 * Queue (or skip, if already queued) a cron event to compute
	 * the blurhash for an attachment. Internal helper for
	 * {@see self::schedule_encode()} — callers should go through
	 * that filter callback so the metadata-regen invalidation pass
	 * stays load-bearing.
	 *
	 * @param int $attachment_id Attachment post ID.
	 */
	private static function schedule( int $attachment_id ): void {
		if ( $attachment_id < 1 ) {
			return;
		}
		// Rely on WP's own duplicate-event guard inside
		// `wp_schedule_single_event` (rejects matching args within
		// the 10-minute window) rather than running an explicit
		// `wp_next_scheduled` check first. The explicit check did
		// nothing the underlying scheduler doesn't already do and
		// added a needless read against the autoloaded cron option
		// on every attachment metadata regen.
		\wp_schedule_single_event( \time(), self::CRON_HOOK, array( $attachment_id ) );
	}

	/**
	 * Cron callback: compute and store the blurhash. No-op when a
	 * hash is already stored; callers wanting a re-encode delete
	 * the postmeta first ({@see self::delete()}) — that's what
	 * {@see self::schedule_encode()} does before scheduling, so the
	 * media-replace path always recomputes.
	 *
	 * Emits `activitypub_blurhash_encode_failed` (action) and an
	 * `error_log` line when the encoder returns null without a
	 * pre-existing stored hash, so transient failures (NFS hiccup,
	 * S3 lag, GD blip) leave a signal for monitoring instead of
	 * silently never producing a placeholder.
	 *
	 * @param int $attachment_id Attachment post ID.
	 */
	public static function run_encode( int $attachment_id ): void {
		$attachment_id = (int) $attachment_id;
		if ( $attachment_id < 1 ) {
			return;
		}

		// Predictable unencodability (system-wide GD missing,
		// non-raster mime, deleted attachment) is silent — logging
		// per-event would spam diagnostics on every scheduled run
		// even though the reason is global. Only unexpected failures
		// (encoder exception, missing file, decode failure) below
		// fire the diagnostic action.
		if ( ! self::is_encoder_runnable() || ! self::is_encodable_attachment( $attachment_id ) ) {
			return;
		}

		if ( null !== self::get( $attachment_id ) ) {
			return;
		}
		$hash = self::encode_from_attachment( $attachment_id );
		if ( null !== $hash ) {
			self::set( $attachment_id, $hash );
			return;
		}

		// Silent-failure guard — surface the gap so an operator can
		// investigate (or wire monitoring against the action).
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- intentional plugin diagnostics; cron path only.
		\error_log( "[activitypub:blurhash] encode failed for attachment {$attachment_id}; placeholder will be absent until backfill." );

		/**
		 * Fires when the cron-deferred encoder fails to produce a
		 * hash for an attachment. Monitoring integrations can hook
		 * this to count blurhash-encode failures over time.
		 *
		 * @param int $attachment_id The attachment ID that failed to encode.
		 */
		\do_action( 'activitypub_blurhash_encode_failed', $attachment_id );
	}

	/**
	 * `activitypub_attachment` filter callback. Injects `blurhash`
	 * into image attachment arrays when a usable postmeta value is
	 * stored. No-op on anything else (non-image attachments, missing
	 * meta, malformed meta, malformed arrays) so non-photo federation
	 * paths are untouched. Sanitization is enforced inside
	 * {@see self::get()} — anyone with `edit_post_meta` on an
	 * attachment could otherwise rewrite `_activitypub_blurhash` to bytes
	 * that break `wp_json_encode` and drop the entire AP envelope.
	 *
	 * @param mixed $attachment    The attachment array as built by bundled AP.
	 * @param mixed $attachment_id The attachment post ID (mixed because the upstream filter is loosely typed).
	 * @return mixed
	 */
	public static function inject_blurhash( $attachment, $attachment_id ) {
		if ( ! \is_array( $attachment ) ) {
			return $attachment;
		}
		if ( 'Image' !== ( $attachment['type'] ?? '' ) ) {
			return $attachment;
		}
		$hash = self::get( (int) $attachment_id );
		if ( null === $hash ) {
			return $attachment;
		}
		$attachment['blurhash'] = $hash;
		return $attachment;
	}

	/**
	 * Validate a stored hash against the blurhash spec's character
	 * set and our length bound. Treat any out-of-bounds value as
	 * absent rather than coerce — preserves the "no blurhash is
	 * better than a wrong blurhash" posture the rest of the class
	 * follows. The base83 alphabet is defined by the spec at
	 * {@link https://github.com/woltapp/blurhash/blob/master/Algorithm.md#base-83}.
	 *
	 * @param string $hash Candidate hash string.
	 * @return bool
	 */
	private static function is_well_formed_hash( string $hash ): bool {
		$length = \strlen( $hash );
		if ( $length < 6 || $length > self::MAX_HASH_LENGTH ) {
			return false;
		}
		// Base83 alphabet — same character set the encoder library
		// emits, conservative enough to reject any byte sequence
		// that would surprise a downstream JSON encoder or client
		// decoder.
		return 1 === \preg_match( '/\A[0-9A-Za-z#$%*+,\-.:;=?@\[\]\^_{|}~]+\z/', $hash );
	}
}
