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
	 * @param bool     $force   Optional. Force recollection even if stats exist. Default false.
	 *
	 * @return array Array of collected stats per user.
	 */
	public static function trigger_monthly_collection( $user_id = null, $year = null, $month = null, $force = false ) {
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
			if ( $force ) {
				$results[ $uid ] = Statistics_Collector::recollect_monthly_stats( $uid, $year, $month );
			} else {
				$results[ $uid ] = Statistics_Collector::collect_monthly_stats( $uid, $year, $month );
			}
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
