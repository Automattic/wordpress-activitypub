<?php
/**
 * Plugin Name: ActivityPub
 * Plugin URI: https://github.com/Automattic/wordpress-activitypub
 * Description: The ActivityPub protocol is a decentralized social networking protocol based upon the ActivityStreams 2.0 data format.
 * Version: 7.5.0
 * Author: Matthias Pfefferle & Automattic
 * Author URI: https://automattic.com/
 * License: MIT
 * License URI: http://opensource.org/licenses/MIT
 * Requires PHP: 7.2
 * Text Domain: activitypub
 * Domain Path: /languages
 *
 * @package Activitypub
 */

namespace Activitypub;

\define( 'ACTIVITYPUB_PLUGIN_VERSION', '7.5.0' );

// Plugin related constants.
\define( 'ACTIVITYPUB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
\define( 'ACTIVITYPUB_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
\define( 'ACTIVITYPUB_PLUGIN_FILE', ACTIVITYPUB_PLUGIN_DIR . basename( __FILE__ ) );
\define( 'ACTIVITYPUB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once __DIR__ . '/includes/class-autoloader.php';
require_once __DIR__ . '/includes/compat.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/constants.php';
require_once __DIR__ . '/integration/load.php';

Autoloader::register_path( __NAMESPACE__, __DIR__ . '/includes' );

\register_activation_hook( __FILE__, array( Activitypub::class, 'activate' ) );
\register_deactivation_hook( __FILE__, array( Activitypub::class, 'deactivate' ) );
\register_uninstall_hook( __FILE__, array( Activitypub::class, 'uninstall' ) );

/**
 * Initialize REST routes.
 */
function rest_init() {
	Rest\Server::init();
	( new Rest\Actors_Controller() )->register_routes();
	( new Rest\Actors_Inbox_Controller() )->register_routes();
	( new Rest\Application_Controller() )->register_routes();
	( new Rest\Collections_Controller() )->register_routes();
	( new Rest\Comments_Controller() )->register_routes();
	( new Rest\Followers_Controller() )->register_routes();
	( new Rest\Following_Controller() )->register_routes();
	( new Rest\Inbox_Controller() )->register_routes();
	( new Rest\Interaction_Controller() )->register_routes();
	( new Rest\Moderators_Controller() )->register_routes();
	( new Rest\Outbox_Controller() )->register_routes();
	( new Rest\Post_Controller() )->register_routes();
	( new Rest\Replies_Controller() )->register_routes();
	( new Rest\Webfinger_Controller() )->register_routes();

	// Load NodeInfo endpoints only if blog is public.
	if ( is_blog_public() ) {
		( new Rest\Nodeinfo_Controller() )->register_routes();
	}
}
\add_action( 'rest_api_init', __NAMESPACE__ . '\rest_init' );

/**
 * Initialize plugin.
 */
function plugin_init() {
	\add_action( 'init', array( __NAMESPACE__ . '\Activitypub', 'init' ) );
	\add_action( 'init', array( __NAMESPACE__ . '\Comment', 'init' ) );
	\add_action( 'init', array( __NAMESPACE__ . '\Dispatcher', 'init' ) );
	\add_action( 'init', array( __NAMESPACE__ . '\Embed', 'init' ) );
	\add_action( 'init', array( __NAMESPACE__ . '\Handler', 'init' ) );
	\add_action( 'init', array( __NAMESPACE__ . '\Hashtag', 'init' ) );
	\add_action( 'init', array( __NAMESPACE__ . '\Link', 'init' ) );
	\add_action( 'init', array( __NAMESPACE__ . '\Mailer', 'init' ) );
	\add_action( 'init', array( __NAMESPACE__ . '\Mention', 'init' ) );
	\add_action( 'init', array( __NAMESPACE__ . '\Migration', 'init' ), 1 );
	\add_action( 'init', array( __NAMESPACE__ . '\Move', 'init' ) );
	\add_action( 'init', array( __NAMESPACE__ . '\Options', 'init' ) );
	\add_action( 'init', array( __NAMESPACE__ . '\Post_Types', 'init' ) );
	\add_action( 'init', array( __NAMESPACE__ . '\Router', 'init' ) );
	\add_action( 'init', array( __NAMESPACE__ . '\Scheduler', 'init' ) );
	\add_action( 'init', array( __NAMESPACE__ . '\Search', 'init' ) );
	\add_action( 'init', array( __NAMESPACE__ . '\Signature', 'init' ) );

	if ( site_supports_blocks() ) {
		\add_action( 'init', array( __NAMESPACE__ . '\Blocks', 'init' ) );
	}

	// Load development tools.
	if ( 'local' === wp_get_environment_type() ) {
		$loader_file = __DIR__ . '/local/load.php';
		if ( \file_exists( $loader_file ) && \is_readable( $loader_file ) ) {
			require_once $loader_file;
		}
	}

	if ( \defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		$debug_file = __DIR__ . '/includes/debug.php';
		if ( \file_exists( $debug_file ) && \is_readable( $debug_file ) ) {
			require_once $debug_file;
			Debug::init();
		}
	}
}
\add_action( 'plugins_loaded', __NAMESPACE__ . '\plugin_init' );

/**
 * Initialize plugin admin.
 */
function plugin_admin_init() {
	// Screen Options and Menus are set before `admin_init`.
	\add_action( 'init', array( __NAMESPACE__ . '\WP_Admin\Heartbeat', 'init' ), 9 ); // Before script loader.
	\add_filter( 'init', array( __NAMESPACE__ . '\WP_Admin\Screen_Options', 'init' ) );
	\add_action( 'admin_menu', array( __NAMESPACE__ . '\WP_Admin\Menu', 'admin_menu' ) );

	\add_action( 'admin_init', array( __NAMESPACE__ . '\WP_Admin\Admin', 'init' ) );
	\add_action( 'admin_init', array( __NAMESPACE__ . '\WP_Admin\Advanced_Settings_Fields', 'init' ) );
	\add_action( 'admin_init', array( __NAMESPACE__ . '\WP_Admin\Blog_Settings_Fields', 'init' ) );
	\add_action( 'admin_init', array( __NAMESPACE__ . '\WP_Admin\Health_Check', 'init' ) );
	\add_action( 'admin_init', array( __NAMESPACE__ . '\WP_Admin\Settings', 'init' ) );
	\add_action( 'admin_init', array( __NAMESPACE__ . '\WP_Admin\Settings_Fields', 'init' ) );
	\add_action( 'admin_init', array( __NAMESPACE__ . '\WP_Admin\User_Settings_Fields', 'init' ) );
	\add_action( 'admin_init', array( __NAMESPACE__ . '\WP_Admin\Welcome_Fields', 'init' ) );

	if ( defined( 'WP_LOAD_IMPORTERS' ) && WP_LOAD_IMPORTERS ) {
		require_once __DIR__ . '/includes/wp-admin/import/load.php';
		\add_action( 'admin_init', __NAMESPACE__ . '\WP_Admin\Import\load' );
	}
}
\add_action( 'plugins_loaded', __NAMESPACE__ . '\plugin_admin_init' );

/**
 * Redirect to the welcome page after plugin activation.
 *
 * @param string $plugin The plugin basename.
 */
function activation_redirect( $plugin ) {
	if ( ACTIVITYPUB_PLUGIN_BASENAME === $plugin ) {
		\wp_safe_redirect( \admin_url( 'options-general.php?page=activitypub' ) );
		exit;
	}
}
\add_action( 'activated_plugin', __NAMESPACE__ . '\activation_redirect' );

// Check for CLI env, to add the CLI commands.Add commentMore actions.
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	\WP_CLI::add_command(
		'activitypub',
		'\Activitypub\Cli',
		array(
			'shortdesc' => 'ActivityPub related commands to manage plugin functionality and the federation of posts and comments.',
		)
	);
}
