<?php \load_template( \dirname( __FILE__ ) . '/admin-header.php', true, array( 'settings' => '', 'welcome' => 'active' ) ); ?>

<div class="privacy-settings-body hide-if-no-js">
	<h2><?php \esc_html_e( 'Welcome', 'activitypub' ); ?></h2>

	<p><?php \_e( 'ActivityPub turns your blog into a federated social network. This means you can share and talk to everyone using the ActivityPub protocol, including users of Friendica, Pleroma and Mastodon.', 'activitypub' ); ?></p>
	<p>
		<?php
		\printf(
			\__(
				'People can follow you by using the username <code>%s</code> or the URL <code>%s</code>. Users, that can not access this settings page, will find their username on the <a href="%s">Edit Profile</a> page.',
				'activitypub'
			),
			\Activitypub\get_webfinger_resource( wp_get_current_user()->ID ),
			\get_author_posts_url( wp_get_current_user()->ID ),
			\admin_url( 'profile.php#fediverse' )
		);
		?>
	</p>
	<p><?php \printf( __( 'If you have problems using this plugin, please check the <a href="%s">Site Health</a> to ensure that your site is compatible and/or use the "Help" tab (in the top right of the settings pages).', 'activitypub' ), admin_url( '/wp-admin/site-health.php' ) ); ?></p>
	<hr />
	<p><?php \_e( 'To follow people on Mastodon or similar platforms using your own WordPress, you can use the <a href="https://wordpress.org/plugins/friends">Friends Plugin for WordPress</a> which uses this plugin to receive posts and display them on your own WordPress, thus making your own WordPress a Mastodon instance of its own.', 'activitypub' ); ?></p>
</div>
