=== ActivityPub ===
Contributors: automattic, pfefferle, mattwiebe, obenland, akirk, jeherve, mediaformat, nuriapena, cavalierlife, andremenrath
Tags: OStatus, fediverse, activitypub, activitystream
Requires at least: 6.4
Tested up to: 6.8
Stable tag: 5.9.0
Requires PHP: 7.2
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

	RedirectMatch "^\/\.well-known/(webfinger|nodeinfo)(.*)$" /blog/.well-known/$1$2

Where 'blog' is the path to the subdirectory at which your blog resides.

**Nginx**

Add the following to the site.conf in sites-available:

	location ~* /.well-known {
		allow all;
		try_files $uri $uri/ /blog/?$args;
	}

Where 'blog' is the path to the subdirectory at which your blog resides.

If you are running your blog in a subdirectory, but have a different [wp_siteurl](https://wordpress.org/documentation/article/giving-wordpress-its-own-directory/), you don't need the redirect, because the index.php will take care of that.

= What if you are running your blog behind a reverse proxy with Apache? =

If you are using a reverse proxy with Apache to run your host you may encounter that you are unable to have followers join the blog. This will occur because the proxy system rewrites the host headers to be the internal DNS name of your server, which the plugin then uses to attempt to sign the replies. The remote site attempting to follow your users is expecting the public DNS name on the replies. In these cases you will need to use the 'ProxyPreserveHost On' directive to ensure the external host name is passed to your internal host.

If you are using SSL between the proxy and internal host you may also need to `SSLProxyCheckPeerName off` if your internal host can not answer with the correct SSL name. This may present a security issue in some environments.

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

### 5.9.0 - 2025-05-14
#### Added
- ActivityPub embeds now support audios, videos, and up to 4 images.
- Added a check to make sure we only attempt to embed activity objects, when processing fallback embeds.
- Add setting to enable or disable how content is tailored for browsers and Fediverse services.
- Adjusted the plugin's default behavior based on the caching plugins installed.
- A guided onboarding flow after plugin activation to help users make key setup decisions and understand Fediverse concepts.
- Author profiles will cap the amount of extra fields they return to 20, to avoid response size errors in clients.
- Fediverse Preview in the Editor now also supports video and audio attachments.
- Guidance for configuring Surge to support ActivityPub caching.
- Help tab section explaining ActivityPub capabilities on the users page.
- Profile sections have been moved from the Welcome page to new Dashboard widgets for easier access.
- The ActivityPub blog news feed to WordPress dashboard.
- The Outbox now skips invalid items instead of trying to process them for output and encountering an error.

#### Changed
- Batch processing jobs can now be scheduled with individual hooks.
- Better error handling when other servers request Outbox items in the wrong format, and 404 pages now show correctly.
- Fediverse Previews in the Block Editor now show media items, even if the post has not been published yet.
- Hide interaction buttons in emails when the Classic Editor is used.
- Improve compatibility with third-party caching plugins by sending a `Vary` header.
- Much more comprehensive plugin documentation in the Help tab of ActivityPub Settings.
- NodeInfo endpoint response now correctly formats `localPosts` values.
- Reactions block heading now uses Core's heading block with all its customization options.
- Settings pages are now more mobile-friendly with more space and easier scrolling.
- The number of images shared to the Fediverse can now be chosen on a per-post basis.
- Updated default max attachment count to four, creating better-looking gallery grids for posts with 4 or more images.
- Use a dedicated hook for the "Dismiss Welcome Page Welcome" link.
- Use FEP-c180 schema for error responses.
- Use `Audio` and `Video` type for Attachments, instead of the very generic `Document` type.

#### Deprecated
- Deprecated `rest_activitypub_outbox_query` filter in favor of `activitypub_rest_outbox_query`.
  Deprecated `activitypub_outbox_post` action in favor of `activitypub_rest_outbox_post`.

#### Fixed
- Broken avatars in the Reactions and Follower block are now replaced with the default avatar.
- Email notifications for interactions with Brid.gy actors no longer trigger PHP Warnings.
- Improved support for users from more Fediverse platforms in email notifications.
- Improved the handling of Shares and Boosts.
- Issue preventing "Receive reblogs (boosts)" setting from being properly saved.
- Mention emails will no longer be sent for reply Activities.
- Prevent accidental follower removal by resetting errors properly.
- Properly remove retries schedules, with the invalidation of an Outbox-Item.
- The blog profile can no longer be queried when the blog actor option is disabled.

### 5.8.0 - 2025-04-24
#### Added
- An option to receive notification emails when an Actor was mentioned in the Fediverse.
- Enable direct linking to Help Tabs.
- Fallback embed support for Fediverse content that lacks native oEmbed responses.
- Support for all media types in the Mastodon Importer.

#### Changed
- Added WordPress disallowed list filtering to block unwanted ActivityPub interactions.
- Mastodon imports now support blocks, with automatic reply embedding for conversations.
- Tested and compatible with the latest version of WordPress.
- Updated design of new follower notification email and added meta information.
- Update DM email notification to include an embed display of the DM.
- Updated notification settings to be user-specific for more personalization.

#### Fixed
- Add support for Multisite Language Switcher
- Better check for an empty `headers` array key in the Signature class.
- Include user context in Global-Inbox actions.
- No more PHP warning when Mastodon Apps run out of posts to process.
- Reply links and popup modals are now properly translated for logged-out visitors.

### 5.7.0 - 2025-04-11
#### Added
- Advanced Settings tab, with special settings for advanced users.
- Check if pretty permalinks are enabled and recommend to use threaded comments.
- Reply block: show embeds where available.
- Support same-server domain migrations.
- Upgrade routine that removes any erroneously created extra field entries.

#### Changed
- Add option to enable/disable the "shared inbox" to the "Advanced Settings".
- Add option to enable/disable the `Vary` Header to the "Advanced Settings".
- Configure the "Follow Me" button to have a button-only mode.
- Importers are loaded on admin-specific hook.
- Improve the troubleshooting UI and show Site-Health stats in ActivityPub settings.
- Increased compatibility with Mobilizon and other platforms by improving signature verification for different key formats.

#### Fixed
- Ensure that an `Activity` has an `Actor` before adding it to the Outbox.
- Fixed some bugs and added additional information on the Debug tab of the Site-Health page.
- Follow-up to the reply block changes that makes sure Mastodon embeds are displayed in the editor.
- Outbox endpoint bug where non-numeric usernames caused errors when querying Outbox data.
- Show Site Health error if site uses old "Almost Pretty Permalinks" structure.
- Sites with comments from the Fediverse no longer create uncached extra fields posts that flood the Outbox.
- Transformers allow settings values to false again, a regression from 5.5.0.

### 5.6.1 - 2025-04-02
#### Fixed
- "Post Interactions" settings will now be saved to the options table.
- So not show `movedTo` attribute instead of setting it to `false` if empty.
- Use specified date format for `updated` field in Outbox-Activites.

### 5.6.0 - 2025-04-01
#### Added
- Added a Mastodon importer to move your Mastodon posts to your WordPress site.
- A default Extra-Field to do a little advertising for WordPress.
- Move: Differentiate between `internal` and 'external' Move.
- Redirect user to the welcome page after ActivityPub plugin is activated.
- The option to show/hide the "Welcome Page".
- User setting to enable/disable Likes and Reblogs

#### Changed
- Logged-out remote reply button markup to look closer to logged-in version.
- No longer federates `Delete` activities for posts that were not federated.
- OrderedCollection and OrderedCollectionPage behave closer to spec now.
- Outbox items now contain the full activity, not just activity objects.
- Standardized mentions to use usernames only in comments and posts.

#### Fixed
- Changelog entries: allow automating changelog entry generation from forks as well.
- Comments from Fediverse actors will now be purged as expected.
- Importing attachments no longer creates Outbox items for them.
- Improved readability in Mastodon Apps plugin string.
- No more PHP warnings when previewing posts without attachments.
- Outbox batch processing adheres to passed batch size.
- Permanently delete reactions that were `Undo` instead of trashing them.
- PHP warnings when scheduling post activities for an invalid post.
- PHP Warning when there's no actor information in comment activities.
- Prevent self-replies on local comments.
- Properly set `to` audience of `Activity` instead of changing the `Follow` Object.
- Run all Site-Health checks with the required headers and a valid signature.
- Set `updated` field for profile updates, otherwise the `Update`-`Activity` wouldn't be handled by Mastodon.
- Support multiple layers of nested Outbox activities when searching for the Object ID.
- The Custom-Avatar getter on WP.com.
- Use the $from account for the object in Move activity for external Moves
- Use the `$from` account for the object in Move activity for internal Moves
- Use `add_to_outbox` instead of the changed scheduler hooks.
- Use `JSON_UNESCAPED_SLASHES` because Mastodon seems to have problems with encoded URLs.
- `Scheduler::schedule_announce_activity` to handle Activities instead of Activity-Objects.

### 5.5.0 - 2025-03-19
#### Added
- Added "Enable Mastodon Apps" and "Event Bridge for ActivityPub" to the recommended plugins section.
- Added Constants to the Site-Health debug informations.
- Development environment: add Changelogger tool to environment dependencies.
- Development environment: allow contributors to specify a changelog entry directly from their Pull Request description.
- Documentation for migrating from a Mastodon instance to WordPress.
- Support for sending Activities to ActivityPub Relays, to improve discoverability of public content.

#### Changed
- Documentation: expand Pull Request process docs, and mention the new changelog process as well as the updated release process.
- Don't redirect @-name URLs to trailing slashed versions
- Improved and simplified Query code.
- Improved readability for actor mode setting.
- Improved title case for NodeInfo settings.
- Introduced utility function to determine actor type based on user ID.
- Outbox items only get sent to followers when there are any.
- Restricted modifications to settings if they are predefined as constants.
- The Welcome page now uses WordPress's Settings API and the classic design of the WP Admin.
- Uses two-digit version numbers in Outbox and NodeInfo responses.

#### Removed
- Our version of `sanitize_url()` was unused—use Core's `sanitize_url()` instead.

#### Fixed
- Ensured that Query::get_object_id() returns an ID instead of an Object.
- Fix a fatal error in the Preview when a post contains no (hash)tags.
- Fixed an issue with the Content Carousel and Blog Posts block: https://github.com/Automattic/wp-calypso/issues/101220
- Fixed default value for `activitypub_authorized_fetch` option.
- Follow-Me blocks now show the correct avatar on attachment pages.
- Images with the correct aspect ratio no longer get sent through the crop step again.
- No more PHP warnings when a header image gets cropped.
- PHP warnings when trying to process empty tags or image blocks without ID attributes.
- Properly re-added support for `Update` and `Delete` `Announce`ments.
- Updates to certain user meta fields did not trigger an Update activity.
- When viewing Reply Contexts, we'll now attribute the post to the blog user when the post author is disabled.

### 5.4.1 - 2025-03-04
#### Fixed
- Fixed transition handling of posts to ensure that `Create` and `Update` activities are properly processed.
- Show "full content" preview even if post is in still in draft mode.

### 5.4.0 - 2025-03-03
#### Added
- Upgrade script to fix Follower json representations with unescaped backslashes.
- Centralized place for sanitization functions.

#### Changed
- Bumped minimum required WordPress version to 6.4.
- Use a later hook for Posts to get published to the Outbox, to get sure all `post_meta`s and `taxonomy`s are set stored properly.
- Use webfinger as author email for comments from the Fediverse.
- Remove the special handling of comments from Enable Mastodon Apps.

#### Fixed
- Do not redirect `/@username` URLs to the API any more, to improve `AUTHORIZED_FETCH` handling.

### 5.3.2 - 2025-02-27
#### Fixed
- Remove `activitypub_reply_block` filter after Activity-JSON is rendered, to not affect the HTML representation.
- Remove `render_block_core/embed` filter after Activity-JSON is rendered, to not affect the HTML representation.

### 5.3.1 - 2025-02-26
#### Fixed
- Blog profile settings can be saved again without errors.
- Followers with backslashes in their descriptions no longer break their actor representation.

### 5.3.0 - 2025-02-25
#### Added
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

#### Changed
- Outbox now precesses the first batch of followers right away to avoid delays in processing new Activities.
- Post bulk edits no longer create Outbox items, unless author or post status change.
- Properly process `Update` activities on profiles and ensure all properties of a followed person are updated accordingly.
- Outbox processing accounts for shared inboxes again.
- Improved check for `?activitypub` query-var.
- Rewrite rules: be more specific in author rewrite rules to avoid conflicts on sites that use the "@author" pattern in their permalinks.
- Deprecate the `activitypub_post_locale` filter in favor of the `activitypub_locale` filter.

#### Fixed
- The Outbox purging routine no longer is limited to deleting 5 items at a time.
- Ellipses now display correctly in notification emails for Likes and Reposts.
- Send Update-Activity when "Actor-Mode" is changed.
- Added delay to `Announce` Activity from the Blog-Actor, to not have race conditions.
- `Actor` validation in several REST API endpoints.
- Bring back the `activitypub_post_locale` filter to allow overriding the post's locale.

### 5.2.0 - 2025-02-13
#### Added
- Batch Outbox-Processing.
- Outbox processed events get logged in Stream and show any errors returned from inboxes.
- Outbox items older than 6 months will be purged to avoid performance issues.
- REST API endpoints for likes and shares.

#### Changed
- Increased probability of Outbox items being processed with the correct author.
- Enabled querying of Outbox posts through the REST API to improve troubleshooting and debugging.
- Updated terminology to be client-neutral in the Federated Reply block.

#### Fixed
- Fixed an issue where the outbox could not send object types other than `Base_Object` (introduced in 5.0.0).
- Enforce 200 status header for valid ActivityPub requests.
- `object_id_to_comment` returns a commment now, even if there are more than one matching comment in the DB.
- Integration of content-visibility setup in the block editor.
- Update CLI commands to the new scheduler refactorings.
- Do not add an audience to the Actor-Profiles.
- `Activity::set_object` falsely overwrites the Activity-ID with a default.

### 5.1.0 - 2025-02-06
#### Added
- Cleanup of option values when the plugin is uninstalled.
- Third-party plugins can filter settings tabs to add their own settings pages for ActivityPub.
- Show ActivityPub preview in row actions when Block Editor is enabled but not used for the post type.

#### Changed
- Manually granting `activitypub` cap no longer requires the receiving user to have `publish_post`.
- Allow omitting replies in ActivityPub representations instead of setting them as empty.
- Allow Base Transformer to handle WP_Term objects for transformation.
- Improved Query extensibility for third party plugins.

#### Fixed
- Negotiation of ActivityPub requests for custom post types when queried by the ActivityPub ID.
- Avoid PHP warnings when using Debug mode and when the `actor` is not set.
- No longer creates Outbox items when importing content/users.
- Fix NodeInfo 2.0 URL to be HTTP instead of HTTPS.

### 5.0.0 - 2025-02-03
#### Changed
- Improved content negotiation and AUTHORIZED_FETCH support for third-party plugins.
- Moved password check to `is_post_disabled` function.

#### Fixed
- Handle deletes from remote servers that leave behind an accessible Tombstone object.
- No longer parses tags for post types that don't support Activitypub.
- rel attribute will now contain no more than one "me" value.

See full Changelog on [GitHub](https://github.com/Automattic/wordpress-activitypub/blob/trunk/CHANGELOG.md).

== Upgrade Notice ==

= 5.9.0 =

Experience our new onboarding flow and improved help docs—making it easier than ever to connect your site to the Fediverse!

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
