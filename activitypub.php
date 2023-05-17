<?php
/**
 * Plugin Name: ActivityPub
 * Plugin URI: https://github.com/pfefferle/wordpress-activitypub/
 * Description: The ActivityPub protocol is a decentralized social networking protocol based upon the ActivityStreams 2.0 data format.
 * Version: 0.17.0
 * Author: Matthias Pfefferle & Automattic
 * Author URI: https://automattic.com/
 * License: MIT
 * License URI: http://opensource.org/licenses/MIT
 * Requires PHP: 5.6
 * Text Domain: activitypub
 * Domain Path: /languages
 */

namespace Activitypub;

/**
 * Initialize plugin
 */
function init() {
	\defined( 'ACTIVITYPUB_EXCERPT_LENGTH' ) || \define( 'ACTIVITYPUB_EXCERPT_LENGTH', 400 );
	\defined( 'ACTIVITYPUB_SHOW_PLUGIN_RECOMMENDATIONS' ) || \define( 'ACTIVITYPUB_SHOW_PLUGIN_RECOMMENDATIONS', true );
	\defined( 'ACTIVITYPUB_MAX_IMAGE_ATTACHMENTS' ) || \define( 'ACTIVITYPUB_MAX_IMAGE_ATTACHMENTS', 3 );
	\defined( 'ACTIVITYPUB_HASHTAGS_REGEXP' ) || \define( 'ACTIVITYPUB_HASHTAGS_REGEXP', '(?:(?<=\s)|(?<=<p>)|(?<=<br>)|^)#([A-Za-z0-9_]+)(?:(?=\s|[[:punct:]]|$))' );
	\defined( 'ACTIVITYPUB_USERNAME_REGEXP' ) || \define( 'ACTIVITYPUB_USERNAME_REGEXP', '(?:([A-Za-z0-9_-]+)@((?:[A-Za-z0-9_-]+\.)+[A-Za-z]+))' );
	\defined( 'ACTIVITYPUB_CUSTOM_POST_CONTENT' ) || \define( 'ACTIVITYPUB_CUSTOM_POST_CONTENT', "<strong>[ap_title]</strong>\n\n[ap_content]\n\n[ap_hashtags]\n\n[ap_shortlink]" );
	\defined( 'ACTIVITYPUB_REST_NAMESPACE' ) || \define( 'ACTIVITYPUB_REST_NAMESPACE', 'activitypub/1.0' );

	\define( 'ACTIVITYPUB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
	\define( 'ACTIVITYPUB_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
	\define( 'ACTIVITYPUB_PLUGIN_FILE', plugin_dir_path( __FILE__ ) . '/' . basename( __FILE__ ) );

	Migration::init();
	Activitypub::init();
	Activity_Dispatcher::init();
	Collection\Followers::init();

	// Configure the REST API route
	Rest\Outbox::init();
	Rest\Inbox::init();
	Rest\Followers::init();
	Rest\Following::init();
	Rest\Webfinger::init();

	Admin::init();
	Hashtag::init();
	Shortcodes::init();
	Mention::init();
	Health_Check::init();
	Scheduler::init();
}
\add_action( 'plugins_loaded', '\Activitypub\init' );

/**
 * Class Autoloader
 */
spl_autoload_register(
	function ( $full_class ) {
		$base_dir = \dirname( __FILE__ ) . '/includes/';
		$base     = 'activitypub';

		$class = strtolower( $full_class );

		if ( strncmp( $class, $base, strlen( $base ) ) === 0 ) {
			$class = str_replace( 'activitypub\\', '', $class );

			if ( false !== strpos( $class, '\\' ) ) {
				$parts    = explode( '\\', $class );
				$class    = array_pop( $parts );
				$sub_dir  = implode( '/', $parts );
				$base_dir = $base_dir . $sub_dir . '/';
			}

			$filename = 'class-' . strtr( $class, '_', '-' );
			$file     = $base_dir . $filename . '.php';

			if ( file_exists( $file ) && is_readable( $file ) ) {
				require_once $file;
			} else {
				// translators: %s is the class name
				\wp_die( sprintf( esc_html__( 'Required class not found or not readable: %s', 'activitypub' ), esc_html( $full_class ) ) );
			}
		}
	}
);

require_once \dirname( __FILE__ ) . '/includes/functions.php';

// load NodeInfo endpoints only if blog is public
if ( \get_option( 'blog_public', 1 ) ) {
	Rest\NodeInfo::init();
}

$debug_file = \dirname( __FILE__ ) . '/includes/debug.php';
if ( \WP_DEBUG && file_exists( $debug_file ) && is_readable( $debug_file ) ) {
	require_once $debug_file;
	Debug::init();
}

/**
 * Add plugin settings link
 */
function plugin_settings_link( $actions ) {
	$settings_link[] = \sprintf(
		'<a href="%1s">%2s</a>',
		\menu_page_url( 'activitypub', false ),
		\__( 'Settings', 'activitypub' )
	);

	return \array_merge( $settings_link, $actions );
}
\add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), __NAMESPACE__ . '\plugin_settings_link' );

\register_activation_hook(
	__FILE__,
	array(
		__NAMESPACE__ . '\Activitypub',
		'activate',
	)
);

\register_deactivation_hook(
	__FILE__,
	array(
		__NAMESPACE__ . '\Activitypub',
		'deactivate',
	)
);

\register_uninstall_hook(
	__FILE__,
	array(
		__NAMESPACE__ . '\Activitypub',
		'uninstall',
	)
);

/**
 * Only load code that needs BuddyPress to run once BP is loaded and initialized.
 */
function enable_buddypress_features() {
	require_once \dirname( __FILE__ ) . '/integration/class-buddypress.php';
	Integration\Buddypress::init();
}
add_action( 'bp_include', '\Activitypub\enable_buddypress_features' );

/**
 * `get_plugin_data` wrapper
 *
 * @return array The plugin metadata array
 */
function get_plugin_meta( $default_headers = array() ) {
	if ( ! $default_headers ) {
		$default_headers = array(
			'Name'        => 'Plugin Name',
			'PluginURI'   => 'Plugin URI',
			'Version'     => 'Version',
			'Description' => 'Description',
			'Author'      => 'Author',
			'AuthorURI'   => 'Author URI',
			'TextDomain'  => 'Text Domain',
			'DomainPath'  => 'Domain Path',
			'Network'     => 'Network',
			'RequiresWP'  => 'Requires at least',
			'RequiresPHP' => 'Requires PHP',
			'UpdateURI'   => 'Update URI',
		);
	}

	return \get_file_data( __FILE__, $default_headers, 'plugin' );
}

/**
 * Plugin Version Number used for caching.
 */
function get_plugin_version() {
	$meta = get_plugin_meta( array( 'Version' => 'Version' ) );

	return $meta['Version'];
}
