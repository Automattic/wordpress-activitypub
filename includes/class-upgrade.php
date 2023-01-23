<?php
namespace Activitypub;

/**
 * ActivityPub Upgrade class
 *
 * @author Greg Ross
 */
class Upgrade {
	/**
	 * Processes the plugin upgrade
	 */
	public static function init() {
		$current_version = get_option( 'activitypub_version' );

		// If this is the first time loading since updating the plugin, do the upgrade routines.
		if( $current_version == false || version_compare( $current_version, ACTIVITYPUB_VERSION, '<' ) ) {
			self::upgrade_database();

			// Check to see if we're older than the first version that stored the version number.
			if( $current_version == false ) {
				// If so, let's convert the old followers format to the new one.
				self::upgrade_followers();
			}
		}

		update_option( 'activitypub_version', ACTIVITYPUB_VERSION );
	}

	/**
	 * Processes the database upgrade
	 */
	public static function upgrade_database() {
    	GLOBAL $wpdb;

    	// Get the db_delta() function.
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	    $charset_collate = $wpdb->get_charset_collate();

	    // Create the followers database table
	    $table_name = $wpdb->prefix . 'ap_followers';

	    $sql = "CREATE TABLE $table_name (
								  			`ID` bigint(20) NOT NULL,
								  			`follower` varchar(255) NOT NULL,
								  			`since` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
											PRIMARY KEY  (follower),
											KEY ID (ID),
											KEY since (since)
										) $charset_collate;";

	    dbDelta( $sql );

	    // Create the follower database table
	    $table_name = $wpdb->prefix . 'ap_follower';

	    $sql = "CREATE TABLE $table_name (
											`follower` varchar(255) NOT NULL,
											`server` varchar(255) NOT NULL,
											`avatar` varchar(255) NOT NULL,
											`profile` varchar(255) NOT NULL,
											`name` varchar(255) NOT NULL,
											`description` text NOT NULL,
											`is_bot` tinyint(1) NOT NULL,
											`last_updated` timestamp NOT NULL DEFAULT current_timestamp(),
											PRIMARY KEY  (follower),
											KEY server (server),
											KEY last_updated (last_updated)
										) $charset_collate;";

	    dbDelta( $sql );

	    // Create the services database table
	    $table_name = $wpdb->prefix . 'ap_services';

	    $sql = "CREATE TABLE $table_name (
											`server` varchar(255) NOT NULL,
											`service` text NOT NULL,
											`version` text NOT NULL,
											`open_reg` tinyint(1) NOT NULL,
											`last_updated` timestamp NOT NULL DEFAULT current_timestamp().
											PRIMARY KEY  (server(255)),
											KEY service (service)
										) $charset_collate;";

	    dbDelta( $sql );
	}

	/**
	 * Processes the followers upgrade from a user option to the database tables.
	 */
	public static function upgrade_followers() {
    	$users = \get_users();

    	// Loop through all the sites users.
    	foreach( $users as $user ) {
    		// Retrieve the list of followers for the user.
    		$followers = \get_user_option( 'activitypub_followers', $user->ID );

    		// Check to see if we have any followers to process.
    		if( $followers != false && is_array( $followers ) ) {
    			// If so, loop through them.
    			foreach( $followers as $follower ) {
    				// Call the standard all followers code to process them.
    				\Activitypub\Peer\Followers::add_follower( $follower, $user->ID );
    			}

    			// Now delete the older followers list.
    			\delete_user_option( $user->ID, 'activitypub_followers' );
    		}
    	}

    }
}
