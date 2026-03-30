# Auto-Approve Reactions

Automatically approves all incoming ActivityPub reactions (likes, reposts, and quotes) without requiring manual moderation.

## How It Works

The snippet hooks into WordPress's `pre_comment_approved` filter to automatically approve any incoming ActivityPub comment that has a reaction type (like, repost, or quote). It runs before the core plugin's approval logic, so reactions are approved regardless of the "Auto approve reactions" setting in the plugin's settings page.

Only ActivityPub comments are affected. Regular WordPress comments continue to follow your normal moderation rules.

## When to Use

This is useful if you want to:

- Accept all reactions from the Fediverse without reviewing them first.
- Skip the moderation queue for likes and reposts while keeping comments moderated.

## Installation

Copy this folder to `wp-content/plugins/` and activate **Auto-Approve Reactions** from the WordPress admin, or copy `auto-approve-reactions.php` to `wp-content/mu-plugins/` for automatic activation.

## Requirements

- WordPress 5.9+
- PHP 7.4+
- [ActivityPub](https://wordpress.org/plugins/activitypub/) plugin
