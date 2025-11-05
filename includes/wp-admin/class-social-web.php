<?php
/**
 * Social Web admin page file.
 *
 * @package Activitypub
 */

namespace Activitypub\WP_Admin;

/**
 * ActivityPub Social Web Admin Page Class.
 */
class Social_Web {

	/**
	 * Remove admin notices from the Social Web page.
	 */
	public static function remove_admin_notices() {
		\remove_all_actions( 'admin_notices' );
		\remove_all_actions( 'all_admin_notices' );

		// Add fullscreen mode body class.
		\add_filter(
			'admin_body_class',
			static function ( $classes ) {
				return "$classes is-fullscreen-mode";
			}
		);
	}

	/**
	 * Enqueue scripts and styles for the Social Web page.
	 */
	public static function enqueue_scripts() {
		// Define paths to preload - must match exact fields from entities.js.
		$preload_paths = array(
			'/?_fields=description,gmt_offset,home,name,site_icon,site_icon_url,site_logo,timezone_string,url,page_for_posts,page_on_front,show_on_front',
			array( '/wp/v2/settings', 'OPTIONS' ),
		);

		// Use rest_preload_api_request to gather the preloaded data.
		$preload_data = array_reduce(
			$preload_paths,
			'rest_preload_api_request',
			array()
		);

		// Register the preloading middleware with wp-api-fetch.
		\wp_add_inline_script(
			'wp-api-fetch',
			sprintf(
				'wp.apiFetch.use( wp.apiFetch.createPreloadingMiddleware( %s ) );',
				wp_json_encode( $preload_data )
			)
		);

		$asset_file = include \plugin_dir_path( ACTIVITYPUB_PLUGIN_FILE ) . 'build/social-web/index.asset.php';

		// Filter out dependencies that aren't registered (like wp-theme in core WP).
		$dependencies = array_filter(
			$asset_file['dependencies'],
			function ( $dependency ) {
				return \wp_script_is( $dependency, 'registered' );
			}
		);

		\wp_enqueue_script(
			'activitypub-social-web',
			\plugins_url( 'build/social-web/index.js', ACTIVITYPUB_PLUGIN_FILE ),
			$dependencies,
			$asset_file['version'],
			true
		);

		\wp_enqueue_style(
			'activitypub-social-web',
			\plugins_url( 'build/social-web/style-index.css', ACTIVITYPUB_PLUGIN_FILE ),
			array( 'wp-components', 'wp-edit-site' ),
			$asset_file['version']
		);

		\wp_add_inline_script(
			'activitypub-social-web',
			'wp.domReady( function() {
				wp.activitypubSocialWeb.initialize( "activitypub-social-web-root" );
			} );'
		);
	}

	/**
	 * Render the Social Web admin page.
	 */
	public static function render_page() {
		?>
		<div id="activitypub-social-web-root" class="activitypub-social-web-layout"></div>
		<?php
	}
}
