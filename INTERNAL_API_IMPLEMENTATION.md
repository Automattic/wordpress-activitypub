# Internal API Implementation

This document describes the implementation of the internal REST API and entity system for the ActivityPub plugin, specifically organized under the `/internal` namespace.

## Overview

The internal API provides WordPress Core Data entities for ActivityPub actors with support for filtering by relationships (followers/following). This allows block editor components to easily fetch and display actor data using standard WordPress hooks.

## Structure

### PHP (REST API)

**Location**: `includes/rest/internal/`

- **`class-actors-controller.php`** - Internal actors REST controller

**Namespace**: `Activitypub\Rest\Internal`

### JavaScript (Entities)

**Location**: `src/internal/`

- **`actors-entity/`** - Actors entity registration and examples

**Build Output**: `build/internal/`

## REST API Endpoint

### Base URL

```
/wp-json/activitypub/v1/internal/actors
```

### Endpoints

#### 1. Get All Actors (Local)

```
GET /wp-json/activitypub/v1/internal/actors
```

Returns all local actors (users, blog, application).

**Query Parameters**:
- `context` (string): Response context (`view`, `edit`, `embed`)
- `type` (string): Filter by actor type (`user`, `blog`, `application`, `remote`)
- `page` (integer): Page number (default: 1)
- `per_page` (integer): Items per page (default: 10, max: 100)
- `order` (string): Sort order (`asc`, `desc`, default: `desc`)
- `search` (string): Search term

**Example**:
```bash
curl "http://localhost/wp-json/activitypub/v1/internal/actors?type=user"
```

#### 2. Get Single Actor

```
GET /wp-json/activitypub/v1/internal/actors/{id}
```

Returns a single actor by ID.

**Example**:
```bash
curl "http://localhost/wp-json/activitypub/v1/internal/actors/0"  # Blog actor
curl "http://localhost/wp-json/activitypub/v1/internal/actors/1"  # User with ID 1
```

#### 3. Get Followers (NEW!)

```
GET /wp-json/activitypub/v1/internal/actors?relationship=followers&user_id={id}
```

Returns followers for a specific user.

**Query Parameters**:
- `relationship` (string): **Required**. Must be `followers` or `following`
- `user_id` (integer): **Required**. Actor ID to get relationships for
- `page` (integer): Page number (default: 1)
- `per_page` (integer): Items per page (default: 10, max: 100)
- `order` (string): Sort order (`asc`, `desc`, default: `desc`)
- `search` (string): Search followers by name

**Example**:
```bash
# Get followers for blog actor (ID: 0)
curl "http://localhost/wp-json/activitypub/v1/internal/actors?relationship=followers&user_id=0&per_page=10"

# Get followers for user ID 1
curl "http://localhost/wp-json/activitypub/v1/internal/actors?relationship=followers&user_id=1"

# Search followers
curl "http://localhost/wp-json/activitypub/v1/internal/actors?relationship=followers&user_id=0&search=mastodon"
```

#### 4. Get Following

```
GET /wp-json/activitypub/v1/internal/actors?relationship=following&user_id={id}
```

Returns actors that a user is following.

**Parameters**: Same as followers endpoint, but with `relationship=following`

### Response Format

#### Local Actor Response

```json
{
  "id": 0,
  "type": "blog",
  "name": "My WordPress Site",
  "preferred_username": "blog",
  "url": "https://example.com",
  "icon": {
    "type": "Image",
    "url": "https://example.com/avatar.jpg"
  },
  "summary": "A WordPress blog about technology",
  "activitypub_id": "https://example.com"
}
```

#### Remote Actor Response (Followers/Following)

```json
{
  "id": 12345,
  "type": "remote",
  "name": "John Doe",
  "preferred_username": "johndoe",
  "url": "https://mastodon.social/@johndoe",
  "icon": {
    "type": "Image",
    "url": "https://mastodon.social/avatar.jpg"
  },
  "summary": "Software developer from Berlin",
  "activitypub_id": "https://mastodon.social/users/johndoe"
}
```

### Pagination

Paginated responses include headers:
- `X-WP-Total`: Total number of items
- `X-WP-TotalPages`: Total number of pages

## JavaScript Entity

### Registration

The actors entity is registered in `src/internal/actors-entity/index.js`:

```javascript
registerEntityType( {
    kind: 'activitypub/v1',
    name: 'actor',
    baseURL: '/wp-json/activitypub/v1/internal/actors',
    // ... configuration
} );
```

### Usage Examples

#### 1. Fetch Followers

```javascript
import { useEntityRecords } from '@wordpress/core-data';

function UserFollowers( { userId } ) {
    const { records: followers, isResolving, totalItems } = useEntityRecords(
        'activitypub/v1',
        'actor',
        {
            relationship: 'followers',
            user_id: userId,
            per_page: 10,
        }
    );

    if ( isResolving ) {
        return <p>Loading...</p>;
    }

    return (
        <div>
            <h3>Followers ({ totalItems })</h3>
            <ul>
                { followers?.map( follower => (
                    <li key={ follower.id }>
                        { follower.name } (@{ follower.preferred_username })
                    </li>
                ) ) }
            </ul>
        </div>
    );
}
```

#### 2. Fetch Following

```javascript
const { records: following } = useEntityRecords(
    'activitypub/v1',
    'actor',
    {
        relationship: 'following',
        user_id: userId,
    }
);
```

#### 3. Fetch Local Actors Only

```javascript
const { records: localActors } = useEntityRecords(
    'activitypub/v1',
    'actor',
    {
        type: 'user',  // Only user actors
    }
);
```

#### 4. Search Followers

```javascript
const { records: searchResults } = useEntityRecords(
    'activitypub/v1',
    'actor',
    {
        relationship: 'followers',
        user_id: userId,
        search: 'mastodon',
    }
);
```

#### 5. Paginated Followers

```javascript
const [ page, setPage ] = useState( 1 );

const { records, totalPages } = useEntityRecords(
    'activitypub/v1',
    'actor',
    {
        relationship: 'followers',
        user_id: userId,
        page,
        per_page: 20,
    }
);
```

## Using in Followers Block

The followers block can be updated to use the entity API like this:

```javascript
// Instead of apiFetch
const { records: followers, isResolving, totalItems, totalPages } = useEntityRecords(
    'activitypub/v1',
    'actor',
    {
        relationship: 'followers',
        user_id: userId,
        per_page,
        page,
        order,
    }
);
```

**Benefits**:
1. Automatic caching through WordPress Core Data
2. Consistent API across all components
3. Built-in pagination support
4. Standard WordPress patterns
5. Easy to extend with additional filters

## Files Created/Modified

### Created

1. `includes/rest/internal/class-actors-controller.php` - Internal actors REST controller
2. `src/internal/actors-entity/index.js` - Entity registration
3. `src/internal/actors-entity/block.json` - Build configuration
4. `src/internal/actors-entity/README.md` - Documentation
5. `src/internal/actors-entity/example.js` - Usage examples
6. `src/internal/actors-entity/example-followers.js` - Followers-specific examples

### Modified

1. `activitypub.php` - Added REST route registration
2. `includes/class-blocks.php` - Added entity script enqueuing

## Features

### Actor Types

- `user` - WordPress users with ActivityPub enabled
- `blog` - Blog actor (ID: 0)
- `application` - Application actor (ID: -1)
- `remote` - Remote actors (followers/following)

### Relationships

- `followers` - Get followers for a user
- `following` - Get users/actors that a user is following

### Filtering

- By type
- By relationship
- By search term
- With pagination
- With custom sort order

## Authentication

All endpoints require authentication:
- User must be logged in
- Suitable for block editor and admin interfaces
- Not for public API consumption

## Testing

### Test REST API

```bash
# Start development environment
npm run env-start

# Test endpoints (with valid WordPress cookie)
curl -X GET "http://localhost/wp-json/activitypub/v1/internal/actors" \
  --cookie "wordpress_logged_in_..."

# Test followers endpoint
curl -X GET "http://localhost/wp-json/activitypub/v1/internal/actors?relationship=followers&user_id=0" \
  --cookie "wordpress_logged_in_..."
```

### Test in Browser Console

```javascript
// Fetch all actors
wp.data.select('core').getEntityRecords('activitypub/v1', 'actor');

// Fetch followers for blog actor
wp.data.select('core').getEntityRecords('activitypub/v1', 'actor', {
    relationship: 'followers',
    user_id: 0
});

// Fetch following
wp.data.select('core').getEntityRecords('activitypub/v1', 'actor', {
    relationship: 'following',
    user_id: 1
});
```

## Next Steps

1. Update the followers block to use the entity API
2. Update the following block (if exists) to use the entity API
3. Add unit tests for the REST controller
4. Add E2E tests for the entity integration
5. Consider adding write operations (follow/unfollow)

## Related Documentation

- [WordPress Core Data API](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-core-data/)
- [WordPress REST API](https://developer.wordpress.org/rest-api/)
- [ActivityPub Actors Collection](../../includes/collection/class-actors.php)
- [ActivityPub Followers Collection](../../includes/collection/class-followers.php)
- [ActivityPub Following Collection](../../includes/collection/class-following.php)
