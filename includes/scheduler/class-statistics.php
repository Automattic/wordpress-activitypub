<?php
/**
 * Statistics scheduler class file.
 *
 * Handles scheduled collection of ActivityPub statistics.
 *
 * @package Activitypub
 */

namespace Activitypub\Scheduler;

use Activitypub\Mailer;
use Activitypub\Statistics as Statistics_Collector;

/**
 * Statistics scheduler class.
 */
class Statistics {

	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		\add_action( 'activitypub_collect_monthly_stats', array( self::class, 'collect_all_monthly_stats' ) );
		\add_action( 'activitypub_compile_annual_stats', array( self::class, 'compile_and_send_annual_stats' ) );
	}

	/**
	 * Collect monthly statistics for all active users.
	 *
	 * This runs on the 1st of each month and collects stats for the previous month.
	 */
	public static function collect_all_monthly_stats() {
		$user_ids = Statistics_Collector::get_active_user_ids();

		// Get previous month.
		$now        = \current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
		$prev_month = \strtotime( '-1 month', $now );
		$year       = (int) \gmdate( 'Y', $prev_month );
		$month      = (int) \gmdate( 'n', $prev_month );

		foreach ( $user_ids as $user_id ) {
			Statistics_Collector::collect_monthly_stats( $user_id, $year, $month );
			self::send_monthly_email( $user_id, $year, $month );
		}

		// Reschedule to the exact next 1st of month to prevent drift from the 30-day interval.
		$next_first = \strtotime( 'first day of next month 02:00:00', $now );
		\wp_clear_scheduled_hook( 'activitypub_collect_monthly_stats' );
		\wp_schedule_event( $next_first, 'monthly', 'activitypub_collect_monthly_stats' );

		/**
		 * Fires after monthly statistics have been collected for all users.
		 *
		 * @param int $year  The year of the collected stats.
		 * @param int $month The month of the collected stats.
		 */
		\do_action( 'activitypub_monthly_stats_collected', $year, $month );
	}

	/**
	 * Compile annual statistics and send notifications.
	 *
	 * This runs on December 1st and compiles stats for the current year
	 * (through November), giving users time to share their "wrapped" stats
	 * before year-end.
	 *
	 * @todo Create a shareable landing page instead of just sending an email.
	 *       The email should link to a public page where stats can be viewed
	 *       and shared. Consider adding a summary image generator.
	 */
	public static function compile_and_send_annual_stats() {
		$user_ids = Statistics_Collector::get_active_user_ids();

		// Get current year (we're running in December, compiling Jan-Nov stats).
		$now  = \current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
		$year = (int) \gmdate( 'Y', $now );

		foreach ( $user_ids as $user_id ) {
			$summary = Statistics_Collector::compile_annual_summary( $user_id, $year );

			// Send email notification.
			self::send_annual_email( $user_id, $year, $summary );
		}

		/**
		 * Fires after annual statistics have been compiled for all users.
		 *
		 * @param int $year The year of the compiled stats.
		 */
		\do_action( 'activitypub_annual_stats_compiled', $year );
	}

	/**
	 * Send the annual report email.
	 *
	 * @param int   $user_id The user ID.
	 * @param int   $year    The year.
	 * @param array $summary The annual summary data.
	 * @param bool  $force   Whether to bypass user preference checks.
	 */
	public static function send_annual_email( $user_id, $year, $summary, $force = false ) {
		if ( ! $force && ! self::should_send_report( $user_id, $summary, 'activitypub_mailer_annual_report', '1' ) ) {
			return;
		}

		// Get month name for most_active_month.
		$most_active_month_name = '';
		if ( ! empty( $summary['most_active_month'] ) ) {
			$most_active_month_name = \date_i18n( 'F', \strtotime( \sprintf( '%d-%02d-01', $year, $summary['most_active_month'] ) ) );
		}

		// Build follower text.
		$followers_text = '';
		if ( ! empty( $summary['followers_start'] ) || ! empty( $summary['followers_end'] ) ) {
			$followers_text = \sprintf(
				/* translators: 1: follower count at start, 2: follower count at end */
				\__( 'From <strong>%1$s</strong> to <strong>%2$s</strong> followers', 'activitypub' ),
				\number_format_i18n( $summary['followers_start'] ?? 0 ),
				\number_format_i18n( $summary['followers_end'] ?? 0 )
			);
		}

		// Build supporter text.
		$supporter_text = '';
		if ( ! empty( $summary['top_multiplicator'] ) ) {
			$supporter_text = \sprintf(
				/* translators: 1: supporter URL, 2: supporter name, 3: boost count */
				\__( '<strong><a href="%1$s">%2$s</a></strong> with %3$s boosts', 'activitypub' ),
				\esc_url( $summary['top_multiplicator']['url'] ),
				\esc_html( $summary['top_multiplicator']['name'] ),
				\number_format_i18n( $summary['top_multiplicator']['count'] )
			);
		}

		$args = \array_merge(
			$summary,
			array(
				/* translators: %d: Year */
				'title'                  => \sprintf( \__( 'Your %d Fediverse Year in Review', 'activitypub' ), $year ),
				/* translators: %d: Year */
				'intro'                  => \sprintf( \__( "Here's a look back at your %d activity on the Fediverse.", 'activitypub' ), $year ),
				'closing'                => \__( 'Thanks for being part of the Fediverse! Here\'s to another great year.', 'activitypub' ),
				'most_active_month_name' => $most_active_month_name,
				'followers_text'         => $followers_text,
				'supporter_text'         => $supporter_text,
				'user_id'                => $user_id,
			)
		);

		$subject = \sprintf(
			/* translators: 1: Blog name, 2: Year */
			\__( '[%1$s] Your %2$d Fediverse Year in Review', 'activitypub' ),
			\esc_html( \get_option( 'blogname' ) ),
			$year
		);

		// Build plain text alternative.
		/* translators: %d: Year */
		$alt_body = \sprintf( \__( "Here's your %d Fediverse year in review:\n\n", 'activitypub' ), $year );

		if ( ! empty( $args['posts_count'] ) ) {
			/* translators: %d: Number of posts */
			$alt_body .= \sprintf( \__( "Posts published: %d\n", 'activitypub' ), $args['posts_count'] );
		}

		if ( ! empty( $args['followers_net_change'] ) ) {
			/* translators: %d: Net follower change */
			$alt_body .= \sprintf( \__( "Follower growth: %+d\n", 'activitypub' ), $args['followers_net_change'] );
		}

		if ( ! empty( $most_active_month_name ) ) {
			/* translators: %s: Month name */
			$alt_body .= \sprintf( \__( "Most active month: %s\n", 'activitypub' ), $most_active_month_name );
		}

		Mailer::send( $user_id, $subject, 'stats-report', $args, $alt_body );
	}

	/**
	 * Send the monthly stats report email.
	 *
	 * @param int  $user_id The user ID.
	 * @param int  $year    The year.
	 * @param int  $month   The month (1-12).
	 * @param bool $force   Whether to bypass user preference checks.
	 */
	public static function send_monthly_email( $user_id, $year, $month, $force = false ) {
		$option_name = Statistics_Collector::get_monthly_option_name( $user_id, $year, $month );
		$stats       = \get_option( $option_name, array() );

		if ( empty( $stats ) ) {
			return;
		}

		if ( ! $force && ! self::should_send_report( $user_id, $stats, 'activitypub_mailer_monthly_report', '1' ) ) {
			return;
		}

		$month_name = \date_i18n( 'F Y', \strtotime( \sprintf( '%d-%02d-01', $year, $month ) ) );

		// Build follower text.
		$followers_text = '';
		if ( ! empty( $stats['followers_total'] ) ) {
			$followers_text = \sprintf(
				/* translators: %s: total follower count */
				\__( 'You now have <strong>%s</strong> followers', 'activitypub' ),
				\number_format_i18n( $stats['followers_total'] )
			);
		}

		// Build supporter text.
		$supporter_text = '';
		if ( ! empty( $stats['top_multiplicator'] ) ) {
			$supporter_text = \sprintf(
				/* translators: 1: supporter URL, 2: supporter name, 3: boost count */
				\__( '<strong><a href="%1$s">%2$s</a></strong> with %3$s boosts', 'activitypub' ),
				\esc_url( $stats['top_multiplicator']['url'] ),
				\esc_html( $stats['top_multiplicator']['name'] ),
				\number_format_i18n( $stats['top_multiplicator']['count'] )
			);
		}

		$args = \array_merge(
			$stats,
			array(
				/* translators: %s: Month and year, e.g. "March 2025" */
				'title'          => \sprintf( \__( 'Your Fediverse Stats for %s', 'activitypub' ), $month_name ),
				/* translators: %s: Month and year, e.g. "March 2025" */
				'intro'          => \sprintf( \__( "Here's how your content performed on the Fediverse in %s.", 'activitypub' ), $month_name ),
				'closing'        => \__( 'Keep sharing great content on the Fediverse!', 'activitypub' ),
				'followers_text' => $followers_text,
				'supporter_text' => $supporter_text,
				'user_id'        => $user_id,
			)
		);

		$subject = \sprintf(
			/* translators: 1: Blog name, 2: Month and year */
			\__( '[%1$s] Your Fediverse Stats for %2$s', 'activitypub' ),
			\esc_html( \get_option( 'blogname' ) ),
			$month_name
		);

		// Build plain text alternative.
		/* translators: %s: Month and year */
		$alt_body = \sprintf( \__( "Here's your Fediverse stats for %s:\n\n", 'activitypub' ), $month_name );

		if ( ! empty( $stats['posts_count'] ) ) {
			/* translators: %d: Number of posts */
			$alt_body .= \sprintf( \__( "Posts published: %d\n", 'activitypub' ), $stats['posts_count'] );
		}

		if ( ! empty( $stats['followers_count'] ) ) {
			/* translators: %d: New follower count */
			$alt_body .= \sprintf( \__( "New followers: %+d\n", 'activitypub' ), $stats['followers_count'] );
		}

		Mailer::send( $user_id, $subject, 'stats-report', $args, $alt_body );
	}

	/**
	 * Check whether a stats report should be sent.
	 *
	 * Verifies user preference and that there is meaningful activity.
	 *
	 * @param int    $user_id     The user ID.
	 * @param array  $stats       The stats data.
	 * @param string $option_name The preference option name (same for blog and user).
	 * @param string $fallback    The fallback value for the blog option.
	 *
	 * @return bool True if the report should be sent.
	 */
	private static function should_send_report( $user_id, $stats, $option_name, $fallback = '1' ) {
		if ( empty( $stats ) ) {
			return false;
		}

		// Check user preference.
		if ( $user_id > \Activitypub\Collection\Actors::BLOG_USER_ID ) {
			if ( ! \get_user_option( $option_name, $user_id ) ) {
				return false;
			}
		} elseif ( '1' !== \get_option( $option_name, $fallback ) ) {
			return false;
		}

		// Check that there is meaningful activity.
		if ( ! empty( $stats['posts_count'] ) || ! empty( $stats['followers_count'] ) ) {
			return true;
		}

		$comment_types = \array_keys( Statistics_Collector::get_comment_types_for_stats() );
		foreach ( $comment_types as $type ) {
			if ( ! empty( $stats[ $type . '_count' ] ) ) {
				return true;
			}
		}

		return false;
	}
}
