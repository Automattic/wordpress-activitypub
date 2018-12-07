<?php
/**
 * Plugin Name: ActivityPub
 * Plugin URI: https://github.com/pfefferle/wordpress-activitypub/
 * Description: The ActivityPub protocol is a decentralized social networking protocol based upon the ActivityStreams 2.0 data format.
 * Version: 0.0.2
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
	require_once dirname( __FILE__ ) . '/includes/class-db-activitypub-actor.php';
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
	add_action( 'webfinger_user_data', array( 'Rest_Activitypub_Webfinger', 'add_webfinger_discovery' ), 10, 3 );

	// Configure activities
	require_once dirname( __FILE__ ) . '/includes/class-activitypub-activities.php';
	add_action( 'activitypub_inbox_follow', array( 'Activitypub_Activities', 'accept' ), 10, 2 );
	add_action( 'activitypub_inbox_follow', array( 'Activitypub_Activities', 'follow' ), 10, 2 );
	add_action( 'activitypub_inbox_unfollow', array( 'Activitypub_Activities', 'unfollow' ), 10, 2 );
}
add_action( 'plugins_loaded', 'activitypub_init' );
