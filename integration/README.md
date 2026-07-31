# Integrations

This folder holds the plugin's **third-party compatibility layer** — small classes that make ActivityPub cooperate with other plugins and hosting features, without any of them having to know about each other.

## How they load

`load.php` runs on `plugins_loaded` (`plugin_init()`; BuddyPress hooks `bp_include` instead, because it loads later). For each integration it first checks that the companion plugin is present — a `defined()`, `class_exists()`, or `function_exists()` probe — and only then wires it up:

```php
if ( \defined( 'JETPACK__VERSION' ) ) {
    Jetpack::init();
}
```

A few integrations are always initialized (`Nodeinfo`, `Webfinger`, `Surge`, `Litespeed_Cache`, `Stream`) because they either target an always-available surface or do their own detection inside `init()`.

## Two patterns

1. **`::init()` classes** (most of them). A static `init()` registers the WordPress hooks the integration needs. Files are named `class-{name}.php`, namespaced `Activitypub\Integration`.

2. **Transformer swaps** (`Podlove Podcast Publisher`, `Seriously Simple Podcasting`). These hook the `activitypub_transformer` filter in `load.php` to return a subclass of `Activitypub\Transformer\Post` that overrides `get_attachment()`, so an episode federates with its audio enclosure and cover art.

## The integrations

### Federation standards

| Integration | What it does |
|---|---|
| **WebFinger** | Adds ActivityPub actor links to the [WebFinger](https://wordpress.org/plugins/webfinger/) plugin's responses. |
| **NodeInfo** | Advertises ActivityPub in the [NodeInfo](https://wordpress.org/plugins/nodeinfo/) / NodeInfo2 server metadata. |
| **OpenGraph** | Coordinates Open Graph output with the [OpenGraph](https://wordpress.org/plugins/opengraph/) plugin so tags aren't duplicated. |

### Fediverse & reader surfaces

| Integration | What it does |
|---|---|
| **Jetpack** | Syncs ActivityPub options and follower/following meta to WordPress.com, adds a Reader link on the Following screen, enables the Following UI, and adapts the "share to reply" flow. |
| **Enable Mastodon Apps** | Feeds ActivityPub account, follower, post, and notification data to [Enable Mastodon Apps](https://wordpress.org/plugins/enable-mastodon-apps/) so native Mastodon client apps work against the site. |
| **BuddyPress** | Maps BuddyPress member profiles into ActivityPub actors and adapts the Followers/Following blocks. |

### Content & publishing

| Integration | What it does |
|---|---|
| **Classic Editor** | Provides attachment/media extraction and editor UI for sites without the block editor. |
| **Podlove Podcast Publisher** | Federates Podlove episodes with their audio enclosure and cover art *(transformer)*. |
| **Seriously Simple Podcasting** | Federates Seriously Simple Podcasting episodes with their audio enclosure *(transformer)*. |

### Caching

| Integration | What it does |
|---|---|
| **LiteSpeed Cache** | Keeps the html and ActivityPub (JSON) responses in separate cache buckets, with a Site Health check. |
| **Surge** | Keeps the html and ActivityPub (JSON) responses in separate cache buckets, with a Site Health check. |

### Internationalization

| Integration | What it does |
|---|---|
| **WPML** | Supplies the correct per-object locale for federated content under WPML. |
| **Multisite Language Switcher** | Keeps ActivityPub data consistent across MSLS post translations. |

### Other

| Integration | What it does |
|---|---|
| **Akismet** | Keeps Akismet's comment handling sensible for federated interactions (likes, reposts, replies). |
| **Yoast SEO** | Adds a Site Health check for Yoast settings that can interfere with federation. |

## Adding an integration

1. Create `class-{name}.php` in this folder, namespaced `Activitypub\Integration`, with a static `init()` (or, for a content transformer, a `Post` subclass).
2. In `load.php`, add a guarded call — detect the companion plugin with `defined()` / `class_exists()` / `function_exists()`, then `init()` it (or register the transformer via the `activitypub_transformer` filter).
3. Add tests under `tests/phpunit/tests/integration/`.
