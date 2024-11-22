# ActivityPub

This is the [ActivityPub plugin](https://wordpress.org/plugins/activitypub/) repo.

Enter the fediverse with **ActivityPub**, broadcasting your blog to a wider audience! Attract followers, deliver updates, and receive comments from a diverse user base of **ActivityPub**-compliant platforms.

## Demo

You can test out the plugin (settings) with [WordPress Playground](https://wordpress.org/plugins/activitypub/?preview=1).

> [!NOTE]
> It may take up to 15 minutes or so for the new post to show up in your federated feed. This is because the messages are sent to the federated platforms using a delayed cron. This avoids breaking the publishing process for those cases where users might have lots of followers. So please don’t assume that just because you didn’t see it show up right away that something is broken. Give it some time. In most cases, it will show up within a few minutes, and you’ll know everything is working as expected.

## Frequently Asked Questions ##

### tl;dr ###

This plugin connects your WordPress blog to popular social platforms like Mastodon, making your posts more accessible to a wider audience. Once installed, your blog can be followed by users on these platforms, allowing them to receive your new posts in their feeds.

### What is the status of this plugin? ###

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

### What is "ActivityPub for WordPress" ###

*ActivityPub for WordPress* extends WordPress with some Fediverse features, but it does not compete with platforms like Friendica or Mastodon. If you want to run a **decentralized social network**, please use [Mastodon](https://joinmastodon.org/) or [GNU social](https://gnusocial.network/).

### What if you are running your blog in a subdirectory? ###

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

### What if you are running your blog in a subdirectory? ###

If you are running your blog in a subdirectory, but have a different [wp_siteurl](https://wordpress.org/documentation/article/giving-wordpress-its-own-directory/), you don't need the redirect, because the index.php will take care of that.

### What if you are running your blog behind a reverse proxy with Apache? ###

If you are using a reverse proxy with Apache to run your host you may encounter that you are unable to have followers join the blog. This will occur because the proxy system rewrites the host headers to be the internal DNS name of your server, which the plugin then uses to attempt to sign the replies. The remote site attempting to follow your users is expecting the public DNS name on the replies. In these cases you will need to use the 'ProxyPreserveHost On' directive to ensure the external host name is passed to your internal host.

If you are using SSL between the proxy and internal host you may also need to `SSLProxyCheckPeerName off` if your internal host can not answer with the correct SSL name. This may present a security issue in some environments.

### Constants ###

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

### Where can you manage your followers? ###

If you have activated the blog user, you will find the list of his followers in the settings under `/wp-admin/options-general.php?page=activitypub&tab=followers`.

The followers of a user can be found in the menu under "Users" -> "Followers" or under `wp-admin/users.php?page=activitypub-followers-list`.

For reasons of data protection, it is not possible to see the followers of other users.

## Screenshots ##

1. The "Follow me"-Block in the Block-Editor
2. The "Followers"-Block in the Block-Editor
3. The "Federated Reply"-Block in the Block-Editor
4. A "Federated Reply" in a Post
5. A Blog-Profile on Mastodon

## Changelog ##

### Dev ###

* Added: A `pre_activitypub_get_upload_baseurl` filter
* Added: GitHub action to enforce Changelog updates.
* Improved: Outsource Constants to a separate file

### 4.2.1 ###

* Added: Mastodon Apps status provider
* Improved: Image-Handling
* Improved: Have better checks if audience should be set or not
* Fixed: Don't overwrite an existing `wp-tests-config.php`
* Fixed: PHPCS for phpunit files

### 4.2.0 ###

* Added: Unit tests for the `ActivityPub\Transformer\Post` class
* Improved: Reuse constants once they're defined
* Improved: "FEP-b2b8: Long-form Text" support
* Improved: Admin notice for plain permalink settings is more user-friendly and actionable
* Improved: Post-Formats support
* Fixed: Do not display ActivityPub's user sub-menus to users who do not have the capabilities of writing posts.
* Fixed: Proper margins for notices and font size for page title in settings screen.
* Fixed: Ensure that `?author=0` resolves to blog user

### 4.1.1 ###

* Fixed: Only revert to URL if there is one
* Fixed: Migration

### 4.1.0 ###

* Added: Add custom Preview for "Fediverse"
* Added: Support `comment_previously_approved` setting
* Fixed: Hide sticky posts that are not public
* Improved: `activity_handle_undo` action
* Improved: Add title to content if post is a `Note`
* Improved: Fallback to blog-user if user is disabled

### 4.0.2 ###

* Fixed: Do not federate "Local" posts
* Improved: Help-text for Content-Warning box

### 4.0.1 ###

* Fixed: Missing URL-Param handling in REST API
* Fixed: Seriously Simple Podcasting integration
* Fixed: Multiple small fixes
* Improved: Provide contextual fallback for dynamic blocks

### 4.0.0 ###
=======
> [WordPress Playground](https://wordpress.org/playground/) is the platform that lets you run WordPress instantly on any device without a host. It’s your place to build, experiment, test, and grow.

## Documentation

WIP.

## Federation

ActivityPub is a protocol for federated social networks, enabling communication between different platforms. For details on what the plugin supports, refer to the [FEDERATION.md](.FEDERATION.md) file.

## Support

If you need help, [check out the support forums on WordPress.org](https://wordpress.org/support/plugin/activitypub/).

## Contribute

Thank you for thinking about contributing to the ActivityPub plugin! If you're unsure of anything, feel free to submit an issue or pull request on any topic. The worst that can happen is that you'll be politely directed to the best location to ask your question or to change something in your pull request. There are a variety of options for how you can help:

* Write and submit patches.
* [Discuss new features and enhancements](https://github.com/Automattic/wordpress-activitypub/discussions).
* If you found a bug, [file a report here](https://github.com/Automattic/wordpress-activitypub/issues/new?q=sort%3Aupdated-desc+is%3Aissue+is%3Aopen&template=bug_report.yml).
* [Translate the ActivityPub plugin in your language](https://translate.wordpress.org/projects/wp-plugins/activitypub/).

To clarify these expectations, we have adopted the code of conduct defined by the Contributor Covenant. [It can be read in full here](./CODE_OF_CONDUCT.md).

## Security

Need to report a security vulnerability? Go to https://automattic.com/security/ or directly to our security bug bounty site https://hackerone.com/automattic.

You can find more information on reporting security vulnerabilities in our [SECURITY.md](./SECURITY.md) file.

## License

The ActivityPub plugin is licensed under the [MIT license](./LICENSE).

## Join us!

Interested in working on awesome open-source code all day? [Join us](https://automattic.com/work-with-us/)!
