# Inspect Internal Storage

Makes the ActivityPub plugin's internal Inbox, Outbox, and remote post (`ap_post`) storage visible in the WordPress admin, so you can inspect the raw activities the plugin sends and receives.

## How It Works

The ActivityPub plugin stores the activities it exchanges with the Fediverse as hidden custom post types:

- **Inbox** (`ap_inbox`) — activities received from remote servers.
- **Outbox** (`ap_outbox`) — activities your site has sent.
- **Posts** (`ap_post`) — cached remote objects (the content shown in the reader).

These are registered with `show_ui` disabled, so they never appear in the admin. This snippet hooks into `register_post_type_args` and `register_taxonomy_args` to flip those screens on, adds menu icons, and exposes the `ap_post` taxonomies (`ap_object_type`, `ap_tag`) for filtering. It also adds a **Meta** column to the Inbox and Outbox list tables so you can read each activity's stored metadata at a glance.

Nothing about how the plugin behaves changes — the snippet only reveals existing data for inspection.

## When to Use

This is useful if you want to:

- Debug federation problems by confirming whether an activity (a Follow, Like, or Delete) actually reached your Inbox.
- See exactly what your site sent in its Outbox, and to whom.
- Inspect cached remote objects and their object types.

> **Note:** This exposes raw, internal data intended for debugging. Use it on a staging or development site, and deactivate it when you are done.

## Installation

Copy this folder to `wp-content/plugins/` and activate **Inspect Internal Storage** from the WordPress admin, or copy `inspect-internal-storage.php` to `wp-content/mu-plugins/` for automatic activation.

## Requirements

- WordPress 5.9+
- PHP 7.4+
- [ActivityPub](https://wordpress.org/plugins/activitypub/) plugin
