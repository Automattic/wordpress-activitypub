# Content exposure

What a post, its metadata or its existence shows to someone who may not see it.

Applies to: `includes/class-router.php`, `includes/class-query.php`, `includes/functions-post.php`, `includes/scheduler/**`, `includes/class-scheduler.php`, `includes/transformer/**`, `includes/collection/class-outbox.php`, `includes/rest/**`, `templates/**`, `src/*/render.php`

## Past issues

- **Unauthenticated REST API access** (WPScan `5fb58642-61ba-447c-80ac-68d3777486d7`, CVE-2023-52199, fixed 1.0.6): endpoints reachable without auth.
- **Post title/content disclosure** (WPScan `daa4d93a-…` / `541bbe4c-…`, fixed 1.0.0): low-privilege users read unpublished content.
- **Drafts, scheduled and pending posts via content negotiation** (WPScan `50f68395-72fc-4f99-8e6d-6aa90cc640b5`, CVE-2026-4338, fixed 8.0.2, PR #3045): non-public posts served for an ActivityPub `Accept` header.
- **Per-post REST routes leaking non-public posts** (2026): `/posts/{id}/reactions`, `/posts/{id}/replies`, `/posts/{id}/likes`, `/posts/{id}/shares` and `/comments/{id}/remote-reply` loaded the post with `get_post()` and only checked that it exists, so private, draft, password-protected, local-only and formerly federated posts showed their metadata.
- **Visibility changes without a `post_status` change** (2026): a password, a visibility meta of `local`/`private` or a post type losing `activitypub` support kept the content exposed. The scheduler sent `Update` instead of `Delete`, content helpers read raw fields, content negotiation still rendered the post, and the outbox kept the leaked snapshot.

## Three kinds of "not public"

Check visibility and password on every surface that shows content. Skipping either is the usual leak.

| Trigger | Where it lives | Detected by |
|---|---|---|
| `post_status` not `publish` (draft, pending, private, trash, future) | `posts` table | `is_post_publicly_queryable()` |
| `activitypub_content_visibility` meta = `local` or `private` | post meta | `get_content_visibility()` and `is_post_publicly_queryable()` |
| `post_password` non-empty | `posts` table | `! empty( $post->post_password )` and `is_post_publicly_queryable()` |

`get_content_visibility()` does not see the password. A check that only uses it is incomplete: use `is_post_publicly_queryable()`, or add `! empty( $post->post_password )`.

## Two gates, two jobs

- `is_post_disabled()` is the pipeline gate for schedulers, transformers and outbox dispatch. It lets posts in a lifecycle change (`federated` and now private, `deleted` and now restored) through on purpose, so Delete and Create can still fire.
- `is_post_publicly_queryable()` is the content-exposure gate, without that exception. Use it wherever a post's content, metadata or existence reaches someone unauthenticated. Using `is_post_disabled()` there is a known leak.

## Content negotiation and helpers

- `is_activitypub_request()` and the `?activitypub` query parameter must not bypass access checks.
- `is_post_publicly_queryable()` blocks draft, pending, future, trash and private.
- Password-protected posts are blocked everywhere, including `template_include`, `pre_handle_404`, transformer helpers and block render callbacks, not only the REST controllers.
- Attachments (`inherit`) are only served when the parent is public and has no password. `is_post_publicly_queryable()` checks the parent; a hand-written parent status check does not.
- The transformer strips content, summary and attachments for non-public and password-protected posts. Only `[ap_content]` historically checked `post_password_required()`; `generate_post_summary()`, `get_name()`, `get_preview()` and any new helper need their own gate.

## The scheduler sends Delete on every way out of "public"

`includes/scheduler/class-post.php` picks `Create`, `Update` or `Delete` from `post_status`, but a post can stop being public without a status change:

| Change | Covered before? | Expected |
|---|---|---|
| `publish` → `trash` | yes (`new_status`) | Delete |
| `publish` → `private` | yes | Delete |
| visibility meta → `local` / `private` | yes (since #3045) | Delete |
| `post_password` set | no, this was the leak | Delete |
| post type loses `activitypub` support | partly, flag if missing | Delete |
| `supports` removed by a filter | partly, flag if missing | Delete |

The check that covers all of them is `ACTIVITYPUB_OBJECT_STATE_FEDERATED === $object_status && ! is_post_publicly_queryable( $post )`.

`add_to_outbox()` stores the activity as an `ap_outbox` post when it is scheduled, and `/outbox` keeps serving that snapshot after the post is fixed. Treat the outbox as its own surface, and check that a fix removes leaked snapshots.

## Per-post routes

Every route under `/posts/(?P<id>[^/]+)/…` and every controller that takes a post ID, directly or through `comment_post_ID`, gates on `is_post_publicly_queryable()`. Known callers: `Post_Controller::get_reactions`, `Post_Controller::get_context`, `Post_Controller::get_remote_intent_template`, `Replies_Controller::get_items`, `Comments_Controller::validate_comment`, `src/reactions/render.php`, `Replies::get_context_collection`.

- For a comment ID, gate on `$comment->comment_post_ID`.
- Where existence leaks, answer like a missing comment would.
- Prefer a `validate_callback` on the `id` argument, so the REST server rejects the post before the handler runs.

## REST routes and signed GETs

Files: `includes/rest/`, `includes/rest/trait-verification.php`.

- List every endpoint and its permission callback. `__return_true` on a write or sensitive endpoint is a finding.
- `verify_signature()` is on every S2S inbox route, `verify_authentication()` on every C2S route. The HEAD bypass is intentional; confirm it stays safe.
- Read what hooks `activitypub_defer_signature_verification`. Every `__return_true` there has to be scoped (like `includes/class-dispatcher.php` around one delivery) or a documented exception (the Delete handler). An unscoped one turns off auth for everything.
- A response that depends on who asks must not reach an integration that stores or republishes it (see `integrations.md`).

`verify_signature()` only requires a signature on GET when `use_authorized_fetch()` is on. That is right for public data (actor, `/followers` summary, `/outbox`) and wrong for peer-only routes such as `/followers/sync`:

1. Sort every `verify_signature` route into public or peer-only.
2. A peer-only route uses `verify_signature( $request, true )`; `$force_signature` skips the Authorized Fetch shortcut. The plain callable is a finding.
3. A route that answers for a specific peer (an `authority` parameter, a peer identity in the URL) also compares the signer's keyId host with that peer. A signature alone is not enough.
4. Test with Authorized Fetch off (unsigned peer-only requests get 401) and on (public routes still work). Do not test with a site-wide `__return_true` on the deferral filter.
5. Report each peer-only route as pass or fail for: mandatory signing, authority match, rejecting a mismatched keyId host, signing with Authorized Fetch off.
