# Federation in WordPress

The WordPress plugin largely follows ActivityPub's server-to-server specification, but makes use of some non-standard extensions, some of which are required to interact with the plugin. Most of these extensions are for the purpose of compatibility with other, sometimes very restrictive networks, such as Mastodon.

## Supported federation protocols and standards

- [ActivityPub](https://www.w3.org/TR/activitypub/) (Server-to-Server)
- [ActivityPub API: Basic Profile](https://swicg.github.io/activitypub-api/basicprofile) (Client-to-Server, partial; see [OAuth 2.0 for Client-to-Server](#oauth-20-for-client-to-server))
- [ActivityPub API: Server-Sent Events](https://swicg.github.io/activitypub-api/sse) (partial, see below)
- [ActivityPub API: Actor Autocomplete](https://swicg.github.io/activitypub-api/autocomplete) (typeahead search over local and cached remote actors; requires the ActivityPub API to be enabled)
- [WebFinger](https://www.w3.org/community/reports/socialcg/CG-FINAL-apwf-20240608/)
- [HTTP Signatures](https://swicg.github.io/activitypub-http-signature/)
- [NodeInfo](https://nodeinfo.diaspora.software/)
- [Interaction Policy](https://docs.gotosocial.org/en/latest/federation/interaction_policy/)

## Supported FEPs

- [FEP-0151: NodeInfo in Fediverse Software (2025 edition)](https://codeberg.org/fediverse/fep/src/branch/main/fep/0151/fep-0151.md)
- [FEP-044f: Consent-respecting quote posts](https://codeberg.org/fediverse/fep/src/branch/main/fep/044f/fep-044f.md)
- [FEP-2677: Identifying the Application Actor](https://codeberg.org/fediverse/fep/src/branch/main/fep/2677/fep-2677.md)
- [FEP-2c59: Discovery of a Webfinger address from an ActivityPub actor](https://codeberg.org/fediverse/fep/src/branch/main/fep/2c59/fep-2c59.md)
- [FEP-3b86: Activity Intents](https://codeberg.org/fediverse/fep/src/branch/main/fep/3b86/fep-3b86.md)
- [FEP-4f05: Soft Delete](https://codeberg.org/fediverse/fep/src/branch/main/fep/4f05/fep-4f05.md)
- [FEP-5feb: Search indexing consent for actors](https://codeberg.org/fediverse/fep/src/branch/main/fep/5feb/fep-5feb.md)
- [FEP-67ff: FEDERATION.md](https://codeberg.org/fediverse/fep/src/branch/main/fep/67ff/fep-67ff.md)
- [FEP-7628: Move Actor](https://codeberg.org/fediverse/fep/src/branch/main/fep/7628/fep-7628.md)
- [FEP-7888: Demystifying the context property](https://codeberg.org/fediverse/fep/src/branch/main/fep/7888/fep-7888.md)
- [FEP-844e: Capability discovery](https://codeberg.org/fediverse/fep/src/branch/main/fep/844e/fep-844e.md)
- [FEP-8fcf: Followers collection synchronization across servers](https://codeberg.org/fediverse/fep/src/branch/main/fep/8fcf/fep-8fcf.md)
- [FEP-9098: Custom Emojis](https://codeberg.org/fediverse/fep/src/branch/main/fep/9098/fep-9098.md)
- [FEP-b2b8: Long-form Text](https://codeberg.org/fediverse/fep/src/branch/main/fep/b2b8/fep-b2b8.md)
- [FEP-c180: Problem Details for ActivityPub](https://codeberg.org/fediverse/fep/src/branch/main/fep/c180/fep-c180.md)
- [FEP-ee3a: Exif metadata support](https://codeberg.org/fediverse/fep/src/branch/main/fep/ee3a/fep-ee3a.md)
- [FEP-f1d5: NodeInfo in Fediverse Software](https://codeberg.org/fediverse/fep/src/branch/main/fep/f1d5/fep-f1d5.md)
- [FEP-fb2a: Actor metadata](https://codeberg.org/fediverse/fep/src/branch/main/fep/fb2a/fep-fb2a.md)

### Partially supported FEPs

- [FEP-1b12: Group federation](https://codeberg.org/fediverse/fep/src/branch/main/fep/1b12/fep-1b12.md)
- [FEP-ae0c: Fediverse Relay Protocols](https://codeberg.org/fediverse/fep/src/branch/main/fep/ae0c/fep-ae0c.md)
- [FEP-dc88: MathML in ActivityPub](https://codeberg.org/fediverse/fep/src/branch/main/fep/dc88/fep-dc88.md)

## ActivityPub

### Actor Types

The plugin supports the following actor types:

- `Person` - Individual WordPress users
- `Group` - The WordPress blog as a whole
- `Application` - Server-level actor for system operations

### Supported Activities

**Outgoing activities:**

- `Create` - Publishing posts and comments
- `Update` - Editing posts and comments
- `Delete` - Removing posts and comments
- `Announce` - Sharing/boosting content
- `Like` - Liking content
- `Follow` - Following remote actors
- `Move` - Actor migration (see FEP-7628)
- `Undo` - Reversing previous activities (unfollow, unlike, etc.)
- `Accept` / `Reject` - Responding to follow requests

**Incoming activities:**

- `Create` - Receiving replies and mentions
- `Update` - Updates to remote content
- `Delete` - Deletion notifications
- `Announce` - Boost notifications
- `Like` - Like notifications
- `Follow` - Follow requests
- `Move` - Actor migration notifications (see FEP-7628)
- `Undo` - Reversal of activities
- `Accept` / `Reject` - Follow request responses

### Object Types

- `Note` - Short-form content (default for posts)
- `Article` - Long-form content (for standard posts without a specific post format)
- `Image`, `Audio`, `Video`, `Document` - Media attachments

### HTTP Signatures

The plugin signs all outgoing `POST` requests and supports signed `GET` requests for fetching remote actors and objects.

Two signature methods are supported for maximum compatibility:

- **RFC-9421** (modern standard) - Used by default
- **Draft Cavage** (legacy) - Automatic fallback for older implementations

More information on HTTP Signatures: <https://swicg.github.io/activitypub-http-signature/>

### Relays

The plugin supports basic relay functionality for distributing public content across the Fediverse (see FEP-ae0c):

**As relay client:**

- Public activities can be sent to configured relay inboxes via settings
- LitePub-style subscription: Follow a relay actor, receive reciprocal follow, relay becomes a follower and receives activities

**As relay server:**

- When relay mode is enabled, incoming public activities are wrapped in `Announce` and forwarded to followers
- Blog actor type changes to `Service` in relay mode

**Limitations:**

- Mastodon-style relay subscription (Follow to `#Public`) is not supported
- LD Signatures for forwarded activity attribution are not implemented

### WebFinger

The plugin provides WebFinger discovery at `/.well-known/webfinger` for all enabled actors. Supported resource formats:

- `acct:username@domain`
- `https://domain/@username`
- `https://domain/author/username`

### Mastodon Extensions

For compatibility with Mastodon, the plugin supports several extensions from the `toot:` namespace:

- `toot:featured` - Pinned/featured posts collection
- `toot:featuredTags` - Featured hashtags
- `toot:discoverable` - Actor discovery preference
- `toot:indexable` - Search indexing consent (see FEP-5feb)
- `toot:attributionDomains` - Domain attribution for verification links

### Other Extensions

**[Dublin Core](http://purl.org/dc/terms/)**

- `dcterms:subject` - Content warnings (see FEP-b2b8)

**[GoToSocial](https://gotosocial.org/ns)**

- `gts:interactionPolicy` - Interaction policies for objects

**[iCalendar](http://www.w3.org/2002/12/cal/ical)**

- `Event` - `ical:status` for event status

**[Lemmy](https://join-lemmy.org/ns)**

- `Actor` - `lemmy:matrixUserId`, `lemmy:chatMessage`

**[LitePub](https://litepub.social/spec/)**

- `Actor` - `litepub:invisible` visibility flag

**[Misskey](https://misskey-hub.net/ns/)**

- `Note` - `_misskey_quote` for quote posts

**[Mobilizon](https://docs.mobilizon.org/5.%20Interoperability/1.activity_pub/#extensions)**

- `Event` - `mz:externalParticipationUrl`, `mz:joinMode`, `mz:participantCount`

**[PeerTube](https://joinpeertube.org/ns)**

- `Event` - `pt:commentsEnabled`, `pt:isOnline`

**[schema.org](https://schema.org/)**

- `Actor`, `Group` - `PropertyValue` for metadata fields (see FEP-fb2a)
- `Event` - `category`, `inLanguage`, `maximumAttendeeCapacity`
- `Place` - Location objects with `PostalAddress` support

### Server-Sent Events (SSE)

The plugin provides real-time streaming of collection changes via [Server-Sent Events](https://swicg.github.io/activitypub-api/sse). Requires OAuth authentication with the `push` scope.

**Supported features:**

- `eventStream` property advertised on outbox and inbox collections
- `proxyEventStream` advertised in actor `endpoints`
- `access_token` URL parameter accepted (required for browser `EventSource` API)
- SSE event types: `Add`, `Update`, `Remove`, `Delete` mapped from ActivityPub activities
- `Last-Event-ID` header honored on reconnect to replay missed events
- Event payload contains the full ActivityStreams Activity object
- SSE `event:` and `id:` fields set per event
- `proxyEventStream` relays remote eventStreams through the local server

**Known limitations:**

- No `retry:` field sent to hint reconnect interval

### Endpoints

All REST API endpoints use the `activitypub/1.0` namespace.

**Well-Known:**

- `/.well-known/webfinger` - Actor discovery
- `/.well-known/nodeinfo` - Server capability discovery

**Actor endpoints:**

- `/activitypub/1.0/actors/{user_id}` - Actor profile
- `/activitypub/1.0/actors/{user_id}/inbox` - User inbox
- `/activitypub/1.0/actors/{user_id}/outbox` - Published activities
- `/activitypub/1.0/actors/{user_id}/followers` - Followers collection
- `/activitypub/1.0/actors/{user_id}/following` - Following collection
- `/activitypub/1.0/actors/{user_id}/collections/{type}` - Featured posts or tags

**Server endpoints:**

- `/activitypub/1.0/inbox` - Shared inbox
- `/activitypub/1.0/application` - Application actor
- `/activitypub/1.0/proxy` - Proxy endpoint for fetching remote objects

**Object endpoints:**

- `/activitypub/1.0/posts/{id}/replies` - Replies collection
- `/activitypub/1.0/posts/{id}/likes` - Likes collection
- `/activitypub/1.0/posts/{id}/shares` - Shares collection

**Content negotiation:**

Posts and author pages serve ActivityPub JSON-LD when the request includes an appropriate `Accept` header (`application/activity+json` or `application/ld+json`).

### OAuth 2.0 for Client-to-Server

When the ActivityPub API option is enabled, the plugin exposes OAuth 2.0 endpoints under the `activitypub/1.0/oauth/` namespace for third-party clients (Mastodon-compatible apps, native apps, browser apps).

**Supported standards:**

- [SWICG ActivityPub API: Basic Profile](https://swicg.github.io/activitypub-api/basicprofile) - C2S baseline. The token response includes `activitypub_actor_id` alongside the IndieAuth `me` URI, and `scopes_supported` advertises the canonical aliases `activitypub:read:all` and `activitypub:write:all`. Any `activitypub:read:*` or `activitypub:write:*` scope is accepted and collapsed to the plugin's coarse `read` / `write` scope — there is no per-activity-type access control yet.
- [RFC 8252](https://datatracker.ietf.org/doc/html/rfc8252) - OAuth 2.0 for Native Apps. Loopback redirect URIs (`http://127.0.0.1:{port}` and `http://[::1]:{port}`) are accepted with port flexibility per §7.3/§8.3. `localhost` is also accepted for compatibility; §8.3 marks this "NOT RECOMMENDED" but it remains common practice.
- [RFC 7591](https://datatracker.ietf.org/doc/html/rfc7591) - Dynamic Client Registration.
- [RFC 7636](https://datatracker.ietf.org/doc/html/rfc7636) - PKCE. Required by default for public clients; only `S256` is accepted.
- [RFC 6585](https://datatracker.ietf.org/doc/html/rfc6585) - Additional HTTP Status Codes. OAuth rate-limit responses use `429 Too Many Requests` and include a `Retry-After` header.
- [`draft-ietf-oauth-client-id-metadata-document`](https://datatracker.ietf.org/doc/html/draft-ietf-oauth-client-id-metadata-document) - Client Identifier Metadata Document (CIMD). When `client_id` is an `https://` URL, the plugin fetches the metadata document and auto-registers the client. Cleartext (`http://`) `client_id` URLs are rejected.

**Loopback scope:**

The loopback allowance from RFC 8252 applies *only* to redirect URI matching. Reserved-but-not-loopback addresses (`0.0.0.0`, link-local `169.254.0.0/16`, RFC1918 private ranges, etc.) are not treated as loopback. CIMD metadata URLs must use `https://`, and the metadata host is resolved and validated against private/reserved ranges before any fetch. Loopback CIMD origins are not supported, even on dev installs.

## Additional documentation

- Plugin Documentation: [docs/readme.md](docs/readme.md)
- Changelog: <https://github.com/Automattic/wordpress-activitypub/blob/trunk/CHANGELOG.md>
