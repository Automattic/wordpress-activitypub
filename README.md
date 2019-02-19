# ActivityPub #
**Contributors:** [pfefferle](https://profiles.wordpress.org/pfefferle)  
**Donate link:** https://notiz.blog/donate/  
**Tags:** OStatus, fediverse, activitypub, activitystream  
**Requires at least:** 4.7  
**Tested up to:** 5.1  
**Stable tag:** 0.4.1  
**Requires PHP:** 5.6  
**License:** MIT  
**License URI:** http://opensource.org/licenses/MIT  

The ActivityPub protocol is a decentralized social networking protocol based upon the ActivityStreams 2.0 data format.

## Description ##

This is **BETA** software, see the FAQ to see the current feature set or rather what is still planned.

The plugin implements the ActivityPub protocol for your Blog. Your readers will be able to follow your Blogposts on Mastodon and other Federated Plattforms that support ActivityPub.

## Frequently Asked Questions ##

### What is the status of this plugin? ###

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

### What is "ActivityPub for WordPress" ###

*ActivityPub for WordPress* extends WordPress with some fediverse features, but it does not compete with plattforms like Friendi.ca or Mastodon. If you want to run a **decentralized social network**, please use [Mastodon](https://joinmastodon.org/) or [GNU.social](https://gnu.io/social/).

### What are the differences between this plugin and Pterotype? ###

**Compatibility**

*ActivityPub for WordPress* is compatible with OStatus and IndieWeb plugin suites. *Pterotype* is incompatible with the standalone [WebFinger plugin](https://wordpress.org/plugins/webfinger/) plugin, so it can't be run together with OStatus.

**Custom tables**

*Pterotype* creates/uses a bunch of custom tables, *ActivityPub for WordPress* only uses the native tables and adds as few meta data as possible.

## Changelog ##

Project maintained on github at [pfefferle/wordpress-activitypub](https://github.com/pfefferle/wordpress-activitypub).

### 0.4.1 ###

* finally fixed contact list

### 0.4.0 ###

* added settings to enable/disable hashtag support
* fixed follower list
* send activities only for new posts, otherwise send updates

### 0.3.2 ###

* added "followers" endpoint
* change activity content from blog 'excerpt' to blog 'content'

### 0.3.1 ###

* better json encoding

### 0.3.0 ###

* basic hashtag support
* temporarily deactived likes and boosts
* added support for actor objects
* fixed encoding issue

### 0.2.1 ###

* customizable backlink (permalink or shorturl)
* show profile-identifiers also on profile settings

### 0.2.0 ###

* added option to switch between content and excerpt
* removed html and duplicateded new-lines

### 0.1.1 ###

* fixed "excerpt" in AS JSON
* added settings for the activity-summary and for the activity-object-type

### 0.1.0 ###

* added basic WebFinger support
* added basic NodeInfo support
* fully functional "follow" activity
* send new posts to your followers
* receive comments from your followers

### 0.0.2 ###

* refactorins
* functional inbox
* nicer profile views

### 0.0.1 ###

* initial

## Installation ##

Follow the normal instructions for [installing WordPress plugins](https://codex.wordpress.org/Managing_Plugins#Installing_Plugins).

### Automatic Plugin Installation ###

To add a WordPress Plugin using the [built-in plugin installer](https://codex.wordpress.org/Administration_Screens#Add_New_Plugins):

1. Go to [Plugins](https://codex.wordpress.org/Administration_Screens#Plugins) > [Add New](https://codex.wordpress.org/Plugins_Add_New_Screen).
1. Type "`activitypub`" into the **Search Plugins** box.
1. Find the WordPress Plugin you wish to install.
    1. Click **Details** for more information about the Plugin and instructions you may wish to print or save to help setup the Plugin.
    1. Click **Install Now** to install the WordPress Plugin.
1. The resulting installation screen will list the installation as successful or note any problems during the install.
1. If successful, click **Activate Plugin** to activate it, or **Return to Plugin Installer** for further actions.

### Manual Plugin Installation ###

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
