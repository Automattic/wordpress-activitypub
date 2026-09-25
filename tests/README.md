# Testing Reference

This file provides detailed testing patterns and examples. For basic test commands, see the [Development Environment documentation](../docs/development-environment.md#testing-workflows).

## Table of Contents
- [Test Organization](#test-organization)
- [Writing Effective Tests](#writing-effective-tests)
- [Common Test Patterns](#common-test-patterns)
- [Debugging Tests](#debugging-tests)


## Test Organization

### PHP Test Structure

```
tests/phpunit/
├── bootstrap.php           # Test bootstrap file
├── includes/               # Shared test helpers (base test cases, stubs, traits)
├── data/                   # Fixtures, mocks and other test data
└── tests/                  # The tests, mirroring the plugin's file structure
    ├── includes/           # Tests for includes/, e.g. includes/rest/class-test-seek-controller.php
    └── integration/        # Tests for integration/ (third-party plugin integrations)
```

### One Test File per Source File

Every test file mirrors the source file it tests: same path below `tests/phpunit/tests/`, file name prefixed with `class-test-`.

| Source file | Test file |
|---|---|
| `includes/class-query.php` | `tests/phpunit/tests/includes/class-test-query.php` |
| `includes/rest/class-seek-controller.php` | `tests/phpunit/tests/includes/rest/class-test-seek-controller.php` |
| `includes/rest/trait-event-stream.php` | `tests/phpunit/tests/includes/rest/class-test-trait-event-stream.php` |
| `integration/stream/class-connector.php` | `tests/phpunit/tests/integration/stream/class-test-connector.php` |

- Add new tests to the existing test file of the source file they cover. Tests for a REST route go into the test file of the controller that registers it.
- Do not create test files that span several source files. If a test needs a new kind of file, document the pattern here first.
- Shared setup belongs in `tests/phpunit/includes/`, not in a test file.

The pre-commit hook enforces this with `bin/precommit/check-test-file-location.js`. A few older test files predate the rule and are listed as exceptions there; do not add to that list.

### Test Groups

Use `@group` annotations to organize tests. Run with: `npm run env-test -- --group=groupname`.

Common groups: `migration`, `rest`, `integration`.

### Test Naming Conventions

```php
// Test class names.
class Test_Notification extends \WP_UnitTestCase {}

// Test method names.
public function test_send_notification() {}
public function test_notification_with_invalid_data() {}
public function test_should_handle_empty_followers() {}
```

## Writing Effective Tests

### PHP Test Template

```php
<?php
/**
 * Test_Feature_Name class.
 *
 * @package Activitypub
 * @group activitypub
 */

namespace Activitypub\Tests;

use Activitypub\Feature_Name;

/**
 * Test Feature_Name functionality.
 */
class Test_Feature_Name extends \WP_UnitTestCase {
    /**
     * Set up test environment.
     */
    public function set_up(): void {
        parent::set_up();
        // Test-specific setup.
    }

    /**
     * Tear down test environment.
     */
    public function tear_down(): void {
        // Clean up.
        parent::tear_down();
    }

    /**
     * Test basic functionality.
     */
    public function test_basic_feature() {
        // Arrange.
        $input = 'test';

        // Act.
        $result = Feature_Name::process( $input );

        // Assert.
        $this->assertEquals( 'expected', $result );
    }
}
```

### Common Assertions

```php
// Equality assertions.
$this->assertEquals( $expected, $actual );
$this->assertSame( $expected, $actual );  // Strict comparison.
$this->assertNotEquals( $expected, $actual );

// Type assertions.
$this->assertIsArray( $value );
$this->assertIsString( $value );
$this->assertIsBool( $value );

// WordPress assertions.
$this->assertWPError( $result );
$this->assertNotWPError( $result );
$this->assertQueryTrue( 'is_single', 'is_singular' );

// Count assertions.
$this->assertCount( 3, $array );
$this->assertEmpty( $value );
$this->assertNotEmpty( $value );

// Exception assertions.
$this->expectException( Exception::class );
$this->expectExceptionMessage( 'Error message' );
```

## Debugging Tests

### Debugging Techniques

1. **Print debugging:**
   ```php
   var_dump( $variable );
   print_r( $array );
   error_log( print_r( $data, true ) );
   ```

2. **Stop on failure:**
   ```bash
   npm run env-test -- --stop-on-failure
   ```

3. **Verbose output:**
   ```bash
   npm run env-test -- --verbose --debug
   ```

4. **Single test isolation:**
   ```bash
   npm run env-test -- --filter=test_specific_method
   ```

5. **Check test database:**
   ```bash
   npm run env -- run tests-cli wp db query "SELECT * FROM wp_posts"
   ```

### Common Issues and Solutions

**Issue: Tests pass individually but fail together**
- Check for test interdependencies
- Ensure proper tearDown() cleanup
- Look for global state modifications

**Issue: Different results locally vs CI**
- Check PHP/WordPress versions
- Verify timezone settings
- Check for environment-specific code

**Issue: Timeout errors**
- Increase timeout in test
- Check for infinite loops
- Verify external API mocks

**Issue: Database not reset between tests**
- Check transaction rollback
- Ensure proper cleanup in tearDown()
- Look for direct database writes

### Test Utilities

**Creating test data:**
```php
// Create test user.
$user_id = self::factory()->user->create( [
    'role' => 'editor',
] );

// Create test post.
$post_id = self::factory()->post->create( [
    'post_author' => $user_id,
    'post_status' => 'publish',
] );

// Create test term.
$term_id = self::factory()->term->create( [
    'taxonomy' => 'category',
] );
```

**Mocking HTTP requests:**

**IMPORTANT:** Always save filter callbacks to a variable so they can be removed properly. NEVER use `remove_all_filters()` or `remove_all_actions()` as they can break other tests and plugins.

```php
// CORRECT: Save callback to variable for proper removal.
$http_filter_callback = function( $preempt, $args, $url ) {
    if ( strpos( $url, 'example.com' ) !== false ) {
        return array(
            'response' => array( 'code' => 200 ),
            'body' => json_encode( array( 'success' => true ) ),
        );
    }
    
    return $preempt;
};

// Add the filter.
\add_filter( 'pre_http_request', $http_filter_callback, 10, 3 );

// Do your test...

// Remove the specific filter.
\remove_filter( 'pre_http_request', $http_filter_callback, 10 );

// NEVER DO THIS - it breaks other tests and plugins:
// remove_all_filters( 'pre_http_request' );  // ❌ WRONG
// remove_all_actions( 'some_action' );       // ❌ WRONG
```

**Time-based testing:**
```php
// Mock current time.
$now = '2024-01-01 12:00:00';
add_filter( 'current_time', function() use ( $now ) {
    return $now;
} );
```
