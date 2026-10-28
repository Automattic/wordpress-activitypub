# Inbox and handlers

Inbound activities: binding every reference to the signing actor, relayed activities, ownership checks.

Applies to: `includes/handler/**`, `includes/collection/**`, `includes/rest/class-inbox-controller.php`, `includes/rest/class-actors-inbox-controller.php`, `includes/functions-activity.php`

## Past issues

- **Actor↔object binding gaps** (2026): Update, Undo and Accept handlers and remote upserts (`Remote_Actors`, `Remote_Posts`, `Interactions::update_comment`, `Inbox::undo`) changed or deleted records found by the sender's `object.id`, without checking the `actor` owns it. The Delete handler did check: `object_to_uri($activity['object']) === $activity['actor']`.
- **Relayed activity not re-authenticated** (2026): `Announce::handle_announce()` passed the inner activity to its handler as it came. The announcer is not necessarily its author, so an inner `Undo` or `Delete` could name another actor. The inner activity now has to be fetched from its own `id` with `host(id) === host(actor)`.
- **Ownership check fallbacks** (2026): the Update and Undo owner checks let records through when no owner was recorded, when the recorded owner was deleted or merged, or when the lookup and the check read different identifiers.

## Every reference is bound to the signing actor

The signature binds only the top-level `actor`. Everything else a handler reads, stores or acts on (`object`, `attributedTo`, any field specific to the activity) must be on the actor's host: `host(ref) === host(actor)`. This includes create paths: `Remote_Posts::add()` requires `actor === object.attributedTo`, like `Remote_Posts::update()`.

- Compare hosts with `is_same_host()`. It normalizes through `object_to_uri()` and fails closed when a side has no host (handles and identifiers that are not `http(s)` parse to none). A hand-written `wp_parse_url` comparison that passes on an empty side fails open.
- Fold both sides of a host gate with `fold_host()`/`fold_authority()`, and make sure the folded host is the one the plugin connects to (see "Canonicalization" in `signatures.md`).
- A binding is worthless when one side is derived from the other, for example an `actor` filled in from the signing key. Pin whatever the handler still trusts on its own.
- The signature's `keyId` belongs to the `actor`, not only to its host: the key's owner (the stored actor the keyId resolves to, or the key as its host serves it through `Proxy::get()`) has to be the actor. On a server where users hold their own keys, a same-host check lets one user sign for another (see "Same-host key accepted for another actor" in `signatures.md`).

## Relayed and wrapped activities

Any path that takes an activity out of another one and dispatches it (today `Handler\Announce::handle_announce()` through `do_action( "activitypub_inbox_{$type}", $object, … )`) treats the inner activity as attacker-controlled, embedded or named by URL. An inner `Undo`/`Delete`/`Remove`/`Block` can name a victim.

- The inner activity is authentic only if it is fetched from its own `id` and `host( id ) === host( actor )`. `Http::get_remote_object()` does not check that the returned `id` matches the URL, so the caller does.
- That fetch uses `redirection => 0` and `$cached = false`. `Http::get()` follows redirects, and its cache is keyed by URL and read before the args apply, so an earlier cached redirect would undo `redirection => 0`. (Same as the CIMD fetch in `includes/oauth/class-client.php`.)
- `Inbox::undo()` and the Delete handler act on the inner activity's `actor`. If that actor can arrive unverified through a wrapper, it is critical.
- `handle_announce()`, and any new relay path, authenticates before dispatch.

## Ownership checks

- **A fallback is a gate of its own.** "No owner recorded, let it through" for old rows still binds the actor, at least `is_same_host( actor, <the identifier the record was found by> )`. Missing never means anyone.
- **A recorded reference that no longer resolves fails closed.** `if ( $owner instanceof WP_Post && … )` skips the check exactly when the owner is gone. Every path that deletes or merges a record an authorization depends on (actor Delete, Move, scheduled cleanup) repoints or removes the references in the same step.
- **Check the value you look up by.** `object_to_uri()` returns `url` for `Image`, `Audio`, `Video` and `Document`, so a gate on `object_to_uri( $object )` and a lookup on `$object['id']` can get two different identifiers. Derive it once and use it for lookup, check and stored key, as `add_comment()` does.

## Other inbox checks

Files: `includes/rest/class-inbox-controller.php`, `includes/rest/class-actors-inbox-controller.php`, `includes/handler/`.

- `type` is sanitized; it becomes part of a hook name (`do_action('activitypub_inbox_' . $type)`).
- `to`/`cc`/`bcc` do not cause SSRF through `get_local_recipients()`.
- `object` cannot inject stored content; the Create handler is checked for stored XSS.
- Remote content is sanitized before storage (`wp_kses_post()` or equivalent).
