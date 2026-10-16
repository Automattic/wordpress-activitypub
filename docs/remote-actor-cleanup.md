# Remote actor cache cleanup (draft)

Integrations can nominate a cached actor with
`Activitypub\Collection\Remote_Actors::mark_for_cleanup( $post_id )`.
Nomination is idempotent and adds a shared `candidate` term in the private
`ap_actor_cleanup` taxonomy. Existing actors are not nominated automatically.

Public actor lookups by post ID, URI, or account handle, actor conversion,
account/avatar access, and actor search automatically remove the marker.
Callers do not need to remember to cancel cleanup. Bulk existence probes and
maintenance selection (`get_outdated()` and `get_faulty()`) do not rescue actors.
Direct WordPress `get_post()` calls cannot be observed by this API.

The existing `activitypub_cleanup_remote_actors` daily action has two independent
callbacks: the unchanged faulty-actor cleanup and candidate cleanup. When a full batch makes progress, the
`activitypub_cleanup_actor_candidates` hook continues through the existing async
batch scheduler. That hook only handles continuation; it has no recurring schedule.
Marking an actor does not create any cron events.
Cleanup works on stored posts rather than public actor getters so it does not
rescue its own candidates. Failed deletions keep their marker for a later run.

Actors with followers, accepted/pending follows, blocks, comments, remote-post
references, stored inbox actor references, or outgoing activity references are
preserved and unmarked. Blog actor ID zero counts as a relationship. Trashed
content retains its actor reference. Outbox JSON is decoded once per batch,
without direct SQL or serialized-string searches.

Integrations can return true from `activitypub_remote_actor_is_in_use` to protect
references stored outside ActivityPub. The callback receives a stored WP_Post;
using a public actor lookup in that callback also automatically rescues it.
Returning false cannot override native references. If a durable reference is
later removed, an integration can nominate the actor again.

This only discards a local cache record through `Remote_Actors::delete()`.
It does not create a tombstone or delete comments or remote posts. A subsequent
URI lookup may fetch the actor again with a new local post ID. Integrations
must preserve the actor URI and tolerate missing cache IDs before nominating
actors shared by their own records.

## Questions before enabling this outside a development installation

- A final marker check catches access during an integration callback, but does
  not close the race between that check and a concurrent access or new reference.
  Reference writers and cleanup need a shared synchronization strategy.
- Calls to public APIs from other maintenance code may conservatively rescue
  candidates. Those paths need auditing as integrations adopt nomination.
- Unknown integrations may retain numeric actor IDs without participating in the
  protection filter. This API cannot discover arbitrary external references.
- Native metadata queries and comment author URL lookups still need profiling.
  Outbox scanning is paginated, but the total scan and URI set are not bounded
  by the actor batch size. An indexed reference mechanism would be preferable
  for very large outboxes.
- Repeated deletion failures at the front of the queue can delay other candidates.
  Retry ordering needs refinement before bulk backlog migrations adopt this API.
