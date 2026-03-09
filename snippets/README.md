# Snippets

This folder contains community-contributed snippets that extend or customize the ActivityPub plugin. Each snippet is a small, self-contained WordPress plugin that hooks into the ActivityPub plugin to add or modify functionality.

Think of snippets as a testing ground, similar to WordPress' [feature plugin](https://make.wordpress.org/core/handbook/about/release-cycle/features-as-plugins/) concept. Experimental ideas are developed here, and mature, valuable ones may eventually be integrated into the main plugin.

## Available Snippets

| Snippet | Description |
|---------|-------------|
| [Bot Account](bot-account/) | Marks ActivityPub profiles as bot/automated accounts, displaying a "BOT" badge in the Fediverse. |
| [FediBlog Tag](fediblog-tag/) | Automatically adds the `FediBlog` tag to standard format blog posts. |
| [Locale from Tags](locale-from-tags/) | Sets a post's ActivityPub language based on post tags matching language codes. |
| [Blockless ActivityPub](blockless-activitypub/) | Fediverse reactions without all that frontend-rendered but JavaScript-heavy blocks magic. |
| [Use Jetpack's Site Accelerator CDN (Photon) for Remote Media](photon/) | Rewrites ActivityPub remote media URLs through Jetpack's free image CDN instead of caching files locally. |
| [ATproto DID for Bridgy Fed](atproto-did-for-bridgy-fed/) | Allows you to serve an ATproto DID from your blog's `.well-known` directory to allow Bridgy Fed to use your blog's hostname as its Bluesky handle. |

## How to Use

1. Copy the snippet folder you want to use into your `wp-content/plugins/` directory.
2. Activate the snippet plugin from the WordPress admin under **Plugins**.
3. The snippet will require the ActivityPub plugin to be active.

Alternatively, you can copy the snippet's main PHP file directly into your `wp-content/mu-plugins/` directory for automatic activation.

## How to Contribute

We welcome new snippets! To contribute:

1. **Create a new folder** in `snippets/` with a descriptive, kebab-case name (e.g., `my-snippet-name/`).
2. **Add a main PHP file** with proper WordPress plugin headers, including `Requires Plugins: activitypub`.
3. **Use the `Activitypub\Snippets` namespace** to keep things consistent.
4. **Add a `README.md`** to your snippet folder explaining what it does, how it works, and any configuration options.
5. **Update this file** by adding your snippet to the "Available Snippets" table above.
6. **Submit a pull request** following the [Pull Request Guidelines](../docs/pull-request.md).

### Snippet Structure

Each snippet folder should contain at minimum:

```
snippets/
  my-snippet/
    my-snippet.php   # Main plugin file with WordPress plugin headers
    README.md        # Documentation for the snippet
```

### Plugin Header Template

```php
<?php
/**
 * Plugin Name:       My Snippet Name
 * Plugin URI:        https://github.com/Automattic/wordpress-activitypub
 * Description:       Brief description of what this snippet does.
 * Version:           1.0.0
 * Requires at least: 5.9
 * Requires PHP:      7.4
 * Author:            Your Name
 * Author URI:        https://example.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Requires Plugins:  activitypub
 */

namespace Activitypub\Snippets;
```

### Guidelines

- Keep snippets small and focused on a single feature or behavior.
- Use WordPress coding standards ([WPCS](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/)).
- Snippets must not break the core ActivityPub plugin when deactivated.
- Include inline documentation for hooks and filters.
