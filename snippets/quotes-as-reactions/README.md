# Quotes as Reactions

Displays ActivityPub quotes in the Reactions block instead of the comment list.

## How It Works

The ActivityPub plugin shows quotes as comments. A quote is a post of its own, with its own content, and others can reply to it. Likes and reposts are reactions: they are hidden from the comment list and the comment count, and shown as facepiles (avatar rows) in the Reactions block.

This snippet registers the `quote` comment type as a reaction again, so quotes are shown in the Reactions block and not counted as comments.

Replies to a quote are hidden together with the quote, because their parent is not in the comment list. They are still in the WordPress admin.

When the snippet is activated or deactivated, the comment counts are recalculated in the background.

## Installation

Copy this folder to `wp-content/plugins/` and activate **Quotes as Reactions** from the WordPress admin, or copy `quotes-as-reactions.php` to `wp-content/mu-plugins/` for automatic activation. As a must-use plugin, the comment counts are not recalculated, they are updated with the next comment on a post.

## Requirements

- WordPress 5.9+
- PHP 7.4+
- [ActivityPub](https://wordpress.org/plugins/activitypub/) plugin
