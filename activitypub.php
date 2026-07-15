<?php
/**
 * Plugin Name: ActivityPub
 * Plugin URI: https://github.com/Automattic/wordpress-activitypub
 * Description: The ActivityPub protocol is a decentralized social networking protocol based upon the ActivityStreams 2.0 data format.
 * Version: 9.0.2
 * Author: Matthias Pfefferle & Automattic
 * Author URI: https://automattic.com/
 * License: MIT
 * License URI: http://opensource.org/licenses/MIT
 * Requires PHP: 7.4
 * Text Domain: activitypub
 * Domain Path: /languages
 *
 * @package Activitypub
 */

namespace Activitypub;

\define( 'ACTIVITYPUB_PLUGIN_VERSION', '9.0.2' );

// Plugin related constants.
\define( 'ACTIVITYPUB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
\define( 'ACTIVITYPUB_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
\define( 'ACTIVITYPUB_PLUGIN_FILE', ACTIVITYPUB_PLUGIN_DIR . basename( __FILE__ ) );
\define( 'ACTIVITYPUB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once __DIR__ . '/includes/class-autoloader.php';
require_once __DIR__ . '/includes/compat.php';
require_once __DIR__ . '/includes/constants.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/functions-activity.php';
require_once __DIR__ . '/includes/functions-comment.php';
require_once __DIR__ . '/includes/functions-federation.php';
require_once __DIR__ . '/includes/functions-media.php';
require_once __DIR__ . '/includes/functions-post.php';
require_once __DIR__ . '/includes/functions-request.php';
require_once __DIR__ . '/includes/functions-url.php';
require_once __DIR__ . '/includes/functions-user.php';
require_once __DIR__ . '/integration/load.php';

Autoloader::register_path( __NAMESPACE__, __DIR__ . '/includes' );

\register_activation_hook( __FILE__, array( Activitypub::class, 'activate' ) );
\register_deactivation_hook( __FILE__, array( Activitypub::class, 'deactivate' ) );
\register_uninstall_hook( __FILE__, array( Activitypub::class, 'uninstall' ) );

/**
 * Check whether one independently initialized plugin module is enabled.
 *
 * Companion plugins may use this gate to replace a domain surface while retaining
 * the official plugin's protocol implementation. Modules default to enabled so the
 * filter is fully backwards compatible.
 *
 * @param string $module Stable module identifier.
 * @return bool Whether the module may initialize.
 */
function is_module_enabled( $module ) {
	/**
	 * Filters whether an ActivityPub module may initialize.
	 *
	 * @param bool   $enabled Whether the module is enabled. Default true.
	 * @param string $module  Module identifier such as `runtime.router` or `rest.inbox`.
	 */
	return (bool) \apply_filters( 'activitypub_module_enabled', true, (string) $module );
}

/**
 * Initialize REST routes.
 */
function rest_init() {
	if ( is_module_enabled( 'rest.server' ) ) {
		Rest\Server::init();
	}
	if ( is_module_enabled( 'rest.actors' ) ) {
		( new Rest\Actors_Controller() )->register_routes();
	}
	if ( is_module_enabled( 'rest.actors_inbox' ) ) {
		( new Rest\Actors_Inbox_Controller() )->register_routes();
	}
	if ( is_module_enabled( 'rest.admin_actions' ) ) {
		( new Rest\Admin\Actions_Controller() )->register_routes();
	}
	if ( is_module_enabled( 'rest.admin_statistics' ) ) {
		( new Rest\Admin\Statistics_Controller() )->register_routes();
	}
	if ( is_module_enabled( 'rest.application' ) ) {
		( new Rest\Application_Controller() )->register_routes();
	}
	if ( is_module_enabled( 'rest.stats_image' ) ) {
		( new Rest\Stats_Image_Controller() )->register_routes();
	}
	if ( is_module_enabled( 'rest.collections' ) ) {
		( new Rest\Collections_Controller() )->register_routes();
	}
	if ( is_module_enabled( 'rest.comments' ) ) {
		( new Rest\Comments_Controller() )->register_routes();
	}
	if ( is_module_enabled( 'rest.followers' ) ) {
		( new Rest\Followers_Controller() )->register_routes();
	}
	if ( is_module_enabled( 'rest.following' ) ) {
		( new Rest\Following_Controller() )->register_routes();
	}
	if ( is_module_enabled( 'rest.liked' ) ) {
		( new Rest\Liked_Controller() )->register_routes();
	}
	if ( is_module_enabled( 'rest.inbox' ) ) {
		( new Rest\Inbox_Controller() )->register_routes();
	}
	if ( is_module_enabled( 'rest.interaction' ) ) {
		( new Rest\Interaction_Controller() )->register_routes();
	}
	if ( is_module_enabled( 'rest.moderators' ) ) {
		( new Rest\Moderators_Controller() )->register_routes();
	}
	if ( \get_option( 'activitypub_api', false ) && is_module_enabled( 'rest.oauth' ) ) {
		( new Rest\OAuth\Authorization_Controller() )->register_routes();
		( new Rest\OAuth\Clients_Controller() )->register_routes();
		( new Rest\OAuth\Token_Controller() )->register_routes();
	}
	if ( is_module_enabled( 'rest.outbox' ) ) {
		( new Rest\Outbox_Controller() )->register_routes();
	}
	if ( is_module_enabled( 'rest.post' ) ) {
		( new Rest\Post_Controller() )->register_routes();
	}
	if ( is_module_enabled( 'rest.replies' ) ) {
		( new Rest\Replies_Controller() )->register_routes();
	}
	if ( is_module_enabled( 'rest.webfinger' ) ) {
		( new Rest\Webfinger_Controller() )->register_routes();
	}

	// Load NodeInfo endpoints only if blog is public.
	if ( is_blog_public() && is_module_enabled( 'rest.nodeinfo' ) ) {
		( new Rest\Nodeinfo_Controller() )->register_routes();
	}
	if ( is_module_enabled( 'rest.proxy' ) ) {
		( new Rest\Proxy_Controller() )->register_routes();
	}
}
\add_action( 'rest_api_init', __NAMESPACE__ . '\rest_init' );

/**
 * Initialize plugin.
 */
function plugin_init() {
	$modules = array(
		'runtime.activitypub' => array( __NAMESPACE__ . '\Activitypub', 'init' ),
		'runtime.application' => array( __NAMESPACE__ . '\Application', 'init' ),
		'runtime.avatars'     => array( __NAMESPACE__ . '\Avatars', 'init' ),
		'runtime.blurhash'    => array( __NAMESPACE__ . '\Blurhash', 'init' ),
		'runtime.cache'       => array( __NAMESPACE__ . '\Cache', 'init' ),
		'runtime.comment'     => array( __NAMESPACE__ . '\Comment', 'init' ),
		'runtime.dispatcher'  => array( __NAMESPACE__ . '\Dispatcher', 'init' ),
		'runtime.embed'       => array( __NAMESPACE__ . '\Embed', 'init' ),
		'runtime.handler'     => array( __NAMESPACE__ . '\Handler', 'init' ),
		'runtime.hashtag'     => array( __NAMESPACE__ . '\Hashtag', 'init' ),
		'runtime.link'        => array( __NAMESPACE__ . '\Link', 'init' ),
		'runtime.mailer'      => array( __NAMESPACE__ . '\Mailer', 'init' ),
		'runtime.mention'     => array( __NAMESPACE__ . '\Mention', 'init' ),
		'runtime.move'        => array( __NAMESPACE__ . '\Move', 'init' ),
		'runtime.options'     => array( __NAMESPACE__ . '\Options', 'init' ),
		'runtime.post_types'  => array( __NAMESPACE__ . '\Post_Types', 'init' ),
		'runtime.router'      => array( __NAMESPACE__ . '\Router', 'init' ),
		'runtime.search'      => array( __NAMESPACE__ . '\Search', 'init' ),
		'runtime.signature'   => array( __NAMESPACE__ . '\Signature', 'init' ),
	);

	foreach ( $modules as $module => $callback ) {
		if ( is_module_enabled( $module ) ) {
			\add_action( 'init', $callback );
		}
	}

	if ( \get_option( 'activitypub_api', false ) && is_module_enabled( 'runtime.event_stream' ) ) {
		\add_action( 'init', array( __NAMESPACE__ . '\Event_Stream', 'init' ) );
	}
	if ( is_module_enabled( 'runtime.migration' ) ) {
		\add_action( 'init', array( __NAMESPACE__ . '\Migration', 'init' ), 1 );
	}
	// Priority 0 ensures Scheduler hooks are registered before Migration (priority 1) runs.
	if ( is_module_enabled( 'runtime.scheduler' ) ) {
		\add_action( 'init', array( __NAMESPACE__ . '\Scheduler', 'init' ), 0 );
	}
	// Only load OAuth Server if the ActivityPub API is enabled.
	if ( \get_option( 'activitypub_api', false ) && is_module_enabled( 'runtime.oauth' ) ) {
		\add_action( 'init', array( __NAMESPACE__ . '\OAuth\Server', 'init' ) );
	}

	if ( site_supports_blocks() && is_module_enabled( 'runtime.blocks' ) ) {
		\add_action( 'init', array( __NAMESPACE__ . '\Blocks', 'init' ) );
	}

	// Only load relay if relay mode is enabled.
	if ( \get_option( 'activitypub_relay_mode', false ) && is_module_enabled( 'runtime.relay' ) ) {
		\add_action( 'init', array( __NAMESPACE__ . '\Relay', 'init' ) );
	}

	// Load development tools.
	if ( 'local' === wp_get_environment_type() ) {
		$loader_file = __DIR__ . '/local/load.php';
		if ( \file_exists( $loader_file ) && \is_readable( $loader_file ) ) {
			require_once $loader_file;
		}
	}
}
\add_action( 'plugins_loaded', __NAMESPACE__ . '\plugin_init' );

/**
 * Initialize plugin admin.
 */
function plugin_admin_init() {
	if ( ! is_module_enabled( 'admin' ) ) {
		return;
	}

	// Screen Options and Menus are set before `admin_init`.
	\add_action( 'init', array( __NAMESPACE__ . '\WP_Admin\Heartbeat', 'init' ), 9 ); // Before script loader.
	\add_filter( 'init', array( __NAMESPACE__ . '\WP_Admin\Screen_Options', 'init' ) );
	\add_action( 'init', array( __NAMESPACE__ . '\WP_Admin\Menu', 'init' ) );

	\add_action( 'admin_init', array( __NAMESPACE__ . '\WP_Admin\Admin', 'init' ) );
	\add_action( 'admin_init', array( __NAMESPACE__ . '\WP_Admin\Advanced_Settings_Fields', 'init' ) );
	\add_action( 'admin_init', array( __NAMESPACE__ . '\WP_Admin\App', 'init' ), 0 ); // Before admin bar init.
	\add_action( 'admin_init', array( __NAMESPACE__ . '\WP_Admin\Blog_Settings_Fields', 'init' ) );
	\add_action( 'admin_init', array( __NAMESPACE__ . '\WP_Admin\Health_Check', 'init' ) );
	\add_action( 'admin_init', array( __NAMESPACE__ . '\WP_Admin\Settings', 'init' ) );
	\add_action( 'admin_init', array( __NAMESPACE__ . '\WP_Admin\Settings_Fields', 'init' ) );
	\add_action( 'admin_init', array( __NAMESPACE__ . '\WP_Admin\Dashboard', 'init' ) );
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

// Check for CLI env, to add the CLI commands.
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	Cli::register();
}

// Register OAuth login form handler early (before wp-login.php processes).
\add_action( 'login_form_activitypub_authorize', array( __NAMESPACE__ . '\OAuth\Server', 'login_form_authorize' ) );
