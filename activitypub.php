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
	\defined( 'ACTIVITYPUB_CUSTOM_POST_CONTENT' ) || \define( 'ACTIVITYPUB_CUSTOM_POST_CONTENT', "<p><strong>[ap_title]</strong></p>\n\n[ap_content]\n\n<p>[ap_hashtags]</p>\n\n<p>[ap_shortlink]</p>" );

	\define( 'ACTIVITYPUB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
	\define( 'ACTIVITYPUB_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
	\define( 'ACTIVITYPUB_PLUGIN_FILE', plugin_dir_path( __FILE__ ) . '/' . basename( __FILE__ ) );

	\define( 'ACTIVITYPUB_OBJECT', 'ACTIVITYPUB_OBJECT' );

	require_once \dirname( __FILE__ ) . '/includes/table/class-followers.php';
	require_once \dirname( __FILE__ ) . '/includes/class-http.php';
	require_once \dirname( __FILE__ ) . '/includes/class-signature.php';
	require_once \dirname( __FILE__ ) . '/includes/class-webfinger.php';
	require_once \dirname( __FILE__ ) . '/includes/peer/class-followers.php';
	require_once \dirname( __FILE__ ) . '/includes/functions.php';

	require_once \dirname( __FILE__ ) . '/includes/model/class-activity.php';
	require_once \dirname( __FILE__ ) . '/includes/model/class-post.php';
	require_once \dirname( __FILE__ ) . '/includes/model/class-follower.php';

	require_once \dirname( __FILE__ ) . '/includes/class-migration.php';
	Migration::init();

	require_once \dirname( __FILE__ ) . '/includes/class-activity-dispatcher.php';
	Activity_Dispatcher::init();

	require_once \dirname( __FILE__ ) . '/includes/class-activitypub.php';
	Activitypub::init();

	require_once \dirname( __FILE__ ) . '/includes/collection/class-followers.php';
	Collection\Followers::init();

	// Configure the REST API route
	require_once \dirname( __FILE__ ) . '/includes/rest/class-outbox.php';
	Rest\Outbox::init();

	require_once \dirname( __FILE__ ) . '/includes/rest/class-inbox.php';
	Rest\Inbox::init();

	require_once \dirname( __FILE__ ) . '/includes/rest/class-followers.php';
	Rest\Followers::init();

	require_once \dirname( __FILE__ ) . '/includes/rest/class-following.php';
	Rest\Following::init();

	require_once \dirname( __FILE__ ) . '/includes/rest/class-webfinger.php';
	Rest\Webfinger::init();

	// load NodeInfo endpoints only if blog is public
	if ( true === (bool) \get_option( 'blog_public', 1 ) ) {
		require_once \dirname( __FILE__ ) . '/includes/rest/class-nodeinfo.php';
		Rest\NodeInfo::init();
	}

	require_once \dirname( __FILE__ ) . '/includes/class-admin.php';
	Admin::init();

	require_once \dirname( __FILE__ ) . '/includes/class-hashtag.php';
	Hashtag::init();

	require_once \dirname( __FILE__ ) . '/includes/class-shortcodes.php';
	Shortcodes::init();

	require_once \dirname( __FILE__ ) . '/includes/class-mention.php';
	Mention::init();

	require_once \dirname( __FILE__ ) . '/includes/class-debug.php';
	Debug::init();

	require_once \dirname( __FILE__ ) . '/includes/class-health-check.php';
	Health_Check::init();

	if ( \WP_DEBUG ) {
		require_once \dirname( __FILE__ ) . '/includes/debug.php';
	}
}
\add_action( 'plugins_loaded', '\Activitypub\init' );

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
\add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), '\Activitypub\plugin_settings_link' );

/**
 * Add rewrite rules
 */
function add_rewrite_rules() {
	if ( ! \class_exists( 'Webfinger' ) ) {
		\add_rewrite_rule( '^.well-known/webfinger', 'index.php?rest_route=/activitypub/1.0/webfinger', 'top' );
	}

	if ( ! \class_exists( 'Nodeinfo' ) || ! (bool) \get_option( 'blog_public', 1 ) ) {
		\add_rewrite_rule( '^.well-known/nodeinfo', 'index.php?rest_route=/activitypub/1.0/nodeinfo/discovery', 'top' );
		\add_rewrite_rule( '^.well-known/x-nodeinfo2', 'index.php?rest_route=/activitypub/1.0/nodeinfo2', 'top' );
	}

	\add_rewrite_endpoint( 'activitypub', EP_AUTHORS | EP_PERMALINK | EP_PAGES );
}
\add_action( 'init', '\Activitypub\add_rewrite_rules', 1 );

/**
 * Flush rewrite rules;
 */
function flush_rewrite_rules() {
	\Activitypub\add_rewrite_rules();
	\flush_rewrite_rules();
}
\register_activation_hook( __FILE__, '\Activitypub\flush_rewrite_rules' );
\register_deactivation_hook( __FILE__, '\flush_rewrite_rules' );

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
