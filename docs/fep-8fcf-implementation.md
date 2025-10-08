# FEP-8fcf Implementation

This is a prototype implementation of [FEP-8fcf: Followers collection synchronization across servers](https://codeberg.org/fediverse/fep/src/branch/main/fep/8fcf/fep-8fcf.md).

## Overview

FEP-8fcf provides a mechanism for detecting and resolving discrepancies in follow relationships between ActivityPub instances. This helps ensure that follower lists stay synchronized even when there are software bugs, server crashes, or database rollbacks.

## How It Works

### 1. Outgoing Activities

When sending Create activities to followers, the plugin automatically adds a `Collection-Synchronization` HTTP header that includes:

- `collectionId`: The sender's followers collection URI
- `url`: URL to fetch the partial followers collection for that specific instance
- `digest`: A cryptographic digest (XOR'd SHA256 hashes) of followers from the receiving instance

This is implemented in `includes/class-http.php`.

### 2. Partial Followers Collection

A new REST endpoint `/actors/{user_id}/followers/sync` provides partial followers collections filtered by instance authority. This endpoint only returns followers whose IDs match the requesting instance's domain.

This is implemented in `includes/rest/class-followers-controller.php`.

### 3. Incoming Activities

When receiving activities with a `Collection-Synchronization` header, the plugin:

1. Parses and validates the header parameters
2. Computes the local digest for comparison
3. If digests don't match, schedules an async reconciliation job

This is implemented in `includes/rest/class-inbox-controller.php`.

### 4. Reconciliation

When a digest mismatch is detected, the plugin asynchronously:

1. Fetches the authoritative partial followers collection from the remote server
2. Compares it with the local follower list
3. Removes followers that shouldn't exist locally
4. Logs followers that exist remotely but not locally (for review)

This is implemented in `includes/scheduler/class-collection-sync.php`.

## Components

### Core Classes

- **`Followers`** (`includes/collection/class-followers.php`)
  - Computes partial follower digests using XOR'd SHA256 hashes
  - Generates and parses Collection-Synchronization headers
  - Filters followers by instance authority
  - Validates header parameters
  - New FEP-8fcf methods: `compute_partial_digest()`, `get_partial_followers()`, `generate_sync_header()`, `parse_sync_header()`, `validate_sync_header_params()`, `get_authority()`

- **`Follower`** (`includes/scheduler/class-follower.php`)
  - Handles async reconciliation when digest mismatches occur
  - Removes out-of-sync followers
  - Provides action hooks for monitoring sync events

### Traits

- **`Followers_Sync`** (`includes/rest/trait-followers-sync.php`)
  - Reusable trait for inbox controllers
  - Provides `process_followers_synchronization()` method
  - Used by both `Inbox_Controller` and `Actors_Inbox_Controller`

### Modified Classes

- **`Http`** - Adds Collection-Synchronization header to outgoing Create activities
- **`Followers_Controller`** - Adds `/followers/sync` endpoint for partial collections
- **`Inbox_Controller`** - Uses `Followers_Sync` trait to process incoming headers
- **`Actors_Inbox_Controller`** - Uses `Followers_Sync` trait to process incoming headers
- **`Scheduler`** - Registers the Collection_Sync scheduler

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

// Triggered when a follower is removed during sync
do_action( 'activitypub_followers_sync_follower_removed', $user_id, $follower_url, $actor_url );

// Triggered when follower exists remotely but not locally
do_action( 'activitypub_followers_sync_follower_mismatch', $user_id, $follower_url, $actor_url );

// Triggered after reconciliation completes
do_action( 'activitypub_followers_sync_reconciled', $user_id, $actor_url, $to_remove, $to_check );
```

## Compatibility

This implementation is compatible with:

- Mastodon (v3.3.0+)
- Fedify (v0.8.0+)
- Tootik (v0.18.0+)
- Any other server that implements FEP-8fcf

## Testing

To test the implementation:

1. Set up two WordPress instances with the ActivityPub plugin
2. Have users follow each other
3. Monitor the `Collection-Synchronization` headers in HTTP requests
4. Simulate a follower mismatch by manually removing a follower from the database
5. Send a Create activity and verify reconciliation occurs

## Future Enhancements

Potential improvements for the future:

- Add admin UI to view synchronization logs
- Implement configurable sync frequency
- Add metrics/statistics for sync operations
- Support synchronization for Following collections
- Add option to disable FEP-8fcf support
- Implement exponential backoff for failed reconciliations

## References

- [FEP-8fcf Specification](https://codeberg.org/fediverse/fep/src/branch/main/fep/8fcf/fep-8fcf.md)
- [Mastodon Implementation](https://github.com/tootsuite/mastodon/pull/14510)
- [Fedify Documentation](https://fedify.dev/manual/send#followers-collection-synchronization)
