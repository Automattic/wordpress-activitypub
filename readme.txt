=== ActivityPub ===
Contributors: automattic, pfefferle, mattwiebe, obenland, akirk, jeherve, mediaformat, nuriapena, cavalierlife, andremenrath
Tags: OStatus, fediverse, activitypub, activitystream
Requires at least: 5.5
Tested up to: 6.7
Stable tag: 4.2.1
Requires PHP: 7.0
License: MIT
License URI: http://opensource.org/licenses/MIT

The ActivityPub protocol is a decentralized social networking protocol based upon the ActivityStreams 2.0 data format.

== Description ==

Enter the fediverse with **ActivityPub**, broadcasting your blog to a wider audience! Attract followers, deliver updates, and receive comments from a diverse user base of **ActivityPub**\-compliant platforms.

https://www.youtube.com/watch?v=QzYozbNneVc

With the ActivityPub plugin installed, your WordPress blog itself function as a federated profile, along with profiles for each author. For instance, if your website is `example.com`, then the blog-wide profile can be found at `@example.com@example.com`, and authors like Jane and Bob would have their individual profiles at `@jane@example.com` and `@bobz@example.com`, respectively.

An example: I give you my Mastodon profile name: `@pfefferle@mastodon.social`. You search, see my profile, and hit follow. Now, any post I make appears in your Home feed. Similarly, with the ActivityPub plugin, you can find and follow Jane's profile at `@jane@example.com`.

Once you follow Jane's `@jane@example.com` profile, any blog post she crafts on `example.com` will land in your Home feed. Simultaneously, by following the blog-wide profile `@example.com@example.com`, you'll receive updates from all authors.

**Note**: If no one follows your author or blog instance, your posts remain unseen. The simplest method to verify the plugin's operation is by following your profile. If you possess a Mastodon profile, initiate by following your new one.

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
1. Once ActivityPub is installed, *only new posts going forward* will be available in the fediverse. Likewise, even if you’ve been using ActivityPub for a while, anyone who follows your site will only see new posts you publish from that moment on. They will never see previously-published posts in their Home feed. This process is very similar to subscribing to a newsletter. If you subscribe to a newsletter, you will only receive future emails, but not the old archived ones. With ActivityPub, if someone follows your site, they will only receive new blog posts you publish from then on.

So what’s the process?

1. Install the ActivityPub plugin.
1. Go to the plugin’s settings page and adjust the settings to your liking. Click the Save button when ready.
1. Make sure your blog’s author profile page is active if you are using author profiles.
1. Go to Mastodon or any other federated platform, and search for your profile, and follow it. Your new profile will be in the form of either `@your_username@example.com` or `@example.com@example.com`, so that is what you’ll search for.
1. On your blog, publish a new post.
1. From Mastodon, check to see if the new post appears in your Home feed.

**Note**: It may take up to 15 minutes or so for the new post to show up in your federated feed. This is because the messages are sent to the federated platforms using a delayed cron. This avoids breaking the publishing process for those cases where users might have lots of followers. So please don’t assume that just because you didn’t see it show up right away that something is broken. Give it some time. In most cases, it will show up within a few minutes, and you’ll know everything is working as expected.

== Frequently Asked Questions ==

= tl;dr =

This plugin connects your WordPress blog to popular social platforms like Mastodon, making your posts more accessible to a wider audience. Once installed, your blog can be followed by users on these platforms, allowing them to receive your new posts in their feeds.

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

= What if you are running your blog in a subdirectory? =

If you are running your blog in a subdirectory, but have a different [wp_siteurl](https://wordpress.org/documentation/article/giving-wordpress-its-own-directory/), you don't need the redirect, because the index.php will take care of that.

= What if you are running your blog behind a reverse proxy with Apache? =

If you are using a reverse proxy with Apache to run your host you may encounter that you are unable to have followers join the blog. This will occur because the proxy system rewrites the host headers to be the internal DNS name of your server, which the plugin then uses to attempt to sign the replies. The remote site attempting to follow your users is expecting the public DNS name on the replies. In these cases you will need to use the 'ProxyPreserveHost On' directive to ensure the external host name is passed to your internal host.

If you are using SSL between the proxy and internal host you may also need to `SSLProxyCheckPeerName off` if your internal host can not answer with the correct SSL name. This may present a security issue in some environments.

= Constants =

The plugin uses PHP Constants to enable, disable or change its default behaviour. Please use them with caution and only if you know what you are doing.

* `ACTIVITYPUB_REST_NAMESPACE` - Change the default Namespace of the REST endpoint. Default: `activitypub/1.0`.
* `ACTIVITYPUB_EXCERPT_LENGTH` - Change the length of the Excerpt. Default: `400`.
* `ACTIVITYPUB_SHOW_PLUGIN_RECOMMENDATIONS` - show plugin recommendations in the ActivityPub settings. Default: `true`.
* `ACTIVITYPUB_MAX_IMAGE_ATTACHMENTS` - Change the number of attachments, that should be federated. Default: `3`.
* `ACTIVITYPUB_HASHTAGS_REGEXP` - Change the default regex to detect hashtext in a text. Default: `(?:(?<=\s)|(?<=<p>)|(?<=<br>)|^)#([A-Za-z0-9_]+)(?:(?=\s|[[:punct:]]|$))`.
* `ACTIVITYPUB_USERNAME_REGEXP` - Change the default regex to detect @-replies in a text. Default: `(?:([A-Za-z0-9\._-]+)@((?:[A-Za-z0-9_-]+\.)+[A-Za-z]+))`.
* `ACTIVITYPUB_URL_REGEXP` - Change the default regex to detect urls in a text. Default: `(www.|http:|https:)+[^\s]+[\w\/]`.
* `ACTIVITYPUB_CUSTOM_POST_CONTENT` - Change the default template for Activities. Default: `<strong>[ap_title]</strong>\n\n[ap_content]\n\n[ap_hashtags]\n\n[ap_shortlink]`.
* `ACTIVITYPUB_AUTHORIZED_FETCH` - Enable AUTHORIZED_FETCH. Default: `false`.
* `ACTIVITYPUB_DISABLE_REWRITES` - Disable auto generation of `mod_rewrite` rules. Default: `false`.
* `ACTIVITYPUB_DISABLE_INCOMING_INTERACTIONS` - Block incoming replies/comments/likes. Default: `false`.
* `ACTIVITYPUB_DISABLE_OUTGOING_INTERACTIONS` - Disable outgoing replies/comments/likes. Default: `false`.
* `ACTIVITYPUB_SHARED_INBOX_FEATURE` - Enable the shared inbox. Default: `false`.
* `ACTIVITYPUB_SEND_VARY_HEADER` - Enable to send the `Vary: Accept` header. Default: `false`.

= Where can you manage your followers? =

If you have activated the blog user, you will find the list of his followers in the settings under `/wp-admin/options-general.php?page=activitypub&tab=followers`.

The followers of a user can be found in the menu under "Users" -> "Followers" or under `wp-admin/users.php?page=activitypub-followers-list`.

For reasons of data protection, it is not possible to see the followers of other users.

== Screenshots ==

1. The "Follow me"-Block in the Block-Editor
2. The "Followers"-Block in the Block-Editor
3. The "Federated Reply"-Block in the Block-Editor
4. A "Federated Reply" in a Post
5. A Blog-Profile on Mastodon

== Changelog ==

= Dev =

* Added: A `pre_activitypub_get_upload_baseurl` filter
* Added: Fediverse Preview on post-overview page
* Added: GitHub action to enforce Changelog updates
* Added: New contributors
* Improved: Outsource Constants to a separate file
* Improved: Better handling of `readme.txt` and `README.md`
* Fixed: Fediverse preview showing `preferredUsername` instead of `name`
* Fixed: Potential fatal error in Enable Mastodon Apps

= 4.2.1 =

* Added: Mastodon Apps status provider
* Improved: Image-Handling
* Improved: Have better checks if audience should be set or not
* Fixed: Don't overwrite an existing `wp-tests-config.php`
* Fixed: PHPCS for phpunit files

= 4.2.0 =

* Added: Unit tests for the `ActivityPub\Transformer\Post` class
* Improved: Reuse constants once they're defined
* Improved: "FEP-b2b8: Long-form Text" support
* Improved: Admin notice for plain permalink settings is more user-friendly and actionable
* Improved: Post-Formats support
* Fixed: Do not display ActivityPub's user sub-menus to users who do not have the capabilities of writing posts.
* Fixed: Proper margins for notices and font size for page title in settings screen.
* Fixed: Ensure that `?author=0` resolves to blog user

= 4.1.1 =

* Fixed: Only revert to URL if there is one
* Fixed: Migration

= 4.1.0 =

* Added: Add custom Preview for "Fediverse"
* Added: Support `comment_previously_approved` setting
* Fixed: Hide sticky posts that are not public
* Improved: `activity_handle_undo` action
* Improved: Add title to content if post is a `Note`
* Improved: Fallback to blog-user if user is disabled

= 4.0.2 =

* Fixed: Do not federate "Local" posts
* Improved: Help-text for Content-Warning box

= 4.0.1 =

* Fixed: Missing URL-Param handling in REST API
* Fixed: Seriously Simple Podcasting integration
* Fixed: Multiple small fixes
* Improved: Provide contextual fallback for dynamic blocks

= 4.0.0 =

* Added: Fire an action before a follower is removed
* Added: Make Intent-URL filterable
* Added: `title` attribute to link headers for better readability
* Added: Post "visibility" feature
* Added: Attribution-Domains support
* Improved: Inbox validation
* Improved: WordPress-Post-Type - Detection
* Improved: Only validate POST params and do not fall back to GET params
* Improved: ID handling for a better compatibility with caching plugins
* Fixed: The "Shared Inbox" endpoint
* Fixed: Ensure that sticky_posts is an array
* Fixed: URLs and Hashtags in profiles were not converted
* Fixed: A lot of small improvements and fixes

See full Changelog on [GitHub](https://github.com/Automattic/wordpress-activitypub/blob/trunk/CHANGELOG.md).

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
