---
name: summary
description: Summarize the current session including changes made, files modified, and next steps. Use at the end of a session or when the user asks for a summary.
---

# Session Summary

When invoked, provide a concise summary of the current session.

## Summary Format

### Changes Made
- List all commits created (with commit messages)
- List PRs created or updated (with links)
- List branches created or modified

### Files Modified
- Group by type (PHP, CSS, JS, config, etc.)
- Note significant changes briefly

### Key Decisions
- Document any important decisions made during the session
- Note any trade-offs or alternatives considered

### Open Items
- List any unfinished tasks
- Note blockers or pending reviews
- Mention follow-up actions needed

### Commands Run
- List significant commands (tests, builds, deployments)
- Note any failures or warnings

## Example Output

```
## Session Summary

### Changes Made
- Created branch `fix/notification-issue`
- Commit: "Fix notification query for deleted actors"
- PR #123 created: https://github.com/...

### Files Modified
- PHP: `includes/class-notification.php`, `includes/model/class-actor.php`
- Tests: `tests/test-notification.php`

### Key Decisions
- Used soft delete instead of hard delete for actors
- Chose to filter at query level rather than display level

### Open Items
- [ ] Waiting for review on PR #123
- [ ] Need to add migration for existing data

### Commands Run
- `composer lint` - passed
- `npm run test:e2e` - passed
```
