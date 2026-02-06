<?php
/**
 * WP-CLI commands registration.
 *
 * @package Activitypub
 */

namespace Activitypub;

/**
 * ActivityPub CLI command registry.
 *
 * Registers all ActivityPub CLI subcommands with WP-CLI.
 *
 * @package Activitypub
 */
class Cli {

	/**
	 * Register all ActivityPub CLI commands.
	 *
	 * This method registers the main 'activitypub' command namespace and all its
	 * subcommands for managing ActivityPub functionality via WP-CLI.
	 *
	 * Available commands:
	 * - wp activitypub post <delete|update> <id>
	 * - wp activitypub comment <delete|update> <id>
	 * - wp activitypub actor <delete|update> <id>
	 * - wp activitypub outbox <undo|reschedule> <id>
	 * - wp activitypub self-destruct [--status] [--yes]
	 * - wp activitypub move <from> <to>
	 * - wp activitypub follow <remote_user>
	 */
	public static function register() {
		// Register parent command with version subcommand.
		\WP_CLI::add_command(
			'activitypub',
			'\Activitypub\Cli\Command',
			array(
				'shortdesc' => 'Manage ActivityPub plugin functionality and federation.',
			)
		);

		\WP_CLI::add_command(
			'activitypub post',
			'\Activitypub\Cli\Post_Command',
			array(
				'shortdesc' => 'Manage ActivityPub posts (delete or update).',
			)
		);

		\WP_CLI::add_command(
			'activitypub comment',
			'\Activitypub\Cli\Comment_Command',
			array(
				'shortdesc' => 'Manage ActivityPub comments (delete or update).',
			)
		);

		\WP_CLI::add_command(
			'activitypub actor',
			'\Activitypub\Cli\Actor_Command',
			array(
				'shortdesc' => 'Manage ActivityPub actors (delete or update).',
			)
		);

		\WP_CLI::add_command(
			'activitypub outbox',
			'\Activitypub\Cli\Outbox_Command',
			array(
				'shortdesc' => 'Manage ActivityPub outbox items (undo or reschedule).',
			)
		);

		\WP_CLI::add_command(
			'activitypub self-destruct',
			'\Activitypub\Cli\Self_Destruct_Command',
			array(
				'shortdesc' => 'Remove the entire blog from the Fediverse.',
			)
		);

		\WP_CLI::add_command(
			'activitypub move',
			'\Activitypub\Cli\Move_Command',
			array(
				'shortdesc' => 'Move the blog to a new URL.',
			)
		);

		\WP_CLI::add_command(
			'activitypub follow',
			'\Activitypub\Cli\Follow_Command',
			array(
				'shortdesc' => 'Follow a remote ActivityPub user.',
			)
		);
	}
}
