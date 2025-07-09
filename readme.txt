=== ActivityPub ===
Contributors: automattic, pfefferle, mattwiebe, obenland, akirk, jeherve, mediaformat, nuriapena, cavalierlife, andremenrath
Tags: fediverse, activitypub, indieweb, activitystream, social web
Requires at least: 6.5
Tested up to: 6.8
Stable tag: 7.0.0
Requires PHP: 7.2
License: MIT
License URI: http://opensource.org/licenses/MIT

The ActivityPub protocol is a decentralized social networking protocol based upon the ActivityStreams 2.0 data format.

== Description ==

Enter the fediverse with **ActivityPub**, broadcasting your blog to a wider audience! Attract followers, deliver updates, and receive comments from a diverse user base of **ActivityPub**\-compliant platforms.

https://www.youtube.com/watch?v=QzYozbNneVc

With the ActivityPub plugin installed, your WordPress blog itself functions as a federated profile, along with profiles for each author. For instance, if your website is `example.com`, then the blog-wide profile can be found at `@example.com@example.com`, and authors like Jane and Bob would have their individual profiles at `@jane@example.com` and `@bobz@example.com`, respectively.

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

*ActivityPub for WordPress* adds Fediverse features to WordPress, but it is not a replacement for platforms like Friendica or Mastodon. If you're looking to host a decentralized social network, consider using [Mastodon](https://joinmastodon.org/) or [Friendica](https://friendi.ca/).

= Why "ActivityPub"? =

The name ActivityPub comes from the two core ideas behind the protocol:

* Activity: It is based on the concept of activities, like "Create", "Like", "Follow", "Announce", etc. These are structured messages (usually in [ActivityStreams](https://www.w3.org/TR/activitystreams-core/) format) that describe what users do on the network.
* Pub: Short for publish or publication. It refers to the fact that this is a publish-subscribe (pub-sub) protocol — one user can "follow" another, and receive their published activities.

Put together, ActivityPub is a protocol for publishing and subscribing to activities, which enables decentralized social networking — where different servers can interact and users can follow each other across the Fediverse.

= How do I solve… =

We have a **How-To** section in the [docs](https://github.com/Automattic/wordpress-activitypub/tree/trunk/docs/how-to) directory that can help you troubleshoot common issues.

= Constants =

The plugin uses PHP Constants to enable, disable or change its default behaviour. Please use them with caution and only if you know what you are doing.

* `ACTIVITYPUB_REST_NAMESPACE` - Change the default Namespace of the REST endpoint. Default: `activitypub/1.0`.
* `ACTIVITYPUB_EXCERPT_LENGTH` - Change the length of the Excerpt. Default: `400`.
* `ACTIVITYPUB_MAX_IMAGE_ATTACHMENTS` - Change the number of attachments, that should be federated. Default: `4`.
* `ACTIVITYPUB_HASHTAGS_REGEXP` - Change the default regex to detect hashtext in a text. Default: `(?:(?<=\s)|(?<=<p>)|(?<=<br>)|^)#([A-Za-z0-9_]+)(?:(?=\s|[[:punct:]]|$))`.
* `ACTIVITYPUB_USERNAME_REGEXP` - Change the default regex to detect @-replies in a text. Default: `(?:([A-Za-z0-9\._-]+)@((?:[A-Za-z0-9_-]+\.)+[A-Za-z]+))`.
* `ACTIVITYPUB_URL_REGEXP` - Change the default regex to detect urls in a text. Default: `(www.|http:|https:)+[^\s]+[\w\/]`.
* `ACTIVITYPUB_CUSTOM_POST_CONTENT` - Change the default template for Activities. Default: `<strong>[ap_title]</strong>\n\n[ap_content]\n\n[ap_hashtags]\n\n[ap_shortlink]`.
* `ACTIVITYPUB_AUTHORIZED_FETCH` - Enable AUTHORIZED_FETCH.
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

### 7.0.0 - 2025-07-09
#### Added
- Added basic support for handling remote rejections of follow requests.
- Added basic support for RFC-9421 style signatures for incoming activities.
- Added initial Following support for Actors, hidden for now until plugins add support.
- Added missing "Advanced Settings" details to Site Health debug information.
- Added option to auto-approve reactions like likes and reposts.
- Added support for namespaced attributes and the dcterms:subject field (FEP-b2b8), as a first step toward phasing out summary-based content warnings.
- Added support for the WP Rest Cache plugin to help with caching REST API responses.
- Documented support for FEP-844e.
- Optional support for RFC-9421 style signatures for outgoing activities, including retry with Draft-Cavage-style signature.
- Reactions block now supports customizing colors, borders, box-shadows, and typography.
- Support for sending follow requests to remote actors is now in place, including outbox delivery and status updates—UI integration will follow later.

#### Changed
- Comment feeds now show only comments by default, with a new `type` filter (e.g., `like`, `all`) to customize which reactions appear.
- Consistent naming of Blog user in Block settings.
- hs2019 signatures for incoming REST API requests now have their algorithm determined based on their public key.
- Likes, comments, and reposts from the Fediverse now require either a name or `preferredUsername` to be set when the Discussion option `require_name_email` is set to true. It falls back to "Anonymous", if not.
- Management of public/private keys for Actors now lives in the Actors collection, in preparation for Signature improvements down the line.
- Notification emails for new reactions received from the Fediverse now link to the moderation page instead of the edit page, preventing errors and making comment management smoother.
- Plugins now have full control over which Settings tabs are shown in Settings > Activitypub.
- Reworked follower structure to simplify handling and enable reuse for following mechanism.
- Screen options in the Activitypub settings page are now filterable.
- Setting the blog identifier to empty will no longer trigger an error message about it being the same as an existing user name.
- Step completion tracking in the Welcome tab now even works when the number of steps gets reduced.
- The image attachment setting is no longer saved to the database if it matches the default value.
- The welcome page now links to the correct profile when Blog Only mode was selected in the profile mode step.
- Unified retrieval of comment avatars and re-used core filters to give access to third-part plugins.

#### Fixed
- Allow interaction redirect URLs that contain an ampersand.
- Comments received from the Fediverse no longer show an Edit link in the comment list, despite not being editable.
- Fixed an issue where links to remote likes and boosts could open raw JSON instead of a proper page.
- Fixed a potential error when getting an Activitypub ID based on a user ID.
- HTTP signatures using the hs2019 algorithm now get accepted without error.
- Improved compatibility with older follower data.
- Inbox requests that are missing an `algorithm` parameter in their signature no longer create a PHP warning.
- Interaction attempts that pass a webfinger ID instead of a URL will work again.
- Names containing HTML entities now get displayed correctly in the Reactions block's list of users.
- Prevent storage of empty or default post meta values.
- The amount of avatars shown in the Reactions block no longer depends on the amount of likes, but is comment type agnostic.
- The command-line interface extension, accidentally removed in a recent cleanup, has been restored.
- The image attachment setting now correctly respects a value of 0, instead of falling back to the default.
- The Welcome screen now loads with proper styling when shown as a fallback.
- Using categories as hashtags has been removed to prevent conflicts with tags of the same name.
- When verifying signatures on incoming requests, the digest header now gets checked as expected.

See full Changelog on [GitHub](https://github.com/Automattic/wordpress-activitypub/blob/trunk/CHANGELOG.md).

== Upgrade Notice ==

= 7.0.0 =

HTTP signatures now accept modern RFC 9421 standard with fallback and verification.

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
