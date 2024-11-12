<?php
/**
 * WP-CLI file.
 *
 * @package Activitypub
 */

namespace Activitypub;

use WP_CLI;
use WP_CLI_Command;

/**
 * WP-CLI commands.
 *
 * @package Activitypub
 */
class Cli extends WP_CLI_Command {

	/**
	 * Remove the entire blog from the Fediverse.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp activitypub self-destruct
	 *
	 * @param array|null $args       The arguments.
	 * @param array|null $assoc_args The associative arguments.
	 *
	 * @return void
	 */
	public function self_destruct( $args, $assoc_args ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		WP_CLI::warning( 'Self-Destructing is not implemented yet.' );
	}

	/**
	 * Delete or Update a Post, Page, Custom Post Type or Attachment.
	 *
	 * ## OPTIONS
	 *
	 * <action>
	 * : The action to perform. Either `delete` or `update`.
	 * ---
	 * options:
	 *   - delete
	 *   - update
	 * ---
	 *
	 * <id>
	 * : The id of the Post, Page, Custom Post Type or Attachment.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp activitypub post delete 1
	 *
	 * @synopsis <action> <id>
	 *
	 * @param array $args The arguments.
	 */
	public function post( $args ) {
		$post = get_post( $args[1] );

		if ( ! $post ) {
			WP_CLI::error( 'Post not found.' );
		}

		switch ( $args[0] ) {
			case 'delete':
				WP_CLI::confirm( 'Do you really want to delete the (Custom) Post with the ID: ' . $args[1] );
				Scheduler::schedule_post_activity( 'trash', 'publish', $args[1] );
				WP_CLI::success( '"Delete" activity is queued.' );
				break;
			case 'update':
				Scheduler::schedule_post_activity( 'publish', 'publish', $args[1] );
				WP_CLI::success( '"Update" activity is queued.' );
				break;
			default:
				WP_CLI::error( 'Unknown action.' );
		}
	}

	/**
	 * Delete or Update a Comment.
	 *
	 * ## OPTIONS
	 *
	 * <action>
	 * : The action to perform. Either `delete` or `update`.
	 * ---
	 * options:
	 *   - delete
	 *   - update
	 * ---
	 *
	 * <id>
	 * : The id of the Comment.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp activitypub comment delete 1
	 *
	 * @synopsis <action> <id>
	 *
	 * @param array $args The arguments.
	 */
	public function comment( $args ) {
		$comment = get_comment( $args[1] );

		if ( ! $comment ) {
			WP_CLI::error( 'Comment not found.' );
		}

		if ( was_comment_received( $comment ) ) {
			WP_CLI::error( 'This comment was received via ActivityPub and cannot be deleted or updated.' );
		}

		switch ( $args[0] ) {
			case 'delete':
				WP_CLI::confirm( 'Do you really want to delete the Comment with the ID: ' . $args[1] );
				Scheduler::schedule_comment_activity( 'trash', 'approved', $args[1] );
				WP_CLI::success( '"Delete" activity is queued.' );
				break;
			case 'update':
				Scheduler::schedule_comment_activity( 'approved', 'approved', $args[1] );
				WP_CLI::success( '"Update" activity is queued.' );
				break;
			default:
				WP_CLI::error( 'Unknown action.' );
		}
	}
}
