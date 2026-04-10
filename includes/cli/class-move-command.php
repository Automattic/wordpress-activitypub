<?php
/**
 * Move CLI Command.
 *
 * @package Activitypub
 */

namespace Activitypub\Cli;

use Activitypub\Move;

/**
 * Move an ActivityPub account to a new URL.
 *
 * @package Activitypub
 */
class Move_Command extends \WP_CLI_Command {

	/**
	 * Move the blog to a new URL.
	 *
	 * Sends a Move activity to notify followers that your blog has moved
	 * to a new location. Followers on compatible instances will automatically
	 * update their subscription.
	 *
	 * ## OPTIONS
	 *
	 * <from>
	 * : The current URL of the blog.
	 *
	 * <to>
	 * : The new URL of the blog.
	 *
	 * ## EXAMPLES
	 *
	 *     # Move blog from old URL to new URL
	 *     $ wp activitypub move https://example.com/ https://newsite.com/
	 *
	 * @param array $args       The positional arguments.
	 * @param array $assoc_args The associative arguments (unused).
	 */
	public function __invoke( $args, $assoc_args ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		$from = $args[0];
		$to   = $args[1];

		$outbox_item_id = Move::account( $from, $to );

		if ( \is_wp_error( $outbox_item_id ) ) {
			\WP_CLI::error( $outbox_item_id->get_error_message() );
		} else {
			\WP_CLI::success( 'Move Scheduled.' );
		}
	}
}
