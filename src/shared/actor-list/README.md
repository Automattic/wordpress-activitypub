# ActivityPub Actor List Components

Reusable React components for displaying lists of ActivityPub actors (followers, following) in the block editor.

## Overview

These components provide a consistent way to display paginated lists of ActivityPub actors in different blocks' edit views. They handle fetching data from the REST API, pagination, loading states, and avatar fallbacks.

## Components

### ActorList

The main container component that handles data fetching and renders a list of actors.

```jsx
import { ActorList } from '../shared/actor-list';

<ActorList
    selectedUser={ userId }
    perPage={ 10 }
    order="desc"
    endpoint="followers"  // or "following"
    page={ page }
    setPage={ setPage }
    emptyMessage={ __( 'No followers found.', 'activitypub' ) }
    navLabel={ __( 'Follower navigation', 'activitypub' ) }
/>
```

**Props:**

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `selectedUser` | string/number | required | User ID or "blog" |
| `perPage` | number | required | Items per page |
| `order` | string | required | Sort order ("asc" or "desc") |
| `endpoint` | string | "followers" | API endpoint ("followers" or "following") |
| `page` | number | (internal) | Current page (controlled mode) |
| `setPage` | function | (internal) | Page setter (controlled mode) |
| `initialData` | object | false | Pre-fetched data `{ items, total }` |
| `emptyMessage` | string | "No results found." | Empty state message |
| `navLabel` | string | "Navigation" | Screen reader nav label |

### ActorItem

Displays a single actor with avatar, name, and handle.

```jsx
import { ActorItem } from '../shared/actor-list';

<ActorItem
    name="John Doe"
    preferredUsername="johndoe"
    url="https://example.com/@johndoe"
    icon={ { url: 'https://example.com/avatar.jpg' } }
/>
```

### Pagination

Pagination controls with previous/next navigation.

```jsx
import { Pagination } from '../shared/actor-list';

<Pagination
    page={ 2 }
    pages={ 5 }
    setPage={ setPage }
    navLabel={ __( 'Follower navigation', 'activitypub' ) }
/>
```

## Styling

Import the shared styles in your block's style.scss:

```scss
@import "../shared/actor-list/style";

.wp-block-activitypub-your-block {
    // Card style variant:
    &.is-style-card:not(.block-editor-block-list__block) {
        @include actor-list-card-style;
    }

    // Compact style variant:
    &.is-style-compact {
        @include actor-list-compact-style;
    }
}
```

## PHP Helper

For server-side rendering (render.php), use the `Blocks::render_actor_list()` helper:

```php
Blocks::render_actor_list(
    array(
        'show_avatars' => $show_avatars,
        'total'        => $total,
        'per_page'     => $per_page,
        'nav_label'    => __( 'Follower navigation', 'activitypub' ),
    )
);
```

## Frontend (view.js)

The frontend uses the WordPress Interactivity API. Each block has its own simple view.js - there's no shared store because the logic is straightforward and tightly coupled to specific endpoints.

## Example: Creating a Following Block

1. **edit.js** - Use the shared components:
```jsx
import { ActorList } from '../shared/actor-list';

<ActorList
    selectedUser={ selectedUser }
    perPage={ perPage }
    order={ order }
    endpoint="following"
    emptyMessage={ __( 'Not following anyone.', 'activitypub' ) }
    navLabel={ __( 'Following navigation', 'activitypub' ) }
/>
```

2. **style.scss** - Import shared styles:
```scss
@import "../shared/actor-list/style";
```

3. **render.php** - Use the PHP helper:
```php
Blocks::render_actor_list( array( ... ) );
```

4. **view.js** - Simple Interactivity API store (copy from followers and change endpoint)
