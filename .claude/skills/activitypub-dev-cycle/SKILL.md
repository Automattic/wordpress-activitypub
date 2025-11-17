---
name: activitypub-dev-cycle
description: Development workflows for WordPress ActivityPub plugin including wp-env setup, testing commands, linting, and build processes. Use when setting up development environment, running tests, checking code quality, building assets, or working with wp-env.
---

# ActivityPub Development Cycle

This skill provides guidance for common development workflows in the WordPress ActivityPub plugin.

**This is the authoritative source for:**
- Environment setup and wp-env management
- Testing commands and workflows
- Linting and code quality tools
- Build processes

## Quick Reference

### Environment Management
```bash
npm run env-start    # Start WordPress at http://localhost
npm run env-stop     # Stop WordPress environment
```

### Testing Commands
```bash
# Run all PHP tests
npm run env-test

# Run specific test
npm run env-test -- --filter=test_name

# Run tests from specific file
npm run env-test -- tests/phpunit/tests/path/to/test.php

# Run tests by group
npm run env-test -- --group=migration
```

### Code Quality
```bash
# PHP linting
composer lint         # Check PHP coding standards
composer lint:fix     # Auto-fix PHP issues

# JavaScript linting
npm run lint:js       # Check JavaScript
npm run lint:css      # Check CSS styles

# Format all code
npm run format        # Runs wp-scripts format
```

### Building Assets
```bash
npm run build         # Format and build for production
npm run dev           # Start development watch mode
```

## Initial Setup

1. **Clone and install dependencies:**
   ```bash
   git clone git@github.com:Automattic/wordpress-activitypub.git
   cd wordpress-activitypub
   npm install
   composer install
   ```

2. **Configure git hooks:**
   ```bash
   npm run prepare  # Sets up pre-commit hooks
   ```

3. **Start development environment:**
   ```bash
   npm run env-start
   ```
   WordPress will be available at http://localhost:8888

For detailed setup instructions, see [environment-setup.md](environment-setup.md).

## Testing Workflows

See [Testing Reference](../../../tests/README.md) for comprehensive testing guidance.

### Running Tests

**Basic test execution:**
```bash
npm run env-test
```

**Common PHPUnit arguments:**
- `--filter=pattern` - Run tests matching pattern
- `--group=name` - Run tests with specific @group
- `--exclude-group=name` - Skip tests with @group
- `--verbose` - Show detailed output
- `--debug` - Display debugging information

**Examples:**
```bash
# Test specific functionality
npm run env-test -- --filter=Notification

# Test specific file
npm run env-test -- tests/phpunit/tests/includes/class-test-notification.php

# Run migration tests only
npm run env-test -- --group=migration
```

### Code Coverage

Generate coverage reports with Xdebug:

```bash
# Start environment with coverage support
npm run env-start -- --xdebug=coverage

# Generate text coverage report
npm run env-test -- --coverage-text

# Generate HTML coverage report
npm run env-test -- --coverage-html ./coverage
open coverage/index.html  # View report (macOS)
```

## Code Quality Standards

See [linting.md](linting.md) for detailed linting configuration.

### PHP Standards

The project uses WordPress Coding Standards with custom rules:
- Run `composer lint` to check standards
- Run `composer lint:fix` to auto-fix issues
- Configuration in `.phpcs.xml.dist`

### JavaScript Standards

Uses `@wordpress/scripts` for linting:
- Run `npm run lint:js` for JavaScript
- Run `npm run lint:css` for stylesheets
- Configuration extends WordPress standards

## Pre-commit Hooks

The repository uses automated pre-commit hooks (`.githooks/pre-commit`) that:

1. **Sort PHP imports** - Automatically organizes use statements
2. **Check unused imports** - Prevents unused use statements
3. **Validate test patterns** - Blocks `remove_all_filters('pre_http_request')`
4. **Run PHPCS auto-fix** - Applies coding standards
5. **Format JavaScript** - Runs wp-scripts formatter

**Important:** Hooks modify files automatically. Always review changes before committing.

## Build Process

### Development Build
```bash
npm run dev  # Starts watch mode with source maps
```

### Production Build
```bash
npm run build  # Formats and minifies for production
```

The build process:
1. Runs `wp-scripts format` on JavaScript files
2. Builds with experimental modules support
3. Outputs to `build/` directory

## Common Development Tasks

### Start Fresh Environment
```bash
npm run env-stop
npm run env-start
```

### Run Tests After Code Changes
```bash
npm run env-test -- --filter=ChangedFeature
```

### Check Code Before Committing
```bash
composer lint
npm run lint:js
```

### Debug Failing Tests
```bash
# Run with verbose output
npm run env-test -- --verbose --filter=failing_test

# Check test groups
npm run env-test -- --group=specific_group
```

### E2E Testing
```bash
# Run Playwright E2E tests
npm run test:e2e

# Debug mode
npm run test:e2e:debug
```

## Environment Variables

wp-env automatically sets up WordPress with:
- WordPress latest version
- Debug mode enabled
- ActivityPub plugin activated
- Test user accounts created

## Troubleshooting

### Port Conflicts
If port 80 is in use:
```bash
wp-env stop
# Then check what's using the port
lsof -i :80  # macOS/Linux
```

### Permission Issues
Ensure Docker is running and you have proper permissions.

## Key Files

- `package.json` - npm scripts and dependencies
- `composer.json` - PHP dependencies and scripts
- `.wp-env.json` - wp-env configuration
- `.phpcs.xml.dist` - PHP coding standards
- `.githooks/pre-commit` - Git hook configuration
