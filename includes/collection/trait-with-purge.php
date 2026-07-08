<?php
/**
 * Purge Trait file.
 *
 * @package Activitypub
 */

namespace Activitypub\Collection;

/**
 * Purge Trait.
 *
 * The CPT-backed collections (Inbox, Outbox, Remote_Posts) all cap their stored items
 * with the same batched, time-boxed delete loop: purge items older than N days, and once
 * the total exceeds the hard cap, drop the date filter to trim the oldest first until back
 * under the cap. This trait holds that loop; callers supply their own thresholds, extra
 * query args, and an optional per-batch preservation callback.
 *
 * Constants (`MAX_ITEMS`, `PURGE_BATCH_SIZE`, `PURGE_TIMEOUT`) and `POST_TYPE` come from
 * the using class via late static binding.
 */
trait With_Purge {

	/**
	 * Run the batched purge loop.
	 *
	 * @param int   $days    Number of days to keep items. Older items are eligible for deletion.
	 * @param array $options {
	 *     Optional. Purge behavior overrides.
	 *
	 *     @type int      $min_total  Skip the purge entirely when the total item count is at or below this. Default 0.
	 *     @type array    $query_args Extra `get_posts()` arguments merged over the defaults (e.g. a meta_query).
	 *     @type callable $preserve   Receives the current batch of post IDs and returns the subset to keep.
	 *                                When set, kept IDs accumulate into `exclude` so they are not re-fetched.
	 * }
	 *
	 * @return int The number of items deleted.
	 */
	protected static function run_purge( $days, $options = array() ) {
		if ( $days <= 0 ) {
			return 0;
		}

		$min_total  = $options['min_total'] ?? 0;
		$extra_args = $options['query_args'] ?? array();
		$preserve   = $options['preserve'] ?? null;

		$counts = \wp_count_posts( static::POST_TYPE );
		$total  = 0;
		foreach ( $counts as $count ) {
			$total += (int) $count;
		}

		if ( $total <= $min_total ) {
			return 0;
		}

		$deleted    = 0;
		$cutoff     = \gmdate( 'Y-m-d', \time() - ( $days * DAY_IN_SECONDS ) );
		$start_time = \time();
		$exclude    = array();

		// If total exceeds the hard cap, drop the date filter to purge oldest items first.
		$overflow   = $total > static::MAX_ITEMS;
		$date_query = array(
			array(
				'before' => $cutoff,
			),
		);

		$query_args = \array_merge(
			array(
				'post_type'   => static::POST_TYPE,
				'post_status' => 'any',
				'fields'      => 'ids',
				'numberposts' => static::PURGE_BATCH_SIZE,
				'orderby'     => 'date',
				'order'       => 'ASC',
			),
			$extra_args
		);

		if ( ! $overflow ) {
			$query_args['date_query'] = $date_query;
		}

		do {
			// Skip already-preserved items so the loop keeps advancing instead of re-fetching them.
			if ( $preserve ) {
				$query_args['exclude'] = $exclude;
			}

			$post_ids = \get_posts( $query_args );

			if ( empty( $post_ids ) ) {
				break;
			}

			$keep = $preserve ? \array_flip( \call_user_func( $preserve, $post_ids ) ) : array();

			foreach ( $post_ids as $post_id ) {
				if ( isset( $keep[ $post_id ] ) ) {
					$exclude[] = $post_id;
					continue;
				}

				\wp_delete_post( $post_id, true );
				++$deleted;
			}

			// Once we're back under the cap, re-apply the date filter.
			if ( $overflow && ( $total - $deleted ) <= static::MAX_ITEMS ) {
				$overflow                 = false;
				$query_args['date_query'] = $date_query;
			}
		} while ( ! empty( $post_ids ) && ( \time() - $start_time ) < static::PURGE_TIMEOUT );

		return $deleted;
	}
}
