<?php
/**
 * WP-CLI commands for the Blurhash encoder.
 *
 * @package Activitypub
 */

namespace Activitypub\Cli;

use Activitypub\Blurhash;

/**
 * `wp activitypub blurhash …` command surface for backfilling and managing
 * stored blurhash placeholders.
 *
 * The runtime path ({@see Blurhash}) covers new uploads via a cron
 * event; this command exists for two cases the runtime can't handle:
 *
 *   1. **First install on a site with existing media** — every
 *      already-uploaded image attachment is missing the postmeta and
 *      will never get it without a re-upload or a forced re-encode.
 *      `wp activitypub blurhash backfill` walks the library and fills the
 *      gap.
 *   2. **Re-encode after an algorithm change** — if the encoder's
 *      components or source-size change, the previously-computed
 *      hashes are stale (still decodable, but produced from a
 *      different recipe). `--force` recomputes against the latest
 *      bytes.
 *
 * Registration is gated on `WP_CLI` so the class is only loaded by
 * the CLI process — no overhead on web requests.
 */
class Blurhash_Command extends \WP_CLI_Command {

	/**
	 * WP_Query page size for the backfill walk. 100 is the same batch
	 * size WP's own bulk operations default to and keeps each query's
	 * working set bounded.
	 *
	 * @var int
	 */
	private const PAGE_SIZE = 100;

	/**
	 * Compute and persist Blurhash placeholders for image
	 * attachments missing one. Use after first install on a site
	 * with existing media, or after an encoder change with --force.
	 *
	 * Default mode walks only attachments that have no stored
	 * `_activitypub_blurhash` — already-encoded media is filtered out
	 * server-side via a `NOT EXISTS` meta query rather than fetched
	 * and skipped in PHP. Combined with `--limit`, this means a
	 * limit of N processes exactly N candidates (the prior behavior
	 * counted already-hashed attachments against the limit and could
	 * never advance past a fully-encoded first page).
	 *
	 * `--force` drops the meta-query filter and walks every image
	 * attachment in ID order. The existing hash is overwritten only
	 * after the new encode succeeds, so a failed re-encode leaves
	 * the prior good hash in place.
	 *
	 * Non-raster `image/*` mime types (SVG, etc.) are recognized by
	 * `wp_attachment_is_image()` returning false and are counted as
	 * skipped, not failed — they're outside the encoder's GD-based
	 * scope by design.
	 *
	 * Exits nonzero when any encode failed, so automation can detect
	 * partial-success runs without parsing the summary text.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Walk and report what would be encoded, but don't write postmeta.
	 *
	 * [--limit=<n>]
	 * : Process at most <n> candidate attachments. Default: 0 (no limit).
	 *
	 * [--force]
	 * : Re-encode attachments that already have a stored hash.
	 *
	 * ## EXAMPLES
	 *
	 *     wp activitypub blurhash backfill --dry-run
	 *     wp activitypub blurhash backfill --limit=100
	 *     wp activitypub blurhash backfill --force
	 *
	 * @param array<int, string>   $args       Positional CLI args (unused).
	 * @param array<string, mixed> $assoc_args Associative CLI flags.
	 *
	 * @when after_wp_load
	 */
	public function backfill( $args, $assoc_args ) {
		unset( $args );

		$dry_run = ! empty( $assoc_args['dry-run'] );
		$force   = ! empty( $assoc_args['force'] );
		$limit   = isset( $assoc_args['limit'] ) ? (int) $assoc_args['limit'] : 0;

		// Fail fast when the encoder can't run on this host.
		// Without this gate, the loop would emit a `\WP_CLI::warning`
		// per attachment and exit non-zero — noisy and unactionable.
		// `--dry-run` still works (no encoding attempted), so
		// operators can enumerate candidates from a GD-less host
		// to decide whether to migrate the media.
		if ( ! $dry_run && ! Blurhash::is_encoder_runnable() ) {
			\WP_CLI::error( 'Blurhash encoder requires GD (imagecreatefromstring/truecolor/scale). Install or enable GD and re-run.' );
			return;
		}

		$encoded = 0;
		$skipped = 0;
		$failed  = 0;
		$last_id = 0;

		while ( true ) {
			$query_args = array(
				'post_type'              => 'attachment',
				'post_status'            => 'inherit',
				'post_mime_type'         => 'image',
				'posts_per_page'         => self::PAGE_SIZE,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
			);

			// Default mode filters to candidates server-side, so we
			// never call `Blurhash::get()` per row in the loop (the
			// N+1 the prior pagination shape had). `--force` skips
			// the filter and walks everything in ID order.
			//
			// Candidate set is "key missing OR value empty". A
			// non-empty-but-malformed row (postmeta poisoning, a
			// truncated import) self-heals through the runtime
			// cron path because {@see Blurhash::get()} reports
			// malformed values as absent, so `run_encode()` will
			// re-compute on the next `wp_generate_attachment_metadata`
			// regen. Operators who need a one-shot rescue without
			// waiting for a regen run the command with `--force`.
			if ( ! $force ) {
				$query_args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- one-shot CLI backfill; not a web-path query.
					'relation' => 'OR',
					array(
						'key'     => Blurhash::META_KEY,
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'     => Blurhash::META_KEY,
						'value'   => '',
						'compare' => '=',
					),
				);
			}

			// Keyset pagination — `ID > $last_id` is stable under
			// concurrent inserts and deletes, so a long backfill can't
			// silently skip attachments the way offset pagination
			// (paged=N) does when rows shift mid-run.
			$where_filter = null;
			if ( $last_id > 0 ) {
				$where_filter = self::keyset_where( $last_id );
				\add_filter( 'posts_where', $where_filter, 10, 1 );
			}

			$query = new \WP_Query( $query_args );

			if ( null !== $where_filter ) {
				\remove_filter( 'posts_where', $where_filter, 10 );
			}

			if ( empty( $query->posts ) ) {
				break;
			}

			foreach ( $query->posts as $attachment_id ) {
				$attachment_id = (int) $attachment_id;
				$last_id       = $attachment_id;

				// Non-encodable mime types (SVG, ICO, anything
				// outside the GD raster set) get filtered out
				// up-front and counted as skipped, not failed. The
				// encoder would return null on them too, but a
				// per-attachment WARNING for every SVG on every
				// backfill run is noise; the user already knows we
				// don't encode vector formats.
				if ( ! Blurhash::is_encodable_attachment( $attachment_id ) ) {
					++$skipped;
					continue;
				}

				if ( $limit > 0 && ( $encoded + $failed ) >= $limit ) {
					break 2;
				}

				if ( $dry_run ) {
					++$encoded;
					\WP_CLI::log( "would encode: attachment {$attachment_id}" );
					continue;
				}

				$hash = Blurhash::encode_from_attachment( $attachment_id );
				if ( null === $hash ) {
					++$failed;
					\WP_CLI::warning( "encode failed: attachment {$attachment_id}" );
					continue;
				}

				// Encode-then-set — never delete-first. A failed
				// re-encode on `--force` leaves the prior good hash
				// in place rather than wiping it.
				Blurhash::set( $attachment_id, $hash );
				++$encoded;
				\WP_CLI::log( "encoded {$attachment_id}: {$hash}" );
			}
		}

		$encoded_label = $dry_run ? 'would encode' : 'encoded';
		$summary       = sprintf(
			'%s %d, skipped %d (non-raster or unsupported), failed %d.',
			$encoded_label,
			$encoded,
			$skipped,
			$failed
		);

		if ( $dry_run ) {
			\WP_CLI::success( '[dry-run] ' . $summary );
			return;
		}

		// Non-zero exit on any failure so automation can detect
		// partial-success runs without parsing the summary text.
		if ( $failed > 0 ) {
			\WP_CLI::error( $summary );
			return;
		}

		\WP_CLI::success( $summary );
	}

	/**
	 * Build a `posts_where` filter closure that constrains the
	 * query to `ID > $last_id`. Used to implement keyset pagination
	 * without polluting WP_Query's stable args. The closure clears
	 * itself after each query in {@see self::backfill()}.
	 *
	 * @param int $last_id Last processed attachment ID.
	 * @return \Closure
	 */
	private static function keyset_where( int $last_id ): \Closure {
		return function ( $where ) use ( $last_id ) {
			global $wpdb;
			return $where . $wpdb->prepare( " AND {$wpdb->posts}.ID > %d", $last_id );
		};
	}
}
