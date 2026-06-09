# Caching and Content Negotiation

The ActivityPub plugin uses content negotiation to serve two different responses from the same URL: HTML for browsers and JSON-LD for Fediverse servers. This is elegant and spec-compliant, but it conflicts with most caching setups, which assume one URL equals one response.

This guide explains the problem, how to configure your cache correctly, and when to disable content negotiation entirely.

## The problem

When a Fediverse server like Mastodon fetches one of your posts, it sends an `Accept: application/activity+json` header. The plugin detects this and returns a JSON-LD representation instead of the normal HTML page.

A caching layer that does not account for the `Accept` header will cache whichever response was generated first and serve it to everyone. This leads to one of two symptoms:

- **Browsers see raw JSON** instead of your website, because a Fediverse server requested the page first and the JSON response was cached.
- **Fediverse servers receive HTML** instead of JSON, because a browser visited first and the HTML response was cached. This breaks federation silently.

## The Vary header

The standard solution is the HTTP `Vary` header. Sending `Vary: Accept` tells caches to store separate versions of the response based on the `Accept` header the client sent.

The plugin sends `Vary: Accept` by default. You can toggle this under **Settings > ActivityPub > [Advanced](advanced-settings.md) > Vary Header**, or force it via `wp-config.php`:

```php
define( 'ACTIVITYPUB_SEND_VARY_HEADER', true );
```

Whether this actually works depends on your caching layer respecting the `Vary` header, which many do not.

## Compatible caching plugins

These WordPress caching plugins are known to work with the ActivityPub plugin:

| Plugin | How it works |
|--------|-------------|
| **[Surge](https://wordpress.org/plugins/surge/)** | Best option. The ActivityPub plugin automatically configures Surge to cache HTML and JSON as separate variants. This is the only plugin that properly caches both response types. |
| **WP Super Cache** | Excludes ActivityPub requests from the cache. Federation works, but JSON requests always hit PHP. |
| **Cachify** | Same as WP Super Cache, excludes ActivityPub requests. |
| **Cache Enabler** | Same approach, excludes ActivityPub requests. |
| **WP-Optimize** (v3.3.1+) | Compatible, excludes ActivityPub requests. |

### Incompatible caching plugins

These plugins are known to cause problems:

- **W3 Total Cache** — Ignores the `Vary` header, serves cached HTML to Fediverse servers.
- **WP Rocket** — Same issue, no support for `Vary: Accept`.
- **Breeze** — Same issue.

If you must use one of these, consider disabling content negotiation (see below).

## Server-level caches

### Varnish

Varnish does not pass through `Vary: Accept` by default. You need to configure it explicitly. In your VCL:

```vcl
sub vcl_hash {
    if (req.http.Accept ~ "application/(ld\+json|activity\+json|json)") {
        hash_data("activitypub");
    }
}
```

Alternatively, add `Vary: Accept` in your Apache or Nginx config so Varnish sees it from the backend.

### Nginx FastCGI Cache

Use a map to distinguish JSON from HTML and add it to your cache key. Avoid using the raw `$http_accept` header directly, as browsers send many different Accept values that would fragment your cache unnecessarily:

```nginx
map $http_accept $activitypub_suffix {
    default        "html";
    ~application/  "json";
}

fastcgi_cache_key "$scheme$request_method$host$request_uri$activitypub_suffix";
```

### Cloudflare

Cloudflare does not vary cache by `Accept` header on most plans. The simplest workaround is to disable content negotiation (see below). Federation still works because Fediverse servers discover your content through the REST API endpoints and the `Link` alternate header, which point to distinct URLs that Cloudflare can cache normally.

### LiteSpeed / OpenLiteSpeed

The plugin automatically adds `.htaccess` rules when it detects the LiteSpeed Cache plugin. If the automatic setup fails, you can add the rules manually:

```apache
# BEGIN ActivityPub LiteSpeed Cache
<IfModule LiteSpeed>
RewriteEngine On
RewriteCond %{HTTP:Accept} application
RewriteRule ^ - [E=Cache-Control:vary=%{ENV:LSCACHE_VARY_VALUE}+isjson]
</IfModule>
# END ActivityPub LiteSpeed Cache
```

Check **Tools > Site Health** to verify the integration is working.

Note that OpenLiteSpeed (the open-source version) may ignore `Vary` headers entirely. If you are on OpenLiteSpeed and still seeing issues, consider disabling content negotiation.

## Disabling content negotiation

If your caching setup cannot handle `Vary: Accept` properly, you can disable content negotiation entirely:

1. Go to **Settings > ActivityPub > [Advanced](advanced-settings.md)**.
2. Uncheck **Content Negotiation**.
3. Save.

Or via `wp-config.php` / a plugin:

```php
add_filter( 'activitypub_should_negotiate_content', '__return_false' );
```

### What happens when content negotiation is off?

- Your post URLs (`https://example.com/hello-world/`) will always return HTML, regardless of the `Accept` header. Caching works as normal.
- Fediverse servers will still discover your content through the REST API endpoints (`/wp-json/activitypub/1.0/...`), which are separate URLs and not affected by page caching.
- The JSON representation is still accessible by appending `?activitypub` to any URL (e.g. `https://example.com/hello-world/?activitypub`). This always triggers content negotiation, even when the global setting is off.
- The `Link` header with `rel="alternate"` and `type="application/activity+json"` is still sent, so well-behaved clients can discover the JSON endpoint.

Disabling content negotiation is a safe choice if you are primarily concerned with caching. Federation still works, it just uses dedicated API endpoints instead of serving JSON from the same URL as HTML.

## The thundering herd problem

When you publish a post and have many followers (1,000+), hundreds of Fediverse servers may request your new post simultaneously. If your cache does not cache ActivityPub JSON responses (most compatible plugins simply exclude them), every single request hits PHP.

Recommendations for high-follower sites:

- Use **Surge** with the ActivityPub integration, which actually caches both HTML and JSON variants.
- Use **Nginx FastCGI Cache** or **Varnish** with proper `Vary` support so JSON responses are cached at the server level.
- Consider a reverse proxy or CDN that supports `Vary: Accept`.

## Checking your setup

The plugin adds checks to **Tools > Site Health** that verify:

- Whether the `Vary` header is enabled.
- Whether content negotiation is enabled.
- Whether LiteSpeed Cache or Surge are properly configured (if installed).

You can also test manually by requesting a post URL with an ActivityPub Accept header:

```bash
curl -H "Accept: application/activity+json" https://example.com/hello-world/
```

If you get JSON back, content negotiation is working. If you get HTML, either content negotiation is disabled or your cache is serving the wrong variant.
