# Locale from Tags

Sets a post's ActivityPub language based on post tags that match language codes.

If you write in multiple languages and tag your posts with a language identifier (e.g. `en`, `fr`, `de`), this snippet ensures the correct language is set in the ActivityPub representation of your post. This helps federated platforms display accurate language metadata and enables automatic translation features.

When no matching language tag is found, the site's default locale is used.

## Installation

Copy this folder to `wp-content/plugins/` and activate **Locale from Tags** from the WordPress admin, or copy `locale-from-tags.php` to `wp-content/mu-plugins/` for automatic activation.

## Requirements

- WordPress 5.9+
- PHP 7.4+
- [ActivityPub](https://wordpress.org/plugins/activitypub/) plugin

## Configuration

By default, the snippet looks for these language code tags: `en`, `fr`, `de`, `es`, `it`, `pt`, `nl`, `ja`, `zh`, `ko`.

To customize the list, use the `activitypub_snippet_locale_from_tags_codes` filter:

```php
add_filter(
	'activitypub_snippet_locale_from_tags_codes',
	function () {
		return array( 'en', 'fr', 'hu' );
	}
);
```

## Origin

Based on a [blog post by Jeremy Herve](https://herve.bzh/wordpress-set-a-posts-language-in-its-activitypub-representation-based-on-post-tags/).
