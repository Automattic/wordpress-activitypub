<?php
/**
 * Statistics class file.
 *
 * Collects and stores ActivityPub statistics for monthly/annual reports.
 *
 * @package Activitypub
 */

namespace Activitypub;

use Activitypub\Collection\Actors;
use Activitypub\Collection\Followers;
use Activitypub\Collection\Outbox;
use Activitypub\Comment;

/**
 * Statistics class.
 *
 * Handles collection and storage of ActivityPub statistics.
 */
class Statistics {

	/**
	 * Option prefix for statistics storage.
	 *
	 * @var string
	 */
	const OPTION_PREFIX = 'activitypub_stats_';

	/**
	 * Get the start and end date strings for a given month.
	 *
	 * @param int $year  The year.
	 * @param int $month The month (1-12).
	 *
	 * @return array { start: string, end: string } in 'Y-m-d H:i:s' format.
	 */
	public static function get_month_date_range( $year, $month ) {
		$last_day = (int) \gmdate( 't', \gmmktime( 0, 0, 0, $month, 1, $year ) );

		return array(
			'start' => \sprintf( '%d-%02d-01 00:00:00', $year, $month ),
			'end'   => \sprintf( '%d-%02d-%02d 23:59:59', $year, $month, $last_day ),
		);
	}

	/**
	 * Get the option name for monthly stats.
	 *
	 * @param int $user_id The user ID.
	 * @param int $year    The year.
	 * @param int $month   The month.
	 *
	 * @return string The option name.
	 */
	public static function get_monthly_option_name( $user_id, $year, $month ) {
		return sprintf( '%s%d_%d_%02d', self::OPTION_PREFIX, $user_id, $year, $month );
	}

	/**
	 * Get the option name for annual stats.
	 *
	 * @param int $user_id The user ID.
	 * @param int $year    The year.
	 *
	 * @return string The option name.
	 */
	public static function get_annual_option_name( $user_id, $year ) {
		return sprintf( '%s%d_%d_annual', self::OPTION_PREFIX, $user_id, $year );
	}

	/**
	 * Get monthly statistics.
	 *
	 * @param int $user_id The user ID.
	 * @param int $year    The year.
	 * @param int $month   The month.
	 *
	 * @return array|false The monthly stats array or false if not found.
	 */
	public static function get_monthly_stats( $user_id, $year, $month ) {
		return \get_option( self::get_monthly_option_name( $user_id, $year, $month ), false );
	}

	/**
	 * Get annual summary statistics.
	 *
	 * @param int $user_id The user ID.
	 * @param int $year    The year.
	 *
	 * @return array|false The annual stats array or false if not found.
	 */
	public static function get_annual_summary( $user_id, $year ) {
		return \get_option( self::get_annual_option_name( $user_id, $year ), false );
	}

	/**
	 * Save monthly statistics.
	 *
	 * @param int   $user_id The user ID.
	 * @param int   $year    The year.
	 * @param int   $month   The month.
	 * @param array $stats   The stats array.
	 *
	 * @return bool True on success, false on failure.
	 */
	public static function save_monthly_stats( $user_id, $year, $month, $stats ) {
		// Invalidate the REST API transient cache so fresh data is served.
		\delete_transient( 'activitypub_stats_' . $user_id );

		return \update_option( self::get_monthly_option_name( $user_id, $year, $month ), $stats, false );
	}

	/**
	 * Save annual summary statistics.
	 *
	 * @param int   $user_id The user ID.
	 * @param int   $year    The year.
	 * @param array $stats   The stats array.
	 *
	 * @return bool True on success, false on failure.
	 */
	public static function save_annual_summary( $user_id, $year, $stats ) {
		return \update_option( self::get_annual_option_name( $user_id, $year ), $stats, false );
	}

	/**
	 * Collect monthly statistics for a user.
	 *
	 * @param int $user_id The user ID.
	 * @param int $year    The year.
	 * @param int $month   The month.
	 *
	 * @return array The collected stats.
	 */
	public static function collect_monthly_stats( $user_id, $year, $month ) {
		$month = (int) $month;
		$year  = (int) $year;

		if ( $month < 1 || $month > 12 ) {
			return array();
		}

		$range = self::get_month_date_range( $year, $month );
		$start = $range['start'];
		$end   = $range['end'];

		// Count new followers gained this month (by post_date in followers table).
		$followers_count = Followers::count_in_range( $user_id, $start, $end );

		$stats = array(
			'posts_count'       => self::count_federated_posts_in_range( $user_id, $start, $end ),
			'followers_count'   => $followers_count,
			'followers_total'   => self::get_follower_count( $user_id ),
			'top_posts'         => self::get_top_posts( $user_id, $start, $end, 5 ),
			'top_multiplicator' => self::get_top_multiplicator( $user_id, $start, $end ),
			'collected_at'      => \gmdate( 'Y-m-d H:i:s' ),
		);

		// Add counts for each comment type dynamically.
		foreach ( \array_keys( self::get_comment_types_for_stats() ) as $type ) {
			$stats[ $type . '_count' ] = self::count_engagement_in_range( $user_id, $start, $end, $type );
		}

		self::save_monthly_stats( $user_id, $year, $month, $stats );

		return $stats;
	}

	/**
	 * Compile annual summary from monthly stats.
	 *
	 * @param int $user_id The user ID.
	 * @param int $year    The year.
	 *
	 * @return array The annual summary.
	 */
	public static function compile_annual_summary( $user_id, $year ) {
		// Initialize totals dynamically based on registered comment types.
		$comment_types = \array_keys( self::get_comment_types_for_stats() );
		$totals        = array( 'posts_count' => 0 );
		foreach ( $comment_types as $type ) {
			$totals[ $type . '_count' ] = 0;
		}

		$most_active_month      = null;
		$most_active_engagement = 0;
		$first_month_stats      = null;
		$last_month_stats       = null;
		$all_multiplicators     = array();

		for ( $month = 1; $month <= 12; $month++ ) {
			$stats = self::get_monthly_stats( $user_id, $year, $month );

			if ( ! $stats ) {
				continue;
			}

			// Track first and last months with data.
			if ( ! $first_month_stats ) {
				$first_month_stats = $stats;
			}
			$last_month_stats = $stats;

			// Sum totals dynamically.
			$totals['posts_count'] += $stats['posts_count'] ?? 0;
			foreach ( $comment_types as $type ) {
				$key             = $type . '_count';
				$totals[ $key ] += $stats[ $key ] ?? 0;
			}

			// Calculate engagement for this month (sum of all comment type counts).
			$engagement = 0;
			foreach ( $comment_types as $type ) {
				$engagement += $stats[ $type . '_count' ] ?? 0;
			}

			if ( $engagement > $most_active_engagement ) {
				$most_active_engagement = $engagement;
				$most_active_month      = $month;
			}

			// Aggregate multiplicators.
			if ( ! empty( $stats['top_multiplicator'] ) && ! empty( $stats['top_multiplicator']['url'] ) ) {
				$url = $stats['top_multiplicator']['url'];
				if ( ! isset( $all_multiplicators[ $url ] ) ) {
					$all_multiplicators[ $url ] = array(
						'name'  => $stats['top_multiplicator']['name'],
						'url'   => $url,
						'count' => 0,
					);
				}
				$all_multiplicators[ $url ]['count'] += $stats['top_multiplicator']['count'] ?? 0;
			}
		}

		// Find top multiplicator for the year.
		$top_multiplicator = null;
		if ( ! empty( $all_multiplicators ) ) {
			\usort(
				$all_multiplicators,
				function ( $a, $b ) {
					return $b['count'] - $a['count'];
				}
			);
			$top_multiplicator = \reset( $all_multiplicators );
		}

		// Build summary with dynamic comment type counts.
		// Calculate followers_start: total at start of first month (total minus gained that month).
		// Monthly stats store: followers_count (gained this month), followers_total (total at end of month).
		$followers_start = 0;
		if ( $first_month_stats ) {
			$followers_start = ( $first_month_stats['followers_total'] ?? 0 ) - ( $first_month_stats['followers_count'] ?? 0 );
		}

		// Get top posts for the full year.
		$year_start = \sprintf( '%d-01-01 00:00:00', $year );
		$year_end   = \sprintf( '%d-12-31 23:59:59', $year );

		$summary = array(
			'posts_count'          => $totals['posts_count'],
			'most_active_month'    => $most_active_month,
			'followers_start'      => $followers_start,
			'followers_end'        => $last_month_stats ? ( $last_month_stats['followers_total'] ?? 0 ) : self::get_follower_count( $user_id ),
			'followers_net_change' => 0,
			'top_multiplicator'    => $top_multiplicator,
			'top_posts'            => self::get_top_posts( $user_id, $year_start, $year_end, 5 ),
			'compiled_at'          => \gmdate( 'Y-m-d H:i:s' ),
		);

		// Add comment type totals dynamically.
		foreach ( $comment_types as $type ) {
			$summary[ $type . '_count' ] = $totals[ $type . '_count' ];
		}

		$summary['followers_net_change'] = $summary['followers_end'] - $summary['followers_start'];

		self::save_annual_summary( $user_id, $year, $summary );

		return $summary;
	}

	/**
	 * Count published posts in a date range.
	 *
	 * Counts published posts in ActivityPub-enabled post types.
	 *
	 * @param int    $user_id The user ID.
	 * @param string $start   Start date (Y-m-d H:i:s).
	 * @param string $end     End date (Y-m-d H:i:s).
	 *
	 * @return int The post count.
	 */
	public static function count_federated_posts_in_range( $user_id, $start, $end ) {
		global $wpdb;

		$post_subquery = self::get_post_ids_subquery( $user_id, $start, $end );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
		$count = $wpdb->get_var(
			"SELECT COUNT(*) FROM ({$post_subquery}) AS posts"
		);
		// phpcs:enable

		return (int) $count;
	}

	/**
	 * Count engagement (likes, reposts, comments, quotes) in a date range.
	 *
	 * @param int         $user_id The user ID.
	 * @param string      $start   Start date (Y-m-d H:i:s).
	 * @param string      $end     End date (Y-m-d H:i:s).
	 * @param string|null $type    Optional. The engagement type ('like', 'repost', 'comment', 'quote').
	 *
	 * @return int The engagement count.
	 */
	public static function count_engagement_in_range( $user_id, $start, $end, $type = null ) {
		global $wpdb;

		// Use a subquery to avoid loading all post IDs into memory.
		$post_subquery = self::get_post_ids_subquery( $user_id );

		$type_clause = '';
		if ( $type ) {
			$type_clause = $wpdb->prepare( ' AND c.comment_type = %s', $type );
		} else {
			// Get all comment types tracked in statistics (includes federated comments via filter).
			$comment_types = \array_keys( self::get_comment_types_for_stats() );
			if ( ! empty( $comment_types ) ) {
				$placeholders_types = \implode( ', ', \array_fill( 0, \count( $comment_types ), '%s' ) );
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
				$type_clause = $wpdb->prepare( " AND c.comment_type IN ($placeholders_types)", $comment_types );
			}
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT c.comment_ID) FROM {$wpdb->comments} c
				INNER JOIN {$wpdb->commentmeta} cm ON c.comment_ID = cm.comment_id
				WHERE c.comment_post_ID IN ({$post_subquery})
				AND cm.meta_key = 'protocol'
				AND cm.meta_value = 'activitypub'
				AND c.comment_date_gmt >= %s
				AND c.comment_date_gmt <= %s
				{$type_clause}",
				$start,
				$end
			)
		);
		// phpcs:enable

		return (int) $count;
	}

	/**
	 * Get top performing posts in a date range.
	 *
	 * @param int    $user_id The user ID.
	 * @param string $start   Start date (Y-m-d H:i:s).
	 * @param string $end     End date (Y-m-d H:i:s).
	 * @param int    $limit   Maximum number of posts to return.
	 *
	 * @return array Array of top posts with engagement data.
	 */
	public static function get_top_posts( $user_id, $start, $end, $limit = 5 ) {
		global $wpdb;

		// Use a subquery with date range to only consider posts published in the period.
		$post_subquery = self::get_post_ids_subquery( $user_id, $start, $end );

		// Use the same comment type source as all other statistics methods.
		$comment_types = \array_keys( self::get_comment_types_for_stats() );
		if ( empty( $comment_types ) ) {
			return array();
		}

		$placeholders_types = \implode( ', ', \array_fill( 0, \count( $comment_types ), '%s' ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$type_clause = $wpdb->prepare( "AND c.comment_type IN ({$placeholders_types})", $comment_types );

		// Get engagement counts per post (only engagement within the date range).
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT c.comment_post_ID as post_id, COUNT(c.comment_ID) as engagement_count
				FROM {$wpdb->comments} c
				INNER JOIN {$wpdb->commentmeta} cm ON c.comment_ID = cm.comment_id
				WHERE c.comment_post_ID IN ({$post_subquery})
				AND cm.meta_key = 'protocol'
				AND cm.meta_value = 'activitypub'
				{$type_clause}
				AND c.comment_date_gmt >= %s
				AND c.comment_date_gmt <= %s
				GROUP BY c.comment_post_ID
				ORDER BY engagement_count DESC
				LIMIT %d",
				$start,
				$end,
				$limit
			),
			ARRAY_A
		);
		// phpcs:enable

		$top_posts = array();
		foreach ( $results as $result ) {
			$post = \get_post( $result['post_id'] );
			if ( $post ) {
				$top_posts[] = array(
					'post_id'          => $result['post_id'],
					'title'            => \html_entity_decode( \get_the_title( $post ), ENT_QUOTES, 'UTF-8' ),
					'url'              => \get_permalink( $post ),
					'edit_url'         => \get_edit_post_link( $post, 'raw' ),
					'engagement_count' => (int) $result['engagement_count'],
				);
			}
		}

		return $top_posts;
	}

	/**
	 * Get the top multiplicator (actor who boosted content the most) in a date range.
	 *
	 * @param int    $user_id The user ID.
	 * @param string $start   Start date (Y-m-d H:i:s).
	 * @param string $end     End date (Y-m-d H:i:s).
	 *
	 * @return array|null Actor data or null if none found.
	 */
	public static function get_top_multiplicator( $user_id, $start, $end ) {
		global $wpdb;

		// Use a subquery to avoid loading all post IDs into memory.
		$post_subquery = self::get_post_ids_subquery( $user_id );

		// Get actor who boosted the most.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$result = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT c.comment_author as name, c.comment_author_url as url, COUNT(c.comment_ID) as boost_count
				FROM {$wpdb->comments} c
				INNER JOIN {$wpdb->commentmeta} cm ON c.comment_ID = cm.comment_id
				WHERE c.comment_post_ID IN ({$post_subquery})
				AND cm.meta_key = 'protocol'
				AND cm.meta_value = 'activitypub'
				AND c.comment_type = 'repost'
				AND c.comment_date_gmt >= %s
				AND c.comment_date_gmt <= %s
				GROUP BY c.comment_author_url
				ORDER BY boost_count DESC
				LIMIT 1",
				$start,
				$end
			),
			ARRAY_A
		);
		// phpcs:enable

		if ( ! $result || empty( $result['url'] ) ) {
			return null;
		}

		return array(
			'name'  => $result['name'],
			'url'   => $result['url'],
			'count' => (int) $result['boost_count'],
		);
	}

	/**
	 * Get current follower count for a user.
	 *
	 * @param int $user_id The user ID.
	 *
	 * @return int The follower count.
	 */
	public static function get_follower_count( $user_id ) {
		return Followers::count( $user_id );
	}

	/**
	 * Get all active user IDs that have ActivityPub enabled.
	 *
	 * @return int[] Array of user IDs including BLOG_USER_ID if enabled.
	 */
	public static function get_active_user_ids() {
		return Actors::get_all_ids();
	}

	/**
	 * Get statistics for the current period.
	 *
	 * Always queries live data for the current period to include recent engagement.
	 *
	 * @param int    $user_id The user ID.
	 * @param string $period  The period ('month', 'year', 'all').
	 *
	 * @return array The statistics.
	 */
	public static function get_current_stats( $user_id, $period = 'month' ) {
		$now = \current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested

		switch ( $period ) {
			case 'year':
				$start = \gmdate( 'Y-01-01 00:00:00', $now );
				$end   = \gmdate( 'Y-12-31 23:59:59', $now );
				break;

			case 'all':
				$start = '1970-01-01 00:00:00';
				$end   = \gmdate( 'Y-m-d 23:59:59', $now );
				break;

			case 'month':
			default:
				$start = \gmdate( 'Y-m-01 00:00:00', $now );
				$end   = \gmdate( 'Y-m-t 23:59:59', $now );
				break;
		}

		$stats = array(
			'posts_count'       => self::count_federated_posts_in_range( $user_id, $start, $end ),
			'followers_total'   => self::get_follower_count( $user_id ),
			'top_posts'         => self::get_top_posts( $user_id, $start, $end, 3 ),
			'top_multiplicator' => self::get_top_multiplicator( $user_id, $start, $end ),
			'period'            => $period,
			'start'             => $start,
			'end'               => $end,
		);

		// Add counts for each comment type dynamically.
		foreach ( \array_keys( self::get_comment_types_for_stats() ) as $type ) {
			$stats[ $type . '_count' ] = self::count_engagement_in_range( $user_id, $start, $end, $type );
		}

		return $stats;
	}

	/**
	 * Get rolling monthly breakdown (last X months).
	 *
	 * Returns stats for the last X months, crossing year boundaries as needed.
	 *
	 * @param int $user_id     The user ID.
	 * @param int $num_months  Optional. Number of months to return. Defaults to 12.
	 *
	 * @return array Array of monthly stats ordered chronologically.
	 */
	public static function get_rolling_monthly_breakdown( $user_id, $num_months = 12 ) {
		$now           = \current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
		$months        = array();
		$comment_types = \array_keys( self::get_comment_types_for_stats() );

		// Start from (num_months - 1) months ago and go to current month.
		for ( $i = $num_months - 1; $i >= 0; $i-- ) {
			$timestamp = \strtotime( "-{$i} months", $now );
			$year      = (int) \gmdate( 'Y', $timestamp );
			$month     = (int) \gmdate( 'n', $timestamp );

			$month_data          = self::get_month_data( $user_id, $year, $month, $comment_types );
			$month_data['year']  = $year;
			$month_data['month'] = $month;

			$months[] = $month_data;
		}

		return $months;
	}

	/**
	 * Get data for a single month.
	 *
	 * @param int   $user_id       The user ID.
	 * @param int   $year          The year.
	 * @param int   $month         The month.
	 * @param array $comment_types Array of comment type slugs.
	 *
	 * @return array Month data with posts_count, engagement, and type counts.
	 */
	private static function get_month_data( $user_id, $year, $month, $comment_types ) {
		$now           = \current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
		$current_year  = (int) \gmdate( 'Y', $now );
		$current_month = (int) \gmdate( 'n', $now );

		// Always query live for the current month to include recent engagement.
		$is_current_month = ( $year === $current_year && $month === $current_month );

		// Check for stored monthly stats first (but not for current month).
		$stored_stats = $is_current_month ? false : self::get_monthly_stats( $user_id, $year, $month );

		if ( $stored_stats ) {
			// Use stored data.
			$engagement = 0;
			foreach ( $comment_types as $type ) {
				$engagement += $stored_stats[ $type . '_count' ] ?? 0;
			}

			$month_data = array(
				'month'       => $month,
				'posts_count' => $stored_stats['posts_count'] ?? 0,
				'engagement'  => $engagement,
			);

			// Add counts for each comment type from stored stats.
			foreach ( $comment_types as $type ) {
				$month_data[ $type . '_count' ] = $stored_stats[ $type . '_count' ] ?? 0;
			}
		} else {
			// Query live data.
			$range = self::get_month_date_range( $year, $month );
			$start = $range['start'];
			$end   = $range['end'];

			$month_data = array(
				'month'       => $month,
				'posts_count' => self::count_federated_posts_in_range( $user_id, $start, $end ),
				'engagement'  => 0,
			);

			// Query each type and sum for total engagement (avoids extra N+1 total query).
			foreach ( $comment_types as $type ) {
				$type_count                     = self::count_engagement_in_range( $user_id, $start, $end, $type );
				$month_data[ $type . '_count' ] = $type_count;
				$month_data['engagement']      += $type_count;
			}
		}

		return $month_data;
	}

	/**
	 * Get period-over-period comparison (current month vs previous month).
	 *
	 * Reuses pre-computed current stats when available to avoid duplicate queries.
	 * Falls back to live queries for current month if no stats are provided.
	 *
	 * @param int        $user_id       The user ID.
	 * @param array|null $current_stats Optional. Pre-computed current month stats from get_current_stats().
	 *
	 * @return array Comparison data with current values and changes from previous month.
	 */
	public static function get_period_comparison( $user_id, $current_stats = null ) {
		$now = \current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested

		// Previous month (handles year boundary).
		$prev_timestamp = \strtotime( '-1 month', $now );
		$prev_year      = (int) \gmdate( 'Y', $prev_timestamp );
		$prev_month     = (int) \gmdate( 'n', $prev_timestamp );

		// Check for stored stats (only for previous month - current month is always live).
		$prev_stats = self::get_monthly_stats( $user_id, $prev_year, $prev_month );

		// Reuse pre-computed current stats or query live.
		if ( $current_stats ) {
			$current_posts = $current_stats['posts_count'] ?? 0;
		} else {
			$current_start = \gmdate( 'Y-m-01 00:00:00', $now );
			$current_end   = \gmdate( 'Y-m-t 23:59:59', $now );
			$current_posts = self::count_federated_posts_in_range( $user_id, $current_start, $current_end );
		}

		$current_start = $current_start ?? \gmdate( 'Y-m-01 00:00:00', $now );
		$current_end   = $current_end ?? \gmdate( 'Y-m-t 23:59:59', $now );

		$current_followers = Followers::count_in_range( $user_id, $current_start, $current_end );

		// Get previous month data (from stored stats or live query).
		if ( $prev_stats ) {
			$prev_posts     = $prev_stats['posts_count'] ?? 0;
			$prev_followers = $prev_stats['followers_count'] ?? 0;
		} else {
			$prev_start     = \gmdate( 'Y-m-01 00:00:00', $prev_timestamp );
			$prev_end       = \gmdate( 'Y-m-t 23:59:59', $prev_timestamp );
			$prev_posts     = self::count_federated_posts_in_range( $user_id, $prev_start, $prev_end );
			$prev_followers = Followers::count_in_range( $user_id, $prev_start, $prev_end );
		}

		$comparison = array(
			'posts'     => array(
				'current' => $current_posts,
				'change'  => $current_posts - $prev_posts,
			),
			'followers' => array(
				'current' => $current_followers,
				'change'  => $current_followers - $prev_followers,
			),
		);

		// Add comparison for each comment type tracked in statistics (includes federated comments).
		$comment_types = \array_keys( self::get_comment_types_for_stats() );
		foreach ( $comment_types as $type ) {
			// Reuse pre-computed current stats or query live.
			if ( $current_stats && isset( $current_stats[ $type . '_count' ] ) ) {
				$current_count = $current_stats[ $type . '_count' ];
			} else {
				$current_count = self::count_engagement_in_range( $user_id, $current_start, $current_end, $type );
			}

			// Use stored stats for previous month if available.
			if ( $prev_stats ) {
				$prev_count = $prev_stats[ $type . '_count' ] ?? 0;
			} else {
				$prev_count = self::count_engagement_in_range( $user_id, $prev_start, $prev_end, $type );
			}

			$comparison[ $type ] = array(
				'current' => $current_count,
				'change'  => $current_count - $prev_count,
			);
		}

		return $comparison;
	}

	/**
	 * Get comment types to track in statistics.
	 *
	 * By default includes all registered ActivityPub comment types.
	 * Use the 'activitypub_stats_comment_types' filter to add additional types.
	 *
	 * @return array Array of comment type data with slug, label, and singular.
	 */
	public static function get_comment_types_for_stats() {
		$comment_types = Comment::get_comment_types();
		$result        = array();

		foreach ( $comment_types as $slug => $type ) {
			$result[ $slug ] = array(
				'slug'     => $slug,
				'label'    => $type['label'] ?? \ucfirst( $slug ),
				'singular' => $type['singular'] ?? \ucfirst( $slug ),
			);
		}

		// Add federated comments (replies) which use the standard 'comment' type.
		if ( ! isset( $result['comment'] ) ) {
			$result['comment'] = array(
				'slug'     => 'comment',
				'label'    => \__( 'Comments', 'activitypub' ),
				'singular' => \__( 'Comment', 'activitypub' ),
			);
		}

		/**
		 * Filter the comment types tracked in statistics.
		 *
		 * Allows adding additional comment types to be tracked
		 * in the statistics dashboard.
		 *
		 * @param array $result Array of comment type data with slug, label, and singular.
		 */
		return \apply_filters( 'activitypub_stats_comment_types', $result );
	}

	/**
	 * Backfill historical statistics for all active users.
	 *
	 * This method processes statistics in batches to avoid timeouts.
	 * It only collects stats for completed months (not the current month).
	 *
	 * @param int $batch_size Optional. Number of months to process per batch. Default 12.
	 * @param int $user_index Optional. The current user index being processed. Default 0.
	 * @param int $year       Optional. The year being processed. Default 0 (will determine earliest year).
	 * @param int $month      Optional. The month being processed. Default 1.
	 *
	 * @return array|null Array with batch info if more processing needed, null if complete.
	 */
	public static function backfill_historical_stats( $batch_size = 12, $user_index = 0, $year = 0, $month = 1 ) {
		$user_ids = self::get_active_user_ids();

		if ( empty( $user_ids ) || $user_index >= \count( $user_ids ) ) {
			return null; // All done.
		}

		$user_id       = $user_ids[ $user_index ];
		$now           = \current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
		$current_year  = (int) \gmdate( 'Y', $now );
		$current_month = (int) \gmdate( 'n', $now );

		// Determine the earliest year with data if not set.
		if ( 0 === $year ) {
			$year = self::get_earliest_data_year( $user_id );
			if ( ! $year ) {
				// No data for this user, move to next user.
				return array(
					'batch_size' => $batch_size,
					'user_index' => $user_index + 1,
					'year'       => 0,
					'month'      => 1,
				);
			}
		}

		$months_processed = 0;

		// Process months for this user.
		while ( $months_processed < $batch_size ) {
			// Skip the current month - it's still in progress and should always be queried live.
			// Only process completed months (before the current month).
			if ( $year > $current_year || ( $year === $current_year && $month >= $current_month ) ) {
				// Move to next user.
				return array(
					'batch_size' => $batch_size,
					'user_index' => $user_index + 1,
					'year'       => 0,
					'month'      => 1,
				);
			}

			// Check if stats already exist for this month.
			$existing = self::get_monthly_stats( $user_id, $year, $month );
			if ( ! $existing ) {
				// Collect stats for this month.
				self::collect_monthly_stats( $user_id, $year, $month );
			}

			++$months_processed;
			++$month;

			// Move to next year if needed.
			if ( $month > 12 ) {
				$month = 1;
				++$year;
			}
		}

		// More months to process for this user.
		return array(
			'batch_size' => $batch_size,
			'user_index' => $user_index,
			'year'       => $year,
			'month'      => $month,
		);
	}

	/**
	 * Get a prepared SQL subquery that returns post IDs for a user.
	 *
	 * This avoids loading all post IDs into PHP memory by using a SQL subquery
	 * that can be embedded in other queries via IN (...).
	 *
	 * @param int         $user_id The user ID.
	 * @param string|null $start   Optional start date (Y-m-d H:i:s).
	 * @param string|null $end     Optional end date (Y-m-d H:i:s).
	 *
	 * @return string Prepared SQL subquery string.
	 */
	private static function get_post_ids_subquery( $user_id, $start = null, $end = null ) {
		global $wpdb;

		$post_types        = (array) \get_option( 'activitypub_support_post_types', array( 'post' ) );
		$type_placeholders = \implode( ', ', \array_fill( 0, \count( $post_types ), '%s' ) );
		$params            = $post_types;

		$author_clause = '';
		if ( Actors::BLOG_USER_ID !== $user_id ) {
			$author_clause = ' AND post_author = %d';
			$params[]      = $user_id;
		}

		$date_clause = '';
		if ( $start && $end ) {
			// Use COALESCE to fall back to post_date when post_date_gmt is empty.
			$date_clause = " AND COALESCE(NULLIF(post_date_gmt, '0000-00-00 00:00:00'), post_date) >= %s AND COALESCE(NULLIF(post_date_gmt, '0000-00-00 00:00:00'), post_date) <= %s";
			$params[]    = $start;
			$params[]    = $end;
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		return $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ({$type_placeholders}){$author_clause}{$date_clause}",
			$params
		);
		// phpcs:enable
	}

	/**
	 * Get the earliest year that has ActivityPub data for a user.
	 *
	 * @param int $user_id The user ID.
	 *
	 * @return int|null The earliest year with data, or null if no data.
	 */
	private static function get_earliest_data_year( $user_id ) {
		global $wpdb;

		// Use a subquery to avoid loading all post IDs into memory.
		$post_subquery = self::get_post_ids_subquery( $user_id );

		// Find earliest comment with ActivityPub protocol.
		// The $post_subquery is already prepared via $wpdb->prepare() in get_post_ids_subquery(),
		// so the outer query is safe despite not using prepare() itself.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
		$earliest_date = $wpdb->get_var(
			"SELECT MIN(c.comment_date_gmt) FROM {$wpdb->comments} c
			INNER JOIN {$wpdb->commentmeta} cm ON c.comment_ID = cm.comment_id
			WHERE c.comment_post_ID IN ({$post_subquery})
			AND cm.meta_key = 'protocol'
			AND cm.meta_value = 'activitypub'"
		);
		// phpcs:enable

		if ( ! $earliest_date ) {
			// No ActivityPub data, check outbox instead.
			$outbox_args = array(
				'post_type'      => Outbox::POST_TYPE,
				'posts_per_page' => 1,
				'orderby'        => 'date',
				'order'          => 'ASC',
				'fields'         => 'ids',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_query'     => array(
					array(
						'key'   => '_activitypub_activity_type',
						'value' => 'Create',
					),
				),
			);

			if ( Actors::BLOG_USER_ID !== $user_id ) {
				$outbox_args['author'] = $user_id;
			}

			$earliest_outbox = \get_posts( $outbox_args );

			if ( empty( $earliest_outbox ) ) {
				return null;
			}

			$earliest_post = \get_post( $earliest_outbox[0] );
			$earliest_date = $earliest_post->post_date_gmt;
		}

		return (int) \gmdate( 'Y', \strtotime( $earliest_date ) );
	}
}
