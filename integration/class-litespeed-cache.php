<?php
/**
 * LiteSpeed Cache integration file.
 *
 * @package Activitypub
 */

namespace Activitypub\Integration;

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
	 * Initialize the integration.
	 */
	public static function init() {
		\add_action( 'activate_litespeed-cache/litespeed-cache.php', array( self::class, 'add_htaccess_rules' ) );
		\add_action( 'deactivate_litespeed-cache/litespeed-cache.php', array( self::class, 'remove_htaccess_rules' ) );

		\add_filter( 'site_status_tests', array( self::class, 'maybe_add_site_health' ) );
	}

	/**
	 * Add the Litespeed Cache htaccess rules.
	 */
	public static function add_htaccess_rules() {
		$added_rules = self::append_with_markers( self::$marker, self::$rules );

		\update_option( self::$option_name, $added_rules );
	}

	/**
	 * Remove the Litespeed Cache htaccess rules.
	 */
	public static function remove_htaccess_rules() {
		self::append_with_markers( self::$marker, '' );

		\delete_option( self::$option_name );
	}

	/**
	 * Maybe add the Litespeed Cache config to the site health.
	 *
	 * @param array $tests The site health tests.
	 *
	 * @return array The site health tests with the Litespeed Cache config test.
	 */
	public static function maybe_add_site_health( $tests ) {
		if ( ! \is_plugin_active( 'litespeed-cache/litespeed-cache.php' ) ) {
			return $tests;
		}

		$tests['direct']['activitypub_test_litespeed_cache_integration'] = array(
			'label' => \__( 'Litespeed Cache Test', 'activitypub' ),
			'test'  => array( self::class, 'test_litespeed_cache_integration' ),
		);

		return $tests;
	}

	/**
	 * Test the Litespeed Cache integration.
	 *
	 * @return array The test results.
	 */
	public static function test_litespeed_cache_integration() {
		$result = array(
			'label'       => \__( 'Compatibility with Litespeed Cache', 'activitypub' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => \__( 'ActivityPub', 'activitypub' ),
				'color' => 'green',
			),
			'description' => \sprintf(
				'<p>%s</p>',
				\__( 'Litespeed Cache is well configured to work with ActivityPub.', 'activitypub' )
			),
			'actions'     => '',
			'test'        => 'test_litespeed_cache_integration',
		);

		if ( 0 === \get_option( self::$option_name, '0' ) ) {
			$result['status']         = 'critical';
			$result['label']          = \__( 'Litespeed Cache might not be properly configured.', 'activitypub' );
			$result['badge']['color'] = 'red';
			$result['description']    = \sprintf(
				'<p>%s</p>',
				\__( 'Litespeed Cache isn&#8217;t currently set up to work with ActivityPub. While this isn&#8217;t a major problem, it&#8217;s a good idea to enable support. Without it, some technical files (like JSON) might accidentally show up in your website&#8217;s cache and be visible to visitors.', 'activitypub' )
			);
			$result['actions']        = \sprintf(
				'<p>%s</p><pre>%s</pre>',
				\__( 'To enable the ActivityPub integration with Litespeed Cache, add the following rules to your <code>.htaccess</code> file:', 'activitypub' ),
				\esc_html( self::$rules )
			);
		}

		return $result;
	}

	/**
	 * Append rules to a file with markers.
	 *
	 * @param string $marker The marker to identify the rules in the file.
	 * @param string $rules  The rules to append.
	 *
	 * @return bool True on success, false on failure.
	 */
	public static function append_with_markers( $marker, $rules ) {
		$htaccess_file = self::get_htaccess_file_path();

		if ( ! \wp_is_writable( $htaccess_file ) ) {
			return false;
		}

		// Ensure get_home_path() is declared.
		require_once ABSPATH . 'wp-admin/includes/file.php';

		global $wp_filesystem;
		\WP_Filesystem();

		$htaccess = $wp_filesystem->get_contents( $htaccess_file );

		if ( strpos( $htaccess, $marker ) !== false ) {
			return \insert_with_markers( $htaccess_file, $marker, $rules );
		}

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
	public static function get_htaccess_file_path() {
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
