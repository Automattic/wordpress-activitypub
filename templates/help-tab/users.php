<?php
/**
 * Users Help Tab template.
 *
 * @package Activitypub
 */

?>
<h2><?php esc_html_e( 'Managing ActivityPub Capabilities', 'activitypub' ); ?></h2>

<p><?php esc_html_e( 'Use the bulk actions on this page to control which users have access to ActivityPub features:', 'activitypub' ); ?></p>

<ol>
	<li><?php esc_html_e( 'Select the users you want to update by checking the boxes next to their names.', 'activitypub' ); ?></li>
	<li><?php esc_html_e( 'In the &#8220;Bulk Actions&#8221; dropdown, choose:', 'activitypub' ); ?>
		<ul>
			<li><?php esc_html_e( '&#8220;Enable for ActivityPub&#8221; to grant ActivityPub capabilities.', 'activitypub' ); ?></li>
			<li><?php esc_html_e( '&#8220;Disable for ActivityPub&#8221; to remove ActivityPub capabilities.', 'activitypub' ); ?></li>
		</ul>
	</li>
	<li><?php esc_html_e( 'Click &#8220;Apply&#8221; to save your changes.', 'activitypub' ); ?></li>
</ol>

<h3><?php esc_html_e( 'What are ActivityPub Capabilities?', 'activitypub' ); ?></h3>

<p><?php esc_html_e( 'The ActivityPub capability allows a user to:', 'activitypub' ); ?></p>

<ul>
	<li><?php esc_html_e( 'Have an individual ActivityPub profile that can be followed from other Fediverse platforms.', 'activitypub' ); ?></li>
	<li><?php esc_html_e( 'Publish content to the Fediverse automatically when posting on your WordPress site.', 'activitypub' ); ?></li>
	<li><?php esc_html_e( 'Manage followers and interactions from Fediverse users.', 'activitypub' ); ?></li>
	<li><?php esc_html_e( 'Access ActivityPub-specific settings and features.', 'activitypub' ); ?></li>
</ul>

<h3><?php esc_html_e( 'Default Behavior', 'activitypub' ); ?></h3>

<p><?php esc_html_e( 'By default, users who can publish posts are automatically granted ActivityPub capabilities. You can override this default behavior using the bulk edit options described above.', 'activitypub' ); ?></p>

<p><em>
<?php
printf(
	wp_kses(
		/* translators: %s: URL to ActivityPub settings */
		__( 'Note: If <a href="%s">&#8220;Blog profile only&#8221; mode</a> is enabled (where the site acts as a single ActivityPub profile), individual user capabilities do not affect ActivityPub functionality. All content is published under the blog&#8217;s profile.', 'activitypub' ),
		array( 'a' => array( 'href' => true ) )
	),
	esc_url( admin_url( 'options-general.php?page=activitypub&tab=settings' ) )
);
?>
</em></p>
