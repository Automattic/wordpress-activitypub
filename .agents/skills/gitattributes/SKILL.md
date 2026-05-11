---
name: gitattributes
description: Use when auditing or updating .gitattributes export-ignore coverage so dev-only files (lint configs, CI, tests, docs, build tooling) don't ship in the WordPress.org plugin zip. Run before a release, after adding a new top-level file or config, or when a tool is renamed (e.g. .eslintrc.js → eslint.config.cjs).
---

# .gitattributes Export-Ignore Audit

Ensure the WordPress.org release archive (`git archive` output) contains only runtime files. Dev tooling, tests, CI, and repo meta must be `export-ignore`d.

## Why This Matters

The WordPress.org zip is built via `git archive`. Anything without `export-ignore` ends up on every user's site, bloating the install and shipping dev-only code. Equally bad: stale `export-ignore` entries for files that no longer exist look tidy but protect nothing.

## Quick Audit (one command)

```bash
# Entries in the release archive at the repository root
git archive --format=tar HEAD | tar -t | awk -F/ '{print $1}' | sort -u

# Same, but uses the working-tree .gitattributes (use while iterating on edits)
git archive --worktree-attributes --format=tar HEAD | tar -t | awk -F/ '{print $1}' | sort -u
```

`git archive HEAD` reads `.gitattributes` from the commit, so uncommitted fixes won't show up. Use `--worktree-attributes` to preview a pending change before committing.

The output should contain ONLY runtime artifacts. For this plugin that's:
`LICENSE`, `readme.txt`, `activitypub.php`, `assets`, `build`, `includes`, `integration`, `patterns`, `templates`.

Anything else in the list is a gap — add an `export-ignore` rule.

## Cross-Check Tracked vs Ignored

```bash
# Top-level tracked entries
git ls-tree --name-only HEAD | sort > /tmp/tracked.txt

# Entries referenced in .gitattributes
grep export-ignore .gitattributes | awk '{print $1}' | sed 's|^/||; s|/$||' | sort > /tmp/ignored.txt

# Stale entries (in .gitattributes but no longer tracked)
comm -23 /tmp/ignored.txt /tmp/tracked.txt
```

Stale entries aren't dangerous, but they rot and mislead future readers. Delete them.

## What Belongs in Each Bucket

| Category | Examples | Rule |
|----------|----------|------|
| Runtime PHP | `activitypub.php`, `includes/`, `integration/` | keep |
| Runtime assets | `assets/`, `build/`, `patterns/`, `templates/` | keep |
| WordPress.org meta | `readme.txt`, `LICENSE` | keep |
| Dev dotfiles | `.editorconfig`, `.prettierrc.js`, `.stylelintrc.json`, `eslint.config.cjs`, `.wp-env.json` | export-ignore |
| Repo meta | `.github/`, `.githooks/`, `.gitignore`, `.gitattributes`, `.wordpress-org/` | export-ignore |
| Agent/AI tooling | `.agents/`, `.claude/`, `AGENTS.md`, `CLAUDE.md` | export-ignore |
| Build & package configs | `package.json`, `package-lock.json`, `composer.json`, `webpack.config.js`, `tsconfig.json`, `jest.config.js`, `jest.setup.js` | export-ignore |
| QA configs | `phpcs.xml`, `phpunit.xml.dist` | export-ignore |
| Source (pre-build) | `src/` | export-ignore (shipping `build/` instead) |
| Tests | `tests/` | export-ignore |
| Docs for contributors | `docs/`, `snippets/`, `CHANGELOG.md`, `CODE_OF_CONDUCT.md`, `CONTRIBUTING.md`, `FEDERATION.md`, `README.md`, `SECURITY.md` | export-ignore |
| Scripts | `bin/`, `local/` | export-ignore |

## When to Run This Audit

- Before tagging a release.
- After adding or renaming any top-level file, dotfile, or config.
- After adopting a new dev tool (lint, test runner, bundler).
- When a linter flat-config migration renames files (e.g. `.eslintrc.js` → `eslint.config.cjs`).

## Writing Rules

- Use a leading `/` to anchor to the repo root: `/phpcs.xml`, not `phpcs.xml`.
- Use a trailing `/` for directories: `/tests/`.
- Keep grouping comments tidy (dotfiles, build configs, source/tests, docs, linguist).

## Linguist Attributes (bonus)

Also used in `.gitattributes` to shape GitHub's language stats:

- `/build/**  linguist-generated` — hides machine-built output.
- `/docs/**   linguist-documentation` — keeps docs out of the language bar.
- `/package-lock.json  linguist-generated` — hides lockfile churn.

These don't affect the archive, but live in the same file, so keep them current too.

## Red Flags

- Release zip is larger than expected → something new is leaking in.
- New dev dependency added, `.gitattributes` untouched → likely a gap.
- Lint tool migrated to a new config filename → old entry is stale, new file is leaking.
- Top-level file renamed → old export-ignore is dead weight, new one is missing.
