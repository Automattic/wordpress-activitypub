# FediBlog Tag

Automatically adds the `FediBlog` tag to standard format blog posts when they are saved.

## What It Does

[FediBlog](https://fediblog.net/) is a directory of blogs in the Fediverse. This snippet automatically tags your standard format blog posts with `FediBlog`, making them discoverable on platforms that aggregate content by this tag.

The snippet hooks into WordPress' `save_post` action and:

1. Checks that the post is not a revision.
2. Checks that the post uses the standard format (no specific post format assigned).
3. Adds the `FediBlog` tag to the post without removing existing tags.

## Installation

Copy this folder to `wp-content/plugins/` and activate **FediBlog Tag** from the WordPress admin, or copy `fediblog-tag.php` to `wp-content/mu-plugins/` for automatic activation.

## Requirements

- WordPress 5.9+
- PHP 7.4+
- [ActivityPub](https://wordpress.org/plugins/activitypub/) plugin

## Origin

Based on a [suggestion by Tim Chambers](https://indieweb.social/@tchambers/114197659039490619) to nudge blog authors into adding the `#FediBlog` hashtag to help with discovery of blogs in the Fediverse.
