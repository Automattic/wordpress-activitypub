<?php
/**
 * Account Migration Help Tab template.
 *
 * @package Activitypub
 */

use Activitypub\Collection\Actors;
use Activitypub\Model\Blog;

use function Activitypub\user_can_activitypub;

if ( user_can_activitypub( get_current_user_id() ) ) {
	$webfinger = Actors::get_by_id( get_current_user_id() )->get_webfinger();
} else {
	$webfinger = ( new Blog() )->get_webfinger();
}
?>

<h2><?php esc_html_e( 'Understanding Account Migration', 'activitypub' ); ?></h2>
<p><?php esc_html_e( 'Account migration in the Fediverse allows you to move your identity from one platform to another while bringing your followers with you.', 'activitypub' ); ?></p>
<p><?php esc_html_e( 'When you migrate properly, your followers are automatically redirected to follow your new account, and your old account can point people to your new one.', 'activitypub' ); ?></p>
<p><?php esc_html_e( 'This is especially useful if you&#8217;re moving from a Mastodon instance to your WordPress site, or if you&#8217;re changing domains.', 'activitypub' ); ?></p>

<h2><?php esc_html_e( 'Migrating from Mastodon to WordPress', 'activitypub' ); ?></h2>
<ol>
	<li>
	<?php
	echo wp_kses(
		sprintf(
		/* translators: %s is the URL to the profile page */
			__( 'In your WordPress profile, go to the <a href="%s">Account Aliases</a> section and add your Mastodon profile URL (e.g., <code>https://mastodon.social/@username</code>).', 'activitypub' ),
			esc_url( admin_url( 'profile.php#activitypub_blog_user_also_known_as' ) )
		),
		array_merge(
			array( 'code' => array() ),
			array(
				'a' => array(
					'href'   => true,
					'target' => true,
				),
			)
		)
	);
	?>
	</li>
	<li><?php esc_html_e( 'Save your WordPress profile changes.', 'activitypub' ); ?></li>
	<li><?php esc_html_e( 'Log in to your Mastodon account.', 'activitypub' ); ?></li>
	<li><?php esc_html_e( 'Go to Preferences > Account > Move to a different account.', 'activitypub' ); ?></li>
	<li>
	<?php
	echo wp_kses(
		sprintf(
			/* translators: %s is the user's ActivityPub username */
			__( 'Enter your WordPress ActivityPub username (e.g., <code>%s</code>) in the &#8220;Handle of the new account&#8221; field.', 'activitypub' ),
			esc_html( $webfinger )
		),
		array( 'code' => array() )
	);
	?>
	</li>
	<li><?php esc_html_e( 'Confirm the migration in Mastodon by entering your password.', 'activitypub' ); ?></li>
	<li><?php esc_html_e( 'Your followers will be notified and redirected to follow your WordPress account.', 'activitypub' ); ?></li>
</ol>

<h2><?php esc_html_e( 'Managing Multiple Accounts', 'activitypub' ); ?></h2>
<p><?php esc_html_e( 'If you maintain presence on multiple platforms:', 'activitypub' ); ?></p>
<ul>
	<li><?php esc_html_e( 'Use the Account Aliases feature to link your identities.', 'activitypub' ); ?></li>
	<li><?php esc_html_e( 'Consider which account will be your primary one.', 'activitypub' ); ?></li>
	<li><?php esc_html_e( 'Be clear with your followers about where to find you.', 'activitypub' ); ?></li>
	<li><?php esc_html_e( 'Remember that full migration moves your followers completely.', 'activitypub' ); ?></li>
</ul>
