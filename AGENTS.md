# WordPress ActivityPub Plugin

WordPress plugin implementing the ActivityPub protocol, enabling federation with Mastodon, Pixelfed, Pleroma, and other compatible platforms.

**Tech stack:** PHP 7.4+, WordPress 6.x, `@wordpress/scripts` for JS/CSS, Playwright for E2E, PHPUnit for unit/integration tests, wp-env for local dev.

Prefer reading project files and `docs/` over relying on training data for WordPress and ActivityPub patterns.

**Do NOT:**
- Use PHP 8.0+ syntax (named args, union types, `match`)
- Edit WordPress core files
- Use `remove_all_filters('pre_http_request')` in tests
- Hardcode new version numbers (use `'unreleased'`)

## Common Pitfalls

- **PHP 7.4 compatibility.** CI tests against PHP 7.4. No named arguments, no union types, no `match` (PHP 8.0+).
- **Never edit WordPress core files.** Only modify plugin code.
- **Pre-commit hooks modify files.** If the hook changed files, stage and commit again — do not assume the first commit succeeded.
- **`remove_all_filters('pre_http_request')` is forbidden in tests.** The pre-commit hook blocks this. Use targeted filter removal.
- **Changelog entries MUST be end-user friendly and end with punctuation.** Users see these in the WordPress update screen. Describe what changed from their perspective — no jargon, class names, or method names.
- **`post_date_gmt` may be empty.** Check for `0000-00-00` or empty values.
- **Scheduled cron handlers must be idempotent if they have user-visible side effects** (emails, external API calls, push notifications). WP-Cron can re-enter the same callback via concurrent workers, plugin deactivate→reactivate (which re-runs `register_schedules()`), `wp cron event run`, and traffic spikes that fire overlapping loopback requests. Claim the unit of work atomically — `add_option( $key, $value, '', false )` only succeeds when the row doesn't yet exist, which makes it a race-safe sentinel — *before* the side effect runs, not after. See `Activitypub\Scheduler\Statistics::send_monthly_email()` for the canonical pattern.

## PHP Conventions

**MUST** backslash-prefix all WordPress functions in namespaced code: `\get_option()`, `\add_action()`, `\apply_filters()`, `\__()`, `\_e()`, etc. PHP falls back to global scope, but backslashes are a project standard for consistency and to avoid accidentally shadowing globals.

**No inline namespaces.** Use `use` statements at the top of the file instead of inline fully-qualified class names (e.g., `use Activitypub\Options;` then `Options::method()`, not `\Activitypub\Options::method()`).

**For new or modified code**, MUST use `'unreleased'` for all `@since`, `@deprecated`, and deprecation function version strings so the release script can replace them. Do not introduce new hardcoded version numbers like `'5.1.0'`; existing versioned tags in the codebase are fine.

## Testing Conventions

See `tests/README.md` for test utilities, data factories, and detailed patterns.

## Documentation Index

```
docs/development-environment.md  — wp-env setup, prerequisites, troubleshooting
docs/php-coding-standards.md     — full WordPress coding standards reference
docs/php-class-structure.md      — complete directory and class organization
docs/code-linting.md             — linting configuration and rules
docs/pull-request.md             — PR workflow details
docs/release-process.md          — release workflow and versioning
tests/README.md                  — test utilities, data factories, writing patterns
src/app/README.md                — admin React app: target architecture for new screens
FEDERATION.md                    — implemented FEPs, supported standards, compatibility
```

## Skills and Agents

Skills are complex procedures loaded on demand. Canonical files live in `.agents/skills/`, with stubs in `.claude/skills/` for Claude Code discovery.

**CRITICAL:** After reading a skill, check for a local override at `~/.claude/skills/{skill-name}-local/SKILL.md`. Local overrides take precedence.
