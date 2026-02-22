# Blockless ActivityPub

This snippet for ActivityPub allows showing the **Fediverse reactions** even on a theme that does not support blocks and aims to **remove the need for all that JavaScript** that comes with front-end rendered blocks.

Additionally it will also **disable "remote-reply"** which handles the federation of local comments on Fediverse reactions, but which also requires JavaScript to be downloaded and executed.

## Installation

Copy this folder to `wp-content/plugins/` and activate **Blockless ActivityPub** from the WordPress admin, or copy `blockless-activitypub.php` to `wp-content/mu-plugins/` for automatic activation.

## Requirements

- WordPress 5.9+
- PHP 7.4+
- [ActivityPub](https://wordpress.org/plugins/activitypub/) plugin

## Origin

Based on a [blog post by Frank Goosssens (aka Futtta and OptimizingMatters)](https://blog.futtta.be/2026/02/12/wordpress-activitypub-plugin-what-about-performance//).
