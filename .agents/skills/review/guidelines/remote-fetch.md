# Remote fetches and the remote cache

Outbound requests (SSRF) and what gets stored from them.

Applies to: `includes/class-http.php`, `includes/class-proxy.php`, `includes/functions-request.php`, `includes/collection/class-remote-*.php`, `includes/collection/class-interactions.php`, `includes/rest/class-proxy-controller.php`, `includes/rest/class-interaction-controller.php`, `includes/rest/trait-event-stream.php`, `includes/class-webfinger.php`, `includes/oauth/class-client.php`

## Past issues

- **Remote object cached under a self-declared id** (2026): actors (profile and public key) and embedded objects were cached under their own `id`, with no check that it matched the host they came from, so one host could write a record under another host's identifier. `Http::get_remote_object()` did not compare the returned `id` with the URL, and the writes keyed on `$object['id']`. Now a fetched object must confirm its own `id`, and an embedded one needs `host(object.id) === host(actor)`.
- **Link-local and CGNAT in core URL validation** (2026, known, do not report again): `wp_safe_remote_get/post()` uses core's `wp_http_validate_url()`, which blocks loopback and RFC1918 but allows link-local `169.254.0.0/16` (cloud metadata `169.254.169.254`) and CGNAT `100.64.0.0/10`. A core fix is pending; check whether it landed. The exposure is blind (a reachability or timing signal, no body). CGNAT stays unblocked on purpose, because overlay VPNs use it.

## Outbound requests

Files: `includes/class-http.php`, `includes/functions-request.php`, `includes/rest/class-proxy-controller.php`, `includes/rest/class-interaction-controller.php`, `includes/class-webfinger.php`, `includes/oauth/class-client.php`, and every other `wp_safe_remote_*` caller.

The plugin's stricter guard is `resolve_public_host()` (`includes/functions-request.php`): `FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE` plus `is_unsafe_ipv6_literal()`, link-local included, CGNAT allowed. It protects connection paths outside the WP HTTP API (`oauth/class-client.php`, `rest/trait-event-stream.php`), not `Http::get()`/`Http::post()`. The `activitypub_allow_non_public_host` filter lets intranet sites opt out; a fix that adds the guard to a new path keeps that filter working.

- List every fetch: `git grep -nE "wp_(safe_)?remote_(get|post|request|head)"` in `includes/` and `integration/`. A plain `wp_remote_*` on a URL a remote can influence is a finding. Socket paths (`stream_socket_client`) call `resolve_public_host()` first.
- Trace where the URL comes from: the `interactions` `uri` parameter, an actor's `inbox`/`outbox`/`sharedInbox`, `id`/`url` in fetched objects, hosts from WebFinger. A signature authenticates the sender, not the destination.
- Every hop of a chain (WebFinger → actor → inbox, collection → items) goes through a validated fetch. Redirects are hops: inside the WP HTTP API they get the same validation, and a fetch outside it validates every hop itself.
- Blind SSRF counts, but with no body returned it is low.
- Federation fetches need the guard. Admin fetches may target internal hosts (Site Health probing the site's own URL, admin-configured blocklist subscriptions); flag them only when an attacker can influence the URL.
- Check the proxy controller's `eventStream` follow-up fetch and the WebFinger host extraction.
- When a gate decides on one host and a later step builds a URL from a related value, both name the same DNS host, and a folded host is checked to be a bare host first (see "Canonicalization" in `signatures.md`). A handle's host is cut at `/` as well as `?#`.
- Every unauthenticated trigger of a fetch is rate-limited.

## The remote cache: fetch, write, read

Files: `includes/collection/class-remote-actors.php`, `includes/collection/class-remote-posts.php`, `includes/collection/class-interactions.php`, `includes/class-http.php`, `includes/functions-request.php`, `includes/handler/`, and any store keyed by a remote identifier.

Whenever remote data is stored under a remote-controlled key (`id`, `url`, `guid`, `acct`/handle, `keyId`, `serverId`), the write is bound to a trusted origin. Otherwise one host writes a record under another host's identifier. This applies to actors, keys, posts, interactions, followers, tombstones and every new store.

Find the writes (`upsert`, `create`/`update`, `wp_insert_*`/`wp_update_*`/`add_*_meta`/`update_option`), the key each one uses and where the key comes from. Each write uses one of two trust models:

1. **Dereferenced-by-URL objects** (actors, keys, collections, anything fetched by URL): trusted only when served under their own `id`. If the document declares another `id`, fetch that `id` from its own host once and accept only the copy that confirms itself (`id === served-from-url`, against the URL after redirects), no further chasing. This mirrors Mastodon's `JsonLdHelper#fetch_resource`. `Http::get_remote_object()` does this for every caller through `id_matches_url()`; new writes get their document there, not from a raw `Http::get()`.
2. **Embedded objects inside a signed activity** (a Create's post, a reaction's id): the signature vouches for the content, not for a cross-host `id`, so bind `host(object.id) === host(activity.actor)`. Do not fetch again.

The stored `ap_actor` record is written from the actor embedded in incoming Updates, and the key check reads from it. It is only the actor's own data because the signer has to be the key's owner (see `signatures.md`). Every path that writes a remote actor, its key or its inbox is bound to the signer the same way; a check that relaxes the owner rule poisons the store.

Trace the read side as well (`get_public_key`, `get_by_uri`/`get_by_guid`, `object_id_to_comment`/`url_to_commentid`): it must not be steerable to another actor's record or serve a poisoned entry again.

Red flags:

- A lookup with a different key than the write (`fetch_by_uri` checks `get_by_uri($url)`, but `upsert()` keys on `$object['id']`).
- An `upsert`/`create`/`insert` keyed by `$object['id']` / `$activity['object']['id']` / a remote `guid` with no origin binding.
- A read that hands a stored record to a security decision (signature, ownership, addressing) without checking its origin.
- A document cached under its own claimed id instead of the URL it was fetched from.

Almost every inbound activity resolves its actor through `get_remote_metadata_by_actor()` → `Remote_Actors::fetch_by_uri()`, and Follow, Create, Like and Announce are on by default, so any fediverse server reaches these writes without authentication.
