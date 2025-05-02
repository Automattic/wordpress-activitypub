<?php
/**
 * Getting Started Help Tab template.
 *
 * @package Activitypub
 */

?>

<h2><?php esc_html_e( 'What is the Fediverse?', 'activitypub' ); ?></h2>
<p><?php esc_html_e( 'The Fediverse is a collection of social networks that talk to each other, similar to how email works between different providers. It allows people on different platforms to follow and interact with each other, regardless of which service they use.', 'activitypub' ); ?></p>
<p><?php esc_html_e( 'Unlike traditional social media where everyone must use the same service (like Twitter or Facebook), the Fediverse lets you choose where your content lives while still reaching people across many different platforms.', 'activitypub' ); ?></p>
<p style="position: relative; padding-top: 56.25%;">
	<iframe title="<?php echo esc_attr__( 'What is the Fediverse?', 'activitypub' ); ?>" width="100%" height="100%" src="https://framatube.org/videos/embed/9dRFC6Ya11NCVeYKn8ZhiD?subtitle=<?php echo esc_attr( substr( get_locale(), 0, 2 ) ); ?>" frameborder="0" allowfullscreen="" sandbox="allow-same-origin allow-scripts allow-popups allow-forms" style="position: absolute; inset: 0;"></iframe>
</p>

<h2><?php esc_html_e( 'How WordPress fits into the Fediverse', 'activitypub' ); ?></h2>
<p><?php esc_html_e( 'This plugin turns your WordPress blog into part of the Fediverse. When activated, your blog becomes a Fediverse &#8220;instance&#8221; that can interact with other platforms like Mastodon.', 'activitypub' ); ?></p>
<p><?php esc_html_e( 'Your WordPress posts can be followed by people on Mastodon and other Fediverse platforms. Comments, likes, and shares from these platforms can appear on your WordPress site.', 'activitypub' ); ?></p>
<p><?php esc_html_e( 'The plugin supports two modes: individual user accounts (each author has their own Fediverse identity) or a whole-blog account (the blog itself has a Fediverse identity).', 'activitypub' ); ?></p>

<h2><?php esc_html_e( 'What to expect when federating', 'activitypub' ); ?></h2>
<p><?php esc_html_e( 'When your content federates to the Fediverse:', 'activitypub' ); ?></p>
<ul>
	<li><?php esc_html_e( 'Your posts will appear in the feeds of people who follow you.', 'activitypub' ); ?></li>
	<li><?php esc_html_e( 'People can comment, like, and share your posts from their Fediverse accounts.', 'activitypub' ); ?></li>
	<li><?php esc_html_e( 'Your featured images, excerpts, and other post elements will be included.', 'activitypub' ); ?></li>
	<li><?php esc_html_e( 'Building a following takes time, just like on any social platform.', 'activitypub' ); ?></li>
</ul>
<p><?php esc_html_e( 'Remember that public posts are truly public in the Fediverse - they can be seen by anyone on any connected platform.', 'activitypub' ); ?></p>
