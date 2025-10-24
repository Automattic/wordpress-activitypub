<?php
/**
 * LiteSpeed Cache integration file.
 *
 * @package Activitypub
 */

namespace Activitypub\Integration;

use function Activitypub\is_plugin_active;

/**
 * LiteSpeed Cache integration.
 *
 * @see https://wordpress.org/support/topic/avoiding-caching-activitypub-content/
 */
class Litespeed_Cache {

	/**
	 * The rules to add to the htaccess file.
	 *
	 * @var string
	 */
	public static $rules = '<IfModule LiteSpeed>
RewriteEngine On
RewriteCond %{HTTP:Accept} application
RewriteRule ^ - [E=Cache-Control:vary=%{ENV:LSCACHE_VARY_VALUE}+isjson]
</IfModule>';

	/**
	 * The option name to store the htaccess rules.
	 *
	 * @var string
	 */
	public static $option_name = 'activitypub_litespeed_cache_setup';

	/**
	 * The marker to identify the rules in the htaccess file.
	 *
	 * @var string
	 */
	public static $marker = 'ActivityPub LiteSpeed Cache';

	/**
	 * The LiteSpeed Cache plugin slug.
	 *
	 * @var string
	 */
	public static $plugin_slug = 'litespeed-cache/litespeed-cache.php';

	/**
	 * Initialize the integration.
	 */
	public static function init() {
		// Add rules if LiteSpeed Cache is active and rules aren't set.
		if ( is_plugin_active( self::$plugin_slug ) ) {
			if ( ! \get_option( self::$option_name ) ) {
				self::add_htaccess_rules();
			}

			\add_filter( 'site_status_tests', array( self::class, 'add_site_health_test' ) );

			// Remove rules if LiteSpeed Cache is not active but rules were previously set.
		} elseif ( \get_option( self::$option_name ) ) {
			self::remove_htaccess_rules();
		}

		// Clean up when LiteSpeed Cache plugin is deleted.
		\add_action( 'deleted_plugin', array( self::class, 'on_plugin_deleted' ) );
	}

	/**
	 * Clean up htaccess rules when LiteSpeed Cache plugin is deleted.
	 *
	 * @param string $plugin_file Path to the plugin file relative to the plugins directory.
	 */
	public static function on_plugin_deleted( $plugin_file ) {
		if ( self::$plugin_slug === $plugin_file && \get_option( self::$option_name ) ) {
			self::remove_htaccess_rules();
		}
	}

	/**
	 * Add the LiteSpeed Cache htaccess rules.
	 */
	public static function add_htaccess_rules() {
		$added_rules = self::append_with_markers( self::$marker, self::$rules );

		if ( $added_rules ) {
			\update_option( self::$option_name, '1' );
		} else {
			\update_option( self::$option_name, '0' );
		}
	}

	/**
	 * Remove the LiteSpeed Cache htaccess rules.
	 */
	public static function remove_htaccess_rules() {
		self::append_with_markers( self::$marker, '' );

		\delete_option( self::$option_name );
	}

	/**
	 * Add the LiteSpeed Cache config test to site health.
	 *
	 * @param array $tests The site health tests.
	 *
	 * @return array The site health tests with the LiteSpeed Cache config test.
	 */
	public static function add_site_health_test( $tests ) {
		$tests['direct']['activitypub_test_litespeed_cache_integration'] = array(
			'label' => \__( 'LiteSpeed Cache Test', 'activitypub' ),
			'test'  => array( self::class, 'test_litespeed_cache_integration' ),
		);

		return $tests;
	}

	/**
	 * Test the LiteSpeed Cache integration.
	 *
	 * @return array The test results.
	 */
	public static function test_litespeed_cache_integration() {
		$result = array(
			'label'       => \__( 'Compatibility with LiteSpeed Cache', 'activitypub' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => \__( 'ActivityPub', 'activitypub' ),
				'color' => 'green',
			),
			'description' => \sprintf(
				'<p>%s</p>',
				\__( 'LiteSpeed Cache is well configured to work with ActivityPub.', 'activitypub' )
			),
			'actions'     => '',
			'test'        => 'test_litespeed_cache_integration',
		);

		if ( ! \get_option( self::$option_name ) ) {
			$result['status']         = 'critical';
			$result['label']          = \__( 'LiteSpeed Cache might not be properly configured.', 'activitypub' );
			$result['badge']['color'] = 'red';
			$result['description']    = \sprintf(
				'<p>%s</p>',
				\__( 'LiteSpeed Cache isn&#8217;t currently set up to work with ActivityPub. While this isn&#8217;t a major problem, it&#8217;s a good idea to enable support. Without it, some technical files (like JSON) might accidentally show up in your website&#8217;s cache and be visible to visitors.', 'activitypub' )
			);
			$result['actions']        = \sprintf(
				'<p>%s</p><pre>%s</pre>',
				\__( 'To enable the ActivityPub integration with LiteSpeed Cache, add the following rules to your <code>.htaccess</code> file:', 'activitypub' ),
				\esc_html( self::$rules )
			);
		}

		return $result;
	}

	/**
	 * Prepend rules to the top of a file with markers.
	 *
	 * @param string $marker The marker to identify the rules in the file.
	 * @param string $rules  The rules to prepend.
	 *
	 * @return bool True on success, false on failure.
	 */
	private static function append_with_markers( $marker, $rules ) {
		$htaccess_file = self::get_htaccess_file_path();

		if ( ! \wp_is_writable( $htaccess_file ) ) {
			return false;
		}

		// Ensure get_home_path() is declared.
		require_once ABSPATH . 'wp-admin/includes/file.php';

		global $wp_filesystem;
		\WP_Filesystem();

		$htaccess = $wp_filesystem->get_contents( $htaccess_file );

		// If marker exists, remove the old block first.
		if ( strpos( $htaccess, $marker ) !== false ) {
			// Remove existing marker block.
			$pattern  = '/# BEGIN ' . preg_quote( $marker, '/' ) . '.*?# END ' . preg_quote( $marker, '/' ) . '\r?\n?/s';
			$htaccess = preg_replace( $pattern, '', $htaccess );
			$htaccess = trim( $htaccess );
		}

		// If rules are empty, just return (for removal case).
		if ( empty( $rules ) ) {
			return $wp_filesystem->put_contents( $htaccess_file, $htaccess, FS_CHMOD_FILE );
		}

		// Prepend new rules to the top of the file.
		$start_marker = "# BEGIN {$marker}";
		$end_marker   = "# END {$marker}";

		$rules    = $start_marker . PHP_EOL . $rules . PHP_EOL . $end_marker;
		$htaccess = $rules . PHP_EOL . PHP_EOL . $htaccess;

		return $wp_filesystem->put_contents( $htaccess_file, $htaccess, FS_CHMOD_FILE );
	}

	/**
	 * Get the htaccess file.
	 *
	 * @return string|false The htaccess file or false.
	 */
	private static function get_htaccess_file_path() {
		$htaccess_file = false;

		// phpcs:ignore WordPress.PHP.NoSilencedErrors
		if ( @file_exists( \get_home_path() . '.htaccess' ) ) {
			/** The htaccess file resides in ABSPATH */
			$htaccess_file = \get_home_path() . '.htaccess';
		}

		/**
		 * Filter the htaccess file path.
		 *
		 * @param string|false $htaccess_file The htaccess file path.
		 */
		return \apply_filters( 'activitypub_litespeed_cache_htaccess_file', $htaccess_file );
	}
}
