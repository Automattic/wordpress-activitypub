<?php
namespace Activitypub;

use WP_CLI;
use WP_CLI_Command;

/**
 * WP-CLI commands
 *
 * @package Activitypub
 */
class Cli extends WP_CLI_Command {
	/**
	 * Remove the blog from the Fediverse.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp activitypub self-destruct
	 *
	 * @subcommand self-destruct
	 *
	 * @param array|null $args       The arguments.
	 * @param array|null $assoc_args The associative arguments.
	 *
	 * @return void
	 */
	public function self_destruct( $args, $assoc_args ) {
		$question = __( 'We are in the process of deleting your blog from the Fediverse. This action could be irreversible, so are you sure you want to continue?', 'activitypub' );
		WP_CLI::confirm( WP_CLI::colorize( "%r{$question}%n" ), $assoc_args = array() );

		WP_CLI::success( __( 'Deleting your blog from the Fediverse...', 'activitypub' ) );
	}

	/**
	 * Delete or Update a User.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp activitypub user
	 *
	 * @param array|null $args       The arguments.
	 * @param array|null $assoc_args The associative arguments.
	 *
	 * @return void
	 */
	public function user( $args, $assoc_args ) {

	}
}
