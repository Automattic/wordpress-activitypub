<?php
/**
 * Dashboard Widgets Class.
 *
 * @package Activitypub
 */

namespace Activitypub\WP_Admin;

use Activitypub\Collection\Actors;
use Activitypub\Model\Blog;

use function Activitypub\is_user_type_disabled;
use function Activitypub\user_can_activitypub;

/**
 * Dashboard Widgets Class.
 *
 * Provides all ActivityPub dashboard widgets.
 */
class Dashboard {

	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		\add_action( 'wp_dashboard_setup', array( self::class, 'add_dashboard_widgets' ) );
		\add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_scripts' ) );
	}

	/**
	 * Add Dashboard widgets.
	 */
	public static function add_dashboard_widgets() {
		// Plugin news widget.
		\wp_add_dashboard_widget(
			'activitypub_blog',
			\__( 'ActivityPub Plugin News', 'activitypub' ),
			array( self::class, 'render_news_widget' )
		);

		// Author profile widget.
		if ( user_can_activitypub( \get_current_user_id() ) && ! is_user_type_disabled( 'user' ) ) {
			\wp_add_dashboard_widget(
				'activitypub_profile',
				\__( 'ActivityPub Author Profile', 'activitypub' ),
				array( self::class, 'render_author_profile_widget' )
			);
		}

		// Blog profile widget.
		if ( ! is_user_type_disabled( 'blog' ) ) {
			\wp_add_dashboard_widget(
				'activitypub_blog_profile',
				\__( 'ActivityPub Blog Profile', 'activitypub' ),
				array( self::class, 'render_blog_profile_widget' )
			);
		}

		// Stats widget.
		if ( self::user_can_see_stats() ) {
			\wp_add_dashboard_widget(
				'activitypub_stats',
				\__( 'Fediverse Stats', 'activitypub' ),
				array( self::class, 'render_stats_widget' ),
				null,
				null,
				'normal',
				'high'
			);
		}
	}

	/**
	 * Enqueue scripts for the dashboard widgets.
	 *
	 * @param string $hook The current admin page.
	 */
	public static function enqueue_scripts( $hook ) {
		if ( 'index.php' !== $hook ) {
			return;
		}

		// Only enqueue if user has access to stats.
		if ( ! self::user_can_see_stats() ) {
			return;
		}

		$asset_file = ACTIVITYPUB_PLUGIN_DIR . 'build/dashboard-stats/index.asset.php';

		if ( ! \file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		$dependencies   = $asset['dependencies'];
		$dependencies[] = 'wp-dom-ready';

		\wp_enqueue_script(
			'activitypub-dashboard-stats',
			\plugins_url( 'build/dashboard-stats/index.js', ACTIVITYPUB_PLUGIN_FILE ),
			$dependencies,
			$asset['version'],
			true
		);

		/*
		 * Pass the resolved REST URLs to the JS so it uses get_rest_url() — which
		 * is filtered on environments like WordPress.com that remap the REST
		 * namespace — rather than a hardcoded `/activitypub/1.0/…` path that only
		 * works on the default namespace.
		 */
		\wp_localize_script(
			'activitypub-dashboard-stats',
			'activitypubDashboardStats',
			array(
				'blogStatsUrl' => \get_rest_url( null, ACTIVITYPUB_REST_NAMESPACE . '/admin/stats/' . Actors::BLOG_USER_ID ),
				'userStatsUrl' => \get_rest_url( null, ACTIVITYPUB_REST_NAMESPACE . '/admin/stats/' ),
			)
		);

		\wp_enqueue_style(
			'activitypub-dashboard-stats',
			\plugins_url( 'build/dashboard-stats/style-index.css', ACTIVITYPUB_PLUGIN_FILE ),
			array( 'wp-components' ),
			$asset['version']
		);

		// Add inline script to initialize the widget.
		\wp_add_inline_script(
			'activitypub-dashboard-stats',
			'wp.domReady( function() { if ( activitypub && activitypub.dashboardStats ) { activitypub.dashboardStats.initialize( "activitypub-stats-widget-root" ); } } );'
		);
	}

	/**
	 * Check if the current user can see the stats widget.
	 *
	 * @return bool True if user has access.
	 */
	private static function user_can_see_stats() {
		$has_user_access = user_can_activitypub( \get_current_user_id() ) && ! is_user_type_disabled( 'user' );
		$has_blog_access = ! is_user_type_disabled( 'blog' ) && \current_user_can( 'manage_options' );

		return $has_user_access || $has_blog_access;
	}

	/**
	 * Render the ActivityPub.blog news feed widget.
	 */
	public static function render_news_widget() {
		echo '<div class="rss-widget">';
		\wp_widget_rss_output(
			array(
				'url'          => 'https://activitypub.blog/feed/',
				'items'        => 3,
				'show_summary' => 1,
				'show_author'  => 0,
				'show_date'    => 1,
			)
		);
		echo '</div>';
	}

	/**
	 * Render the ActivityPub Author profile widget.
	 */
	public static function render_author_profile_widget() {
		$user = Actors::get_by_id( \get_current_user_id() );
		?>
		<p>
			<?php \esc_html_e( 'People can follow you by using your author name:', 'activitypub' ); ?>
		</p>
		<p><label for="activitypub-user-identifier"><?php \esc_html_e( 'Username', 'activitypub' ); ?></label><input type="text" class="large-text code" id="activitypub-user-identifier" value="<?php echo \esc_attr( $user->get_webfinger() ); ?>" readonly /></p>
		<p><label for="activitypub-user-url"><?php \esc_html_e( 'Profile URL', 'activitypub' ); ?></label><input type="text" class="large-text code" id="activitypub-user-url" value="<?php echo \esc_attr( $user->get_url() ); ?>" readonly /></p>
		<p>
			<?php \esc_html_e( 'Authors who can not access this settings page will find their username on the "Edit Profile" page.', 'activitypub' ); ?>
			<a href="<?php echo \esc_url( \admin_url( '/profile.php#activitypub' ) ); ?>">
			<?php \esc_html_e( 'Customize username on "Edit Profile" page.', 'activitypub' ); ?>
			</a>
		</p>
		<?php
	}

	/**
	 * Render the ActivityPub Blog profile widget.
	 */
	public static function render_blog_profile_widget() {
		$user = new Blog();
		?>
		<p>
			<?php \esc_html_e( 'People can follow your blog by using:', 'activitypub' ); ?>
		</p>
		<p><label for="activitypub-user-identifier"><?php \esc_html_e( 'Username', 'activitypub' ); ?></label><input type="text" class="large-text code" id="activitypub-user-identifier" value="<?php echo \esc_attr( $user->get_webfinger() ); ?>" readonly /></p>
		<p><label for="activitypub-user-url"><?php \esc_html_e( 'Profile URL', 'activitypub' ); ?></label><input type="text" class="large-text code" id="activitypub-user-url" value="<?php echo \esc_attr( $user->get_url() ); ?>" readonly /></p>
		<p>
			<?php \esc_html_e( 'This blog profile will federate all posts written on your blog, regardless of the author who posted it.', 'activitypub' ); ?>
			<?php if ( \current_user_can( 'manage_options' ) ) : ?>
			<a href="<?php echo \esc_url( \admin_url( '/options-general.php?page=activitypub&tab=blog-profile' ) ); ?>">
				<?php \esc_html_e( 'Customize the blog profile.', 'activitypub' ); ?>
			</a>
			<?php endif; ?>
		</p>
		<?php
	}

	/**
	 * Render the stats widget container.
	 */
	public static function render_stats_widget() {
		echo '<div id="activitypub-stats-widget-root"></div>';
	}
}
