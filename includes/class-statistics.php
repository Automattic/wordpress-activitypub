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
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		// Register rewrite rules directly since we're already on init.
		self::add_rewrite_rules();

		\add_action( 'template_redirect', array( self::class, 'render_wrapped_card' ) );
		\add_filter( 'query_vars', array( self::class, 'add_query_vars' ) );
		\add_filter( 'activitypub_stats_comment_types', array( self::class, 'add_federated_comments_type' ) );
	}

	/**
	 * Add federated comments to the statistics comment types.
	 *
	 * @param array $types The comment types array.
	 *
	 * @return array The modified comment types array.
	 */
	public static function add_federated_comments_type( $types ) {
		$types['comment'] = array(
			'slug'     => 'comment',
			'label'    => \__( 'Comments', 'activitypub' ),
			'singular' => \__( 'Comment', 'activitypub' ),
		);

		return $types;
	}

	/**
	 * Add rewrite rules for nice wrapped URLs.
	 */
	public static function add_rewrite_rules() {
		// /fediverse-wrapped or /fediverse-wrapped/2024.
		\add_rewrite_rule(
			'^fediverse-wrapped/?$',
			'index.php?activitypub_wrapped=1',
			'top'
		);

		\add_rewrite_rule(
			'^fediverse-wrapped/(\d{4})/?$',
			'index.php?activitypub_wrapped=1&activitypub_wrapped_year=$matches[1]',
			'top'
		);

		// /@username/wrapped or /@username/wrapped/2024.
		\add_rewrite_rule(
			'^@([\w\-\.]+)/wrapped/?$',
			'index.php?activitypub_wrapped=1&actor=$matches[1]',
			'top'
		);

		\add_rewrite_rule(
			'^@([\w\-\.]+)/wrapped/(\d{4})/?$',
			'index.php?activitypub_wrapped=1&actor=$matches[1]&activitypub_wrapped_year=$matches[2]',
			'top'
		);
	}

	/**
	 * Add custom query vars.
	 *
	 * @param array $vars The existing query vars.
	 *
	 * @return array The modified query vars.
	 */
	public static function add_query_vars( $vars ) {
		$vars[] = 'activitypub_wrapped';
		$vars[] = 'activitypub_wrapped_year';
		return $vars;
	}

	/**
	 * Render the wrapped card if requested.
	 */
	public static function render_wrapped_card() {
		if ( ! \get_query_var( 'activitypub_wrapped' ) ) {
			return;
		}

		// Get user ID from actor query var or fallback to GET param.
		$actor = \get_query_var( 'actor' );
		if ( $actor ) {
			$actor_object = Actors::get_by_username( $actor );
			$user_id      = $actor_object && ! \is_wp_error( $actor_object ) ? $actor_object->get__id() : Actors::BLOG_USER_ID;
		} else {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$user_id = isset( $_GET['user_id'] ) ? \intval( $_GET['user_id'] ) : Actors::BLOG_USER_ID;
		}

		// Get year from query var or GET param.
		$year = \get_query_var( 'activitypub_wrapped_year' );
		if ( ! $year ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$year = isset( $_GET['year'] ) ? \intval( $_GET['year'] ) : (int) \gmdate( 'Y' );
		}
		$year = (int) $year;

		// Get annual summary if available.
		$stats = self::get_annual_summary( $user_id, $year );

		if ( ! $stats ) {
			// No stored stats - get real-time data for the requested year.
			$start = \gmdate( 'Y-01-01 00:00:00', \strtotime( $year . '-01-01' ) );
			$end   = \gmdate( 'Y-12-31 23:59:59', \strtotime( $year . '-12-31' ) );

			$stats = array(
				'posts_count'       => self::count_federated_posts_in_range( $user_id, $start, $end ),
				'followers_total'   => self::get_follower_count( $user_id ),
				'top_posts'         => self::get_top_posts( $user_id, $start, $end, 5 ),
				'top_multiplicator' => self::get_top_multiplicator( $user_id, $start, $end ),
			);

			// Add counts for each comment type dynamically.
			foreach ( \array_keys( self::get_comment_types_for_stats() ) as $type ) {
				$stats[ $type . '_count' ] = self::count_engagement_in_range( $user_id, $start, $end, $type );
			}

			// Add annual-specific fields.
			$stats['most_active_month']    = null;
			$stats['followers_net_change'] = 0;
		}

		$args = \array_merge(
			$stats,
			array(
				'year'            => $year,
				'user_id'         => $user_id,
				'site_name'       => \get_bloginfo( 'name' ),
				'followers_total' => self::get_follower_count( $user_id ),
			)
		);

		// Load the template.
		\load_template( ACTIVITYPUB_PLUGIN_DIR . 'templates/statistics/wrapped-card.php', true, array( 'args' => $args ) );
		exit;
	}

	/**
	 * Get the wrapped card URL for a user.
	 *
	 * @param int $user_id The user ID.
	 * @param int $year    Optional. The year. Defaults to current year.
	 *
	 * @return string The wrapped card URL.
	 */
	public static function get_wrapped_url( $user_id, $year = null ) {
		if ( ! $year ) {
			$year = (int) \gmdate( 'Y' );
		}

		// For blog actor, use /fediverse-wrapped/YEAR.
		if ( Actors::BLOG_USER_ID === $user_id ) {
			return \home_url( sprintf( '/fediverse-wrapped/%d/', $year ) );
		}

		// For user actors, use /@username/wrapped/YEAR.
		$actor = Actors::get_by_id( $user_id );
		if ( $actor && ! \is_wp_error( $actor ) ) {
			$username = $actor->get_preferred_username();
			return \home_url( sprintf( '/@%s/wrapped/%d/', $username, $year ) );
		}

		// Fallback to query string URL.
		return \add_query_arg(
			array(
				'activitypub_wrapped' => '1',
				'user_id'             => $user_id,
				'year'                => $year,
			),
			\home_url()
		);
	}

	/**
	 * Option prefix for statistics storage.
	 *
	 * @var string
	 */
	const OPTION_PREFIX = 'activitypub_stats_';

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
		$start = \gmdate( 'Y-m-d 00:00:00', \strtotime( sprintf( '%d-%02d-01', $year, $month ) ) );
		$end   = \gmdate( 'Y-m-d 23:59:59', \strtotime( 'last day of ' . sprintf( '%d-%02d', $year, $month ) ) );

		// Get previous month's follower count for comparison.
		$prev_month = $month - 1;
		$prev_year  = $year;
		if ( $prev_month < 1 ) {
			$prev_month = 12;
			--$prev_year;
		}
		$prev_stats        = self::get_monthly_stats( $user_id, $prev_year, $prev_month );
		$prev_followers    = $prev_stats ? $prev_stats['followers_total'] : 0;
		$current_followers = self::get_follower_count( $user_id );

		$stats = array(
			'posts_count'       => self::count_federated_posts_in_range( $user_id, $start, $end ),
			'followers_gained'  => \max( 0, $current_followers - $prev_followers ),
			'followers_lost'    => \max( 0, $prev_followers - $current_followers ),
			'followers_total'   => $current_followers,
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
		$summary = array(
			'posts_count'          => $totals['posts_count'],
			'most_active_month'    => $most_active_month,
			'followers_start'      => $first_month_stats ? ( $first_month_stats['followers_total'] ?? 0 ) - ( $first_month_stats['followers_gained'] ?? 0 ) + ( $first_month_stats['followers_lost'] ?? 0 ) : 0,
			'followers_end'        => $last_month_stats ? ( $last_month_stats['followers_total'] ?? 0 ) : self::get_follower_count( $user_id ),
			'followers_net_change' => 0,
			'top_multiplicator'    => $top_multiplicator,
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
	 * Count federated posts in a date range.
	 *
	 * Counts posts sent via the outbox with activity type 'Create'.
	 *
	 * @param int    $user_id The user ID.
	 * @param string $start   Start date (Y-m-d H:i:s).
	 * @param string $end     End date (Y-m-d H:i:s).
	 *
	 * @return int The post count.
	 */
	public static function count_federated_posts_in_range( $user_id, $start, $end ) {
		$meta_query = array(
			array(
				'key'   => '_activitypub_activity_type',
				'value' => 'Create',
			),
		);

		// Filter by actor type for user stats.
		if ( Actors::BLOG_USER_ID !== $user_id ) {
			$meta_query[] = array(
				'key'   => '_activitypub_activity_actor',
				'value' => 'user',
			);
		} else {
			$meta_query[] = array(
				'key'   => '_activitypub_activity_actor',
				'value' => 'blog',
			);
		}

		$args = array(
			'post_type'      => Outbox::POST_TYPE,
			'post_status'    => array( 'publish', 'pending' ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'date_query'     => array(
				array(
					'after'     => $start,
					'before'    => $end,
					'inclusive' => true,
				),
			),
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'meta_query'     => $meta_query,
		);

		// Filter by post author for user-specific stats.
		if ( Actors::BLOG_USER_ID !== $user_id ) {
			$args['author'] = $user_id;
		}

		$query = new \WP_Query( $args );

		return $query->found_posts;
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

		// Get post IDs for the user.
		$post_args = array(
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'post_status'    => 'publish',
		);

		if ( Actors::BLOG_USER_ID !== $user_id ) {
			$post_args['author'] = $user_id;
		}

		$post_ids = \get_posts( $post_args );

		if ( empty( $post_ids ) ) {
			return 0;
		}

		$placeholders = \implode( ', ', \array_fill( 0, \count( $post_ids ), '%d' ) );

		$type_clause = '';
		if ( $type ) {
			$type_clause = $wpdb->prepare( ' AND c.comment_type = %s', $type );
		} else {
			// Get all registered ActivityPub comment types dynamically.
			$comment_types = Comment::get_comment_type_slugs();
			if ( ! empty( $comment_types ) ) {
				$placeholders_types = \implode( ', ', \array_fill( 0, \count( $comment_types ), '%s' ) );
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
				$type_clause = $wpdb->prepare( " AND c.comment_type IN ($placeholders_types)", $comment_types );
			}
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT c.comment_ID) FROM {$wpdb->comments} c
				INNER JOIN {$wpdb->commentmeta} cm ON c.comment_ID = cm.comment_id
				WHERE c.comment_post_ID IN ({$placeholders})
				AND cm.meta_key = 'protocol'
				AND cm.meta_value = 'activitypub'
				AND c.comment_date_gmt >= %s
				AND c.comment_date_gmt <= %s
				{$type_clause}",
				\array_merge( $post_ids, array( $start, $end ) )
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

		$post_args = array(
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'post_status'    => 'publish',
			'date_query'     => array(
				array(
					'after'     => $start,
					'before'    => $end,
					'inclusive' => true,
				),
			),
		);

		if ( Actors::BLOG_USER_ID !== $user_id ) {
			$post_args['author'] = $user_id;
		}

		$post_ids = \get_posts( $post_args );

		if ( empty( $post_ids ) ) {
			return array();
		}

		$placeholders = \implode( ', ', \array_fill( 0, \count( $post_ids ), '%d' ) );

		// Get registered comment types dynamically.
		$comment_types = Comment::get_comment_type_slugs();
		if ( empty( $comment_types ) ) {
			return array();
		}

		$placeholders_types = \implode( ', ', \array_fill( 0, \count( $comment_types ), '%s' ) );

		// Get engagement counts per post.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
		// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT c.comment_post_ID as post_id, COUNT(c.comment_ID) as engagement_count
				FROM {$wpdb->comments} c
				INNER JOIN {$wpdb->commentmeta} cm ON c.comment_ID = cm.comment_id
				WHERE c.comment_post_ID IN ({$placeholders})
				AND cm.meta_key = 'protocol'
				AND cm.meta_value = 'activitypub'
				AND c.comment_type IN ({$placeholders_types})
				GROUP BY c.comment_post_ID
				ORDER BY engagement_count DESC
				LIMIT %d",
				\array_merge( $post_ids, $comment_types, array( $limit ) )
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
					'title'            => \get_the_title( $post ),
					'url'              => \get_permalink( $post ),
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

		$post_args = array(
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'post_status'    => 'publish',
		);

		if ( Actors::BLOG_USER_ID !== $user_id ) {
			$post_args['author'] = $user_id;
		}

		$post_ids = \get_posts( $post_args );

		if ( empty( $post_ids ) ) {
			return null;
		}

		$placeholders = \implode( ', ', \array_fill( 0, \count( $post_ids ), '%d' ) );

		// Get actor who boosted the most.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		$result = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT c.comment_author as name, c.comment_author_url as url, COUNT(c.comment_ID) as boost_count
				FROM {$wpdb->comments} c
				INNER JOIN {$wpdb->commentmeta} cm ON c.comment_ID = cm.comment_id
				WHERE c.comment_post_ID IN ({$placeholders})
				AND cm.meta_key = 'protocol'
				AND cm.meta_value = 'activitypub'
				AND c.comment_type = 'repost'
				AND c.comment_date_gmt >= %s
				AND c.comment_date_gmt <= %s
				GROUP BY c.comment_author_url
				ORDER BY boost_count DESC
				LIMIT 1",
				\array_merge( $post_ids, array( $start, $end ) )
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
	 * @return array Array of user IDs including BLOG_USER_ID if enabled.
	 */
	public static function get_active_user_ids() {
		$user_ids = array();

		// Check if blog actor is enabled.
		$actor_mode = \get_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_MODE );
		if ( \in_array( $actor_mode, array( ACTIVITYPUB_BLOG_MODE, ACTIVITYPUB_ACTOR_AND_BLOG_MODE ), true ) ) {
			$user_ids[] = Actors::BLOG_USER_ID;
		}

		// Get users with ActivityPub enabled.
		if ( \in_array( $actor_mode, array( ACTIVITYPUB_ACTOR_MODE, ACTIVITYPUB_ACTOR_AND_BLOG_MODE ), true ) ) {
			$users = \get_users(
				array(
					'capability__in' => array( 'activitypub' ),
					'fields'         => 'ID',
				)
			);

			$user_ids = \array_merge( $user_ids, $users );
		}

		return $user_ids;
	}

	/**
	 * Get statistics for the current period (real-time).
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
	 * Get monthly breakdown for the current year (for graphs).
	 *
	 * @param int $user_id The user ID.
	 * @param int $year    Optional. The year. Defaults to current year.
	 *
	 * @return array Array of monthly stats with month number as key.
	 */
	public static function get_yearly_monthly_breakdown( $user_id, $year = null ) {
		if ( ! $year ) {
			$year = (int) \gmdate( 'Y' );
		}

		$current_month = (int) \gmdate( 'n' );
		$current_year  = (int) \gmdate( 'Y' );
		$months        = array();

		// Get all comment types tracked in stats (includes federated comments via filter).
		$comment_types = \array_keys( self::get_comment_types_for_stats() );

		// Only go up to current month if we're in the current year.
		$max_month = ( $year === $current_year ) ? $current_month : 12;

		for ( $month = 1; $month <= $max_month; $month++ ) {
			$start = \gmdate( 'Y-m-d 00:00:00', \strtotime( sprintf( '%d-%02d-01', $year, $month ) ) );
			$end   = \gmdate( 'Y-m-d 23:59:59', \strtotime( 'last day of ' . sprintf( '%d-%02d', $year, $month ) ) );

			$engagement = self::count_engagement_in_range( $user_id, $start, $end );

			$month_data = array(
				'month'       => $month,
				'posts_count' => self::count_federated_posts_in_range( $user_id, $start, $end ),
				'engagement'  => $engagement,
			);

			// Add counts for each comment type tracked in stats.
			foreach ( $comment_types as $type ) {
				$month_data[ $type . '_count' ] = self::count_engagement_in_range( $user_id, $start, $end, $type );
			}

			$months[ $month ] = $month_data;
		}

		return $months;
	}

	/**
	 * Get year-over-year comparison for current month.
	 *
	 * @param int $user_id The user ID.
	 *
	 * @return array Comparison data with current values and changes from last year.
	 */
	public static function get_year_comparison( $user_id ) {
		$now           = \current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
		$current_year  = (int) \gmdate( 'Y', $now );
		$current_month = (int) \gmdate( 'n', $now );
		$last_year     = $current_year - 1;

		// Current month this year.
		$this_year_start = \gmdate( 'Y-m-01 00:00:00', $now );
		$this_year_end   = \gmdate( 'Y-m-t 23:59:59', $now );

		// Same month last year.
		$last_year_start = \gmdate( 'Y-m-d 00:00:00', \strtotime( sprintf( '%d-%02d-01', $last_year, $current_month ) ) );
		$last_year_end   = \gmdate( 'Y-m-d 23:59:59', \strtotime( 'last day of ' . sprintf( '%d-%02d', $last_year, $current_month ) ) );

		// Get current stats.
		$current_posts     = self::count_federated_posts_in_range( $user_id, $this_year_start, $this_year_end );
		$current_followers = self::get_follower_count( $user_id );

		// Get last year stats.
		$last_posts = self::count_federated_posts_in_range( $user_id, $last_year_start, $last_year_end );

		// Get last year's follower count from stored stats.
		$last_year_stats = self::get_monthly_stats( $user_id, $last_year, $current_month );
		$last_followers  = $last_year_stats ? ( $last_year_stats['followers_total'] ?? 0 ) : 0;

		$comparison = array(
			'posts'     => array(
				'current' => $current_posts,
				'change'  => $current_posts - $last_posts,
			),
			'followers' => array(
				'current' => $current_followers,
				'change'  => $last_followers > 0 ? $current_followers - $last_followers : 0,
			),
		);

		// Add comparison for each registered comment type dynamically.
		$comment_types = Comment::get_comment_type_slugs();
		foreach ( $comment_types as $type ) {
			$current_count = self::count_engagement_in_range( $user_id, $this_year_start, $this_year_end, $type );
			$last_count    = self::count_engagement_in_range( $user_id, $last_year_start, $last_year_end, $type );

			$comparison[ $type ] = array(
				'current' => $current_count,
				'change'  => $current_count - $last_count,
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

		/**
		 * Filter the comment types tracked in statistics.
		 *
		 * Allows adding additional comment types (like federated comments)
		 * to be tracked in the statistics dashboard.
		 *
		 * @param array $result Array of comment type data with slug, label, and singular.
		 */
		return \apply_filters( 'activitypub_stats_comment_types', $result );
	}

	/**
	 * Backfill historical statistics for all active users.
	 *
	 * This method processes statistics in batches to avoid timeouts.
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
		$current_year  = (int) \gmdate( 'Y' );
		$current_month = (int) \gmdate( 'n' );

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
			// Check if we've gone past the current month.
			if ( $year > $current_year || ( $year === $current_year && $month > $current_month ) ) {
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
	 * Get the earliest year that has ActivityPub data for a user.
	 *
	 * @param int $user_id The user ID.
	 *
	 * @return int|null The earliest year with data, or null if no data.
	 */
	private static function get_earliest_data_year( $user_id ) {
		global $wpdb;

		// Get post IDs for the user.
		$post_args = array(
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'post_status'    => 'publish',
		);

		if ( Actors::BLOG_USER_ID !== $user_id ) {
			$post_args['author'] = $user_id;
		}

		$post_ids = \get_posts( $post_args );

		if ( empty( $post_ids ) ) {
			return null;
		}

		$placeholders = \implode( ', ', \array_fill( 0, \count( $post_ids ), '%d' ) );

		// Find earliest comment with ActivityPub protocol.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$earliest_date = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MIN(c.comment_date_gmt) FROM {$wpdb->comments} c
				INNER JOIN {$wpdb->commentmeta} cm ON c.comment_ID = cm.comment_id
				WHERE c.comment_post_ID IN ({$placeholders})
				AND cm.meta_key = 'protocol'
				AND cm.meta_value = 'activitypub'",
				$post_ids
			)
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
