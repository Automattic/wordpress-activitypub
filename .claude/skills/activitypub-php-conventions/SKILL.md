---
name: activitypub-php-conventions
description: PHP coding standards and WordPress patterns for ActivityPub plugin. Use when writing PHP code, creating classes, implementing WordPress hooks, structuring plugin files, or following WordPress coding conventions.
---

# ActivityPub PHP Conventions

This skill provides guidance on PHP coding standards, WordPress patterns, and architectural conventions used in the ActivityPub plugin.

**This is the authoritative source for:**
- File naming conventions
- Directory structure and organization
- WordPress coding standards
- PHP patterns and best practices

## Quick Reference

### File Naming
```
class-{name}.php         # Regular classes
trait-{name}.php         # Traits
interface-{name}.php     # Interfaces
```

### Namespace Pattern
```php
namespace Activitypub;
namespace Activitypub\Transformer;
namespace Activitypub\Collection;
namespace Activitypub\Handler;
```

### Text Domain
Always use `'activitypub'` for translations:
```php
\__( 'Text', 'activitypub' );
\_e( 'Text', 'activitypub' );
```

## File Structure

### Standard PHP File Header
```php
<?php
/**
 * Description of file.
 *
 * @package Activitypub
 */

namespace Activitypub;

use Required\Classes;
```

### Class Documentation
```php
/**
 * ActivityPub Feature_Name Class.
 *
 * @author Author Name
 */
class Feature_Name {
```

### Method Documentation
```php
/**
 * Method description.
 *
 * @param string       $param_one First parameter description.
 * @param array|object $param_two Second parameter description.
 *
 * @return mixed Return value description.
 */
public function method_name( $param_one, $param_two = array() ) {
```

## Coding Standards

See [PHP Coding Standards](../../../docs/php-coding-standards.md) for comprehensive standards.

### WordPress Coding Standards

The project follows WordPress Coding Standards with these key points:

1. **Indentation:** Use tabs, not spaces
2. **Spacing:** Space inside parentheses: `function( $param )`
3. **Naming:**
   - Classes: `Class_Name` (capitalized snake_case)
   - Methods/Functions: `method_name()` (snake_case)
   - Constants: `CONSTANT_NAME` (uppercase snake_case)
   - Variables: `$variable_name` (snake_case)

### Escaping and Sanitization

```php
// Output escaping
echo esc_html( $text );
echo esc_url( $url );
echo esc_attr( $attribute );
echo wp_kses_post( $html_content );

// Input sanitization
$text = sanitize_text_field( $_POST['field'] );
$url = sanitize_url( $_POST['url'] );
$email = sanitize_email( $_POST['email'] );
```

## Directory Organization

See [PHP Class Structure](../../../docs/php-class-structure.md) for detailed organization.

```
includes/
├── class-*.php              # Core classes
├── trait-*.php              # Traits
├── activity/                # Activity type classes
│   ├── class-accept.php
│   ├── class-follow.php
│   └── class-undo.php
├── collection/              # Collection classes
│   ├── class-followers.php
│   └── class-following.php
├── handler/                 # Activity handlers
│   ├── class-create.php
│   ├── class-delete.php
│   └── class-update.php
├── rest/                    # REST API endpoints
│   ├── class-actors.php
│   └── class-webfinger.php
├── transformer/             # Content transformers
│   ├── class-post.php
│   ├── class-comment.php
│   └── class-attachment.php
├── wp-admin/                # Admin functionality
│   └── table/              # List table classes
└── integration/             # Third-party integrations
```

## WordPress Hook Patterns

### Action Hooks
```php
// Adding actions.
\add_action( 'init', array( self::class, 'init' ) );
\add_action( 'wp_head', array( $this, 'add_meta_tags' ), 10, 0 );

// Custom actions.
\do_action( 'activitypub_before_send_activity', $activity );
```

### Filter Hooks
```php
// Adding filters.
\add_filter( 'the_content', array( self::class, 'filter_content' ), 99 );
\add_filter( 'activitypub_activity_object', array( $this, 'modify_object' ), 10, 2 );

// Custom filters.
$data = \apply_filters( 'activitypub_activity_data', $data, $post );
```

### Hook Documentation
```php
/**
 * Filters the activity object before sending.
 *
 * @param array   $activity The activity array.
 * @param WP_Post $post     The post object.
 */
$activity = \apply_filters( 'activitypub_activity_object', $activity, $post );
```

## Class Patterns

See [examples.md](examples.md) for complete examples.

### Singleton Pattern
```php
class Manager {
    /**
     * Instance.
     *
     * @var self|null
     */
    private static $instance = null;

    /**
     * Get instance.
     *
     * @return self
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor (private for singleton).
     */
    private function __construct() {
        $this->init();
    }
}
```

### Static Initialization
```php
class Feature {
    /**
     * Initialize the class.
     */
    public static function init() {
        \add_action( 'init', array( self::class, 'register' ) );
        \add_filter( 'the_content', array( self::class, 'filter_content' ) );
    }
}
```

### Transformer Pattern
```php
namespace Activitypub\Transformer;

class Custom extends Base {
    /**
     * Transform object to ActivityPub format.
     *
     * @return array The ActivityPub representation.
     */
    public function transform() {
        $object = parent::transform();
        // Custom transformation logic.
        return $object;
    }
}
```

## Error Handling

### Using WP_Error
```php
// Creating errors.
if ( empty( $data ) ) {
    return new \WP_Error(
        'activitypub_no_data',
        \__( 'No data provided', 'activitypub' ),
        array( 'status' => 400 )
    );
}

// Checking for errors.
if ( \is_wp_error( $result ) ) {
    return $result;
}

// Multiple error codes.
$error = new \WP_Error();
$error->add( 'code_1', 'Message 1' );
$error->add( 'code_2', 'Message 2' );
```

## Database Patterns

### Custom Tables (if needed)
```php
global $wpdb;
$table_name = $wpdb->prefix . 'activitypub_followers';

// Prepared statements.
$results = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT * FROM {$table_name} WHERE actor = %s",
        $actor_url
    )
);
```

### Options API
```php
// Get option with default.
$value = \get_option( 'activitypub_setting', 'default' );

// Update option.
\update_option( 'activitypub_setting', $value );

// Delete option.
\delete_option( 'activitypub_setting' );
```

### Transients
```php
// Set transient.
\set_transient( 'activitypub_cache_' . $key, $data, HOUR_IN_SECONDS );

// Get transient.
$cached = \get_transient( 'activitypub_cache_' . $key );

// Delete transient.
\delete_transient( 'activitypub_cache_' . $key );
```

## REST API Patterns

### Registering Endpoints
```php
\register_rest_route(
    ACTIVITYPUB_REST_NAMESPACE,
    '/users/(?P<user_id>\d+)/followers',
    array(
        array(
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => array( self::class, 'get_followers' ),
            'permission_callback' => array( self::class, 'permission_check' ),
            'args'                => self::get_collection_params(),
        ),
    )
);
```

### Permission Callbacks
```php
public static function permission_check( $request ) {
    return \current_user_can( 'read' );
}
```

## Security Best Practices

### Nonce Verification
```php
if ( ! isset( $_POST['_wpnonce'] ) ||
     ! \wp_verify_nonce( $_POST['_wpnonce'], 'activitypub_action' ) ) {
    \wp_die( 'Security check failed' );
}
```

### Capability Checks
```php
if ( ! \current_user_can( 'manage_options' ) ) {
    return;
}
```

### Data Validation
```php
// Validate URLs.
if ( ! \wp_http_validate_url( $url ) ) {
    return new \WP_Error( 'invalid_url' );
}

// Validate email.
if ( ! \is_email( $email ) ) {
    return new \WP_Error( 'invalid_email' );
}
```

## Common Functions

### ActivityPub Helper Functions
```php
// Get remote metadata.
$metadata = get_remote_metadata_by_actor( $actor_url );

// Convert object to URI.
$uri = object_to_uri( $object );

// Enrich content data.
$content = enrich_content_data( $content, $pattern, $callback );

// Get Webfinger resource.
$resource = Webfinger::resolve( $handle );
```

### WordPress Global Functions
When in a namespace, always escape WordPress functions with backslash: `\get_option()`, `\add_action()`, etc.

## Testing Considerations

When writing code, consider testability:

1. **Dependency Injection:** Pass dependencies as parameters
2. **Hooks for Testing:** Add filters/actions for test manipulation
3. **Pure Functions:** Separate logic from WordPress functions where possible
4. **Mock-friendly:** Structure code to allow mocking external calls

## Performance Guidelines

1. **Cache expensive operations:** Use transients
2. **Lazy loading:** Load resources only when needed
3. **Batch operations:** Process multiple items together
4. **Avoid N+1 queries:** Fetch related data in single queries
5. **Use WordPress APIs:** Leverage built-in caching
