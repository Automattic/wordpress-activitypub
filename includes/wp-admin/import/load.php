<?php
/**
 * Importers file.
 *
 * @package Activitypub
 */

namespace Activitypub\WP_Admin\Import;

/**
 * Load importers.
 */
function load() {
	require_once ABSPATH . 'wp-admin/includes/import.php';

	if ( ! class_exists( 'WP_Importer' ) ) {
		$class_wp_importer = ABSPATH . 'wp-admin/includes/class-wp-importer.php';
		if ( file_exists( $class_wp_importer ) ) {
			require_once $class_wp_importer;
		}
	}

	\register_importer(
		'mastodon',
		\__( 'Mastodon (Beta)', 'activitypub' ),
		\__( 'Import content from Mastodon.', 'activitypub' ),
		array( __NAMESPACE__ . '\Mastodon', 'dispatch' )
	);
}
