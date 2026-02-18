# ActivityPub API Implementation Plan

This document outlines the implementation plan for adding SWICG ActivityPub API support to the WordPress ActivityPub plugin, enabling Client-to-Server (C2S) functionality.

## Executive Summary

The [SWICG ActivityPub API](https://github.com/swicg/activitypub-api) specification aims to standardize client-to-server ActivityPub interactions, allowing third-party clients to work across different ActivityPub servers.

### Implementation Status

| Feature | Status | Notes |
|---------|--------|-------|
| S2S Federation | ✅ Maintained | Unchanged |
| HTTP Signatures | ✅ Maintained | RFC-9421 + Draft-Cavage |
| OAuth 2.0 + PKCE | ✅ Implemented | Authorization code flow, token refresh, revocation, introspection |
| Dynamic Client Registration | ✅ Implemented | RFC 7591 + CIMD auto-discovery |
| POST to Outbox | ✅ Implemented | Create, Update, Delete, Follow, Undo, Like, Announce |
| GET Inbox | ✅ Implemented | OAuth-authenticated read access |
| CORS Headers | ✅ Implemented | Cross-origin support for C2S clients |
| Proxy Endpoint | ✅ Implemented | Fetch remote objects on behalf of clients |
| Connected Applications UI | ✅ Implemented | User profile section for managing OAuth tokens and registering clients |
| Content Pipeline | ✅ Implemented | HTML-to-blocks conversion, hashtag extraction, link processing |
| Application Passwords | ✅ Implemented | Alternative auth for C2S |
| SSE Push | ⬜ Future | Real-time updates |
| Media Upload | ⬜ Future | Standard upload endpoint |
| Search & Discovery | ⬜ Future | Content and actor search |
| User Controls | ⬜ Future | Mute/block management |

---

## Phase 1: OAuth 2.0 Foundation ✅

**Status**: Implemented

### 1.1 OAuth Server Implementation

#### Files
```
includes/oauth/
├── class-server.php           # OAuth 2.0 server logic, Bearer token validation
├── class-token.php            # Token model (transient + user meta storage)
├── class-client.php           # Client registration model
└── class-scope.php            # Scope definitions and validation (READ, WRITE, FOLLOW)
```

#### Storage

Tokens are stored using WordPress transients (access tokens) and user meta (refresh tokens), avoiding custom post types for performance.

Clients are stored as `activitypub_oauth_client` custom post types with meta for `client_id`, `redirect_uris`, `grant_types`, and `scope`.

#### REST Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/activitypub/1.0/oauth/authorize` | GET | Authorization page (redirect to WP login) |
| `/activitypub/1.0/oauth/token` | POST | Token exchange + refresh |
| `/activitypub/1.0/oauth/revoke` | POST | Token revocation (RFC 7009) |
| `/activitypub/1.0/oauth/introspect` | POST | Token introspection (RFC 7662) |
| `/activitypub/1.0/oauth/clients` | POST | Dynamic client registration (RFC 7591) |
| `/.well-known/oauth-authorization-server` | GET | Authorization Server Metadata |

#### OAuth Flow (PKCE)

```
┌─────────────┐                              ┌─────────────┐
│   Client    │                              │   Server    │
└──────┬──────┘                              └──────┬──────┘
       │                                            │
       │  1. GET /oauth/authorize                   │
       │     ?response_type=code                    │
       │     &client_id=...                         │
       │     &redirect_uri=...                      │
       │     &scope=read write                      │
       │     &state=...                             │
       │     &code_challenge=...                    │
       │     &code_challenge_method=S256            │
       │ ─────────────────────────────────────────> │
       │                                            │
       │  2. Redirect to WordPress login            │
       │ <───────────────────────────────────────── │
       │                                            │
       │  3. User authenticates & authorizes        │
       │ ─────────────────────────────────────────> │
       │                                            │
       │  4. Redirect to client with code           │
       │ <───────────────────────────────────────── │
       │                                            │
       │  5. POST /oauth/token                      │
       │     grant_type=authorization_code          │
       │     &code=...                              │
       │     &code_verifier=...                     │
       │ ─────────────────────────────────────────> │
       │                                            │
       │  6. Return access_token + refresh_token    │
       │ <───────────────────────────────────────── │
       │                                            │
```

#### Scopes (Implemented)

| Scope | Description |
|-------|-------------|
| `read` | Read actor profile, collections, inbox |
| `write` | Create activities via POST to outbox |
| `follow` | Manage following relationships |

### 1.2 Actor Endpoints Property

Extend `get_endpoints()` in User/Blog models:

```php
public function get_endpoints() {
    $endpoints = array(
        'sharedInbox' => get_rest_url_by_path( 'inbox' ),
    );

    if ( $this->supports_c2s() ) {
        $endpoints['oauthAuthorizationEndpoint'] = get_rest_url_by_path( 'oauth/authorize' );
        $endpoints['oauthTokenEndpoint'] = get_rest_url_by_path( 'oauth/token' );
        $endpoints['uploadMedia'] = get_rest_url_by_path( 'users/' . $this->get__id() . '/media' );
    }

    return $endpoints;
}
```

### 1.3 Authentication Filter

Add Bearer token validation to REST API:

```php
// Hook into WordPress REST authentication
add_filter( 'rest_authentication_errors', array( $this, 'authenticate_oauth' ), 20 );

public function authenticate_oauth( $result ) {
    // Check for Bearer token
    $auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if ( strpos( $auth_header, 'Bearer ' ) !== 0 ) {
        return $result; // Let other auth methods handle
    }

    $token = substr( $auth_header, 7 );
    $validated = OAuth\Token::validate( $token );

    if ( is_wp_error( $validated ) ) {
        return $validated;
    }

    wp_set_current_user( $validated->user_id );
    return true;
}
```

---

## Phase 2: POST to Outbox (Core C2S) ✅

**Status**: Implemented

### 2.1 Outbox POST Endpoint

Extend `class-outbox-controller.php`:

```php
// Add CREATABLE route
array(
    'methods'             => WP_REST_Server::CREATABLE,
    'callback'            => array( $this, 'create_item' ),
    'permission_callback' => array( $this, 'create_item_permissions_check' ),
    'args'                => $this->get_create_item_args(),
)
```

#### Permission Check

```php
public function create_item_permissions_check( $request ) {
    // Must be authenticated via OAuth with 'write' scope
    $token = OAuth\Server::get_current_token();

    if ( ! $token || ! $token->has_scope( 'write' ) ) {
        return new WP_Error(
            'activitypub_unauthorized',
            __( 'OAuth token with write scope required', 'activitypub' ),
            array( 'status' => 401 )
        );
    }

    // Token user must match actor
    $user_id = $this->get_user_id_from_request( $request );
    if ( $token->user_id !== $user_id ) {
        return new WP_Error(
            'activitypub_forbidden',
            __( 'Token does not match actor', 'activitypub' ),
            array( 'status' => 403 )
        );
    }

    return true;
}
```

### 2.2 Activity Processing

#### Supported Activity Types (Implemented)

| Activity | Action | WordPress Mapping |
|----------|--------|-------------------|
| `Create` + `Note` | Create post (status format) | `Collection\Posts::create()` |
| `Create` + `Article` | Create post | `Collection\Posts::create()` |
| `Update` | Update post | `Collection\Posts::update()` |
| `Delete` | Trash post / delete comment | `Collection\Posts::delete()` |
| `Follow` | Follow actor | Delegates to `follow()` for delivery |
| `Undo` + `Follow` | Unfollow actor | Delegates to `unfollow()` |
| `Like` | Like object | Outbox entry stored |
| `Announce` | Boost/Reblog | Outbox entry stored |

Content created via Create/Update goes through `Posts::prepare_content()` which applies `wpautop()`, processes links and hashtags, and converts HTML to Gutenberg blocks. Hashtags are automatically saved as WordPress tags via `Hashtag::insert_post()`.

#### Architecture: Outbox Handlers

Outbox handlers live in `includes/handler/outbox/` (separate from S2S inbox handlers in `includes/handler/`). They are thin wrappers that delegate to `Collection\Posts` for CRUD and fire action hooks for federation dispatch.

#### Create Activity Handler

```php
public function handle_create( $activity, $user ) {
    $object = $activity->get_object();
    $type = $object->get_type();

    // Wrap bare objects in Create activity
    if ( ! $activity->get_type() ) {
        $activity = Activity::wrap_in_create( $object, $user );
    }

    switch ( $type ) {
        case 'Note':
            return $this->create_note( $object, $user );
        case 'Article':
            return $this->create_article( $object, $user );
        case 'Image':
        case 'Video':
        case 'Audio':
            return $this->create_media( $object, $user );
        default:
            return new WP_Error( 'unsupported_type', "Type $type not supported" );
    }
}

private function create_note( $object, $user ) {
    $post_data = array(
        'post_author'  => $user->get__id(),
        'post_content' => $object->get_content(),
        'post_status'  => 'publish',
        'post_type'    => 'post',
    );

    // Handle addressing for visibility
    $to = $object->get_to();
    if ( in_array( 'https://www.w3.org/ns/activitystreams#Public', $to ) ) {
        $post_data['post_status'] = 'publish';
    } else {
        $post_data['post_status'] = 'private';
    }

    $post_id = wp_insert_post( $post_data, true );

    if ( is_wp_error( $post_id ) ) {
        return $post_id;
    }

    // Return 201 Created with Location header
    return array(
        'id'       => Transformer::get_activity_id_for_post( $post_id ),
        'post_id'  => $post_id,
        'location' => get_permalink( $post_id ),
    );
}
```

### 2.3 Response Format

Per W3C spec, return `201 Created` with `Location` header:

```php
public function create_item( $request ) {
    $activity = $this->parse_activity( $request );
    $result = $this->process_activity( $activity );

    if ( is_wp_error( $result ) ) {
        return $result;
    }

    $response = new WP_REST_Response( $result, 201 );
    $response->header( 'Location', $result['id'] );

    return $response;
}
```

---

## Phase 3: Server-Sent Events (SSE) ⬜

**Status**: Future — not yet implemented

### 3.1 SSE Endpoint

New file: `includes/rest/class-sse-controller.php`

```php
class SSE_Controller extends WP_REST_Controller {

    public function register_routes() {
        register_rest_route(
            ACTIVITYPUB_REST_NAMESPACE,
            '/users/(?P<user_id>[\d]+)/stream',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'stream' ),
                'permission_callback' => array( $this, 'stream_permissions_check' ),
            )
        );
    }

    public function stream( $request ) {
        $user_id = $request->get_param( 'user_id' );
        $last_event_id = $request->get_header( 'Last-Event-ID' );

        // Set SSE headers
        header( 'Content-Type: text/event-stream' );
        header( 'Cache-Control: no-cache' );
        header( 'Connection: keep-alive' );
        header( 'X-Accel-Buffering: no' ); // Disable nginx buffering

        // Flush headers
        if ( ob_get_level() ) {
            ob_end_flush();
        }
        flush();

        // Stream loop
        $stream = new SSE_Stream( $user_id, $last_event_id );
        $stream->run();

        exit;
    }
}
```

### 3.2 Event Types

```php
class SSE_Event {
    const ADD    = 'Add';
    const REMOVE = 'Remove';
    const UPDATE = 'Update';
    const DELETE = 'Delete';
}
```

### 3.3 Event Generation Hooks

Hook into WordPress actions to generate SSE events:

```php
// New follower
add_action( 'activitypub_followers_post_follow', function( $user_id, $follower ) {
    SSE_Stream::broadcast( $user_id, 'followers', SSE_Event::ADD, $follower );
}, 10, 2 );

// New inbox activity
add_action( 'activitypub_inbox_received', function( $user_id, $activity ) {
    SSE_Stream::broadcast( $user_id, 'inbox', SSE_Event::ADD, $activity );
}, 10, 2 );

// Post published (outbox)
add_action( 'activitypub_outbox_created', function( $user_id, $activity ) {
    SSE_Stream::broadcast( $user_id, 'outbox', SSE_Event::ADD, $activity );
}, 10, 2 );
```

### 3.4 Actor eventStream Property

Add to actor JSON-LD:

```php
public function get_event_stream() {
    if ( ! $this->supports_sse() ) {
        return null;
    }

    return get_rest_url_by_path( 'users/' . $this->get__id() . '/stream' );
}
```

---

## Phase 4: Feature Discovery ⬜

**Status**: Partially implemented — OAuth endpoints advertised in actor profiles, Authorization Server Metadata at `/.well-known/oauth-authorization-server`

### 4.1 NodeInfo Extensions

Extend existing NodeInfo to include C2S features:

```php
public function get_nodeinfo_2_1() {
    $nodeinfo = parent::get_nodeinfo_2_1();

    $nodeinfo['metadata']['activitypubApi'] = array(
        'version' => '1.0',
        'features' => array(
            'oauth2'           => true,
            'postToOutbox'     => true,
            'mediaUpload'      => true,
            'serverSentEvents' => true,
            'collections'      => array(
                'inbox',
                'outbox',
                'followers',
                'following',
                'liked',
                'featured',
            ),
            'activityTypes' => array(
                'Create',
                'Update',
                'Delete',
                'Follow',
                'Like',
                'Announce',
                'Undo',
                'Add',
                'Remove',
            ),
        ),
    );

    return $nodeinfo;
}
```

### 4.2 Actor Capabilities

Use FEP-844e pattern for capability discovery:

```php
public function get_capabilities() {
    return array(
        'acceptsChatMessages' => false,
        'supportsClientToServer' => true,
        'supportsServerSentEvents' => true,
        'supportedActivities' => array(
            'Create', 'Update', 'Delete', 'Follow', 'Like', 'Announce', 'Undo'
        ),
    );
}
```

---

## Phase 5: Collection Enhancements ⬜

**Status**: Future — not yet implemented. Note: `Collection\Posts` (local CRUD) and `Collection\Remote_Posts` (federated posts) have been split into separate classes.

### 5.1 Collection Filtering

Add query parameters to collection endpoints:

| Parameter | Description | Example |
|-----------|-------------|---------|
| `type` | Filter by object type | `?type=Note` |
| `object` | Filter by object ID | `?object=https://...` |
| `actor` | Filter by actor | `?actor=https://...` |
| `after` | Pagination cursor | `?after=2024-01-01` |
| `limit` | Page size | `?limit=20` |

```php
public function get_collection_items( $request ) {
    $args = array(
        'post_type'      => Outbox::POST_TYPE,
        'posts_per_page' => $request->get_param( 'limit' ) ?? 20,
    );

    // Type filter
    if ( $type = $request->get_param( 'type' ) ) {
        $args['meta_query'][] = array(
            'key'   => 'activitypub_object_type',
            'value' => $type,
        );
    }

    // Object filter
    if ( $object = $request->get_param( 'object' ) ) {
        $args['meta_query'][] = array(
            'key'   => 'activitypub_object_id',
            'value' => $object,
        );
    }

    return new WP_Query( $args );
}
```

### 5.2 Collection Membership Check

New endpoint: `GET /collections/{id}/contains?object={uri}`

```php
register_rest_route(
    ACTIVITYPUB_REST_NAMESPACE,
    '/users/(?P<user_id>[\d]+)/(?P<collection>followers|following|liked)/contains',
    array(
        'methods'  => 'GET',
        'callback' => array( $this, 'check_membership' ),
        'args'     => array(
            'object' => array(
                'required' => true,
                'type'     => 'string',
                'format'   => 'uri',
            ),
        ),
    )
);

public function check_membership( $request ) {
    $collection = $request->get_param( 'collection' );
    $object = $request->get_param( 'object' );

    $is_member = $this->collection_contains( $collection, $object );

    return array(
        'isMember' => $is_member,
    );
}
```

### 5.3 Additional Collections

| Collection | Description | Status |
|------------|-------------|--------|
| `liked` | Objects the actor has liked | New |
| `bookmarks` | Saved/bookmarked objects | New |
| `blocked` | Blocked actors | New |
| `muted` | Muted actors | New |
| `pendingFollowers` | Awaiting approval | New |
| `pendingFollowing` | Sent, awaiting response | New |

---

## Phase 6: Media Upload ⬜

**Status**: Future — not yet implemented

### 6.1 Upload Endpoint

```php
register_rest_route(
    ACTIVITYPUB_REST_NAMESPACE,
    '/users/(?P<user_id>[\d]+)/media',
    array(
        'methods'             => 'POST',
        'callback'            => array( $this, 'upload_media' ),
        'permission_callback' => array( $this, 'upload_permissions_check' ),
    )
);

public function upload_media( $request ) {
    $files = $request->get_file_params();
    $file = $files['file'] ?? null;

    if ( ! $file ) {
        return new WP_Error( 'no_file', 'No file uploaded', array( 'status' => 400 ) );
    }

    // Use WordPress media handling
    require_once ABSPATH . 'wp-admin/includes/image.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';

    $attachment_id = media_handle_upload( 'file', 0 );

    if ( is_wp_error( $attachment_id ) ) {
        return $attachment_id;
    }

    $url = wp_get_attachment_url( $attachment_id );
    $mime = get_post_mime_type( $attachment_id );

    // Return ActivityPub-compatible object
    return array(
        'id'        => $url,
        'type'      => $this->mime_to_as_type( $mime ),
        'mediaType' => $mime,
        'url'       => $url,
    );
}
```

---

## Phase 7: User Controls ⬜

**Status**: Future — not yet implemented

### 7.1 Mute/Block Endpoints

```php
// POST /users/{id}/muted
register_rest_route(
    ACTIVITYPUB_REST_NAMESPACE,
    '/users/(?P<user_id>[\d]+)/muted',
    array(
        'methods'  => 'POST',
        'callback' => array( $this, 'mute_actor' ),
    )
);

// DELETE /users/{id}/muted/{actor}
register_rest_route(
    ACTIVITYPUB_REST_NAMESPACE,
    '/users/(?P<user_id>[\d]+)/muted/(?P<actor_id>.+)',
    array(
        'methods'  => 'DELETE',
        'callback' => array( $this, 'unmute_actor' ),
    )
);
```

### 7.2 Follow Request Management

```php
// GET /users/{id}/pendingFollowers
// POST /users/{id}/pendingFollowers/{id}/accept
// POST /users/{id}/pendingFollowers/{id}/reject
```

### 7.3 Profile Editing

```php
// PATCH /users/{id}
public function update_profile( $request ) {
    $user_id = $request->get_param( 'user_id' );
    $updates = $request->get_json_params();

    $allowed_fields = array(
        'name'    => 'display_name',
        'summary' => 'description',
        'icon'    => 'avatar', // Requires special handling
        'image'   => 'header', // Requires special handling
    );

    foreach ( $updates as $field => $value ) {
        if ( isset( $allowed_fields[ $field ] ) ) {
            $this->update_user_field( $user_id, $allowed_fields[ $field ], $value );
        }
    }

    return $this->get_actor( $user_id );
}
```

---

## Phase 8: Search & Discovery ⬜

**Status**: Future — not yet implemented

### 8.1 Search Endpoint

```php
register_rest_route(
    ACTIVITYPUB_REST_NAMESPACE,
    '/search',
    array(
        'methods'  => 'GET',
        'callback' => array( $this, 'search' ),
        'args'     => array(
            'q'     => array( 'required' => true, 'type' => 'string' ),
            'type'  => array( 'enum' => array( 'accounts', 'statuses', 'hashtags' ) ),
            'limit' => array( 'default' => 20, 'maximum' => 40 ),
        ),
    )
);
```

### 8.2 Actor Directory

```php
register_rest_route(
    ACTIVITYPUB_REST_NAMESPACE,
    '/directory',
    array(
        'methods'  => 'GET',
        'callback' => array( $this, 'get_directory' ),
    )
);
```

---

## Phase 9: Error Handling & Rate Limiting

### 9.1 Standard Error Format

Per SWICG user story #30:

```php
class API_Error extends WP_Error {
    public function to_response() {
        return array(
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'type'     => 'Error',
            'error'    => $this->get_error_code(),
            'message'  => $this->get_error_message(),
            'details'  => $this->get_error_data(),
        );
    }
}
```

### 9.2 Rate Limiting

```php
// Add rate limit headers to responses
add_filter( 'rest_post_dispatch', function( $response, $server, $request ) {
    $limits = Rate_Limiter::get_limits( $request );

    $response->header( 'X-RateLimit-Limit', $limits['limit'] );
    $response->header( 'X-RateLimit-Remaining', $limits['remaining'] );
    $response->header( 'X-RateLimit-Reset', $limits['reset'] );

    return $response;
}, 10, 3 );
```

---

## Implementation Priority

### Implemented (MVP)
1. ✅ **Phase 1**: OAuth 2.0 with PKCE, dynamic client registration, token introspection/revocation
2. ✅ **Phase 2**: POST to Outbox — Create, Update, Delete, Follow, Undo, Like, Announce
3. ✅ **Inbox GET**: Authenticated read access to user inbox
4. ✅ **Connected Applications UI**: User profile section for OAuth management
5. ✅ **Content Pipeline**: HTML-to-blocks conversion, hashtag/link processing

### Next Up
6. ⬜ **Phase 6**: Media upload
7. ⬜ **Phase 5**: Collection filtering and membership checks
8. ⬜ **Phase 4**: Extended feature discovery (NodeInfo, capabilities)

### Future
9. ⬜ **Phase 3**: SSE push delivery
10. ⬜ **Phase 7**: Mute/block/profile editing
11. ⬜ **Phase 8**: Search & discovery
12. ⬜ **Phase 9**: Rate limiting

---

## File Structure (Implemented)

```
includes/
├── oauth/
│   ├── class-server.php              # OAuth server logic, Bearer token validation
│   ├── class-token.php               # Token model (transients + user meta)
│   ├── class-client.php              # Client registration model (CPT)
│   └── class-scope.php               # Scope definitions (READ, WRITE, FOLLOW)
├── rest/
│   ├── class-outbox-controller.php   # Extended with POST (create_item)
│   ├── class-actors-inbox-controller.php # Extended with GET for authenticated users
│   └── class-proxy-controller.php    # Proxy endpoint for fetching remote objects
├── collection/
│   ├── class-posts.php               # Local posts CRUD (create/update/delete/prepare_content)
│   └── class-remote-posts.php        # Federated remote posts (renamed from class-posts.php)
├── handler/
│   └── outbox/                       # Outbox activity handlers
│       ├── class-create.php          # Delegates to Collection\Posts::create()
│       ├── class-update.php          # Delegates to Collection\Posts::update()
│       ├── class-delete.php          # Post/comment deletion
│       ├── class-follow.php          # Follow/unfollow management
│       ├── class-like.php            # Like activities
│       └── class-announce.php        # Boost/reblog activities
├── wp-admin/
│   ├── class-admin.php               # AJAX handlers for Connected Applications
│   └── class-user-settings-fields.php # Connected Applications UI section
└── class-blocks.php                  # Extended with convert_from_html()
assets/js/
└── activitypub-connected-apps.js     # Client-side for token revocation and client registration
```

---

## Testing Strategy

### Unit Tests
- OAuth token generation and validation
- Activity parsing and processing
- Collection membership checks

### Integration Tests
- Full OAuth flow with PKCE
- POST to outbox creates WordPress post
- SSE events fire on collection changes

### E2E Tests
- Use SWICG example OAuth client
- Test with existing ActivityPub clients (if any support C2S)

---

## References

- [W3C ActivityPub Spec](https://www.w3.org/TR/activitypub/) - Sections 6-9 for C2S
- [SWICG ActivityPub API](https://github.com/swicg/activitypub-api) - User stories and extensions
- [SWICG SSE Spec](https://swicg.github.io/activitypub-api/sse) - Server-Sent Events
- [OAuth 2.0 PKCE](https://oauth.net/2/pkce/) - Authorization Code Flow with PKCE
- [FEP-844e](https://codeberg.org/fediverse/fep/src/branch/main/fep/844e/fep-844e.md) - Capability discovery

---

## Open Questions

1. **Scope granularity**: Should scopes be more fine-grained (e.g., `write:statuses`, `write:follows`)? Currently using `read`, `write`, `follow`.
2. ~~**Dynamic client registration**: Should we support RFC 7591?~~ → ✅ Implemented, enabled by default.
3. **Rate limits**: What are appropriate limits for POST to outbox?
4. **SSE scaling**: How to handle SSE with multiple PHP workers (Redis pub/sub)?
5. **Compatibility**: Should we maintain Mastodon API compatibility alongside ActivityPub API?
