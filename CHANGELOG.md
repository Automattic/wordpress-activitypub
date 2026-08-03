# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [9.2.1] - 2026-08-03
### Fixed
- Fixed duplicate entries and failed undo actions for activities received from other WordPress sites. [#3612]
- Fixed posts and profiles not being found on Mastodon and other Fediverse software, which happened when a request asked for ActivityPub data but also accepted HTML as a low-priority fallback. [#3601]
- Fixed remote profiles and followers not being found when their address contains unusual characters. [#3613]
- Improved checks on boosted content so it is only accepted from the server that published it. [#3613]
- Improve escaping of embedded link URLs in federated content. [#3599]
- Improve permission checks for admin-only actions. [#3609]
- Improve validation of incoming federated activities so the signing key and referenced objects are bound to the sender. [#3610]

## [9.2.0] - 2026-07-31
### Changed
- ActivityPub responses are now served only to clients that ask for ActivityPub data and nothing else, which keeps that data out of page caches meant for regular web pages. [#3596]
- Only users enabled for ActivityPub can obtain and use OAuth access tokens. [#3592]

### Removed
- Remove support for the WP REST Cache plugin. [#3597]

### Fixed
- Hide the heading on the Followers and Following blocks when its text is cleared, matching the Reactions block. [#3574]
- Under Authorized Fetch, ActivityPub responses are no longer stored by page caches such as LiteSpeed or Surge. [#3597]

## [9.1.0] - 2026-07-22
### Security
- Ensure apps you connect can only act within the access you granted them, and not make wider changes to your site. [#3569]
- Ensure remote profiles and content are served from the address they claim before storing them. [#3570]
- Fix a security issue where a remote actor's profile link could run scripts in the admin area. [#3568]
- Ignore an incoming follow request whose actor resolves to a different account than the one that sent it. [#3570]

### Added
- Add a filter that allows federating with servers on private or internal networks. [#3531]
- Add an actor autocomplete endpoint so Fediverse apps can offer typeahead search when mentioning people. [#3555]
- Federate the episode summary for Podlove Podcast Publisher episodes. [#3457]

### Changed
- Improve reliability of the Social Web admin screen loading. [#3436]
- Improve the internal handling of the Application actor used for server-to-server requests.

### Fixed
- Ensure a follow can only be declined by the account you followed. [#3561]
- Fixed using the correct cache representation in Surge config [#3558]
- Fix follow requests from some fediverse services staying pending after they are accepted. [#3526]
- Fix likes from some accounts being recorded as multiple duplicate comments. [#3468]
- Fix posts being removed from the Fediverse when edited while scheduled for a future publish date. [#3443]
- Fix repeated deliveries of a like or repost creating new comments after the original was marked as spam or moved to the trash. [#3532]
- Fix Starter Kit imports failing on Fediverse servers that require signed requests. [#3531]
- Fix the scheduled refresh of remote profiles so it actually re-fetches from the remote server. Previously, stale avatars and bios for commenters never updated until they sent a new activity to your site. [#3450]
- Fix URLs with multiple query parameters (such as avatars, images, profile links, and podcast media) being corrupted in content sent to the Fediverse. [#3567]
- Prevent caching non-actor objects (such as notes) as remote profiles. [#3452]
- Refresh cached remote profiles in place during scheduled updates to avoid creating duplicate copies. [#3451]
- Show the Fediverse Preview for scheduled posts instead of the regular post preview. [#3540]
- Stop storing responses from remote servers in the database when they were requested uncached. [#3531]

## [9.0.2] - 2026-06-29
### Fixed
- Improve handling of content received from other servers. [#3475]

## [9.0.1] - 2026-06-15
### Added
- Add FAQ guides that help solve follow requests stuck on "pending" and comments from the Fediverse not showing up. [#3404]

### Fixed
- Notify followers about your new preference when the Starter Kit policy setting changes, so other servers no longer act on an outdated one. [#3405]
- Publish the Starter Kit consent policy only on your own blog and author profiles, no longer on system or third-party profiles. [#3406]
- Starter Kit consent now also works for the blog profile, not just for individual authors. [#3407]

## [9.0.0] - 2026-06-10
### Security
- Enforce the signing-key host check on incoming federated activities regardless of how the key identifier is formatted. [#3357]
- Fix the real-time activity stream so it only returns the requesting user's own activities. [#3356]
- Harden the Site Health connectivity check so it cannot be used to reach unsafe network addresses. [#3391]
- Only share comment replies in the Fediverse when the post they belong to is itself federated, so replies on private or non-federated posts stay private. [#3374]
- Prevent a remote server from discovering which of your followers belong to a third-party server it does not control. [#3390]
- Prevent logged-in users from viewing another user's private outbox activities. [#3358]
- Prevent remote servers from modifying or deleting federated profiles, posts, and interactions they do not own. [#3360]
- Rate-limit the remote-follow lookup to prevent it from being abused to trigger outbound requests. [#3361]
- Stop the OAuth token introspection endpoint from revealing another user's token details to logged-in users. [#3363]
- Stop the quote-authorization stamp from exposing a post's other metadata. [#3364]

### Added
- Add a Distribution Mode setting to control how quickly posts are delivered to followers. [#3044]
- Add an opt-in setting to consent to inclusion in Starter Kits (also called Starter Packs or Featured Collections). Off by default. Find it under Settings, ActivityPub, Activities. [#3277]
- C2S clients can now request canonical SWICG ActivityPub API scope names such as `activitypub:read:all` and `activitypub:write:all`, and the OAuth discovery metadata advertises them. [#3328]
- C2S token responses now include `activitypub_actor_id` so clients following the SWICG ActivityPub API Basic Profile can discover the authenticated actor. [#3328]
- Generate a blurred color preview (blurhash) for images so other fediverse apps can show a placeholder while your photos load. [#3355]
- Quote notification emails now include a link to the post that quoted you, so you can review and respond more quickly. [#3351]
- Warn in the editor before making a post that's already shared on the Fediverse a draft, private, or password-protected, since followers' copies will be removed. [#2860]

### Changed
- Add the `blurhash` term to the outbound JSON-LD `@context` so attachments that include a `blurhash` property are strictly correct JSON-LD, matching Mastodon's own context shape. [#3327]
- Federated posts moved to draft, pending, private, trash, or password-protected now send a Delete to followers (previously sent a placeholder "editing" Update or were silent). [#2860]
- OAuth rate-limit responses now include a `Retry-After` header so clients know how long to wait before retrying. [#3328]
- Updated a build dependency to a clean release now that a fixed version is available. [#3346]

### Removed
- Removed functions, methods, and the Follower class that were deprecated in versions 7.0 through 7.4. [#3387]

### Fixed
- Fix a fatal error when receiving a new follower while the Stream plugin is active. [#3372]
- Fix a follow request being marked as accepted when the confirmation came from a different account than the one being followed. [#3377]
- Fix the Fediverse settings appearing twice and visibility changes not saving in the block editor when the Classic Editor plugin is also active. [#3354]
- Fix the introduction video failing to load on the Getting Started help screen. [#3350]
- Follower synchronization with Mastodon no longer fails, signed requests with query strings now verify correctly. [#3369]
- Harden the Blurhash encoder: skip decompression-bomb images before decoding, flatten transparency onto white so transparent logos no longer produce near-black placeholders, and defer the cron encode until attachment metadata is saved. [#3386]
- Images and videos placed in a Media & Text block are now included when a post is shared to the Fediverse. [#3355]
- Requests from other platforms to feature your posts are now handled correctly instead of being ignored. [#3385]
- RSS and Atom feeds now show a simple `@username` mention in place of the reply block's full embed card, which only renders properly when the plugin's frontend CSS is loaded. [#3340]
- Stop a deprecation notice from appearing in the error log when the NodeInfo plugin is also active. [#3347]

## [8.3.0] - 2026-05-18
### Security
- Block a recently compromised JavaScript dependency from being installed during builds. [#3285]

### Added
- Allow site administrators to post from third-party apps on behalf of the site's blog account. [#3281]
- Store content warnings from posts published through third-party ActivityPub apps so they federate correctly. [#3292]

### Changed
- Improve compatibility with newer Fediverse servers by recognizing the FEP-3b86 Object Intent link when resolving remote follow and other intent endpoints. [#3316]
- Improve compatibility with newer Fediverse servers by recognizing the standardized FEP-3b86 follow link for remote follows. [#3307]
- Refresh bundled scripts to pick up the latest WordPress component updates. [#3259]
- Stagger background data processing after plugin updates to reduce server load on hosts running many sites. [#3275]

### Fixed
- Allow third-party apps connected to your site to look up Fediverse users by their handle (like @user@example.com). [#3289]
- Fix ActivityPub blocks and widgets failing to load on cross-origin embeds (such as WordPress.com sites) due to a missing nonce header in the CORS allow-list. [#3308]
- Fix a JavaScript console error that could appear on pages with the Follow, Reactions, Followers, Following, or Remote Reply blocks. [#3302]
- Fix posting an Undo of a Follow through the outbox API failing with a server error or silently leaving the follow in place. [#3303]
- Prevent a PHP warning during the monthly statistics backfill when an outbox item disappears between lookup steps. [#3284]
- Prevent private outbox items authored by the site account from being visible to logged-out visitors at their permalink URLs. [#3281]
- Prevent the site's follower and following lists from being visible to logged-out visitors when the social graph is set to private. [#3281]
- Reduce database overhead on sites with many deleted posts by moving the tombstone registry to its own storage. [#3293]
- Set a real author on posts created via the blog actor outbox so they no longer appear without a byline. [#3283]
- Silence the upcoming WordPress 7.0 deprecation warning about `data-wp-on-async` by switching the plugin's interactive blocks to the new `withSyncEvent()` helper. [#3220]

## [8.2.1] - 2026-05-01
### Security
- Hardened how the inbox processes large recipient lists in incoming activities. [#3094]

### Fixed
- Fix monthly and annual Fediverse Stats emails being sent more than once per period when the scheduler ran multiple times. [#3252]

## [8.2.0] - 2026-04-27
### Security
- ActivityPub REST endpoints no longer advertise credentialed cross-origin access. Browser-based clients using OAuth bearer tokens continue to work as before. [#3237]
- Aligned the deprecated signature verifier's clock tolerance with the supported verifiers. [#3230]
- Blocked additional reserved IPv6 ranges from outbound request safety checks. [#3233]
- Decoded percent-encoded forms in the follower sync authority before the safety check. [#3234]
- Fail closed when an OAuth request can't be tied to a client IP, instead of sharing one rate-limit bucket. [#3231]
- Hardened input handling for incoming federated activity types. [#3227]
- Hardened outbound request handling for third-party app connections and live activity streams. [#3228]
- Hardened outbound request safety to cover IPv6-only third-party hosts. [#3229]
- Per-IP rate limits now only trust the actual TCP peer by default, so an attacker on a directly-exposed site cannot bypass the cap by spoofing X-Forwarded-For or similar proxy headers. Sites behind a trusted reverse proxy (Cloudflare, Akamai, nginx) can opt the relevant header back in via the new "activitypub_client_ip_sources" filter. [#3238]
- Reject follower sync requests targeted at internal-network hosts at the route layer. [#3232]
- Required signatures on HEAD requests to peer-only endpoints. [#3235]

### Changed
- Development tooling: require PHPUnit 9.6.33 or newer (security fix CVE-2026-24765). No runtime impact for end users. [#3224]
- OAuth public clients must now use PKCE by default, matching OAuth 2.1. Site operators can relax this via the activitypub_oauth_require_pkce filter if legacy clients need to connect. [#3222]
- Returned the standard rate-limit response from the OAuth token endpoint when too many requests are sent. [#3236]

### Fixed
- Delete activities no longer bypass signature verification on endpoints that explicitly require it. [#3223]
- OAuth token revocation now verifies the caller owns the token being revoked. [#3221]
- Tighten HTTP signature verification: narrow the clock-skew window, reject signatures that carry no freshness timestamp, and cap unreasonable expiry times. Peers that sign without a Date or creation timestamp will no longer verify. [#3212]
- Trim dev-only configuration files from the plugin release package. [#3214]

## [8.1.1] - 2026-04-22
### Added
- Added the `activitypub_post_object_type` filter so plugins can override the federated object type (Note, Article, Page) for a post. [#3210]

### Changed
- Always flush rewrite rules at the end of a plugin migration so that users upgrading across multiple versions do not miss a flush. [#3207]

### Fixed
- Fix the Fediverse stats widget on sites where the REST namespace is remapped, such as WordPress.com. [#3206]
- Harden the reactions API response so stored author names and URLs cannot introduce markup or non-HTTP schemes into the JSON output. [#3211]
- Stop hiding posts that contain a federated reply block from the main blog listing and the admin post list on sites that do not use the Posts and Replies block. [#3209]

## [8.1.0] - 2026-04-21
### Security
- Add rate limiting to app registration to prevent abuse. [#3108]
- Fix blog actor outbox exposing private activities to unauthenticated visitors. [#3188]
- Restrict localhost URL allowance to local development environments only. [#3076]
- Verify that the signing key belongs to the same server as the activity actor. [#3109]

### Added
- Add a "Posts and Replies" tab bar for author archives that filters between posts and replies, similar to Mastodon's profile view. [#3036]
- Add a liked collection to actor profiles, showing all posts the actor has liked. [#3128]
- Add a seasonal starter pattern that suggests sharing Fediverse stats when creating a new post in December and January. [#3160]
- Add a stats block that displays annual Fediverse statistics as a card on the site and as a shareable image on the Fediverse, with automatic color and font adoption from the site's theme. [#3126]
- Added `activitypub_pre_get_by_id` filter to allow plugins to register custom virtual actors resolved by ID. [#3124]
- Add EXIF metadata support for image attachments using Vernissage namespace. [#2751]
- Add new Fediverse Following Page and Profile Page block patterns. [#3032]
- Add OAuth server metadata and registration endpoint discovery to actor profiles. [#3175]
- Add real-time streaming for inbox and outbox updates via Server-Sent Events (SSE). [#2945]
- Add support for Block, Add (pin post), and Remove (unpin post) activities via Client-to-Server API. [#3033]
- Add support for check-in activities posted via compatible apps. [#3120]
- Add support for importing Starter Packs in both the Pixelfed and Mastodon formats. [#3168]
- Add tags.pub integration to supplement tag timelines with posts from across the Fediverse. [#3151]
- Support for ActivityPub Client-to-Server (C2S) protocol, allowing apps like federated clients to create, edit, and delete posts on your behalf. [#2851]

### Changed
- Block patterns for follow, following, and profile pages are now only suggested when editing pages. [#3032]
- Fix notification pagination when using Enable Mastodon Apps: use date-constrained queries instead of truncating the shared notification pool, and expose `$limit`, `$before_date`, and `$after_date` as additional filter arguments so third-party handlers can fetch the correct window. [#3150]
- Improve the pre-publish format suggestion panel with clearer messages and a confirmation after applying a format. [#3090]
- Podcast episodes now respect the configured object type setting instead of always being sent as "Note". [#3065]
- Show reaction action buttons even when a post has no reactions yet. [#3091]

### Fixed
- ActivityPub endpoints that surface comment, reply, like, share, and remote-reply metadata now honor the parent post's visibility setting. [#3203]
- Added validation for SSE access tokens passed via query parameter. [#3095]
- Fix account migration (Move) not working when moving back to an external account. [#3102]
- Fix a fatal error during activity delivery when the outbox item has been deleted. [#3058]
- Fix a fatal error when receiving activities with a non-string language property. [#3158]
- Fix a fatal `array_keys(null)` in `Comment::get_comment_type_slugs()` that could take down any request where a third-party plugin transitioned a custom comment type before `add_comment_type()` had been called. [#3196]
- Fix a missing script dependency notice on the admin page in WordPress 6.9.1 and later. [#3084]
- Fix BuddyPress @mention filter corrupting Fediverse Followers and Following blocks. [#3174]
- Fix cleanup jobs silently doing nothing on sites where purge retention options were not set. [#3138]
- Fix comments on remote posts being incorrectly held in moderation. [#3129]
- Fix double-encoded HTML entities in post titles on the Fediverse Stats dashboard. [#3162]
- Fixed an issue where quote authorization stamps could reference unrelated posts. [#3093]
- Fixed double-encoding of special characters in comment author names on updates. [#3100]
- Fixed emoji shortcode replacement to handle special characters in emoji names correctly. [#3099]
- Fix fatal error when other plugins hook into the user agent filter expecting two arguments. [#3179]
- Fix Fediverse Preview showing the standard web view instead of the ActivityPub preview for draft posts. [#3054]
- Fix OAuth authentication failing for local development clients using localhost subdomains. [#3169]
- Fix performance regression from reply-exclusion filter by skipping it for queries targeting non-ActivityPub post types. [#3153]
- Fix Reader feed failing to load with newer WordPress versions. [#3194]
- Fix remote actor avatars getting stuck on broken URLs when the original image becomes unavailable. [#3041]
- Fix Site Health check showing an empty error message when the WebFinger endpoint is not reachable. [#3123]
- Fix the Fediverse profile "Joined" date showing the oldest post date instead of when the site started federating. [#3137]
- Fix the Fediverse profile showing an inflated post count by excluding incoming comments from the total. [#3136]
- Fix Update handler using stale local actor data instead of the activity payload [#3110]
- Improved HTTP Signature validation for requests with a missing Date header. [#3096]
- Only allow S256 as PKCE code challenge method for OAuth authorization. [#3097]
- Prevent third-party plugin UI elements and scripts from appearing in federated content. [#3049]
- Require signed peer requests for the followers synchronization endpoint per FEP-8fcf. [#3202]
- Show a styled error page instead of raw technical output when an OAuth application cannot be reached during authorization. [#3043]
- Strip private recipient fields from all outgoing activities to prevent leaking private audiences. [#3200]
- Sync ActivityPub blog actor settings via Jetpack. [#3176]
- Use ap_actor post ID for remote account IDs instead of remapping URI strings. [#3152]
- Use safe HTTP request for signature retry to prevent requests to private IP ranges. [#3098]
- Validate emoji updated timestamps before storing them. [#3101]

## [8.0.2] - 2026-03-17
### Security
- Prevent non-public posts (drafts, scheduled, pending review) from being accessible via ActivityPub. [#3045]

## [8.0.1] - 2026-03-11
### Changed
- Simplify the follow page block pattern to avoid duplicate headings and improve accessibility. [#3029]

### Fixed
- Fix dark sidebar colors appearing incorrectly with non-default admin color schemes. [#3022]
- Fix Fediverse Reactions block not aligning with post content in block themes. [#3025]
- Fix new posts being marked as modified on load, which prevented Gutenberg's starter pattern modal from appearing. [#3028]

## [8.0.0] - 2026-03-04
### Security
- Prevent private recipient lists from being shared when sending activities to other servers. [#2956]

### Added
- Add a help section to interaction dialogs explaining the Fediverse and why entering a profile is needed. [#2993]
- Add a notice on the Settings page to easily switch from legacy template mode to automatic mode. [#2985]
- Add a pre-publish suggestion that recommends a post format for better compatibility with media-focused Fediverse platforms. [#2971]
- Add a Site Health check that warns when plugins are causing too many federation updates. [#2928]
- Add backwards compatibility for the `ACTIVITYPUB_DISABLE_SIDELOADING` constant and `activitypub_sideloading_enabled` filter from version 7.9.1. [#2973]
- Add bot account snippet that marks ActivityPub profiles as automated accounts, displaying a "BOT" badge on Mastodon and other Fediverse platforms. [#2861]
- Add Cache namespace for remote media caching with CLI commands, improved MIME validation, and filter-based architecture. [#2887]
- Add federation of video poster images set in the WordPress video block. [#2982]
- Add Locale from Tags community snippet. [#2923]
- Add optional Like and Boost action buttons to the Fediverse Reactions block, allowing visitors to interact with posts from their own server. [#2988]
- Add pre-built Fediverse block patterns for easy profile, follow page, and sidebar setup. [#2891]
- Add snippet for blockless fediverse reactions [#2958]
- Add `wp activitypub fetch` CLI command for fetching remote URLs with signed HTTP requests. [#2906]

### Changed
- Improved active user counting for NodeInfo to include all federated content types and comments. [#2943]
- Improve language map resolution to strictly follow the ActivityStreams spec. [#2979]
- Superseded outbox activities are now removed instead of kept, reducing clutter in the outbox. [#2932]
- The minimum required PHP version is now 7.4. [#2942]

### Fixed
- Accept incoming activities from servers that use standalone key objects for HTTP Signatures. [#2935]
- Fix a crash on servers where WordPress uses FTP instead of direct file access for media caching. [#2974]
- Fix a crash when receiving posts from certain federated platforms that send multilingual content. [#2950]
- Fix automatic cleanup of old activities failing silently on sites with large numbers of outbox, inbox, or remote post items. [#2929]
- Fix comment count to properly exclude likes, shares, and notes. [#2913]
- Fix follow button redirect from Mastodon not being recognized. [#2922]
- Fix modal overlay not covering the full screen on block themes. [#3000]
- Fix outbox invalidation canceling pending Accept/Reject responses to QuoteRequests for the same post. [#2911]
- Fix QuoteRequest handler to derive responding actor from post author instead of inbox recipient. [#2924]
- Fix reactions block buttons inheriting theme background color on classic themes. [#2996]
- Fix reactions block layout on small screens and remove unwanted button highlight when clicking action buttons. [#2992]
- Fix signature verification rejecting valid requests that use lowercase algorithm names in the Digest header. [#2949]
- Fix soft-deleted posts being served instead of a tombstone when the post is re-saved. [#2991]
- Improve compatibility with federated services that use a URL reference for the actor's public key. [#2947]
- Improve handling of all public audience identifiers when sending activities to followers and relays. [#2944]

## [7.9.1] - 2026-02-09
### Added
- Add option to disable direct file sideloading via `ACTIVITYPUB_DISABLE_SIDELOADING` constant or `activitypub_sideloading_enabled` filter, and `activitypub_remote_media_url` filter for CDN proxying. [#2899]

### Changed
- Refactor attachment download handling. [#2889]
- Restructure CLI into separate command classes for better organization. [#2881]

### Fixed
- Fix PHP warning when deleting quote comments. [#2895]
- Fix podcast integrations ignoring user-configured content template settings. [#2897]

## [7.9.0] - 2026-02-05
### Added
- Add Fediverse Following block to display accounts the user follows. [#2837]
- Add global default quote policy setting that can be overridden per-post. [#2839]
- Add health check to verify scheduled events are registered and auto-repair if missing. [#2786]
- Add location support for posts using WordPress Geodata post meta fields. [#2760]
- Add Podlove Podcast Publisher integration for podcast episode federation. [#2870]
- Add site health check to detect when security plugins block REST API access. [#2768]
- Add Social Web item to the admin bar for quick access to the reader. [#2805]
- Add soft delete support with Tombstone objects when post visibility changes to local/private. [#2824]
- Custom emoji from the fediverse now show up instead of looking like :sad_trombone:. [#1129]
- Make actor table columns filterable. [#2704]
- Send Add/Remove activities when changing a post's sticky status to improve interoperability with the featured collection. [#2802]
- Show warning instead of reply link when logged-in user cannot federate replies to fediverse comments. [#2817]

### Changed
- Defer outbox processing to async execution to improve publishing performance. [#2761]
- Move Jest mocks to tests/js directory for better project organization. [#2841]
- Remove redundant __nextHasNoMarginBottom props now that @wordpress/components 32.0.0 defaults to true. [#2801]
- Revert to synchronous outbox processing with improved timeout handling and WebFinger error caching. [#2858]

### Fixed
- Don't filter the comment query when type__not_in has been set [#2850]
- Filter comments on ActivityPub posts from REST API responses. [#2777]
- Fix duplicate media attachments when featured image is also in post content. [#2814]
- Fixed Federated Reply block embed appearing squished at 200x200 pixels for same-site embeds by passing explicit width to wp_oembed_get(). [#2848]
- Fixed pagination metadata leaking when "Hide Social Graph" privacy setting is enabled. [#2836]
- Fix migration activities not being scheduled for federation due to hook registration timing. [#2771]
- Fix older comments with empty type not being federated. [#2831]
- Fix quote requests from Mastodon not being received. [#2830]
- Fix users not being accessible after re-enabling ActivityPub capability. [#2875]
- Hide admin REST API endpoints from discovery index. [#2873]
- Show informational notice when trying to follow an already-followed account. [#2815]
- Skip fetching public audience identifiers which are not actual recipients. [#2794]

## [7.8.5] - 2026-01-14
### Fixed
- Only disable blocks for ClassicPress, not when Classic Editor plugin is installed. [#2765]

## [7.8.4] - 2026-01-13
### Fixed
- Fix Follow requests from Pixelfed and other implementations that don't set audience fields. [#2755]

## [7.8.3] - 2026-01-12
### Security
- Improved security of the Starter Kit URL import by using wp_safe_remote_get. [#2732]

### Added
- Force content negotiation on author pages when using permalink as Actor ID. [#2745]

### Fixed
- Actors: avoid PHP warnings when trying to fetch invalid actor. [#2722]
- Add ClassicPress compatibility by detecting it and disabling block support. [#2752]
- Check if WP_Filesystem initialized successfully before using it to prevent fatal errors on hosts using FTP-based filesystem. [#2728]
- Fixed ActivityPub comments being marked as spam by Akismet. [#2740]
- Fixed an issue where embedding remote posts could fail when the author's profile was temporarily unavailable. [#2681]
- Fixed flaky test for purge_ap_posts due to date boundary condition with -1 month [#2724]
- Fixed inconsistent default value for the hashtag setting on new installations. [#2726]
- Fixed reactions popover styles affecting other WordPress popovers. [#2733]
- Fixed unwanted 301 redirects on search and posts pages when using Polylang or similar plugins. [#2734]
- Fixed unwanted tags being created from hashtags inside links and other protected HTML elements. [#2727]
- Fixed visibility setting not being saved correctly in block editor and classic editor. [#2737]

## [7.8.2] - 2025-12-21
### Fixed
- Fix error when receiving replies to non-existent posts. [#2673]
- Fix fatal error when displaying posts with mentions of invalid remote actors. [#2676]

## [7.8.1] - 2025-12-18
### Added
- Hide comments from specific post types in the WordPress admin comments list. [#2669]

### Fixed
- Prevent comment email notifications for ap_post. [#2667]
- Prevent post creation when Reader is deactivated. [#2666]

## [7.8.0] - 2025-12-17
### Added
- Add blocklist subscriptions for automatic weekly synchronization of remote blocklists. [#2590]
- Add compact display style to Reactions block that hides avatars. [#2617]
- Add domain blocklist importer for bulk importing blocked domains. [#2589]
- Add image optimization for imported attachments (resize to 1200px max, convert to WebP). [#2609]
- Add local caching for remote actor avatars. [#2610]
- Add relay mode to forward public activities to all followers. [#2560]
- Add scheduled cleanup for remote posts, preserving posts with local user interactions. [#2612]
- Add site health check to warn when DISABLE_WP_CRON may impact ActivityPub functionality [#2583]
- Add Social Web Reader for browsing ActivityPub content directly in WordPress admin. [#2388]
- Delete remote posts on plugin uninstall. [#2632]
- Mastodon importer now imports self-replies as comments, preserving thread structure. [#2572]

### Changed
- Cache expensive operations in Post transformer to improve performance. [#2604]
- Improve performance and reliability of @-mention detection. [#2602]
- Reduce federated content size by removing unnecessary HTML attributes. [#2643]
- Skip downloading video and audio attachments, embedding remote URLs directly to avoid storage limits. [#2608]
- Use stable term_id-based IDs for Term transformer to ensure federation consistency. [#2605]
- Wrap blocked domains and keywords tables in collapsible details element. [#2591]

### Fixed
- Ensure NodeInfo accurately represents site administrators to the Fediverse. [#2639]
- Fediverse Followers block now works correctly when the "Hide Social Graph" privacy option is enabled. [#2625]
- Fix NodeInfo documents to comply with schema specification. [#2603]
- Follow Me block button-only style now respects width settings from the inner Button block. [#2588]
- Preserve whitespace inside preformatted elements when federating content. [#2621]
- Respect WordPress "show avatars" setting for remote actor avatars. [#2611]

## [7.7.1] - 2025-12-04
### Fixed
- Fix admin styling for quote comments to match likes and reposts [#2584]
- Mastodon importer now unpacks nested archives instead of getting confused by the extra folder. [#2581]
- Add individually specified recipients to public activities in shared inbox. [#2585]

## [7.7.0] - 2025-12-03
### Added
- Add documentation guide for using ActivityPub blocks in classic themes with Block Template Parts [#2577]
- Added a new Fediverse Extra Fields block to display ActivityPub extra fields, featuring compact, stacked, and card layouts with flexible user selection options. [#2439]
- Added support for quote comments, improving detection and handling of quoted replies and links in post interactions. [#2330]
- Add notifications for boosts, likes, and new followers in Mastodon apps via the Enable Mastodon Apps plugin [#2570]
- Adds support for turning tags, categories, and custom taxonomies into federated collections in the Reader view so you can browse and follow topics more seamlessly. [#2552]
- Prevent email notifications for comments on ActivityPub custom post types. [#2527]
- Send a Reject activity when a quote comment is deleted, revoking previous quote permissions and ensuring consistent inbox handling. [#2460]
- Store and retrieve webfinger acct for remote actors to improve identification and reduce lookups [#2575]

### Changed
- Improve gallery and image block markup for ap_posts with better alt text and optimized layouts. [#2519]
- Improve support for media attachments by handling Audio, Document, and Video object types in addition to Images. [#2544]
- Maintain consistent return values in Create handler. [#2507]
- Remove trailing hashtags from incoming posts to prevent duplication with taxonomy tags. [#2518]
- Store comments and reactions from followed actors on reader posts, and keep them separate from your site's comments in wp-admin. [#2508]
- Update compatibility testing for PHP 8.5 and WordPress 6.9 [#2573]
- Use tag name instead of slug for hashtag display. [#2564]

### Fixed
- Always includes id, first, and last links in collection responses, ensuring followers and following lists display correctly in Mastodon. [#2473]
- Automatically approves reactions on ActivityPub posts in the Reader view for a smoother, more seamless interaction experience. [#2526]
- Deliver public activities to followers only. [#2539]
- Disable REST API endpoints for internal post types. [#2463]
- False mention email notifications for users in CC field without actual mention tags. [#2532]
- Fix "Filename too long" errors when downloading attachments from URLs with query parameters (e.g., Instagram CDN URLs). [#2499]
- Fix make_clickable corrupting existing anchor tags in ActivityPub content [#2517]
- Fix PHP 8.5 deprecation warnings for ReflectionProperty::setAccessible() and ReflectionMethod::setAccessible() [#2574]
- Improved handling of unusual activity data to avoid errors when activities contain unexpected formats. [#2502]
- Preserve original ActivityPub activity timestamps when creating posts and comments instead of using current time. [#2498]
- Prevented duplicate email notifications when ActivityPub instances re-send Follow activities for already-following actors. [#2452]
- Prevents unwanted comment types—like pingbacks, trackbacks, notes and custom system comments, from being federated, ensuring only real user comments are shared with the fediverse. [#2494]
- Removed a redundant instruction from the custom post content settings to simplify the UI. [#2471]
- Reply block now shows fallback link when oEmbed fails instead of empty div. [#2571]
- Simplified reply links by removing special handling for federated comments, making replies work the same for all comments where replying is allowed. [#2461]
- Undefined array key warning in Scheduler::async_batch when called without arguments. [#2497]

## [7.6.1] - 2025-11-12
### Fixed
- Fixed compatibility with Pixelfed and similar platforms by treating activities without recipients as public, ensuring boosts and reposts work correctly. [#2448]
- Improved delete handling for remote replies by streamlining tombstone detection and simplifying object deletion for more reliable and consistent behavior. [#2446]
- Made inbox cleanup more reliable and ensuring deduplication only affects the specific activity being removed. [#2447]

## [7.6.0] - 2025-11-11
### Added
- Add bidirectional transforms between reply and embed blocks for improved user experience. [#2244]
- Add Command Palette integration for quick navigation to ActivityPub admin pages [#2315]
- Added a new ap_object post type and taxonomies for storing and managing incoming ActivityPub objects, with updated handlers [#2311]
- Added a privacy option to hide followers and following lists from profiles while keeping follow relationships intact. [#2294]
- Added a scheduled task and setting to automatically purge old inbox items, helping maintain site performance and storage control. [#2305]
- Added fallback to trigger create handling when updates fail for missing posts or comments, ensuring objects are properly created. [#2328]
- Added immediate dispatch for Accept activities to speed up quoted posts while keeping scheduled processing for compatibility with other instances. [#2301]
- Added new configuration options to better manage traffic spikes when federating posts, allowing finer control over retry limits, delays, and batch pauses. [#2360]
- Added support for FEP-8fcf follower synchronization, improving data consistency across servers with new sync headers, digest checks, and reconciliation tasks. [#2297]
- Add LiteSpeed Cache integration to prevent ActivityPub JSON responses from being cached incorrectly. Includes automatic .htaccess rules and Site Health check to ensure proper configuration. [#1683]
- Add quote visibility setting for Classic Editor users. [#2374]
- Add unified attachment processor for handling ActivityPub media imports from both remote URLs and local files, with automatic media block generation and Classic Editor support. [#2314]
- Integrate Federated Reply block with WP.com Reader's post share functionality, allowing users to reply to ActivityPub posts directly from the Reader. [#2302]

### Changed
- Added support for FEP-3b86 Activity Intents, extending WebFinger and REST interactions with new Create and Follow intent links. [#2256]
- Added support for the latest NodeInfo (FEP-0151), with improved federation details, staff info, and software metadata for better ActivityPub compliance. [#2255]
- Extended inbox support for undoing Like, Create, and Announce activities, with refactored undo logic and improved activity persistence. [#2295]
- Improved Classic Editor integration by adding better media handling and full test coverage for attachments, permissions, and metadata. [#2387]
- Improved delivery of public and follower activities by expanding local recipient handling to include all ActivityPub-capable users and follower collections. [#2349]
- Improved inbox performance by batching and deduplicating activities, reducing redundant processing and improving handling during high activity periods. [#2376]
- Improved REST API responses with smarter context handling. [#2306]
- Improved REST collection pagination by using explicit total item counts for more accurate results. [#2300]
- Moved default visibility handling from the server to the editor UI, ensuring consistent and flexible ActivityPub visibility settings across both block and classic editors. [#2378]
- Prevented self-announcing by ignoring announces from the blog actor, while still processing announces from user and external actors. [#2437]
- Refactored activity handling to support multiple recipients per activity, allowing posts and interactions to be linked to several local users at once. [#2381]
- Refactored avatar handling into a new system that stores and manages avatars per remote actor, improving reliability and preparing for future caching support. [#2373]
- Refactored the inbox system to use a shared inbox, storing activities once with multiple recipients for improved efficiency and reduced duplication. [#2357]
- Reorganize integration loader and move Stream integration into dedicated folder structure. [#2383]
- Reply posts: do not display post title before @mentions in posts that are replies to somebody else [#2419]
- Simplified configuration by always enabling the shared inbox and removing its separate setting, UI field, and related logic. [#2359]
- Simplified inbox storage settings, allowing certain activities (like deletes) to be skipped to reduce unnecessary database use. [#2363]
- Simplify follow() API return types to int|WP_Error for better predictability. [#2384]
- Updated inbox handling to support multiple users receiving the same activity and improve overall data consistency. [#2350]
- Updated mailer hooks to send notifications only when activities are successfully handled, preventing emails for failed events. [#2304]
- Update plugin short description to be more user-friendly. [#2425]

### Fixed
- Added a safeguard to ensure the plugin works correctly even when no post types are selected. [#2339]
- Added a safety check to prevent errors when resolving comment author hostnames without a valid IP address. [#2342]
- Fixed activity processing to handle QuoteRequest and other edge cases more reliably. [#2260]
- Fixed an issue with post content templates to ensure the correct fallback is always applied. [#2417]
- Fixed fatal error when transformer Factory receives WP_Error objects. [#2429]
- Fixed HTML entity encoding in extra field names when displayed on ActivityPub platforms [#2261]
- Fixed typo in example, improve quoting description. [#2290]
- Fix Following table error message to display user input instead of empty string when webfinger lookup fails. [#2385]
- Fix infinite recursion when storing remote actors with mentions in their bios [#2369]
- Fix local inbox delivery to use internal REST API instead of HTTP, enabling local follows and proper boost counting. [#2379]
- Fix logic errors in Move handler: remove redundant assignment and fix variable name collision. [#2362]
- Fix public key retrieval for GoToSocial profiles with path-based key URLs. [#2354]
- Improved actor resolution by prioritizing blog actor detection before remote actor checks and refining home page URL handling. [#2327]
- Improved handling of empty fields for better compatibility with Pixelfed and more consistent fallback behavior across actor names, URLs, and related data. [#2433]
- Improved hashtag encoding for consistent formatting. [#2352]
- Improved Jetpack integration by initializing it during the WordPress startup process. [#2434]
- Refactored Mastodon import handling to use consistent array-based data, improving reliability and compatibility across all import scenarios. [#2412]
- Reply block now properly validates ActivityPub URLs before setting inReplyTo field [#2252]

## [7.5.0] - 2025-10-01
### Added
- Added a setting to control who can quote your posts. [#2207]
- Added support for QuoteRequest activities (FEP-044f), enabling proper handling, validation, and policy-based acceptance or rejection of quote requests. [#2240]
- Add upgrade routine to enable ActivityPub feeds in WordPress.com Reader [#2243]
- Add Yoast SEO integration for author archives site health check. [#2193]
- Improved interaction policies with clearer defaults and better Mastodon compatibility. [#2221]
- New site health check warns if active Captcha plugins may block ActivityPub comments. [#2231]
- Sync following meta to enable RSS feed subscriptions for ActivityPub actors in WordPress.com Reader [#2226]
- You can now follow people and see their updates right in the WordPress.com Reader when using Jetpack or WordPress.com. [#2241]

### Changed
- Added support for fetching actors by account identifiers and improved reliability of actor retrieval. [#2235]
- Clarify error messages in account modal to specify full profile URL format. [#2209]
- Improved checks to better identify public Activities. [#2206]
- Improved compatibility by making the 'implements' field always use multiple entries. [#2195]
- Improved recipient handling for clarity and improved visibility handling of activities. [#2210]
- Remote reply blocks now sync account info across all blocks on the same page [#2211]
- Standardized notification handling with new hooks for better extensibility and consistency. [#2223]
- Updated sync allowlist to add support for Jetpack notifications of likes and reposts. [#2233]

### Fixed
- Fixed an issue where post metadata in the block editor was missing or failed to update. [#2232]
- Fix Flag activity object list processing to preserve URL arrays [#2200]
- Fix PHP warning in bulk edit scenario when post_author is missing from $_REQUEST [#2230]
- Posts now only fall back to the blog user when blog mode is enabled and no valid author exists, ensuring content negotiation only runs if an Actor is available. [#2246]

## [7.4.0] - 2025-09-15
### Added
- Add activitypub_json REST field for ap_actor posts to access raw JSON data [#2121]
- Add Delete activity support for permanently deleted federated comments. [#2141]
- Added a new WP-CLI command to manage Actors. [#2118]
- Added confirmation step for bulk removal of ActivityPub capability, asking whether to also delete users from the Fediverse. [#2150]
- Adds support for virtual deletes and restores, allowing objects to be removed from the fediverse without being deleted locally. [#2116]
- Add Yoast SEO integration for media pages site health check [#2136]
- Optimized WebFinger lookups by centralizing and caching account resolution for faster, more consistent handling across lists. [#2169]

### Changed
- Clarified the 'attachment' post type description to explain it refers to media library uploads and recommend disabling federation in most cases. [#2153]
- Hide site-wide checkbox in block confirmations when accessed from ActivityPub settings page [#2114]
- Improved ActivityPub compatibility by aligning with Mastodon’s Application Actor. [#2113]
- It’s now possible to reply to multiple posts using multiple reply blocks. [#2160]
- Refactored Reply block to use WordPress core embed functionality for better compatibility and performance. [#2129]
- Use wp_interactivity_config() for static values instead of wp_interactivity_state() to improve performance and code clarity [#2096]

### Deprecated
- ActivityPub now defaults to automated object type selection, with the old manual option moved to Advanced settings for compatibility. [#2148]

### Fixed
- Fix content visibility override issue preventing authors from changing visibility on older posts. [#2139]
- Fix PHP warning when saving ActivityPub settings. [#2137]
- Fix query args preservation in collection pagination links [#2120]
- Fix release script to catch more 'unreleased' deprecation patterns that were previously missed during version updates. [#2171]
- Fix reply block rendering inconsistency where blocks were always converted to @-mentions in ActivityPub content. Now only first reply blocks become @-mentions, others remain as regular links. [#2132]
- Stop sending follow notifications to the Application user, since system-level accounts cannot be followed. [#2117]

## [7.3.0] - 2025-08-28
### Added
- Add actor blocking functionality with list table interface for managing blocked users and site-wide blocks [#2027]
- Add code coverage reporting to GitHub Actions PHPUnit workflow with dedicated coverage job using Xdebug [#2044]
- Add comprehensive blocking and moderation system for ActivityPub with user-specific and site-wide controls for actors, domains, and keywords. [#2020]
- Add comprehensive unit tests for Followers and Following table classes with proper ActivityPub icon object handling. [#2088]
- Added link and explanation for the existing Starter Kit importer on the help tab of the Following pages. [#2029]
- Adds a self-destruct feature to remove a blog from the Fediverse by sending Delete activities to followers. [#2046]
- Adds a User Interface to select accounts during Starter Kit import [#2047]
- Adds support for importing Starter Kits from a link (URL). [#2048]
- Adds support for searching (remote) URLs similar to Mastodon, redirecting to existing replies or importing them if missing. [#2034]
- Adds support for sending Delete activities when a user is removed. [#2066]
- Adds support for Starter Kit collections in the ActivityPub API. [#2049]
- A global Inbox handler and persistence layer to log incoming Create and Update requests for debugging and verifying Activity handling. [#2009]
- Follower lists now include the option to block individual accounts. [#2027]
- Improved handling of deleted content with a new unified system for better tracking and compatibility. [#2066]
- Moderation now checks blocked keywords across all language variants of the content, summary and name fields. [#2093]
- When activated or deactivated network-wide, the plugin now refreshes rewrite rules across all sites. [#2104]

### Changed
- Add default avatars for actors without icons in admin tables [#2106]
- Added support for list of Actor IDs in Starter Kits. [#2039]
- Improve Following class documentation and optimize count methods for better performance [#2086]
- Refactor actor blocking with unified API for better maintainability [#2097]

### Fixed
- Blocks relying on user selectors no longer error due to a race condition when fetching users. [#2105]
- Fix duplicate HTML IDs and missing form labels in modal blocks [#2083]
- Fix malformed ActivityPub handles for users with email-based logins (e.g., from Site Kit Google authentication) [#2082]
- Fix PHP 8.4 deprecation warnings by preventing null values from being passed to WordPress core functions [#2085]
- Improves handling of author URLs by converting them to a proper format. [#2061]
- Improves REST responses by skipping invalid actors in Followers and Following controllers. [#2055]
- More reliable Actor checks during the follow process. [#2041]
- Prevents Application users from being followed. [#2101]
- Proper implementation of FEP 844e. [#2068]
- Switches ActivityPub summaries to plain text for better compatibility. [#2063]

## [7.2.0] - 2025-07-30
### Added
- Add image attachment support to federated comments - HTML images in comment content now include proper ActivityStreams attachment fields. [#1996]
- Link to the following internal dialog for remote interactions, if the feature is enabled. [#2001]
- The followers list now shows follow status and allows quick follow-back actions. [#2003]
- Trigger Actor updates on (un)setting a post as sticky. [#1982]
- You can now use `OrderedCollection`s as starter packs — just drop in the output from a Follower or Following endpoint. [#2028]

### Changed
- Ensure that tests run in production-like conditions, avoiding interference from local development tools. [#2026]
- Moved HTTP request signing to a filter instead of calling it directly. [#1994]

### Fixed
- Allow non-administrator users to use Follow Me and Followers blocks [#2015]
- Correct linking from followers to the following list [#2002]
- Fix avatar rendering for followers with missing icon property [#2010]
- Fix multibyte character corruption in post summaries, preventing Greek and other non-ASCII text from being garbled during text processing. [#1995]
- Informational Fediverse blocks are no longer rendered when posts get added to the Outbox. [#2019]

## [7.1.0] - 2025-07-23
### Added
- Added a first version of the Follow form, allowing users to follow other Actors by username or profile link. [#1930]
- Added initial support for Fediverse Starter Kits, allowing users to follow recommended accounts from a predefined list. [#1919]
- Ensure that all schedulers are registered during every plugin update. [#1959]
- Followers and Following list tables now support Columns and Pagination screen options. [#1925]
- The featured tags endpoint is now available again for all profiles, showing the most frequently used tags by each user. [#1922]
- The `following` endpoint now returns the actual list of users being followed. [#1916]

### Changed
- Follower tables now look closer to what other tables in WordPress look like. [#1913]
- Improved Account-Aliases handling by internally normalizing input formats. [#1974]
- Minor performance improvement when querying posts of various types, by avoiding double queries. [#1907]
- Set older unfederated posts to local visibility by default. [#1900]
- Step counts for the Welcome checklist now only take into account steps that are added in the Welcome class. [#1942]
- Table actions are now faster by using the Custom Post Type ID instead of the remote user URI, thanks to the unified Actor Model. [#1946]
- The following tables now more closely match the appearance of other WordPress tables and can be filtered by status. [#1909]

### Fixed
- Ensure correct visibility handling for `Undo` and `Follow` requests [#1988]
- Ensure that the Actor-ID is always a URL. [#1920]
- Fixed a bug in how follow requests were accepted to ensure they work correctly. [#1931]
- Fixed an issue where the number of followers shown didn’t always match the actual follower list. [#1918]
- Fixed a PHP error that prevented the Follower overview from loading. [#1973]
- Fixed missing avatar class so that CSS styles are correctly applied to ActivityPub avatars on the Dashboard. [#1932]
- Fixed potential errors when unrelated requests get caught in double-knocking callback. [#1985]
- Improved WebFinger fallback to better guess usernames from profile links. [#1979]
- Prevent WordPress from loading all admin notices twice on ActivityPub settings pages. [#1943]
- Removed follower dates to avoid confusion, as they may not have accurately reflected the actual follow time. [#1928]
- Stop purging Follow activities from the Outbox to allow proper Unfollow (Undo) handling. [#1980]

## [7.0.1] - 2025-07-10
### Fixed
- When deleting interactions for cleaned up actors, we use the actor's URL again to retrieve their information instead of our internal ID. [#1915]

## [7.0.0] - 2025-07-09
### Added
- Added basic support for handling remote rejections of follow requests. [#1865]
- Added basic support for RFC-9421 style signatures for incoming activities. [#1849]
- Added initial Following support for Actors, hidden for now until plugins add support. [#1866]
- Added missing "Advanced Settings" details to Site Health debug information. [#1846]
- Added option to auto-approve reactions like likes and reposts. [#1847]
- Added support for namespaced attributes and the dcterms:subject field (FEP-b2b8), as a first step toward phasing out summary-based content warnings. [#1893]
- Added support for the WP Rest Cache plugin to help with caching REST API responses. [#1630]
- Documented support for FEP-844e. [#1868]
- Optional support for RFC-9421 style signatures for outgoing activities, including retry with Draft-Cavage-style signature. [#1858]
- Reactions block now supports customizing colors, borders, box-shadows, and typography. [#1826]
- Support for sending follow requests to remote actors is now in place, including outbox delivery and status updates—UI integration will follow later. [#1839]

### Changed
- Comment feeds now show only comments by default, with a new `type` filter (e.g., `like`, `all`) to customize which reactions appear. [#1877]
- Consistent naming of Blog user in Block settings. [#1862]
- hs2019 signatures for incoming REST API requests now have their algorithm determined based on their public key. [#1848]
- Likes, comments, and reposts from the Fediverse now require either a name or `preferredUsername` to be set when the Discussion option `require_name_email` is set to true. It falls back to "Anonymous", if not. [#1811]
- Management of public/private keys for Actors now lives in the Actors collection, in preparation for Signature improvements down the line. [#1832]
- Notification emails for new reactions received from the Fediverse now link to the moderation page instead of the edit page, preventing errors and making comment management smoother. [#1887]
- Plugins now have full control over which Settings tabs are shown in Settings > Activitypub. [#1806]
- Reworked follower structure to simplify handling and enable reuse for following mechanism. [#1759]
- Screen options in the Activitypub settings page are now filterable. [#1802]
- Setting the blog identifier to empty will no longer trigger an error message about it being the same as an existing user name. [#1805]
- Step completion tracking in the Welcome tab now even works when the number of steps gets reduced. [#1809]
- The image attachment setting is no longer saved to the database if it matches the default value. [#1821]
- The welcome page now links to the correct profile when Blog Only mode was selected in the profile mode step. [#1807]
- Unified retrieval of comment avatars and re-used core filters to give access to third-part plugins. [#1812]

### Fixed
- Allow interaction redirect URLs that contain an ampersand. [#1819]
- Comments received from the Fediverse no longer show an Edit link in the comment list, despite not being editable. [#1895]
- Fixed an issue where links to remote likes and boosts could open raw JSON instead of a proper page. [#1857]
- Fixed a potential error when getting an Activitypub ID based on a user ID. [#1889]
- HTTP signatures using the hs2019 algorithm now get accepted without error. [#1814]
- Improved compatibility with older follower data. [#1841]
- Inbox requests that are missing an `algorithm` parameter in their signature no longer create a PHP warning. [#1803]
- Interaction attempts that pass a webfinger ID instead of a URL will work again. [#1834]
- Names containing HTML entities now get displayed correctly in the Reactions block's list of users. [#1810]
- Prevent storage of empty or default post meta values. [#1829]
- The amount of avatars shown in the Reactions block no longer depends on the amount of likes, but is comment type agnostic. [#1835]
- The command-line interface extension, accidentally removed in a recent cleanup, has been restored. [#1878]
- The image attachment setting now correctly respects a value of 0, instead of falling back to the default. [#1822]
- The Welcome screen now loads with proper styling when shown as a fallback. [#1820]
- Using categories as hashtags has been removed to prevent conflicts with tags of the same name. [#1873]
- When verifying signatures on incoming requests, the digest header now gets checked as expected. [#1837]

## [6.0.2] - 2025-06-11
### Changed
- Reactions button color is now a little more theme agnostic. [#1795]

### Fixed
- "Account Aliases" setting in user profiles get saved correctly again and no longer return empty. [#1798]
- Blocks updated in 6.0.0 are back to not showing up in feeds and federated posts. [#1794]
- Webfinger data from Pleroma instances no longer creates unexpected mention markup. [#1799]

## [6.0.1] - 2025-06-09
### Fixed
- Added fallback for follower list during migration to new database schema. [#1781]
- Avoids the button block breaking for users that don't have the `unfiltered_html` capability.
  Blog users now get their correct post count displayed in the Editor and the front-end. [#1777]
- Improved follower migration: scheduler now more reliable and won't stop too early. [#1778]
- Update the Stream Connector integration to align with the new database schema. [#1787]

## [6.0.0] - 2025-06-05
### Added
- Enhanced markup of the "follow me" block, for a better Webmention and IndieWeb support. [#1771]
- The actor of the replied-to post is now included in cc or to based on the post's visibility. [#1711]

### Changed
- "Reply on the Fediverse" now uses the Interactivity API for display on the frontend. [#1721]
- Bumped minimum required WordPress version to 6.5. [#1703]
- Default avatar and error handling for the reactions popover list. [#1719]
- Ensured that publishing a new blog post always sends a Create to the Fediverse. [#1713]
- Followers block has an updated design, new block variations, and uses the Interactivity API for display on the frontend. [#1747]
- Follow Me and Followers blocks can now list any user that is Activitypub-enabled, even if they have the Subscriber role. [#1754]
- Likes and Reposts for comments to a post are no longer attributed to the post itself. [#1735]
- New system to manage followers and followings more consistently using a unified actor type. [#1726]
- Re-enabled HTML support in excerpts and summaries to properly display hashtags and @-replies, now that Mastodon supports it. [#1731]
- Refactored to use CSS for effects instead of JavaScript, simplifying the code. [#1718]
- Refine the plugin’s handling and storage of remote actor data. [#1751]
- The Follow Me block now uses the latest Block Editor technology for display on the frontend. [#1691]
- The Reactions block now uses the latest Block Editor technology for display on the frontend. [#1722]

### Removed
- Cleaned up the codebase and removed deprecated functions. [#1723]

### Fixed
- Added forward compatibility for Editor Controls, fixing deprecated warnings in the Editor. [#1748]
- Avoid type mismatch when updating `activitypub_content_warning` meta values. [#1766]
- Default number of attachments now works correctly in block editor. [#1765]
- Fixed a bug in Site Health that caused a PHP warning and missing details for the WebFinger check. [#1733]
- Fixes a bug in WordPress 6.5 where the plugin settings in the Editor would fail to render, due to a backwards compatibility break. [#1760]
- Improved automated setup process for the Surge caching plugin. [#1724]
- Improved excerpt handling by removing shortcodes from summaries. [#1730]

## [5.9.2] - 2025-05-16
### Fixed
- Titles added through a Heading block in the Reactions block now stay properly hidden when there are no reactions. [#1709]

## [5.9.1] - 2025-05-15
### Fixed
- Fixed a bug where Reaction blocks without modified titles did not get displayed correctly. [#1705]

## [5.9.0] - 2025-05-14
### Added
- ActivityPub embeds now support audios, videos, and up to 4 images. [#1645]
- Added a check to make sure we only attempt to embed activity objects, when processing fallback embeds. [#1642]
- Add setting to enable or disable how content is tailored for browsers and Fediverse services. [#1639]
- Adjusted the plugin's default behavior based on the caching plugins installed. [#1640]
- A guided onboarding flow after plugin activation to help users make key setup decisions and understand Fediverse concepts. [#1625]
- Author profiles will cap the amount of extra fields they return to 20, to avoid response size errors in clients. [#1660]
- Fediverse Preview in the Editor now also supports video and audio attachments. [#1596]
- Guidance for configuring Surge to support ActivityPub caching. [#1648]
- Help tab section explaining ActivityPub capabilities on the users page. [#1682]
- Profile sections have been moved from the Welcome page to new Dashboard widgets for easier access. [#1658]
- The ActivityPub blog news feed to WordPress dashboard. [#1623]
- The Outbox now skips invalid items instead of trying to process them for output and encountering an error. [#1627]

### Changed
- Batch processing jobs can now be scheduled with individual hooks. [#1521]
- Better error handling when other servers request Outbox items in the wrong format, and 404 pages now show correctly. [#1685]
- Fediverse Previews in the Block Editor now show media items, even if the post has not been published yet. [#1636]
- Hide interaction buttons in emails when the Classic Editor is used. [#1643]
- Improve compatibility with third-party caching plugins by sending a `Vary` header. [#1638]
- Much more comprehensive plugin documentation in the Help tab of ActivityPub Settings. [#1599]
- NodeInfo endpoint response now correctly formats `localPosts` values. [#1667]
- Reactions block heading now uses Core's heading block with all its customization options. [#1657]
- Settings pages are now more mobile-friendly with more space and easier scrolling. [#1684]
- The number of images shared to the Fediverse can now be chosen on a per-post basis. [#1619]
- Updated default max attachment count to four, creating better-looking gallery grids for posts with 4 or more images. [#1607]
- Use a dedicated hook for the "Dismiss Welcome Page Welcome" link. [#1600]
- Use FEP-c180 schema for error responses. [#1563]
- Use `Audio` and `Video` type for Attachments, instead of the very generic `Document` type. [#1486]

### Deprecated
- Deprecated `rest_activitypub_outbox_query` filter in favor of `activitypub_rest_outbox_query`.
  Deprecated `activitypub_outbox_post` action in favor of `activitypub_rest_outbox_post`. [#1628]

### Fixed
- Broken avatars in the Reactions and Follower block are now replaced with the default avatar. [#1695]
- Email notifications for interactions with Brid.gy actors no longer trigger PHP Warnings. [#1677]
- Improved support for users from more Fediverse platforms in email notifications. [#1612]
- Improved the handling of Shares and Boosts. [#1626]
- Issue preventing "Receive reblogs (boosts)" setting from being properly saved. [#1622]
- Mention emails will no longer be sent for reply Activities. [#1681]
- Prevent accidental follower removal by resetting errors properly. [#1668]
- Properly remove retries schedules, with the invalidation of an Outbox-Item. [#1519]
- The blog profile can no longer be queried when the blog actor option is disabled. [#1661]

## [5.8.0] - 2025-04-24
### Added
- An option to receive notification emails when an Actor was mentioned in the Fediverse. [#1577]
- Enable direct linking to Help Tabs. [#1598]
- Fallback embed support for Fediverse content that lacks native oEmbed responses. [#1576]
- Support for all media types in the Mastodon Importer. [#1585]

### Changed
- Added WordPress disallowed list filtering to block unwanted ActivityPub interactions. [#1590]
- Mastodon imports now support blocks, with automatic reply embedding for conversations. [#1591]
- Tested and compatible with the latest version of WordPress. [#1584]
- Updated design of new follower notification email and added meta information. [#1581]
- Update DM email notification to include an embed display of the DM. [#1582]
- Updated notification settings to be user-specific for more personalization. [#1586]

### Fixed
- Add support for Multisite Language Switcher [#1604]
- Better check for an empty `headers` array key in the Signature class. [#1594]
- Include user context in Global-Inbox actions. [#1603]
- No more PHP warning when Mastodon Apps run out of posts to process. [#1583]
- Reply links and popup modals are now properly translated for logged-out visitors. [#1595]

## [5.7.0] - 2025-04-11
### Added
- Advanced Settings tab, with special settings for advanced users. [#1449]
- Check if pretty permalinks are enabled and recommend to use threaded comments. [#1524]
- Reply block: show embeds where available. [#1572]
- Support same-server domain migrations. [#1572]
- Upgrade routine that removes any erroneously created extra field entries. [#1566]

### Changed
- Add option to enable/disable the "shared inbox" to the "Advanced Settings". [#1553]
- Add option to enable/disable the `Vary` Header to the "Advanced Settings". [#1552]
- Configure the "Follow Me" button to have a button-only mode. [#1133]
- Importers are loaded on admin-specific hook. [#1561]
- Improve the troubleshooting UI and show Site-Health stats in ActivityPub settings. [#1546]
- Increased compatibility with Mobilizon and other platforms by improving signature verification for different key formats. [#1557]

### Fixed
- Ensure that an `Activity` has an `Actor` before adding it to the Outbox. [#1564]
- Fixed some bugs and added additional information on the Debug tab of the Site-Health page. [#1547]
- Follow-up to the reply block changes that makes sure Mastodon embeds are displayed in the editor. [#1555]
- Outbox endpoint bug where non-numeric usernames caused errors when querying Outbox data. [#1559]
- Show Site Health error if site uses old "Almost Pretty Permalinks" structure. [#1570]
- Sites with comments from the Fediverse no longer create uncached extra fields posts that flood the Outbox. [#1554]
- Transformers allow settings values to false again, a regression from 5.5.0. [#1567]

## [5.6.1] - 2025-04-02
### Fixed
- "Post Interactions" settings will now be saved to the options table. [#1540]
- So not show `movedTo` attribute instead of setting it to `false` if empty. [#1539]
- Use specified date format for `updated` field in Outbox-Activites. [#1537]

## [5.6.0] - 2025-04-01
### Added
- Added a Mastodon importer to move your Mastodon posts to your WordPress site. [#1502]
- A default Extra-Field to do a little advertising for WordPress. [#1493]
- Move: Differentiate between `internal` and 'external' Move. [#1533]
- Redirect user to the welcome page after ActivityPub plugin is activated. [#1511]
- The option to show/hide the "Welcome Page". [#1504]
- User setting to enable/disable Likes and Reblogs [#1395]

### Changed
- Logged-out remote reply button markup to look closer to logged-in version. [#1509]
- No longer federates `Delete` activities for posts that were not federated. [#1528]
- OrderedCollection and OrderedCollectionPage behave closer to spec now. [#1444]
- Outbox items now contain the full activity, not just activity objects. [#1474]
- Standardized mentions to use usernames only in comments and posts. [#1510]

### Fixed
- Changelog entries: allow automating changelog entry generation from forks as well. [#1479]
- Comments from Fediverse actors will now be purged as expected. [#1485]
- Importing attachments no longer creates Outbox items for them. [#1526]
- Improved readability in Mastodon Apps plugin string. [#1477]
- No more PHP warnings when previewing posts without attachments. [#1478]
- Outbox batch processing adheres to passed batch size. [#1514]
- Permanently delete reactions that were `Undo` instead of trashing them. [#1520]
- PHP warnings when scheduling post activities for an invalid post. [#1507]
- PHP Warning when there's no actor information in comment activities. [#1508]
- Prevent self-replies on local comments. [#1517]
- Properly set `to` audience of `Activity` instead of changing the `Follow` Object. [#1501]
- Run all Site-Health checks with the required headers and a valid signature. [#1487]
- Set `updated` field for profile updates, otherwise the `Update`-`Activity` wouldn't be handled by Mastodon. [#1495]
- Support multiple layers of nested Outbox activities when searching for the Object ID. [#1518]
- The Custom-Avatar getter on WP.com. [#1491]
- Use the $from account for the object in Move activity for external Moves [#1531]
- Use the `$from` account for the object in Move activity for internal Moves [#1516]
- Use `add_to_outbox` instead of the changed scheduler hooks. [#1481]
- Use `JSON_UNESCAPED_SLASHES` because Mastodon seems to have problems with encoded URLs. [#1488]
- `Scheduler::schedule_announce_activity` to handle Activities instead of Activity-Objects. [#1500]

## [5.5.0] - 2025-03-19
### Added
- Added "Enable Mastodon Apps" and "Event Bridge for ActivityPub" to the recommended plugins section. [#1450]
- Added Constants to the Site-Health debug informations. [#1452]
- Development environment: add Changelogger tool to environment dependencies. [#1452]
- Development environment: allow contributors to specify a changelog entry directly from their Pull Request description. [#1456]
- Documentation for migrating from a Mastodon instance to WordPress. [#1452]
- Support for sending Activities to ActivityPub Relays, to improve discoverability of public content. [#1291]

### Changed
- Documentation: expand Pull Request process docs, and mention the new changelog process as well as the updated release process. [#1454]
- Don't redirect @-name URLs to trailing slashed versions [#1447]
- Improved and simplified Query code. [#1453]
- Improved readability for actor mode setting. [#1472]
- Improved title case for NodeInfo settings. [#1452]
- Introduced utility function to determine actor type based on user ID. [#1473]
- Outbox items only get sent to followers when there are any. [#1452]
- Restricted modifications to settings if they are predefined as constants. [#1430]
- The Welcome page now uses WordPress's Settings API and the classic design of the WP Admin. [#1452]
- Uses two-digit version numbers in Outbox and NodeInfo responses. [#1452]

### Removed
- Our version of `sanitize_url()` was unused—use Core's `sanitize_url()` instead. [#1462]

### Fixed
- Ensured that Query::get_object_id() returns an ID instead of an Object. [#1453]
- Fix a fatal error in the Preview when a post contains no (hash)tags. [#1452]
- Fixed an issue with the Content Carousel and Blog Posts block: https://github.com/Automattic/wp-calypso/issues/101220 [#1453]
- Fixed default value for `activitypub_authorized_fetch` option. [#1465]
- Follow-Me blocks now show the correct avatar on attachment pages. [#1460]
- Images with the correct aspect ratio no longer get sent through the crop step again. [#1452]
- No more PHP warnings when a header image gets cropped. [#1452]
- PHP warnings when trying to process empty tags or image blocks without ID attributes. [#1452]
- Properly re-added support for `Update` and `Delete` `Announce`ments. [#1452]
- Updates to certain user meta fields did not trigger an Update activity. [#1452]
- When viewing Reply Contexts, we'll now attribute the post to the blog user when the post author is disabled. [#1452]

## [5.4.1] - 2025-03-04
### Fixed
- Fixed transition handling of posts to ensure that `Create` and `Update` activities are properly processed.
- Show "full content" preview even if post is in still in draft mode.

## [5.4.0] - 2025-03-03
### Added
- Upgrade script to fix Follower json representations with unescaped backslashes.
- Centralized place for sanitization functions.

### Changed
- Bumped minimum required WordPress version to 6.4.
- Use a later hook for Posts to get published to the Outbox, to get sure all `post_meta`s and `taxonomy`s are set stored properly.
- Use webfinger as author email for comments from the Fediverse.
- Remove the special handling of comments from Enable Mastodon Apps.

### Fixed
- Do not redirect `/@username` URLs to the API any more, to improve `AUTHORIZED_FETCH` handling.

## [5.3.2] - 2025-02-27
### Fixed
- Remove `activitypub_reply_block` filter after Activity-JSON is rendered, to not affect the HTML representation.
- Remove `render_block_core/embed` filter after Activity-JSON is rendered, to not affect the HTML representation.

## [5.3.1] - 2025-02-26
### Fixed
- Blog profile settings can be saved again without errors.
- Followers with backslashes in their descriptions no longer break their actor representation.

## [5.3.0] - 2025-02-25
### Added
- A fallback `Note` for `Article` objects to improve previews on services that don't support Articles yet.
- A reply `context` for Posts and Comments to allow relying parties to discover the whole conversation of a thread.
- Setting to adjust the number of days Outbox items are kept before being purged.
- Failed Follower notifications for Outbox items now get retried for two more times.
- Undo API for Outbox items.
- Metadata to New Follower E-Mail.
- Allow Activities on URLs instead of requiring Activity-Objects. This is useful especially for sending Announces and Likes.
- Outbox Activity IDs can now be resolved when the ActivityPub `Accept header is used.
- Support for incoming `Move` activities and ensure that followed persons are updated accordingly.
- Labels to add context to visibility settings in the block editor.
- WP CLI command to reschedule Outbox-Activities.

### Changed
- Outbox now precesses the first batch of followers right away to avoid delays in processing new Activities.
- Post bulk edits no longer create Outbox items, unless author or post status change.
- Properly process `Update` activities on profiles and ensure all properties of a followed person are updated accordingly.
- Outbox processing accounts for shared inboxes again.
- Improved check for `?activitypub` query-var.
- Rewrite rules: be more specific in author rewrite rules to avoid conflicts on sites that use the "@author" pattern in their permalinks.
- Deprecate the `activitypub_post_locale` filter in favor of the `activitypub_locale` filter.

### Fixed
- The Outbox purging routine no longer is limited to deleting 5 items at a time.
- Ellipses now display correctly in notification emails for Likes and Reposts.
- Send Update-Activity when "Actor-Mode" is changed.
- Added delay to `Announce` Activity from the Blog-Actor, to not have race conditions.
- `Actor` validation in several REST API endpoints.
- Bring back the `activitypub_post_locale` filter to allow overriding the post's locale.

## [5.2.0] - 2025-02-13
### Added
- Batch Outbox-Processing.
- Outbox processed events get logged in Stream and show any errors returned from inboxes.
- Outbox items older than 6 months will be purged to avoid performance issues.
- REST API endpoints for likes and shares.

### Changed
- Increased probability of Outbox items being processed with the correct author.
- Enabled querying of Outbox posts through the REST API to improve troubleshooting and debugging.
- Updated terminology to be client-neutral in the Federated Reply block.

### Fixed
- Fixed an issue where the outbox could not send object types other than `Base_Object` (introduced in 5.0.0).
- Enforce 200 status header for valid ActivityPub requests.
- `object_id_to_comment` returns a commment now, even if there are more than one matching comment in the DB.
- Integration of content-visibility setup in the block editor.
- Update CLI commands to the new scheduler refactorings.
- Do not add an audience to the Actor-Profiles.
- `Activity::set_object` falsely overwrites the Activity-ID with a default.

## [5.1.0] - 2025-02-06
### Added
- Cleanup of option values when the plugin is uninstalled.
- Third-party plugins can filter settings tabs to add their own settings pages for ActivityPub.
- Show ActivityPub preview in row actions when Block Editor is enabled but not used for the post type.

### Changed
- Manually granting `activitypub` cap no longer requires the receiving user to have `publish_post`.
- Allow omitting replies in ActivityPub representations instead of setting them as empty.
- Allow Base Transformer to handle WP_Term objects for transformation.
- Improved Query extensibility for third party plugins.

### Fixed
- Negotiation of ActivityPub requests for custom post types when queried by the ActivityPub ID.
- Avoid PHP warnings when using Debug mode and when the `actor` is not set.
- No longer creates Outbox items when importing content/users.
- Fix NodeInfo 2.0 URL to be HTTP instead of HTTPS.

## [5.0.0] - 2025-02-03
### Changed
- Improved content negotiation and AUTHORIZED_FETCH support for third-party plugins.
- Moved password check to `is_post_disabled` function.

### Fixed
- Handle deletes from remote servers that leave behind an accessible Tombstone object.
- No longer parses tags for post types that don't support Activitypub.
- rel attribute will now contain no more than one "me" value.

## [4.7.3] - 2025-01-21
### Fixed
- Flush rewrite rules after NodeInfo update.

## [4.7.2] - 2025-01-17
### Fixed
- More robust handling of `_activityPubOptions` in scripts, using a `useOptions()` helper.
- Flush post caches after Followers migration.

### Added
- Support for WPML post locale

### Changed
- Rewrite the current dispatcher system, to use the Outbox instead of the Scheduler.

### Removed
- Built-in support for nodeinfo2. Use the [NodeInfo plugin](https://wordpress.org/plugins/nodeinfo/) instead.

## [4.7.1] - 2025-01-14
### Fixed
- Missing migration

## [4.7.0] - 2025-01-13
### Added
- Comment counts get updated when the plugin is activated/deactivated/deleted
- Added a filter to make custom comment types manageable in WP.com Calypso

### Changed
- Hide ActivityPub post meta keys from the custom Fields UI
- Bumped minimum required PHP version to 7.2
- Print `_activityPubOptions` in the `wp_footer` action on the frontend.

### Fixed
- Undefined array key warnings in various places
- @-mentions in federated comments being displayed with a line break
- Fetching replies from the same instance for Enable Mastodon Apps
- Image captions not being included in the ActivityPub representation when the image is attached to the post

## [4.6.0] - 2024-12-20
### Added
- Add a filter to allow modifying the ActivityPub preview template
- `@mentions` in the JSON representation of the reply
- Add settings to enable/disable e-mail notifications for new followers and direct messages

### Changed
- Direct Messages: Test for the user being in the to field
- Direct Messages: Improve HTML to e-mail text conversion
- Better support for FSE color schemes

### Fixed
- Reactions: Provide a fallback for empty avatar URLs

## [4.5.1] - 2024-12-18
### Changed
- Reactions block: Remove the `wp-block-editor` dependency for frontend views

### Fixed
- Direct Messages: Don't send notification for received public activities

## [4.5.0] - 2024-12-17
### Added
- Reactions block to display likes and reposts
- `icon` support for `Audio` and `Video` attachments
- Send "new follower" emails
- Send "direct message" emails
- Account for custom comment types when calculating comment counts
- Plugin upgrade routine that automatically updates comment counts

### Changed
- Likes and Reposts enabled by default
- Email templates for Likes and Reposts
- Improve Interactions moderation
- Compatibility with Akismet
- Comment type mapping for `Like` and `Announce`
- Signature verification for API endpoints
- Changed priority of Attachments, to favor `Image` over `Audio` and `Video`

### Fixed
- Empty `url` attributes in the Reply block no longer cause PHP warnings

## [4.4.0] - 2024-12-09
### Added
- Setting to enable/disable Authorized-Fetch

### Changed
- Added screen reader text to the "Follow Me" block for improved accessibility
- Added `media_type` support to Activity-Object-Transformers
- Clarified settings page text around which users get Activitypub profiles
- Add a filter to the REST API moderators list
- Refactored settings to use the WordPress Settings API

### Fixed
- Prevent hex color codes in HTML attributes from being added as post tags
- Fixed a typo in the custom post content settings
- Prevent draft posts from being federated when bulk deleted

## [4.3.0] - 2024-12-02
### Added
- Fix editor error when switching to edit a synced Pattern
- A `pre_activitypub_get_upload_baseurl` filter
- Fediverse Preview on post-overview page
- GitHub action to enforce Changelog updates
- New contributors

### Changed
- Basic enclosure validation
- More User -> Actor renaming
- Outsource Constants to a separate file
- Better handling of `readme.txt` and `README.md`

### Fixed
- Fediverse preview showing `preferredUsername` instead of `name`
- A potential fatal error in Enable Mastodon Apps
- Show Followers name instead of avatar on mobile view
- Fixed a potential fatal error in Enable Mastodon Apps
- Broken escaping of Usernames in Actor-JSON
- Fixed missing attachement-type for enclosures
- Prevention against self pings

## [4.2.1] - 2024-11-20
### Added
- Mastodon Apps status provider

### Changed
- Image-Handling
- Have better checks if audience should be set or not

### Fixed
- Don't overwrite an existing `wp-tests-config.php`
- PHPCS for phpunit files

## [4.2.0] - 2024-11-15
### Added
- Unit tests for the `Activitypub\Transformer\Post` class

### Changed
- Reuse constants once they're defined
- "FEP-b2b8: Long-form Text" support
- Admin notice for plain permalink settings is more user-friendly and actionable
- Post-Formats support

### Fixed
- Do not display ActivityPub's user sub-menus to users who do not have the capabilities of writing posts
- Proper margins for notices and font size for page title in settings screen
- Ensure that `?author=0` resolves to blog user

### Removed
- Remove `meta` CLI command
- Remove unneeded translation functions from CLI commands

## [4.1.1] - 2024-11-10
### Fixed
- Only revert to URL if there is one
- Migration

## [4.1.0] - 2024-11-08
### Added
- Add custom Preview for "Fediverse"
- Support `comment_previously_approved` setting

### Fixed
- Hide sticky posts that are not public

### Changed
- `activity_handle_undo` action
- Add title to content if post is a `Note`
- Fallback to blog-user if user is disabled

## [4.0.2] - 2024-10-30
### Fixed
- Do not federate "Local" posts

### Changed
- Help-text for Content-Warning box

## [4.0.1] - 2024-10-26
### Fixed
- Missing URL-Param handling in REST API
- Seriously Simple Podcasting integration
- Multiple small fixes

### Changed
- Provide contextual fallback for dynamic blocks

## [4.0.0] - 2024-10-23
### Added
- Fire an action before a follower is removed
- Make Intent-URL filterable
- `title` attribute to link headers for better readability
- Post "visibility" feature
- Attribution-Domains support

### Changed
- Inbox validation
- WordPress-Post-Type - Detection
- Only validate POST params and do not fall back to GET params
- ID handling for a better compatibility with caching plugins

### Fixed
- The "Shared Inbox" endpoint
- Ensure that sticky_posts is an array
- URLs and Hashtags in profiles were not converted
- A lot of small improvements and fixes

## [3.3.3] - 2024-10-09
### Fixed
- Sanitization callback

### Changed
- A lot of PHPCS cleanups
- Prepare multi-lang support

## [3.3.2] - 2024-10-02
### Fixed
- Keep priority of Icons
- Fatal error if remote-object is `WP_Error`

### Changed
- Adopt WordPress PHP Coding Standards

## [3.3.1] - 2024-09-26
### Fixed
- PHP Warnings
- PHPCS issues

## [3.3.0] - 2024-09-25
### Added
- Content warning support
- Replies collection
- Enable Mastodon Apps: support profile editing, blog user
- Follow Me/Followers: add inherit mode for dynamic templating

### Fixed
- Cropping Header Images for users without the 'customize' capability

### Changed
- OpenSSL handling
- Added missing @ in Follow-Me block

## [3.2.5] - 2024-09-17
### Fixed
- Enable Mastodon Apps check
- Fediverse replies were not threaded properly

## [3.2.4] - 2024-09-16
### Changed
- Inbox validation

## [3.2.3] - 2024-09-15
### Fixed
- NodeInfo endpoint
- (Temporarily) Remove HTML from `summary`, because it seems that Mastodon has issues with it

### Changed
- Accessibility for Reply-Context
- Use `Article` Object-Type as default

## [3.2.2] - 2024-09-09
### Fixed
- Fixed: Extra-Fields check

## [3.2.1] - 2024-09-09
### Fixed
- Fixed: Use `Excerpt` for Podcast Episodes

## [3.2.0] - 2024-09-09
### Added
- Support for Seriously Simple Podcasting
- Blog extra fields
- Support "read more" for Activity-Summary
- `Like` and `Announce` (Boost) handler
- Simple Remote-Reply endpoint
- "Stream" Plugin support
- New Fediverse symbol

### Changed
- Replace hashtags, URLs, and mentions in summary with links
- Hide Bookmarklet if site does not support Blocks

### Fixed
- Link detection for extra fields when spaces after the link and fix when two links in the content
- `Undo` for `Likes` and `Announces`
- Show Avatars on `Likes` and `Announces`
- Remove proprietary WebFinger resource
- Wrong followers URL in "to" attribute of posts

## [3.1.0] - 2024-08-07
### Added
- `menu_order` to `ap_extrafield` so that user can decide in which order they will be displayed
- Line breaks to user biography
- Blueprint

### Changed
- Simplified WebFinger code

### Fixed
- Changed missing `activitypub_user_description` to `activitypub_description`
- Undefined `get_sample_permalink`
- Only send Update for previously-published posts

## [3.0.0] - 2024-07-29
### Added
- "Reply Context" support, you can now reply to posts on the Fediverse through a WordPress post
- Bookmarklet to automatically pre-fill the "Reply Context" block
- "Header Image" support and ability to edit other profile information for Authors and the Blog-User
- ActivityPub link HTML/HTTP-Header support
- Tag support for Actors (only auto-generated for now)

### Changed
- Add setting to enable/disable the `fediverse:creator` OGP tag.

### Removed
- Deprecated `class-post.php` model

## [2.6.1] - 2024-07-18
### Fixed
- Extra Fields will generate wrong entries

## [2.6.0] - 2024-07-17
### Added
- Support for FEP-fb2a
- CRUD support for Extra Fields

### Changed
- Remote-Follow UI and UX
- Open Graph `fediverse:creator` implementation

### Fixed
- Compatibility issues with fed.brid.gy
- Remote-Reply endpoint
- WebFinger Error Codes (thanks to the FediTest project)
- Fatal Error when `wp_schedule_single_event` third argument is being passed as a string

## [2.5.0] - 2024-07-01
### Added
- WebFinger cors header
- WebFinger Content-Type
- The Fediverse creator of a post to OpenGraph

### Changed
- Try to lookup local users first for Enable Mastodon Apps
- Send also Announces for deletes
- Load time by adding `count_total=false` to `WP_User_Query`

### Fixed
- Several WebFinger issues
- Redirect issue for Application user
- Accessibilty issues with missing screen-reader-text on User overview page

## [2.4.0] - 2024-06-05
### Added
- A core/embed block filter to transform iframes to links
- Basic support of incoming `Announce`s
- Improve attachment handling
- Notifications: Introduce general class and use it for new follows
- Always fall back to `get_by_username` if one of the above fail
- Notification support for Jetpack
- EMA: Support for fetching external statuses without replies
- EMA: Remote context
- EMA: Allow searching for URLs
- EMA: Ensuring numeric ids is now done in EMA directly
- Podcast support
- Follower count to "At a Glance" dashboard widget

### Changed
- Use `Note` as default Object-Type, instead of `Article`
- Improve `AUTHORIZED_FETCH`
- Only send Mentions to comments in the direct hierarchy
- Improve transformer
- Improve Lemmy compatibility
- Updated JS dependencies

### Fixed
- EMA: Add missing static keyword and try to lookup if the id is 0
- Blog-wide account when WordPress is in subdirectory
- Funkwhale URLs
- Prevent infinite loops in `get_comment_ancestors`
- Better Content-Negotiation handling

## [2.3.1] - 2024-04-29
### Added
- Enable Mastodon Apps: Add remote outbox fetching
- Help texts

### Fixed
- Compatibility issues with Discourse
- Do not announce replies
- Also delete interactions with deleted person
- Check Author-URL only if user is enabled for ActivityPub
- Generate comment IDs for federation from home_url

### Removed
- Beta label from the #Hashtag settings

## [2.3.0] - 2024-04-16
### Added
- Mark links as "unhandled-link" and "status-link", for a better UX in the Mastodon App
- Enable-Mastodon-Apps: Provide followers
- Enable-Mastodon-Apps: Extend account with ActivityPub data
- Enable-Mastodon-Apps: Search in followers
- Add `alt` support for images (for Block and Classic-Editor)

### Fixed
- Counter for system users outbox
- Don't set a default actor type in the actor class
- Outbox collection for blog and application user

### Changed
- A better default content handling based on the Object Type
- Improve User management
- Federated replies: Improved UX for "your reply will federate"
- Comment reply federation: support `is_single_user` sites
- Mask WordPress version number
- Improve remote reply handling
- Remote Reply: limit enqueue to when needed
- Abstract shared Dialog code

## [2.2.0] - 2024-02-27
### Added
- Remote-Reply lightbox
- Support `application/ld+json` mime-type with AP profile in WebFinger

### Fixed
- Prevent scheduler overload

## [2.1.1] - 2024-02-13
### Added
- Add `@` prefix to Follow-Block
- Apply `comment_text` filter to Activity

## [2.1.0] - 2024-02-12
### Added
- Various endpoints for the "Enable Mastodon Apps" plugin
- Event Objects
- Send notification to all Repliers if a new Comment is added
- Vary-Header support behind feature flag

### Fixed
- Some Federated Comment improvements
- Remove old/abandoned Crons

## [2.0.1] - 2024-01-12
### Fixed
- Comment `Update` Federation
- WebFinger check
- Classic editor image finding for large images

### Changed
- Re-Added Post Model Class because of some weird caching issues

## [2.0.0] - 2024-01-09
### Added
- Bidirectional Comment Federation
- URL support for WebFinger
- Make Post-Template filterable
- CSS class for ActivityPub comments to allow custom designs
- FEP-2677: Identifying the Application Actor
- FEP-2c59: Discovery of a Webfinger address from an ActivityPub actor
- Profile Update Activities

### Changed
- WebFinger endpoints

### Removed
- Deprecated Classes

### Fixed
- Normalize attributes that can have mixed value types

## [1.3.0] - 2023-12-05
### Added
- Threaded-Comments support

### Changed
- alt text for avatars in Follow Me/Followers blocks
- `Delete`, `Update` and `Follow` Activities
- better/more effective handling of `Delete` Activities
- allow `<p />` and `<br />` for Comments

### Fixed
- removed default limit of WP_Query to send updates to all Inboxes and not only to the first 10

## [1.2.0] - 2023-11-18
### Added
- Search and order followerer lists
- Have a filter to defer signature verification

### Changed
- "Follow Me" styles for dark themes
- Allow `p` and `br` tags only for AP comments

### Fixed
- Deduplicate attachments earlier to prevent incorrect max_media

## [1.1.0] - 2023-11-08
### Changed
- audio and video attachments are now supported!
- better error messages if remote profile is not accessible
- PHP 8.1 compatibility
- more reliable [ap_author], props @uk3
- NodeInfo statistics

### Fixed
- don't try to parse mentions or hashtags for very large (>1MB) posts to prevent timeouts
- better handling of ISO-639-1 locale codes

## [1.0.10] - 2023-10-24
### Changed
- better error messages if remote profile is not accessible

## [1.0.9] - 2023-10-24
### Fixed
- broken following endpoint

## [1.0.8] - 2023-10-24
### Fixed
- blocking of HEAD requests
- PHP fatal error
- several typos
- error codes

### Changed
- loading of shortcodes
- caching of followers
- Application-User is no longer "indexable"
- more consistent usage of the `application/activity+json` Content-Type

### Removed
- featured tags endpoint

## [1.0.7] - 2023-10-13
### Added
- filter to hook into "is blog public" check

### Fixed
- broken function call

## [1.0.6] - 2023-10-12
### Fixed
- more restrictive request verification

## [1.0.5] - 2023-10-11
### Fixed
- compatibility with WebFinger and NodeInfo plugin

## [1.0.4] - 2023-10-10
### Fixed
- Constants were not loaded early enough, resulting in a race condition
- Featured image was ignored when using the block editor

## [1.0.3] - 2023-10-10
### Changed
- refactoring of the Plugin init process
- better frontend UX and improved theme compat for blocks
- add a `ACTIVITYPUB_DISABLE_REWRITES` constant
- add pre-fetch hook to allow plugins to hang filters on

### Fixed
- compatibility with older WordPress/PHP versions

## [1.0.2] - 2023-10-02
### Changed
- improved hashtag visibility in default template
- reduced number of followers to be checked/updated via Cron, when System Cron is not set up
- check if username of Blog-User collides with an Authors name
- improved Group meta informations

### Fixed
- detection of single user mode
- remote delete
- styles in Follow-Me block
- various encoding and formatting issues
- (health) check Author URLs only if Authors are enabled

## [1.0.1] - 2023-09-22
### Changed
- improve image attachment detection using the block editor
- better error code handling for API responses
- use a tag stack instead of regex for protecting tags for Hashtags and @-Mentions
- better signature support for subpath-installations
- allow deactivating blocks registered by the plugin
- avoid Fatal Errors when using ClassicPress
- improve the Group-Actor to play nicely with existing implementations

### Fixed
- truncate long blog titles and handles for the "Follow me" block
- ensure that only a valid user can be selected for the "Follow me" block
- fix a typo in a hook name
- a problem with signatures when running WordPress in a sub-path

## [1.0.0] - 2023-09-13
### Added
- blog-wide Account (catchall, like `example.com@example.com`)
- a Follow Me block (help visitors to follow your Profile)
- Signature Verification: https://docs.joinmastodon.org/spec/security/
- a Followers Block (show off your Followers)
- Simple caching
- Collection endpoints for Featured Tags and Featured Posts
- Better handling of Hashtags in mobile apps

### Changed
- Complete rewrite of the Follower-System based on Custom Post Types
- Improved linter (PHPCS)
- Add a new conditional, `\Activitypub\is_activitypub_request()`, to allow third-party plugins to detect ActivityPub requests
- Add hooks to allow modifying images returned in ActivityPub requests
- Indicate that the plugin is compatible and has been tested with the latest version of WordPress, 6.3
- Avoid PHP notice on sites using PHP 8.2

### Fixed
- Load the plugin later in the WordPress code lifecycle to avoid errors in some requests
- Updating posts
- Hashtag now support CamelCase and UTF-8

## [0.17.0] - 2023-03-03
### Changed
- Allow more HTML elements in Activity-Objects

### Fixed
- Fix type-selector

## [0.16.5] - 2023-03-02
### Changed
- Return empty content/excerpt on password protected posts/pages

## [0.16.4] - 2023-02-20
### Changed
- Remove scripts later in the queue, to also handle scripts added by blocks
- Add published date to author profiles

## [0.16.3] - 2023-02-20
### Changed
- "cc", "to", ... fields can either be an array or a string

### Removed
- Remove "style" and "script" HTML elements from content

## [0.16.2] - 2023-02-02
### Fixed
- Fix fatal error in outbox

## [0.16.1] - 2023-02-02
### Fixed
- Fix "update and create, posts appear blank on Mastodon" issue

## [0.16.0] - 2023-02-01
### Added
- Add "Outgoing Mentions" ([#213](https://github.com/pfefferle/wordpress-activitypub/pull/213)) props [@akirk](https://github.com/akirk)
- Add configuration item for number of images to attach ([#248](https://github.com/pfefferle/wordpress-activitypub/pull/248)) props [@mexon](https://github.com/mexon)
- Use shortcodes instead of custom templates, to setup the Activity Post-Content ([#250](https://github.com/pfefferle/wordpress-activitypub/pull/250)) props [@toolstack](https://github.com/toolstack)

### Changed
- Change priorites, to maybe fix the hashtag issue

### Removed
- Remove custom REST Server, because the needed changes are now merged into Core.

### Fixed
- Fix hashtags ([#261](https://github.com/pfefferle/wordpress-activitypub/pull/261)) props [@akirk](https://github.com/akirk)

## [0.15.0] - 2023-01-12
### Changed
- Enable ActivityPub only for users that can `publish_posts`
- Persist only public Activities

### Fixed
- Fix remote-delete

## [0.14.3] - 2022-12-15
### Changed
- Better error handling. props [@akirk](https://github.com/akirk)

## [0.14.2] - 2022-12-11
### Fixed
- Fix Critical error when using Friends Plugin and adding new URL to follow. props [@akirk](https://github.com/akirk)

## [0.14.1] - 2022-12-10
### Fixed
- Fix "WebFinger not compatible with PHP < 8.0". props [@mexon](https://github.com/mexon)

## [0.14.0] - 2022-12-09
### Changed
- Friends support: https://wordpress.org/plugins/friends/ props [@akirk](https://github.com/akirk)
- Massive guidance improvements. props [mediaformat](https://github.com/mediaformat) & [@akirk](https://github.com/akirk)
- Add Custom Post Type support to outbox API. props [blueset](https://github.com/blueset)
- Better hash-tag support. props [bocops](https://github.com/bocops)

### Fixed
- Fix user-count (NodeInfo). props [mediaformat](https://github.com/mediaformat)

## [0.13.4] - 2022-07-08
### Fixed
- fix webfinger for email identifiers

## [0.13.3] - 2022-01-26
### Fixed
- Create and Note should not have the same ActivityPub ID

## [0.13.2] - 2021-11-25
### Fixed
- fix Follow issue AGAIN

## [0.13.1] - 2021-07-26
### Fixed
- fix Inbox issue

## [0.13.0] - 2021-07-23
### Added
- add Autor URL and WebFinger health checks

### Fixed
- fix NodeInfo endpoint

## [0.12.0] - 2020-12-21
### Changed
- use "pre_option_require_name_email" filter instead of "check_comment_flood". props [@akirk](https://github.com/akirk)
- save only comments/replies
- check for an explicit "undo -> follow" action. see https://wordpress.org/support/topic/qs-after-latest/

## [0.11.2] - 2020-12-17
### Fixed
- fix inconsistent `%tags%` placeholder

## [0.11.1] - 2020-12-17
### Fixed
- fix follow/unfollow actions

## [0.11.0] - 2020-12-17
### Added
- add support for customizable post-content
- first try of a delete activity

### Changed
- do not require email for AP entries. props [@akirk](https://github.com/akirk)

### Fixed
- fix [timezones](https://github.com/pfefferle/wordpress-activitypub/issues/63) bug. props [@mediaformat](https://github.com/mediaformat)
- fix [digest header](https://github.com/pfefferle/wordpress-activitypub/issues/104) bug. props [@mediaformat](https://github.com/mediaformat)

## [0.10.1] - 2020-05-03
### Fixed
- fix inbox activities, like follow
- fix debug

## [0.10.0] - 2020-03-15
### Added
- add image alt text to the ActivityStreams attachment property in a format that Mastodon can read. props [@BenLubar](https://github.com/BenLubar)
- use the "summary" property for a title as Mastodon does. props [@BenLubar](https://github.com/BenLubar)
- add new post type: "title and link only". props [@bgcarlisle](https://github.com/bgcarlisle)

### Changed
- support authorized fetch to avoid having comments from "Anonymous". props [@BenLubar](https://github.com/BenLubar)

## [0.9.1] - 2019-11-27
### Removed
- disable shared inbox
- disable delete activity

## [0.9.0] - 2019-11-24
### Changed
- some code refactorings

### Fixed
- fix #73

## [0.8.3] - 2019-09-30
### Fixed
- fixed accept header bug

## [0.8.2] - 2019-09-29
### Added
- all required accept header
- debugging mechanism
- setting to enable AP for different (public) Post-Types

### Changed
- explicit use of global functions
- better/simpler accept-header handling

## [0.8.1] - 2019-08-21
### Fixed
- fixed PHP warnings

## [0.8.0] - 2019-08-21
### Changed
- Moved followers list to user-menu

## [0.7.4] - 2019-08-20
### Added
- added admin_email to metadata, to be able to "Manage your instance" on https://fediverse.network/manage/

## [0.7.3] - 2019-08-20
### Changed
- refactorings
- fixed PHP warnings
- better hashtag regex

## [0.7.2] - 2019-04-13
### Fixed
- fixed JSON representation of posts https://merveilles.town/@xuv/101907542498716956

## [0.7.1] - 2019-03-14
### Fixed
- fixed inbox problems with pleroma

## [0.7.0] - 2019-03-12
### Added
- added "following" endpoint

### Changed
- simplified "followers" endpoint

### Fixed
- finally fixed pleroma compatibility
- fixed default value problem

## [0.6.0] - 2019-03-09
### Added
- add tags as hashtags to the end of each activity

### Changed
- followers-list improvements

### Fixed
- fixed pleroma following issue

## [0.5.1] - 2019-03-02
### Fixed
- fixed name-collision that caused an infinite loop

## [0.5.0] - 2019-02-28
### Changed
- complete refactoring

### Fixed
- fixed bug #30: Password-protected posts are federated
- only send Activites when ActivityPub is enabled for this post-type

## [0.4.4] - 2019-02-20
### Changed
- show avatars

## [0.4.3] - 2019-02-20
### Fixed
- finally fixed backlink in excerpt/summary posts

## [0.4.2] - 2019-02-20
### Fixed
- fixed backlink in excerpt/summary posts (thanks @depone)

## [0.4.1] - 2019-02-19
### Fixed
- finally fixed contact list

## [0.4.0] - 2019-02-17
### Added
- added settings to enable/disable hashtag support

### Fixed
- fixed follower list

### Changed
- send activities only for new posts, otherwise send updates

## [0.3.2] - 2019-02-04
### Added
- added "followers" endpoint

### Changed
- change activity content from blog 'excerpt' to blog 'content'

## [0.3.1] - 2019-02-03
### Changed
- better json encoding

## [0.3.0] - 2019-02-02
### Adeed
- basic hashtag support
- added support for actor objects

### Removed
- temporarily deactivated likes and boosts

### Fixed
- fixed encoding issue

## [0.2.1] - 2019-01-16
### Changed
- customizable backlink (permalink or shorturl)
- show profile-identifiers also on profile settings

## [0.2.0] - 2019-01-04
### Added
- option to switch between content and excerpt

### Removed
- html and duplicate new-lines

## [0.1.1] - 2018-12-30
### Added
- settings for the activity-summary and for the activity-object-type

### Fixed
- "excerpt" in AS JSON

## [0.1.0] - 2018-12-20
### Added
- basic WebFinger support
- basic NodeInfo support
- fully functional "follow" activity
- send new posts to your followers
- receive comments from your followers

## [0.0.2] - 2018-11-06
### Added
- functional inbox

### Changed
- refactoring
- nicer profile views

## [0.0.1] - 2018-09-24
### Added
- initial

[9.2.1]: https://github.com/Automattic/wordpress-activitypub/compare/9.2.0...9.2.1
[9.2.0]: https://github.com/Automattic/wordpress-activitypub/compare/9.1.0...9.2.0
[9.1.0]: https://github.com/Automattic/wordpress-activitypub/compare/9.0.2...9.1.0
[9.0.2]: https://github.com/Automattic/wordpress-activitypub/compare/9.0.1...9.0.2
[9.0.1]: https://github.com/Automattic/wordpress-activitypub/compare/9.0.0...9.0.1
[9.0.0]: https://github.com/Automattic/wordpress-activitypub/compare/8.3.0...9.0.0
[8.3.0]: https://github.com/Automattic/wordpress-activitypub/compare/8.2.1...8.3.0
[8.2.1]: https://github.com/Automattic/wordpress-activitypub/compare/8.2.0...8.2.1
[8.2.0]: https://github.com/Automattic/wordpress-activitypub/compare/8.1.1...8.2.0
[8.1.1]: https://github.com/Automattic/wordpress-activitypub/compare/8.1.0...8.1.1
[8.1.0]: https://github.com/Automattic/wordpress-activitypub/compare/8.0.2...8.1.0
[8.0.2]: https://github.com/Automattic/wordpress-activitypub/compare/8.0.1...8.0.2
[8.0.1]: https://github.com/Automattic/wordpress-activitypub/compare/8.0.0...8.0.1
[8.0.0]: https://github.com/Automattic/wordpress-activitypub/compare/7.9.1...8.0.0
[7.9.1]: https://github.com/Automattic/wordpress-activitypub/compare/7.9.0...7.9.1
[7.9.0]: https://github.com/Automattic/wordpress-activitypub/compare/7.8.5...7.9.0
[7.8.5]: https://github.com/Automattic/wordpress-activitypub/compare/7.8.4...7.8.5
[7.8.4]: https://github.com/Automattic/wordpress-activitypub/compare/7.8.3...7.8.4
[7.8.3]: https://github.com/Automattic/wordpress-activitypub/compare/7.8.2...7.8.3
[7.8.2]: https://github.com/Automattic/wordpress-activitypub/compare/7.8.1...7.8.2
[7.8.1]: https://github.com/Automattic/wordpress-activitypub/compare/7.8.0...7.8.1
[7.8.0]: https://github.com/Automattic/wordpress-activitypub/compare/7.7.1...7.8.0
[7.7.1]: https://github.com/Automattic/wordpress-activitypub/compare/7.7.0...7.7.1
[7.7.0]: https://github.com/Automattic/wordpress-activitypub/compare/7.6.1...7.7.0
[7.6.1]: https://github.com/Automattic/wordpress-activitypub/compare/7.6.0...7.6.1
[7.6.0]: https://github.com/Automattic/wordpress-activitypub/compare/7.5.0...7.6.0
[7.5.0]: https://github.com/Automattic/wordpress-activitypub/compare/7.4.0...7.5.0
[7.4.0]: https://github.com/Automattic/wordpress-activitypub/compare/7.3.0...7.4.0
[7.3.0]: https://github.com/Automattic/wordpress-activitypub/compare/7.2.0...7.3.0
[7.2.0]: https://github.com/Automattic/wordpress-activitypub/compare/7.1.0...7.2.0
[7.1.0]: https://github.com/Automattic/wordpress-activitypub/compare/7.0.1...7.1.0
[7.0.1]: https://github.com/Automattic/wordpress-activitypub/compare/7.0.0...7.0.1
[7.0.0]: https://github.com/Automattic/wordpress-activitypub/compare/6.0.2...7.0.0
[6.0.2]: https://github.com/Automattic/wordpress-activitypub/compare/6.0.1...6.0.2
[6.0.1]: https://github.com/Automattic/wordpress-activitypub/compare/6.0.0...6.0.1
[6.0.0]: https://github.com/Automattic/wordpress-activitypub/compare/5.9.2...6.0.0
[5.9.2]: https://github.com/Automattic/wordpress-activitypub/compare/5.9.1...5.9.2
[5.9.1]: https://github.com/Automattic/wordpress-activitypub/compare/5.9.0...5.9.1
[5.9.0]: https://github.com/Automattic/wordpress-activitypub/compare/5.8.0...5.9.0
[5.8.0]: https://github.com/Automattic/wordpress-activitypub/compare/5.7.0...5.8.0
[5.7.0]: https://github.com/Automattic/wordpress-activitypub/compare/5.6.1...5.7.0
[5.6.1]: https://github.com/Automattic/wordpress-activitypub/compare/5.6.0...5.6.1
[5.6.0]: https://github.com/Automattic/wordpress-activitypub/compare/5.5.0...5.6.0
[5.5.0]: https://github.com/Automattic/wordpress-activitypub/compare/5.4.1...5.5.0
[5.4.1]: https://github.com/Automattic/wordpress-activitypub/compare/5.4.0...5.4.1
[5.4.0]: https://github.com/Automattic/wordpress-activitypub/compare/5.3.2...5.4.0
[5.3.2]: https://github.com/Automattic/wordpress-activitypub/compare/5.3.1...5.3.2
[5.3.1]: https://github.com/Automattic/wordpress-activitypub/compare/5.3.0...5.3.1
[5.3.0]: https://github.com/Automattic/wordpress-activitypub/compare/5.2.0...5.3.0
[5.2.0]: https://github.com/Automattic/wordpress-activitypub/compare/5.1.0...5.2.0
[5.1.0]: https://github.com/Automattic/wordpress-activitypub/compare/5.0.0...5.1.0
[5.0.0]: https://github.com/Automattic/wordpress-activitypub/compare/4.7.3...5.0.0
[4.7.3]: https://github.com/Automattic/wordpress-activitypub/compare/4.7.2...4.7.3
[4.7.2]: https://github.com/Automattic/wordpress-activitypub/compare/4.7.1...4.7.2
[4.7.1]: https://github.com/Automattic/wordpress-activitypub/compare/4.7.0...4.7.1
[4.7.0]: https://github.com/Automattic/wordpress-activitypub/compare/4.6.0...4.7.0
[4.6.0]: https://github.com/Automattic/wordpress-activitypub/compare/4.5.1...4.6.0
[4.5.1]: https://github.com/Automattic/wordpress-activitypub/compare/4.5.0...4.5.1
[4.5.0]: https://github.com/Automattic/wordpress-activitypub/compare/4.4.0...4.5.0
[4.4.0]: https://github.com/Automattic/wordpress-activitypub/compare/4.3.0...4.4.0
[4.3.0]: https://github.com/Automattic/wordpress-activitypub/compare/4.2.1...4.3.0
[4.2.1]: https://github.com/Automattic/wordpress-activitypub/compare/4.2.0...4.2.1
[4.2.0]: https://github.com/Automattic/wordpress-activitypub/compare/4.1.1...4.2.0
[4.1.1]: https://github.com/Automattic/wordpress-activitypub/compare/4.1.0...4.1.1
[4.1.0]: https://github.com/Automattic/wordpress-activitypub/compare/4.0.2...4.1.0
[4.0.2]: https://github.com/Automattic/wordpress-activitypub/compare/4.0.1...4.0.2
[4.0.1]: https://github.com/Automattic/wordpress-activitypub/compare/4.0.0...4.0.1
[4.0.0]: https://github.com/Automattic/wordpress-activitypub/compare/3.3.3...4.0.0
[3.3.3]: https://github.com/Automattic/wordpress-activitypub/compare/3.3.2...3.3.3
[3.3.2]: https://github.com/Automattic/wordpress-activitypub/compare/3.3.1...3.3.2
[3.3.1]: https://github.com/Automattic/wordpress-activitypub/compare/3.3.0...3.3.1
[3.3.0]: https://github.com/Automattic/wordpress-activitypub/compare/3.2.5...3.3.0
[3.2.5]: https://github.com/Automattic/wordpress-activitypub/compare/3.2.4...3.2.5
[3.2.4]: https://github.com/Automattic/wordpress-activitypub/compare/3.2.3...3.2.4
[3.2.3]: https://github.com/Automattic/wordpress-activitypub/compare/3.2.2...3.2.3
[3.2.2]: https://github.com/Automattic/wordpress-activitypub/compare/3.2.1...3.2.2
[3.2.1]: https://github.com/Automattic/wordpress-activitypub/compare/3.2.0...3.2.1
[3.2.0]: https://github.com/Automattic/wordpress-activitypub/compare/3.1.0...3.2.0
[3.1.0]: https://github.com/Automattic/wordpress-activitypub/compare/3.0.0...3.1.0
[3.0.0]: https://github.com/Automattic/wordpress-activitypub/compare/2.6.1...3.0.0
[2.6.1]: https://github.com/Automattic/wordpress-activitypub/compare/2.6.0...2.6.1
[2.6.0]: https://github.com/Automattic/wordpress-activitypub/compare/2.5.0...2.6.0
[2.5.0]: https://github.com/Automattic/wordpress-activitypub/compare/2.4.0...2.5.0
[2.4.0]: https://github.com/Automattic/wordpress-activitypub/compare/2.3.1...2.4.0
[2.3.1]: https://github.com/Automattic/wordpress-activitypub/compare/2.3.0...2.3.1
[2.3.0]: https://github.com/Automattic/wordpress-activitypub/compare/2.2.0...2.3.0
[2.2.0]: https://github.com/Automattic/wordpress-activitypub/compare/2.1.1...2.2.0
[2.1.1]: https://github.com/Automattic/wordpress-activitypub/compare/2.1.0...2.1.1
[2.1.0]: https://github.com/Automattic/wordpress-activitypub/compare/2.0.1...2.1.0
[2.0.1]: https://github.com/Automattic/wordpress-activitypub/compare/2.0.0...2.0.1
[2.0.0]: https://github.com/Automattic/wordpress-activitypub/compare/1.3.0...2.0.0
[1.3.0]: https://github.com/Automattic/wordpress-activitypub/compare/1.2.0...1.3.0
[1.2.0]: https://github.com/Automattic/wordpress-activitypub/compare/1.1.0...1.2.0
[1.1.0]: https://github.com/Automattic/wordpress-activitypub/compare/1.0.10...1.1.0
[1.0.10]: https://github.com/Automattic/wordpress-activitypub/compare/1.0.9...1.0.10
[1.0.9]: https://github.com/Automattic/wordpress-activitypub/compare/1.0.8...1.0.9
[1.0.8]: https://github.com/Automattic/wordpress-activitypub/compare/1.0.7...1.0.8
[1.0.7]: https://github.com/Automattic/wordpress-activitypub/compare/1.0.6...1.0.7
[1.0.6]: https://github.com/Automattic/wordpress-activitypub/compare/1.0.5...1.0.6
[1.0.5]: https://github.com/Automattic/wordpress-activitypub/compare/1.0.4...1.0.5
[1.0.4]: https://github.com/Automattic/wordpress-activitypub/compare/1.0.3...1.0.4
[1.0.3]: https://github.com/Automattic/wordpress-activitypub/compare/1.0.2...1.0.3
[1.0.2]: https://github.com/Automattic/wordpress-activitypub/compare/1.0.1...1.0.2
[1.0.1]: https://github.com/Automattic/wordpress-activitypub/compare/1.0.0...1.0.1
[1.0.0]: https://github.com/Automattic/wordpress-activitypub/compare/0.17.0...1.0.0
[0.17.0]: https://github.com/Automattic/wordpress-activitypub/compare/0.16.5...0.17.0
[0.16.5]: https://github.com/Automattic/wordpress-activitypub/compare/0.16.4...0.16.5
[0.16.4]: https://github.com/Automattic/wordpress-activitypub/compare/0.16.3...0.16.4
[0.16.3]: https://github.com/Automattic/wordpress-activitypub/compare/0.16.2...0.16.3
[0.16.2]: https://github.com/Automattic/wordpress-activitypub/compare/0.16.1...0.16.2
[0.16.1]: https://github.com/Automattic/wordpress-activitypub/compare/0.16.0...0.16.1
[0.16.0]: https://github.com/Automattic/wordpress-activitypub/compare/0.15.0...0.16.0
[0.15.0]: https://github.com/Automattic/wordpress-activitypub/compare/0.14.3...0.15.0
[0.14.3]: https://github.com/Automattic/wordpress-activitypub/compare/0.14.2...0.14.3
[0.14.2]: https://github.com/Automattic/wordpress-activitypub/compare/0.14.1...0.14.2
[0.14.1]: https://github.com/Automattic/wordpress-activitypub/compare/0.14.0...0.14.1
[0.14.0]: https://github.com/Automattic/wordpress-activitypub/compare/0.13.4...0.14.0
[0.13.4]: https://github.com/Automattic/wordpress-activitypub/compare/0.13.3...0.13.4
[0.13.3]: https://github.com/Automattic/wordpress-activitypub/compare/0.13.2...0.13.3
[0.13.2]: https://github.com/Automattic/wordpress-activitypub/compare/0.13.1...0.13.2
[0.13.1]: https://github.com/Automattic/wordpress-activitypub/compare/0.13.0...0.13.1
[0.13.0]: https://github.com/Automattic/wordpress-activitypub/compare/0.12.0...0.13.0
[0.12.0]: https://github.com/Automattic/wordpress-activitypub/compare/0.11.2...0.12.0
[0.11.2]: https://github.com/Automattic/wordpress-activitypub/compare/0.11.1...0.11.2
[0.11.1]: https://github.com/Automattic/wordpress-activitypub/compare/0.11.0...0.11.1
[0.11.0]: https://github.com/Automattic/wordpress-activitypub/compare/0.10.1...0.11.0
[0.10.1]: https://github.com/Automattic/wordpress-activitypub/compare/0.10.0...0.10.1
[0.10.0]: https://github.com/Automattic/wordpress-activitypub/compare/0.9.1...0.10.0
[0.9.1]: https://github.com/Automattic/wordpress-activitypub/compare/0.9.0...0.9.1
[0.9.0]: https://github.com/Automattic/wordpress-activitypub/compare/0.8.3...0.9.0
[0.8.3]: https://github.com/Automattic/wordpress-activitypub/compare/0.8.2...0.8.3
[0.8.2]: https://github.com/Automattic/wordpress-activitypub/compare/0.8.1...0.8.2
[0.8.1]: https://github.com/Automattic/wordpress-activitypub/compare/0.8.0...0.8.1
[0.8.0]: https://github.com/Automattic/wordpress-activitypub/compare/0.7.4...0.8.0
[0.7.4]: https://github.com/Automattic/wordpress-activitypub/compare/0.7.3...0.7.4
[0.7.3]: https://github.com/Automattic/wordpress-activitypub/compare/0.7.2...0.7.3
[0.7.2]: https://github.com/Automattic/wordpress-activitypub/compare/0.7.1...0.7.2
[0.7.1]: https://github.com/Automattic/wordpress-activitypub/compare/0.7.0...0.7.1
[0.7.0]: https://github.com/Automattic/wordpress-activitypub/compare/0.6.0...0.7.0
[0.6.0]: https://github.com/Automattic/wordpress-activitypub/compare/0.5.1...0.6.0
[0.5.1]: https://github.com/Automattic/wordpress-activitypub/compare/0.5.0...0.5.1
[0.5.0]: https://github.com/Automattic/wordpress-activitypub/compare/0.4.4...0.5.0
[0.4.4]: https://github.com/Automattic/wordpress-activitypub/compare/0.4.3...0.4.4
[0.4.3]: https://github.com/Automattic/wordpress-activitypub/compare/0.4.2...0.4.3
[0.4.2]: https://github.com/Automattic/wordpress-activitypub/compare/0.4.1...0.4.2
[0.4.1]: https://github.com/Automattic/wordpress-activitypub/compare/0.4.0...0.4.1
[0.4.0]: https://github.com/Automattic/wordpress-activitypub/compare/0.3.2...0.4.0
[0.3.2]: https://github.com/Automattic/wordpress-activitypub/compare/0.3.1...0.3.2
[0.3.1]: https://github.com/Automattic/wordpress-activitypub/compare/0.3.0...0.3.1
[0.3.0]: https://github.com/Automattic/wordpress-activitypub/compare/0.2.1...0.3.0
[0.2.1]: https://github.com/Automattic/wordpress-activitypub/compare/0.2.0...0.2.1
[0.2.0]: https://github.com/Automattic/wordpress-activitypub/compare/0.1.1...0.2.0
[0.1.1]: https://github.com/Automattic/wordpress-activitypub/compare/0.1.0...0.1.1
[0.1.0]: https://github.com/Automattic/wordpress-activitypub/compare/0.0.2...0.1.0
[0.0.2]: https://github.com/Automattic/wordpress-activitypub/compare/0.0.1...0.0.2
[0.0.1]: https://github.com/Automattic/wordpress-activitypub/releases
