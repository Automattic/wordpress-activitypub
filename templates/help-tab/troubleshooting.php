<?php
/**
 * Troubleshooting Help Tab template.
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

<h2><?php esc_html_e( 'Common Issues and Solutions', 'activitypub' ); ?></h2>
<dl>
	<dt><?php esc_html_e( 'My posts aren&#8217;t appearing in the Fediverse', 'activitypub' ); ?></dt>
	<dd><?php esc_html_e( 'Check that federation is enabled for your user or blog, verify the post type is set to be federated, and ensure the post is public. Also verify that your site is accessible from the public internet.', 'activitypub' ); ?></dd>
	<dt><?php esc_html_e( 'Fediverse users can&#8217;t follow my account', 'activitypub' ); ?></dt>
	<dd><?php esc_html_e( 'Make sure your WebFinger endpoint is accessible. Try searching for your full username (user@yourdomain.com) from a Fediverse account. Check that your server allows the necessary API requests.', 'activitypub' ); ?></dd>
	<dt><?php esc_html_e( 'Images aren&#8217;t displaying properly', 'activitypub' ); ?></dt>
	<dd><?php esc_html_e( 'Verify that your images are publicly accessible. Check image size limits on the receiving platforms. Consider using different image sizes in your templates.', 'activitypub' ); ?></dd>
	<dt><?php esc_html_e( 'Comments from the Fediverse aren&#8217;t showing up', 'activitypub' ); ?></dt>
	<dd><?php esc_html_e( 'Check your WordPress comment moderation settings. Verify that your inbox endpoint is accessible. Look for any error messages in your logs.', 'activitypub' ); ?></dd>
</dl>

<h2><?php esc_html_e( 'Debugging Federation Issues', 'activitypub' ); ?></h2>
<p><?php esc_html_e( 'To verify your WordPress site is properly federating:', 'activitypub' ); ?></p>
<ol>
	<li><?php esc_html_e( 'Test your WebFinger endpoint by visiting yourdomain.com/.well-known/webfinger?resource=acct:username@yourdomain.com.', 'activitypub' ); ?></li>
	<li><?php esc_html_e( 'Check that your ActivityPub endpoints are accessible.', 'activitypub' ); ?></li>
	<li><?php esc_html_e( 'Try following your account from a Fediverse account.', 'activitypub' ); ?></li>
	<li><?php esc_html_e( 'Publish a test post and verify it appears for followers.', 'activitypub' ); ?></li>
</ol>

<h2><?php esc_html_e( 'Understanding Error Messages', 'activitypub' ); ?></h2>
<p><?php esc_html_e( 'Common error messages you might encounter:', 'activitypub' ); ?></p>
<ul>
	<li><?php esc_html_e( 'WebFinger resource not found: Your WebFinger endpoint isn&#8217;t configured correctly or the username doesn&#8217;t exist.', 'activitypub' ); ?></li>
	<li><?php esc_html_e( 'Unable to deliver to inbox: The receiving server couldn&#8217;t be reached or rejected the message.', 'activitypub' ); ?></li>
	<li><?php esc_html_e( 'Signature verification failed: Authentication issues between servers.', 'activitypub' ); ?></li>
</ul>

<h2><?php esc_html_e( 'Getting Help', 'activitypub' ); ?></h2>
<p><?php esc_html_e( 'If you&#8217;re still having issues:', 'activitypub' ); ?></p>
<ul>
	<li><?php echo wp_kses( __( 'Check the <a href="https://wordpress.org/support/plugin/activitypub/" target="_blank">support forum</a> for similar issues.', 'activitypub' ), $anchor_html ); ?></li>
	<li><?php echo wp_kses( __( 'Report bugs on <a href="https://github.com/automattic/wordpress-activitypub/issues" target="_blank">GitHub</a> with detailed information.', 'activitypub' ), $anchor_html ); ?></li>
	<li><?php esc_html_e( 'Include your WordPress and PHP versions, along with any error messages when seeking help.', 'activitypub' ); ?></li>
</ul>
