# Actors Entity Implementation

This document describes the implementation of ActivityPub actors as a WordPress Core Data entity.

## Overview

The actors entity allows block editor components to fetch and display ActivityPub actor information using WordPress Core Data API hooks like `useEntityRecords()` and `useEntityRecord()`.

## What Was Created

### 1. REST API Controller (PHP)

**File**: `includes/rest/class-internal-actors-controller.php`

A REST API controller that provides internal endpoints for fetching actor data:

- **GET** `/wp-json/activitypub/v1/internal/actors` - Get all actors
- **GET** `/wp-json/activitypub/v1/internal/actors/{id}` - Get single actor

**Features**:
- Read-only access to local actors (users, blog, application)
- Requires authentication (logged-in users only)
- Returns actor data in a standardized format
- Includes proper WordPress coding standards

**Actor Data Structure**:
```php
array(
    'id'                 => int,      // WordPress user ID (0 for blog, -1 for application)
    'type'               => string,   // 'user', 'blog', or 'application'
    'name'               => string,   // Display name
    'preferred_username' => string,   // Username/identifier
    'url'                => string,   // Profile URL
    'icon'               => object,   // Avatar/icon info
    'summary'            => string,   // Bio/description
    'activitypub_id'     => string,   // ActivityPub URI
)
```

### 2. Entity Registration (JavaScript)

**File**: `src/actors-entity/index.js`

Registers the actor entity with WordPress Core Data API:

```javascript
registerEntityType( {
    kind: 'activitypub/v1',
    name: 'actor',
    baseURL: '/wp-json/activitypub/v1/internal/actors',
    // ... configuration
} );
```

### 3. Build Configuration

**File**: `src/actors-entity/block.json`

Minimal block.json to enable wp-scripts build process:

```json
{
    "name": "actors-entity",
    "title": "Actors Entity: Registers actors as WordPress Core Data entities",
    "editorScript": "file:./index.js"
}
```

### 4. Integration

**Modified Files**:

1. `activitypub.php` - Added REST route registration:
   ```php
   ( new Rest\Internal_Actors_Controller() )->register_routes();
   ```

2. `includes/class-blocks.php` - Added script enqueuing:
   ```php
   // Register the actors entity with WordPress Core Data API.
   $entity_asset_file = ACTIVITYPUB_PLUGIN_DIR . 'build/actors-entity/index.asset.php';
   if ( file_exists( $entity_asset_file ) ) {
       $entity_asset_data = include $entity_asset_file;
       $entity_url        = plugins_url( 'build/actors-entity/index.js', ACTIVITYPUB_PLUGIN_FILE );
       wp_enqueue_script(
           'activitypub-actors-entity',
           $entity_url,
           $entity_asset_data['dependencies'],
           $entity_asset_data['version'],
           true
       );
   }
   ```

### 5. Documentation

**File**: `src/actors-entity/README.md`

Comprehensive documentation including:
- What WordPress entities are
- How to use the actors entity
- 5 detailed usage examples
- REST API endpoint documentation
- Actor schema reference
- Testing instructions
- Extension guidelines

### 6. Example Code

**File**: `src/actors-entity/example.js`

Complete example components demonstrating:
- `ActorsList` - Display all actors
- `ActorProfile` - Display single actor details
- `ActorSelector` - Dropdown for selecting actors
- `ActorsByType` - Filter actors by type
- `ActorBrowser` - Complete interactive browser

## Usage in Block Editor

### Basic Example: Fetch All Actors

```javascript
import { useEntityRecords } from '@wordpress/core-data';

function MyComponent() {
    const { records: actors, isResolving } = useEntityRecords(
        'activitypub/v1',
        'actor'
    );

    if ( isResolving ) {
        return <p>Loading...</p>;
    }

    return (
        <ul>
            { actors?.map( actor => (
                <li key={ actor.id }>
                    { actor.name } (@{ actor.preferred_username })
                </li>
            ) ) }
        </ul>
    );
}
```

### Fetch Single Actor

```javascript
import { useEntityRecord } from '@wordpress/core-data';

function ActorProfile( { actorId } ) {
    const { record: actor, isResolving } = useEntityRecord(
        'activitypub/v1',
        'actor',
        actorId
    );

    if ( isResolving ) return <p>Loading...</p>;
    if ( ! actor ) return <p>Not found</p>;

    return (
        <div>
            <h2>{ actor.name }</h2>
            <p>@{ actor.preferred_username }</p>
            <p>Type: { actor.type }</p>
        </div>
    );
}
```

## Testing

### Test REST API Endpoint

Start the development environment:

```bash
npm run env-start
```

Test the endpoints (replace cookie with actual logged-in cookie):

```bash
# Get all actors
curl -X GET "http://localhost/wp-json/activitypub/v1/internal/actors" \
  --cookie "wordpress_logged_in_..."

# Get specific actor (blog actor)
curl -X GET "http://localhost/wp-json/activitypub/v1/internal/actors/0" \
  --cookie "wordpress_logged_in_..."

# Get user actor
curl -X GET "http://localhost/wp-json/activitypub/v1/internal/actors/1" \
  --cookie "wordpress_logged_in_..."
```

### Test in Block Editor

1. Create a new post
2. Open browser console
3. Test the entity:

```javascript
// Fetch all actors
wp.data.select('core').getEntityRecords('activitypub/v1', 'actor');

// Fetch specific actor
wp.data.select('core').getEntityRecord('activitypub/v1', 'actor', 0);
```

## File Structure

```
includes/
└── rest/
    └── class-internal-actors-controller.php  # REST API controller

src/
└── actors-entity/
    ├── index.js           # Entity registration
    ├── block.json         # Build configuration
    ├── README.md          # Documentation
    └── example.js         # Example components

build/
└── actors-entity/
    ├── index.js           # Built entity registration
    ├── index.asset.php    # Asset dependencies
    └── block.json         # Copied block.json
```

## Benefits

1. **Standard WordPress Pattern**: Uses WordPress Core Data API
2. **Type Safety**: Returns consistent data structure
3. **Automatic Caching**: Core Data handles caching automatically
4. **Easy to Use**: Simple hooks interface
5. **Flexible**: Can be extended with filters
6. **Documented**: Comprehensive documentation and examples

## Future Enhancements

Possible improvements:

1. Add support for remote actors (not just local)
2. Add pagination for large actor lists
3. Add search/filter parameters
4. Add write operations (if needed)
5. Add real-time updates via WebSocket or polling

## Related Resources

- [WordPress Core Data Package](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-core-data/)
- [WordPress REST API Handbook](https://developer.wordpress.org/rest-api/)
- [ActivityPub Actors Collection](includes/collection/class-actors.php)

## Changelog

### v1.0.0 - Initial Implementation

- Created Internal_Actors_Controller REST API endpoint
- Registered actor entity with WordPress Core Data
- Added comprehensive documentation
- Added example components
- Integrated with block editor assets
