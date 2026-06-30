# Why is a follow request stuck on "pending"?

Someone tries to follow your site from Mastodon (or another Fediverse platform) and the request stays on "Follow requested" / "Cancel request" forever.

The plugin **always accepts follow requests automatically** — there is no manual approval step. A follow that stays pending therefore always means the technical round-trip broke somewhere:

1. The remote server sends a `Follow` activity to your site's inbox (`/wp-json/activitypub/1.0/actors/…/inbox`).
2. Your site stores the follower and queues an `Accept` activity.
3. WP-Cron delivers the `Accept` back to the remote server.

If step 1 is blocked, your site never learns about the follow. If step 2 or 3 fails, your site knows about the follower but the remote server never hears back. The first thing to check tells you which half is broken:

> [!TIP]
> **Does the follower appear in WordPress?** Check **Users → Followers** (or **Settings → ActivityPub → Followers** for the blog profile).
>
> - **No** → the follow request never reached your site. Work through [Inbound checks](#inbound-checks).
> - **Yes, but still pending on the remote side** → the `Accept` is not being delivered. Work through [Outbound checks](#outbound-checks).

Also run **Tools → Site Health** first — the plugin ships its own checks (author URL reachable, WebFinger, System Task Scheduler) that catch several of the causes below.

## Inbound checks

These block the follow request before your site ever sees it. Your server's access log is the best diagnostic: look for `POST` requests to `/wp-json/activitypub/1.0/` paths and note the status code.

| Status in log | Likely cause |
|---|---|
| No request at all | Blocked upstream (firewall, Cloudflare) or the remote server gave up ([see below](#the-remote-server-stopped-trying)) |
| `403` | Host firewall (mod_security) or security plugin |
| `401` | Signature verification failure |
| `404` | Permalink / REST API routing problem |
| `202` | The request arrived — continue with [Outbound checks](#outbound-checks) |

### 1. Host firewall (mod_security)

> [!IMPORTANT]
> **The most common cause on shared hosting.** Many hosts (HostGator, Bluehost, OVH, Strato, and other cPanel-based hosts are recurring examples) run mod_security rules that block `POST` requests with the `application/activity+json` content type.

There is nothing the plugin can do about this — only your host can fix it. Contact their support with something like:

> My WordPress site uses the ActivityPub plugin. Please allow POST requests with the Content-Type `application/activity+json` to paths under `/wp-json/activitypub/`. Your mod_security configuration is currently rejecting them (often OWASP rule 920420). Other customers of yours have had this rule adjusted successfully.

After the host fixes it, ask followers to **cancel the pending request and follow again**.

### 2. Security plugins restricting the REST API

Security plugins that restrict the WordPress REST API to logged-in users break federation completely — the inbox returns `401 rest_login_required`.

Known options to look for:

- **Wordfence**: "Prevent discovery of usernames through `/?author=N` scans, the oEmbed API, the WordPress REST API…" — also breaks author URLs (Site Health shows "Author URL is not accessible").
- **All In One WP Security**: REST API restriction, "Deny bad query strings", "Advanced character string filter", and the 6G blacklist have all been reported to block ActivityPub traffic.
- **Patchstack / Solid Security / Sucuri / "Disable WP REST API"-type plugins**: any "restrict REST API to authenticated users" toggle.

Allowlist the `activitypub` REST namespace or disable the restriction.

### 3. Caching and Cloudflare

ActivityPub serves JSON and HTML from the same URLs ([content negotiation](../how-to/caching.md)). A cache that ignores the `Accept` header serves cached HTML to the remote server, which then cannot fetch or verify your profile. Test it:

```
curl -sL -H "Accept: application/activity+json" https://example.com/author/your-name/
```

You should get JSON, not HTML. If you get HTML, work through the [Caching guide](../how-to/caching.md). Quick fixes that have resolved real cases:

- Enable the **Vary Header** setting (**Settings → ActivityPub → [Advanced](../how-to/advanced-settings.md)**) — this fixed Cloudflare setups.
- In **W3 Total Cache**, enable its Vary-header option; **WP Rocket** and **Breeze** are known to ignore `Vary: Accept`.
- Cloudflare has also been reported to strip the `date` header on proxied requests, which breaks signature verification (`401`). Try grey-clouding the DNS record to confirm.

### 4. The Disallowed Comment Keys list

Surprising but real: the plugin checks **every incoming activity** against **Settings → Discussion → Disallowed Comment Keys**. A broad entry like `.com`, `.ru`, or `http` matches the JSON of every follow request (the actor URL contains it) and silently drops it.

Check the list for overly broad patterns — including ones added by block-list plugins. Deactivating such a plugin is not enough; the entries it added must be deleted manually.

### 5. Signature verification failures (401)

If the log shows `401` for inbox POSTs:

- **WordPress in a subdirectory** (`example.com/blog/`): see [WordPress in a Subdirectory](../how-to/wordpress-in-a-subdir.md). Fixed in current plugin versions — update first.
- **"Plain" permalinks** with `index.php/` in URLs: switch to a pretty permalink structure (**Settings → Permalinks**).
- **Reverse proxy / Docker**: the proxy must pass the public hostname through (`ProxyPreserveHost On` for Apache) — see [Reverse Proxy](../how-to/reverse-proxy.md). Also make sure the container clock is synced; signatures embed timestamps and clock skew makes them invalid.

### The remote server stopped trying

If your logs show no inbox requests at all even though the firewall is clean, the remote server may have given up on your domain: after repeated failures, Mastodon marks a domain "unavailable" and stops delivering *anything* — including new follow attempts. This persists even after you fix the original problem.

- Any activity sent *from* your site to that server (for example replying to one of its users) resets the status.
- The instance admin can also reset it manually (`DeliveryFailureTracker.reset!('example.com')`).
- Ask followers to cancel the pending request and follow again afterwards.

## Outbound checks

The follower shows up in WordPress, but the remote side still says pending — the `Accept` is queued but not delivered.

### 6. WP-Cron is not running

The `Accept` is sent asynchronously via WP-Cron. If cron never fires, the reply never leaves:

- `define( 'DISABLE_WP_CRON', true );` in `wp-config.php` without a real system cron job.
- A system cron configured to run rarely (e.g. once a day) — accepts are delayed by up to that interval.
- Very low-traffic sites where WP-Cron (which piggybacks on visits) rarely triggers.

Check **Tools → Site Health** for scheduler warnings, or inspect pending `activitypub_*` events with [WP Crontrol](https://wordpress.org/plugins/wp-crontrol/) or `wp cron event list`. The fix is a system cron job hitting `wp-cron.php` every few minutes.

### 7. Outbound requests blocked

Some hosts block outgoing HTTP requests or specific ports. Test with:

```
wp eval 'var_dump( wp_remote_get( "https://mastodon.social/.well-known/nodeinfo" ) );'
```

If outbound requests fail, contact your host.

## Still stuck?

Update the plugin first — several historic causes (subdirectory signatures, the 4.0.0 inbox regression, Pixelfed follows without addressing) were plugin bugs that are long fixed.

To pinpoint which half of the round-trip broke, the [Inspect Internal Storage snippet](https://github.com/Automattic/wordpress-activitypub/tree/trunk/snippets/inspect-internal-storage) exposes the plugin's Inbox and Outbox in the WordPress admin: a missing `Follow` in the Inbox points to the [Inbound checks](#inbound-checks), while a stuck `Accept` in the Outbox points to the [Outbound checks](#outbound-checks).

If none of this helps, open a thread in the [support forum](https://wordpress.org/support/plugin/activitypub/) and include: your hosting provider, active security/caching/antispam plugins, whether the follower appears in WordPress, and the status code your server log shows for inbox POSTs.
