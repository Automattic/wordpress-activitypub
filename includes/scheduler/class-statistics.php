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
		\add_filter( 'cron_schedules', array( self::class, 'add_cron_schedules' ) );
	}

	/**
	 * Add custom cron schedules.
	 *
	 * @param array $schedules Existing cron schedules.
	 *
	 * @return array Modified cron schedules.
	 */
	public static function add_cron_schedules( $schedules ) {
		$schedules['monthly'] = array(
			'interval' => 30 * DAY_IN_SECONDS,
			'display'  => \__( 'Once Monthly', 'activitypub' ),
		);

		$schedules['yearly'] = array(
			'interval' => 365 * DAY_IN_SECONDS,
			'display'  => \__( 'Once Yearly', 'activitypub' ),
		);

		return $schedules;
	}

	/**
	 * Register statistics schedules.
	 */
	public static function register_schedules() {
		// Schedule monthly stats collection for the 1st of each month.
		if ( ! \wp_next_scheduled( 'activitypub_collect_monthly_stats' ) ) {
			// Calculate next 1st of month at 2:00 AM.
			$next_first = self::get_next_first_of_month();
			\wp_schedule_event( $next_first, 'monthly', 'activitypub_collect_monthly_stats' );
		}

		// Schedule annual stats compilation for January 1st.
		if ( ! \wp_next_scheduled( 'activitypub_compile_annual_stats' ) ) {
			$next_year = self::get_next_january_first();
			\wp_schedule_event( $next_year, 'yearly', 'activitypub_compile_annual_stats' );
		}
	}

	/**
	 * Deregister statistics schedules.
	 */
	public static function deregister_schedules() {
		\wp_unschedule_hook( 'activitypub_collect_monthly_stats' );
		\wp_unschedule_hook( 'activitypub_compile_annual_stats' );
	}

	/**
	 * Get the next 1st of month timestamp.
	 *
	 * @return int Unix timestamp of next 1st of month at 2:00 AM.
	 */
	private static function get_next_first_of_month() {
		$now        = \current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
		$next_month = \strtotime( 'first day of next month 02:00:00', $now );

		return $next_month;
	}

	/**
	 * Get the next January 1st timestamp.
	 *
	 * @return int Unix timestamp of next January 1st at 3:00 AM.
	 */
	private static function get_next_january_first() {
		$now  = \current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
		$year = (int) \gmdate( 'Y', $now );

		// If we're past January 1st, schedule for next year.
		$jan_first = \strtotime( sprintf( '%d-01-01 03:00:00', $year + 1 ) );

		return $jan_first;
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
		}

		/**
		 * Fires after monthly statistics have been collected for all users.
		 *
		 * @param int $year  The year of the collected stats.
		 * @param int $month The month of the collected stats.
		 */
		\do_action( 'activitypub_monthly_stats_collected', $year, $month );
	}

	/**
	 * Compile annual statistics and send emails.
	 *
	 * This runs on January 1st and compiles stats for the previous year.
	 */
	public static function compile_and_send_annual_stats() {
		$user_ids = Statistics_Collector::get_active_user_ids();

		// Get previous year.
		$now  = \current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
		$year = (int) \gmdate( 'Y', $now ) - 1;

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
	 * Send the annual wrapped email.
	 *
	 * @param int   $user_id The user ID.
	 * @param int   $year    The year.
	 * @param array $summary The annual summary data.
	 */
	private static function send_annual_email( $user_id, $year, $summary ) {
		if ( empty( $summary ) ) {
			return;
		}

		// Don't send email if there's no activity.
		if (
			empty( $summary['posts_count'] ) &&
			empty( $summary['likes_count'] ) &&
			empty( $summary['reposts_count'] ) &&
			empty( $summary['comments_count'] )
		) {
			return;
		}

		$args = \array_merge(
			$summary,
			array(
				'year'    => $year,
				'user_id' => $user_id,
			)
		);

		// Get month name for most_active_month.
		if ( ! empty( $summary['most_active_month'] ) ) {
			$args['most_active_month_name'] = \date_i18n( 'F', \strtotime( sprintf( '%d-%02d-01', $year, $summary['most_active_month'] ) ) );
		}

		Mailer::send( $user_id, 'annual_wrapped', $args );
	}

	/**
	 * Manually trigger monthly stats collection.
	 *
	 * Useful for CLI or testing purposes.
	 *
	 * @param int|null $user_id Optional. Specific user ID or null for all users.
	 * @param int|null $year    Optional. Year to collect stats for.
	 * @param int|null $month   Optional. Month to collect stats for.
	 *
	 * @return array Array of collected stats per user.
	 */
	public static function trigger_monthly_collection( $user_id = null, $year = null, $month = null ) {
		$now = \current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested

		if ( null === $year ) {
			$year = (int) \gmdate( 'Y', $now );
		}

		if ( null === $month ) {
			$month = (int) \gmdate( 'n', $now );
		}

		$user_ids = $user_id ? array( $user_id ) : Statistics_Collector::get_active_user_ids();
		$results  = array();

		foreach ( $user_ids as $uid ) {
			$results[ $uid ] = Statistics_Collector::collect_monthly_stats( $uid, $year, $month );
		}

		return $results;
	}

	/**
	 * Manually trigger annual stats compilation.
	 *
	 * Useful for CLI or testing purposes.
	 *
	 * @param int|null $user_id   Optional. Specific user ID or null for all users.
	 * @param int|null $year      Optional. Year to compile stats for.
	 * @param bool     $send_email Optional. Whether to send the email. Default true.
	 *
	 * @return array Array of compiled summaries per user.
	 */
	public static function trigger_annual_compilation( $user_id = null, $year = null, $send_email = true ) {
		$now = \current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested

		if ( null === $year ) {
			$year = (int) \gmdate( 'Y', $now ) - 1;
		}

		$user_ids = $user_id ? array( $user_id ) : Statistics_Collector::get_active_user_ids();
		$results  = array();

		foreach ( $user_ids as $uid ) {
			$summary         = Statistics_Collector::compile_annual_summary( $uid, $year );
			$results[ $uid ] = $summary;

			if ( $send_email ) {
				self::send_annual_email( $uid, $year, $summary );
			}
		}

		return $results;
	}
}
