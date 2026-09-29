# WP-CLI commands

The plugin registers its commands under `wp activitypub`. They need [WP-CLI](https://wp-cli.org/) and a working site; run them from the WordPress root or pass `--path`.

Commands that act for a specific account take the account from WP-CLI's global `--user` flag, for example `wp --user=alice activitypub follow …`. Without it they act as the blog actor.

`wp help activitypub <command>` prints the same information as this file, generated from the command's docblock.

| Command | What it does |
| --- | --- |
| `wp activitypub version` | Print the plugin version. |
| `wp activitypub post <delete\|update> <id>` | Send a Delete or Update for a post to the Fediverse. |
| `wp activitypub comment <delete\|update> <id>` | Send a Delete or Update for a comment. |
| `wp activitypub actor <delete\|update> <id>` | Send a Delete or Update for an actor (a user). |
| `wp activitypub outbox <undo\|reschedule> <id>` | Undo a sent activity, or send it again. |
| `wp activitypub follow <remote_user>` | Follow a remote account. |
| `wp activitypub move <from> <to>` | Move the blog to a new URL. |
| `wp activitypub self-destruct` | Remove the blog from the Fediverse. |
| `wp activitypub fetch <url>` | Fetch a remote ActivityPub URL with a signed request, for debugging. |
| `wp activitypub cache <clear\|status\|cleanup>` | Manage the cache of remote images. |
| `wp activitypub stats <collect\|compile\|send>` | Collect, compile and mail statistics. |
| `wp activitypub blurhash backfill` | Compute placeholder hashes for existing images. |

## Posts, comments and actors

```
wp activitypub post delete <id> [--yes]
wp activitypub post update <id>
wp activitypub comment delete <id> [--yes]
wp activitypub comment update <id>
wp activitypub actor delete <id>
wp activitypub actor update <id>
```

`delete` sends a `Delete` activity for the object, `update` sends an `Update` with its current state. For posts `<id>` is a post, page, custom post type or attachment ID; for actors it is the user ID. Deleting asks for confirmation; `--yes` skips that.

```
$ wp activitypub post update 123
$ wp activitypub comment delete 123 --yes
$ wp activitypub actor update 1
```

## Outbox

```
wp activitypub outbox undo <id>
wp activitypub outbox reschedule <id>
```

`<id>` is the ID or the URL of an outbox item. `undo` sends an `Undo` for an activity that already went out; `reschedule` queues it for sending again.

```
$ wp activitypub outbox undo 123
$ wp activitypub outbox reschedule "https://example.com/?post_type=ap_outbox&p=123"
```

## Follow

```
wp activitypub follow <remote_user>
```

Follows a remote account, given as a URL or as `@user@domain`. Use `--user` to follow from a specific account.

```
$ wp activitypub follow https://example.com/@user
$ wp --user=alice activitypub follow @user@example.com
```

## Move

```
wp activitypub move <from> <to>
```

Moves the blog from its current URL to a new one and tells followers. See [the account migration guide](../../docs/how-to/account-migration.md) for the whole procedure.

```
$ wp activitypub move https://example.com/ https://newsite.com/
```

## Self-destruct

```
wp activitypub self-destruct [--status] [--yes]
```

Sends `Delete` activities for the blog and its actors to all followers and removes the blog from the Fediverse. This cannot be undone. The process runs in the background; `--status` reports how far it has got. It asks for confirmation unless `--yes` is passed, which skips every safety check.

```
$ wp activitypub self-destruct
$ wp activitypub self-destruct --status
```

## Fetch

```
wp activitypub fetch <url> [--signature=<mode>] [--raw] [--include-headers]
```

Fetches a remote ActivityPub URL with a signed HTTP request, the way the plugin does when it talks to another server. Useful for debugging HTTP Signatures and federation problems.

`--signature` picks how the request is signed: `default` (what the plugin is configured to use), `draft-cavage`, `rfc9421`, `double-knock` (RFC 9421 first, draft-cavage again when the server answers 4xx) or `none`. `--raw` prints the body as received, `--include-headers` prints the response headers as well.

```
$ wp activitypub fetch https://mastodon.social/@Gargron
$ wp activitypub fetch https://mastodon.social/@Gargron --signature=rfc9421 --include-headers
```

## Cache

```
wp activitypub cache status [--format=<format>]
wp activitypub cache clear [--type=<type>] [--yes]
wp activitypub cache cleanup [--type=<type>] [--delete]
```

The plugin caches remote images (avatars, post media, custom emoji) in the uploads directory. `--type` is `avatar`, `media`, `emoji` or `all` (the default).

`status` lists file counts and sizes per cache type; `--format` is `table`, `json`, `csv` or `yaml`.

`clear` removes the cached files of the given type; they are downloaded again when needed. It asks for confirmation unless `--yes` is passed.

`cleanup` finds the duplicate copies (`<hash>-1.webp`, `<hash>-2.webp`, …) that earlier versions could leave next to a cached image, and removes them with `--delete`. Without `--delete` it only reports what it would remove. When the original file is missing, the newest copy is kept under the original name instead of being removed.

```
$ wp activitypub cache status --format=json
$ wp activitypub cache clear --type=avatar --yes
$ wp activitypub cache cleanup
$ wp activitypub cache cleanup --type=avatar --delete
```

## Stats

```
wp activitypub stats collect [--user_id=<user_id>] [--year=<year>] [--month=<month>] [--force]
wp activitypub stats compile [--user_id=<user_id>] [--year=<year>]
wp activitypub stats send    [--user_id=<user_id>] [--year=<year>] [--month=<month>]
```

`collect` gathers the monthly statistics (defaults to the current month; `--force` recollects a month that already has them), `compile` builds the annual statistics from the months (defaults to the previous year), `send` mails the report: the annual one, or the monthly one when `--month` is given. Without `--user_id` each runs for every active user.

```
$ wp activitypub stats collect --year=2024 --month=6
$ wp activitypub stats compile --year=2024
$ wp activitypub stats send --user_id=1 --year=2025 --month=6
```

## Blurhash

```
wp activitypub blurhash backfill [--dry-run] [--limit=<n>] [--force]
```

Computes and stores Blurhash placeholders for image attachments that don't have one yet. `--dry-run` reports what would be encoded without writing anything, `--limit` stops after `<n>` attachments, `--force` re-encodes attachments that already have a hash.

## Adding a command

Each command is one class in this directory, `class-<name>-command.php`, registered in `includes/class-cli.php` with `\WP_CLI::add_command( 'activitypub <name>', <Name>_Command::class )`. A class with several subcommands has one public method per subcommand, each tagged `@subcommand`; a class that is one command implements `__invoke()`.

WP-CLI builds the `--help` output from the method's docblock, so the docblock is the documentation: a one-line summary, a longer description, an `## OPTIONS` block (`<arg>` for positional arguments, `[--flag]` and `[--option=<value>]` for optional ones, each followed by a line starting with `:`), and an `## EXAMPLES` block. When you add or change a command, update the table and the section above as well.
