<?php
/**
 * Post CLI Command.
 *
 * @package Activitypub
 */

namespace Activitypub\Cli;

use function Activitypub\add_to_outbox;

/**
 * Manage ActivityPub posts.
 *
 * @package Activitypub
 */
class Post_Command extends \WP_CLI_Command {

	/**
	 * Delete a Post from the Fediverse.
	 *
	 * Sends a Delete activity to all followers to remove the post from
	 * federated instances.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : The ID of the Post, Page, Custom Post Type or Attachment.
	 *
	 * [--yes]
	 * : Skip the confirmation prompt.
	 *
	 * ## EXAMPLES
	 *
	 *     # Delete post with ID 123
	 *     $ wp activitypub post delete 123
	 *
	 * @subcommand delete
	 *
	 * @param array $args       The positional arguments.
	 * @param array $assoc_args The associative arguments.
	 */
	public function delete( $args, $assoc_args ) {
		$post = \get_post( $args[0] );

		if ( ! $post ) {
			\WP_CLI::error( 'Post not found.' );
		}

		\WP_CLI::confirm( 'Do you really want to delete the (Custom) Post with the ID: ' . $args[0], $assoc_args );
		add_to_outbox( $post, 'Delete', $post->post_author );
		\WP_CLI::success( '"Delete" activity is queued.' );
	}

	/**
	 * Update a Post on the Fediverse.
	 *
	 * Sends an Update activity to all followers to refresh the post content
	 * on federated instances.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : The ID of the Post, Page, Custom Post Type or Attachment.
	 *
	 * ## EXAMPLES
	 *
	 *     # Update post with ID 123
	 *     $ wp activitypub post update 123
	 *
	 * @subcommand update
	 *
	 * @param array $args       The positional arguments.
	 * @param array $assoc_args The associative arguments (unused).
	 */
	public function update( $args, $assoc_args ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		$post = \get_post( $args[0] );

		if ( ! $post ) {
			\WP_CLI::error( 'Post not found.' );
		}

		add_to_outbox( $post, 'Update', $post->post_author );
		\WP_CLI::success( '"Update" activity is queued.' );
	}
}
