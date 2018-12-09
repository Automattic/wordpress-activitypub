<?php
/**
 * Plugin Name: ActivityPub
 * Plugin URI: https://github.com/pfefferle/wordpress-activitypub/
 * Description: The ActivityPub protocol is a decentralized social networking protocol based upon the ActivityStreams 2.0 data format.
 * Version: 0.1.0
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
	require_once dirname( __FILE__ ) . '/includes/class-activitypub-signature.php';
	require_once dirname( __FILE__ ) . '/includes/class-activitypub-post.php';
	require_once dirname( __FILE__ ) . '/includes/class-db-activitypub-followers.php';
	require_once dirname( __FILE__ ) . '/includes/functions.php';

	require_once dirname( __FILE__ ) . '/includes/class-activitypub.php';
	add_filter( 'template_include', array( 'Activitypub', 'render_json_template' ), 99 );
	add_filter( 'query_vars', array( 'Activitypub', 'add_query_vars' ) );
	add_action( 'init', array( 'Activitypub', 'add_rewrite_endpoint' ) );

	// Configure the REST API route
	require_once dirname( __FILE__ ) . '/includes/class-rest-activitypub-outbox.php';
	add_action( 'rest_api_init', array( 'Rest_Activitypub_Outbox', 'register_routes' ) );

	require_once dirname( __FILE__ ) . '/includes/class-rest-activitypub-inbox.php';
	add_action( 'rest_api_init', array( 'Rest_Activitypub_Inbox', 'register_routes' ) );

	require_once dirname( __FILE__ ) . '/includes/class-rest-activitypub-followers.php';
	add_action( 'rest_api_init', array( 'Rest_Activitypub_Followers', 'register_routes' ) );

	require_once dirname( __FILE__ ) . '/includes/class-rest-activitypub-webfinger.php';
	add_action( 'rest_api_init', array( 'Rest_Activitypub_Webfinger', 'register_routes' ) );
	add_action( 'webfinger_user_data', array( 'Rest_Activitypub_Webfinger', 'add_webfinger_discovery' ), 10, 3 );

	require_once dirname( __FILE__ ) . '/includes/class-rest-activitypub-nodeinfo.php';
	add_action( 'rest_api_init', array( 'Rest_Activitypub_Nodeinfo', 'register_routes' ) );
	add_filter( 'nodeinfo_data', array( 'Rest_Activitypub_Nodeinfo', 'add_nodeinfo_discovery' ), 10, 2 );
	add_filter( 'nodeinfo2_data', array( 'Rest_Activitypub_Nodeinfo', 'add_nodeinfo2_discovery' ), 10 );

	// Configure activities
	require_once dirname( __FILE__ ) . '/includes/class-activitypub-activities.php';
	add_action( 'activitypub_inbox_follow', array( 'Activitypub_Activities', 'accept' ), 10, 2 );
	add_action( 'activitypub_inbox_follow', array( 'Activitypub_Activities', 'follow' ), 10, 2 );
	add_action( 'activitypub_inbox_unfollow', array( 'Activitypub_Activities', 'unfollow' ), 10, 2 );
}
add_action( 'plugins_loaded', 'activitypub_init' );

/**
 * Add rewrite rules
 */
function activitypub_add_rewrite_rules() {
	if ( ! class_exists( 'Webfinger' ) ) {
		add_rewrite_rule( '^.well-known/webfinger', 'index.php?rest_route=/activitypub/1.0/webfinger', 'top' );
	}

	if ( ! class_exists( 'Nodeinfo' ) ) {
		add_rewrite_rule( '^.well-known/nodeinfo', 'index.php?rest_route=/activitypub/1.0/nodeinfo/discovery', 'top' );
		add_rewrite_rule( '^.well-known/x-nodeinfo2', 'index.php?rest_route=/activitypub/1.0/nodeinfo2', 'top' );
	}
}
add_action( 'init', 'activitypub_add_rewrite_rules', 1 );

/**
 * Flush rewrite rules;
 */
function activitypub_flush_rewrite_rules() {
	activitypub_add_rewrite_rules();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'activitypub_flush_rewrite_rules' );
register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );
