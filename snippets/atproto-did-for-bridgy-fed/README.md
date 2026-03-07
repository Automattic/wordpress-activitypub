# ATproto DID for Bridgy Fed

This snippet for ActivityPub serves a static ATproto DID from your blog's `.well-known` directory to allow Bridgy Fed to use your blog's hostname as its Bluesky handle.

This snippet is very barebones and does not, by itself, allow you to set up a custom domain handle for your bridged Bluesky account. However, serving the DID on the web is one of the steps involved in that process, and this snippet handles it for you. Notably, the implementation does not incur any HTTP redirects, which could otherwise hinder handle validation.

There is not yet a way to trigger the actual handle change from within WordPress.

## Installation

Modify line 37 of `atproto-did-for-bridgy-fed.php` to the DID of your blog's bridged account on Bluesky. Bridgy Fed [documents the process to find your DID](https://fed.brid.gy/docs#bluesky-enhanced).

Afterwards, copy this folder to `wp-content/plugins/` and activate **ATproto DID for Bridgy Fed** from the WordPress admin, or copy `atproto-did-for-bridgy-fed.php` to `wp-content/mu-plugins/` for automatic activation.

## Requirements

- WordPress 5.9+
- PHP 7.4+
- [ActivityPub](https://wordpress.org/plugins/activitypub/) plugin

## Resources

- [ATproto handle specification](https://atproto.com/specs/handle), especially the section “HTTPS well-known Method”
- [Bluesky handle debugger](https://bsky-debug.app/handle)
- [Bridgy Fed documentation on custom handles](https://fed.brid.gy/docs#bluesky-enhanced)
