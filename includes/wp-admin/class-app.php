<?php
/**
 * App admin page file.
 *
 * @package Activitypub
 */

namespace Activitypub\WP_Admin;

/**
 * ActivityPub App Admin Page Class.
 */
class App {

	/**
	 * Remove admin notices from the App page.
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
	 * Enqueue scripts and styles for the App page.
	 */
	public static function enqueue_scripts() {
		// Define paths to preload - must match exact fields from entities.js.
		$preload_paths = array(
			'/?_fields=description,gmt_offset,home,name,site_icon,site_icon_url,site_logo,timezone_string,url,page_for_posts,page_on_front,show_on_front',
			array( '/wp/v2/settings', 'OPTIONS' ),
		);

		// Use rest_preload_api_request to gather the preloaded data.
		$preload_data = \array_reduce(
			$preload_paths,
			'rest_preload_api_request',
			array()
		);

		// Register the preloading middleware with wp-api-fetch.
		\wp_add_inline_script(
			'wp-api-fetch',
			\sprintf( 'wp.apiFetch.use( wp.apiFetch.createPreloadingMiddleware( %s ) );', \wp_json_encode( $preload_data ) )
		);

		$vendors_asset = include \plugin_dir_path( ACTIVITYPUB_PLUGIN_FILE ) . 'build/app/vendors.asset.php';

		\wp_enqueue_script(
			'activitypub-app-vendors',
			\plugins_url( 'build/app/vendors.js', ACTIVITYPUB_PLUGIN_FILE ),
			$vendors_asset['dependencies'],
			$vendors_asset['version'],
			true
		);

		$asset_file = include \plugin_dir_path( ACTIVITYPUB_PLUGIN_FILE ) . 'build/app/index.asset.php';

		\wp_enqueue_script(
			'activitypub-app',
			\plugins_url( 'build/app/index.js', ACTIVITYPUB_PLUGIN_FILE ),
			array_merge( $asset_file['dependencies'], array( 'activitypub-app-vendors' ) ),
			$asset_file['version'],
			true
		);

		\wp_enqueue_style(
			'activitypub-app',
			\plugins_url( 'build/app/style-index.css', ACTIVITYPUB_PLUGIN_FILE ),
			array( 'wp-components', 'wp-edit-site' ),
			$asset_file['version']
		);

		\wp_add_inline_script(
			'activitypub-app',
			sprintf(
				'wp.domReady( function() {
					wp.activitypubApp.initialize( "activitypub-app-root", %s );
				} );',
				\wp_json_encode(
					array(
						'siteUrl'       => \site_url(),
						'siteTitle'     => \get_bloginfo( 'name' ),
						'adminUrl'      => \admin_url(),
						'restUrl'       => \rest_url(),
						'nonce'         => \wp_create_nonce( 'wp_rest' ),
						'namespace'     => ACTIVITYPUB_REST_NAMESPACE,
						'defaultAvatar' => \plugins_url( 'assets/img/mp.jpg', ACTIVITYPUB_PLUGIN_FILE ),
					)
				)
			)
		);
	}

	/**
	 * Render the App admin page.
	 */
	public static function render_page() {
		?>
		<div id="activitypub-app-root" class="activitypub-app-layout"></div>
		<?php
	}
}
