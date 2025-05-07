<?php
/**
 * Surge integration file.
 *
 * @package Activitypub
 */

namespace Activitypub\Integration;

use function Activitypub\user_can_activitypub;

/**
 * Surge Cache integration.
 *
 * This class handles the compatibility with the Surge plugin.
 *
 * @see https://wordpress.org/plugins/surge/
 */
class Surge {
	/**
	 * The define for the Surge cache config.
	 *
	 * @var string
	 */
	private static $cache_config = 'define( \'WP_CACHE_CONFIG\', \'' . ACTIVITYPUB_PLUGIN_DIR . 'integration/surge-cache-config.php\' );';

	/**
	 * The pattern to find the define for the Surge cache config.
	 *
	 * @var string
	 */
	private static $cache_config_pattern = '/define\s*\(\s*[\'"](WP_CACHE_CONFIG)[\'"]\s*,\s*[\'"](.*?)[\'"]\s*\)\s*;/i';

	/**
	 * Initialize the Surge integration.
	 */
	public static function init() {
		if ( \is_plugin_active( 'surge/surge.php' ) && ! \defined( 'WP_CACHE_CONFIG' ) ) {
			self::add_cache_config();
		}

		if ( ! \is_plugin_active( 'surge/surge.php' ) && \defined( 'WP_CACHE_CONFIG' ) ) {
			self::remove_cache_config();
		}
	}

	/**
	 * Add the Surge cache config.
	 */
	public static function add_cache_config() {
		$file = self::get_config_file();

		if ( ! \wp_is_writable( $file ) ) {
			return;
		}

		if ( ! \function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		global $wp_filesystem;
		\WP_Filesystem();

		$config = $wp_filesystem->get_contents( $file );

		// Check if the define already exists.
		if ( preg_match( self::$cache_config_pattern, $config ) ) {
			return;
		}

		// Add a WP_CACHE_CONFIG to wp-config.php.
		$anchor = "/* That's all, stop editing!";
		if ( false !== \strpos( $config, $anchor ) ) {
			$config = \str_replace( $anchor, self::$cache_config . "\n\n" . $anchor, $config );
		} elseif ( false !== \strpos( $config, '<?php' ) ) {
			$config = \str_replace( '<?php', "<?php\n\n" . self::$cache_config . "\n", $config );
		}

		$wp_filesystem->put_contents( $file, $config, FS_CHMOD_FILE );
	}

	/**
	 * Remove the Surge cache config.
	 */
	public static function remove_cache_config() {
		$file = self::get_config_file();

		global $wp_filesystem;
		\WP_Filesystem();

		$config = $wp_filesystem->get_contents( $file );

		// Remove the define line.
		$config = preg_replace( self::$cache_config_pattern, '', $config );

		$wp_filesystem->put_contents( $file, $config, FS_CHMOD_FILE );
	}

	/**
	 * Get the config file.
	 *
	 * @return string|false The config file or false.
	 */
	public static function get_config_file() {
		// phpcs:ignore
		if ( @file_exists( ABSPATH . 'wp-config.php' ) ) {

			/** The config file resides in ABSPATH */
			$config_file = ABSPATH . 'wp-config.php';
		// phpcs:ignore
		} elseif ( @file_exists( dirname( ABSPATH ) . '/wp-config.php' ) && ! @file_exists( dirname( ABSPATH ) . '/wp-settings.php' ) ) {

			/** The config file resides one level above ABSPATH but is not part of another installation */
			$config_file = dirname( ABSPATH ) . '/wp-config.php';
		}

		return \apply_filters( 'activitypub_surge_cache_config_file', $config_file );
	}
}
