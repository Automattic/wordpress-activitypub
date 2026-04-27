=== ActivityPub ===
Contributors: automattic, pfefferle, mattwiebe, obenland, akirk, jeherve, mediaformat, nuriapena, cavalierlife, andremenrath
Tags: fediverse, activitypub, indieweb, activitystream, social web
Requires at least: 6.5
Tested up to: 6.9
Stable tag: 8.1.1
Requires PHP: 7.4
License: MIT
License URI: http://opensource.org/licenses/MIT

Connect your site to the Open Social Web and let millions of users follow, share, and interact with your content from Mastodon, Pixelfed, and more.

== Description ==

Enter the fediverse with **ActivityPub**, broadcasting your blog to a wider audience! Attract followers, deliver updates, and receive comments from a diverse user base of **ActivityPub**\-compliant platforms.

https://www.youtube.com/watch?v=QzYozbNneVc

With the ActivityPub plugin installed, your WordPress blog itself functions as a federated profile, along with profiles for each author. For instance, if your website is `example.com`, then the blog-wide profile can be found at `@example.com@example.com`, and authors like Jane and Bob would have their individual profiles at `@jane@example.com` and `@bob@example.com`, respectively.

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
* `ACTIVITYPUB_DISABLE_REMOTE_CACHE` - Disable remote media caching (avatars, media, emoji). Default: `false`. Replaces `ACTIVITYPUB_DISABLE_SIDELOADING` from 7.9.1.
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

### 8.1.1 - 2026-04-22
#### Added
- Added the `activitypub_post_object_type` filter so plugins can override the federated object type (Note, Article, Page) for a post.

#### Changed
- Always flush rewrite rules at the end of a plugin migration so that users upgrading across multiple versions do not miss a flush.

#### Fixed
- Fix the Fediverse stats widget on sites where the REST namespace is remapped, such as WordPress.com.
- Harden the reactions API response so stored author names and URLs cannot introduce markup or non-HTTP schemes into the JSON output.
- Stop hiding posts that contain a federated reply block from the main blog listing and the admin post list on sites that do not use the Posts and Replies block.

### 8.1.0 - 2026-04-21
#### Security
- Add rate limiting to app registration to prevent abuse.
- Fix blog actor outbox exposing private activities to unauthenticated visitors.
- Restrict localhost URL allowance to local development environments only.
- Verify that the signing key belongs to the same server as the activity actor.

#### Added
- Add a "Posts and Replies" tab bar for author archives that filters between posts and replies, similar to Mastodon's profile view.
- Add a liked collection to actor profiles, showing all posts the actor has liked.
- Add a seasonal starter pattern that suggests sharing Fediverse stats when creating a new post in December and January.
- Add a stats block that displays annual Fediverse statistics as a card on the site and as a shareable image on the Fediverse, with automatic color and font adoption from the site's theme.
- Added `activitypub_pre_get_by_id` filter to allow plugins to register custom virtual actors resolved by ID.
- Add EXIF metadata support for image attachments using Vernissage namespace.
- Add new Fediverse Following Page and Profile Page block patterns.
- Add OAuth server metadata and registration endpoint discovery to actor profiles.
- Add real-time streaming for inbox and outbox updates via Server-Sent Events (SSE).
- Add support for Block, Add (pin post), and Remove (unpin post) activities via Client-to-Server API.
- Add support for check-in activities posted via compatible apps.
- Add support for importing Starter Packs in both the Pixelfed and Mastodon formats.
- Add tags.pub integration to supplement tag timelines with posts from across the Fediverse.
- Support for ActivityPub Client-to-Server (C2S) protocol, allowing apps like federated clients to create, edit, and delete posts on your behalf.

#### Changed
- Block patterns for follow, following, and profile pages are now only suggested when editing pages.
- Fix notification pagination when using Enable Mastodon Apps: use date-constrained queries instead of truncating the shared notification pool, and expose `$limit`, `$before_date`, and `$after_date` as additional filter arguments so third-party handlers can fetch the correct window.
- Improve the pre-publish format suggestion panel with clearer messages and a confirmation after applying a format.
- Podcast episodes now respect the configured object type setting instead of always being sent as "Note".
- Show reaction action buttons even when a post has no reactions yet.

#### Fixed
- ActivityPub endpoints that surface comment, reply, like, share, and remote-reply metadata now honor the parent post's visibility setting.
- Added validation for SSE access tokens passed via query parameter.
- Fix account migration (Move) not working when moving back to an external account.
- Fix a fatal error during activity delivery when the outbox item has been deleted.
- Fix a fatal error when receiving activities with a non-string language property.
- Fix a fatal `array_keys(null)` in `Comment::get_comment_type_slugs()` that could take down any request where a third-party plugin transitioned a custom comment type before `add_comment_type()` had been called.
- Fix a missing script dependency notice on the admin page in WordPress 6.9.1 and later.
- Fix BuddyPress @mention filter corrupting Fediverse Followers and Following blocks.
- Fix cleanup jobs silently doing nothing on sites where purge retention options were not set.
- Fix comments on remote posts being incorrectly held in moderation.
- Fix double-encoded HTML entities in post titles on the Fediverse Stats dashboard.
- Fixed an issue where quote authorization stamps could reference unrelated posts.
- Fixed double-encoding of special characters in comment author names on updates.
- Fixed emoji shortcode replacement to handle special characters in emoji names correctly.
- Fix fatal error when other plugins hook into the user agent filter expecting two arguments.
- Fix Fediverse Preview showing the standard web view instead of the ActivityPub preview for draft posts.
- Fix OAuth authentication failing for local development clients using localhost subdomains.
- Fix performance regression from reply-exclusion filter by skipping it for queries targeting non-ActivityPub post types.
- Fix Reader feed failing to load with newer WordPress versions.
- Fix remote actor avatars getting stuck on broken URLs when the original image becomes unavailable.
- Fix Site Health check showing an empty error message when the WebFinger endpoint is not reachable.
- Fix the Fediverse profile "Joined" date showing the oldest post date instead of when the site started federating.
- Fix the Fediverse profile showing an inflated post count by excluding incoming comments from the total.
- Fix Update handler using stale local actor data instead of the activity payload
- Improved HTTP Signature validation for requests with a missing Date header.
- Only allow S256 as PKCE code challenge method for OAuth authorization.
- Prevent third-party plugin UI elements and scripts from appearing in federated content.
- Require signed peer requests for the followers synchronization endpoint per FEP-8fcf.
- Show a styled error page instead of raw technical output when an OAuth application cannot be reached during authorization.
- Strip private recipient fields from all outgoing activities to prevent leaking private audiences.
- Sync ActivityPub blog actor settings via Jetpack.
- Use ap_actor post ID for remote account IDs instead of remapping URI strings.
- Use safe HTTP request for signature retry to prevent requests to private IP ranges.
- Validate emoji updated timestamps before storing them.

### 8.0.2 - 2026-03-17
#### Security
- Prevent non-public posts (drafts, scheduled, pending review) from being accessible via ActivityPub.

### 8.0.1 - 2026-03-11
#### Changed
- Simplify the follow page block pattern to avoid duplicate headings and improve accessibility.

#### Fixed
- Fix dark sidebar colors appearing incorrectly with non-default admin color schemes.
- Fix Fediverse Reactions block not aligning with post content in block themes.
- Fix new posts being marked as modified on load, which prevented Gutenberg's starter pattern modal from appearing.

### 8.0.0 - 2026-03-04
#### Security
- Prevent private recipient lists from being shared when sending activities to other servers.

#### Added
- Add a help section to interaction dialogs explaining the Fediverse and why entering a profile is needed.
- Add a notice on the Settings page to easily switch from legacy template mode to automatic mode.
- Add a pre-publish suggestion that recommends a post format for better compatibility with media-focused Fediverse platforms.
- Add a Site Health check that warns when plugins are causing too many federation updates.
- Add backwards compatibility for the `ACTIVITYPUB_DISABLE_SIDELOADING` constant and `activitypub_sideloading_enabled` filter from version 7.9.1.
- Add bot account snippet that marks ActivityPub profiles as automated accounts, displaying a "BOT" badge on Mastodon and other Fediverse platforms.
- Add Cache namespace for remote media caching with CLI commands, improved MIME validation, and filter-based architecture.
- Add federation of video poster images set in the WordPress video block.
- Add Locale from Tags community snippet.
- Add optional Like and Boost action buttons to the Fediverse Reactions block, allowing visitors to interact with posts from their own server.
- Add pre-built Fediverse block patterns for easy profile, follow page, and sidebar setup.
- Add snippet for blockless fediverse reactions
- Add `wp activitypub fetch` CLI command for fetching remote URLs with signed HTTP requests.

#### Changed
- Improved active user counting for NodeInfo to include all federated content types and comments.
- Improve language map resolution to strictly follow the ActivityStreams spec.
- Superseded outbox activities are now removed instead of kept, reducing clutter in the outbox.
- The minimum required PHP version is now 7.4.

#### Fixed
- Accept incoming activities from servers that use standalone key objects for HTTP Signatures.
- Fix a crash on servers where WordPress uses FTP instead of direct file access for media caching.
- Fix a crash when receiving posts from certain federated platforms that send multilingual content.
- Fix automatic cleanup of old activities failing silently on sites with large numbers of outbox, inbox, or remote post items.
- Fix comment count to properly exclude likes, shares, and notes.
- Fix follow button redirect from Mastodon not being recognized.
- Fix modal overlay not covering the full screen on block themes.
- Fix outbox invalidation canceling pending Accept/Reject responses to QuoteRequests for the same post.
- Fix QuoteRequest handler to derive responding actor from post author instead of inbox recipient.
- Fix reactions block buttons inheriting theme background color on classic themes.
- Fix reactions block layout on small screens and remove unwanted button highlight when clicking action buttons.
- Fix signature verification rejecting valid requests that use lowercase algorithm names in the Digest header.
- Fix soft-deleted posts being served instead of a tombstone when the post is re-saved.
- Improve compatibility with federated services that use a URL reference for the actor's public key.
- Improve handling of all public audience identifiers when sending activities to followers and relays.

See full Changelog on [GitHub](https://github.com/Automattic/wordpress-activitypub/blob/trunk/CHANGELOG.md).

== Upgrade Notice ==

= 8.1.0 =

See your year on the Fediverse with the new Stats feature, bringing your highlights together in one simple view you can share anywhere.

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
