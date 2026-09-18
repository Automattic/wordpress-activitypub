---
name: integrations
description: Third-party WordPress plugin integration patterns. Use when adding new integrations, debugging compatibility with other plugins, or working with existing integrations.
---

# ActivityPub Integrations

This skill provides guidance on integrating the ActivityPub plugin with other WordPress plugins.

## Quick Reference

### Integration Location
All integrations live in the `integration/` directory.

**File naming:** `class-{plugin-name}.php` (following PHP conventions in AGENTS.md)

**Namespace:** `Activitypub\Integration`

### How Integrations Load
Integrations are wired up in `integration/load.php` inside `plugin_init()`. Each one is guarded by a detection check and then calls its class's static `init()`:

```php
// integration/load.php
if ( \defined( 'JETPACK__VERSION' ) ) {
    Jetpack::init();
}
```

### Existing Integrations
The real integrations shipped today (see `integration/`):

`akismet`, `buddypress`, `classic-editor`, `enable-mastodon-apps`, `jetpack`,
`litespeed-cache`, `multisite-language-switcher`, `nodeinfo`, `opengraph`,
`podlove-podcast-publisher`, `seriously-simple-podcasting`, `surge`, `webfinger`,
`wpml`, `yoast-seo`.

A few examples:
- **Akismet** — runs inbound comments/interactions through Akismet spam checks.
- **OpenGraph** — adds `fediverse:creator` and related metadata via the OpenGraph plugin.
- **Enable Mastodon Apps** — lets Mastodon client apps talk to the site.
- **Caches (LiteSpeed Cache, Surge)** — keep ActivityPub responses cacheable/uncached correctly.
- **Multilingual (WPML, Multisite Language Switcher)** — language-aware actor and content handling.

For complete directory structure and naming conventions, see `docs/php-class-structure.md`.

## Creating New Integration

### Basic Integration Class

```php
<?php
namespace Activitypub\Integration;

class Plugin_Name {
    /**
     * Initialize the class, registering WordPress hooks.
     */
    public static function init() {
        // Enable federation for the plugin's post type.
        \add_post_type_support( 'plugin_post_type', 'activitypub' );

        // Provide a custom transformer if the default Post transformer is not enough.
        \add_filter( 'activitypub_transformer', array( self::class, 'transformer' ), 10, 3 );
    }

    /**
     * Return a custom transformer for the plugin's objects.
     *
     * @param mixed  $transformer  The transformer to use. Default null.
     * @param mixed  $data         The object to transform.
     * @param string $object_class The class of the object to transform.
     * @return mixed
     */
    public static function transformer( $transformer, $data, $object_class ) {
        if ( 'WP_Post' === $object_class && 'plugin_post_type' === $data->post_type ) {
            require_once __DIR__ . '/class-custom-transformer.php';
            return new Custom_Transformer( $data );
        }
        return $transformer;
    }
}
```

Then register it in `integration/load.php`:

```php
if ( \defined( 'PLUGIN_VERSION' ) ) {
    Plugin_Name::init();
}
```

## Integration Patterns

### Enabling Federation for a Post Type

Post-type support drives which content federates. Use `add_post_type_support()` — there is no `activitypub_post_types` filter:

```php
\add_post_type_support( 'event', 'activitypub' );
\add_post_type_support( 'product', 'activitypub' );
```

The enabled post types are stored in the `activitypub_support_post_types` option (read with `\get_option( 'activitypub_support_post_types', array( 'post' ) )`).

### Custom Transformers

The `activitypub_transformer` filter takes **three** arguments — `( $transformer, $data, $object_class )` — and is registered with priority `10, 3`:

```php
\add_filter( 'activitypub_transformer', function ( $transformer, $data, $object_class ) {
    if ( 'WP_Post' === $object_class && 'event' === $data->post_type ) {
        return new Event_Transformer( $data );
    }
    return $transformer;
}, 10, 3 );
```

### Modifying the Activity Object

To tweak the final ActivityStreams array, hook `activitypub_activity_object_array` — `( $array, $class, $id, $object )`:

```php
\add_filter( 'activitypub_activity_object_array', function ( $array, $class, $id, $object ) {
    if ( isset( $array['type'] ) && 'Note' === $array['type'] ) {
        // Adjust the outgoing object array here.
    }
    return $array;
}, 10, 4 );
```

## Testing Integrations

### Verify Integration Loading

```php
// Check if integration is active.
if ( \class_exists( '\Activitypub\Integration\Plugin_Name' ) ) {
    // Integration loaded.
}
```

### Test Compatibility

1. Install target plugin
2. Activate ActivityPub
3. Check for conflicts
4. Verify custom post types federate
5. Test federation of plugin content

## Common Integration Issues

### Plugin Detection
```php
// Multiple detection methods (match how integration/load.php guards each one).
if ( \defined( 'PLUGIN_VERSION' ) ) { }
if ( \function_exists( 'plugin_function' ) ) { }
if ( \class_exists( 'Plugin_Class' ) ) { }
```

### Hook Priority
```php
// Use appropriate priority.
\add_filter( 'hook', 'callback', 20 ); // After plugin's filter.
```

### Namespace Conflicts
```php
// Use fully qualified names.
$object = new \Plugin\Namespace\Class();
```

## Best Practices

1. **Always guard the integration** with a detection check in `integration/load.php` before calling `init()`.
2. **Use late priority** for filters when you need to override another plugin's defaults.
3. **Test with multiple plugin versions.**
4. **Document compatibility requirements.**
5. **Handle plugin deactivation gracefully.**
