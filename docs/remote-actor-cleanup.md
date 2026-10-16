# Remote actor storage and cleanup

ActivityPub stores remote accounts as `ap_actor` posts. These records contain
profile data, inbox addresses, and keys, but also hold relationships such as
followers, follows, and blocks. Comments and remote posts can reference their
local post IDs. An actor record therefore has a different lifetime from an
individual cached post.

This document covers the existing lifecycle and the cache-only classification API
proposed in this draft. Cache-only cleanup does not replace the existing handling
of unavailable or deleted accounts.

## Creation and access

`Collection\Remote_Actors::fetch_by_uri()` looks up a stored actor by its canonical
URI and fetches and stores it if missing. `fetch_by_acct()` resolves an account
handle, while `fetch_by_various()` accepts either form. `get()` and `get_by_uri()`
only look up existing records. `get_actor()` converts stored data to an actor
object; it does not fetch a missing record by URI.

`create()`, `update()`, and `upsert()` persist actor data. A received actor Update
can refresh an existing record through the Update handler. Stored actors do not
expire simply because the posts that introduced them have been removed.

The URI identifies the remote account. The numeric post ID identifies this
site's cached copy. If that copy is deleted and fetched again, its new ID does
not repair references to the old ID automatically. Integrations must either
protect those references or retain the URI and recover missing cache entries.

## Scheduled refresh

The hourly `activitypub_update_remote_actors` action runs
`Scheduler::update_remote_actors()`. It selects actors whose stored profile has
not been updated for at least a day, oldest first, and fetches their current
profiles through `Proxy::get()` with its cache bypassed.

A successful response with the same identity updates the stored actor and clears
its errors. Fetch or parse failures add an `_activitypub_errors` entry. A response
claiming another identity leaves the profile unchanged and advances its modified
date so that actor does not monopolize the refresh queue.

The default batch is five actors, or 50 when `DISABLE_WP_CRON` is enabled.
`activitypub_update_remote_actors_number` filters that limit.

## Scheduled cleanup

The daily `activitypub_cleanup_remote_actors` action has two independent callbacks:

| Callback | Selection | Purpose |
| --- | --- | --- |
| `Scheduler::cleanup_remote_actors()` | Actors with errors or a missing/empty inbox | Existing handling of faulty or deleted remote accounts. |
| `Collection\Remote_Actor_Cleanup::start_cleanup()` | Actors explicitly classified as cache-only | Proposed removal of disposable local cache records. |

Both use the existing recurring action. Cache-only cleanup has no separate
recurring schedule, and classifying an actor does not create a cron event.

### Faulty actors: existing behavior

`Remote_Actors::get_faulty()` selects actors with recorded errors or a missing
inbox. The scheduler obtains metadata and takes one of these branches:

- A recognized tombstone deletes the actor record.
- An empty, invalid, or error response adds an error. If at least five errors
  already exist, the actor is deleted and deletion of its interactions and
  remote posts is scheduled.
- Valid metadata updates the actor and clears its errors on success.

The default batch is five actors, or 50 with `DISABLE_WP_CRON` enabled.
`activitypub_cleanup_remote_actors_number` filters that limit. A healthy actor
without errors or a missing inbox is not selected merely because it is old or
unreferenced.

There are existing implementation details to distinguish from the intended
remote recheck: `get_remote_metadata_by_actor( $uri, false )` currently ignores
its cache argument and can return the stored actor. Fresh scheduled profile
requests happen in the hourly refresh described above. Also, the repeated-error
branch currently schedules associated-content deletion with an actor URI,
whereas those handlers accept a numeric actor ID. This draft does not change
those paths.

### Cache-only actors: proposed behavior

Local `_activitypub_cache_only` metadata states whether an actor is a disposable
profile cache or a record that has acquired a lasting use:

| Stored state | Meaning |
| --- | --- |
| Missing | Unclassified; protected by default for compatibility. |
| `true` | Explicitly cache-only; eligible for the cache cleanup callback. |
| `false` | Non-cache-only; excluded from cache cleanup. |

Classification happens after use without adding arguments to actor fetch APIs:

```php
$post = Activitypub\Collection\Remote_Actors::fetch_by_uri( $uri );
if ( ! is_wp_error( $post ) ) {
    Activitypub\Collection\Remote_Actors::set_cache_only( $post, true );
}
```

A caller establishing a lasting reference can use
`Remote_Actors::set_cache_only( $post, false )`. The setter accepts a stored
`WP_Post` or post ID. The protocol `Activity\Actor` object is not a persistence
wrapper; this metadata stays local rather than being federated.

ActivityPub automatically sets false when recording a follower, pending or
accepted follow, block, remote-post author, or comment interaction reference.
An explicit false is sticky: later attempts to set true are refused. Removing a
relationship does not automatically downgrade the actor. This keeps the first
version binary rather than introducing per-reason reference counts, at the cost
of keeping formerly used actors until a future reconciliation.

Ordinary lookups, actor conversion, profile reads, search, and background refresh
leave the classification unchanged. There is no candidate taxonomy or
rescue-on-read behavior. A cache-only actor may disappear while an integration
still displays its posts, so that integration must retain its URI and handle a
new cache ID on the next fetch. Unclassified actors are not swept automatically.

The cache cleanup callback selects published actors whose stored flag is true.
As a safeguard for existing data, it still checks native relationships, comments,
remote posts, inbox references, and stored outgoing activities. Post checks are
scoped to `Remote_Posts::POST_TYPE` and `Inbox::POST_TYPE`. The
`activitypub_remote_actor_is_in_use` filter can report other integrations' uses.
A discovered use promotes the record to false, excluding it from subsequent
cache sweeps. Returning false from the filter cannot override native references.

The default batch size is 20, filtered by `activitypub_actor_cleanup_batch_size`.
A full batch making progress continues through
`activitypub_cleanup_actor_candidates` using the existing async scheduler.
That is only a continuation hook; the recurring trigger remains
`activitypub_cleanup_remote_actors`. A final flag check avoids deletion if an
integration promotes the actor while inspecting it. Failed deletions remain
eligible for another attempt.

Cache cleanup invokes `Remote_Actors::delete()` only. It does not publish a Delete
activity, create a local tombstone, or delete comments or remote posts. The
classification and protection filter do not change the existing faulty-actor
policy or confirmed account deletion.

## Confirmed remote account deletion

The Delete handler treats an incoming actor Delete separately from disposable
cache cleanup. It confirms deletion at the actor's own URI rather than trusting
an inline tombstone. Tombstone detection recognizes a Tombstone object or the
supported HTTP 404/410 responses.

Once confirmed, the handler purges the proxy cache, schedules deletion of the
actor's interactions and remote posts using its local ID, and deletes the actor
record. Candidate markers and the candidate-protection filter do not govern
this path: the account has disappeared from its remote server.

## Other ways an actor record can disappear

The Move handler may reuse the origin record for a new account URI or, when
merging two existing records, transfer follower relationships and delete the
origin record. This is account migration, not unused-cache cleanup.

`Remote_Actors::delete( $post_id )` itself deletes the WordPress post and clears
affected inbox caches. It does not check whether the actor is unused, migrate
references, or delete associated content. Callers choose the policy before
invoking it. Integrations should use this API rather than a bare
`wp_delete_post()` so inbox cache invalidation is preserved.

## Cache-classification questions still open in this draft

- A final flag check does not close the race with a concurrent promotion or new
  reference immediately before deletion. Writers and cleanup need shared
  synchronization; the setter's sticky semantics are not a concurrency guarantee.
- Explicit false is deliberately sticky. Reclaiming formerly used actors later
  would require a reconciliation policy or finer-grained reasons.
- Unknown integrations may hold IDs without classifying their actors. Missing
  metadata protects them until another consumer explicitly opts the record in;
  ownership of shared, previously unclassified actors still needs agreement.
- Existing data needs conservative classification. Native reference checks remain
  necessary until legacy actors and all reference writers have been accounted for.
- Native queries need profiling. Outbox scanning is paginated, but its total work
  and URI set are not bounded by the actor batch size.
- Repeated deletion failures at the front of the queue can delay other records.

## Code reference

- [Actor collection and access APIs](../includes/collection/class-remote-actors.php)
- [Schedules, refresh, and faulty-actor cleanup](../includes/class-scheduler.php)
- [Proposed candidate cleanup](../includes/collection/class-remote-actor-cleanup.php)
- [Delete handler and associated-content deletion](../includes/handler/class-delete.php)
- [Tombstone detection](../includes/class-tombstone.php)
- [Move handler](../includes/handler/class-move.php)
