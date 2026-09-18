# Keep the Featured Image out of the Fediverse

Stops the featured image from being federated, so it does not take a slot from the images that are actually in the post.

The transformer lists the featured image first, ahead of anything found in the content. With a limit on how many attachments a post may carry, that means the featured image takes one of those slots and the last image in the post falls off the end. For a site that gives every post a featured image for search results, that image often carries no meaning in a Fediverse timeline.

Two things are removed:

- the featured image is dropped from the attachments, which frees the slot
- the `image` property is dropped from `Note` and `Article` objects, which is the preview some servers show for a link

The actor icon is untouched. It is filled from the featured image too, falling back to the site icon, and it is a small avatar rather than a media attachment.

## Installation

Copy this folder to `wp-content/plugins/` and activate **Keep the Featured Image out of the Fediverse** from the WordPress admin, or copy `no-featured-image.php` to `wp-content/mu-plugins/` for automatic activation.

## Requirements

- WordPress 5.9+
- PHP 7.4+
- [ActivityPub](https://wordpress.org/plugins/activitypub/) plugin

## Keeping the preview image

To free the attachment slot but keep the link preview, delete the `remove_featured_image_property()` function and its `add_filter()` call. The featured image then stays in the `image` property and is only left out of the media.

## Origin

Requested in [#3655](https://github.com/Automattic/wordpress-activitypub/issues/3655), for a site using post formats where every post has a featured image for search results and a quote post federates a quote symbol.
