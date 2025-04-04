<?php
/**
 * ActivityPub Welcome Fields Class.
 *
 * @package Activitypub
 */

namespace Activitypub\WP_Admin;

use Activitypub\Model\Blog;
use Activitypub\Collection\Actors;

use function Activitypub\get_reply_intent_js;
use function Activitypub\user_can_activitypub;

/**
 * Class Welcome_Fields.
 */
class Welcome_Fields {
	/**
	 * Initialize the welcome fields.
	 */
	public static function init() {
		\add_action( 'load-settings_page_activitypub', array( self::class, 'register_welcome_fields' ) );
		\add_action( 'load-settings_page_activitypub', array( self::class, 'add_admin_notices' ) );
	}

	/**
	 * Register welcome fields.
	 */
	public static function register_welcome_fields() {
		// Add settings sections.
		\add_settings_section(
			'activitypub_intro',
			\__( 'Welcome', 'activitypub' ),
			array( self::class, 'render_welcome_intro_section' ),
			'activitypub_welcome'
		);

		\add_settings_section(
			'activitypub_bookmarklet',
			\__( 'Bookmarklet', 'activitypub' ),
			array( self::class, 'render_bookmarklet_section' ),
			'activitypub_welcome'
		);

		if ( user_can_activitypub( Actors::BLOG_USER_ID ) ) {
			\add_settings_section(
				'activitypub_blog_profile',
				\__( 'Blog profile', 'activitypub' ),
				array( self::class, 'render_blog_profile_section' ),
				'activitypub_welcome'
			);
		}

		if ( user_can_activitypub( \get_current_user_id() ) ) {
			\add_settings_section(
				'activitypub_author_profile',
				\__( 'Author profile', 'activitypub' ),
				array( self::class, 'render_author_profile_section' ),
				'activitypub_welcome'
			);
		}

		if ( ACTIVITYPUB_SHOW_PLUGIN_RECOMMENDATIONS ) {
			\add_settings_section(
				'activitypub_recommended_plugins',
				\__( 'Recommended Plugins', 'activitypub' ),
				array( self::class, 'render_recommended_plugins_section' ),
				'activitypub_welcome'
			);
		}
	}

	/**
	 * Add Health Check errors as admin notices.
	 */
	public static function add_admin_notices() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['tab'] ) && 'welcome' !== $_GET['tab'] ) {
			return;
		}

		if ( ! \get_user_meta( \get_current_user_id(), 'activitypub_show_welcome_tab', true ) ) {
			return;
		}

		if ( Health_Check::count_results( 'critical' ) ) {
			\add_action( 'admin_notices', array( self::class, 'admin_notices' ) );
		}
	}

	/**
	 * Render welcome intro section.
	 */
	public static function render_welcome_intro_section() {
		?>
		<a class="welcome-tab-close" href="<?php echo \esc_url( \admin_url( 'options-general.php?page=activitypub&welcome=0' ) ); ?>" aria-label="<?php \esc_attr_e( 'Dismiss the welcome page', 'activitypub' ); ?>"><?php \esc_html_e( 'Dismiss Welcome Page', 'activitypub' ); ?></a>
		<p><?php echo wp_kses( \__( 'Enter the fediverse with <strong>ActivityPub</strong>, broadcasting your blog to a wider audience. Attract followers, deliver updates, and receive comments from a diverse user base on <strong>Mastodon</strong>, <strong>Friendica</strong>, <strong>Pleroma</strong>, <strong>Pixelfed</strong>, and all <strong>ActivityPub</strong>-compliant platforms.', 'activitypub' ), array( 'strong' => array() ) ); ?></p>
		<?php
	}

	/**
	 * Render bookmarklet section.
	 */
	public static function render_bookmarklet_section() {
		?>
		<p>
			<?php
			$bookmarklet_js = get_reply_intent_js();

			/* translators: %s is the domain of this site */
			$reply_from_template = __( 'Reply from %s', 'activitypub' );

			printf(
				'<a href="%s" class="button">%s</a>',
				esc_attr( $bookmarklet_js ), // Need to escape quotes for the bookmarklet.
				sprintf( esc_html( $reply_from_template ), esc_html( \wp_parse_url( \home_url(), PHP_URL_HOST ) ) )
			);
			?>
		</p>
		<p>
			<?php
			/* translators: %s is where the button HTML will be rendered. */
			\esc_html_e(
				'Save this bookmarklet to reply to posts on other sites from your own blog! When visiting a post on another site, click the bookmarklet to start a reply.',
				'activitypub'
			);

			printf( ' <a href="%s">%s</a>', esc_url( \admin_url( 'tools.php#activitypub' ) ), esc_html__( 'For additional information, please visit the Tools page.', 'activitypub' ) );
			?>
		</p>
		<?php
	}

	/**
	 * Render blog profile section.
	 */
	public static function render_blog_profile_section() {
		$blog_user = new Blog();

		?>
		<p>
			<?php \esc_html_e( 'People can follow your blog by using:', 'activitypub' ); ?>
		</p>
		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row"><?php \esc_html_e( 'Username', 'activitypub' ); ?></th>
					<td>
						<input type="text" class="large-text code" id="activitypub-blog-identifier" value="<?php echo \esc_attr( $blog_user->get_webfinger() ); ?>" readonly />
					</td>
				</tr>
				<tr>
					<th scope="row"><?php \esc_html_e( 'Profile URL', 'activitypub' ); ?></th>
					<td>
						<input type="text" class="large-text code" id="activitypub-blog-url" value="<?php echo \esc_attr( $blog_user->get_url() ); ?>" readonly />
					</td>
				</tr>
			</tbody>
		</table>
		<p>
			<?php \esc_html_e( 'This blog profile will federate all posts written on your blog, regardless of the author who posted it.', 'activitypub' ); ?>
			<a href="<?php echo \esc_url( \admin_url( '/options-general.php?page=activitypub&tab=blog-profile' ) ); ?>">
				<?php \esc_html_e( 'Customize the blog profile.', 'activitypub' ); ?>
			</a>
		</p>
		<?php
	}

	/**
	 * Render author profile section.
	 */
	public static function render_author_profile_section() {
		$user = Actors::get_by_id( \get_current_user_id() );
		?>
		<p>
			<?php \esc_html_e( 'People can follow you by using your author name:', 'activitypub' ); ?>
		</p>
		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row"><?php \esc_html_e( 'Username', 'activitypub' ); ?></th>
					<td>
						<input type="text" class="large-text code" id="activitypub-user-identifier" value="<?php echo \esc_attr( $user->get_webfinger() ); ?>" readonly />
					</td>
				</tr>
				<tr>
					<th scope="row"><?php \esc_html_e( 'Profile URL', 'activitypub' ); ?></th>
					<td>
						<input type="text" class="large-text code" id="activitypub-user-url" value="<?php echo \esc_attr( $user->get_url() ); ?>" readonly />
					</td>
				</tr>
			</tbody>
		</table>
		<p>
			<?php \esc_html_e( 'Authors who can not access this settings page will find their username on the "Edit Profile" page.', 'activitypub' ); ?>
			<a href="<?php echo \esc_url( \admin_url( '/profile.php#activitypub' ) ); ?>">
			<?php \esc_html_e( 'Customize username on "Edit Profile" page.', 'activitypub' ); ?>
			</a>
		</p>
		<?php
	}

	/**
	 * Render troubleshooting section.
	 */
	public static function admin_notices() {
		$results = Health_Check::count_results();
		?>
		<div class="activitypub-notice notice notice-warning">
			<p>
				<span class="dashicons dashicons-warning"></span>
				<?php
				echo wp_kses(
					\sprintf(
						/* translators: the placeholders are the number of critical and recommended issues on the site. */
						\__(
							'<strong>Important:</strong> There are <span class="count">%1$d</span> critical and <span class="count">%2$d</span> recommended issues affecting your site&#8217;s compatibility with the fediverse. Please check the <a href="%3$s">Site Health</a> page to resolve these issues.',
							'activitypub'
						),
						$results['critical'],
						$results['recommended'],
						\esc_url( \admin_url( 'site-health.php' ) )
					),
					array(
						'strong' => array(),
						'span'   => array(
							'class' => array(),
						),
						'a'      => array(
							'href' => array(),
						),
					)
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Render recommended plugins section.
	 */
	public static function render_recommended_plugins_section() {
		?>
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
				<p><a href="<?php echo \esc_url( \admin_url( 'plugin-install.php?tab=plugin-information&plugin=friends&TB_iframe=true' ) ); ?>" class="thickbox open-plugin-details-modal button install-now" target="_blank"><?php \esc_html_e( 'Install the Friends Plugin', 'activitypub' ); ?></a></p>
			</div>
			<?php endif; ?>
			<?php if ( ! \defined( 'EVENT_BRIDGE_FOR_ACTIVITYPUB_PLUGIN_VERSION' ) ) : ?>
			<h4 class="activitypub-settings-accordion-heading">
				<button aria-expanded="false" class="activitypub-settings-accordion-trigger" aria-controls="activitypub-settings-accordion-block-event-bridge-for-activitypub-plugin" type="button">
					<span class="title"><?php \esc_html_e( 'Federate Events', 'activitypub' ); ?></span>
					<span class="icon"></span>
				</button>
			</h4>
			<div id="activitypub-settings-accordion-block-event-bridge-for-activitypub-plugin" class="activitypub-settings-accordion-panel plugin-card-block-event-bridge-for-activitypub" hidden="hidden">
				<p><?php \esc_html_e( 'Make your events more discoverable, expand your reach effortlessly while being independent of other (commercial) platforms, and be a part of the growing decentralized web (the Fediverse). With the Event Bridge for ActivityPub Plugin for WordPress, your events can be automatically followed, aggregated and displayed across decentralized platforms like Mastodon or Gancio, without any extra work.', 'activitypub' ); ?></p>
				<p><a href="<?php echo \esc_url( \admin_url( 'plugin-install.php?tab=plugin-information&plugin=event-bridge-for-activitypub&TB_iframe=true' ) ); ?>" class="thickbox open-plugin-details-modal button install-now" target="_blank"><?php \esc_html_e( 'Install the Event Bridge for ActivityPub Plugin', 'activitypub' ); ?></a></p>
			</div>
			<?php endif; ?>
			<?php if ( ! \defined( 'ENABLE_MASTODON_APPS_VERSION' ) ) : ?>
			<h4 class="activitypub-settings-accordion-heading">
				<button aria-expanded="false" class="activitypub-settings-accordion-trigger" aria-controls="activitypub-settings-accordion-block-enable-mastodon-apps-plugin" type="button">
					<span class="title"><?php \esc_html_e( 'Use Mastodon Apps', 'activitypub' ); ?></span>
					<span class="icon"></span>
				</button>
			</h4>
			<div id="activitypub-settings-accordion-block-enable-mastodon-apps-plugin" class="activitypub-settings-accordion-panel plugin-card-block-enable-mastodon-apps" hidden="hidden">
				<p>
					<?php
					echo \wp_kses(
						\sprintf(
							// translators: %s is a URL.
							\__( 'Enable the use of a wide variety of <a href="%s" target="_blank">Mastodon apps</a> to interact with your WordPress site, for example write posts that can then be federated via the ActivityPub plugin.', 'activitypub' ),
							'https://joinmastodon.org/apps'
						),
						array(
							'a' => array(
								'href'   => true,
								'target' => true,
							),
						)
					);
					?>
				</p>
				<p><a href="<?php echo \esc_url( \admin_url( 'plugin-install.php?tab=plugin-information&plugin=enable-mastodon-apps&TB_iframe=true' ) ); ?>" class="thickbox open-plugin-details-modal button install-now" target="_blank"><?php \esc_html_e( 'Install the Enable Mastodon Apps Plugin', 'activitypub' ); ?></a></p>
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
					<span class="title"><?php \esc_html_e( 'Provide Enhanced Information About Your Blog', 'activitypub' ); ?></span>
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
		<?php
	}
}
