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
		$vendors_asset = include \plugin_dir_path( ACTIVITYPUB_PLUGIN_FILE ) . 'build/social-web/vendors.asset.php';

		\wp_enqueue_script(
			'activitypub-social-web-vendors',
			\plugins_url( 'build/social-web/vendors.js', ACTIVITYPUB_PLUGIN_FILE ),
			$vendors_asset['dependencies'],
			$vendors_asset['version'],
			true
		);

		$asset_file = include \plugin_dir_path( ACTIVITYPUB_PLUGIN_FILE ) . 'build/social-web/index.asset.php';

		\wp_enqueue_script(
			'activitypub-social-web',
			\plugins_url( 'build/social-web/index.js', ACTIVITYPUB_PLUGIN_FILE ),
			array_merge( $asset_file['dependencies'], array( 'activitypub-social-web-vendors' ) ),
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
			sprintf(
				'wp.domReady( function() {
					wp.activitypubSocialWeb.initialize( "activitypub-social-web-root", %s );
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
	 * Render the Social Web admin page.
	 */
	public static function render_page() {
		?>
		<div id="activitypub-social-web-root" class="activitypub-social-web-layout"></div>
		<?php
	}
}
