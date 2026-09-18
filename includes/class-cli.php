<?php
/**
 * WP-CLI commands registration.
 *
 * @package Activitypub
 */

namespace Activitypub;

use Activitypub\Cli\Actor_Command;
use Activitypub\Cli\Blurhash_Command;
use Activitypub\Cli\Cache_Command;
use Activitypub\Cli\Command;
use Activitypub\Cli\Comment_Command;
use Activitypub\Cli\Fetch_Command;
use Activitypub\Cli\Follow_Command;
use Activitypub\Cli\Move_Command;
use Activitypub\Cli\Outbox_Command;
use Activitypub\Cli\Post_Command;
use Activitypub\Cli\Self_Destruct_Command;
use Activitypub\Cli\Stats_Command;

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
	 * - wp activitypub cache <clear|status> [--type=<type>]
	 * - wp activitypub self-destruct [--status] [--yes]
	 * - wp activitypub move <from> <to>
	 * - wp activitypub follow <remote_user>
	 * - wp activitypub stats <collect|compile|send>
	 * - wp activitypub fetch <url>
	 * - wp activitypub blurhash backfill [--dry-run] [--limit=<n>] [--force]
	 */
	public static function register() {
		// Register parent command with version subcommand.
		\WP_CLI::add_command(
			'activitypub',
			Command::class,
			array(
				'shortdesc' => 'Manage ActivityPub plugin functionality and federation.',
			)
		);

		\WP_CLI::add_command(
			'activitypub post',
			Post_Command::class,
			array(
				'shortdesc' => 'Manage ActivityPub posts (delete or update).',
			)
		);

		\WP_CLI::add_command(
			'activitypub comment',
			Comment_Command::class,
			array(
				'shortdesc' => 'Manage ActivityPub comments (delete or update).',
			)
		);

		\WP_CLI::add_command(
			'activitypub actor',
			Actor_Command::class,
			array(
				'shortdesc' => 'Manage ActivityPub actors (delete or update).',
			)
		);

		\WP_CLI::add_command(
			'activitypub outbox',
			Outbox_Command::class,
			array(
				'shortdesc' => 'Manage ActivityPub outbox items (undo or reschedule).',
			)
		);

		\WP_CLI::add_command(
			'activitypub self-destruct',
			Self_Destruct_Command::class,
			array(
				'shortdesc' => 'Remove the entire blog from the Fediverse.',
			)
		);

		\WP_CLI::add_command(
			'activitypub move',
			Move_Command::class,
			array(
				'shortdesc' => 'Move the blog to a new URL.',
			)
		);

		\WP_CLI::add_command(
			'activitypub follow',
			Follow_Command::class,
			array(
				'shortdesc' => 'Follow a remote ActivityPub user.',
			)
		);

		\WP_CLI::add_command(
			'activitypub cache',
			Cache_Command::class,
			array(
				'shortdesc' => 'Manage remote media cache (clear or show status).',
			)
		);

		\WP_CLI::add_command(
			'activitypub fetch',
			Fetch_Command::class,
			array(
				'shortdesc' => 'Fetch a remote URL with a signed ActivityPub request.',
			)
		);

		\WP_CLI::add_command(
			'activitypub stats',
			Stats_Command::class,
			array(
				'shortdesc' => 'Manage ActivityPub statistics (collect, compile or send).',
			)
		);

		\WP_CLI::add_command(
			'activitypub blurhash',
			Blurhash_Command::class,
			array(
				'shortdesc' => 'Backfill Blurhash placeholders for image attachments.',
			)
		);
	}
}
