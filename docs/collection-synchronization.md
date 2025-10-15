# Collection Synchronization

This is a prototype implementation of [FEP-8fcf: Followers collection synchronization across servers](https://codeberg.org/fediverse/fep/src/branch/main/fep/8fcf/fep-8fcf.md).

## Overview

FEP-8fcf provides a mechanism for detecting and resolving discrepancies in follow relationships between ActivityPub instances. This helps ensure that follower lists stay synchronized even when there are software bugs, server crashes, or database rollbacks.

## How It Works

### 1. Outgoing Activities

When sending Create activities to followers, the plugin automatically adds a `Collection-Synchronization` HTTP header that includes:

- `collectionId`: The sender's followers collection URI
- `url`: URL to fetch the partial followers collection for that specific instance (e.g., `/actors/{id}/followers/sync?authority=https://example.com`)
- `digest`: A cryptographic digest (XOR'd SHA256 hashes) of followers from the receiving instance

The header is added during HTTP delivery in `Http::post()` when sending to inboxes and is automatically covered by the HTTP signature to meet the FEP requirement for authenticity.

This is implemented in `includes/class-http.php`.

### 2. Partial Followers Collection

A new REST endpoint `/actors/{user_id}/followers/sync` provides partial followers collections filtered by instance authority. This endpoint only returns followers whose IDs match the requesting instance's domain.

This is implemented in `includes/rest/class-followers-controller.php`.

### 3. Incoming Activities

When receiving Create activities with a `Collection-Synchronization` header, the plugin:

1. Detects the collection type from the URL (e.g., followers, following)
2. Validates the header parameters against the actor's collection
3. Computes the local digest for comparison
4. If digests don't match, fires the `activitypub_followers_sync_mismatch` action for async reconciliation

This is implemented in `includes/handler/class-collection-sync.php`.

### 4. Reconciliation

When a digest mismatch is detected, the plugin triggers a scheduled reconciliation job that:

1. Fetches the authoritative partial followers collection from the remote server
2. Compares it with the local *following* relationships for that remote actor
3. Removes local follow records that the remote server no longer recognises
4. Promotes pending follow requests that the remote server already lists as accepted
5. Issues Undo Follow activities for any unexpected entries reported by the remote server

The reconciliation is handled asynchronously via WordPress's cron system.

This is implemented in `includes/scheduler/class-collection-sync.php`.

## Components

### Core Classes

- **`Http`** (`includes/class-http.php`)
  - Adds `Collection-Synchronization` header to outgoing Create activities
  - Generates sync headers with digest, collectionId, and URL
  - Methods: `post()` with sync header generation

- **`Collection_Sync`** (`includes/handler/class-collection-sync.php`)
  - Handles incoming activities with Collection-Synchronization headers
  - Detects collection type from URLs (followers, following, etc.)
  - Validates header parameters against actor collections
  - Triggers reconciliation on digest mismatch
  - Methods: `handle_collection_synchronization()`, `detect_collection_type()`, `process_followers_collection_sync()`, `validate_collection_sync_header_params()`

- **`Followers`** (`includes/collection/class-followers.php`)
  - Computes partial follower digests for outgoing deliveries using XOR'd SHA256 hashes
  - Filters followers by instance authority when building partial collections
  - Methods: `compute_partial_digest()`, `get_partial_followers()`

- **`Following`** (`includes/collection/class-following.php`)
  - Exposes local following state for reconciliation and digest calculations
  - Maps local user IDs to ActivityPub actor URLs for comparison
  - Methods: `get_local_followers_snapshot()`

- **`Followers_Controller`** (`includes/rest/class-followers-controller.php`)
  - Adds `/actors/{id}/followers/sync` REST endpoint for partial collections
  - Filters followers by authority parameter
  - Returns ActivityStreams OrderedCollection with only matching followers
  - Methods: `get_partial_followers()`

- **`Collection_Sync`** (`includes/scheduler/class-collection-sync.php`)
  - Handles async reconciliation when digest mismatches occur
  - Fetches authoritative partial followers from the remote server
  - Removes stale local follow relationships, promotes pending accepts, and cleans up unexpected entries
  - Reports changes via action hooks
  - Methods: `reconcile_followers()`

- **`Scheduler`** (`includes/class-scheduler.php`)
  - Registers the follower reconciliation scheduled action
## Privacy Considerations

FEP-8fcf is designed with privacy in mind:

- Only followers from the requesting instance are included in partial collections
- Each instance only gets information about its own users
- No global follower list is exposed

## Action Hooks

The implementation provides several action hooks for monitoring and extending:

```php
// Triggered when digest mismatch is detected
do_action( 'activitypub_followers_sync_mismatch', $user_id, $actor_url, $params );

// Triggered when a local follow record is removed during sync
do_action( 'activitypub_followers_sync_follower_removed', $local_user_id, $local_actor_uri, $actor_url );

// Triggered when a pending follow is auto-accepted during sync
do_action( 'activitypub_followers_sync_follow_request_accepted', $local_user_id, $local_actor_uri, $actor_url );

// Triggered when an unexpected remote entry requires an Undo Follow
do_action( 'activitypub_followers_sync_follower_mismatch', $local_user_id, $local_actor_uri, $actor_url );

// Triggered after reconciliation completes
do_action( 'activitypub_followers_sync_reconciled', $user_id, $actor_url, $removed_actor_uris, $undo_actor_uris );
```

## REST API Endpoints

### Partial Followers Collection

```
GET /wp-json/activitypub/1.0/actors/{user_id}/followers/sync?authority={authority}
```

**Parameters:**
- `user_id` (required): The local actor's user ID
- `authority` (required): URI authority to filter followers (e.g., `https://mastodon.social`)
- `page` (optional): Page number for pagination
- `per_page` (optional): Items per page (default: 20)

**Response:** ActivityStreams OrderedCollection with filtered followers

**Example:**
```bash
curl -H "Accept: application/activity+json" \
  "https://example.com/wp-json/activitypub/1.0/actors/1/followers/sync?authority=https://mastodon.social"
```

## Compatibility

This implementation is compatible with:

- Mastodon (v3.3.0+)
- Fedify (v0.8.0+)
- Tootik (v0.18.0+)
- Any other server that implements FEP-8fcf

## Testing

### Manual Testing

To test the implementation:

1. Set up two WordPress instances with the ActivityPub plugin
2. Have users follow each other
3. Monitor the `Collection-Synchronization` headers in HTTP requests
4. Simulate a follower mismatch by manually removing a follower from the database
5. Send a Create activity and verify reconciliation occurs

### Automated Tests

The implementation includes:
- **Unit tests** (`tests/phpunit/tests/includes/class-test-http.php`) - Tests header generation
- **E2E tests** (`tests/e2e/specs/includes/rest/followers-controller.test.js`) - Tests the sync endpoint
- **Integration tests** - Tests full reconciliation flow

Run tests with:
```bash
# PHP unit tests
vendor/bin/phpunit

# E2E tests
npm run test:e2e
```

## Configuration

The FEP-8fcf implementation is enabled by default. There are no configuration options currently available.

## Debugging

To debug synchronization issues:

1. Enable WordPress debug logging:
   ```php
   define( 'WP_DEBUG', true );
   define( 'WP_DEBUG_LOG', true );
   ```

2. Monitor action hooks:
   ```php
   add_action( 'activitypub_followers_sync_mismatch', function( $user_id, $actor_url, $params ) {
       error_log( "Sync mismatch for user $user_id from $actor_url" );
   }, 10, 3 );
   ```

3. Check scheduled actions in WordPress admin under Tools > Scheduled Actions

## Future Enhancements

Potential improvements for the future:

- Add admin UI to view synchronization logs
- Implement configurable sync frequency
- Add metrics/statistics for sync operations
- Support synchronization for Following collections
- Add option to disable FEP-8fcf support
- Implement exponential backoff for failed reconciliations
- Add support for other collection types (liked, outbox, etc.)

## References

- [FEP-8fcf Specification](https://codeberg.org/fediverse/fep/src/branch/main/fep/8fcf/fep-8fcf.md)
- [Mastodon Implementation](https://github.com/tootsuite/mastodon/pull/14510)
- [Fedify Documentation](https://fedify.dev/manual/send#followers-collection-synchronization)
