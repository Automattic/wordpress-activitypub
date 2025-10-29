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
		$asset_file = include \plugin_dir_path( ACTIVITYPUB_PLUGIN_FILE ) . 'build/social-web/index.asset.php';

		\wp_enqueue_script(
			'activitypub-social-web',
			\plugins_url( 'build/social-web/index.js', ACTIVITYPUB_PLUGIN_FILE ),
			$asset_file['dependencies'],
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
						'siteUrl'   => \site_url(),
						'siteTitle' => \get_bloginfo( 'name' ),
						'adminUrl'  => \admin_url(),
						'restUrl'   => \rest_url(),
						'nonce'     => \wp_create_nonce( 'wp_rest' ),
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
		<div id="activitypub-social-web-root" class="activitypub-social-web-layout" style="background:#f1f1f1;"></div>
		<?php
	}
}
