# Pull Request Checklist

## Before Creating PR

### Code Preparation
- [ ] Branch created from latest `trunk`
- [ ] Branch follows naming convention (`add/`, `update/`, `fix/`, `try/`)
- [ ] Changes are focused and single-purpose
- [ ] Code follows [WordPress coding standards](../activitypub-php-conventions/coding-standards.md)
- [ ] No debug code or console.logs left

### Testing
- [ ] PHP tests pass: `npm run env-test`
- [ ] Linting passes: `composer lint`
- [ ] JavaScript linting passes: `npm run lint:js`
- [ ] No regressions in existing functionality

### Documentation
- [ ] Changelog entry created: `composer changelog:add`
- [ ] Changelog entry ends with proper punctuation
- [ ] Code comments added where needed
- [ ] README updated if adding new feature
- [ ] Inline documentation follows WordPress standards (trailing periods, etc.)

## Creating the PR

### PR Description
- [ ] Clear, descriptive title
- [ ] Summary section explains the change
- [ ] Testing instructions provided
- [ ] Screenshots added for visual changes
- [ ] Related issue linked (if applicable)

### GitHub Settings
- [ ] Self-assigned as assignee
- [ ] pfefferle added as reviewer
- [ ] Appropriate labels added
- [ ] Milestone set (if applicable)

## After Creating PR

### CI/CD
- [ ] All CI checks passing
- [ ] No merge conflicts with trunk
- [ ] Code coverage maintained or improved

### Review Process
- [ ] Responded to all review comments
- [ ] Requested re-review after changes
- [ ] Resolved conversations that are addressed
- [ ] Thanked reviewers for their time

## Before Merge

### Final Checks
- [ ] Branch is up to date with trunk
- [ ] All review feedback addressed
- [ ] CI still passing after final changes
- [ ] Changelog entry still accurate
- [ ] No accidental files included

### Clean History
- [ ] Commits are logical and well-organized
- [ ] Fixup commits squashed
- [ ] Commit messages are clear
- [ ] No merge commits (use rebase)

## PR Description Template

```markdown
## Summary
<!-- Brief description of what this PR does -->

## Why?
<!-- Explain the motivation for this change -->

## Changes
<!-- List the specific changes made -->
-
-
-

## Testing Instructions
<!-- Step-by-step instructions to test -->
1.
2.
3.

## Screenshots
<!-- If applicable, add before/after screenshots -->
### Before
![Before]()

### After
![After]()

## Checklist
- [ ] Changelog entry added
- [ ] Tests added/updated
- [ ] Documentation updated
- [ ] Follows coding standards

## Related Issues
<!-- Link to related issues -->
Fixes #
Related to #
```

## Special Situations

### Hotfix PR
- [ ] Marked with "Hotfix" label
- [ ] Minimal changeset
- [ ] Tested thoroughly despite urgency
- [ ] Changelog marks as patch release

### Breaking Changes
- [ ] Marked with "Breaking Change" label
- [ ] Migration guide provided
- [ ] Major version bump indicated
- [ ] Deprecation notices added where needed

### New Feature
- [ ] Feature flag added (if applicable)
- [ ] Documentation added
- [ ] Examples provided
- [ ] Performance impact assessed

### Bug Fix
- [ ] Root cause identified
- [ ] Test added to prevent regression
- [ ] Related issues linked
- [ ] Verified fix doesn't break other features

## Common Review Feedback

### Code Quality
- "Please add error handling here"
- "This could use a comment explaining why"
- "Consider extracting this to a method"
- "Please add type hints"

### Testing
- "Please add a test for this edge case"
- "Can you verify this works with [scenario]"
- "What happens when [condition]"

### Documentation
- "Please update the docblock"
- "The changelog needs more detail"
- "Can you add an example"

### Performance
- "This could cause N+1 queries"
- "Consider caching this result"
- "This might be expensive for large datasets"

## Commit Message Guidelines

### Format
```
Type: Brief description

Longer explanation if needed.
Multiple paragraphs are fine.

Fixes #123
```

### Types
- `Add:` New feature
- `Fix:` Bug fix
- `Update:` Enhancement to existing feature
- `Remove:` Removed functionality
- `Refactor:` Code restructuring
- `Test:` Test additions/changes
- `Docs:` Documentation only

### Examples
```
Fix: Correct signature verification for Delete activities

The signature verification was failing for Delete activities
because the actor URL was not being properly extracted.

This commit extracts the actor from the activity object
and uses it for verification.

Fixes #456
```

**Important:** Never mention AI tools, coding assistants, or automation tools in commit messages, PR descriptions, code comments, or anywhere in the repository.

## Resources

- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/)
- [GitHub PR Documentation](https://docs.github.com/en/pull-requests)
- [Project Release Process](../release-process.md)
- [Project Contributing Guide](../../../CONTRIBUTING.md)
