---
name: activitypub-release
description: Version management and release processes using Jetpack Changelogger. Use when creating releases, managing changelogs, bumping versions, or preparing patch releases.
---

# ActivityPub Release Process

This skill provides guidance on managing releases and changelogs for the WordPress ActivityPub plugin.

## Quick Reference

### Changelog Commands
```bash
# Create release PR
npm run release
```

### Version Locations
- `activitypub.php` - Plugin header
- `readme.txt` - WordPress.org readme
- `package.json` - npm version
- `CHANGELOG.md` - Changelog file

## Major/Minor Releases

### Process Overview
1. Generate version bump PR with `npm run release`
2. Review and merge PR
3. Create GitHub release from trunk

### Using Release Script

```bash
# Run from plugin root
npm run release

# Script will:
# - Determine version from changelogs
# - Update version numbers
# - Update CHANGELOG.md
# - Create PR
```

## Patch Releases

### Process Overview
1. Create branch from the release tag to patch
2. Cherry-pick fixes
3. Update changelog manually
4. Create release from branch

### Cherry-picking Fixes

```bash
# Create branch from the tag of the release to patch
git fetch --tags
git checkout -b release/5.3.1 5.3.0  # Create 5.3.1 branch from 5.3.0 tag

# Cherry-pick merge commits from trunk
git cherry-pick -m 1 <commit-hash>

# Update changelog
composer changelog:write

# Manually update versions in:
# - activitypub.php
# - readme.txt

# Push the branch
git push -u origin release/5.3.1
```

## Changelog Management

### How Changelog Works

Changelogs are managed automatically through the PR process:

1. **PR Template** (`.github/PULL_REQUEST_TEMPLATE.md`):
   - Check "Automatically create a changelog entry" checkbox
   - Select significance level (Patch/Minor/Major)
   - Select change type (Added/Fixed/Changed/Deprecated/Removed/Security)
   - Write clear message ending with punctuation

2. **GitHub Action** (`.github/workflows/changelog.yml`):
   - Automatically creates changelog file from PR description
   - Validates message has proper punctuation
   - Creates file in `.github/changelog/` directory

3. **Release Process**:
   - `npm run release` aggregates all changelog entries
   - Updates `CHANGELOG.md` and `readme.txt` automatically

**Requirements:**
- **Always end messages with punctuation!**
- Never mention AI tools or coding assistants
- Focus on user impact
- Be clear and concise

### Changelog Format

```markdown
## [1.0.0] - 2024-01-15
### Added
- New feature description.

### Fixed
- Bug fix description.

### Changed
- Updated feature description.
```

## Version Numbering

### Semantic Versioning
- **Major (X.0.0)** - Breaking changes
- **Minor (0.X.0)** - New features
- **Patch (0.0.X)** - Bug fixes only

### Version Update Locations

1. **activitypub.php:**
```php
/**
 * Plugin Name: ActivityPub
 * Version: 1.0.0
 */
```

2. **readme.txt:**
```
Stable tag: 1.0.0
```

3. **package.json:**
```json
{
  "version": "1.0.0"
}
```

## Creating GitHub Release

1. Go to repository releases page
2. Click "Draft a new release"
3. Create new tag with version number
4. Select target branch (trunk or release branch)
5. Generate release notes
6. Publish release

