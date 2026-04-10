# Quotes as Comments

Displays ActivityPub quotes as regular comments instead of rendering them in the facepile reactions block.

## How It Works

The ActivityPub plugin treats likes, reposts, and quotes as special comment types that are excluded from the normal comment list and displayed as facepiles (avatar rows) instead. Unlike likes and reposts, quotes carry actual content, so it can make more sense to show them as full comments.

This snippet removes `quote` from the excluded comment types list, so quotes appear in the regular comment section alongside replies. Likes and reposts remain as facepiles.

Two hooks are used:

- **`pre_get_comments`** (priority 20) removes `quote` from `type__not_in` in the main comment query, so quotes show up on singular post pages.
- **`rest_comment_query`** (priority 20) does the same for the WordPress REST API comments endpoint.

## Installation

Copy this folder to `wp-content/plugins/` and activate **Quotes as Comments** from the WordPress admin, or copy `quotes-as-comments.php` to `wp-content/mu-plugins/` for automatic activation.

## Requirements

- WordPress 5.9+
- PHP 7.4+
- [ActivityPub](https://wordpress.org/plugins/activitypub/) plugin
