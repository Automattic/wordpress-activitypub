# WordPress ActivityPub Plugin

WordPress plugin implementing the ActivityPub protocol, enabling federation with Mastodon, Pixelfed, Pleroma, and other compatible platforms.

Prefer reading project files and `docs/` over relying on training data for WordPress and ActivityPub patterns.

**Do NOT:**
- Edit WordPress core files
- Use `remove_all_filters('pre_http_request')` in tests
- Hardcode new version numbers (use `'unreleased'`)
- Remove an escaping, sanitizing or capability check, even when a tool reports it as redundant
- Change the priority or signature of a registered hook (third-party code unhooks against the exact signature)

## Common Pitfalls

- **PHP 7.4 compatibility.** CI tests against PHP 7.4. No named arguments, no union types, no `match` (PHP 8.0+).
- **Never edit WordPress core files.** Only modify plugin code.
- **Pre-commit hooks modify files.** If the hook changed files, stage and commit again — do not assume the first commit succeeded.
- **`remove_all_filters('pre_http_request')` is forbidden in tests.** The pre-commit hook blocks this. Use targeted filter removal.
- **Changelog entries are end-user friendly and end with punctuation.** Users see these in the WordPress update screen. Describe what changed from their perspective — no jargon, class names, or method names.
- **`post_date_gmt` may be empty.** Check for `0000-00-00` or empty values.
- **Cleanups change no behaviour.** A lint, static-analysis or refactor task changes docblocks and types, not what the code does. When the analyzer is wrong about a core function, fix its view (a stub), never the output line.
- **A docblock is a claim, not evidence.** Read the call sites before changing code because of what a comment says, and never revert on a comment's authority either.
- **A request handler answers only for the actor whose inbox received it.** Requests (`QuoteRequest`, `FeatureRequest`, any new `*Request`) ask the owner of their object for consent. The inbox checks blocks for the recipient only, so a handler that resolves the owner itself (post author, featured actor) must reject when that owner is not among `$user_ids`. Otherwise a request sent to one user's inbox gets an Accept in another user's name, past that user's blocks. See `Quote_Request::handle_quote_request()` for the check.
- **An owner's block stops every activity that targets their content.** Recipients are filtered per recipient, but a reply, reaction or quote lands on a post or comment whose owner is often not among the recipients (the actor's local followers, a mention, nobody). `Moderation::activity_is_blocked()` therefore also checks the blocks of the post author and the local comment author the activity targets, resolving the target with `get_target_post_id()`, the same function that stores the interaction, and both inboxes and `Scheduler::process_inbox_activity()` call it before any handler runs. The owner decides for the whole activity, so other recipients do not get it either (no mention notification). Only a post type enabled for ActivityPub with a non-zero author has an owner: a cached remote object (`ap_post`, or a companion plugin's own post type) sits in several users' streams, so blocks there apply per viewer when it is shown, not when it arrives. Never add a block check to one handler or to `Interactions` instead: every other handler and plugin would bypass it. A handler that dispatches an embedded activity (Announce) runs it through the same check. `test_owner_block_stops_activity()` pins this for every activity type.
- **Scheduled cron handlers must be idempotent if they have user-visible side effects** (emails, external API calls, push notifications). WP-Cron can re-enter the same callback via concurrent workers, plugin deactivate→reactivate (which re-runs `register_schedules()`), `wp cron event run`, and traffic spikes that fire overlapping loopback requests. Claim the unit of work atomically — `add_option( $key, $value, '', false )` only succeeds when the row doesn't yet exist, which makes it a race-safe sentinel — *before* the side effect runs, not after. See `Activitypub\Scheduler\Statistics::send_monthly_email()` for the canonical pattern.

## PHP Conventions

**Reuse existing functionality instead of hand-rolling it.** Before writing any parsing, validation, escaping, or formatting logic, check for a WordPress core function that does the job (e.g. `wp_kses_bad_protocol()`, `wp_parse_url()`, `wp_http_validate_url()`), then for an existing plugin helper (`includes/functions*.php`, `Sanitize`, `src/shared/`). Core primitives are battle-tested against adversarial input in ways a fresh implementation is not, and reviewers can trust them on sight. A custom implementation needs a stated reason why no existing function fits — "shorter" is not one. This applies to refactors too: never replace a core-function-based check with bespoke logic just to save a line.

**Backslash-prefix all WordPress functions** in namespaced code: `\get_option()`, `\add_action()`, `\apply_filters()`, `\__()`, `\_e()`, etc. PHP falls back to global scope, but backslashes are a project standard for consistency and to avoid accidentally shadowing globals.

**No inline namespaces.** Use `use` statements at the top of the file instead of inline fully-qualified class names (e.g., `use Activitypub\Options;` then `Options::method()`, not `\Activitypub\Options::method()`).

**For new or modified code**, use `'unreleased'` for all `@since`, `@deprecated`, and deprecation function version strings so the release script can replace them. Do not introduce new hardcoded version numbers like `'5.1.0'`; existing versioned tags in the codebase are fine.

## Testing Conventions

**Read `tests/README.md` and the `test` skill before writing or changing tests.** It covers test utilities, data factories, and detailed patterns.

**One test file per source file.** Tests mirror the file they test: `includes/rest/class-seek-controller.php` → `tests/phpunit/tests/includes/rest/class-test-seek-controller.php`. Add tests to that file; never create a test file that spans several source files. The pre-commit hook enforces this.

## Documentation Index

```
docs/development-environment.md  — wp-env setup, prerequisites, troubleshooting
docs/php-coding-standards.md     — full WordPress coding standards reference
docs/php-class-structure.md      — complete directory and class organization
docs/code-linting.md             — linting configuration and rules
docs/pull-request.md             — PR workflow details
docs/release-process.md          — release workflow and versioning
tests/README.md                  — test file layout, utilities, data factories, writing patterns
src/app/README.md                — admin React app: target architecture for new screens
FEDERATION.md                    — implemented FEPs, supported standards, compatibility
```

## Skills and Agents

Skills are complex procedures loaded on demand. Canonical files live in `.agents/skills/`, with stubs in `.claude/skills/` for Claude Code discovery.

After reading a skill, check for a local override at `~/.claude/skills/{skill-name}-local/SKILL.md`. Local overrides take precedence.
