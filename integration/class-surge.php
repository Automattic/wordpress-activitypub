<?php
/**
 * Surge integration file.
 *
 * @package Activitypub
 */

namespace Activitypub\Integration;

/**
 * Surge Cache integration.
 *
 * This class handles the compatibility with the Surge plugin.
 *
 * @see https://wordpress.org/plugins/surge/
 */
class Surge {

	/**
	 * The pattern to find the Surge cache config constant.
	 *
	 * @var string
	 */
	public static $cache_config_pattern = '/define\s*\(\s*[\'"](WP_CACHE_CONFIG)[\'"]\s*,\s*[\'"](.*?)[\'"]\s*\)\s*;/i';

	/**
	 * Initialize the Surge integration.
	 */
	public static function init() {
		\add_action( 'activate_surge/surge.php', array( self::class, 'add_cache_config' ) );
		\add_action( 'deactivate_surge/surge.php', array( self::class, 'remove_cache_config' ) );

		\add_filter( 'site_status_tests', array( self::class, 'maybe_add_site_health' ) );
	}

	/**
	 * Add the Surge cache config.
	 */
	public static function add_cache_config() {
		// Check if surge is installed and active.
		if ( ! \is_plugin_active( 'surge/surge.php' ) ) {
			return;
		}

		// Check if the constant already exists.
		if ( \defined( 'WP_CACHE_CONFIG' ) ) {
			return;
		}

		$file = self::get_config_file_path();

		if ( ! \wp_is_writable( $file ) ) {
			return;
		}

		if ( ! \function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		global $wp_filesystem;
		\WP_Filesystem();

		$config = $wp_filesystem->get_contents( $file );

		// Check if the constant already exists.
		if ( \preg_match( self::$cache_config_pattern, $config ) ) {
			return;
		}

		// Add a WP_CACHE_CONFIG to wp-config.php.
		$anchor = "/* That's all, stop editing!";
		if ( false !== \strpos( $config, $anchor ) ) {
			$config = \str_replace( $anchor, self::get_cache_config() . PHP_EOL . PHP_EOL . $anchor, $config );
		} elseif ( false !== \strpos( $config, '<?php' ) ) {
			$config = \str_replace( '<?php', '<?php' . PHP_EOL . PHP_EOL . self::get_cache_config() . PHP_EOL, $config );
		}

		$wp_filesystem->put_contents( $file, $config, FS_CHMOD_FILE );
	}

	/**
	 * Remove the Surge cache config.
	 */
	public static function remove_cache_config() {
		if ( ! \defined( 'WP_CACHE_CONFIG' ) ) {
			return;
		}

		$file = self::get_config_file_path();

		if ( ! \wp_is_writable( $file ) ) {
			return;
		}

		global $wp_filesystem;
		\WP_Filesystem();

		$config = $wp_filesystem->get_contents( $file );

		// Remove the define line.
		$config = \preg_replace( PHP_EOL . self::$cache_config_pattern . PHP_EOL, '', $config );

		$wp_filesystem->put_contents( $file, $config, FS_CHMOD_FILE );
	}

	/**
	 * Get the config file.
	 *
	 * @return string|false The config file or false.
	 */
	public static function get_config_file_path() {
		$config_file = false;

		// phpcs:ignore WordPress.PHP.NoSilencedErrors
		if ( @file_exists( ABSPATH . 'wp-config.php' ) ) {

			/** The config file resides in ABSPATH */
			$config_file = ABSPATH . 'wp-config.php';
		// phpcs:ignore WordPress.PHP.NoSilencedErrors
		} elseif ( @file_exists( dirname( ABSPATH ) . '/wp-config.php' ) && ! @file_exists( dirname( ABSPATH ) . '/wp-settings.php' ) ) {

			/** The config file resides one level above ABSPATH but is not part of another installation */
			$config_file = dirname( ABSPATH ) . '/wp-config.php';
		}

		/**
		 * Filter the config file path.
		 *
		 * @param string|false $config_file The config file path.
		 */
		return \apply_filters( 'activitypub_surge_cache_config_file', $config_file );
	}

	/**
	 * Maybe add the Surge cache config to the site health.
	 *
	 * @param array $tests The site health tests.
	 *
	 * @return array The site health tests with the Surge cache config test.
	 */
	public static function maybe_add_site_health( $tests ) {
		if ( ! \is_plugin_active( 'surge/surge.php' ) ) {
			return $tests;
		}

		$tests['direct']['activitypub_test_surge_integration'] = array(
			'label' => \__( 'Surge Test', 'activitypub' ),
			'test'  => array( self::class, 'test_surge_integration' ),
		);

		return $tests;
	}

	/**
	 * Surge integration test.
	 *
	 * @return array The test result.
	 */
	public static function test_surge_integration() {
		$result = array(
			'label'       => \__( 'Compatibility with Surge', 'activitypub' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => \__( 'ActivityPub', 'activitypub' ),
				'color' => 'green',
			),
			'description' => \sprintf(
				'<p>%s</p>',
				\__( 'Surge is well configured to work with ActivityPub.', 'activitypub' )
			),
			'actions'     => '',
			'test'        => 'test_surge_integration',
		);

		if ( ! \defined( 'WP_CACHE_CONFIG' ) ) {
			$result['status']         = 'critical';
			$result['label']          = \__( 'Surge might not be properly configured.', 'activitypub' );
			$result['badge']['color'] = 'red';
			$result['description']    = \sprintf(
				'<p>%s</p>',
				\__( 'Surge isn&#8217;t currently set up to work with ActivityPub. While this isn&#8217;t a major problem, it&#8217;s a good idea to enable support. Without it, some technical files (like JSON) might accidentally show up in your website&#8217;s cache and be visible to visitors.', 'activitypub' )
			);
			$result['actions']        = \sprintf(
				'<p>%s</p>',
				\sprintf(
					// translators: %s: Plugin directory path.
					\__( 'To enable the ActivityPub integration with Surge, add the following line to your <code>wp-config.php</code> file: <br /><code>%s</code>', 'activitypub' ),
					self::get_cache_config()
				)
			);
		}

		return $result;
	}

	/**
	 * Get the cache config.
	 *
	 * @return string The cache config.
	 */
	public static function get_cache_config() {
		return \sprintf( "define( 'WP_CACHE_CONFIG', '%s/integration/surge-cache-config.php' );", \rtrim( ACTIVITYPUB_PLUGIN_DIR, '/' ) );
	}
}
