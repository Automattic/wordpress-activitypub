# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.3.0] - 2024-04-XX

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

## [1.3.0] 2023-12-05

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
