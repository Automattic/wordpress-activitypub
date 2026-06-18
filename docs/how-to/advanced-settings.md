# Advanced Settings

The ActivityPub plugin has an Advanced Settings tab that is hidden by default. It contains options for fine-tuning how the plugin handles content negotiation, the Vary header, authorized fetch, and other technical settings.

## Enabling the Advanced tab

1. Go to **Settings > ActivityPub**.
2. Click **Screen Options** in the top-right corner of the page.
3. Check **Advanced Settings**.
4. Click **Apply**.

An **Advanced** tab will now appear alongside the other tabs on the ActivityPub settings page. This preference is stored per user, so each administrator enables it independently.

You can also reach the Advanced tab directly by navigating to `Settings > ActivityPub > Advanced` or by appending `&tab=advanced` to the settings URL. Visiting the tab directly will make it visible even if you have not enabled it in Screen Options.

## Available settings

The Advanced tab includes the following options:

- **Vary Header** — Sends a `Vary: Accept` HTTP header to help caches distinguish between HTML and JSON responses. Enabled by default. See the [Caching guide](caching.md) for details.
- **Content Negotiation** — Controls whether the same URL can serve both HTML and ActivityPub JSON based on the `Accept` header. Enabled by default. See the [Caching guide](caching.md) for when and why to disable this.
- **Authorized Fetch** — Requires remote servers to sign their requests with HTTP Signatures before the plugin serves ActivityPub data. Disabled by default.
- **Open Registrations** — Advertises in NodeInfo whether the site accepts new user registrations.
- **Custom Outbox Backfill** — Controls how many existing posts are included in the outbox for new followers.
- **NodeInfo2** — Enables the NodeInfo2 protocol for enhanced discovery.
- **Content Warning** — Allows setting a default content warning (summary) for posts.
