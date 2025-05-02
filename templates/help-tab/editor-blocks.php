<?php
/**
 * Editor Blocks Help Tab template.
 *
 * @package Activitypub
 */

?>

<h2><?php esc_html_e( 'Introduction to ActivityPub Blocks', 'activitypub' ); ?></h2>
<p><?php esc_html_e( 'The plugin provides custom blocks for the WordPress Block Editor (Gutenberg) that enhance your Fediverse presence and make it easier to interact with the Fediverse.', 'activitypub' ); ?></p>

<h2><?php esc_html_e( 'Follow Me on the Fediverse', 'activitypub' ); ?></h2>
<p><?php esc_html_e( 'This block displays your Fediverse profile so that visitors can follow you directly from your WordPress site.', 'activitypub' ); ?></p>
<figure class="activitypub-block-screenshot">
	<img src="<?php echo esc_url( ACTIVITYPUB_PLUGIN_URL . 'assets/img/follow-me.png' ); ?>" alt="<?php esc_attr_e( 'Follow Me on the Fediverse block', 'activitypub' ); ?>" width="600" height="auto">
	<figcaption class="activitypub-screenshot-caption"><?php esc_html_e( 'The Follow Me block showing both profile information and follow button.', 'activitypub' ); ?></figcaption>
</figure>
<h4><?php esc_html_e( 'Usage Tips', 'activitypub' ); ?></h4>
<p><?php esc_html_e( 'Place this block in your sidebar, footer, or about page to make it easy for visitors to follow you on the Fediverse. The button-only option works well in compact spaces, while the full profile display provides more context about your Fediverse presence.', 'activitypub' ); ?></p>

<h2><?php esc_html_e( 'Fediverse Followers', 'activitypub' ); ?></h2>
<p><?php esc_html_e( 'This block displays your followers from the Fediverse on your website, showcasing your community and reach across decentralized networks.', 'activitypub' ); ?></p>
<figure class="activitypub-block-screenshot">
	<img src="<?php echo esc_url( ACTIVITYPUB_PLUGIN_URL . 'assets/img/followers.png' ); ?>" alt="<?php esc_attr_e( 'Fediverse Followers block', 'activitypub' ); ?>" width="600" height="auto">
	<figcaption class="activitypub-screenshot-caption"><?php esc_html_e( 'The Followers block displaying a list of Fediverse followers with pagination.', 'activitypub' ); ?></figcaption>
</figure>
<h4><?php esc_html_e( 'Usage Tips', 'activitypub' ); ?></h4>
<p><?php esc_html_e( 'This block works well on community pages, about pages, or sidebars. The compact style is ideal for sidebars with limited space, while the Lines style provides clear visual separation between followers in wider layouts.', 'activitypub' ); ?></p>
<p><?php esc_html_e( 'The block includes pagination controls when you have more followers than the per-page setting, allowing visitors to browse through all your followers.', 'activitypub' ); ?></p>

<h2><?php esc_html_e( 'Fediverse Reactions', 'activitypub' ); ?></h2>
<p><?php esc_html_e( 'This block displays likes and reposts from the Fediverse for your content, showing engagement metrics from across federated networks.', 'activitypub' ); ?></p>
<figure class="activitypub-block-screenshot">
	<img src="<?php echo esc_url( ACTIVITYPUB_PLUGIN_URL . 'assets/img/reactions.png' ); ?>" alt="<?php esc_attr_e( 'Fediverse Reactions block', 'activitypub' ); ?>" width="600" height="auto">
	<figcaption class="activitypub-screenshot-caption"><?php esc_html_e( 'The Reactions block showing likes and reposts from the Fediverse.', 'activitypub' ); ?></figcaption>
</figure>
<h4><?php esc_html_e( 'How It Works', 'activitypub' ); ?></h4>
<p><?php esc_html_e( 'The Reactions block dynamically fetches and displays likes and reposts (boosts) that your content receives from across the Fediverse. It updates automatically as new reactions come in, providing real-time feedback on how your content is being received.', 'activitypub' ); ?></p>
<h4><?php esc_html_e( 'Usage Tips', 'activitypub' ); ?></h4>
<p><?php esc_html_e( 'This block provides social proof by showing how your content is being received across the Fediverse. It works best at the end of posts or pages where it can display engagement metrics without interrupting the content flow.', 'activitypub' ); ?></p>

<h2><?php esc_html_e( 'Federated Reply', 'activitypub' ); ?></h2>
<p><?php esc_html_e( 'This block allows you to respond to posts, notes, videos, and other content on the Fediverse directly within your WordPress posts.', 'activitypub' ); ?></p>
<figure class="activitypub-block-screenshot">
	<img src="<?php echo esc_url( ACTIVITYPUB_PLUGIN_URL . 'assets/img/reply.png' ); ?>" alt="<?php esc_attr_e( 'Federated Reply block', 'activitypub' ); ?>" width="600" height="auto">
	<figcaption class="activitypub-screenshot-caption"><?php esc_html_e( 'The Federated Reply block with embedded Fediverse content and reply interface.', 'activitypub' ); ?></figcaption>
</figure>
<h4><?php esc_html_e( 'How It Works', 'activitypub' ); ?></h4>
<p><?php esc_html_e( 'When you add this block to your post and provide a Fediverse URL, the plugin will:', 'activitypub' ); ?></p>
<ol>
	<li><?php esc_html_e( 'Fetch and optionally display the original content you&#8217;re replying to.', 'activitypub' ); ?></li>
	<li><?php esc_html_e( 'When your post is published, send your reply to the Fediverse.', 'activitypub' ); ?></li>
	<li><?php esc_html_e( 'Create a proper threaded reply that will appear in the original post&#8217;s thread.', 'activitypub' ); ?></li>
</ol>
<h4><?php esc_html_e( 'Important Notes', 'activitypub' ); ?></h4>
<p><?php esc_html_e( 'This block only works with URLs from federated social networks. URLs from non-federated platforms may not function as expected. Your reply will be published to the Fediverse when your WordPress post is published.', 'activitypub' ); ?></p>
<h4><?php esc_html_e( 'Usage Tips', 'activitypub' ); ?></h4>
<p><?php esc_html_e( 'Use this block to create responses to Fediverse discussions. It&#8217;s perfect for bloggers who want to participate in Fediverse conversations while maintaining their content on their own WordPress site.', 'activitypub' ); ?></p>
<h3><?php esc_html_e( 'Reply Bookmarklet', 'activitypub' ); ?></h3>
<p><?php esc_html_e( 'In addition to the Reply block, the ActivityPub plugin offers a Reply bookmarklet for your browser. This tool lets you quickly reply to any ActivityPub-enabled post on the web, even outside your own site. When you click the bookmarklet while viewing a post on another Fediverse site, you&#8217;ll be taken to your WordPress editor with a reply draft ready to go.', 'activitypub' ); ?></p>
<p>
	<?php
	printf(
		/* translators: The host (domain) of the Blog. */
		esc_html__( 'To install the Reply bookmarklet, visit the Tools page in your WordPress dashboard, find the &#8220;Fediverse Bookmarklet&#8221; section, and drag the &#8220;Reply from %s&#8221; button to your bookmarks bar. You can also copy the provided code to create a new bookmark manually.', 'activitypub' ),
		esc_attr( wp_parse_url( home_url(), PHP_URL_HOST ) )
	);
	?>
</p>
<p>
	<?php
	printf(
		wp_kses(
			/* translators: %s: The Tools page URL. */
			__( 'Get the Reply bookmarklet from the <a href="%s">Tools page</a>.', 'activitypub' ),
			array(
				'a' => array(
					'href'   => true,
					'target' => true,
				),
			)
		),
		esc_url( admin_url( 'tools.php' ) )
	);
	?>
</p>

<h2><?php esc_html_e( 'General Usage Instructions', 'activitypub' ); ?></h2>
<p><?php esc_html_e( 'To use any of these blocks:', 'activitypub' ); ?></p>
<ol>
	<li><?php esc_html_e( 'Open the Block Editor when creating or editing a post/page.', 'activitypub' ); ?></li>
	<li><?php esc_html_e( 'Click the &#8220;+&#8221; button to add a new block.', 'activitypub' ); ?></li>
	<li><?php esc_html_e( 'Search for &#8220;ActivityPub&#8221; or the specific block name.', 'activitypub' ); ?></li>
	<li><?php esc_html_e( 'Select the desired block and configure its settings in the block sidebar.', 'activitypub' ); ?></li>
</ol>
<p><?php esc_html_e( 'These blocks help bridge the gap between your WordPress site and the Fediverse, enabling better integration and engagement with decentralized social networks.', 'activitypub' ); ?></p>
