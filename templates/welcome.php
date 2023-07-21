<?php
\load_template(
	__DIR__ . '/admin-header.php',
	true,
	array(
		'settings'  => '',
		'welcome'   => 'active',
		'followers' => '',
	)
);
?>

<div class="activitypub-settings activitypub-welcome-page hide-if-no-js">
	<div class="box">
		<h2><?php \esc_html_e( 'Welcome', 'activitypub' ); ?></h2>

		<p><?php echo wp_kses( \__( 'With ActivityPub your blog becomes part of a federated social network. This means you can share and talk to everyone using the <strong>ActivityPub</strong> protocol, including users of <strong>Friendica</strong>, <strong>Pleroma</strong>, <strong>Pixelfed</strong> and <strong>Mastodon</strong>.', 'activitypub' ), array( 'strong' => array() ) ); ?></p>
	</div>

	<?php
	if ( ! \Activitypub\is_user_disabled( \Activitypub\Collection\Users::BLOG_USER_ID ) ) :
		$blog_user = new \Activitypub\Model\Blog_User();
		?>
	<div class="box">
		<h3><?php \esc_html_e( 'Blog Account', 'activitypub' ); ?></h3>
		<p>
			<?php \esc_html_e( 'People can follow your Blog by using:', 'activitypub' ); ?>
		</p>
		<p>
			<label for="activitypub-blog-username"><?php \esc_html_e( 'Username', 'activitypub' ); ?></label>
		</p>
		<p>
			<input type="text" class="regular-text" id="activitypub-blog-username" value="<?php echo \esc_attr( $blog_user->get_resource() ); ?>" />
		</p>
		<p>
			<label for="activitypub-blog-url"><?php \esc_html_e( 'Profile-URL', 'activitypub' ); ?></label>
		</p>
		<p>
			<input type="text" class="regular-text" id="activitypub-blog-url" value="<?php echo \esc_attr( $blog_user->get_url() ); ?>" />
		</p>
		<p>
			<?php \esc_html_e( 'This Blog-User will federate all posts written on your Blog, regardless of the User who posted it.', 'activitypub' ); ?>
		<p>
		<p>
			<a href="<?php echo \esc_url_raw( \admin_url( '/options-general.php?page=activitypub&tab=settings' ) ); ?>">
				<?php \esc_html_e( 'Customize Blog-User on Settings page.', 'activitypub' ); ?>
			</a>
		</p>
	</div>
	<?php endif; ?>

	<?php
	if ( ! \Activitypub\is_user_disabled( get_current_user_id() ) ) :
		$user = \Activitypub\Collection\Users::get_by_id( wp_get_current_user()->ID );
		?>
	<div class="box">
		<h3><?php \esc_html_e( 'Personal Account', 'activitypub' ); ?></h3>
		<p>
			<?php \esc_html_e( 'People can follow you by using your Username:', 'activitypub' ); ?>
		</p>
		<p>
			<label for="activitypub-user-username"><?php \esc_html_e( 'Username', 'activitypub' ); ?></label>
		</p>
		<p>
			<input type="text" class="regular-text" id="activitypub-user-username" value="<?php echo \esc_attr( $user->get_resource() ); ?>" />
		</p>
		<p>
			<label for="activitypub-user-url"><?php \esc_html_e( 'Profile-URL', 'activitypub' ); ?></label>
		</p>
		<p>
			<input type="text" class="regular-text" id="activitypub-user-url" value="<?php echo \esc_attr( $user->get_url() ); ?>" />
		</p>
		<p>
			<?php \esc_html_e( 'Users who can not access this settings page will find their username on the "Edit Profile" page.', 'activitypub' ); ?>
		<p>
		<p>
			<a href="<?php echo \esc_url_raw( \admin_url( '/profile.php#activitypub' ) ); ?>">
			<?php \esc_html_e( 'Customize Username on "Edit Profile" page.', 'activitypub' ); ?>
			</a>
		</p>
	</div>
	<?php endif; ?>

	<div class="box">
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
	</div>

	<?php if ( ACTIVITYPUB_SHOW_PLUGIN_RECOMMENDATIONS ) : ?>
	<div class="box plugin-recommendations">
		<h3><?php \esc_html_e( 'Recommended Plugins', 'activitypub' ); ?></h3>

		<p><?php \esc_html_e( 'ActivityPub works as is and there is no need for you to install additional plugins, nevertheless there are some plugins that extends the functionality of ActivityPub.', 'activitypub' ); ?></p>
	</div>
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
