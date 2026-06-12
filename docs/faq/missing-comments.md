# Why don't comments from the Fediverse show up?

People reply to your posts on Mastodon (or another Fediverse platform), but the replies never appear as comments on your WordPress site.

Federated replies are inserted as **regular WordPress comments**, through the same pipeline a visitor's comment-form submission goes through. That is by design — moderation, notifications, and spam handling all work as usual — but it also means **every plugin and setting that gatekeeps comments applies to federated replies too**. A reply from Mastodon cannot solve a captcha, so a captcha plugin that checks all comments will reject every single one.

The plugin already exempts federated comments from flood control and the "name and email required" setting, but it cannot bypass third-party comment validation.

Work through this checklist in order:

### 1. The comment is not missing — look in Spam, Pending, and Trash

The most common "missing" comment is sitting in a queue:

- **Comments → Spam**: Akismet and friends regularly misclassify federated comments (see below).
- **Comments → Pending**: **Settings → Discussion → "Comment must be manually approved"** or **"must have a previously approved comment"** holds federated replies like any other comment. If you want Fediverse interactions to skip moderation, use the official [auto-approve-reactions snippet](https://github.com/Automattic/wordpress-activitypub/tree/trunk/snippets/auto-approve-reactions).
- **Comments → Trash**: entries on the Disallowed Comment Keys list land here (see below).

⚠ **Beware of "silently discard" options.** Akismet's "Silently discard the worst and most pervasive spam" and similar auto-delete settings (e.g. Antispam Bee's honeypot delete) remove comments without a trace — it looks exactly like they never arrived. Switch such options to "mark as spam" at least while debugging.

### 2. Captcha and honeypot plugins

**The headline cause.** Captcha plugins usually do not distinguish between a comment submitted via the form and one created via an API — and a federated reply can never pass a captcha. Confirmed examples from support threads and issues:

- **hCaptcha**: the comment-form integration blocks all federated replies. Disable hCaptcha for the comment form (keep it for login etc.).
- **Advanced Google reCAPTCHA**: blocked all incoming replies; a telltale sign was that comment **notification emails still arrived** while the comments themselves never materialized.
- **WP Armour (honeypot)**: caused the inbox request to fail with an HTTP 500, blocking replies (and more).
- **CleanTalk**: has flagged every Fediverse comment as spam.

Fix: disable the plugin's comment-form protection, or deactivate the plugin briefly to confirm it is the culprit, then ask its developers for an exemption. Plugin authors can use the ActivityPub plugin's `\Activitypub\is_activitypub_request()` helper to skip validation for federated comments — please report incompatibilities upstream so they do.

### 3. Akismet specifics

Akismet generally works alongside ActivityPub, but federated comments score worse than form comments and some patterns are misjudged reliably — for example **emoji in the commenter's display name** has repeatedly sent replies straight to spam.

- Check the Spam folder and click **Not Spam** — recent plugin versions submit the commenter's Fediverse address (`user@instance.example`) as the author email, so Akismet can learn per-author reputation.
- Make sure "Silently discard the worst spam" is off (see step 1).

### 4. The Disallowed Comment Keys list

**Settings → Discussion → Disallowed Comment Keys** is applied to the **full JSON of every incoming activity**, not just the comment text. A broad entry like `.com`, `.ru`, or `http` matches the commenter's profile URL inside the activity and trashes (or silently drops) every reply — and blocks follows too.

Review the list for broad patterns, including leftovers from block-list plugins.

### 5. Security plugins and host firewalls blocking the inbox

Everything that blocks your ActivityPub inbox blocks replies exactly like follow requests: mod_security rules on shared hosts, REST-API-restricting security plugins (Wordfence's username-discovery option, All In One WP Security's REST/6G rules, "Disable REST API" plugins), and similar.

Check your server access log for the `POST` to `/wp-json/activitypub/1.0/…/inbox`:

- **`202`** — the activity arrived; the comment is being filtered locally (steps 1-4).
- **`400`/`403`** — firewall or security plugin.
- **`500`** — a plugin conflict during processing (WP Armour was one confirmed case).
- **Nothing** — blocked upstream; work through the same inbound checks as in [Why is a follow request stuck on "pending"?](pending-follow-requests.md#inbound-checks).

### 6. Caching

If a caching layer serves cached HTML where the remote server expects JSON ([content negotiation](../how-to/caching.md)), reply delivery breaks before the comment is ever created. W3 Total Cache (without its Vary-header option), WP Fastest Cache, WP Rocket, and Breeze have all been confirmed culprits. See the [Caching guide](../how-to/caching.md) for compatible configurations.

### 7. It was never a reply

Some "missing comments" are protocol semantics, not bugs:

- **Only actual replies become comments.** A post that merely @-mentions you — written from scratch instead of using the Reply button — has no `inReplyTo` and cannot be matched to a post.
- **Visibility matters.** Replies with "mentioned people only" visibility are treated as private messages, not public comments.
- **Deep threads:** replies to *other people's* replies arrive via the thread; in some setups replies addressed to a local WordPress comment rather than the post are still not imported — a known limitation under investigation.
- **Companion plugins:** if you run the [Friends plugin](https://wordpress.org/plugins/friends/), mention-only posts appear there as feed items rather than as comments — that is intentional routing.

## Still stuck?

Update the plugin first to rule out long-fixed bugs. Then test with all antispam/captcha/security plugins temporarily disabled — if the reply arrives, re-enable them one at a time to find the culprit.

If none of this helps, open a thread in the [support forum](https://wordpress.org/support/plugin/activitypub/) and include: which antispam/captcha/caching/security plugins you run, whether comment notification emails arrive, and the status code your server log shows for the inbox POST.
