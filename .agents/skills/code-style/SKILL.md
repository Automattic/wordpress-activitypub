---
name: code-style
description: PHP coding standards and WordPress patterns for ActivityPub plugin. Use when writing PHP code, creating classes, implementing WordPress hooks, or structuring plugin files.
---

# ActivityPub PHP Conventions

Plugin-specific conventions and architectural patterns for the ActivityPub plugin.

## Quick Reference

### File Naming
```
class-{name}.php         # Regular classes.
trait-{name}.php         # Traits.
interface-{name}.php     # Interfaces.
```

### Namespace Pattern
```php
namespace Activitypub;
namespace Activitypub\Transformer;
namespace Activitypub\Collection;
namespace Activitypub\Handler;
namespace Activitypub\Activity;
namespace Activitypub\Rest;
```

### Text Domain
Always use `'activitypub'` for translations:
```php
\__( 'Text', 'activitypub' );
\_e( 'Text', 'activitypub' );
```

### WordPress Global Functions
When in a namespace, always escape WordPress functions with backslash: `\get_option()`, `\add_action()`, etc.

### Imports and Class References
Two symmetric rules, both enforced in review:

```php
// Plugin classes: import with `use`, never reference inline.
use Activitypub\Collection\Outbox;

Outbox::add( $activity );          // ✅
\Activitypub\Collection\Outbox::add( $activity ); // ❌ no inline namespaces

// Global (WordPress/PHP) classes: reference inline with a backslash, never import.
$query = new \WP_Query( $args );   // ✅
class Command extends \WP_CLI_Command {} // ✅
use WP_Query;                      // ❌ no `use` for global classes
```

### Comments
- `/* */` for multi-line comments, `//` for single-line — not stacked `//` lines.
- Place each comment at the line it documents, not as one block above a block of code. Detail is fine; split it per statement.

## Comprehensive Standards

See `docs/php-coding-standards.md` for complete WordPress coding standards.

See `docs/php-class-structure.md` for detailed directory organization.

## Directory Structure

```
includes/
├── class-*.php              # Core classes.
├── activity/                # Activity type classes.
├── collection/              # Collection classes.
├── handler/                 # Activity handlers.
├── rest/                    # REST API endpoints.
├── transformer/             # Content transformers.
└── wp-admin/                # Admin functionality.

integration/                 # Third-party integrations (root level).
```

## ActivityPub Architectural Patterns

### Transformers
Convert WordPress content into ActivityPub objects.

**When to use:** Converting posts, comments, users, or custom content types into ActivityPub format.

**Base class:** `includes/transformer/class-base.php`

**Pattern:**
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

**Examples:**
- `includes/transformer/class-post.php` - Post transformation.
- `includes/transformer/class-comment.php` - Comment transformation.
- `includes/transformer/class-user.php` - User/actor transformation.

### Handlers
Process incoming ActivityPub activities from remote servers.

**When to use:** Processing incoming Follow, Like, Create, Delete, Update, etc. activities.

**Pattern:** Each handler processes one activity type from the inbox.

**Examples:**
- `includes/handler/class-follow.php` - Process Follow activities.
- `includes/handler/class-create.php` - Process Create activities.
- `includes/handler/class-delete.php` - Process Delete activities.
- `includes/handler/class-like.php` - Process Like activities.

### Collections
Implement ActivityPub collections (Followers, Following, etc.).

**When to use:** Exposing lists of actors, activities, or objects via ActivityPub.

**Examples:**
- `includes/collection/class-followers.php` - Followers collection.
- `includes/collection/class-following.php` - Following collection.

### REST API Controllers
Expose ActivityPub endpoints.

**Namespace:** `ACTIVITYPUB_REST_NAMESPACE`

**Examples:**
- `includes/rest/class-actors-controller.php` - Actor endpoint.
- `includes/rest/class-inbox-controller.php` - Inbox endpoint.
- `includes/rest/class-outbox-controller.php` - Outbox endpoint.
- `includes/rest/class-followers-controller.php` - Followers collection endpoint.

## Plugin-Specific Helper Functions

```php
// Get remote actor metadata.
$metadata = get_remote_metadata_by_actor( $actor_url );

// Convert ActivityPub object to URI string.
$uri = object_to_uri( $object );

// Enrich content with callbacks.
$content = enrich_content_data( $content, $pattern, $callback );

// Resolve WebFinger handle to actor URL.
$resource = Webfinger::resolve( $handle );

// Check whether a post is disabled for ActivityPub (the federation pipeline gate).
$disabled = is_post_disabled( $post );
```

## Real Codebase Examples

**Core Classes:**
- `includes/class-activitypub.php` - Main plugin initialization.
- `includes/class-dispatcher.php` - Activity dispatching to followers.
- `includes/class-scheduler.php` - WP-Cron integration for async tasks.
- `includes/class-signature.php` - HTTP Signatures for federation.

**Activity Types:**
- `includes/activity/class-activity.php` - Activity class (Create, Follow, Undo, etc. are built from this).
- `includes/activity/class-base-object.php` - Base object class.
- `includes/activity/extended-object/` - Extended object types (e.g. Event).

**Integrations (see [Integration Patterns](../integrations/SKILL.md)):**
- `integration/class-buddypress.php` - BuddyPress integration.
- `integration/class-jetpack.php` - Jetpack integration.
- `integration/class-opengraph.php` - OpenGraph integration.

## Common Initialization Patterns

### Static Initialization
```php
class Feature {
    /**
     * Initialize the class.
     */
    public static function init() {
        \add_action( 'init', array( self::class, 'register' ) );
        \add_filter( 'activitypub_the_content', array( self::class, 'filter' ) );
    }
}
```

### Singleton Pattern
```php
class Manager {
    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init();
    }
}
```

## Custom Hook Patterns

**Actions:**
```php
\do_action( 'activitypub_handled_create', $activity, $user_ids, $success, $result );
\do_action( 'activitypub_followers_pre_remove_follower', $follower, $user_id, $actor );
```

**Filters:**
```php
$array   = \apply_filters( 'activitypub_activity_object_array', $array, $class, $id, $object );
$content = \apply_filters( 'activitypub_the_content', $content, $post );
$types   = \apply_filters( 'activitypub_actor_types', $types );
```

## Version Numbers

**Always use `'unreleased'`** for version strings in new code. The release script automatically replaces these with the actual version number during the release process.

**PHPDoc tags:**
```php
/**
 * New function description.
 *
 * @since unreleased
 */
function new_feature() {}

/**
 * Old function.
 *
 * @deprecated unreleased Use new_feature() instead.
 */
function old_feature() {}
```

**Deprecation functions:**
```php
\_deprecated_function( __METHOD__, 'unreleased', 'New_Class::new_method' );
\_deprecated_argument( __METHOD__, 'unreleased', \esc_html__( 'Message', 'activitypub' ) );
\_doing_it_wrong( __METHOD__, \esc_html__( 'Message', 'activitypub' ), 'unreleased' );
```

**Never hardcode version numbers** like `'5.1.0'` — always use `'unreleased'`.
