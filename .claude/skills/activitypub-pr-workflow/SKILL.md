---
name: activitypub-pr-workflow
description: Pull request creation and review workflow for WordPress ActivityPub plugin. Use when creating branches, managing PRs, adding changelogs, assigning reviewers, or following branch naming conventions.
---

# ActivityPub PR Workflow

Workflow guidance for creating and managing pull requests in the WordPress ActivityPub plugin repository.

## Quick Reference

### Branch Naming
```bash
add/{feature}    # New features.
update/{feature} # Iterating on existing features.
fix/{bug}        # Bug fixes.
try/{idea}       # Experimental ideas.
```

**Reserved branches:**
- `release/{X.Y.Z}` - Release process only.
- `trunk` - Main development branch.

### PR Requirements
- ✅ Changelog entry (or "Skip Changelog" label).
- ✅ Passing CI checks.
- ✅ Clean merge with trunk.
- ✅ **Always assign @me**.
- ✅ **Always add Fediverse team as reviewer**.
- ✅ Proper branch naming.

### Quick Commands
```bash
# Create and switch to branch.
git checkout -b add/new-feature

# Create PR with GitHub CLI.
gh pr create --assignee @me --reviewer Fediverse

# Check PR status and CI.
gh pr status
gh pr checks
```

## Comprehensive Workflow Guide

See [Pull Request Guide](../../../docs/pull-request.md) for complete PR workflow, detailed checklist, and git best practices.

## Critical Rules

### AI Tool Usage

**CRITICAL:** Never add Claude Code as a co-author or mention AI tools in:
- Commit messages
- PR descriptions
- PR comments
- Code comments
- Anywhere in the codebase

This is a hard requirement for all PRs.

### Assignment and Review

**Required for every PR:**
- **Always assign yourself** as assignee.
- **Always add Fediverse** as reviewer.
- Add other relevant reviewers if needed.

## Changelog Management

The repository uses Jetpack Changelogger for automated changelog generation.

### PR Template Changelog Section

Every PR template (`.github/PULL_REQUEST_TEMPLATE.md`) includes changelog fields:

**CRITICAL:** You must use the exact template markup from `.github/PULL_REQUEST_TEMPLATE.md`. Do not create custom formatting - use the actual checkboxes `- [x]` and `<details>` sections exactly as shown in the template.

1. **Check "Automatically create a changelog entry" checkbox**
2. **Select Significance:** Patch/Minor/Major
3. **Select Type:** Added/Changed/Deprecated/Removed/Fixed/Security
4. **Write Message:** Clear description that **must end with punctuation!**

**OR** add "Skip Changelog" label if no changelog entry needed.

### Changelog Requirements

**Always end changelog messages with proper punctuation:**
```
✅ Add support for custom post types.
✅ Fix signature verification bug.
❌ Add support for custom post types
❌ Fix signature verification bug
```

See [Release Process - Changelog Management](../activitypub-release/SKILL.md) for complete changelog details.

### Manual Changelog Entry

If you forgot changelog in PR:
```bash
composer changelog:add  # Interactive prompt.
git add .
git commit -m "Add changelog entry"
git push
```

## Development Workflow Best Practices

### Break Down Features

**Important:** Break features into small, focused PRs. Each piece should be its own PR.

**Good approach:**
```
PR 1: Add database schema
PR 2: Add REST endpoint
PR 3: Add UI component
PR 4: Add tests
```

**Bad approach:**
```
Single PR with all changes
```

**For multi-PR features:**
1. Create tracking issue.
2. Link all related PRs to issue.
3. Use consistent naming: `add/feature-part-1`, `add/feature-part-2`.
4. Merge in order.

### Branch Creation Workflow

```bash
# Always branch from trunk.
git checkout trunk
git pull origin trunk

# Create branch with appropriate prefix.
git checkout -b fix/notification-issue

# Make changes, commit frequently.
git add .
git commit -m "Add follower validation"

# Push to remote.
git push -u origin fix/notification-issue
```

### Pre-Push Checks

Before creating PR or pushing changes:

```bash
# Check PHP standards.
composer lint
composer lint:fix  # Auto-fix issues.

# Check JavaScript/CSS if you made changes.
npm run lint:js
npm run lint:css

# Run tests.
npm run env-test

# Build assets if needed.
npm run build
```

See [Development Cycle](../activitypub-dev-cycle/SKILL.md) for complete testing and linting commands.

### Creating the PR

1. Push your branch to remote.
2. Create PR via GitHub UI or `gh pr create`.
3. Fill out PR template completely.
4. Add changelog entry (or "Skip Changelog" label).
5. **Assign yourself**.
6. **Add Fediverse as reviewer**.
7. Mark as draft if not ready for review.

### Review and Iteration

After receiving feedback:
```bash
# Make requested changes.
git add .
git commit -m "Address review feedback"
git push

# CI re-runs automatically.
# Re-request review after addressing feedback.
```

## Special PR Types

### Hotfixes
For urgent fixes:
1. Branch from trunk: `fix/critical-issue`.
2. Minimal changeset.
3. Add "Hotfix" label.
4. Request expedited review.

### Experimental Changes
For trying ideas:
1. Use `try/` prefix.
2. Mark as draft PR.
3. Request feedback early.
4. Convert to proper branch type once approach confirmed.

## Keeping Branch Updated

```bash
# Fetch latest trunk.
git fetch origin

# Rebase your branch.
git rebase origin/trunk

# Resolve conflicts if any, then continue.
git add .
git rebase --continue

# Force push (safe if you're the only one on the branch).
git push --force-with-lease
```

**When to rebase:** Always rebase before final merge to trunk for clean history.

See [Pull Request Guide](../../../docs/pull-request.md) for detailed rebase and merge conflict resolution.

## Common PR Labels

- `[Type] Bug` - Bug fixes.
- `[Type] Enhancement` - New features.
- `[Type] Documentation` - Doc updates.
- `Skip Changelog` - No changelog needed.
- `Needs Review` - Ready for review.
- `In Progress` - Still working.
- `Hotfix` - Urgent fix.
