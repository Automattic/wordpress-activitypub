<?php
/**
 * Stats CLI Command.
 *
 * @package Activitypub
 */

namespace Activitypub\Cli;

use Activitypub\Scheduler\Statistics as Statistics_Scheduler;
use Activitypub\Statistics;

/**
 * Manage ActivityPub statistics.
 *
 * @package Activitypub
 */
class Stats_Command extends \WP_CLI_Command {

	/**
	 * Collect monthly statistics.
	 *
	 * Gathers statistics for a given month including post counts, follower
	 * changes, engagement metrics, and top content.
	 *
	 * ## OPTIONS
	 *
	 * [--user_id=<user_id>]
	 * : The user ID to collect stats for. Omit to collect for all active users.
	 *
	 * [--year=<year>]
	 * : The year to collect stats for. Defaults to current year.
	 *
	 * [--month=<month>]
	 * : The month to collect stats for (1-12). Defaults to current month.
	 *
	 * [--force]
	 * : Force recollection even if stats already exist.
	 *
	 * ## EXAMPLES
	 *
	 *     # Collect real stats for current month
	 *     $ wp activitypub stats collect
	 *
	 *     # Collect stats for a specific month
	 *     $ wp activitypub stats collect --year=2024 --month=6
	 *
	 *     # Force recollect stats for a specific user
	 *     $ wp activitypub stats collect --user_id=1 --force
	 *
	 * @subcommand collect
	 *
	 * @param array $args       The positional arguments (unused).
	 * @param array $assoc_args The associative arguments.
	 */
	public function collect( $args, $assoc_args ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		$user_id = isset( $assoc_args['user_id'] ) ? (int) $assoc_args['user_id'] : null;
		$year    = isset( $assoc_args['year'] ) ? (int) $assoc_args['year'] : (int) \gmdate( 'Y' );
		$month   = isset( $assoc_args['month'] ) ? (int) $assoc_args['month'] : (int) \gmdate( 'n' );
		$force   = isset( $assoc_args['force'] );

		if ( $month < 1 || $month > 12 ) {
			\WP_CLI::error( "Invalid month: {$month}. Must be between 1 and 12." );
		}

		if ( $year < 2000 || $year > (int) \gmdate( 'Y' ) + 1 ) {
			\WP_CLI::error( "Invalid year: {$year}." );
		}

		$user_ids = $user_id ? array( $user_id ) : Statistics::get_active_user_ids();

		foreach ( $user_ids as $uid ) {
			if ( $force ) {
				$option_name = Statistics::get_monthly_option_name( $uid, $year, $month );
				\delete_option( $option_name );
			}
			Statistics::collect_monthly_stats( $uid, $year, $month );
		}

		$count = count( $user_ids );
		\WP_CLI::success( "Monthly stats collected for {$count} user(s) ({$year}-{$month})." );
	}

	/**
	 * Compile annual statistics.
	 *
	 * Aggregates monthly statistics into an annual summary including totals,
	 * averages, and highlights for the year.
	 *
	 * ## OPTIONS
	 *
	 * [--user_id=<user_id>]
	 * : The user ID to compile stats for. Omit to compile for all active users.
	 *
	 * [--year=<year>]
	 * : The year to compile stats for. Defaults to previous year.
	 *
	 * ## EXAMPLES
	 *
	 *     # Compile annual stats for previous year
	 *     $ wp activitypub stats compile
	 *
	 *     # Compile annual stats for a specific year
	 *     $ wp activitypub stats compile --year=2024
	 *
	 *     # Compile for a specific user
	 *     $ wp activitypub stats compile --user_id=1 --year=2024
	 *
	 * @subcommand compile
	 *
	 * @param array $args       The positional arguments (unused).
	 * @param array $assoc_args The associative arguments.
	 */
	public function compile( $args, $assoc_args ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		$user_id = isset( $assoc_args['user_id'] ) ? (int) $assoc_args['user_id'] : null;
		$year    = isset( $assoc_args['year'] ) ? (int) $assoc_args['year'] : ( (int) \gmdate( 'Y' ) - 1 );

		$user_ids = $user_id ? array( $user_id ) : Statistics::get_active_user_ids();

		foreach ( $user_ids as $uid ) {
			Statistics::compile_annual_summary( $uid, $year );
		}

		$count = count( $user_ids );
		\WP_CLI::success( "Annual stats compiled for {$count} user(s) ({$year})." );
	}

	/**
	 * Send the annual report email.
	 *
	 * Compiles annual statistics and sends the Fediverse Year in Review
	 * email for the specified year.
	 *
	 * ## OPTIONS
	 *
	 * [--user_id=<user_id>]
	 * : The user ID to send the email for. Omit to send for all active users.
	 *
	 * [--year=<year>]
	 * : The year to send the report for. Defaults to previous year.
	 *
	 * ## EXAMPLES
	 *
	 *     # Send annual report for previous year
	 *     $ wp activitypub stats send
	 *
	 *     # Send annual report for a specific year
	 *     $ wp activitypub stats send --year=2025
	 *
	 *     # Send for a specific user
	 *     $ wp activitypub stats send --user_id=1 --year=2025
	 *
	 * @subcommand send
	 *
	 * @param array $args       The positional arguments (unused).
	 * @param array $assoc_args The associative arguments.
	 */
	public function send( $args, $assoc_args ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		$user_id = isset( $assoc_args['user_id'] ) ? (int) $assoc_args['user_id'] : null;
		$year    = isset( $assoc_args['year'] ) ? (int) $assoc_args['year'] : ( (int) \gmdate( 'Y' ) - 1 );

		$user_ids = $user_id ? array( $user_id ) : Statistics::get_active_user_ids();

		$sent = 0;
		foreach ( $user_ids as $uid ) {
			$summary = Statistics::compile_annual_summary( $uid, $year );

			if ( empty( $summary ) ) {
				\WP_CLI::warning( "No stats found for user {$uid} ({$year}), skipping." );
				continue;
			}

			Statistics_Scheduler::send_annual_email( $uid, $year, $summary );
			\WP_CLI::log( "Annual report email sent for user {$uid} ({$year})." );
			++$sent;
		}

		\WP_CLI::success( "Annual report email sent for {$sent} user(s) ({$year})." );
	}

	/**
	 * Send the monthly report email.
	 *
	 * Sends the monthly Fediverse stats report email for the specified month.
	 *
	 * ## OPTIONS
	 *
	 * [--user_id=<user_id>]
	 * : The user ID to send the email for. Omit to send for all active users.
	 *
	 * [--year=<year>]
	 * : The year. Defaults to current year.
	 *
	 * [--month=<month>]
	 * : The month (1-12). Defaults to previous month.
	 *
	 * ## EXAMPLES
	 *
	 *     # Send monthly report for previous month
	 *     $ wp activitypub stats send-monthly
	 *
	 *     # Send monthly report for a specific month
	 *     $ wp activitypub stats send-monthly --year=2025 --month=2
	 *
	 *     # Send for a specific user
	 *     $ wp activitypub stats send-monthly --user_id=1 --year=2025 --month=6
	 *
	 * @subcommand send-monthly
	 *
	 * @param array $args       The positional arguments (unused).
	 * @param array $assoc_args The associative arguments.
	 */
	public function send_monthly( $args, $assoc_args ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		$user_id    = isset( $assoc_args['user_id'] ) ? (int) $assoc_args['user_id'] : null;
		$prev_month = \strtotime( '-1 month' );
		$year       = isset( $assoc_args['year'] ) ? (int) $assoc_args['year'] : (int) \gmdate( 'Y', $prev_month );
		$month      = isset( $assoc_args['month'] ) ? (int) $assoc_args['month'] : (int) \gmdate( 'n', $prev_month );

		if ( $month < 1 || $month > 12 ) {
			\WP_CLI::error( "Invalid month: {$month}. Must be between 1 and 12." );
		}

		$user_ids = $user_id ? array( $user_id ) : Statistics::get_active_user_ids();

		$sent = 0;
		foreach ( $user_ids as $uid ) {
			Statistics_Scheduler::send_monthly_email( $uid, $year, $month );
			\WP_CLI::log( "Monthly report email sent for user {$uid} ({$year}-{$month})." );
			++$sent;
		}

		\WP_CLI::success( "Monthly report email sent for {$sent} user(s) ({$year}-{$month})." );
	}
}
