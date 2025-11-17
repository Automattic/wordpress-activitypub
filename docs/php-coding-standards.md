# PHP Coding Standards Reference

## Table of Contents
- [WordPress Coding Standards](#wordpress-coding-standards)
- [File Organization](#file-organization)
- [Naming Conventions](#naming-conventions)
- [Documentation Standards](#documentation-standards)
- [Security Practices](#security-practices)
- [Performance Considerations](#performance-considerations)

## WordPress Coding Standards

The ActivityPub plugin follows WordPress Coding Standards with PHPCS configuration:

### PHPCS Rules Applied
- `WordPress` - Full WordPress coding standards
- `PHPCompatibility` - PHP 7.2+ compatibility
- `PHPCompatibilityWP` - WordPress 6.5+ compatibility
- `VariableAnalysis` - Detect undefined/unused variables

### Indentation and Spacing

```php
// Use tabs for indentation.
function example_function() {
→   $variable = 'value';
→   if ( $condition ) {
→   →   do_something();
→   }
}

// Space inside parentheses.
if ( $condition ) {             // Correct.
if ($condition) {               // Incorrect.

// Space around operators.
$sum = $a + $b;                 // Correct.
$sum=$a+$b;                     // Incorrect.

// Array formatting - use array() syntax.
$array = array(
→   'key_one'   => 'value',
→   'key_two'   => 'value',
→   'key_three' => 'value',
);

// DO NOT use short array syntax [] - WordPress standards require array().
$array = [                      // Incorrect - do not use.
→   'key_one'   => 'value',
→   'key_two'   => 'value',
→   'key_three' => 'value',
];
```

### Control Structures

```php
// If statements.
if ( $condition ) {
→   // Code.
} elseif ( $other_condition ) {
→   // Code.
} else {
→   // Code.
}

// Switch statements.
switch ( $variable ) {
→   case 'value1':
→   →   do_something();
→   →   break;

→   case 'value2':
→   →   do_something_else();
→   →   break;

→   default:
→   →   do_default();
}

// Loops.
foreach ( $items as $key => $item ) {
→   \process_item( $item );
}

while ( $condition ) {
→   // Code.
}

for ( $i = 0; $i < 10; $i++ ) {
→   // Code.
}
```

### Yoda Conditions

WordPress recommends Yoda conditions to prevent assignment errors:

```php
// Yoda conditions (recommended).
if ( 'value' === $variable ) {
if ( true === $condition ) {
if ( null !== $result ) {

// But readable conditions are also acceptable.
if ( $user->has_cap( 'edit_posts' ) ) {
if ( \is_array( $data ) ) {
```

## File Organization

### File Naming Patterns

```
includes/
├── class-{feature}.php         # Regular classes
├── trait-{behavior}.php        # Shared traits
├── interface-{contract}.php    # Interfaces
├── functions.php               # Global functions
├── deprecated.php              # Deprecated functions
```

### File Header Template

```php
<?php
/**
 * {Feature} class file.
 *
 * @package Activitypub
 * @subpackage {Component}
 * @since {version}
 */

namespace Activitypub\{Component};

use Activitypub\Other\Class;
use WP_Error;

/**
 * {Feature} Class.
 *
 * Handles {description of what the class does}.
 *
 * @since {version}
 */
class {Feature} {
```

## Naming Conventions

### PHP Naming Rules

| Element | Convention | Example |
|---------|-----------|---------|
| **Classes** | Pascal_Snake_Case | `class Activity_Handler` |
| **Methods** | snake_case | `public function get_followers()` |
| **Functions** | snake_case with prefix | `function activitypub_get_actor()` |
| **Properties** | snake_case | `private $actor_url` |
| **Constants** | UPPER_SNAKE_CASE | `const DEFAULT_TIMEOUT = 30` |
| **Hooks** | snake_case | `\do_action( 'activitypub_init' )` |
| **Files** | hyphen-case | `class-activity-handler.php` |
| **Namespaces** | PascalCase | `namespace Activitypub\Handler` |

### Hook Naming

```php
// Actions.
\do_action( 'activitypub_before_{action}', $param );
\do_action( 'activitypub_after_{action}', $param );

// Filters.
\apply_filters( 'activitypub_{item}', $value );
\apply_filters( 'activitypub_{item}_{context}', $value, $extra );

// Examples.
\do_action( 'activitypub_before_send_activity', $activity );
\apply_filters( 'activitypub_follower_inbox_list', $inboxes, $user_id );
```

## Documentation Standards

### Class Documentation

```php
/**
 * Short description (one line).
 *
 * Long description. Can span multiple lines and include
 * detailed information about the class purpose and usage.
 *
 * @since 1.0.0
 *
 * @see Related_Class
 * @link https://docs.example.com/
 */
class Example_Class {
```

### Method Documentation

```php
/**
 * Get followers for a specific user.
 *
 * Retrieves the list of followers from the database,
 * applies necessary filters, and returns the formatted result.
 *
 * @since 1.0.0
 *
 * @param int   $user_id    The user ID.
 * @param array $args       {
 *     Optional. An array of arguments.
 *
 *     @type int    $limit  Maximum number of results. Default 20.
 *     @type string $order  Order direction (ASC/DESC). Default DESC.
 *     @type bool   $cached Whether to use cached results. Default true.
 * }
 *
 * @return array|\WP_Error Array of followers on success, WP_Error on failure.
 */
public function get_followers( $user_id, $args = array() ) {
```

### Property Documentation

```php
class Example {
    /**
     * The actor URL.
     *
     * @since 1.0.0
     *
     * @var string
     */
    private $actor_url;

    /**
     * Cache of follower data.
     *
     * @since 1.2.0
     *
     * @var array {
     *     @type string $id       Follower ID.
     *     @type string $inbox    Inbox URL.
     *     @type string $username Username.
     * }
     */
    protected $follower_cache = array();
}
```

### Inline Documentation

```php
// Single line comment for simple clarification.

/*
 * Multi-line comment for longer explanations
 * that need multiple lines.
 */

/**
 * DocBlock for functions, classes, and methods.
 */

// TODO: Implement caching mechanism.
// FIXME: Handle edge case when actor is deleted.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Necessary for performance.
```

## Security Practices

### Input Validation and Sanitization

```php
// Sanitize text input.
$text = \sanitize_text_field( $_POST['text_field'] );

// Sanitize textarea.
$content = \sanitize_textarea_field( $_POST['content'] );

// Sanitize URL.
$url = \sanitize_url( $_POST['url'] );

// Sanitize email.
$email = \sanitize_email( $_POST['email'] );

// Sanitize key.
$key = \sanitize_key( $_POST['key'] );

// Sanitize HTML.
$html = \wp_kses_post( $_POST['html_content'] );

// Custom sanitization.
$value = \preg_replace( '/[^a-zA-Z0-9-_]/', '', $_POST['value'] );
```

### Output Escaping

```php
// Escape HTML.
echo \esc_html( $text );
echo \esc_html__( 'Translatable text', 'activitypub' );

// Escape attributes.
echo '<input value="' . \esc_attr( $value ) . '">';

// Escape URLs.
echo '<a href="' . \esc_url( $url ) . '">Link</a>';

// Escape JavaScript.
echo '<script>var data = ' . \wp_json_encode( $data ) . ';</script>';

// Escape SQL.
$sql = $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id );

// Allow specific HTML tags.
echo \wp_kses( $html, array(
    'a' => array(
        'href' => array(),
        'title' => array(),
    ),
    'br' => array(),
    'em' => array(),
    'strong' => array(),
) );
```

### Nonce Verification

```php
// Create nonce.
\wp_nonce_field( 'activitypub_action', 'activitypub_nonce' );

// Verify nonce.
if ( ! \isset( $_POST['activitypub_nonce'] ) ||
     ! \wp_verify_nonce( $_POST['activitypub_nonce'], 'activitypub_action' ) ) {
    \wp_die( \__( 'Security check failed', 'activitypub' ) );
}

// AJAX nonce.
\check_ajax_referer( 'activitypub_ajax', 'nonce' );
```

### Capability Checks

```php
// Check user capabilities.
if ( ! \current_user_can( 'edit_posts' ) ) {
    \wp_die( \__( 'Insufficient permissions', 'activitypub' ) );
}

// Custom capability.
if ( ! \current_user_can( 'activitypub_manage_followers' ) ) {
    return new \WP_Error( 'forbidden', \__( 'Access denied', 'activitypub' ) );
}

// Check specific object capability.
if ( ! \current_user_can( 'edit_post', $post_id ) ) {
    return false;
}
```

## Performance Considerations

### Caching Strategies

```php
// Use transients for temporary data.
$cache_key = 'activitypub_data_' . \md5( \serialize( $args ) );
$cached    = \get_transient( $cache_key );

if ( false === $cached ) {
    $cached = \expensive_operation();
    \set_transient( $cache_key, $cached, HOUR_IN_SECONDS );
}

// Object caching.
\wp_cache_set( 'key', $data, 'activitypub', 3600 );
$data = \wp_cache_get( 'key', 'activitypub' );

// Static caching in class.
class Example {
    private static $cache = array();

    public static function get_data( $id ) {
        if ( ! \isset( self::$cache[ $id ] ) ) {
            self::$cache[ $id ] = \fetch_data( $id );
        }
        return self::$cache[ $id ];
    }
}
```

### Database Optimization

```php
// Use get_posts() instead of WP_Query when possible.
$posts = \get_posts( array(
    'post_type'      => 'post',
    'posts_per_page' => 10,
    'meta_key'       => 'activitypub_id',
    'fields'         => 'ids', // Only get IDs if that's all you need.
) );

// Batch database operations.
$values = array();
foreach ( $items as $item ) {
    $values[] = $wpdb->prepare( '(%s, %s)', $item['key'], $item['value'] );
}

if ( $values ) {
    $wpdb->query(
        "INSERT INTO {$table} (key, value) VALUES " . \implode( ',', $values )
    );
}

// Use LIMIT in custom queries.
$results = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT * FROM {$table} WHERE status = %s LIMIT %d",
        'active',
        100
    )
);
```

## Error Handling

### WP_Error Usage

```php
// Create single error.
return new \WP_Error(
    'activitypub_invalid_actor',
    \__( 'Invalid actor URL provided', 'activitypub' ),
    array( 'status' => 400, 'actor' => $actor )
);

// Check for errors.
$result = \remote_request( $url );
if ( \is_wp_error( $result ) ) {
    \error_log( 'ActivityPub Error: ' . $result->get_error_message() );
    return $result;
}

// Add multiple errors.
$errors = new \WP_Error();

if ( \empty( $data['id'] ) ) {
    $errors->add( 'missing_id', \__( 'ID is required', 'activitypub' ) );
}

if ( \empty( $data['inbox'] ) ) {
    $errors->add( 'missing_inbox', \__( 'Inbox URL is required', 'activitypub' ) );
}

if ( $errors->has_errors() ) {
    return $errors;
}
```

### Exception Handling

```php
try {
    $result = \risky_operation();
} catch ( \Exception $e ) {
    \error_log( 'ActivityPub Exception: ' . $e->getMessage() );
    return new \WP_Error(
        'activitypub_exception',
        $e->getMessage(),
        array( 'code' => $e->getCode() )
    );
}
```
