<?php
/**
 * Core Features Help Tab template.
 *
 * @package Activitypub
 */

use Activitypub\Collection\Actors;

use function Activitypub\user_can_activitypub;

$host   = wp_parse_url( home_url(), PHP_URL_HOST );
$author = 'username@' . $host;
if ( user_can_activitypub( get_current_user_id() ) ) {
	$author = Actors::get_by_id( get_current_user_id() )->get_webfinger();
}

$blog_user = 'blog@' . $host;
if ( user_can_activitypub( Actors::BLOG_USER_ID ) ) {
	$blog_user = Actors::get_by_id( Actors::BLOG_USER_ID )->get_webfinger();
}
?>

<h2><?php esc_html_e( 'User Accounts vs. Blog Accounts', 'activitypub' ); ?></h2>
<p><?php esc_html_e( 'Your WordPress site can participate in the Fediverse in two ways:', 'activitypub' ); ?></p>
<ul>
	<li>
		<?php
		printf(
			/* translators: %s is the user's ActivityPub username */
			esc_html__( 'Individual user accounts: Each author has their own Fediverse identity (%s).', 'activitypub' ),
			'<code>' . esc_html( $author ) . '</code>'
		);
		?>
	</li>
	<li>
		<?php
		printf(
			/* translators: %s is the blog's ActivityPub username */
			esc_html__( 'Whole blog account: The blog itself has a Fediverse identity (%s).', 'activitypub' ),
			'<code>' . esc_html( $blog_user ) . '</code>'
		);
		?>
	</li>
</ul>
<p><?php esc_html_e( 'User accounts are best when you want each author to have their own following and identity. The blog account is simpler and works well for single-author sites or when you want all content under one identity.', 'activitypub' ); ?></p>

<h2><?php esc_html_e( 'Publishing to the Fediverse', 'activitypub' ); ?></h2>
<p><?php esc_html_e( 'When you publish a post on your WordPress site, the ActivityPub plugin automatically shares it with your followers in the Fediverse. Your content appears in their feeds just like posts from other Fediverse platforms such as Mastodon or Pleroma.', 'activitypub' ); ?></p>
<p><?php esc_html_e( 'The plugin intelligently formats your content for the Fediverse, including featured images, excerpts, and links back to your original post.', 'activitypub' ); ?></p>
<p><?php esc_html_e( 'Before publishing, you can use the Fediverse Preview feature to see exactly how your post will appear to Fediverse users. This helps ensure your content looks great across different platforms. You can access this preview from the post editor sidebar.', 'activitypub' ); ?></p>

<h2><?php esc_html_e( 'Content Visibility Controls', 'activitypub' ); ?></h2>
<p><?php esc_html_e( 'The ActivityPub plugin gives you complete control over which content is shared to the Fediverse. By default, public posts are federated while private or password-protected posts remain exclusive to your WordPress site.', 'activitypub' ); ?></p>
<p><?php esc_html_e( 'In the WordPress editor, each post has visibility settings that determine whether it appears in the Fediverse. You can find these controls in the editor sidebar under &#8220;Fediverse > Visibility&#8221;. Options include &#8220;Public&#8221; (visible to everyone in the Fediverse), &#8220;Quiet Public&#8221; (doesn&#8217;t appear in public timelines), or &#8220;Do Not Federate&#8221; (keeps the post only on your WordPress site).', 'activitypub' ); ?></p>
<p><?php esc_html_e( 'You can also configure global settings to control which post types (posts, pages, custom post types) are federated by default. This gives you both site-wide control and per-post flexibility to manage exactly how your content is shared.', 'activitypub' ); ?></p>

<h2><?php esc_html_e( 'Receiving Interactions', 'activitypub' ); ?></h2>
<p><?php esc_html_e( 'One of the most powerful features of the ActivityPub plugin is its ability to receive and display interactions from across the Fediverse. When someone on Mastodon or another Fediverse platform comments on your post, their comment appears directly in your WordPress comments section, creating a seamless conversation between your blog and the wider Fediverse.', 'activitypub' ); ?></p>
<p><?php esc_html_e( 'These Fediverse comments integrate naturally with your existing WordPress comment system. You can moderate them just like regular comments, and any replies you make are automatically federated back to the original commenter, maintaining the conversation thread across platforms.', 'activitypub' ); ?></p>
<p><?php esc_html_e( 'Beyond comments, the plugin also tracks likes and shares (boosts) from Fediverse users. These interactions can provide valuable feedback and help you understand how your content is being received across the decentralized social web.', 'activitypub' ); ?></p>

<h2><?php esc_html_e( 'Mentions and Replies', 'activitypub' ); ?></h2>
<p><?php echo wp_kses( __( 'The ActivityPub plugin enables true cross-platform conversations by supporting mentions and replies. When writing a post, you can mention any Fediverse user by using their full address format. For example, typing <code>@username@domain.com</code> will create a mention that notifies that user, regardless of which Fediverse platform they use.', 'activitypub' ), array( 'code' => array() ) ); ?></p>
<p><?php echo wp_kses( __( 'Mentions use the format <code>@username@domain.com</code> and work just like mentions on other social platforms. The mentioned user receives a notification, and your post appears in their mentions timeline. This creates a direct connection between your WordPress site and users across the Fediverse.', 'activitypub' ), array( 'code' => array() ) ); ?></p>
<p><?php esc_html_e( 'Similarly, when someone mentions your Fediverse identity in their post, you&#8217;ll receive an email notification that you can respond to with a new post. This two-way communication bridge makes your WordPress site a full participant in Fediverse conversations.', 'activitypub' ); ?></p>
