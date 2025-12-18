<?php
/**
 * Statistics Dashboard Widget Class.
 *
 * @package Activitypub
 */

namespace Activitypub\WP_Admin;

use function Activitypub\is_user_type_disabled;
use function Activitypub\user_can_activitypub;

/**
 * Statistics Dashboard Widget Class.
 *
 * Provides a React-based dashboard widget for ActivityPub statistics.
 */
class Statistics_Dashboard {

	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		\add_action( 'wp_dashboard_setup', array( self::class, 'add_dashboard_widgets' ) );
		\add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_scripts' ) );
	}

	/**
	 * Enqueue scripts for the dashboard widget.
	 *
	 * @param string $hook The current admin page.
	 */
	public static function enqueue_scripts( $hook ) {
		if ( 'index.php' !== $hook ) {
			return;
		}

		// Only enqueue if user has access.
		if ( ! self::user_has_access() ) {
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

		\wp_enqueue_style(
			'activitypub-dashboard-stats',
			\plugins_url( 'build/dashboard-stats/style-index.css', ACTIVITYPUB_PLUGIN_FILE ),
			array( 'wp-components' ),
			$asset['version']
		);

		// Add inline script to initialize the widget.
		\wp_add_inline_script(
			'activitypub-dashboard-stats',
			'wp.domReady( function() { activitypub.dashboardStats.initialize( "activitypub-stats-widget-root" ); } );'
		);
	}

	/**
	 * Check if the current user has access to at least one actor type.
	 *
	 * @return bool True if user has access.
	 */
	private static function user_has_access() {
		$has_user_access = user_can_activitypub( \get_current_user_id() ) && ! is_user_type_disabled( 'user' );
		$has_blog_access = ! is_user_type_disabled( 'blog' ) && \current_user_can( 'manage_options' );

		return $has_user_access || $has_blog_access;
	}

	/**
	 * Add dashboard widgets.
	 */
	public static function add_dashboard_widgets() {
		if ( ! self::user_has_access() ) {
			return;
		}

		\wp_add_dashboard_widget(
			'activitypub_stats',
			\__( 'Fediverse Stats', 'activitypub' ),
			array( self::class, 'render_widget' ),
			null,
			null,
			'normal',
			'high'
		);
	}

	/**
	 * Render the widget container.
	 */
	public static function render_widget() {
		echo '<div id="activitypub-stats-widget-root"></div>';
	}
}
