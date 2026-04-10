# Bot Account

Marks ActivityPub profiles as bot or automated accounts. When active, profiles display with a "BOT" badge on Mastodon and other Fediverse platforms.

## How It Works

The snippet filters the ActivityPub actor object to change the actor `type` from `Person` to `Service`, which is how the Fediverse represents bot/automated accounts.

- **User actors** are always changed to `Service`.
- **Blog actor** is only changed to `Service` in single-user mode. In multi-user mode the blog actor remains a `Group` to preserve [FEP-1b12](https://codeberg.org/fediverse/fep/src/branch/main/fep/1b12/fep-1b12.md) federation.

## Installation

Copy this folder to `wp-content/plugins/` and activate **Bot Account** from the WordPress admin, or copy `bot-account.php` to `wp-content/mu-plugins/` for automatic activation.

## Requirements

- WordPress 5.9+
- PHP 7.4+
- [ActivityPub](https://wordpress.org/plugins/activitypub/) plugin

## Customization

To apply the bot type to only the blog actor or only user actors, remove the corresponding `add_filter` line from `bot-account.php`.
