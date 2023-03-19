=== ActivityPub ===
Contributors: pfefferle, mediaformat, akirk, automattic
Tags: OStatus, fediverse, activitypub, activitystream
Requires at least: 4.7
Tested up to: 6.1
Stable tag: 0.17.0
Requires PHP: 5.6
License: MIT
License URI: http://opensource.org/licenses/MIT

The ActivityPub protocol is a decentralized social networking protocol based upon the ActivityStreams 2.0 data format.

== Description ==

This is BETA software, see the FAQ to see the current feature set or rather what is still planned.

The plugin implements the ActivityPub protocol for your blog, which means that your readers will be able to follow your blog posts on Mastodon and other federated platforms that support ActivityPub. In addition, replies to your posts on Mastodon and related platforms will automatically become comments on your blog post.

The plugin works with the following tested federated platforms, but there may be more that it works with as well:

* [Mastodon](https://joinmastodon.org/)
* [Pleroma](https://pleroma.social/)
* [Friendica](https://friendi.ca/)
* [HubZilla](https://hubzilla.org/)
* [Pixelfed](https://pixelfed.org/)
* [SocialHome](https://socialhome.network/)
* [Misskey](https://join.misskey.page/)

Here’s what that means and what you can expect.

Once the ActivityPub plugin is installed, each author’s page on your WordPress blog will become its own federated instance. In other words, if you have two authors, Jane and Bob, on your website, `example.com`, then your authors would have their own author pages at `example.com/author/jane` and `example.com/author/bob`. Each of those author pages would now be available to Mastodon users (and all other federated platform users) as a profile that can be followed. Let’s break that down further. Let’s say you have a friend on Mastodon who tells you to follow them and they give you their profile name `@janelivesheresomeofthetime@mastodon.social`. You search for her name, see her profile, and click the follow button, right? From then on, everything Jane posts on her profile shows up in your Home feed. Okay, similarly, now that Jane has installed the ActivityPub plugin on her `example.com` site, her friends can also follow her on Mastodon by searching for `@jane@example.com` and clicking the Follow button on that profile.

From now on, every blog post Jane publishes on example.com will show up on your Home feed because you follow her `@jane@example.com` profile.
Of course, if no one follows your author instance, then no one will ever see the posts - including you! So the easiest way to even know if the plugin is working is to follow your new profile yourself. If you already have a Mastodon profile, just follow your new one from there.

Some things to note:

1. Many single-author blogs have chosen to turn off or redirect their author profile pages, usually via an SEO plugin like Yoast or Rank Math. This is usually done to avoid duplicate content with your blog’s home page. If your author page has been deactivated in this way, then ActivityPub won’t work for you. Instead, you can turn your author profile page back on, and then use the option in your SEO plugin to noindex the author page. This will enable the page to be live and ActivityPub will now work, but the live page won’t cause any duplicate content issues with search engines.
1. Once ActivityPub is installed, only new posts going forward will be available in the fediverse. Likewise, even if you’ve been using ActivityPub for a while, anyone who follows your site, will only see new posts you publish from that moment on. They will never see previously-published posts in their Home feed. This process is very similar to subscribing to a newsletter. If you subscribe to a newsletter, you will only receive future emails, but not the old archived ones. With ActivityPub, if someone follows your site, they will only receive new blog posts you publish from then on.

So what’s the process?

1. Install the ActivityPub plugin.
1. Go to the plugin’s settings page and adjust the settings to your liking. Click the Save button when ready.
1. Make sure your blog’s author profile page is active.
1. Go to Mastodon or any other federated platform, search for your author’s new federated profile, and follow it. Your new profile will be in the form of @yourauthorname@yourwebsite.com, so that is what you’ll search for.
1. On your blog, publish a new post.
1. From Mastodon, check to see if the new post appears in your Home feed.

Please note that it may take up to 15 minutes or so for the new post to show up in your federated feed. This is because the messages are sent to the federated platforms using a delayed cron. This avoids breaking the publishing process for those cases where users might have lots of followers. So please don’t assume that just because you didn’t see it show up right away that something is broken. Give it some time. In most cases, it will show up within a few minutes, and you’ll know everything is working as expected.

== Frequently Asked Questions ==

= tl;dr =

This plugin connects your WordPress blog to popular social platforms like Mastodon, making your posts more accessible to a wider audience. Once installed, your blog's author pages can be followed by users on these platforms, allowing them to receive your new posts in their feeds.

Here's how it works:

1. Install the plugin and adjust settings as needed.
1. Ensure your blog's author profile page is active.
1. On Mastodon or other supported platforms, search for and follow your author's new profile (e.g., `@yourauthorname@yourwebsite.com`).
1. Publish a new post on your blog and check if it appears in your Mastodon feed.

Please note that it may take up to 15 minutes for a new post to appear in your feed, as messages are sent on a delay to avoid overwhelming your followers. Be patient and give it some time.

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
