---
name: federation
description: ActivityPub protocol specification and federation concepts. Use when working with ActivityPub activities, understanding federation mechanics, implementing protocol features, or debugging federation issues.
---

# ActivityPub Federation Protocol

This skill provides understanding of the ActivityPub protocol specification and how federation works.

**For supported features and compatibility:** See [FEDERATION.md](FEDERATION.md) for the complete list of implemented FEPs, supported standards, and federation compatibility details.

**For implementation details:** See AGENTS.md for transformers, handlers, and PHP code patterns.

## Core Concepts

### Three Building Blocks

1. **Actors** - Users/accounts in the system
   - Each actor has a unique URI
   - Required: `inbox`, `outbox`
   - Optional: `followers`, `following`, `liked`

2. **Activities** - Actions taken by actors
   - Create, Update, Delete, Follow, Like, Announce, Undo
   - Wrap objects to describe how they're shared

3. **Objects** - Content being acted upon
   - Notes, Articles, Images, Videos, etc.
   - Can be embedded or referenced by URI

### Actor Structure

```json
{
  "@context": "https://www.w3.org/ns/activitystreams",
  "type": "Person",
  "id": "https://example.com/@alice",
  "inbox": "https://example.com/@alice/inbox",
  "outbox": "https://example.com/@alice/outbox",
  "followers": "https://example.com/@alice/followers",
  "following": "https://example.com/@alice/following",
  "preferredUsername": "alice",
  "name": "Alice Example",
  "summary": "Bio text here"
}
```

## Collections

### Standard Collections

**Inbox** - Receives incoming activities
- De-duplicate by activity ID
- Filter based on permissions
- Process activities for side effects

**Outbox** - Publishes actor's activities
- Public record of what actor has posted
- Filtered based on viewer permissions
- Used for profile activity displays

**Followers** - Actors following this actor
- Updated when Follow activities are Accepted
- Used for delivery targeting

**Following** - Actors this actor follows
- Tracks subscriptions
- Used for timeline building

### Public Addressing

Special collection: `https://www.w3.org/ns/activitystreams#Public`

- Makes content publicly accessible
- **Do not deliver to this URI** - it's a marker, not a real inbox
- Used in `to`, `cc`, `bto`, `bcc` fields for visibility

## Activity Types

### Create
Wraps newly published content:
```json
{
  "type": "Create",
  "actor": "https://example.com/@alice",
  "object": {
    "type": "Note",
    "content": "Hello, Fediverse!"
  }
}
```

### Follow
Initiates subscription:
```json
{
  "type": "Follow",
  "actor": "https://example.com/@alice",
  "object": "https://other.example/@bob"
}
```
- Recipient should respond with Accept or Reject
- Only add to followers upon Accept

### Like
Indicates appreciation:
```json
{
  "type": "Like",
  "actor": "https://example.com/@alice",
  "object": "https://other.example/@bob/post/123"
}
```

### Announce
Reshares/boosts content:
```json
{
  "type": "Announce",
  "actor": "https://example.com/@alice",
  "object": "https://other.example/@bob/post/123"
}
```

### Update
Modifies existing content:
- Supplied properties replace existing
- `null` values remove fields
- Must include original object ID

### Delete
Removes content:
- May replace with Tombstone for referential integrity
- Should cascade to related activities

### Undo
Reverses previous activities:
```json
{
  "type": "Undo",
  "actor": "https://example.com/@alice",
  "object": {
    "type": "Follow",
    "id": "https://example.com/@alice/follow/123"
  }
}
```

## Server-to-Server Federation

### Activity Delivery Process

1. **Resolve Recipients**
   - Check `to`, `bto`, `cc`, `bcc`, `audience` fields
   - Dereference collections to find individual actors
   - De-duplicate recipient list
   - Exclude activity's own actor

2. **Discover Inboxes**
   - Fetch actor profiles
   - Extract `inbox` property
   - Use `sharedInbox` if available for efficiency

3. **Deliver via HTTP POST**
   - Content-Type: `application/ld+json; profile="https://www.w3.org/ns/activitystreams"`
   - Include HTTP Signatures for authentication
   - Handle delivery failures gracefully

### Inbox Forwarding

**Ghost Replies Problem:** When Alice replies to Bob's post that Carol follows, Carol might not see the reply if she doesn't follow Alice.

**Solution:** Inbox forwarding
- When receiving activity addressing a local collection
- If activity references local objects
- Forward to collection members
- Ensures conversation participants see replies

### Shared Inbox Optimization

For public posts with many recipients on same server:
- Use `sharedInbox` endpoint instead of individual inboxes
- Reduces number of HTTP requests
- Server distributes internally

## Addressing and Visibility

### To/CC Fields

- `to` - Primary recipients (public in UI)
- `cc` - Secondary recipients (copied/mentioned)
- `bto` - Blind primary (hidden in delivery)
- `bcc` - Blind secondary (hidden in delivery)

**Important:** Remove `bto` and `bcc` before delivery to preserve privacy

### Visibility Patterns

**Public Post:**
```json
{
  "to": ["https://www.w3.org/ns/activitystreams#Public"],
  "cc": ["https://example.com/@alice/followers"]
}
```

**Followers-Only:**
```json
{
  "to": ["https://example.com/@alice/followers"]
}
```

**Direct Message:**
```json
{
  "to": ["https://other.example/@bob"],
  "cc": []
}
```

## Content Verification

### Security Considerations

1. **Verify Origins**
   - Don't trust claimed sources without verification
   - Check HTTP Signatures
   - Validate actor owns referenced objects

2. **Prevent Spoofing**
   - Mallory could claim Alice posted something
   - Always verify before processing side effects

3. **Rate Limiting**
   - Limit recursive dereferencing
   - Protect against denial-of-service
   - Implement spam filtering

4. **Content Sanitization**
   - Clean HTML before browser rendering
   - Validate media types
   - Check for malicious payloads

## Protocol Extensions

### Supported Standards

See [FEDERATION.md](FEDERATION.md) for the complete list of implemented standards and FEPs, including:
- WebFinger - Actor discovery.
- HTTP Signatures - Request authentication.
- NodeInfo - Server metadata.
- Various FEPs (Fediverse Enhancement Proposals).

### FEPs (Fediverse Enhancement Proposals)

FEPs extend ActivityPub with additional features. Common FEP categories include:
- Long-form text support.
- Quote posts.
- Activity intents.
- Follower synchronization.
- Actor metadata extensions.

**For supported FEPs in this plugin:** See [FEDERATION.md](FEDERATION.md) for the authoritative list of implemented FEPs.

## OAuth 2.0 Client-to-Server

When the ActivityPub API option is enabled, third-party clients can authenticate via OAuth 2.0 under `activitypub/1.0/oauth/`. The plugin supports RFC 7591 dynamic registration, RFC 7636 PKCE (S256 only, required by default for public clients), and the CIMD draft (URL-form `client_id`, HTTPS required).

### RFC 8252 — Loopback Redirect URIs

Native apps receive the OAuth callback on a loopback port they opened locally. Per RFC 8252 §7.3 / §8.3, redirect URIs of the form `http://127.0.0.1:{port}/{path}` and `http://[::1]:{port}/{path}` are accepted with port flexibility (any port may be used at request time). `localhost` is also accepted for compatibility, although §8.3 marks it "NOT RECOMMENDED".

The loopback allowance applies *only* to redirect URI matching. Reserved-but-not-loopback addresses — `0.0.0.0`, link-local `169.254.0.0/16`, RFC1918 private ranges (`10/8`, `172.16/12`, `192.168/16`), and similar — are not treated as loopback and never bypass `wp_safe_remote_get()`. CIMD metadata URLs must use `https://`, and the metadata host is resolved and validated against private/reserved ranges before any fetch — loopback CIMD origins are not supported, even on dev installs.

**For implementation details:** See `includes/oauth/class-client.php` (especially the class docblock and `is_loopback()`) and the OAuth section of [FEDERATION.md](FEDERATION.md).

## Implementation Notes

### WordPress Plugin Specifics

This plugin implements:
- **Actor Types**: User, Blog, Application
- **Transformers**: Convert WordPress content to ActivityPub objects
- **Handlers**: Process incoming activities

For implementation details, see:
- AGENTS.md for code structure
- [Integration Guide](../integrations/SKILL.md) for extending

### Testing Federation

```bash
# Test actor endpoint
curl -H "Accept: application/activity+json" \
  https://site.com/@username

# Test WebFinger
curl https://site.com/.well-known/webfinger?resource=acct:user@site.com

# Test NodeInfo
curl https://site.com/.well-known/nodeinfo
```

## Common Issues

### Activities Not Received
- Check inbox URL is accessible
- Verify HTTP signature validation
- Ensure content-type headers correct
- Check for firewall/security blocks

### Replies Not Federated
- Verify inbox forwarding enabled
- Check addressing includes relevant actors
- Ensure `inReplyTo` properly set

### Follower Sync Issues
- Check Accept activities sent for Follow
- Verify followers collection updates
- Ensure shared inbox used when available

## Resources

- [ActivityPub Spec](https://www.w3.org/TR/activitypub/)
- [ActivityStreams Vocabulary](https://www.w3.org/TR/activitystreams-vocabulary/)
- [RFC 8252 — OAuth 2.0 for Native Apps](https://datatracker.ietf.org/doc/html/rfc8252)
- [RFC 7591 — OAuth 2.0 Dynamic Client Registration](https://datatracker.ietf.org/doc/html/rfc7591)
- [RFC 7636 — PKCE](https://datatracker.ietf.org/doc/html/rfc7636)
- [draft-ietf-oauth-client-id-metadata-document](https://datatracker.ietf.org/doc/html/draft-ietf-oauth-client-id-metadata-document)
- [Project FEDERATION.md](FEDERATION.md)
- [FEPs Repository](https://codeberg.org/fediverse/fep)