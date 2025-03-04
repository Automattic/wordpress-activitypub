<?php
/**
 * ActivityPub Welcome template.
 *
 * @package Activitypub
 */

use Activitypub\Collection\Actors;

$user = false;
?>

<div class="activitypub-settings activitypub-welcome-page hide-if-no-js">
	<div class="box">
		<h2><?php \esc_html_e( 'Welcome', 'activitypub' ); ?></h2>

		<p><?php echo wp_kses( \__( 'Enter the fediverse with <strong>ActivityPub</strong>, broadcasting your blog to a wider audience. Attract followers, deliver updates, and receive comments from a diverse user base on <strong>Mastodon</strong>, <strong>Friendica</strong>, <strong>Pleroma</strong>, <strong>Pixelfed</strong>, and all <strong>ActivityPub</strong>-compliant platforms.', 'activitypub' ), array( 'strong' => array() ) ); ?></p>
	</div>

	<?php if ( \Activitypub\site_supports_blocks() ) : ?>
	<div class="box">
		<h3><?php \esc_html_e( 'Bookmarklet', 'activitypub' ); ?></h3>

		<p>
			<?php
			$bookmarklet_js = \Activitypub\get_reply_intent_js();

			/* translators: %s is the domain of this site */
			$reply_from_template = __( 'Reply from %s', 'activitypub' );
			$button              = sprintf(
				'<a href="%s" class="button">%s</a>',
				esc_attr( $bookmarklet_js ), // Need to escape quotes for the bookmarklet.
				sprintf( $reply_from_template, \wp_parse_url( \home_url(), PHP_URL_HOST ) )
			);
			/* translators: %s is where the button HTML will be rendered. */
			$button_and_explanation_template = \__(
				'%s Save this bookmarklet to reply to posts on other sites from your own blog! When visiting a post on another site, click the bookmarklet to start a reply.',
				'activitypub'
			);

			printf( $button_and_explanation_template, $button ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

			printf( ' <a href="%s">%s</a>', esc_url( \admin_url( 'tools.php#activitypub' ) ), esc_html__( 'For additional information, please visit the Tools page.', 'activitypub' ) );
			?>
		</p>
	</div>
		<?php
	endif;

	if ( ! \Activitypub\is_user_disabled( Actors::BLOG_USER_ID ) ) :
		$user = new \Activitypub\Model\Blog();
		?>
	<div class="box">
		<h3><?php \esc_html_e( 'Blog profile', 'activitypub' ); ?></h3>
		<p>
			<?php \esc_html_e( 'People can follow your blog by using:', 'activitypub' ); ?>
		</p>
		<p>
			<label for="activitypub-blog-identifier"><?php \esc_html_e( 'Username', 'activitypub' ); ?></label>
		</p>
		<p>
			<input type="text" class="regular-text" id="activitypub-blog-identifier" value="<?php echo \esc_attr( $user->get_webfinger() ); ?>" readonly />
		</p>
		<p>
			<label for="activitypub-blog-url"><?php \esc_html_e( 'Profile URL', 'activitypub' ); ?></label>
		</p>
		<p>
			<input type="text" class="regular-text" id="activitypub-blog-url" value="<?php echo \esc_attr( $user->get_url() ); ?>" readonly />
		</p>
		<p>
			<?php \esc_html_e( 'This blog profile will federate all posts written on your blog, regardless of the author who posted it.', 'activitypub' ); ?>
		<p>
		<p>
			<a href="<?php echo \esc_url_raw( \admin_url( '/options-general.php?page=activitypub&tab=blog-profile	' ) ); ?>">
				<?php \esc_html_e( 'Customize the blog profile', 'activitypub' ); ?>
			</a>
		</p>
	</div>
	<?php endif; ?>

	<?php
	if ( ! \Activitypub\is_user_disabled( get_current_user_id() ) ) :
		$user = Actors::get_by_id( get_current_user_id() );
		?>
	<div class="box">
		<h3><?php \esc_html_e( 'Author profile', 'activitypub' ); ?></h3>
		<p>
			<?php \esc_html_e( 'People can follow you by using your author name:', 'activitypub' ); ?>
		</p>
		<p>
			<label for="activitypub-user-identifier"><?php \esc_html_e( 'Username', 'activitypub' ); ?></label>
		</p>
		<p>
			<input type="text" class="regular-text" id="activitypub-user-identifier" value="<?php echo \esc_attr( $user->get_webfinger() ); ?>" readonly />
		</p>
		<p>
			<label for="activitypub-user-url"><?php \esc_html_e( 'Profile URL', 'activitypub' ); ?></label>
		</p>
		<p>
			<input type="text" class="regular-text" id="activitypub-user-url" value="<?php echo \esc_attr( $user->get_url() ); ?>" readonly />
		</p>
		<p>
			<?php \esc_html_e( 'Authors who can not access this settings page will find their username on the "Edit Profile" page.', 'activitypub' ); ?>
		<p>
		<p>
			<a href="<?php echo \esc_url_raw( \admin_url( '/profile.php#activitypub' ) ); ?>">
			<?php \esc_html_e( 'Customize username on "Edit Profile" page.', 'activitypub' ); ?>
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
					/* translators: the placeholder is the Site Health URL */
					\__(
						'If you have problems using this plugin, please check the <a href="%s">Site Health</a> page to ensure that your site is compatible and/or use the "Help" tab (in the top right of the settings pages).',
						'activitypub'
					),
					\esc_url( admin_url( 'site-health.php' ) )
				),
				'default'
			);
			?>
		</p>
	</div>

	<div class="box">
		<h3><?php \esc_html_e( 'Account Migration', 'activitypub' ); ?></h3>
		<p>
			<?php \esc_html_e( 'You can migrate your followers between Mastodon and WordPress. This allows you to move your social presence without losing your audience.', 'activitypub' ); ?>
		</p>
		<h4><?php \esc_html_e( 'Migrating from Mastodon to WordPress', 'activitypub' ); ?></h4>
		<p>
			<?php
			echo \wp_kses(
				\sprintf(
					/* translators: %1$s is the URL to the profile page, %2$s is the user's ActivityPub username */
					\__(
						'To migrate your followers from Mastodon to WordPress, add your Mastodon profile URL to the <a href="%1$s">Account Aliases</a> section in your WordPress profile, then initiate the migration from your Mastodon account settings. Your WordPress ActivityPub username is <code>%2$s</code>.',
						'activitypub'
					),
					\esc_url( \admin_url( 'profile.php#activitypub_blog_user_also_known_as' ) ),
					\esc_html( $user->get_webfinger() )
				),
				array(
					'a'    => array( 'href' => array() ),
					'code' => array(),
				)
			);
			?>
		</p>
		<h4><?php \esc_html_e( 'Migrating from WordPress to Mastodon', 'activitypub' ); ?></h4>
		<p>
			<?php
			echo \wp_kses(
				\sprintf(
					/* translators: %1$s is the URL to the profile page, %2$s is the user's ActivityPub username */
					\__(
						'To migrate your followers from WordPress to Mastodon, add your WordPress ActivityPub username (<code>%2$s</code>) as an alias in your Mastodon profile, then add your Mastodon profile URL to the <a href="%1$s">Account Aliases</a> section in WordPress, and finally initiate the migration from Mastodon.',
						'activitypub'
					),
					\esc_url( \admin_url( 'profile.php#activitypub_blog_user_also_known_as' ) ),
					\esc_html( $user->get_webfinger() )
				),
				array(
					'a'    => array( 'href' => array() ),
					'code' => array(),
				)
			);
			?>
		</p>
		<p>
			<?php \esc_html_e( 'For detailed migration instructions, click the "Help" tab in the top-right corner of this screen, then select "Account Migration".', 'activitypub' ); ?>
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
			<p><a href="<?php echo \esc_url( \admin_url( 'plugin-install.php?tab=plugin-information&plugin=friends&TB_iframe=true' ) ); ?>" class="thickbox open-plugin-details-modal button install-now" target="_blank"><?php \esc_html_e( 'Install the Friends Plugin', 'activitypub' ); ?></a></p>
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
			<p><a href="<?php echo \esc_url( \admin_url( 'plugin-install.php?tab=plugin-information&plugin=hum&TB_iframe=true' ) ); ?>" class="thickbox open-plugin-details-modal button install-now" target="_blank"><?php \esc_html_e( 'Install the Hum Plugin', 'activitypub' ); ?></a></p>
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
			<p><a href="<?php echo \esc_url( \admin_url( 'plugin-install.php?tab=plugin-information&plugin=webfinger&TB_iframe=true' ) ); ?>" class="thickbox open-plugin-details-modal button install-now" target="_blank"><?php \esc_html_e( 'Install the WebFinger Plugin', 'activitypub' ); ?></a></p>
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
			<p><a href="<?php echo \esc_url( \admin_url( 'plugin-install.php?tab=plugin-information&plugin=nodeinfo&TB_iframe=true' ) ); ?>" class="thickbox open-plugin-details-modal button install-now" target="_blank"><?php \esc_html_e( 'Install the NodeInfo Plugin', 'activitypub' ); ?></a></p>
		</div>
		<?php endif; ?>
	</div>
	<?php endif; ?>
</div>
