# Allow ActivityPub through Disable WP REST API

Keeps ActivityPub endpoints available to other Fediverse servers while the free [Disable WP REST API](https://wordpress.org/plugins/disable-wp-rest-api/) plugin restricts other REST API requests.

## Installation

1. Install and activate **ActivityPub** and **Disable WP REST API**.
2. Copy this folder to `wp-content/plugins/`.
3. Activate **Allow ActivityPub through Disable WP REST API** under **Plugins**.

Alternatively, copy `disable-wp-rest-api.php` to `wp-content/mu-plugins/` for automatic activation. This is a separate, opt-in plugin; no changes to either existing plugin are needed.

## How it works

The snippet uses `disable_wp_rest_api_server_var` to allow the current request only when WordPress resolves it to the ActivityPub REST namespace. It works with pretty permalinks, `?rest_route=...` URLs, and ActivityPub's built-in WebFinger and NodeInfo rewrites. Existing allowlist entries are preserved.

This exempts ActivityPub routes from the login requirement imposed by Disable WP REST API. It does not bypass ActivityPub's own permissions, OAuth authentication or HTTP signature checks, and it does not open unrelated routes such as `/wp/v2/users`.

The exception covers the ActivityPub namespace, not endpoints registered by separate WebFinger or NodeInfo plugins. It does not configure other REST-blocking plugins or REST Pro Tools.

## Requirements

- WordPress 6.5+
- PHP 7.4+
- [ActivityPub](https://wordpress.org/plugins/activitypub/)
- [Disable WP REST API](https://wordpress.org/plugins/disable-wp-rest-api/) (free version)

## Origin

Based on [this support discussion](https://wordpress.org/support/topic/conflicts-between-activitypub-and-disable-wp-rest-api/#post-19037585).
