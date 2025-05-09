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
	private static $rules = '<IfModule LiteSpeed>
RewriteEngine On
RewriteCond %{HTTP:Accept} application
RewriteRule ^ - [E=Cache-Control:vary=%{ENV:LSCACHE_VARY_VALUE}+isjson]
</IfModule>';

	/**
	 * The option name to store the htaccess rules.
	 *
	 * @var string
	 */
	private static $option_name = 'activitypub_integration_litespeed_cache_htaccess_rules';

	/**
	 * The marker to identify the rules in the htaccess file.
	 *
	 * @var string
	 */
	private static $marker = 'ActivityPub LiteSpeed Cache';

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
		// Ensure get_home_path() is declared.
		require_once ABSPATH . 'wp-admin/includes/file.php';

		$htaccess_file = get_home_path() . '.htaccess';
		$added_rules   = false;

		if ( \wp_is_writable( $htaccess_file ) ) {
			$added_rules = \insert_with_markers( $htaccess_file, self::$marker, self::$rules );
		}

		\update_option( self::$option_name, $added_rules );
	}

	/**
	 * Remove the Litespeed Cache htaccess rules.
	 */
	public static function remove_htaccess_rules() {
		// Ensure get_home_path() is declared.
		require_once ABSPATH . 'wp-admin/includes/file.php';

		$htaccess_file = get_home_path() . '.htaccess';

		if ( \wp_is_writable( $htaccess_file ) ) {
			\insert_with_markers( $htaccess_file, self::$marker, '' );
		}

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
}
