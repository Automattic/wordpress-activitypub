<?php
/**
 * Plugin Name: ActivityPub
 * Plugin URI: https://github.com/pfefferle/wordpress-activitypub/
 * Description: The ActivityPub protocol is a decentralized social networking protocol based upon the ActivityStreams 2.0 data format.
 * Version: 0.0.1
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
	add_filter( 'template_include', array( 'Activitypub', 'render_profile' ), 99 );
	add_action( 'webfinger_user_data', array( 'Activitypub', 'add_webfinger_discovery' ), 10, 3 );
	add_filter( 'query_vars', array( 'Activitypub', 'add_query_vars' ) );
	add_action( 'init', array( 'Activitypub', 'add_rewrite_endpoint' ) );

	require_once dirname( __FILE__ ) . '/includes/class-activitypub-outbox.php';
	// Configure the REST API route
	add_action( 'rest_api_init', array( 'Activitypub_Outbox', 'register_routes' ) );
}
add_action( 'plugins_loaded', 'activitypub_init' );
