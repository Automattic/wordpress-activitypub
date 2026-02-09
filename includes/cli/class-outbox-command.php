<?php
/**
 * Outbox CLI Command.
 *
 * @package Activitypub
 */

namespace Activitypub\Cli;

use Activitypub\Collection\Outbox;

/**
 * Manage ActivityPub outbox items.
 *
 * @package Activitypub
 */
class Outbox_Command extends \WP_CLI_Command {

	/**
	 * Undo an activity that was sent to the Fediverse.
	 *
	 * Creates an Undo activity for a previously sent activity, effectively
	 * reversing its effect on federated instances.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : The ID or URL of the outbox item to undo.
	 *
	 * ## EXAMPLES
	 *
	 *     # Undo outbox item by ID
	 *     $ wp activitypub outbox undo 123
	 *
	 *     # Undo outbox item by URL
	 *     $ wp activitypub outbox undo "https://example.com/?post_type=ap_outbox&p=123"
	 *
	 * @subcommand undo
	 *
	 * @param array $args       The positional arguments.
	 * @param array $assoc_args The associative arguments (unused).
	 */
	public function undo( $args, $assoc_args ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		$outbox_item_id = $args[0];
		if ( ! is_numeric( $outbox_item_id ) ) {
			$outbox_item_id = \url_to_postid( $outbox_item_id );
		}

		$outbox_item = \get_post( $outbox_item_id );
		if ( ! $outbox_item ) {
			\WP_CLI::error( 'Activity not found.' );
		}

		$undo_id = Outbox::undo( $outbox_item );
		if ( ! $undo_id ) {
			\WP_CLI::error( 'Failed to undo activity.' );
		}
		\WP_CLI::success( 'Undo activity scheduled.' );
	}

	/**
	 * Re-schedule an activity that was sent to the Fediverse before.
	 *
	 * Useful for retrying failed deliveries or resending activities to
	 * followers.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : The ID or URL of the outbox item to reschedule.
	 *
	 * ## EXAMPLES
	 *
	 *     # Reschedule outbox item by ID
	 *     $ wp activitypub outbox reschedule 123
	 *
	 *     # Reschedule outbox item by URL
	 *     $ wp activitypub outbox reschedule "https://example.com/?post_type=ap_outbox&p=123"
	 *
	 * @subcommand reschedule
	 *
	 * @param array $args       The positional arguments.
	 * @param array $assoc_args The associative arguments (unused).
	 */
	public function reschedule( $args, $assoc_args ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		$outbox_item_id = $args[0];
		if ( ! is_numeric( $outbox_item_id ) ) {
			$outbox_item_id = \url_to_postid( $outbox_item_id );
		}

		$outbox_item = \get_post( $outbox_item_id );
		if ( ! $outbox_item ) {
			\WP_CLI::error( 'Activity not found.' );
		}

		Outbox::reschedule( $outbox_item );

		\WP_CLI::success( 'Rescheduled activity.' );
	}
}
