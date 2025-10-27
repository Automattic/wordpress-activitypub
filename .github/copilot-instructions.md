# Copilot Instructions for wordpress-activitypub

## Repository Overview

This is the **ActivityPub WordPress Plugin** that enables WordPress sites to join the fediverse. The plugin implements the ActivityPub protocol, allowing WordPress blogs to federate with platforms like Mastodon, Pleroma, and other ActivityPub-compliant services.

**Size**: ~966MB (includes node_modules and vendor)  
**Languages**: PHP (118 files in includes/), JavaScript/TypeScript (33 files in src/)  
**Type**: WordPress plugin  
**License**: MIT  
**PHP Version**: 7.2+ (tested up to 8.4)  
**WordPress Version**: 6.5+ minimum  
**Node Version**: 20 LTS

## Build System & Dependencies

### Initial Setup

**ALWAYS run these commands in order when starting work:**

```bash
npm install    # Installs JavaScript dependencies (takes ~60 seconds)
npm run build  # Builds JavaScript assets (takes ~5 seconds)
```

**Note**: `composer install` can take 2+ minutes and may timeout. If vendor dependencies are already present, you can skip this. The CI workflows handle composer dependencies automatically.

### Build Commands

- **`npm run build`** - Formats code with prettier and builds all JavaScript/TypeScript assets. Takes ~5-10 seconds. Produces warnings about Sass deprecations (these are expected and non-blocking).
- **`npm run dev`** - Development mode with watch enabled
- **`npm run format`** - Runs wp-scripts format (prettier) on all files
- **`npm run lint:js`** - Lints JavaScript files (will show errors in assets/js/*.js files - these are legacy files)
- **`npm run lint:css`** - Lints SCSS/CSS files
- **`npm run test:unit`** - Runs Jest unit tests for JavaScript
- **`npm run test:e2e`** - Runs Playwright E2E tests (requires wp-env running)

### Testing with wp-env

wp-env provides a local WordPress environment using Docker. The configuration is in `.wp-env.json`.

**Start the environment:**
```bash
npm run env-start
# or
npm run env -- start
```

**Stop the environment:**
```bash
npm run env-stop
```

**Run PHP tests:**
```bash
npm run env-test              # Runs all PHPUnit tests
npm run env-test -- --filter=test_name  # Run specific test
npm run env-test -- tests/phpunit/tests/includes/class-test-migration.php  # Run specific file
```

**Important**: The local WordPress site runs at `http://localhost:8888` (NOT http://localhost).

### PHP Testing

**Without wp-env** (requires manual WordPress test environment setup):
```bash
composer install  # WARNING: Can timeout (2+ minutes)
composer run lint  # Runs phpcs
composer run lint:fix  # Runs phpcbf to auto-fix issues
composer run test:wp-env  # Runs phpunit via wp-env
```

**Note**: PHP testing via composer typically requires wp-env to be running. The install-wp-tests.sh script in the CI sets up a test database.

## CI/CD Workflows

All workflows are in `.github/workflows/`. Here are the key checks that MUST pass:

### 1. PHPUnit Tests (`.github/workflows/phpunit.yml`)
- Triggered by PHP file changes, composer.json, phpunit.xml.dist
- Tests against PHP 7.2-8.4 and WordPress 6.5+
- **Key requirement**: Tests must pass on multiple PHP/WordPress versions
- Runs code coverage analysis with Xdebug on PHP 8.4
- **Warning**: Coverage warnings about invalid @covers annotations will fail the build

### 2. PHPCS Linting (`.github/workflows/phpcs.yml`)
- Triggered by PHP file changes
- Runs `./vendor/bin/phpcs` with WordPress coding standards
- Uses PHP 7.4 for linting
- **Must pass**: No coding standard violations allowed
- Standards: WordPress, PHPCompatibility (7.2+), PHPCompatibilityWP, VariableAnalysis
- Configuration: `phpcs.xml` in root

### 3. Jest Tests (`.github/workflows/jest.yml`)
- Triggered by JS/JSX/TS/TSX changes in src/ (excludes tests/e2e/)
- Runs `npm run test:unit`
- Uses Node 20

### 4. Playwright E2E Tests (`.github/workflows/playwright.yml`)
- Triggered by changes to tests/e2e/, REST endpoints, package files
- Starts wp-env, runs `npm run test:e2e`
- 20 minute timeout
- Tests REST API endpoints and core functionality

### 5. Format Check (`.github/workflows/format.yml`)
- Runs `npm run format` and commits changes if needed
- Only runs on PRs to trunk branch
- Auto-commits formatting changes (or fails if from fork)

### 6. Changelog Check (`.github/workflows/changelog.yml`)
- Ensures PRs include changelog entries (or "Skip Changelog" label)
- Use `composer changelog:add` to create entries
- Changelog files stored in `.github/changelog/`

## Code Structure

### Core Plugin Entry Point
- **`activitypub.php`** - Main plugin file, defines constants, registers hooks, loads autoloader

### PHP Code Organization (`includes/`)

**Main classes** (in `includes/`):
- `class-activitypub.php` - Core plugin initialization
- `class-dispatcher.php` - Handles sending ActivityPub activities
- `class-handler.php` - Handles incoming ActivityPub activities  
- `class-comment.php` - Integrates WordPress comments with ActivityPub
- `class-signature.php` - HTTP signature verification
- `class-webfinger.php` - WebFinger protocol implementation
- `class-migration.php` - Handles database migrations between versions
- `functions.php` - Utility functions (50KB+, many helpers)
- `constants.php` - Defines plugin constants

**Subdirectories**:
- `activity/` - ActivityPub activity types (Create, Like, Follow, etc.)
- `collection/` - Collections (Followers, Following, etc.)
- `handler/` - Incoming activity handlers
- `model/` - Data models (User, Blog, Follower, Application)
- `rest/` - REST API controllers (Inbox, Outbox, Actors, Webfinger, NodeInfo, etc.)
- `scheduler/` - Scheduled task handlers
- `signature/` - Signature verification implementations
- `transformer/` - Transforms WordPress posts to ActivityPub objects
- `wp-admin/` - WordPress admin interface integration

### JavaScript/TypeScript Code (`src/`)

**Block Editor Plugins**:
- `command-palette/` - Command palette integration (TypeScript)
- `editor-plugin/` - Core editor enhancements
- `follow-me/` - Follow button block
- `followers/` - Followers list block
- `reactions/` - Reactions block
- `remote-reply/` - Remote reply functionality
- `reply/` - Reply block
- `reply-intent/` - Reply intent handling
- `shared/` - Shared utilities and components (including modal)

Built assets go to `build/` (not committed but generated by `npm run build`).

### Test Structure

**PHP Tests** (`tests/phpunit/`):
- `tests/phpunit/tests/` - Test classes (prefix: `class-test-*.php`)
- `tests/phpunit/bootstrap.php` - Test bootstrap
- Test configuration: `phpunit.xml.dist`
- Groups can be used: `npm run env-test -- --group=migration`

**JavaScript Tests**:
- Inline with source: `src/*/__tests__/*.test.js` or `*.test.tsx`
- Configuration: `jest.config.js`
- Uses `@wordpress/scripts` test configuration

**E2E Tests** (`tests/e2e/`):
- `tests/e2e/specs/` - Test specifications
- `tests/e2e/playwright.config.js` - Playwright configuration
- Tests REST API endpoints

## Configuration Files

- **`package.json`** - npm scripts and dependencies
- **`composer.json`** - PHP dependencies and scripts
- **`phpcs.xml`** - PHP_CodeSniffer rules (WordPress standards, PHP 7.2+ compatibility)
- **`phpunit.xml.dist`** - PHPUnit configuration
- **`.wp-env.json`** - WordPress environment configuration
- **`tsconfig.json`** - TypeScript configuration (extends @wordpress/scripts)
- **`.prettierrc.js`** - Prettier configuration (120 char line width)
- **`.prettierignore`** - Files to skip formatting
- **`jest.config.js`** - Jest test configuration

## Development Workflow Requirements

### Before Making Changes

1. **Always run the existing tests first** to understand baseline:
```bash
npm run test:unit  # Check JS tests
npm run env-start && npm run env-test  # Check PHP tests
```

2. **Always build before committing**:
```bash
npm run build
```

### Making Changes

1. **For PHP changes**:
   - Follow WordPress coding standards (enforced by phpcs.xml)
   - Add `@covers` annotations for test methods
   - Test against PHP 7.2+ compatibility
   - Run: `composer run lint` (if vendor/bin/phpcs exists)

2. **For JavaScript changes**:
   - Use TypeScript where possible (`.tsx` files)
   - Follow WordPress JavaScript standards
   - Run: `npm run lint:js`
   - Add tests in `__tests__/` directory

3. **Test your changes**:
```bash
npm run build         # Always build after JS changes
npm run env-test      # Run PHP tests (wp-env must be running)
npm run test:unit     # Run JS tests
npm run test:e2e      # Run E2E tests (if touching REST endpoints)
```

### Pull Request Requirements

**MUST include**:
- Changelog entry (run `composer changelog:add`) OR "Skip Changelog" label
- All CI checks passing (phpunit, phpcs, jest, playwright)
- Branch naming: `add/`, `update/`, `fix/`, or `try/` prefix

**Branch from**: `trunk` (not `main` or `master`)

**PR Guidelines** (from `docs/pull-request.md`):
- Keep PRs focused and small
- Include before/after screenshots for UI changes
- Rebase against trunk if needed
- Squash fixup commits

### Common Issues & Workarounds

**Issue**: `composer install` timeout  
**Solution**: Skip if vendor/ exists; CI handles it

**Issue**: Build warnings about Sass @import deprecations  
**Solution**: These are expected and non-blocking. The build succeeds despite warnings.

**Issue**: Legacy JS lint errors in `assets/js/*.js`  
**Solution**: These files use older patterns (jQuery, `var`). Only fix if modifying those files.

**Issue**: Invalid @covers annotations in tests  
**Solution**: Ensure @covers annotations reference valid class::method. The coverage job will fail if invalid.

**Issue**: wp-env not starting  
**Solution**: Check Docker is running. Try `npm run env-stop` then `npm run env-start`.

**Issue**: Tests fail with "Could not connect to database"  
**Solution**: Ensure wp-env is running: `npm run env-start`

## Important Notes

- **Never commit `node_modules/`, `vendor/`, or `build/`** - They're in `.gitignore`
- **Always test on PHP 7.2+** - The plugin must maintain backward compatibility
- **WordPress minimum version is 6.5** - Don't use features from newer versions
- **Rewrite rules required** - Blog-wide profiles need mod_rewrite enabled
- **ActivityPub specifics**: 
  - Posts are sent to followers via delayed cron (up to 15 minutes)
  - Only new posts after following are federated (not historical)
  - HTTP Signatures required for authentication
  - Supports WebFinger, NodeInfo, and multiple FEPs (see FEDERATION.md)

## Key Files at Root

```
activitypub.php       - Main plugin file
composer.json         - PHP dependencies & scripts
package.json          - JS dependencies & scripts  
phpcs.xml             - Coding standards config
phpunit.xml.dist      - PHP test config
.wp-env.json          - Local WordPress env config
tsconfig.json         - TypeScript config
jest.config.js        - JS test config
.prettierrc.js        - Code formatting config
README.md             - Project documentation
CONTRIBUTING.md       - Contribution guidelines
FEDERATION.md         - Federation protocol details
CHANGELOG.md          - Version history
```

## Trust These Instructions

These instructions have been validated by testing the build, lint, and test commands. Only search for additional information if these instructions are incomplete or you encounter errors not documented here. The build process is stable and well-established.
