# Remote actor storage and cleanup

ActivityPub stores remote accounts as `ap_actor` posts. These records contain
profile data, inbox addresses, and keys, but also hold relationships such as
followers, follows, and blocks. Comments and remote posts can reference their
local post IDs. An actor record therefore has a different lifetime from an
individual cached post.

This document covers the existing lifecycle and the candidate-cleanup API
proposed in this draft. Candidate cleanup does not replace the existing handling
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
| `Collection\Remote_Actor_Cleanup::start_cleanup()` | Actors explicitly marked as candidates | Proposed removal of disposable local cache records. |

Both use the existing recurring action. Candidate cleanup has no separate
recurring schedule, and marking an actor does not create a cron event.

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

### Disposable candidates: proposed behavior

An integration nominates an actor using:

```php
Activitypub\Collection\Remote_Actors::mark_for_cleanup( $post_id );
```

Nomination is idempotent and adds the shared `candidate` term in the private
`ap_actor_cleanup` taxonomy. Existing actors are not nominated automatically.
Nomination does not mean the remote account has been deleted.

Public lookups by post ID, URI, or account handle, actor conversion,
account/avatar access, and actor search automatically remove the marker. Callers
do not need to cancel cleanup manually. Bulk existence probes and maintenance
selection (`get_outdated()` and `get_faulty()`) do not rescue candidates. Direct
WordPress `get_post()` calls are outside these access APIs and do not cancel
cleanup.

Candidate cleanup reads stored posts without calling those public actor getters.
It preserves and unmarks actors with followers, accepted or pending follows,
blocks, comments, remote-post references, stored inbox actor references, or
outgoing activity references. Blog actor ID zero counts as a relationship, and
trashed content retains its references. Post-reference checks are scoped to
`Remote_Posts::POST_TYPE` (`_activitypub_remote_actor_id`, the author's cache ID)
and `Inbox::POST_TYPE` (`_activitypub_activity_remote_actor`, the sender URI).
Matching metadata on unrelated post types does not preserve a candidate; other
integrations use the protection filter. Outgoing JSON is decoded once per batch.

Integrations can return true from `activitypub_remote_actor_is_in_use` to protect
references unknown to ActivityPub. The callback receives a stored `WP_Post`;
using a public actor lookup there also rescues it. Returning false cannot
override a native reference. This filter only controls candidate cleanup, not
faulty-actor cleanup or confirmed remote deletion.

The default candidate batch is 20, filtered by
`activitypub_actor_cleanup_batch_size`. A full batch that makes progress schedules
a continuation through `activitypub_cleanup_actor_candidates` and the existing
async batch scheduler. Failed deletions retain their marker for a later attempt.
Protected candidates leave the queue; an integration can nominate them again
when their protecting reference is removed.

Candidate deletion only discards the actor record through
`Remote_Actors::delete()`. It does not publish a Delete activity, create a local
tombstone, or schedule removal of comments or remote posts. Fetching the actor
again remains possible if the remote account is available.

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

## Candidate-cleanup questions still open in this draft

- The final marker check catches access during an integration callback, but does
  not close the race with concurrent access or a new reference immediately before
  deletion. Reference writers and cleanup need shared synchronization.
- Other maintenance callers of public actor APIs may conservatively rescue
  candidates. Those paths need auditing as integrations adopt nomination.
- Unknown integrations may retain numeric IDs without using the protection
  filter. Arbitrary external references cannot be discovered automatically.
- Native reference queries need profiling. Outbox scanning is paginated, but its
  total work and URI set are not bounded by the actor batch size.
- Repeated deletion failures at the front of the queue can delay other candidates.

## Code reference

- [Actor collection and access APIs](../includes/collection/class-remote-actors.php)
- [Schedules, refresh, and faulty-actor cleanup](../includes/class-scheduler.php)
- [Proposed candidate cleanup](../includes/collection/class-remote-actor-cleanup.php)
- [Delete handler and associated-content deletion](../includes/handler/class-delete.php)
- [Tombstone detection](../includes/class-tombstone.php)
- [Move handler](../includes/handler/class-move.php)
