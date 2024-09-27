<?php
namespace Activitypub;

use WP_CLI;
use WP_CLI_Command;
use Activitypub\Scheduler;

use function Activitypub\was_comment_received;

/**
 * WP-CLI commands
 *
 * @package Activitypub
 */
class Cli extends WP_CLI_Command {
	/**
	 * Check the Plugins Meta-Informations
	 *
	 * ## OPTIONS
	 *
	 * [--Name]
	 * The Plugin Name
	 *
	 * [--PluginURI]
	 * The Plugin URI
	 *
	 * [--Version]
	 * The Plugin Version
	 *
	 * [--Description]
	 * The Plugin Description
	 *
	 * [--Author]
	 * The Plugin Author
	 *
	 * [--AuthorURI]
	 * The Plugin Author URI
	 *
	 * [--TextDomain]
	 * The Plugin Text Domain
	 *
	 * [--DomainPath]
	 * The Plugin Domain Path
	 *
	 * [--Network]
	 * The Plugin Network
	 *
	 * [--RequiresWP]
	 * The Plugin Requires at least
	 *
	 * [--RequiresPHP]
	 * The Plugin Requires PHP
	 *
	 * [--UpdateURI]
	 * The Plugin Update URI
	 *
	 * See: https://developer.wordpress.org/reference/functions/get_plugin_data/#return
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp webmention meta
	 *
	 *     $ wp webmention meta --Version
	 *     Version: 1.0.0
	 *
	 * @param array|null $args       The arguments.
	 * @param array|null $assoc_args The associative arguments.
	 *
	 * @return void
	 */
	public function meta( $args, $assoc_args ) {
		$plugin_data = get_plugin_meta();

		if ( $assoc_args ) {
			$plugin_data = array_intersect_key( $plugin_data, $assoc_args );
		} else {
			WP_CLI::line( __( "ActivityPub Plugin Meta:\n", 'activitypub' ) );
		}

		foreach ( $plugin_data as $key => $value ) {
			WP_CLI::line( $key . ':	' . $value );
		}
	}

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
		WP_CLI::warning( __( 'Self-Destructing is not implemented yet.', 'activitypub' ) );
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
	 * @param array|null $args       The arguments.
	 * @param array|null $assoc_args The associative arguments.
	 *
	 * @return void
	 */
	public function post( $args, $assoc_args ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		$post = get_post( $args[1] );

		if ( ! $post ) {
			WP_CLI::error( __( 'Post not found.', 'activitypub' ) );
		}

		switch ( $args[0] ) {
			case 'delete':
				// translators: %s is the ID of the post.
				WP_CLI::confirm( sprintf( __( 'Do you really want to delete the (Custom) Post with the ID: %s', 'activitypub' ), $args[1] ) );
				Scheduler::schedule_post_activity( 'trash', 'publish', $args[1] );
				WP_CLI::success( __( '"Delete"-Activity is queued.', 'activitypub' ) );
				break;
			case 'update':
				Scheduler::schedule_post_activity( 'publish', 'publish', $args[1] );
				WP_CLI::success( __( '"Update"-Activity is queued.', 'activitypub' ) );
				break;
			default:
				WP_CLI::error( __( 'Unknown action.', 'activitypub' ) );
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
	 * @param array|null $args       The arguments.
	 * @param array|null $assoc_args The associative arguments.
	 *
	 * @return void
	 */
	public function comment( $args, $assoc_args ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		$comment = get_comment( $args[1] );

		if ( ! $comment ) {
			WP_CLI::error( __( 'Comment not found.', 'activitypub' ) );
		}

		if ( was_comment_received( $comment ) ) {
			WP_CLI::error( __( 'This comment was received via ActivityPub and cannot be deleted or updated.', 'activitypub' ) );
		}

		switch ( $args[0] ) {
			case 'delete':
				// translators: %s is the ID of the comment.
				WP_CLI::confirm( sprintf( __( 'Do you really want to delete the Comment with the ID: %s', 'activitypub' ), $args[1] ) );
				Scheduler::schedule_comment_activity( 'trash', 'approved', $args[1] );
				WP_CLI::success( __( '"Delete"-Activity is queued.', 'activitypub' ) );
				break;
			case 'update':
				Scheduler::schedule_comment_activity( 'approved', 'approved', $args[1] );
				WP_CLI::success( __( '"Update"-Activity is queued.', 'activitypub' ) );
				break;
			default:
				WP_CLI::error( __( 'Unknown action.', 'activitypub' ) );
		}
	}
}
