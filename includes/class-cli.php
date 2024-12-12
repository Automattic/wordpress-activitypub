<?php
/**
 * WP-CLI file.
 *
 * @package Activitypub
 */

namespace Activitypub;

use WP_CLI;
use WP_CLI_Command;
use WP_CLI\Utils;

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

	/**
	 * Generates some number of new dummy ActivityPub reactions.
	 *
	 * Creates a specified number of new ActivityPub reactions with dummy data.
	 *
	 * ## OPTIONS
	 *
	 * <action>
	 * : The action to perform.
	 * ---
	 * options:
	 *   - comments
	 * ---
	 *
	 * [--count=<number>]
	 * : How many ActivityPub reactions to generate?
	 * ---
	 * default: 100
	 * ---
	 *
	 * [--post_id=<post-id>]
	 * : Assign ActivityPub reactions to a specific post.
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: progress
	 * options:
	 *   - progress
	 *   - ids
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # Generate comments for the given post.
	 *     $ wp activitypub generate comments --format=ids --count=3 --post_id=123
	 *     138 139 140
	 *
	 * @param array $args     The arguments.
	 * @param array $assoc_args The associative arguments.
	 */
	public function generate( $args, $assoc_args ) {
		switch ( $args[0] ) {
			case 'comments':
				$this->generate_comments( $args, $assoc_args );
				break;
			default:
				WP_CLI::error( 'Unknown action.' );
		}
	}

	/**
	 * Generate demo comments and reactions.
	 *
	 * @param array $args The arguments.
	 * @param array $assoc_args The associative arguments.
	 */
	private function generate_comments( $args, $assoc_args ) {
		$defaults = array(
			'count'   => 100,
			'post_id' => 0,
		);

		$assoc_args = array_merge( $defaults, $assoc_args );

		$format = Utils\get_flag_value( $assoc_args, 'format', 'progress' );

		$notify = false;
		if ( 'progress' === $format ) {
			$notify = Utils\make_progress_bar( 'Generating comments', $assoc_args['count'] );
		}

		$comment_count = wp_count_comments();
		$total         = (int) $comment_count->total_comments;
		$limit         = $total + $assoc_args['count'];

		for ( $index = $total; $index < $limit; $index++ ) {
			$comment_types   = Comment::get_comment_type_names();
			$comment_types[] = 'comment';

			$comment_type = $comment_types[ array_rand( $comment_types ) ];

			$comment_id = wp_insert_comment(
				array(
					'comment_content' => "{$comment_type} {$index}",
					'comment_post_ID' => $assoc_args['post_id'],
					'comment_type'    => $comment_type,
					'comment_meta'    => array(
						'protocol'   => 'activitypub',
						'avatar_url' => "https://i.pravatar.cc/80?u={$index}",
						'source_id'  => "https://example.org/canonical/{$index}",
					),
				)
			);
			if ( 'progress' === $format ) {
				$notify->tick();
			} elseif ( 'ids' === $format ) {
				echo esc_attr( $comment_id );
				if ( $index < $limit - 1 ) {
					echo ' ';
				}
			}
		}

		if ( 'progress' === $format ) {
			$notify->finish();
		}
	}
}
