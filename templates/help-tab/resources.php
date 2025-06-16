<?php
/**
 * Resources Help Tab template.
 *
 * @package Activitypub
 */

$anchor_html = array(
	'a' => array(
		'href'   => true,
		'target' => true,
	),
);
?>

<h2><?php esc_html_e( 'Official Resources', 'activitypub' ); ?></h2>
<ul>
	<li><?php echo wp_kses( __( '<a href="https://wordpress.org/plugins/activitypub/" target="_blank">WordPress.org Plugin Page</a> - Official plugin listing with documentation.', 'activitypub' ), $anchor_html ); ?></li>
	<li><?php echo wp_kses( __( '<a href="https://github.com/automattic/wordpress-activitypub" target="_blank">GitHub Repository</a> - Source code and development.', 'activitypub' ), $anchor_html ); ?></li>
	<li><?php echo wp_kses( __( '<a href="https://github.com/automattic/wordpress-activitypub/releases" target="_blank">Release Notes</a> - Latest changes and updates.', 'activitypub' ), $anchor_html ); ?></li>
</ul>

<h2><?php esc_html_e( 'Community Support', 'activitypub' ); ?></h2>
<ul>
	<li><?php echo wp_kses( __( '<a href="https://wordpress.org/support/plugin/activitypub/" target="_blank">WordPress.org Support Forums</a> - Get help from the community.', 'activitypub' ), $anchor_html ); ?></li>
	<li><?php echo wp_kses( __( '<a href="https://github.com/automattic/wordpress-activitypub/issues" target="_blank">GitHub Issues</a> - Report bugs or suggest features.', 'activitypub' ), $anchor_html ); ?></li>
</ul>

<h2><?php esc_html_e( 'Complementary Plugins', 'activitypub' ); ?></h2>
<ul>
	<li><?php echo wp_kses( __( '<a href="https://wordpress.org/plugins/hum/" target="_blank">Hum</a> - Enhance shortlinks for better sharing.', 'activitypub' ), $anchor_html ); ?></li>
	<li><?php echo wp_kses( __( '<a href="https://wordpress.org/plugins/webmention/" target="_blank">Webmention</a> - Add Webmention support for additional interactions.', 'activitypub' ), $anchor_html ); ?></li>
</ul>

<h2><?php esc_html_e( 'Fediverse Resources', 'activitypub' ); ?></h2>
<ul>
	<li><?php echo wp_kses( __( '<a href="https://fediverse.party/" target="_blank">Fediverse.Party</a> - Introduction to the Fediverse and its platforms.', 'activitypub' ), $anchor_html ); ?></li>
	<li><?php echo wp_kses( __( '<a href="https://joinmastodon.org/" target="_blank">Join Mastodon</a> - Information about Mastodon, a popular Fediverse platform.', 'activitypub' ), $anchor_html ); ?></li>
	<li><?php echo wp_kses( __( '<a href="https://w3c.github.io/activitypub/" target="_blank">ActivityPub Specification</a> - The official W3C specification.', 'activitypub' ), $anchor_html ); ?></li>
</ul>

<h2><?php esc_html_e( 'Further Reading', 'activitypub' ); ?></h2>
<ul>
	<li><?php echo wp_kses( __( '<a href="https://indieweb.org/" target="_blank">IndieWeb</a> - Movement focused on owning your content and identity online.', 'activitypub' ), $anchor_html ); ?></li>
	<li><?php echo wp_kses( __( '<a href="https://webfinger.net/" target="_blank">WebFinger Protocol</a> - More information about WebFinger.', 'activitypub' ), $anchor_html ); ?></li>
</ul>
