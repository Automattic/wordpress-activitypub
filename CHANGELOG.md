# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
