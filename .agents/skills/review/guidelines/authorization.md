# Authorization

Who may call a route or change a setting: ownership, capabilities, user ID 0, OAuth scopes.

Applies to: `includes/rest/**`, `includes/oauth/**`, `includes/wp-admin/**`

## Past issues

- **Ownership checks written by hand** (2026): endpoints decided ownership themselves instead of using `verify_owner()` / `maybe_verify_owner()` (`includes/rest/trait-verification.php`). The Outbox controller treated `current_user_can('activitypub')` as owning every user's outbox, and the SSE outbox stream checked the owner but queried by the actor type meta (`_activitypub_activity_actor` = `'user'`), so it sent every user's items.
- **User ID 0** (2026): `Actors::BLOG_USER_ID` is `0`, and so is `get_current_user_id()` when logged out. `user_can_activitypub( get_current_user_id() )` or `get_current_user_id() === $user_id` without a login check let anonymous callers act as the blog actor.
- **OAuth scope missing next to identity** (2026): a bearer token proves who the caller is, not what it may do. Read paths that only checked the user served data to tokens without the `read` scope.

## Ownership

- Access to a route and the scope of its query are two checks. Verify both.
- Use `verify_owner()` instead of a hand-written owner check.
- Any check keyed on a user ID that can be `0` confirms `is_user_logged_in()` first. This only matters where the result grants access or exposes data. A redirect choice on a `__return_true` endpoint whose branches all gate themselves (for example by sending to `wp-login`) is not a finding.
- For every stored reference an authorization reads (owner IDs in comment or post meta, stored actors), find the paths that delete or merge the referenced record. Each one repoints or removes the references, or the check fails closed when they dangle (see "Ownership checks" in `inbox.md`).

## OAuth

Files: `includes/oauth/`, `includes/rest/oauth/`.

- `is_user_logged_in()`, `get_current_user_id()`, `current_user_can()` and `verify_owner()` say who the caller is. Every owner-only read also requires `read` and every change `write`, whatever the transport: a stream or a POST that only reads is a read. Use `verify_authentication()`, `owner_may_read()` or `OAuth_Server::permits_scope()`.
- The same data has several read paths: paged collection, event stream, single item, embedded or seek responses, and callers outside REST that authenticate a bearer themselves. List them per collection; each applies the same scope. Shared code that branches per collection or type applies it in every branch.
- Tests cover a token without the needed scope on every path and branch, not only the reported one.
- PKCE: `plain` is blocked, not just discouraged.
- Dynamic client registration is rate-limited.
- `redirect_uri` validation handles custom schemes safely.
- Tokens are stored hashed, expire, and users cannot revoke other users' tokens.
- The consent screen has CSRF protection.
- The Client ID Metadata Document fetch is checked for SSRF (see `remote-fetch.md`).

## Admin and capabilities

Files: `includes/wp-admin/`, `includes/rest/`.

- Admin pages check `manage_options` or the fitting capability.
- Per-user settings (like the profile ActivityPub toggle) check that it is the right user.
- Every form submission has a nonce check. Each `phpcs:ignore WordPress.Security.NonceVerification` explains why it is safe.
- A gate fed by `rawurldecode()`/`urldecode()` sees a value REST has usually decoded once already. Check what the second decode lets in: `*`, `_`, the empty string, characters a later sanitizer strips, encoded spellings of a stored identifier (see "Canonicalization" in `signatures.md`).
