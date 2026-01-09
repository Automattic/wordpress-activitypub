# Class Structure and Organization

## Table of Contents
- [Directory Layout](#directory-layout)
- [Class Types](#class-types)
- [Namespace Organization](#namespace-organization)
- [File Placement Guidelines](#file-placement-guidelines)
- [Integration Patterns](#integration-patterns)

## Directory Layout

### Core Structure
```
wordpress-activitypub/
├── includes/                    # Core plugin functionality
│   ├── class-*.php             # Main classes
│   ├── trait-*.php             # Shared traits
│   ├── interface-*.php         # Interfaces
│   ├── functions.php           # Global functions
│   │
│   ├── activity/               # Activity type implementations
│   │   ├── class-accept.php
│   │   ├── class-create.php
│   │   ├── class-delete.php
│   │   ├── class-follow.php
│   │   ├── class-undo.php
│   │   └── class-update.php
│   │
│   ├── collection/             # Collection implementations
│   │   ├── class-actors.php
│   │   ├── class-extra-fields.php
│   │   ├── class-followers.php
│   │   ├── class-following.php
│   │   ├── class-posts.php
│   │   └── class-replies.php
│   │
│   ├── handler/                # Incoming activity handlers
│   │   ├── class-create.php
│   │   ├── class-delete.php
│   │   ├── class-follow.php
│   │   ├── class-move.php
│   │   ├── class-undo.php
│   │   └── class-update.php
│   │
│   ├── rest/                   # REST API endpoints
│   │   ├── class-actors.php
│   │   ├── class-collections.php
│   │   ├── class-followers.php
│   │   ├── class-nodeinfo.php
│   │   └── class-webfinger.php
│   │
│   ├── transformer/            # Content transformers
│   │   ├── class-activity-object.php
│   │   ├── class-attachment.php
│   │   ├── class-base.php
│   │   ├── class-comment.php
│   │   ├── class-factory.php
│   │   ├── class-json.php
│   │   ├── class-post.php
│   │   └── class-user.php
│   │
│   └── wp-admin/               # Admin functionality
│       ├── table/              # List tables
│       │   ├── class-blocked-actors.php
│       │   └── class-list.php
│       └── views/              # Admin views
│
├── integration/                # Third-party plugin integrations
│   ├── load.php               # Integration loader
│   └── class-{plugin}.php     # Individual integrations
│
├── templates/                  # Template files
│   ├── activitypub-json.php
│   ├── blog.php
│   └── user.php
│
└── tests/                      # Test files
    ├── phpunit/
    └── e2e/
```

## Class Types

### Core Classes

Located in `includes/`:

```php
// Main plugin class.
class Activitypub {
    public static function init() {
        // Initialize plugin.
    }
}

// Specific feature classes.
class Scheduler {
    // Handle scheduled tasks.
}

class Signature {
    // Handle HTTP signatures.
}

class Webfinger {
    // Handle Webfinger protocol.
}
```

### Activity Classes

Located in `includes/activity/`:

```php
namespace Activitypub\Activity;

/**
 * Base activity class.
 */
abstract class Base {
    protected $type;
    protected $actor;
    protected $object;

    abstract public function to_array();
}

/**
 * Specific activity implementation.
 */
class Follow extends Base {
    protected $type = 'Follow';

    public function to_array() {
        // Implementation.
    }
}
```

### Handler Classes

Located in `includes/handler/`:

```php
namespace Activitypub\Handler;

/**
 * Handle incoming Follow activities.
 */
class Follow {
    /**
     * Process the activity.
     *
     * @param array $activity The activity data.
     * @param int   $user_id  The target user.
     */
    public static function handle( $activity, $user_id ) {
        // Process follow request.
    }
}
```

### Transformer Classes

Located in `includes/transformer/`:

```php
namespace Activitypub\Transformer;

/**
 * Base transformer class.
 */
abstract class Base {
    protected $object;

    /**
     * Transform to ActivityPub format.
     *
     * @return array
     */
    abstract public function transform();
}

/**
 * Post transformer.
 */
class Post extends Base {
    /**
     * @var WP_Post
     */
    protected $post;

    public function transform() {
        return array(
            'type'    => 'Note',
            'content' => $this->get_content(),
            // ...
        );
    }
}
```

### Collection Classes

Located in `includes/collection/`:

```php
namespace Activitypub\Collection;

/**
 * Followers collection
 */
class Followers {
    /**
     * Get followers for a user.
     *
     * @param int $user_id User ID.
     * @return array
     */
    public static function get( $user_id ) {
        // Return followers.
    }

    /**
     * Add a follower.
     *
     * @param int    $user_id  User ID.
     * @param string $actor    Actor URL.
     */
    public static function add( $user_id, $actor ) {
        // Add follower.
    }
}
```

### REST API Classes

Located in `includes/rest/`:

**IMPORTANT:** All REST API classes must extend WordPress Core's `WP_REST_Controller` class to ensure proper integration with the WordPress REST API infrastructure.

```php
namespace Activitypub\Rest;

/**
 * REST API endpoint class - MUST extend WP_REST_Controller.
 */
class Webfinger extends \WP_REST_Controller {
    /**
     * The namespace of this controller's route.
     *
     * @var string
     */
    protected $namespace = ACTIVITYPUB_REST_NAMESPACE;

    /**
     * The base of this controller's route.
     *
     * @var string
     */
    protected $rest_base = 'webfinger';

    /**
     * Register routes.
     */
    public function register_routes() {
        register_rest_route(
            $this->namespace,
            '/.well-known/webfinger',
            array(
                array(
                    'methods'             => \WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_item' ),
                    'permission_callback' => array( $this, 'get_item_permissions_check' ),
                    'args'                => $this->get_collection_params(),
                ),
            )
        );
    }

    /**
     * Permission check.
     *
     * @param \WP_REST_Request $request Request object.
     * @return true|WP_Error
     */
    public function get_item_permissions_check( $request ) {
        return true; // Public endpoint.
    }

    /**
     * Handle GET request.
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response|\WP_Error
     */
    public function get_item( $request ) {
        // Implementation.
        return new \WP_REST_Response( $data, 200 );
    }
}
```

**Benefits of extending WP_REST_Controller:**
- Provides standard methods for CRUD operations
- Built-in permission callback support
- Consistent parameter handling and validation
- Schema definition support
- Proper integration with WordPress REST API discovery
- Standard response formatting

## Namespace Organization

### Namespace Hierarchy

```php
// Root namespace.
namespace Activitypub;

// Feature namespaces.
namespace Activitypub\Activity;
namespace Activitypub\Collection;
namespace Activitypub\Handler;
namespace Activitypub\Rest;
namespace Activitypub\Transformer;
namespace Activitypub\Integration;

// Admin namespaces.
namespace Activitypub\Wp_Admin;
namespace Activitypub\Wp_Admin\Table;
```

### Using Namespaces

```php
<?php
namespace Activitypub\Handler;

use Activitypub\Collection\Followers;
use Activitypub\Activity\Accept;
use WP_Error;

class Follow {
    public static function handle( $activity, $user_id ) {
        // Can use imported classes directly.
        $followers = Followers::get( $user_id );
        $accept    = new Accept();

        // WordPress functions need backslash.
        $user = \get_user_by( 'id', $user_id );
    }
}
```

## File Placement Guidelines

### When to Create New Directories

Create a new subdirectory when you have:
- Related classes
- A distinct functional domain
- Need for clear separation of concerns

### File Naming Rules

| Type | Pattern | Example |
|------|---------|---------|
| Class | `class-{name}.php` | `class-scheduler.php` |
| Trait | `trait-{name}.php` | `trait-singleton.php` |
| Interface | `interface-{name}.php` | `interface-transformer.php` |
| Functions | `functions.php` | `functions.php` |
| Template | `{name}.php` | `blog.php` |

### Where to Place New Classes

| Class Type | Location |
|------------|----------|
| Core functionality | `includes/` |
| Activity types | `includes/activity/` |
| Incoming handlers | `includes/handler/` |
| Data transformers | `includes/transformer/` |
| Collections | `includes/collection/` |
| REST endpoints | `includes/rest/` |
| Admin screens | `includes/wp-admin/` |
| List tables | `includes/wp-admin/table/` |
| Third-party integrations | `integration/` |

## Integration Patterns

### Third-Party Plugin Integration

Location: `integration/class-{plugin}.php`

```php
<?php
namespace Activitypub\Integration;

/**
 * BuddyPress integration.
 */
class Buddypress {
    /**
     * Initialize integration.
     */
    public static function init() {
        // Add hooks.
        \add_filter( 'activitypub_transformer', array( self::class, 'transformer' ), 10, 2 );
    }

    /**
     * Custom transformer for BuddyPress content.
     */
    public static function transformer( $transformer, $object ) {
        if ( self::is_buddypress_object( $object ) ) {
            return new Buddypress_Transformer( $object );
        }

        return $transformer;
    }
}
```

### Loading Integrations

Location: `integration/load.php`

The integration loader uses a direct, conditional approach where each integration is checked and loaded individually based on whether its corresponding plugin is active:

```php
<?php
namespace Activitypub\Integration;

use function Activitypub\site_supports_blocks;

// Register autoloader for integration classes.
\Activitypub\Autoloader::register_path( __NAMESPACE__, __DIR__ );

/**
 * Initialize the ActivityPub integrations.
 */
function plugin_init() {
    // Each integration is conditionally loaded based on plugin detection.

    // Detection via defined constants.
    if ( \defined( 'AKISMET_VERSION' ) ) {
        Akismet::init();
    }

    // Detection via class existence.
    if ( class_exists( '\Classic_Editor' ) || ! site_supports_blocks() ) {
        Classic_Editor::init();
    }

    // Some integrations always initialize (check happens internally).
    Litespeed_Cache::init();

    // Option-based activation.
    if ( '1' === \get_option( 'activitypub_use_opengraph', '1' ) ) {
        Opengraph::init();
    }

    // Inline transformer for simple integrations.
    if ( \defined( 'SSP_VERSION' ) ) {
        add_filter(
            'activitypub_transformer',
            function ( $transformer, $data, $object_class ) {
                if (
                    'WP_Post' === $object_class &&
                    \get_post_meta( $data->ID, 'audio_file', true )
                ) {
                    return new Seriously_Simple_Podcasting( $data );
                }

                return $transformer;
            },
            10,
            3
        );
    }
}
\add_action( 'plugins_loaded', __NAMESPACE__ . '\plugin_init' );

// Some integrations hook into plugin-specific actions.
\add_action( 'bp_include', array( __NAMESPACE__ . '\Buddypress', 'init' ), 0 );

// Activation/deactivation hooks for cache plugins.
\register_activation_hook( ACTIVITYPUB_PLUGIN_FILE, array( __NAMESPACE__ . '\Surge', 'add_cache_config' ) );
\register_deactivation_hook( ACTIVITYPUB_PLUGIN_FILE, array( __NAMESPACE__ . '\Surge', 'remove_cache_config' ) );
```

**Key patterns:**
- No centralized list - each integration is explicitly checked
- Detection methods: constants (`AKISMET_VERSION`), class existence, options
- Most use static `::init()` method pattern
- Some integrations use inline filters for simple transformations
- Special hooks for plugin lifecycle (BuddyPress uses `bp_include`)
- Cache plugins need activation/deactivation hooks

## Class Design Patterns

### Singleton Pattern

```php
class Manager {
    private static $instance = null;

    private function __construct() {
        // Private constructor.
    }

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}
```

### Factory Pattern

```php
namespace Activitypub\Transformer;

class Factory {
    /**
     * Get transformer for object.
     *
     * @param mixed $object Object to transform.
     * @return Base Transformer instance.
     */
    public static function get( $object ) {
        if ( $object instanceof \WP_Post ) {
            return new Post( $object );
        }

        if ( $object instanceof \WP_Comment ) {
            return new Comment( $object );
        }

        return new Json( $object );
    }
}
```

### Static Initialization

```php
class Feature {
    /**
     * Initialize the feature.
     */
    public static function init() {
        // Add hooks
        \add_action( 'init', array( self::class, 'register' ) );
        \add_filter( 'the_content', array( self::class, 'filter' ) );
    }

    /**
     * Register functionality.
     */
    public static function register() {
        // Registration logic.
    }
}
```

### Dependency Injection

```php
class Processor {
    private $transformer;
    private $validator;

    public function __construct( Transformer $transformer, Validator $validator ) {
        $this->transformer = $transformer;
        $this->validator = $validator;
    }

    public function process( $data ) {
        if ( $this->validator->validate( $data ) ) {
            return $this->transformer->transform( $data );
        }
        return false;
    }
}
```
