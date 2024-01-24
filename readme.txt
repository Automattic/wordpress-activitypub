=== ActivityPub ===
Contributors: automattic, pfefferle, mediaformat, mattwiebe, akirk, jeherve, nuriapena, cavalierlife
Tags: OStatus, fediverse, activitypub, activitystream
Requires at least: 5.5
Tested up to: 6.4
Stable tag: 2.0.2
Requires PHP: 5.6
License: MIT
License URI: http://opensource.org/licenses/MIT

The ActivityPub protocol is a decentralized social networking protocol based upon the ActivityStreams 2.0 data format.

== Description ==

Enter the fediverse with **ActivityPub**, broadcasting your blog to a wider audience! Attract followers, deliver updates, and receive comments from a diverse user base of **ActivityPub**\-compliant platforms.

With the ActivityPub plugin installed, your WordPress blog itself function as a federated profile, along with profiles for each author. For instance, if your website is `example.com`, then the blog-wide profile can be found at `@example.com@example.com`, and authors like Jane and Bob would have their individual profiles at `@jane@example.com` and `@bobz@example.com`, respectively.

An example: I give you my Mastodon profile name: `@pfefferle@mastodon.social`. You search, see my profile, and hit follow. Now, any post I make appears in your Home feed. Similarly, with the ActivityPub plugin, you can find and follow Jane's profile at `@jane@example.com`.

Once you follow Jane's `@jane@example.com` profile, any blog post she crafts on `example.com` will land in your Home feed. Simultaneously, by following the blog-wide profile `@example.com@example.com`, you'll receive updates from all authors.

**Note**: if no one follows your author or blog instance, your posts remain unseen. The simplest method to verify the plugin's operation is by following your profile. If you possess a Mastodon profile, initiate by following your new one.

The plugin works with the following tested federated platforms, but there may be more that it works with as well:

* [Mastodon](https://joinmastodon.org/)
* [Pleroma](https://pleroma.social/)/[Akkoma](https://akkoma.social/)
* [friendica](https://friendi.ca/)
* [Hubzilla](https://hubzilla.org/)
* [Pixelfed](https://pixelfed.org/)
* [Socialhome](https://socialhome.network/)
* [Misskey](https://join.misskey.page/)
* [Firefish](https://joinfirefish.org/) (rebrand of Calckey)

Some things to note:

1. The blog-wide profile is only compatible with sites with rewrite rules enabled. If your site does not have rewrite rules enabled, the author-specific profiles may still work.
1. Many single-author blogs have chosen to turn off or redirect their author profile pages, usually via an SEO plugin like Yoast or Rank Math. This is usually done to avoid duplicate content with your blog’s home page. If your author page has been deactivated in this way, then ActivityPub author profiles won’t work for you. Instead, you can turn your author profile page back on, and then use the option in your SEO plugin to noindex the author page. This will still resolve duplicate content issues with search engines and will enable ActivityPub author profiles to work.
1. Once ActivityPub is installed, *only new posts going forward* will be available in the fediverse. Likewise, even if you’ve been using ActivityPub for a while, anyone who follows your site, will only see new posts you publish from that moment on. They will never see previously-published posts in their Home feed. This process is very similar to subscribing to a newsletter. If you subscribe to a newsletter, you will only receive future emails, but not the old archived ones. With ActivityPub, if someone follows your site, they will only receive new blog posts you publish from then on.

So what’s the process?

1. Install the ActivityPub plugin.
1. Go to the plugin’s settings page and adjust the settings to your liking. Click the Save button when ready.
1. Make sure your blog’s author profile page is active if you are using author profiles.
1. Go to Mastodon or any other federated platform, and search for your profile, and follow it. Your new profile will be in the form of either `@your_username@example.com` or `@example.com@example.com`, so that is what you’ll search for.
1. On your blog, publish a new post.
1. From Mastodon, check to see if the new post appears in your Home feed.

Please note that it may take up to 15 minutes or so for the new post to show up in your federated feed. This is because the messages are sent to the federated platforms using a delayed cron. This avoids breaking the publishing process for those cases where users might have lots of followers. So please don’t assume that just because you didn’t see it show up right away that something is broken. Give it some time. In most cases, it will show up within a few minutes, and you’ll know everything is working as expected.

== Frequently Asked Questions ==

= tl;dr =

This plugin connects your WordPress blog to popular social platforms like Mastodon, making your posts more accessible to a wider audience. Once installed, your blog can be followed by users on these platforms, allowing them to receive your new posts in their feeds.

= What is the status of this plugin? =

Implemented:

* blog profile pages (JSON representation)
* author profile pages (JSON representation)
* custom links
* functional inbox/outbox
* follow (accept follows)
* share posts
* receive comments/reactions
* signature verification
* threaded comments support

To implement:

* replace shortcodes with blocks for layout

= What is "ActivityPub for WordPress" =

*ActivityPub for WordPress* extends WordPress with some Fediverse features, but it does not compete with platforms like Friendica or Mastodon. If you want to run a **decentralized social network**, please use [Mastodon](https://joinmastodon.org/) or [GNU social](https://gnusocial.network/).

= What if you are running your blog in a subdirectory? =

In order for webfinger to work, it must be mapped to the root directory of the URL on which your blog resides.

**Apache**

Add the following to the .htaccess file in the root directory:

	RedirectMatch "^\/\.well-known/(webfinger|nodeinfo|x-nodeinfo2)(.*)$" /blog/.well-known/$1$2

Where 'blog' is the path to the subdirectory at which your blog resides.

**Nginx**

Add the following to the site.conf in sites-available:

	location ~* /.well-known {
		allow all;
		try_files $uri $uri/ /blog/?$args;
	}

Where 'blog' is the path to the subdirectory at which your blog resides.

= What if you are running your blog in a subdirectory, but have a different [wp_siteurl](https://wordpress.org/documentation/article/giving-wordpress-its-own-directory/)? =

In that case you don't need the redirect, because the index.php will take care of that.

= Constants =

The plugin uses PHP Constants to enable, disable or change its default behaviour. Please use them with caution and only if you know what you are doing.

* `ACTIVITYPUB_REST_NAMESPACE` - Change the default Namespace of the REST endpoint. Default: `activitypub/1.0`.
* `ACTIVITYPUB_EXCERPT_LENGTH` - Change the length of the Excerpt. Default: `400`.
* `ACTIVITYPUB_SHOW_PLUGIN_RECOMMENDATIONS` - show plugin recommendations in the ActivityPub settings. Default: `true`.
* `ACTIVITYPUB_MAX_IMAGE_ATTACHMENTS` - Change the number of attachments, that should be federated. Default: `3`.
* `ACTIVITYPUB_HASHTAGS_REGEXP` - Change the default regex to detect hashtext in a text. Default: `(?:(?<=\s)|(?<=<p>)|(?<=<br>)|^)#([A-Za-z0-9_]+)(?:(?=\s|[[:punct:]]|$))`.
* `ACTIVITYPUB_USERNAME_REGEXP` - Change the default regex to detect @-replies in a text. Default: `(?:([A-Za-z0-9\._-]+)@((?:[A-Za-z0-9_-]+\.)+[A-Za-z]+))`.
* `ACTIVITYPUB_CUSTOM_POST_CONTENT` - Change the default template for Activities. Default: `<strong>[ap_title]</strong>\n\n[ap_content]\n\n[ap_hashtags]\n\n[ap_shortlink]`.
* `ACTIVITYPUB_AUTHORIZED_FETCH` - Enable AUTHORIZED_FETCH. Default: `false`.
* `ACTIVITYPUB_DISABLE_REWRITES` - Disable auto generation of `mod_rewrite` rules. Default: `false`.
* `ACTIVITYPUB_DISABLE_INCOMING_INTERACTIONS` - Block incoming replies/comments/likes. Default: `false`.
* `ACTIVITYPUB_DISABLE_OUTGOING_INTERACTIONS` - Disable outgoing replies/comments/likes. Default: `false`.
* `ACTIVITYPUB_SHARED_INBOX_FEATURE` - Enable the shared inbox. Default: `false`.

== Changelog ==

Project maintained on GitHub at [automattic/wordpress-activitypub](https://github.com/automattic/wordpress-activitypub).

= 2.0.2 =

* Fixed: Some Federated Comment improvements
* Fixed: Remove old/abandoned Crons

= 2.0.1 =

* Fixed: Comment `Update` Federation
* Workaround: Re-Added Post Model Class because of some weird caching issues
* Fixed: WebFinger check
* Fixed: Classic editor image finding for large images

= 2.0.0 =

* Added: Bidirectional Comment Federation
* Removed: Deprecated Classes
* Fixed: Normalize attributes that can have mixed value types
* Added: URL support for WebFinger
* Added: Make Post-Template filterable
* Added: CSS class for ActivityPub comments to allow custom designs
* Added: FEP-2677: Identifying the Application Actor
* Added: FEP-2c59: Discovery of a Webfinger address from an ActivityPub actor
* Added: Profile Update Activities
* Improved: WebFinger endpoints

= 1.3.0 =

* Added: Threaded-Comments support
* Improved: alt text for avatars in Follow Me/Followers blocks
* Improved: `Delete`, `Update` and `Follow` Activities
* Improved: better/more effective handling of `Delete` Activities
* Improved: allow `<p />` and `<br />` for Comments
* Fixed: removed default limit of WP_Query to send updates to all Inboxes and not only to the first 10

= 1.2.0 =

* Add: Search and order followerer lists
* Add: Have a filter to defer signature verification
* Improved: "Follow Me" styles for dark themes
* Improved: Allow `p` and `br` tags only for AP comments
* Fixed: Deduplicate attachments earlier to prevent incorrect max_media


= 1.1.0 =

* Improved: audio and video attachments are now supported!
* Improved: better error messages if remote profile is not accessible
* Improved: PHP 8.1 compatibility
* Fixed: don't try to parse mentions or hashtags for very large (>1MB) posts to prevent timeouts
* Fixed: better handling of ISO-639-1 locale codes
* Improved: more reliable [ap_author], props @uk3
* Improved: NodeInfo statistics

= 1.0.10 =

* Improved: better error messages if remote profile is not accessible

= 1.0.9 =

* Fixed: broken following endpoint

= 1.0.8 =

* Fixed: blocking of HEAD requests
* Fixed: PHP fatal error
* Fixed: several typos
* Fixed: error codes
* Improved: loading of shortcodes
* Updated: caching of followers
* Updated: Application-User is no longer "indexable"
* Updated: more consistent usage of the `application/activity+json` Content-Type
* Removed: featured tags endpoint

= 1.0.7 =

* Fixed: broken function call
* Add: filter to hook into "is blog public" check

= 1.0.6 =

* Fixed: more restrictive request verification

= 1.0.5 =

* Fixed: compatibility with WebFinger and NodeInfo plugin

= 1.0.4 =

* Fixed: Constants were not loaded early enough, resulting in a race condition
* Fixed: Featured image was ignored when using the block editor

= 1.0.3 =

* Fixed: compatibility with older WordPress/PHP versions
* Update: refactoring of the Plugin init process
* Update: better frontend UX and improved theme compat for blocks
* Compatibility: add a ACTIVITYPUB_DISABLE_REWRITES constant
* Compatibility: add pre-fetch hook to allow plugins to hang filters on

= 1.0.2 =

* Updated: improved hashtag visibility in default template
* Updated: reduced number of followers to be checked/updated via Cron, when System Cron is not set up
* Updated: check if username of Blog-User collides with an Authors name
* Compatibility: improved Group meta informations
* Fixed: detection of single user mode
* Fixed: remote delete
* Fixed: styles in Follow-Me block
* Fixed: various encoding and formatting issues
* Fixed: (health) check Author URLs only if Authors are enabled

= 1.0.1 =

* Update: improve image attachment detection using the block editor
* Update: better error code handling for API responses
* Update: use a tag stack instead of regex for protecting tags for Hashtags and @-Mentions
* Compatibility: better signature support for subpath-installations
* Compatibility: allow deactivating blocks registered by the plugin
* Compatibility: avoid Fatal Errors when using ClassicPress
* Compatibility: improve the Group-Actor to play nicely with existing implementations
* Fixed: truncate long blog titles and handles for the "Follow me" block
* Fixed: ensure that only a valid user can be selected for the "Follow me" block
* Fixed: fix a typo in a hook name
* Fixed: a problem with signatures when running WordPress in a sub-path

= 1.0.0 =

* Add: blog-wide Account (catchall, like `example.com@example.com`)
* Add: a Follow Me block (help visitors to follow your Profile)
* Add: Signature Verification: https://docs.joinmastodon.org/spec/security/
* Add: a Followers Block (show off your Followers)
* Add: Simple caching
* Add: Collection endpoints for Featured Tags and Featured Posts
* Add: Better handling of Hashtags in mobile apps
* Update: Complete rewrite of the Follower-System based on Custom Post Types
* Update: Improved linter (PHPCS)
* Compatibility: Add a new conditional, `\Activitypub\is_activitypub_request()`, to allow third-party plugins to detect ActivityPub requests
* Compatibility: Add hooks to allow modifying images returned in ActivityPub requests
* Compatibility: Indicate that the plugin is compatible and has been tested with the latest version of WordPress, 6.3
* Compatibility: Avoid PHP notice on sites using PHP 8.2
* Fixed: Load the plugin later in the WordPress code lifecycle to avoid errors in some requests
* Fixed: Updating posts
* Fixed: Hashtag now support CamelCase and UTF-8

= 0.17.0 =

* Fix type-selector
* Allow more HTML elements in Activity-Objects

= 0.16.5 =

* Return empty content/excerpt on password protected posts/pages

= 0.16.4 =

* Remove scripts later in the queue, to also handle scripts added by blocks
* Add published date to author profiles

= 0.16.3 =

* "cc", "to", ... fields can either be an array or a string
* Remove "style" and "script" HTML elements from content

= 0.16.2 =

* Fix fatal error in outbox

= 0.16.1 =

* Fix "update and create, posts appear blank on Mastodon" issue

= 0.16.0 =

* Add "Outgoing Mentions" ([#213](https://github.com/pfefferle/wordpress-activitypub/pull/213)) props [@akirk](https://github.com/akirk)
* Add configuration item for number of images to attach ([#248](https://github.com/pfefferle/wordpress-activitypub/pull/248)) props [@mexon](https://github.com/mexon)
* Use shortcodes instead of custom templates, to setup the Activity Post-Content ([#250](https://github.com/pfefferle/wordpress-activitypub/pull/250)) props [@toolstack](https://github.com/toolstack)
* Remove custom REST Server, because the needed changes are now merged into Core.
* Fix hashtags ([#261](https://github.com/pfefferle/wordpress-activitypub/pull/261)) props [@akirk](https://github.com/akirk)
* Change priorites, to maybe fix the hashtag issue

= 0.15.0 =

* Enable ActivityPub only for users that can `publish_posts`
* Persist only public Activities
* Fix remote-delete

= 0.14.3 =

* Better error handling. props [@akirk](https://github.com/akirk)

= 0.14.2 =

* Fix Critical error when using Friends Plugin and adding new URL to follow. props [@akirk](https://github.com/akirk)

= 0.14.1 =

* Fix "WebFinger not compatible with PHP < 8.0". props [@mexon](https://github.com/mexon)

= 0.14.0 =

* Friends support: https://wordpress.org/plugins/friends/ props [@akirk](https://github.com/akirk)
* Massive guidance improvements. props [mediaformat](https://github.com/mediaformat) & [@akirk](https://github.com/akirk)
* Add Custom Post Type support to outbox API. props [blueset](https://github.com/blueset)
* Better hash-tag support. props [bocops](https://github.com/bocops)
* Fix user-count (NodeInfo). props [mediaformat](https://github.com/mediaformat)

= 0.13.4 =

* fix webfinger for email identifiers

= 0.13.3 =

* fix: Create and Note should not have the same ActivityPub ID

= 0.13.2 =

* fix Follow issue AGAIN

= 0.13.1 =

* fix Inbox issue

= 0.13.0 =

* add Autor URL and WebFinger health checks
* fix NodeInfo endpoint

= 0.12.0 =

* use "pre_option_require_name_email" filter instead of "check_comment_flood". props [@akirk](https://github.com/akirk)
* save only comments/replies
* check for an explicit "undo -> follow" action. see https://wordpress.org/support/topic/qs-after-latest/

= 0.11.2 =

* fix inconsistent `%tags%` placeholder

= 0.11.1 =

* fix follow/unfollow actions

= 0.11.0 =

* add support for customizable post-content
* first try of a delete activity
* do not require email for AP entries. props [@akirk](https://github.com/akirk)
* fix [timezones](https://github.com/pfefferle/wordpress-activitypub/issues/63) bug. props [@mediaformat](https://github.com/mediaformat)
* fix [digest header](https://github.com/pfefferle/wordpress-activitypub/issues/104) bug. props [@mediaformat](https://github.com/mediaformat)


= 0.10.1 =

* fix inbox activities, like follow
* fix debug

= 0.10.0 =

* add image alt text to the ActivityStreams attachment property in a format that Mastodon can read. props [@BenLubar](https://github.com/BenLubar)
* use the "summary" property for a title as Mastodon does. props [@BenLubar](https://github.com/BenLubar)
* support authorized fetch to avoid having comments from "Anonymous". props [@BenLubar](https://github.com/BenLubar)
* add new post type: "title and link only". props [@bgcarlisle](https://github.com/bgcarlisle)

= 0.9.1 =

* disable shared inbox
* disable delete activity

= 0.9.0 =

* some code refactorings
* fix #73

= 0.8.3 =

* fixed accept header bug

= 0.8.2 =

* add all required accept header
* better/simpler accept-header handling
* add debugging mechanism
* Add setting to enable AP for different (public) Post-Types
* explicit use of global functions

= 0.8.1 =

* fixed PHP warnings

= 0.8.0 =

* Moved followers list to user-menu

= 0.7.4 =

* added admin_email to metadata, to be able to "Manage your instance" on https://fediverse.network/manage/

= 0.7.3 =

* refactorings
* fixed PHP warnings
* better hashtag regex

= 0.7.2 =

* fixed JSON representation of posts https://merveilles.town/@xuv/101907542498716956

= 0.7.1 =

* fixed inbox problems with pleroma

= 0.7.0 =

* finally fixed pleroma compatibility
* added "following" endpoint
* simplified "followers" endpoint
* fixed default value problem

= 0.6.0 =

* add tags as hashtags to the end of each activity
* fixed pleroma following issue
* followers-list improvements

= 0.5.1 =

* fixed name-collision that caused an infinite loop

= 0.5.0 =

* complete refactoring
* fixed bug #30: Password-protected posts are federated
* only send Activites when ActivityPub is enabled for this post-type

= 0.4.4 =

* show avatars

= 0.4.3 =

* finally fixed backlink in excerpt/summary posts

= 0.4.2 =

* fixed backlink in excerpt/summary posts (thanks @depone)

= 0.4.1 =

* finally fixed contact list

= 0.4.0 =

* added settings to enable/disable hashtag support
* fixed follower list
* send activities only for new posts, otherwise send updates

= 0.3.2 =

* added "followers" endpoint
* change activity content from blog 'excerpt' to blog 'content'

= 0.3.1 =

* better json encoding

= 0.3.0 =

* basic hashtag support
* temporarily deactivated likes and boosts
* added support for actor objects
* fixed encoding issue

= 0.2.1 =

* customizable backlink (permalink or shorturl)
* show profile-identifiers also on profile settings

= 0.2.0 =

* added option to switch between content and excerpt
* removed html and duplicate new-lines

= 0.1.1 =

* fixed "excerpt" in AS JSON
* added settings for the activity-summary and for the activity-object-type

= 0.1.0 =

* added basic WebFinger support
* added basic NodeInfo support
* fully functional "follow" activity
* send new posts to your followers
* receive comments from your followers

= 0.0.2 =

* refactoring
* functional inbox
* nicer profile views

= 0.0.1 =

* initial

== Upgrade Notice ==

= 1.0.0 =

For version 1.0.0 we have completely rebuilt the followers lists. There is a migration from the old format to the new, but it may take some time until the migration is complete. No data will be lost in the process, please give the migration some time.

== Installation ==

Follow the normal instructions for [installing WordPress plugins](https://wordpress.org/support/article/managing-plugins/).

= Automatic Plugin Installation =

To add a WordPress Plugin using the [built-in plugin installer](https://codex.wordpress.org/Administration_Screens#Add_New_Plugins):

1. Go to [Plugins](https://codex.wordpress.org/Administration_Screens#Plugins) > [Add New](https://codex.wordpress.org/Plugins_Add_New_Screen).
1. Type "`activitypub`" into the **Search Plugins** box.
1. Find the WordPress Plugin you wish to install.
    1. Click **Details** for more information about the Plugin and instructions you may wish to print or save to help setup the Plugin.
    1. Click **Install Now** to install the WordPress Plugin.
1. The resulting installation screen will list the installation as successful or note any problems during the install.
1. If successful, click **Activate Plugin** to activate it, or **Return to Plugin Installer** for further actions.

= Manual Plugin Installation =

There are a few cases when manually installing a WordPress Plugin is appropriate.

* If you wish to control the placement and the process of installing a WordPress Plugin.
* If your server does not permit automatic installation of a WordPress Plugin.
* If you want to try the [latest development version](https://github.com/pfefferle/wordpress-activitypub).

Installation of a WordPress Plugin manually requires FTP familiarity and the awareness that you may put your site at risk if you install a WordPress Plugin incompatible with the current version or from an unreliable source.

Backup your site completely before proceeding.

To install a WordPress Plugin manually:

* Download your WordPress Plugin to your desktop.
    * Download from [the WordPress directory](https://wordpress.org/plugins/activitypub/)
    * Download from [GitHub](https://github.com/pfefferle/wordpress-activitypub/releases)
* If downloaded as a zip archive, extract the Plugin folder to your desktop.
* With your FTP program, upload the Plugin folder to the `wp-content/plugins` folder in your WordPress directory online.
* Go to [Plugins screen](https://codex.wordpress.org/Administration_Screens#Plugins) and find the newly uploaded Plugin in the list.
* Click **Activate** to activate it.
