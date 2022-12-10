=== ActivityPub ===
Contributors: pfefferle, mediaformat, akirk
Donate link: https://notiz.blog/donate/
Tags: OStatus, fediverse, activitypub, activitystream
Requires at least: 4.7
Tested up to: 6.1
Stable tag: 0.14.0
Requires PHP: 5.6
License: MIT
License URI: http://opensource.org/licenses/MIT

The ActivityPub protocol is a decentralized social networking protocol based upon the ActivityStreams 2.0 data format.

== Description ==

This is **BETA** software, see the FAQ to see the current feature set or rather what is still planned.

The plugin implements the ActivityPub protocol for your blog. Your readers will be able to follow your blogposts on Mastodon and other federated platforms that support ActivityPub.

The plugin works with the following federated platforms:

* [Mastodon](https://joinmastodon.org/)
* [Pleroma](https://pleroma.social/)
* [Friendica](https://friendi.ca/)
* [HubZilla](https://hubzilla.org/)
* [Pixelfed](https://pixelfed.org/)
* [SocialHome](https://socialhome.network/)
* [Misskey](https://join.misskey.page/)

== Frequently Asked Questions ==

= What is the status of this plugin? =

Implemented:

* profile pages (JSON representation)
* custom links
* functional inbox/outbox
* follow (accept follows)
* share posts
* receive comments/reactions

To implement:

* signature verification
* better WordPress integration
* better configuration possibilities
* threaded comments support

= What is "ActivityPub for WordPress" =

*ActivityPub for WordPress* extends WordPress with some Fediverse features, but it does not compete with platforms like Friendica or Mastodon. If you want to run a **decentralized social network**, please use [Mastodon](https://joinmastodon.org/) or [GNU social](https://gnusocial.network/).

= What are the differences between this plugin and Pterotype? =

**Compatibility**

*ActivityPub for WordPress* is compatible with OStatus and IndieWeb plugin suites. *Pterotype* is incompatible with the standalone [WebFinger plugin](https://wordpress.org/plugins/webfinger/), so it can't be run together with OStatus.

**Custom tables**

*Pterotype* creates/uses a bunch of custom tables, *ActivityPub for WordPress* only uses the native tables and adds as little meta data as possible.

= What if you are running your blog in a subdirectory? =

In order for webfinger to work, it must be mapped to the root directory of the URL on which your blog resides.

**Apache**

Add the following to the .htaccess file in the root directory:

	RedirectMatch "^\/\.well-known(.*)$" "\/blog\/\.well-known$1"

Where 'blog' is the path to the subdirectory at which your blog resides.

**Nginx**

Add the following to the site.conf in sites-available:

	location ~* /.well-known {
		allow all;
		try_files $uri $uri/ /blog/?$args;
	}

Where 'blog' is the path to the subdirectory at which your blog resides.

== Changelog ==

Project maintained on GitHub at [pfefferle/wordpress-activitypub](https://github.com/pfefferle/wordpress-activitypub).

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
