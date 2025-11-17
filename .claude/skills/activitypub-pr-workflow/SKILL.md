---
name: activitypub-pr-workflow
description: Pull request creation and review workflow for WordPress ActivityPub plugin. Use when creating branches, managing PRs, adding changelogs, assigning reviewers, or following branch naming conventions.
---

# ActivityPub PR Workflow

This skill provides guidance for creating and managing pull requests in the WordPress ActivityPub plugin repository.

## Quick Reference

### Branch Naming
```bash
add/{feature}    # New features
update/{feature} # Iterating on existing features
fix/{bug}        # Bug fixes
try/{idea}       # Experimental ideas
```

### PR Requirements
- ✅ Changelog entry (or "Skip Changelog" label)
- ✅ Passing CI checks
- ✅ Clean merge with trunk
- ✅ Assign @me
- ✅ Add Fediverse team as reviewer
- ✅ Proper branch naming

## Creating a Branch

### Branch Naming Convention

Choose the appropriate prefix:

| Prefix | Use Case | Example |
|--------|----------|---------|
| `add/` | New feature or functionality | `add/mastodon-api` |
| `update/` | Enhance existing feature | `update/follower-list` |
| `fix/` | Fix a bug | `fix/signature-verification` |
| `try/` | Experimental idea for feedback | `try/new-transformer` |

**Reserved branches:**
- `release/{X.Y.Z}` - Used for release process only
- `trunk` - Main development branch

### Creating Your Branch

```bash
# Always branch from trunk
git checkout trunk
git pull origin trunk

# Create new branch with appropriate prefix
git checkout -b fix/notification-issue

# Or for a new feature
git checkout -b add/custom-emoji-support
```

## Development Workflow

See [Pull Request Guide](../../../docs/pull-request.md) for complete PR workflow and checklist.

### 1. Break Down Features

**Important:** Break your feature into small pieces. Each piece should be its own PR.

Good approach:
- PR 1: Add database schema
- PR 2: Add REST endpoint
- PR 3: Add UI component
- PR 4: Add tests

Bad approach:
- Single PR with all changes

### 2. Commit Best Practices

```bash
# Make frequent commits
git add .
git commit -m "Add follower validation"

# Squash minor fixes
git rebase -i HEAD~3

# Force push to your branch (safe if you're the only one)
git push --force-with-lease origin fix/something
```

**CRITICAL:** Never add Claude Code as a co-author or mention AI tools in commit messages.

### 3. Prepare Changelog Information

For the PR template changelog section:
- Select type: Added/Fixed/Changed/Deprecated/Removed/Security
- Write clear description
- **Always end with proper punctuation!**

See [Release Process - Changelog Management](../activitypub-release/SKILL.md#changelog-management) for complete details.

## Creating the Pull Request

### PR Template

The repository uses `.github/PULL_REQUEST_TEMPLATE.md` which includes:

1. **Changelog Entry Section:**
   - Check "Automatically create a changelog entry" checkbox
   - Select **Significance** (Patch/Minor/Major)
   - Select **Type** (Added/Changed/Deprecated/Removed/Fixed/Security)
   - Write **Message** (must end with punctuation!)
   - OR add "Skip Changelog" label if no changelog needed

2. **PR Description:**
   - Summary of changes
   - Testing instructions
   - Screenshots for visual changes

See the full template at `.github/PULL_REQUEST_TEMPLATE.md`

### Assignment and Review

**Required for every PR:**
- **Always assign yourself** as assignee
- **Always add Fediverse** as reviewer
- Add other relevant reviewers if needed

**CRITICAL:** Never mention Claude Code in commits, PR descriptions, comments, or anywhere in the codebase.

### PR Checklist

Before marking ready for review:

- [ ] Changelog details filled in PR template (or "Skip Changelog" label added)
- [ ] All CI checks passing
- [ ] Branch merges cleanly with trunk
- [ ] Code follows WordPress standards (`composer lint`)
- [ ] Tests added/updated if applicable
- [ ] Documentation updated if needed
- [ ] Self-assigned and reviewer added

## Code Quality

### Pre-Push Checks

```bash
# Check PHP standards
composer lint
composer lint:fix

# Check JavaScript/CSS if needed
npm run lint:js
npm run lint:css

# Run tests
npm run env-test

# Check for merge conflicts
git fetch origin
git merge origin/trunk --no-commit --no-ff
git merge --abort  # If just checking
```

### Handling CI Failures

If CI fails:
1. Check the specific failing job
2. Fix locally and test
3. Push fix to your branch
4. CI will re-run automatically

## Review Process

### Responding to Feedback

```bash
# Make requested changes
git add .
git commit -m "Address review feedback"
git push

# Or amend if minor
git commit --amend
git push --force-with-lease
```

### Re-requesting Review

After addressing feedback:
1. Click "Re-request review" button
2. Leave comment summarizing changes
3. Ensure CI still passes

## Keeping Branch Updated

### Rebasing Against Trunk

```bash
# Fetch latest
git fetch origin

# Rebase your branch
git rebase origin/trunk

# Resolve conflicts if any
git add .
git rebase --continue

# Force push
git push --force-with-lease
```

### When to Rebase vs Merge

- **Rebase:** For clean history before merging
- **Merge:** If branch is shared with others
- **Always rebase** before final merge to trunk

## Special Cases

### Large Features

For features requiring multiple PRs:
1. Create tracking issue
2. Link all related PRs to issue
3. Use consistent branch naming: `add/feature-part-1`, `add/feature-part-2`
4. Merge in order

### Hotfixes

For urgent fixes:
1. Branch from trunk: `fix/critical-issue`
2. Minimal changeset
3. Add "Hotfix" label
4. Request expedited review

### Experimental Changes

For trying ideas:
1. Use `try/` prefix
2. Mark as draft PR
3. Request feedback early
4. Convert to proper branch type once approach confirmed

## Common Issues

### Merge Conflicts

```bash
# Update your branch
git fetch origin
git rebase origin/trunk

# Resolve conflicts
# Edit conflicted files
git add .
git rebase --continue

# Or abort if needed
git rebase --abort
```

### Changelog Conflicts

If changelog conflicts:
1. Keep both changes
2. Ensure proper formatting
3. Verify entries are in correct section

### Forgotten Changelog

If you forgot changelog before PR:
```bash
# On your branch
composer changelog:add

# Commit and push
git add .
git commit -m "Add changelog entry"
git push
```

## Best Practices

### Do's
- ✅ Small, focused PRs
- ✅ Descriptive branch names
- ✅ Clear commit messages
- ✅ Test locally first
- ✅ Add screenshots for UI changes
- ✅ Update documentation

### Don'ts
- ❌ Large, multi-purpose PRs
- ❌ Force push to trunk
- ❌ Merge without review
- ❌ Skip changelog without label
- ❌ Ignore CI failures
- ❌ Leave PRs in draft unnecessarily

## PR Labels

Common labels to use:
- `[Type] Bug` - Bug fixes
- `[Type] Enhancement` - New features
- `[Type] Documentation` - Doc updates
- `Skip Changelog` - No changelog needed
- `Needs Review` - Ready for review
- `In Progress` - Still working

## Quick Commands

```bash
# Create branch
git checkout -b add/new-feature

# Check standards
composer lint

# Create PR (using GitHub CLI)
gh pr create --assignee @me --reviewer Fediverse

# Check PR status
gh pr status

# View PR checks
gh pr checks
```
