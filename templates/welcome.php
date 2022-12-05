<?php
\load_template(
	\dirname( __FILE__ ) . '/admin-header.php',
	true,
	array(
		'settings' => '',
		'welcome' => 'active',
	)
);
?>

<div class="privacy-settings-body hide-if-no-js">
	<h2><?php \esc_html_e( 'Welcome', 'activitypub' ); ?></h2>

	<p><?php \esc_html_e( 'With ActivityPub your blog becomes part of a federated social network. This means you can share and talk to everyone using the ActivityPub protocol, including users of Friendica, Pleroma and Mastodon.', 'activitypub' ); ?></p>
	<p>
		<?php
		\printf(
			// translators:
			\esc_html__(
				'People can follow you by using the username %1$s or the URL %2$s. Users who can not access this settings page will find their username on the %3$sEdit Profile%4$s page.',
				'activitypub'
			),
			\sprintf(
				'<code>@%s</code>',
				\esc_attr( \Activitypub\get_webfinger_resource( wp_get_current_user()->ID ) )
			),
			\sprintf(
				'<code>%s</code>',
				\esc_url_raw( \get_author_posts_url( wp_get_current_user()->ID ) )
			),
			\sprintf(
				'<a href="%s">',
				\esc_url_raw( \admin_url( 'profile.php#activitypub' ) )
			),
			'</a>'
		);
		?>
	</p>
	<p>
		<?php
		\printf(
			// translators:
			\esc_html__( 'If you have problems using this plugin, please check the %1$sSite Health%2$s to ensure that your site is compatible and/or use the "Help" tab (in the top right of the settings pages).', 'activitypub' ),
			\sprintf(
				'<a href="%s">',
				\esc_url_raw( admin_url( '/wp-admin/site-health.php' ) )
			),
			'</a>'
		);
		?>
	</p>

	<hr />

	<h3><?php \esc_html_e( 'Recommended Plugins', 'activitypub' ); ?></h3>

	<p><?php \esc_html_e( 'ActivityPub works as is and there is no need for you to install additional plugins, nevertheless there are some plugins that extends the functionality of ActivityPub.', 'activitypub' ); ?></p>

	<div class="activitypub-settings-accordion">
		<h4 class="activitypub-settings-accordion-heading">
			<button aria-expanded="true" class="activitypub-settings-accordion-trigger" aria-controls="activitypub-settings-accordion-block-friends-plugin" type="button">
				<span class="title"><?php \esc_html_e( 'Following Others', 'activitypub' ); ?></span>
				<span class="icon"></span>
			</button>
		</h4>
		<div id="activitypub-settings-accordion-block-friends-plugin" class="activitypub-settings-accordion-panel">
			<p><?php \esc_html_e( 'To follow people on Mastodon or similar platforms using your own WordPress, you can use the Friends Plugin for WordPress which uses this plugin to receive posts and display them on your own WordPress, thus making your own WordPress a Fediverse instance of its own.', 'activitypub' ); ?></p>
			<p><a href="https://wordpress.org/plugins/friends" class="button"><?php \esc_html_e( 'Install the Friends Plugin for WordPress', 'activitypub' ); ?></a></p>
		</div>
		<h4 class="activitypub-settings-accordion-heading">
			<button aria-expanded="false" class="activitypub-settings-accordion-trigger" aria-controls="activitypub-settings-accordion-block-activitypub-hum-plugin" type="button">
				<span class="title"><?php \esc_html_e( 'Add a URL Shortener', 'activitypub' ); ?></span>
				<span class="icon"></span>
			</button>
		</h4>
		<div id="activitypub-settings-accordion-block-activitypub-hum-plugin" class="activitypub-settings-accordion-panel" hidden="hidden">
			<p><?php \esc_html_e( 'Hum is a personal URL shortener for WordPress, designed to provide short URLs to your personal content, both hosted on WordPress and elsewhere.', 'activitypub' ); ?></p>
			<p><a href="https://wordpress.org/plugins/hum" class="button"><?php \esc_html_e( 'Install Hum Plugin for WordPress', 'activitypub' ); ?></a></p>
		</div>
		<h4 class="activitypub-settings-accordion-heading">
			<button aria-expanded="false" class="activitypub-settings-accordion-trigger" aria-controls="activitypub-settings-accordion-block-activitypub-webfinger-plugin" type="button">
				<span class="title"><?php \esc_html_e( 'Advanced WebFinger Support', 'activitypub' ); ?></span>
				<span class="icon"></span>
			</button>
		</h4>
		<div id="activitypub-settings-accordion-block-activitypub-webfinger-plugin" class="activitypub-settings-accordion-panel" hidden="hidden">
			<p><?php \esc_html_e( 'WebFinger is a protocol that allows for discovery of information about people and things identified by a URI. Information about a person might be discovered via an "acct:" URI, for example, which is a URI that looks like an email address.', 'activitypub' ); ?></p>
			<p><?php \esc_html_e( 'The ActivityPub plugin comes with basic WebFinger support, if you need more configuration options and compatibility with other Fediverse/IndieWeb plugins, please install the WebFinger plugin.', 'activitypub' ); ?></p>
			<p><a href="https://wordpress.org/plugins/webfinger" class="button"><?php \esc_html_e( 'Install WebFinger Plugin for WordPress', 'activitypub' ); ?></a></p>
		</div>
		<h4 class="activitypub-settings-accordion-heading">
			<button aria-expanded="false" class="activitypub-settings-accordion-trigger" aria-controls="activitypub-settings-accordion-block-activitypub-nodeinfo-plugin" type="button">
				<span class="title"><?php \esc_html_e( 'Provide Enhanced Information about Your Blog', 'activitypub' ); ?></span>
				<span class="icon"></span>
			</button>
		</h4>
		<div id="activitypub-settings-accordion-block-activitypub-nodeinfo-plugin" class="activitypub-settings-accordion-panel" hidden="hidden">
			<p><?php \esc_html_e( 'NodeInfo is an effort to create a standardized way of exposing metadata about a server running one of the distributed social networks. The two key goals are being able to get better insights into the user base of distributed social networking and the ability to build tools that allow users to choose the best fitting software and server for their needs.', 'activitypub' ); ?></p>
			<p><?php \esc_html_e( 'The ActivityPub plugin comes with a simple NodeInfo endpoint. If you need more configuration options and compatibility with other Fediverse plugins, please install the NodeInfo plugin.', 'activitypub' ); ?></p>
			<p><a href="https://wordpress.org/plugins/nodeinfo" class="button"><?php \esc_html_e( 'Install NodeInfo Plugin for WordPress', 'activitypub' ); ?></a></p>
		</div>
	</div>
</div>
