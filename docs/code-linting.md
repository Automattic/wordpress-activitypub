# Linting and Code Quality Reference

## Table of Contents
- [PHP Code Standards](#php-code-standards)
- [JavaScript Standards](#javascript-standards)
- [CSS Standards](#css-standards)
- [Pre-commit Automation](#pre-commit-automation)
- [Fixing Common Issues](#fixing-common-issues)

## PHP Code Standards

### Quick Commands

```bash
# Check PHP code standards
composer lint

# Auto-fix PHP issues
composer lint:fix

# Check specific file
vendor/bin/phpcs path/to/file.php

# Fix specific file
vendor/bin/phpcbf path/to/file.php
```

### PHPCS Configuration

The project uses WordPress Coding Standards with custom rules defined in `.phpcs.xml.dist`:

**Key Rules:**
- WordPress-Core standards
- WordPress-Docs standards
- WordPress-Extra standards
- Custom variable analysis
- PHPCompatibility checks for PHP 7.2+

**Excluded Patterns:**
- `vendor/` directory
- `node_modules/` directory
- `build/` directory
- Third-party integration files

### Common PHP Standards

For complete PHP coding standards including:
- File headers and documentation
- Class and method documentation
- Naming conventions
- Spacing and indentation

See [PHP Coding Standards](php-coding-standards.md).

**Important:** All DocBlock descriptions must end with proper punctuation (periods).

## JavaScript Standards

### Quick Commands

```bash
# Check JavaScript
npm run lint:js

# Check CSS
npm run lint:css

# Format all JavaScript/CSS
npm run format
```

### JavaScript Configuration

Uses `@wordpress/scripts` with WordPress coding standards:

**Key Rules:**
- ESLint with WordPress configuration
- Prettier for formatting
- React/JSX support
- ES6+ syntax

### Common JavaScript Standards

Uses `@wordpress/scripts` with WordPress coding standards. Key points:
- Prefer `const`/`let` over `var`
- Use WordPress import organization (external, WordPress, internal)
- Follow WordPress ESLint configuration

## CSS Standards

### Stylelint Configuration

```bash
# Check CSS files
npm run lint:css

# Auto-fix CSS issues
npm run format
```

**Key Rules:**
- WordPress CSS coding standards
- Alphabetical property ordering
- Consistent spacing
- No vendor prefixes (handled by build)

### CSS Best Practices

```css
/* Component naming */
.activitypub-component {
    /* Alphabetical properties */
    background: #fff;
    border: 1px solid #ddd;
    margin: 10px;
    padding: 15px;
}

/* BEM-style modifiers */
.activitypub-component--active {
    background: #f0f0f0;
}

/* Child elements */
.activitypub-component__title {
    font-size: 1.2em;
    font-weight: bold;
}
```

## Pre-commit Automation

### What Happens on Commit

The `.githooks/pre-commit` hook automatically:

1. **Sorts PHP imports:**
   - Organizes `use` statements alphabetically
   - Groups by type (classes, functions, constants)

2. **Checks for unused imports:**
   - Detects unused `use` statements
   - Blocks commit if found

3. **Validates test patterns:**
   - Prevents `remove_all_filters( 'pre_http_request' )` 
   - Suggests using `remove_filter()` instead

4. **Runs PHPCS auto-fix:**
   - Applies coding standards automatically
   - Fixes spacing, indentation, etc.

5. **Formats JavaScript:**
   - Runs Prettier on JS/JSX files
   - Ensures consistent formatting

**Better approach - fix issues:**
```bash
# Fix PHP issues
composer lint:fix

# Fix JS issues
npm run format

# Then commit normally
git add .
git commit -m "Fixed: Issue description"
```

## Fixing Common Issues

### PHP Issues

**Issue: "Missing file comment"**
```php
// Add at top of file:
<?php
/**
 * File description.
 *
 * @package Activitypub
 */
```

**Issue: "Unused use statement"**
```php
// Remove unused imports or use them.
use Unused\Class; // Remove this.
```

**Issue: "Expected 1 space after closing parenthesis"**
```php
// Bad.
if ($condition){

// Good.
if ( $condition ) {
```

**Issue: "Array double arrow not aligned"**
```php
// Bad.
$array = array(
    'short' => 1,
    'longer_key' => 2,
);

// Good.
$array = array(
    'short'      => 1,
    'longer_key' => 2,
);
```

### JavaScript Issues

**Issue: "Prefer const over let"**
```javascript
// Bad
let unchangedValue = 'constant';

// Good
const unchangedValue = 'constant';
```

**Issue: "Missing JSDoc comment"**
```javascript
// Add documentation
/**
 * Function description.
 *
 * @param {string} param Parameter description.
 * @return {boolean} Return description.
 */
function functionName( param ) {
```

**Issue: "Import order"**
```javascript
// Correct order:
// 1. External dependencies
import external from 'package';

// 2. WordPress dependencies
import { Component } from '@wordpress/element';

// 3. Internal dependencies
import internal from './file';
```

### CSS Issues

**Issue: "Properties not alphabetically ordered"**
```css
/* Bad */
.class {
    padding: 10px;
    margin: 5px;
    background: #fff;
}

/* Good */
.class {
    background: #fff;
    margin: 5px;
    padding: 10px;
}
```

**Issue: "Unexpected vendor prefix"**
```css
/* Bad */
.class {
    -webkit-border-radius: 5px;
    border-radius: 5px;
}

/* Good - let build tools handle prefixes */
.class {
    border-radius: 5px;
}
```

## Custom Project Rules

### PHP Specific Rules

1. **Text Domain:** Always use `'activitypub'`
   ```php
   \__( 'Text', 'activitypub' );
   ```

2. **Namespace:** Follow pattern
   ```php
   namespace Activitypub\Feature_Name;
   ```

3. **File Naming:** Use prefix pattern
   ```
   class-*.php for classes
   trait-*.php for traits
   interface-*.php for interfaces
   ```

### Ignored Files

The following are excluded from linting:
- `vendor/` - Composer dependencies
- `node_modules/` - npm dependencies
- `build/` - Compiled assets
- `.wordpress-org/` - WordPress.org assets

## Running Targeted Checks

### Check Changed Files Only

```bash
# PHP files changed in current branch
git diff --name-only HEAD~1 | grep '\.php$' | xargs vendor/bin/phpcs

# JavaScript files changed
git diff --name-only HEAD~1 | grep '\.js$' | xargs npm run lint:js --
```

### Check Specific Directories

```bash
# Check all transformer classes
vendor/bin/phpcs includes/transformer/

# Check all JavaScript in src
npm run lint:js -- src/**/*.js
```

### Generate Reports

```bash
# Generate PHP report
vendor/bin/phpcs --report=summary

# Generate detailed report
vendor/bin/phpcs --report=full > phpcs-report.txt

# Generate JSON report for CI
vendor/bin/phpcs --report=json > phpcs-report.json
```
