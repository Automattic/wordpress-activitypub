<?php
/**
 * Plugin Name: ActivityPub
 * Plugin URI: https://github.com/pfefferle/wordpress-activitypub/
 * Description: The ActivityPub protocol is a decentralized social networking protocol based upon the ActivityStreams 2.0 data format.
 * Version: 1.0.0
 * Author: Matthias Pfefferle
 * Author URI: https://notiz.blog/
 * License: MIT
 * License URI: http://opensource.org/licenses/MIT
 * Text Domain: activitypub
 * Domain Path: /languages
 */

/**
 * Initialize plugin
 */
function activitypub_init() {
	require_once dirname( __FILE__ ) . '/includes/functions.php';

	require_once dirname( __FILE__ ) . '/includes/class-activitypub.php';
	add_filter( 'template_include', array( 'Activity_Pub', 'render_profile' ), 99 );
	add_action( 'webfinger_user_data', array( 'Activity_Pub', 'add_webfinger_discovery' ), 10, 3 );
	add_filter( 'query_vars', array( 'Activity_Pub', 'add_query_vars' ) );
	add_action( 'init', array( 'Activity_Pub', 'add_rewrite_endpoint' ) );
}
add_action( 'plugins_loaded', 'activitypub_init' );
