<?php
/**
 * Settings file.
 *
 * @package Activitypub
 */

namespace Activitypub\WP_Admin;

use Activitypub\Collection\Actors;

use function Activitypub\user_can_activitypub;

/**
 * ActivityPub Settings Class.
 */
class Settings {
	/**
	 * Initialize the class, registering WordPress hooks,
	 */
	public static function init() {
		\add_action( 'admin_menu', array( self::class, 'add_settings_page' ) );

		\add_action( 'load-settings_page_activitypub', array( self::class, 'handle_welcome_query_arg' ) );
	}

	/**
	 * Load settings page.
	 */
	public static function settings_page() {
		$show_welcome_tab  = \get_user_meta( \get_current_user_id(), 'activitypub_show_welcome_tab', true );
		$show_advanced_tab = \get_user_meta( \get_current_user_id(), 'activitypub_show_advanced_tab', true );
		$settings_tabs     = array();
		$settings_tab      = array(
			'label'    => __( 'Settings', 'activitypub' ),
			'template' => ACTIVITYPUB_PLUGIN_DIR . 'templates/settings.php',
		);

		if ( $show_welcome_tab ) {
			$settings_tabs['welcome'] = array(
				'label'    => __( 'Welcome', 'activitypub' ),
				'template' => ACTIVITYPUB_PLUGIN_DIR . 'templates/welcome.php',
			);
		}

		$settings_tabs['settings'] = $settings_tab;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ( isset( $_GET['tab'] ) && 'advanced' === $_GET['tab'] ) || $show_advanced_tab ) {
			$settings_tabs['advanced'] = array(
				'label'    => \__( 'Advanced', 'activitypub' ),
				'template' => ACTIVITYPUB_PLUGIN_DIR . 'templates/advanced-settings.php',
			);
		}

		// Add blocked actors tab for site-wide blocking.
		$settings_tabs['blocked-actors'] = array(
			'label'    => \__( 'Blocked Actors', 'activitypub' ),
			'template' => ACTIVITYPUB_PLUGIN_DIR . 'templates/blocked-actors-list.php',
		);

		if ( user_can_activitypub( Actors::BLOG_USER_ID ) ) {
			$settings_tabs['blog-profile'] = array(
				'label'    => __( 'Blog Profile', 'activitypub' ),
				'template' => ACTIVITYPUB_PLUGIN_DIR . 'templates/blog-settings.php',
			);
			$settings_tabs['followers']    = array(
				'label'    => __( 'Followers', 'activitypub' ),
				'template' => ACTIVITYPUB_PLUGIN_DIR . 'templates/followers-list.php',
			);

			if ( '1' === \get_option( 'activitypub_following_ui', '0' ) ) {
				$settings_tabs['following'] = array(
					'label'    => __( 'Following', 'activitypub' ),
					'template' => ACTIVITYPUB_PLUGIN_DIR . 'templates/following-list.php',
				);
			}
		}

		/**
		 * Filters the tabs displayed in the ActivityPub settings.
		 *
		 * @param array $settings_tabs The tabs to display.
		 */
		$settings_tabs = \apply_filters( 'activitypub_admin_settings_tabs', $settings_tabs );

		if ( empty( $settings_tabs ) ) {
			_doing_it_wrong( __FUNCTION__, 'No settings tabs found. There should be at least one tab to show a settings page.', '7.0.0' );
			$settings_tabs['settings'] = $settings_tab;
		}

		$tab_keys    = array_keys( $settings_tabs );
		$default_tab = reset( $tab_keys );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tab = isset( $_GET['tab'] ) ? \sanitize_key( $_GET['tab'] ) : $default_tab;

		if ( ! isset( $settings_tabs[ $tab ] ) ) {
			$tab = $default_tab;
		}

		switch ( $tab ) {
			case 'blog-profile':
				\wp_enqueue_media();
				\wp_enqueue_script( 'activitypub-header-image' );
				break;
			case 'settings':
				\update_option( 'activitypub_checklist_settings_visited', '1' );
				break;
			default:
				if ( isset( $_GET['help-tab'] ) && 'getting-started' === $_GET['help-tab'] ) { // phpcs:ignore WordPress.Security.NonceVerification
					\update_option( 'activitypub_checklist_fediverse_intro_visited', '1' );
				} elseif ( isset( $_GET['help-tab'] ) && 'editor-blocks' === $_GET['help-tab'] ) { // phpcs:ignore WordPress.Security.NonceVerification
					\update_option( 'activitypub_checklist_blocks_visited', '1' );
				}
				break;
		}

		// Only show tabs if there are more than one.
		$labels = array();
		if ( \count( $settings_tabs ) > 1 ) {
			$labels = \wp_list_pluck( $settings_tabs, 'label' );
		}

		$args         = \array_fill_keys( \array_keys( $labels ), '' );
		$args[ $tab ] = 'active';
		$args['tabs'] = $labels;

		\load_template( ACTIVITYPUB_PLUGIN_DIR . 'templates/admin-header.php', true, $args );
		\load_template( $settings_tabs[ $tab ]['template'] );
	}

	/**
	 * Adds the ActivityPub settings to the Help tab.
	 */
	public static function add_settings_help_tab() {
		// Getting Started / Introduction to the Fediverse.
		\get_current_screen()->add_help_tab(
			array(
				'id'      => 'getting-started',
				'title'   => \__( 'Getting Started', 'activitypub' ),
				'content' => self::get_help_tab_template( 'getting-started' ),
			)
		);

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'following' === \sanitize_text_field( \wp_unslash( $_GET['tab'] ?? '' ) ) ) {
			self::add_following_help_tab();
		}

		// Core Features.
		\get_current_screen()->add_help_tab(
			array(
				'id'      => 'core-features',
				'title'   => \__( 'Core Features', 'activitypub' ),
				'content' => self::get_help_tab_template( 'core-features' ),
			)
		);

		// Editor Blocks.
		\get_current_screen()->add_help_tab(
			array(
				'id'      => 'editor-blocks',
				'title'   => \__( 'Editor Blocks', 'activitypub' ),
				'content' => self::get_help_tab_template( 'editor-blocks' ),
			)
		);

		// Account Migration.
		\get_current_screen()->add_help_tab(
			array(
				'id'      => 'account-migration',
				'title'   => \__( 'Account Migration', 'activitypub' ),
				'content' => self::get_help_tab_template( 'account-migration' ),
			)
		);

		// Show only if templating is enabled.
		$object_type = \get_option( 'activitypub_object_type', ACTIVITYPUB_DEFAULT_OBJECT_TYPE );
		if ( 'note' === $object_type ) {
			// Template Tags.
			\get_current_screen()->add_help_tab(
				array(
					'id'      => 'template-tags',
					'title'   => \__( 'Template Tags', 'activitypub' ),
					'content' => self::get_help_tab_template( 'template-tags' ),
				)
			);
		}

		// Recommended Plugins.
		if ( ! empty( self::get_recommended_plugins() ) ) {
			\get_current_screen()->add_help_tab(
				array(
					'id'      => 'recommended-plugins',
					'title'   => __( 'Recommended Plugins', 'activitypub' ),
					'content' =>
						'<h2>' . esc_html__( 'Supercharge Your Fediverse Experience', 'activitypub' ) . '</h2>' .
						'<p>' . esc_html__( 'Enhance your WordPress ActivityPub setup with these hand-picked plugins, each adding unique capabilities for a richer Fediverse experience.', 'activitypub' ) . '</p>' .
						self::render_recommended_plugins_list(),
				)
			);
		}

		// Troubleshooting.
		\get_current_screen()->add_help_tab(
			array(
				'id'      => 'troubleshooting',
				'title'   => \__( 'Troubleshooting', 'activitypub' ),
				'content' => self::get_help_tab_template( 'troubleshooting' ),
			)
		);

		// Glossary.
		\get_current_screen()->add_help_tab(
			array(
				'id'      => 'glossary',
				'title'   => \__( 'Glossary', 'activitypub' ),
				'content' => self::get_help_tab_template( 'glossary' ),
			)
		);

		// Resources.
		\get_current_screen()->add_help_tab(
			array(
				'id'      => 'resources',
				'title'   => \__( 'Resources', 'activitypub' ),
				'content' => self::get_help_tab_template( 'resources' ),
			)
		);

		// Enhanced Help Sidebar.
		\get_current_screen()->set_help_sidebar(
			'<p><strong>' . \__( 'For more information:', 'activitypub' ) . '</strong></p>' . "\n" .
			'<p><a href="https://wordpress.org/support/plugin/activitypub/">' . \esc_html__( 'Get support', 'activitypub' ) . '</a></p>' . "\n" .
			'<p><a href="https://github.com/Automattic/wordpress-activitypub/issues">' . \esc_html__( 'Report an issue', 'activitypub' ) . '</a></p>' . "\n" .
			'<p><a href="https://github.com/Automattic/wordpress-activitypub/tree/trunk/docs">' . \esc_html__( 'Documentation', 'activitypub' ) . '</a></p>' . "\n" .
			'<p><a href="https://github.com/Automattic/wordpress-activitypub/releases">' . \esc_html__( 'View latest changes', 'activitypub' ) . '</a></p>'
		);
	}

	/**
	 * Adds the ActivityPub help tab to the users page.
	 */
	public static function add_following_help_tab() {
		\get_current_screen()->add_help_tab(
			array(
				'id'      => 'starter-kit',
				'title'   => \__( 'Starter Kits', 'activitypub' ),
				'content' => \sprintf(
					'<h2>%s</h2>' .
					'<p>%s</p>' .
					'<p>%s</p>',
					\__( 'Starter Kits', 'activitypub' ),
					\__( 'Starter kits are curated lists of accounts that help you quickly build your fediverse network. Import a starter kit to automatically follow a collection of interesting accounts in specific topics or communities.', 'activitypub' ),
					// translators: %s: Importer URL.
					\wp_kses_post( \sprintf( \__( 'To import a starter kit, go to <strong>Tools &#8594; Import</strong> and look for <a href="%s">the &#8220;Starter Kit&#8221; option</a>.', 'activitypub' ), \admin_url( 'admin.php?import=starter-kit' ) ) )
				),
			)
		);
	}

	/**
	 * Adds the ActivityPub help tab to the users page.
	 */
	public static function add_users_help_tab() {
		\get_current_screen()->add_help_tab(
			array(
				'id'       => 'activitypub',
				'title'    => \__( 'ActivityPub', 'activitypub' ),
				'content'  => self::get_help_tab_template( 'users' ),
				// Add to the end of the list.
				'priority' => 20,
			)
		);
	}

	/**
	 * Handle 'welcome' query arg.
	 */
	public static function handle_welcome_query_arg() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['welcome'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$welcome_checked = empty( \sanitize_text_field( \wp_unslash( $_GET['welcome'] ) ) ) ? 0 : 1;
			\update_user_meta( \get_current_user_id(), 'activitypub_show_welcome_tab', $welcome_checked );
			\wp_safe_redirect( \admin_url( 'options-general.php?page=activitypub&tab=settings' ) );
			exit;
		}
	}

	/**
	 * Returns an array of recommended plugins for ActivityPub.
	 */
	public static function get_recommended_plugins() {
		$plugins = array();

		if ( ! \is_plugin_active( 'friends/friends.php' ) ) {
			$plugins['friends'] = array(
				'slug'        => 'friends',
				'author'      => 'Alex Kirk',
				'author_url'  => 'https://profiles.wordpress.org/akirk/',
				'icon'        => 'https://ps.w.org/friends/assets/icon-256x256.png',
				'name'        => \__( 'Friends', 'activitypub' ),
				'description' => \__( 'Follow people on Mastodon or similar platforms and display their posts on your WordPress, making your site a true Fediverse instance.', 'activitypub' ),
				'install_url' => \admin_url( 'plugin-install.php?tab=plugin-information&plugin=friends&TB_iframe=true' ),
			);
		}

		if ( ! \is_plugin_active( 'event-bridge-for-activitypub/event-bridge-for-activitypub.php' ) ) {
			$plugins['event_bridge'] = array(
				'slug'        => 'event-bridge-for-activitypub',
				'author'      => 'André Menrath',
				'author_url'  => 'https://profiles.wordpress.org/andremenrath/',
				'icon'        => 'https://ps.w.org/event-bridge-for-activitypub/assets/icon-256x256.gif',
				'name'        => \__( 'Event Bridge for ActivityPub', 'activitypub' ),
				'description' => \__( 'Make your events discoverable and federate them across decentralized platforms like Mastodon or Gancio.', 'activitypub' ),
				'install_url' => \admin_url( 'plugin-install.php?tab=plugin-information&plugin=event-bridge-for-activitypub&TB_iframe=true' ),
			);
		}

		if ( ! \is_plugin_active( 'enable-mastodon-apps/enable-mastodon-apps.php' ) ) {
			$plugins['enable_mastodon_apps'] = array(
				'slug'        => 'enable-mastodon-apps',
				'author'      => 'Alex Kirk',
				'author_url'  => 'https://profiles.wordpress.org/akirk/',
				'icon'        => 'https://ps.w.org/enable-mastodon-apps/assets/icon-256x256.png',
				'name'        => \__( 'Enable Mastodon Apps', 'activitypub' ),
				'description' => \__( 'Allow Mastodon apps to interact with your WordPress site, letting you write posts from your favorite app.', 'activitypub' ),
				'install_url' => \admin_url( 'plugin-install.php?tab=plugin-information&plugin=enable-mastodon-apps&TB_iframe=true' ),
			);
		}

		if ( ! \is_plugin_active( 'hum/hum.php' ) ) {
			$plugins['hum'] = array(
				'slug'        => 'hum',
				'author'      => 'Will Norris',
				'author_url'  => 'https://profiles.wordpress.org/willnorris/',
				'icon'        => 'https://s.w.org/plugins/geopattern-icon/hum.svg',
				'name'        => \__( 'Hum', 'activitypub' ),
				'description' => \__( 'A personal URL shortener for WordPress, perfect for sharing short links on the Fediverse.', 'activitypub' ),
				'install_url' => \admin_url( 'plugin-install.php?tab=plugin-information&plugin=hum&TB_iframe=true' ),
			);
		}

		if ( ! \is_plugin_active( 'webfinger/webfinger.php' ) ) {
			$plugins['webfinger'] = array(
				'slug'        => 'webfinger',
				'author'      => 'Matthias Pfefferle',
				'author_url'  => 'https://profiles.wordpress.org/pfefferle/',
				'icon'        => 'https://ps.w.org/webfinger/assets/icon-256x256.png',
				'name'        => \__( 'WebFinger', 'activitypub' ),
				'description' => \__( 'WebFinger protocol support for better discovery and compatibility.', 'activitypub' ),
				'install_url' => \admin_url( 'plugin-install.php?tab=plugin-information&plugin=webfinger&TB_iframe=true' ),
			);
		}

		if ( ! \is_plugin_active( 'nodeinfo/nodeinfo.php' ) ) {
			$plugins['nodeinfo'] = array(
				'slug'        => 'nodeinfo',
				'author'      => 'Matthias Pfefferle',
				'author_url'  => 'https://profiles.wordpress.org/pfefferle/',
				'icon'        => 'https://ps.w.org/nodeinfo/assets/icon-256x256.png',
				'name'        => \__( 'NodeInfo', 'activitypub' ),
				'description' => \__( 'Advanced NodeInfo protocol support for better discovery and compatibility.', 'activitypub' ),
				'install_url' => \admin_url( 'plugin-install.php?tab=plugin-information&plugin=nodeinfo&TB_iframe=true' ),
			);
		}

		return $plugins;
	}

	/**
	 * Render recommended plugins as a beautiful, rich showcase for the help tab.
	 */
	public static function render_recommended_plugins_list() {
		$plugins = self::get_recommended_plugins();

		\ob_start();

		echo '<div class="plugin-list widefat">';

		foreach ( $plugins as $plugin ) :
			?>
			<div class="plugin-card plugin-card-<?php echo \esc_attr( $plugin['slug'] ); ?>">
				<div class="plugin-card-top">
					<div class="name column-name">
						<h3>
							<a href="<?php echo \esc_url( $plugin['install_url'] ); ?>" class="thickbox open-plugin-details-modal">
								<?php echo \esc_html( $plugin['name'] ); ?>
								<img src="<?php echo \esc_url( $plugin['icon'] ); ?>" class="plugin-icon" alt="">
							</a>
						</h3>
					</div>
					<div class="action-links">
						<ul class="plugin-action-buttons">
							<li>
								<a href="<?php echo \esc_url( $plugin['install_url'] ); ?>" class="button thickbox open-plugin-details-modal"><?php \esc_html_e( 'More Details', 'activitypub' ); ?></a>
							</li>
						</ul>
					</div>
					<div class="desc column-description">
						<p><?php echo \esc_html( $plugin['description'] ); ?></p>
						<p class="authors"> <cite>By <a href="<?php echo \esc_url( $plugin['author_url'] ); ?>"><?php echo \esc_html( $plugin['author'] ); ?></a></cite></p>
					</div>
				</div>
			</div>
			<?php
		endforeach;

		echo '</div>';

		return \ob_get_clean();
	}

	/**
	 * Loads a help tab template.
	 *
	 * @param string $template_name The template file name (without ".php").
	 * @return string Rendered template output.
	 */
	private static function get_help_tab_template( $template_name ) {
		$template_path = ACTIVITYPUB_PLUGIN_DIR . 'templates/help-tab/' . $template_name . '.php';
		if ( ! file_exists( $template_path ) ) {
			return '';
		}

		ob_start();
		load_template( $template_path, false );
		return ob_get_clean();
	}
}
