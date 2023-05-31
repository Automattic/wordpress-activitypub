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
	<h3><?php \esc_html_e( 'Blog Account', 'activitypub' ); ?></h3>
	<p>
		<?php
		$blog_user = new \Activitypub\Model\Blog_User();
		echo wp_kses(
			\sprintf(
				// translators:
				\__(
					'People can follow your Blog by using the username <code>%1$s</code> or the URL <code>%2$s</code>. This Blog-User will federate all posts written on your Blog, regardless of the User who posted it. You can customize the Blog-User on the <a href="%3$s">Settings Page</a>.',
					'activitypub'
				),
				\esc_attr( $blog_user->get_resource() ),
				\esc_url_raw( $blog_user->get_url() ),
				\esc_url_raw( \admin_url( '/options-general.php?page=activitypub&tab=settings' ) )
			),
			'default'
		);
		?>
	</p>
	<h3><?php \esc_html_e( 'Personal Account', 'activitypub' ); ?></h3>
	<p>
		<?php
		$user = \Activitypub\User_Factory::get_by_id( wp_get_current_user()->ID );
		echo wp_kses(
			\sprintf(
				// translators:
				\__(
					'People can also follow you by using your Username <code>%1$s</code> or your Author-URL <code>%2$s</code>. Users who can not access this settings page will find their username on the <a href="%3$s">Edit Profile</a> page.',
					'activitypub'
				),
				\esc_attr( $user->get_resource() ),
				\esc_url_raw( $user->get_url() ),
				\esc_url_raw( \admin_url( 'profile.php#activitypub' ) )
			),
			'default'
		);
		?>
	</p>
	<h3><?php \esc_html_e( 'Troubleshooting', 'activitypub' ); ?></h3>
	<p>
		<?php
		echo wp_kses(
			\sprintf(
				// translators:
				\__(
					'If you have problems using this plugin, please check the <a href="%s">Site Health</a> to ensure that your site is compatible and/or use the "Help" tab (in the top right of the settings pages).',
					'activitypub'
				),
				\esc_url_raw( admin_url( 'site-health.php' ) )
			),
			'default'
		);
		?>
	</p>
	<?php if ( ACTIVITYPUB_SHOW_PLUGIN_RECOMMENDATIONS ) : ?>
	<hr />

	<h3><?php \esc_html_e( 'Recommended Plugins', 'activitypub' ); ?></h3>

	<p><?php \esc_html_e( 'ActivityPub works as is and there is no need for you to install additional plugins, nevertheless there are some plugins that extends the functionality of ActivityPub.', 'activitypub' ); ?></p>

	<div class="activitypub-settings-accordion">
		<?php if ( ! \defined( 'FRIENDS_VERSION' ) ) : ?>
		<h4 class="activitypub-settings-accordion-heading">
			<button aria-expanded="true" class="activitypub-settings-accordion-trigger" aria-controls="activitypub-settings-accordion-block-friends-plugin" type="button">
				<span class="title"><?php \esc_html_e( 'Following Others', 'activitypub' ); ?></span>
				<span class="icon"></span>
			</button>
		</h4>
		<div id="activitypub-settings-accordion-block-friends-plugin" class="activitypub-settings-accordion-panel plugin-card-friends">
			<p><?php \esc_html_e( 'To follow people on Mastodon or similar platforms using your own WordPress, you can use the Friends Plugin for WordPress which uses this plugin to receive posts and display them on your own WordPress, thus making your own WordPress a Fediverse instance of its own.', 'activitypub' ); ?></p>
			<p><a href="<?php echo \esc_url_raw( \admin_url( 'plugin-install.php?tab=plugin-information&plugin=friends&TB_iframe=true' ) ); ?>" class="thickbox open-plugin-details-modal button install-now" target="_blank"><?php \esc_html_e( 'Install the Friends Plugin', 'activitypub' ); ?></a></p>
		</div>
		<?php endif; ?>
		<?php if ( ! \class_exists( 'Hum' ) ) : ?>
		<h4 class="activitypub-settings-accordion-heading">
			<button aria-expanded="false" class="activitypub-settings-accordion-trigger" aria-controls="activitypub-settings-accordion-block-activitypub-hum-plugin" type="button">
				<span class="title"><?php \esc_html_e( 'Add a URL Shortener', 'activitypub' ); ?></span>
				<span class="icon"></span>
			</button>
		</h4>
		<div id="activitypub-settings-accordion-block-activitypub-hum-plugin" class="activitypub-settings-accordion-panel plugin-card-hum" hidden="hidden">
			<p><?php \esc_html_e( 'Hum is a personal URL shortener for WordPress, designed to provide short URLs to your personal content, both hosted on WordPress and elsewhere.', 'activitypub' ); ?></p>
			<p><a href="<?php echo \esc_url_raw( \admin_url( 'plugin-install.php?tab=plugin-information&plugin=hum&TB_iframe=true' ) ); ?>" class="thickbox open-plugin-details-modal button install-now" target="_blank"><?php \esc_html_e( 'Install the Hum Plugin', 'activitypub' ); ?></a></p>
		</div>
		<?php endif; ?>
		<?php if ( ! \class_exists( 'Webfinger' ) ) : ?>
		<h4 class="activitypub-settings-accordion-heading">
			<button aria-expanded="false" class="activitypub-settings-accordion-trigger" aria-controls="activitypub-settings-accordion-block-activitypub-webfinger-plugin" type="button">
				<span class="title"><?php \esc_html_e( 'Advanced WebFinger Support', 'activitypub' ); ?></span>
				<span class="icon"></span>
			</button>
		</h4>
		<div id="activitypub-settings-accordion-block-activitypub-webfinger-plugin" class="activitypub-settings-accordion-panel plugin-card-webfinger" hidden="hidden">
			<p><?php \esc_html_e( 'WebFinger is a protocol that allows for discovery of information about people and things identified by a URI. Information about a person might be discovered via an "acct:" URI, for example, which is a URI that looks like an email address.', 'activitypub' ); ?></p>
			<p><?php \esc_html_e( 'The ActivityPub plugin comes with basic WebFinger support, if you need more configuration options and compatibility with other Fediverse/IndieWeb plugins, please install the WebFinger plugin.', 'activitypub' ); ?></p>
			<p><a href="<?php echo \esc_url_raw( \admin_url( 'plugin-install.php?tab=plugin-information&plugin=webfinger&TB_iframe=true' ) ); ?>" class="thickbox open-plugin-details-modal button install-now" target="_blank"><?php \esc_html_e( 'Install the WebFinger Plugin', 'activitypub' ); ?></a></p>
		</div>
		<?php endif; ?>
		<?php if ( ! \function_exists( 'nodeinfo_init' ) ) : ?>
		<h4 class="activitypub-settings-accordion-heading">
			<button aria-expanded="false" class="activitypub-settings-accordion-trigger" aria-controls="activitypub-settings-accordion-block-activitypub-nodeinfo-plugin" type="button">
				<span class="title"><?php \esc_html_e( 'Provide Enhanced Information about Your Blog', 'activitypub' ); ?></span>
				<span class="icon"></span>
			</button>
		</h4>
		<div id="activitypub-settings-accordion-block-activitypub-nodeinfo-plugin" class="activitypub-settings-accordion-panel plugin-card-nodeinfo" hidden="hidden">
			<p><?php \esc_html_e( 'NodeInfo is an effort to create a standardized way of exposing metadata about a server running one of the distributed social networks. The two key goals are being able to get better insights into the user base of distributed social networking and the ability to build tools that allow users to choose the best fitting software and server for their needs.', 'activitypub' ); ?></p>
			<p><?php \esc_html_e( 'The ActivityPub plugin comes with a simple NodeInfo endpoint. If you need more configuration options and compatibility with other Fediverse plugins, please install the NodeInfo plugin.', 'activitypub' ); ?></p>
			<p><a href="<?php echo \esc_url_raw( \admin_url( 'plugin-install.php?tab=plugin-information&plugin=nodeinfo&TB_iframe=true' ) ); ?>" class="thickbox open-plugin-details-modal button install-now" target="_blank"><?php \esc_html_e( 'Install the NodeInfo Plugin', 'activitypub' ); ?></a></p>
		</div>
		<?php endif; ?>
	</div>
	<?php endif; ?>
</div>
