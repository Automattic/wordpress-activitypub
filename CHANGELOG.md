# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Untitled]

### Changed

* Improved content negotiation and AUTHORIZED_FETCH support for third-party plugins

## [4.7.3] - 2025-01-21

### Fixed

* Flush rewrite rules after NodeInfo update.

## [4.7.2] - 2025-01-17

### Fixed

* More robust handling of `_activityPubOptions` in scripts, using a `useOptions()` helper.
* Flush post caches after Followers migration.

### Added

* Support for WPML post locale

### Removed

* Built-in support for nodeinfo2. Use the [NodeInfo plugin](https://wordpress.org/plugins/nodeinfo/) instead.

## [4.7.1] - 2025-01-14

### Fixed

* Missing migration

## [4.7.0] - 2025-01-13

### Added

* Comment counts get updated when the plugin is activated/deactivated/deleted
* Added a filter to make custom comment types manageable in WP.com Calypso

### Changed

* Hide ActivityPub post meta keys from the custom Fields UI
* Bumped minimum required PHP version to 7.2

### Fixed

* Undefined array key warnings in various places
* @-mentions in federated comments being displayed with a line break
* Fetching replies from the same instance for Enable Mastodon Apps
* Image captions not being included in the ActivityPub representation when the image is attached to the post

### Changed
* Print `_activityPubOptions` in the `wp_footer` action on the frontend.

## [4.6.0] - 2024-12-20

### Added

* Add a filter to allow modifying the ActivityPub preview template
* `@mentions` in the JSON representation of the reply
* Add settings to enable/disable e-mail notifications for new followers and direct messages

### Changed

* Direct Messages: Test for the user being in the to field
* Direct Messages: Improve HTML to e-mail text conversion
* Better support for FSE color schemes

### Fixed

* Reactions: Provide a fallback for empty avatar URLs

## [4.5.1] - 2024-12-18

### Changed

* Reactions block: Remove the `wp-block-editor` dependency for frontend views

### Fixed

* Direct Messages: Don't send notification for received public activities

## [4.5.0] - 2024-12-17

### Added

* Reactions block to display likes and reposts
* `icon` support for `Audio` and `Video` attachments
* Send "new follower" emails
* Send "direct message" emails
* Account for custom comment types when calculating comment counts
* Plugin upgrade routine that automatically updates comment counts

### Changed

* Likes and Reposts enabled by default
* Email templates for Likes and Reposts
* Improve Interactions moderation
* Compatibility with Akismet
* Comment type mapping for `Like` and `Announce`
* Signature verification for API endpoints
* Changed priority of Attachments, to favor `Image` over `Audio` and `Video`

### Fixed

* Empty `url` attributes in the Reply block no longer cause PHP warnings

## [4.4.0] - 2024-12-09

### Added

* Setting to enable/disable Authorized-Fetch

### Changed

* Added screen reader text to the "Follow Me" block for improved accessibility
* Added `media_type` support to Activity-Object-Transformers
* Clarified settings page text around which users get Activitypub profiles
* Add a filter to the REST API moderators list

### Fixed

* Prevent hex color codes in HTML attributes from being added as post tags
* Fixed a typo in the custom post content settings
* Prevent draft posts from being federated when bulk deleted

## [4.3.0] - 2024-12-02

### Added

* Fix editor error when switching to edit a synced Pattern
* A `pre_activitypub_get_upload_baseurl` filter
* Fediverse Preview on post-overview page
* GitHub action to enforce Changelog updates
* New contributors

### Changed

* Basic enclosure validation
* More User -> Actor renaming
* Outsource Constants to a separate file
* Better handling of `readme.txt` and `README.md`

### Fixed

* Fediverse preview showing `preferredUsername` instead of `name`
* A potential fatal error in Enable Mastodon Apps
* Show Followers name instead of avatar on mobile view
* Fixed a potential fatal error in Enable Mastodon Apps
* Broken escaping of Usernames in Actor-JSON
* Fixed missing attachement-type for enclosures
* Prevention against self pings

## [4.2.1] - 2024-11-20

### Added

* Mastodon Apps status provider

### Changed

* Image-Handling
* Have better checks if audience should be set or not

### Fixed

* Don't overwrite an existing `wp-tests-config.php`
* PHPCS for phpunit files

## [4.2.0] - 2024-11-15

### Added

* Unit tests for the `ActivityPub\Transformer\Post` class

### Changed

* Reuse constants once they're defined
* "FEP-b2b8: Long-form Text" support
* Admin notice for plain permalink settings is more user-friendly and actionable
* Post-Formats support

### Fixed

* Do not display ActivityPub's user sub-menus to users who do not have the capabilities of writing posts
* Proper margins for notices and font size for page title in settings screen
* Ensure that `?author=0` resolves to blog user

### Removed

* Remove `meta` CLI command
* Remove unneeded translation functions from CLI commands

## [4.1.1] - 2024-11-10

### Fixed

* Only revert to URL if there is one
* Migration

## [4.1.0] - 2024-11-08

### Added

* Add custom Preview for "Fediverse"
* Support `comment_previously_approved` setting

### Fixed

* Hide sticky posts that are not public

### Changed

* `activity_handle_undo` action
* Add title to content if post is a `Note`
* Fallback to blog-user if user is disabled

## [4.0.2] - 2024-10-30

### Fixed

* Do not federate "Local" posts

### Changed

* Help-text for Content-Warning box

## [4.0.1] - 2024-10-26

### Fixed

* Missing URL-Param handling in REST API
* Seriously Simple Podcasting integration
* Multiple small fixes

### Changed

* Provide contextual fallback for dynamic blocks

## [4.0.0] - 2024-10-23

### Added

* Fire an action before a follower is removed
* Make Intent-URL filterable
* `title` attribute to link headers for better readability
* Post "visibility" feature
* Attribution-Domains support

### Changed

* Inbox validation
* WordPress-Post-Type - Detection
* Only validate POST params and do not fall back to GET params
* ID handling for a better compatibility with caching plugins

### Fixed

* The "Shared Inbox" endpoint
* Ensure that sticky_posts is an array
* URLs and Hashtags in profiles were not converted
* A lot of small improvements and fixes

## [3.3.3] - 2024-10-09

### Fixed

* Sanitization callback

### Changed

* A lot of PHPCS cleanups
* Prepare multi-lang support

## [3.3.2] - 2024-10-02

### Fixed

* Keep priority of Icons
* Fatal error if remote-object is `WP_Error`

### Changed

* Adopt WordPress PHP Coding Standards

## [3.3.1] - 2024-09-26

### Fixed

* PHP Warnings
* PHPCS issues

## [3.3.0] - 2024-09-25

### Added

* Content warning support
* Replies collection
* Enable Mastodon Apps: support profile editing, blog user
* Follow Me/Followers: add inherit mode for dynamic templating

### Fixed

* Cropping Header Images for users without the 'customize' capability

### Changed

* OpenSSL handling
* Added missing @ in Follow-Me block

## [3.2.5] - 2024-09-17

### Fixed

* Enable Mastodon Apps check
* Fediverse replies were not threaded properly

## [3.2.4] - 2024-09-16

### Changed

* Inbox validation

## [3.2.3] - 2024-09-15

### Fixed

* NodeInfo endpoint
* (Temporarily) Remove HTML from `summary`, because it seems that Mastodon has issues with it

### Changed

* Accessibility for Reply-Context
* Use `Article` Object-Type as default

## [3.2.2] - 2024-09-09

### Fixed

* Fixed: Extra-Fields check

## [3.2.1] - 2024-09-09

### Fixed

* Fixed: Use `Excerpt` for Podcast Episodes

## [3.2.0] - 2024-09-09

### Added

* Support for Seriously Simple Podcasting
* Blog extra fields
* Support "read more" for Activity-Summary
* `Like` and `Announce` (Boost) handler
* Simple Remote-Reply endpoint
* "Stream" Plugin support
* New Fediverse symbol

### Changed

* Replace hashtags, URLs, and mentions in summary with links
* Hide Bookmarklet if site does not support Blocks

### Fixed

* Link detection for extra fields when spaces after the link and fix when two links in the content
* `Undo` for `Likes` and `Announces`
* Show Avatars on `Likes` and `Announces`
* Remove proprietary WebFinger resource
* Wrong followers URL in "to" attribute of posts

## [3.1.0] - 2024-08-07

### Added

* `menu_order` to `ap_extrafield` so that user can decide in which order they will be displayed
* Line breaks to user biography
* Blueprint

### Changed

* Simplified WebFinger code

### Fixed

* Changed missing `activitypub_user_description` to `activitypub_description`
* Undefined `get_sample_permalink`
* Only send Update for previously-published posts

## [3.0.0] - 2024-07-29

### Added

* "Reply Context" support, you can now reply to posts on the Fediverse through a WordPress post
* Bookmarklet to automatically pre-fill the "Reply Context" block
* "Header Image" support and ability to edit other profile information for Authors and the Blog-User
* ActivityPub link HTML/HTTP-Header support
* Tag support for Actors (only auto-generated for now)

### Changed

* Add setting to enable/disable the `fediverse:creator` OGP tag.

### Removed

* Deprecated `class-post.php` model

## [2.6.1] - 2024-07-18

### Fixed

* Extra Fields will generate wrong entries

## [2.6.0] - 2024-07-17

### Added

* Support for FEP-fb2a
* CRUD support for Extra Fields

### Changed

* Remote-Follow UI and UX
* Open Graph `fediverse:creator` implementation

### Fixed

* Compatibility issues with fed.brid.gy
* Remote-Reply endpoint
* WebFinger Error Codes (thanks to the FediTest project)
* Fatal Error when `wp_schedule_single_event` third argument is being passed as a string

## [2.5.0] - 2024-07-01

### Added

* WebFinger cors header
* WebFinger Content-Type
* The Fediverse creator of a post to OpenGraph

### Changed

* Try to lookup local users first for Enable Mastodon Apps
* Send also Announces for deletes
* Load time by adding `count_total=false` to `WP_User_Query`

### Fixed

* Several WebFinger issues
* Redirect issue for Application user
* Accessibilty issues with missing screen-reader-text on User overview page


## [2.4.0] - 2024-06-05

### Added

* A core/embed block filter to transform iframes to links
* Basic support of incoming `Announce`s
* Improve attachment handling
* Notifications: Introduce general class and use it for new follows
* Always fall back to `get_by_username` if one of the above fail
* Notification support for Jetpack
* EMA: Support for fetching external statuses without replies
* EMA: Remote context
* EMA: Allow searching for URLs
* EMA: Ensuring numeric ids is now done in EMA directly
* Podcast support
* Follower count to "At a Glance" dashboard widget

### Changed

* Use `Note` as default Object-Type, instead of `Article`
* Improve `AUTHORIZED_FETCH`
* Only send Mentions to comments in the direct hierarchy
* Improve transformer
* Improve Lemmy compatibility
* Updated JS dependencies

### Fixed

* EMA: Add missing static keyword and try to lookup if the id is 0
* Blog-wide account when WordPress is in subdirectory
* Funkwhale URLs
* Prevent infinite loops in `get_comment_ancestors`
* Better Content-Negotiation handling

## [2.3.1] - 2024-04-29

### Added

* Enable Mastodon Apps: Add remote outbox fetching
* Help texts

### Fixed

* Compatibility issues with Discourse
* Do not announce replies
* Also delete interactions with deleted person
* Check Author-URL only if user is enabled for ActivityPub
* Generate comment IDs for federation from home_url

### Removed

* Beta label from the #Hashtag settings

## [2.3.0] - 2024-04-16

### Added

* Mark links as "unhandled-link" and "status-link", for a better UX in the Mastodon App
* Enable-Mastodon-Apps: Provide followers
* Enable-Mastodon-Apps: Extend account with ActivityPub data
* Enable-Mastodon-Apps: Search in followers
* Add `alt` support for images (for Block and Classic-Editor)

## Fixed

* Counter for system users outbox
* Don't set a default actor type in the actor class
* Outbox collection for blog and application user

## Changed

* A better default content handling based on the Object Type
* Improve User management
* Federated replies: Improved UX for "your reply will federate"
* Comment reply federation: support `is_single_user` sites
* Mask WordPress version number
* Improve remote reply handling
* Remote Reply: limit enqueue to when needed
* Abstract shared Dialog code

## [2.2.0] - 2024-02-27

### Added

* Remote-Reply lightbox
* Support `application/ld+json` mime-type with AP profile in WebFinger

## Fixed

* Prevent scheduler overload

## [2.1.1] - 2024-02-13

### Added

* Add `@` prefix to Follow-Block
* Apply `comment_text` filter to Activity

## [2.1.0] - 2024-02-12

### Added

* Various endpoints for the "Enable Mastodon Apps" plugin
* Event Objects
* Send notification to all Repliers if a new Comment is added
* Vary-Header support behind feature flag

### Fixed

* Some Federated Comment improvements
* Remove old/abandoned Crons

## [2.0.1] - 2024-01-12

### Fixed

* Comment `Update` Federation
* WebFinger check
* Classic editor image finding for large images

### Changed

* Re-Added Post Model Class because of some weird caching issues

## [2.0.0] - 2024-01-09

### Added

* Bidirectional Comment Federation
* URL support for WebFinger
* Make Post-Template filterable
* CSS class for ActivityPub comments to allow custom designs
* FEP-2677: Identifying the Application Actor
* FEP-2c59: Discovery of a Webfinger address from an ActivityPub actor
* Profile Update Activities

### Changed

* WebFinger endpoints

### Removed

* Deprecated Classes

### Fixed

* Normalize attributes that can have mixed value types

## [1.3.0] - 2023-12-05

### Added

* Threaded-Comments support

### Changed

* alt text for avatars in Follow Me/Followers blocks
* `Delete`, `Update` and `Follow` Activities
* better/more effective handling of `Delete` Activities
* allow `<p />` and `<br />` for Comments

### Fixed

* removed default limit of WP_Query to send updates to all Inboxes and not only to the first 10

## [1.2.0] - 2023-11-18

### Added

* Search and order followerer lists
* Have a filter to defer signature verification

### Changed

* "Follow Me" styles for dark themes
* Allow `p` and `br` tags only for AP comments

### Fixed

* Deduplicate attachments earlier to prevent incorrect max_media

## [1.1.0] - 2023-11-08

### Changed

* audio and video attachments are now supported!
* better error messages if remote profile is not accessible
* PHP 8.1 compatibility
* more reliable [ap_author], props @uk3
* NodeInfo statistics

### Fixed

* don't try to parse mentions or hashtags for very large (>1MB) posts to prevent timeouts
* better handling of ISO-639-1 locale codes

## [1.0.10]

### Changed

* better error messages if remote profile is not accessible

## [1.0.9]

### Fixed

* broken following endpoint

## [1.0.8]

### Fixed

* blocking of HEAD requests
* PHP fatal error
* several typos
* error codes

### Changed

* loading of shortcodes
* caching of followers
* Application-User is no longer "indexable"
* more consistent usage of the `application/activity+json` Content-Type

### Removed

* featured tags endpoint

## [1.0.7]

### Added

* filter to hook into "is blog public" check

### Fixed

* broken function call

## [1.0.6]

### Fixed

* more restrictive request verification

## [1.0.5]

### Fixed

* compatibility with WebFinger and NodeInfo plugin

## [1.0.4]

### Fixed

* Constants were not loaded early enough, resulting in a race condition
* Featured image was ignored when using the block editor

## [1.0.3]

### Changed

* refactoring of the Plugin init process
* better frontend UX and improved theme compat for blocks
* add a `ACTIVITYPUB_DISABLE_REWRITES` constant
* add pre-fetch hook to allow plugins to hang filters on

### Fixed

* compatibility with older WordPress/PHP versions

## [1.0.2]

### Changed

* improved hashtag visibility in default template
* reduced number of followers to be checked/updated via Cron, when System Cron is not set up
* check if username of Blog-User collides with an Authors name
* improved Group meta informations

### Fixed

* detection of single user mode
* remote delete
* styles in Follow-Me block
* various encoding and formatting issues
* (health) check Author URLs only if Authors are enabled

## [1.0.1]

### Changed

* improve image attachment detection using the block editor
* better error code handling for API responses
* use a tag stack instead of regex for protecting tags for Hashtags and @-Mentions
* better signature support for subpath-installations
* allow deactivating blocks registered by the plugin
* avoid Fatal Errors when using ClassicPress
* improve the Group-Actor to play nicely with existing implementations

### Fixed

* truncate long blog titles and handles for the "Follow me" block
* ensure that only a valid user can be selected for the "Follow me" block
* fix a typo in a hook name
* a problem with signatures when running WordPress in a sub-path

## [1.0.0]

### Added

* blog-wide Account (catchall, like `example.com@example.com`)
* a Follow Me block (help visitors to follow your Profile)
* Signature Verification: https://docs.joinmastodon.org/spec/security/
* a Followers Block (show off your Followers)
* Simple caching
* Collection endpoints for Featured Tags and Featured Posts
* Better handling of Hashtags in mobile apps

### Changed

* Complete rewrite of the Follower-System based on Custom Post Types
* Improved linter (PHPCS)
* Add a new conditional, `\Activitypub\is_activitypub_request()`, to allow third-party plugins to detect ActivityPub requests
* Add hooks to allow modifying images returned in ActivityPub requests
* Indicate that the plugin is compatible and has been tested with the latest version of WordPress, 6.3
* Avoid PHP notice on sites using PHP 8.2

### Fixed

* Load the plugin later in the WordPress code lifecycle to avoid errors in some requests
* Updating posts
* Hashtag now support CamelCase and UTF-8

## [0.17.0]

### Changed

* Allow more HTML elements in Activity-Objects

### Fixed

* Fix type-selector

## [0.16.5]

### Changed

* Return empty content/excerpt on password protected posts/pages

## [0.16.4]

### Changed

* Remove scripts later in the queue, to also handle scripts added by blocks
* Add published date to author profiles

## [0.16.3]

### Changed

* "cc", "to", ... fields can either be an array or a string

### Removed

* Remove "style" and "script" HTML elements from content

## [0.16.2]

### Fixed

* Fix fatal error in outbox

## [0.16.1]

### Fixed

* Fix "update and create, posts appear blank on Mastodon" issue

## [0.16.0]

### Added

* Add "Outgoing Mentions" ([#213](https://github.com/pfefferle/wordpress-activitypub/pull/213)) props [@akirk](https://github.com/akirk)
* Add configuration item for number of images to attach ([#248](https://github.com/pfefferle/wordpress-activitypub/pull/248)) props [@mexon](https://github.com/mexon)
* Use shortcodes instead of custom templates, to setup the Activity Post-Content ([#250](https://github.com/pfefferle/wordpress-activitypub/pull/250)) props [@toolstack](https://github.com/toolstack)

### Changed

* Change priorites, to maybe fix the hashtag issue

### Removed

* Remove custom REST Server, because the needed changes are now merged into Core.

### Fixed

* Fix hashtags ([#261](https://github.com/pfefferle/wordpress-activitypub/pull/261)) props [@akirk](https://github.com/akirk)

## [0.15.0]

### Changed

* Enable ActivityPub only for users that can `publish_posts`
* Persist only public Activities

### Fixed

* Fix remote-delete

## [0.14.3]

### Changed

* Better error handling. props [@akirk](https://github.com/akirk)

## [0.14.2]

### Fixed

* Fix Critical error when using Friends Plugin and adding new URL to follow. props [@akirk](https://github.com/akirk)

## [0.14.1]

### Fixed

* Fix "WebFinger not compatible with PHP < 8.0". props [@mexon](https://github.com/mexon)

## [0.14.0]

### Changed

* Friends support: https://wordpress.org/plugins/friends/ props [@akirk](https://github.com/akirk)
* Massive guidance improvements. props [mediaformat](https://github.com/mediaformat) & [@akirk](https://github.com/akirk)
* Add Custom Post Type support to outbox API. props [blueset](https://github.com/blueset)
* Better hash-tag support. props [bocops](https://github.com/bocops)

### Fixed

* Fix user-count (NodeInfo). props [mediaformat](https://github.com/mediaformat)

## [0.13.4]

### Fixed

* fix webfinger for email identifiers

## [0.13.3]

### Fixed

* Create and Note should not have the same ActivityPub ID

## [0.13.2]

### Fixed

* fix Follow issue AGAIN

## [0.13.1]

### Fixed

* fix Inbox issue

## [0.13.0]

### Added

* add Autor URL and WebFinger health checks

### Fixed

* fix NodeInfo endpoint

## [0.12.0]

### Changed

* use "pre_option_require_name_email" filter instead of "check_comment_flood". props [@akirk](https://github.com/akirk)
* save only comments/replies
* check for an explicit "undo -> follow" action. see https://wordpress.org/support/topic/qs-after-latest/

## [0.11.2]

### Fixed

* fix inconsistent `%tags%` placeholder

## [0.11.1]

### Fixed

* fix follow/unfollow actions

## [0.11.0]

### Added

* add support for customizable post-content
* first try of a delete activity

### Changed

* do not require email for AP entries. props [@akirk](https://github.com/akirk)

### Fixed

* fix [timezones](https://github.com/pfefferle/wordpress-activitypub/issues/63) bug. props [@mediaformat](https://github.com/mediaformat)
* fix [digest header](https://github.com/pfefferle/wordpress-activitypub/issues/104) bug. props [@mediaformat](https://github.com/mediaformat)


## [0.10.1]

### Fixed

* fix inbox activities, like follow
* fix debug

## [0.10.0]

### Added

* add image alt text to the ActivityStreams attachment property in a format that Mastodon can read. props [@BenLubar](https://github.com/BenLubar)
* use the "summary" property for a title as Mastodon does. props [@BenLubar](https://github.com/BenLubar)
* add new post type: "title and link only". props [@bgcarlisle](https://github.com/bgcarlisle)

### Changed

* support authorized fetch to avoid having comments from "Anonymous". props [@BenLubar](https://github.com/BenLubar)

## [0.9.1]

### Removed

* disable shared inbox
* disable delete activity

## [0.9.0]

### Changed

* some code refactorings

### Fixed

* fix #73

## [0.8.3]

### Fixed

* fixed accept header bug

## [0.8.2]

### Added

* all required accept header
* debugging mechanism
* setting to enable AP for different (public) Post-Types

### Changed

* explicit use of global functions
* better/simpler accept-header handling

## [0.8.1]

### Fixed

* fixed PHP warnings

## [0.8.0]

### Changed

* Moved followers list to user-menu

## [0.7.4]

### Added

* added admin_email to metadata, to be able to "Manage your instance" on https://fediverse.network/manage/

## [0.7.3]

### Changed

* refactorings
* fixed PHP warnings
* better hashtag regex

## [0.7.2]

### Fixed

* fixed JSON representation of posts https://merveilles.town/@xuv/101907542498716956

## [0.7.1]

### Fixed

* fixed inbox problems with pleroma

## [0.7.0]

### Added

* added "following" endpoint

### Changed

* simplified "followers" endpoint

### Fixed

* finally fixed pleroma compatibility
* fixed default value problem

## [0.6.0]

### Added

* add tags as hashtags to the end of each activity

### Changed

* followers-list improvements

### Fixed

* fixed pleroma following issue

## [0.5.1]

### Fixed

* fixed name-collision that caused an infinite loop

## [0.5.0]

### Changed

* complete refactoring

### Fixed

* fixed bug #30: Password-protected posts are federated
* only send Activites when ActivityPub is enabled for this post-type

## [0.4.4]

### Changed

* show avatars

## [0.4.3]

### Fixed

* finally fixed backlink in excerpt/summary posts

## [0.4.2]

### Fixed

* fixed backlink in excerpt/summary posts (thanks @depone)

## [0.4.1]

### Fixed

* finally fixed contact list

## [0.4.0] - 2019-02-17

### Added

* added settings to enable/disable hashtag support

### Fixed

* fixed follower list

### Changed

* send activities only for new posts, otherwise send updates

## [0.3.2] - 2019-02-04

### Added

* added "followers" endpoint

### Changed

* change activity content from blog 'excerpt' to blog 'content'

## [0.3.1] - 2019-02-03

### Changed

* better json encoding

## [0.3.0] - 2019-02-02

### Adeed

* basic hashtag support
* added support for actor objects

### Removed

* temporarily deactivated likes and boosts

### Fixed

* fixed encoding issue

## [0.2.1] - 2019-01-16

### Changed

* customizable backlink (permalink or shorturl)
* show profile-identifiers also on profile settings

## [0.2.0] - 2019-01-04

### Added

* option to switch between content and excerpt

### Removed

* html and duplicate new-lines

## [0.1.1] - 2018-12-30

### Added

* settings for the activity-summary and for the activity-object-type

### Fixed

* "excerpt" in AS JSON

## [0.1.0] - 2018-12-20

### Added

* basic WebFinger support
* basic NodeInfo support
* fully functional "follow" activity
* send new posts to your followers
* receive comments from your followers

## [0.0.2] - 2018-11-06

### Added

* functional inbox

### Changed

* refactoring
* nicer profile views

## [0.0.1] - 2018-09-24

### Added

* initial

[Unreleased]: https://github.com/Automattic/wordpress-activitypub/compare/4.7.3...trunk
<!-- Add new release below and update "Unreleased" link -->
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
