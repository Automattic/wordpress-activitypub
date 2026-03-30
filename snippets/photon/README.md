# Use Jetpack's Site Accelerator CDN (Photon) for Remote Media

Rewrites ActivityPub remote media URLs through Jetpack's free image CDN instead of caching files locally.

When Jetpack's Site Accelerator feature (Photon) is available on your site, this snippet automatically proxies all remote media (avatars, images, emoji, audio, video) through the CDN. This eliminates the need for local file caching and leverages Jetpack's CDN for faster delivery.

If Jetpack's Image CDN feature is not available, the snippet does nothing.

## Installation

Copy this folder to `wp-content/plugins/` and activate **Use Jetpack's Site Accelerator CDN (Photon) for Remote Media** from the WordPress admin, or copy `photon.php` to `wp-content/mu-plugins/` for automatic activation.

## Requirements

- WordPress 5.9+
- PHP 7.4+
- [ActivityPub](https://wordpress.org/plugins/activitypub/) plugin
- [Jetpack](https://wordpress.org/plugins/jetpack/) with the Site Accelerator feature enabled, or the [Jetpack Boost](https://wordpress.org/plugins/jetpack-boost/) plugin with its Image CDN feature enabled.

## How It Works

The snippet hooks into the `activitypub_remote_media_url` filter at priority 5, before the built-in cache handlers (priority 10). It rewrites all remote media URLs through `Image_CDN_Core::cdn_url()`, which prepends `i0.wp.com` to serve them via Photon's CDN.

It also disables local file caching via the `activitypub_remote_cache_enabled` filter, since Photon handles CDN proxying and local copies are unnecessary.
