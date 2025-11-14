# Actors Entity

This module registers ActivityPub actors as a WordPress Core Data entity, making it easy to fetch and display actor information in the block editor.

## What is a WordPress Entity?

WordPress entities are data types that can be accessed through the `@wordpress/core-data` package. They provide a standardized way to interact with WordPress data using hooks like `useEntityRecords` and `useEntityRecord`.

Reference: [WordPress Core Data Package Documentation](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-core-data/#whats-an-entity)

## Features

- **Read-only access** to local ActivityPub actors (users, blog, application)
- **REST API integration** via internal endpoint
- **Core Data hooks** for easy data fetching
- **Type-safe** actor information

## REST API Endpoint

The entity is backed by the following internal REST API endpoint:

- **Base URL**: `/wp-json/activitypub/v1/internal/actors`
- **Single Actor**: `/wp-json/activitypub/v1/internal/actors/{id}`
- **Authentication**: Requires logged-in user

### Actor Schema

Each actor has the following properties:

| Property | Type | Description |
|----------|------|-------------|
| `id` | integer | WordPress user ID (0 for blog, -1 for application) |
| `type` | string | Actor type: `user`, `blog`, or `application` |
| `name` | string | Display name of the actor |
| `preferred_username` | string | Username/identifier |
| `url` | string | Profile URL |
| `icon` | object/null | Avatar/icon information |
| `summary` | string | Biography/description |
| `activitypub_id` | string | ActivityPub URI |

## Usage Examples

### 1. Fetch All Actors

```javascript
import { useEntityRecords } from '@wordpress/core-data';

function ActorsList() {
    const { records: actors, isResolving } = useEntityRecords(
        'activitypub/v1',
        'actor'
    );

    if ( isResolving ) {
        return <p>Loading actors...</p>;
    }

    return (
        <ul>
            { actors?.map( ( actor ) => (
                <li key={ actor.id }>
                    { actor.name } (@{ actor.preferred_username })
                </li>
            ) ) }
        </ul>
    );
}
```

### 2. Fetch a Single Actor

```javascript
import { useEntityRecord } from '@wordpress/core-data';

function ActorProfile( { actorId } ) {
    const { record: actor, isResolving } = useEntityRecord(
        'activitypub/v1',
        'actor',
        actorId
    );

    if ( isResolving ) {
        return <p>Loading...</p>;
    }

    if ( ! actor ) {
        return <p>Actor not found</p>;
    }

    return (
        <div>
            <h2>{ actor.name }</h2>
            <p>@{ actor.preferred_username }</p>
            <p>Type: { actor.type }</p>
            { actor.summary && <p>{ actor.summary }</p> }
            <a href={ actor.url }>View Profile</a>
        </div>
    );
}
```

### 3. Use with Select/Dispatch

```javascript
import { useSelect } from '@wordpress/data';
import { store as coreDataStore } from '@wordpress/core-data';

function MyComponent() {
    const actors = useSelect( ( select ) => {
        return select( coreDataStore ).getEntityRecords(
            'activitypub/v1',
            'actor'
        );
    }, [] );

    // Your component logic here
}
```

### 4. Actor Type Filter

```javascript
import { useEntityRecords } from '@wordpress/core-data';

function UserActorsList() {
    const { records: actors } = useEntityRecords(
        'activitypub/v1',
        'actor'
    );

    // Filter to show only user actors (not blog or application)
    const userActors = actors?.filter( ( actor ) => actor.type === 'user' );

    return (
        <ul>
            { userActors?.map( ( actor ) => (
                <li key={ actor.id }>{ actor.name }</li>
            ) ) }
        </ul>
    );
}
```

### 5. Actor Selector Component

```javascript
import { SelectControl } from '@wordpress/components';
import { useEntityRecords } from '@wordpress/core-data';

function ActorSelector( { value, onChange } ) {
    const { records: actors, isResolving } = useEntityRecords(
        'activitypub/v1',
        'actor'
    );

    if ( isResolving ) {
        return <SelectControl disabled label="Loading actors..." />;
    }

    const options = actors?.map( ( actor ) => ( {
        label: `${ actor.name } (@${ actor.preferred_username })`,
        value: actor.id,
    } ) ) || [];

    return (
        <SelectControl
            label="Select Actor"
            value={ value }
            options={ options }
            onChange={ onChange }
        />
    );
}
```

## Actor IDs

The plugin uses special IDs for non-user actors:

- **User actors**: Positive integers (1, 2, 3, ...)
- **Blog actor**: `0`
- **Application actor**: `-1`

## Permissions

The internal endpoint requires authentication. Users must be logged in to fetch actor data. This is enforced by the REST API controller.

## Implementation Details

### PHP Side

- **Controller**: `Activitypub\Rest\Internal_Actors_Controller`
- **Collection**: `Activitypub\Collection\Actors`
- **Route Registration**: `activitypub.php:rest_init()`

### JavaScript Side

- **Entity Registration**: `src/actors-entity/index.js`
- **Enqueuing**: `includes/class-blocks.php:enqueue_editor_assets()`

## Testing

You can test the REST API endpoint directly:

```bash
# Get all actors
curl -X GET "http://localhost/wp-json/activitypub/v1/internal/actors" \
  --cookie "wordpress_logged_in_..."

# Get specific actor
curl -X GET "http://localhost/wp-json/activitypub/v1/internal/actors/0" \
  --cookie "wordpress_logged_in_..."
```

## Extending the Entity

If you need to add custom fields to actors, you can use WordPress filters:

```php
// Add custom field to REST response
add_filter( 'activitypub_rest_prepare_actor', function( $response, $actor, $request ) {
    $response->data['custom_field'] = get_user_meta( $actor->get_user_id(), 'custom_field', true );
    return $response;
}, 10, 3 );
```

## Related

- [WordPress Core Data Package](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-core-data/)
- [ActivityPub Actors Collection](../../includes/collection/class-actors.php)
- [Internal Actors REST Controller](../../includes/rest/class-internal-actors-controller.php)
