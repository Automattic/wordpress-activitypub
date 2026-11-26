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
	 * [<to>]
	 * : The new URL of the blog. Required unless --cancel is given.
	 *
	 * [--cancel]
	 * : Cancel the move, so the blog no longer points at the new URL. Followers that
	 * already moved stay with the new account.
	 *
	 * ## EXAMPLES
	 *
	 *     # Move blog from old URL to new URL
	 *     $ wp activitypub move https://example.com/ https://newsite.com/
	 *
	 *     # Cancel the move of the blog
	 *     $ wp activitypub move https://example.com/ --cancel
	 *
	 * @param array $args       The positional arguments.
	 * @param array $assoc_args The associative arguments.
	 *
	 * @return void
	 */
	public function __invoke( $args, $assoc_args ) {
		$from = $args[0];

		if ( \WP_CLI\Utils\get_flag_value( $assoc_args, 'cancel', false ) ) {
			$result = Move::cancel( $from );

			if ( \is_wp_error( $result ) ) {
				\WP_CLI::error( $result->get_error_message() );
			}

			\WP_CLI::success( 'Move cancelled.' );
			return;
		}

		if ( empty( $args[1] ) ) {
			\WP_CLI::error( 'Give the new URL, or --cancel to cancel a move.' );
		}

		$outbox_item_id = Move::account( $from, $args[1] );

		if ( \is_wp_error( $outbox_item_id ) ) {
			\WP_CLI::error( $outbox_item_id->get_error_message() );
		} else {
			\WP_CLI::success( 'Move Scheduled.' );
		}
	}
}
