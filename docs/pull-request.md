# Pull Request Guide

This guide covers the complete lifecycle of a pull request, from planning to merge.

## Table of Contents
- [Planning Your PR](#planning-your-pr)
- [Creating a Branch](#creating-a-branch)
- [Development Process](#development-process)
- [Before Creating PR](#before-creating-pr)
- [Creating the PR](#creating-the-pr)
- [After Creating PR](#after-creating-pr)
- [Before Merge](#before-merge)
- [PR Description Template](#pr-description-template)
- [Special Situations](#special-situations)
- [Commit Message Guidelines](#commit-message-guidelines)

## Planning Your PR

When you're first starting out, your natural instinct when creating a new feature will be to create a local feature branch, and start building away. If you start doing this, *stop*, take your hands off the keyboard, grab a coffee and read on. :)

**It's important to break your feature down into small pieces first**, each piece should become its own pull request.

Once you know what the first small piece of your feature will be, follow this general process while working.

## Creating a Branch

### Branch Naming Scheme

All changes should be developed in a new branch created from the `trunk` branch.

Branches use the following naming conventions:

* `add/{something}` -- When you are adding a completely new feature
* `update/{something}` -- When you are iterating on an existing feature
* `fix/{something}` -- When you are fixing something broken in a feature
* `try/{something}` -- When you are trying out an idea and want feedback

For example, you can run: `git checkout trunk` and then `git checkout -b fix/whatsits` to create a new `fix/whatsits` branch off of `origin/trunk`.

The ActivityPub repo uses the following "reserved" branch name conventions:

* `release/{X.Y.Z}` -- Used for the release process.

## Development Process

### Develop and Commit

1. Start developing and pushing out commits to your new branch.
    - Push your changes out frequently and try to avoid getting stuck in a long-running branch or a merge nightmare. Smaller changes are much easier to review and to deal with potential conflicts.
    - Don't be afraid to change, [squash](http://gitready.com/advanced/2009/02/10/squashing-commits-with-rebase.html), and rearrange commits or to force push - `git push --force-with-lease origin fix/something-broken`. Keep in mind, however, that if other people are committing on the same branch then you can mess up their history. You are perfectly safe if you are the only one pushing commits to that branch.
    - Squash minor commits such as typo fixes or [fixes to previous commits](http://fle.github.io/git-tip-keep-your-branch-clean-with-fixup-and-autosquash.html) in the pull request.
1. If you have [Composer installed](https://getcomposer.org/), you can run `composer install` and `composer lint [directory or files updated]` to check your changes against WordPress coding standards. Please ensure your changes respect current coding standards.
1. If you end up needing more than a few commits, consider splitting the pull request into separate components. Discuss in the new pull request and in the comments why the branch was broken apart and any changes that may have taken place that necessitated the split. Our goal is to catch early in the review process those pull requests that attempt to do too much.

### Create a Changelog Entry

Before you push your changes, make sure you create a changelog entry. Those entries provide useful information to end-users and other developers about the changes you've made, and how they can impact their WordPress site.

#### How do I create a changelog entry?

You can use the `composer changelog:add` command to create a changelog entry, and then follow the prompt and commit the changelog file that was created for you.

## Before Creating PR

Use this checklist before opening your pull request:

### Code Preparation
- [ ] Branch created from latest `trunk`
- [ ] Branch follows naming convention (`add/`, `update/`, `fix/`, `try/`)
- [ ] Changes are focused and single-purpose
- [ ] Code follows [WordPress coding standards](php-coding-standards.md)
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

When you feel that you are ready for a formal review or for merging into `trunk`, push your branch to GitHub and open a Pull Request.

As you open your Pull Request, make sure you check the following:

### PR Description
- [ ] Clear, descriptive title
- [ ] Summary section explains the change
- [ ] Testing instructions provided
- [ ] Screenshots added for visual changes
- [ ] Related issue linked (if applicable)

### GitHub Settings
- [ ] Self-assigned as assignee
- [ ] Automattic/fediverse added as reviewer
- [ ] Appropriate labels added
- [ ] Milestone set (if applicable)

### Required Checks
- [ ] Make sure your Pull Request includes a changelog entry, or add the "Skip Changelog" label to the PR.
- [ ] Make sure all required checks listed at the bottom of the Pull Request are passing.
- [ ] Make sure your branch merges cleanly and consider rebasing against `trunk` to keep the branch history short and clean.
- [ ] If there are visual changes, add before and after screenshots in the pull request comments.
- [ ] If possible add unit tests.
- [ ] Provide helpful instructions for the reviewer so they can test your changes. This will help speed up the review process.

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

When creating a pull request, GitHub will automatically populate the description field with the [pull request template](../.github/PULL_REQUEST_TEMPLATE.md).

This template includes:
- **Issue reference**: Link to the issue being fixed
- **Proposed changes**: What functional changes are included
- **Testing instructions**: Step-by-step guide for reviewers
- **Changelog entry**: Option to auto-generate changelog from PR details

The template helps ensure consistency and provides reviewers with all necessary context.

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

Fixes #123.
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

Fixes #456.
```


## Resources

- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/)
- [GitHub PR Documentation](https://docs.github.com/en/pull-requests)
- [Development Environment Setup](development-environment.md)
- [Testing Reference](../tests/README.md)
- [PHP Coding Standards](php-coding-standards.md)
- [Code Linting and Quality](code-linting.md)
- [Contributing Guide](../CONTRIBUTING.md)
