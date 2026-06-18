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
	 *   When --year is provided without --month, all months of that year
	 *   are collected (up to the current month for the current year).
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
	 *     # Collect all months of a year
	 *     $ wp activitypub stats collect --year=2024
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
		$user_id       = isset( $assoc_args['user_id'] ) ? (int) $assoc_args['user_id'] : null;
		$year          = isset( $assoc_args['year'] ) ? (int) $assoc_args['year'] : (int) \gmdate( 'Y' );
		$has_month     = isset( $assoc_args['month'] );
		$force         = isset( $assoc_args['force'] );
		$current_year  = (int) \gmdate( 'Y' );
		$current_month = (int) \gmdate( 'n' );

		if ( $year < 2000 || $year > $current_year + 1 ) {
			\WP_CLI::error( "Invalid year: {$year}." );
		}

		/*
		 * When --month is provided, collect that single month.
		 * When only --year is provided, collect all months of the year
		 * (up to the current month for the current year).
		 */
		if ( $has_month ) {
			$months = array( (int) $assoc_args['month'] );

			if ( $months[0] < 1 || $months[0] > 12 ) {
				\WP_CLI::error( "Invalid month: {$months[0]}. Must be between 1 and 12." );
			}
		} elseif ( isset( $assoc_args['year'] ) ) {
			$last_month = ( $year === $current_year ) ? $current_month : 12;
			$months     = \range( 1, $last_month );
		} else {
			$months = array( $current_month );
		}

		$user_ids = $user_id ? array( $user_id ) : Statistics::get_active_user_ids();

		foreach ( $months as $month ) {
			foreach ( $user_ids as $uid ) {
				if ( $force ) {
					$option_name = Statistics::get_monthly_option_name( $uid, $year, $month );
					\delete_option( $option_name );
				}
				Statistics::collect_monthly_stats( $uid, $year, $month );
			}

			$count = \count( $user_ids );
			\WP_CLI::log( "Collected {$year}-{$month} for {$count} user(s)." );
		}

		$total_months = \count( $months );
		\WP_CLI::success( "Monthly stats collected for {$total_months} month(s)." );
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
	 * Send the stats report email.
	 *
	 * Without --month, sends the annual Fediverse Year in Review.
	 * With --month, sends the monthly stats report for that month.
	 *
	 * ## OPTIONS
	 *
	 * [--user_id=<user_id>]
	 * : The user ID to send the email for. Omit to send for all active users.
	 *
	 * [--year=<year>]
	 * : The year. Defaults to previous year (annual) or current year (monthly).
	 *
	 * [--month=<month>]
	 * : The month (1-12). If provided, sends a monthly report instead of annual.
	 *
	 * ## EXAMPLES
	 *
	 *     # Send annual report for previous year
	 *     $ wp activitypub stats send
	 *
	 *     # Send annual report for a specific year
	 *     $ wp activitypub stats send --year=2025
	 *
	 *     # Send monthly report for a specific month
	 *     $ wp activitypub stats send --year=2025 --month=6
	 *
	 *     # Send monthly report for a specific user
	 *     $ wp activitypub stats send --user_id=1 --year=2025 --month=6
	 *
	 * @subcommand send
	 *
	 * @param array $args       The positional arguments (unused).
	 * @param array $assoc_args The associative arguments.
	 */
	public function send( $args, $assoc_args ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		$user_id    = isset( $assoc_args['user_id'] ) ? (int) $assoc_args['user_id'] : null;
		$is_monthly = isset( $assoc_args['month'] );

		if ( $is_monthly ) {
			$month = (int) $assoc_args['month'];
			$year  = isset( $assoc_args['year'] ) ? (int) $assoc_args['year'] : (int) \gmdate( 'Y' );

			if ( $month < 1 || $month > 12 ) {
				\WP_CLI::error( "Invalid month: {$month}. Must be between 1 and 12." );
			}
		} else {
			$year = isset( $assoc_args['year'] ) ? (int) $assoc_args['year'] : ( (int) \gmdate( 'Y' ) - 1 );
		}

		$user_ids = $user_id ? array( $user_id ) : Statistics::get_active_user_ids();

		$sent = 0;
		foreach ( $user_ids as $uid ) {
			if ( $is_monthly ) {
				Statistics_Scheduler::send_monthly_email( $uid, $year, $month, true );
				\WP_CLI::log( "Monthly report email sent for user {$uid} ({$year}-{$month})." );
			} else {
				$summary = Statistics::compile_annual_summary( $uid, $year );

				if ( empty( $summary ) ) {
					\WP_CLI::warning( "No stats found for user {$uid} ({$year}), skipping." );
					continue;
				}

				Statistics_Scheduler::send_annual_email( $uid, $year, $summary, true );
				\WP_CLI::log( "Annual report email sent for user {$uid} ({$year})." );
			}
			++$sent;
		}

		$type   = $is_monthly ? 'Monthly' : 'Annual';
		$period = $is_monthly ? "{$year}-{$month}" : "{$year}";
		\WP_CLI::success( "{$type} report email sent for {$sent} user(s) ({$period})." );
	}
}
