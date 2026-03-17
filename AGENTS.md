# WordPress ActivityPub Plugin

WordPress plugin implementing the ActivityPub protocol, enabling federation with Mastodon, Pixelfed, Pleroma, and other compatible platforms.

**Tech stack:** PHP 7.4+, WordPress 6.x, `@wordpress/scripts` for JS/CSS, Playwright for E2E, PHPUnit for unit/integration tests, wp-env for local dev.

Prefer reading project files and `docs/` over relying on training data for WordPress and ActivityPub patterns.

**Do NOT:**
- Use PHP 8.0+ syntax (named args, union types, `match`)
- Edit WordPress core files
- Use `remove_all_filters('pre_http_request')` in tests
- Hardcode new version numbers (use `'unreleased'`)

## Directory Structure

```
activitypub.php                 # Main plugin file.
includes/
├── class-*.php                 # Core classes (Activitypub, Dispatcher, Scheduler, Signature…).
├── model/                      # Actor models (User, Blog, Application — extend Actor).
├── activity/                   # Activity type classes (Activity, Follow, Undo…).
├── collection/                 # Collection classes (Followers, Following).
├── handler/                    # Incoming activity handlers (Follow, Create, Delete, Like…).
├── rest/                       # REST API controllers (Actors, Inbox, Outbox, Followers…).
├── transformer/                # Content transformers (Post, Comment, Event → AP objects).
└── wp-admin/                   # Admin UI functionality.
integration/                    # Third-party plugin integrations (root level).
tests/
├── phpunit/                    # PHPUnit tests.
└── e2e/                        # Playwright E2E tests.
```

## Commands

```bash
# Environment
npm run env-start               # Start WordPress at http://localhost:8888.
npm run env-stop                # Stop WordPress environment.

# PHP tests (require wp-env running)
npm run env-test                            # All PHP tests.
npm run env-test -- --filter=pattern        # Tests matching pattern.
npm run env-test -- path/to/test.php        # Specific test file.
npm run env-test -- --group=name            # Tests with @group annotation.

# E2E tests (require wp-env running) — tests live in tests/e2e/specs/
npm run test:e2e                            # All E2E tests.
npm run test:e2e:debug                      # Debug with Playwright inspector.
npx playwright test tests/e2e/specs/path/to/test.js --config tests/e2e/playwright.config.js  # Specific file.

# JavaScript tests
npm run test:unit                           # Unit tests.

# Code quality
composer lint                   # Check PHP coding standards (PHPCS).
composer lint:fix               # Auto-fix PHP issues.
npm run lint:js                 # Check JavaScript.
npm run lint:css                # Check CSS.

# Build
npm run build                   # Production build (formatted + minified).
npm run dev                     # Development watch mode.
```

## Common Pitfalls

- **PHP 7.4 compatibility.** CI tests against PHP 7.4. No named arguments, no union types, no `match` (PHP 8.0+).
- **Never edit WordPress core files.** Only modify plugin code.
- **Pre-commit hooks modify files.** If the hook changed files, stage and commit again — do not assume the first commit succeeded.
- **`remove_all_filters('pre_http_request')` is forbidden in tests.** The pre-commit hook blocks this. Use targeted filter removal.
- **Changelog entries MUST be end-user friendly and end with punctuation.** Users see these in the WordPress update screen. Describe what changed from their perspective — no jargon, class names, or method names.
- **`post_date_gmt` may be empty.** Check for `0000-00-00` or empty values.

## Pre-commit Hooks

Automated hooks (`.githooks/pre-commit`) run on `git commit`: sort PHP imports, check unused imports, validate test patterns, run PHPCS auto-fix, format JavaScript.

**CRITICAL:** Hooks modify staged files automatically. If hooks make changes, you MUST stage and commit again. Setup: `npm run prepare` (automatic after `npm install`).

## PHP Conventions

Files: `class-{name}.php` | `trait-{name}.php` | `interface-{name}.php`

Namespaces: `Activitypub`, `Activitypub\{Transformer,Collection,Handler,Activity,Rest,Model}`

Text domain: always `'activitypub'`.

**MUST** backslash-prefix all WordPress functions in namespaced code: `\get_option()`, `\add_action()`, `\apply_filters()`, `\__()`, `\_e()`, etc. PHP falls back to global scope, but backslashes are a project standard for consistency and to avoid accidentally shadowing globals.

**For new or modified code**, MUST use `'unreleased'` for all `@since`, `@deprecated`, and deprecation function version strings so the release script can replace them. Do not introduce new hardcoded version numbers like `'5.1.0'`; existing versioned tags in the codebase are fine.

## Testing Conventions

Tests use namespace `Activitypub\Tests` and extend `WP_UnitTestCase`. E2E tests import from `@wordpress/e2e-test-utils-playwright` with fixtures: `request`, `admin`, `page`, `requestUtils`.

Use `@group` annotations to categorize tests (`@group activitypub`, `@group federation`).

See `tests/README.md` for test utilities, data factories, and detailed patterns.

## Architectural Patterns

**Actor types** — The plugin supports 3 actor types: `User` (WordPress users), `Blog` (site-wide blog actor), and `Application` (system-level). All extend `Actor` base class. See `includes/model/`.

**Transformers** — Convert WordPress content into ActivityPub objects. Extend `Activitypub\Transformer\Base`. See `includes/transformer/`.

**Handlers** — Process incoming activities from remote servers. One handler per activity type. See `includes/handler/`.

**Collections** — Implement AP collections (Followers, Following). See `includes/collection/`.

**REST Controllers** — Expose AP endpoints under `ACTIVITYPUB_REST_NAMESPACE`. See `includes/rest/`.

**Key helpers** (see `includes/functions-*.php` and `includes/class-webfinger.php`): `get_remote_metadata_by_actor()`, `object_to_uri()`, `Webfinger::resolve()`.

**Custom hooks:** see `includes/class-activitypub.php` and `includes/class-dispatcher.php` for the plugin's main actions and filters.

## Documentation Index

```
docs/development-environment.md  — wp-env setup, prerequisites, troubleshooting
docs/php-coding-standards.md     — full WordPress coding standards reference
docs/php-class-structure.md      — complete directory and class organization
docs/code-linting.md             — linting configuration and rules
docs/pull-request.md             — PR workflow details
docs/release-process.md          — release workflow and versioning
tests/README.md                  — test utilities, data factories, writing patterns
FEDERATION.md                    — implemented FEPs, supported standards, compatibility
```

## Skills and Agents

Skills are complex procedures loaded on demand. Canonical files live in `.agents/skills/`, with stubs in `.claude/skills/` for Claude Code discovery.

**CRITICAL:** After reading a skill, check for a local override at `~/.claude/skills/{skill-name}-local/SKILL.md`. Local overrides take precedence.

| Skill | Use when… |
|-------|-----------|
| **pr** | Creating or reviewing pull requests. MUST invoke before any PR creation. |
| **release** | Creating releases, bumping versions, managing changelogs. |
| **federation** | Working with ActivityPub protocol, federation mechanics, or debugging. |
| **integrations** | Adding or debugging third-party plugin integrations. |

| Agent | Trigger |
|-------|---------|
| **summary** | Auto-invoked when session ends (goodbye/done/thanks). |
| **code-review** | Auto-invoked before PR creation to review changes. |
| **spec-check** | Audit endpoints against W3C ActivityPub and SWICG specs. |
| **bug-bounty** | Pick easiest open bug, fix with tests, create draft PR. Runs in background. |
| **patch-release** | Create a patch release by cherry-picking fixes onto a release branch. |
| **security-audit** | Audit for SSRF, auth bypass, content disclosure, XSS, and content negotiation issues. |
