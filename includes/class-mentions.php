<?php
namespace Activitypub;

/**
 * ActivityPub Mentions
 *
 * @author Django Doucet
 */
class Mentions {

	/**
	 * Initialize the class, registering the Custom Post Type
	 */
	public static function init() {
		\add_action( 'init', array( '\Activitypub\Mentions', 'mentions_init' ), 20 );
	//	\add_action( 'admin_notices', array( '\Activitypub\Mentions', 'post_type_dump' ) );
		\add_filter( 'enter_title_here', array( '\Activitypub\Mentions', 'mentions_title' ), 20 );
	}

	public static function mentions_init() {
		$labels = array(
			'name'                  => _x( 'Mentions', 'Post type general name', 'activitypub' ),
			'singular_name'         => _x( 'Mention', 'Post type singular name', 'activitypub' ),
			'menu_name'             => _x( 'Mentions', 'Admin Menu text', 'activitypub' ),
			'name_admin_bar'        => _x( 'Mention', 'Add New on Toolbar', 'activitypub' ),
			'add_new'               => __( 'Add New', 'activitypub' ),
			'add_new_item'          => __( 'Send Mentions', 'activitypub' ),
			'new_item'              => __( 'New Mentions', 'activitypub' ),
			'edit_item'             => __( 'Edit Mentions', 'activitypub' ),
			'view_item'             => __( 'View Mentions', 'activitypub' ),
			'all_items'             => __( 'All Mentions', 'activitypub' ),
			'search_items'          => __( 'Search Mentions', 'activitypub' ),
			'parent_item_colon'     => __( 'Parent Mentions:', 'activitypub' ),
			'not_found'             => __( 'No Mentions found.', 'activitypub' ),
			'not_found_in_trash'    => __( 'No Mentions found in Trash.', 'activitypub' ),
			'featured_image'        => _x( 'Mentions Cover Image', 'Overrides the “Featured Image” phrase for this post type. Added in 4.3', 'activitypub' ),
			'set_featured_image'    => _x( 'Set cover image', 'Overrides the “Set featured image” phrase for this post type. Added in 4.3', 'activitypub' ),
			'remove_featured_image' => _x( 'Remove cover image', 'Overrides the “Remove featured image” phrase for this post type. Added in 4.3', 'activitypub' ),
			'use_featured_image'    => _x( 'Use as cover image', 'Overrides the “Use as featured image” phrase for this post type. Added in 4.3', 'activitypub' ),
			'archives'              => _x( 'Mention archives', 'The post type archive label used in nav menus. Default “Post Archives”. Added in 4.4', 'activitypub' ),
			'insert_into_item'      => _x( 'Insert into Mention', 'Overrides the “Insert into post”/”Insert into page” phrase (used when inserting media into a post). Added in 4.4', 'activitypub' ),
			'uploaded_to_this_item' => _x( 'Uploaded to this Mention', 'Overrides the “Uploaded to this post”/”Uploaded to this page” phrase (used when viewing media attached to a post). Added in 4.4', 'activitypub' ),
			'filter_items_list'     => _x( 'Filter Mentions list', 'Screen reader text for the filter links heading on the post type listing screen. Default “Filter posts list”/”Filter pages list”. Added in 4.4', 'activitypub' ),
			'items_list_navigation' => _x( 'Mentions list navigation', 'Screen reader text for the pagination heading on the post type listing screen. Default “Posts list navigation”/”Pages list navigation”. Added in 4.4', 'activitypub' ),
			'items_list'            => _x( 'Mentions list', 'Screen reader text for the items list heading on the post type listing screen. Default “Posts list”/”Pages list”. Added in 4.4', 'activitypub' ),
		);

		$post_type_args = array(
			'label' => 'Mentions',//Change menu name to ActivityPub
			'labels' => $labels,
			'description' => 'Mentions to and from the fediverse',
			'public' => true,
			'show_ui' => true,
			'show_in_admin_bar' => true,
			//'show_in_rest' => true, //eventually use tagging via https://developer.wordpress.org/block-editor/components/autocomplete/
			'has_archive' => true,
			'menu_icon' => 'dashicons-format-chat',
			// 'capability_type' => 'activitypub',
			// 'capabilities' => array(
			//  	'publish_posts' => 'publish_ap_posts',
			//	 	'edit_posts' => 'edit_ap_posts',
			//	 	'edit_others_posts' => 'edit_others_ap_posts',
			//	 	'read_private_posts' => 'read_private_ap_posts',
			//	 	'edit_post' => 'edit_ap_posts',
			//	 	'delete_post' => 'delete_ap_posts',
			//	 	'read_post' => 'read_ap_posts',
			// ),
			'supports' => array(
				'title',
				'editor',
				//'thumbnail',
				//'comments',
				//'trackbacks',
				array(
					'post_status' => 'inbox',
				)
			),
			'hierarchical' => true,//allows thread like comments
			'has_archive' => false,
			'rewrite' => false,
			//'query_var' => false,
			'delete_with_user' => true,//delete all personal posts
		);
		\register_post_type( 'mention', $post_type_args );

		$inbox_message_args = array(
			'label'                     => _x( 'Inbox', 'post' ),
			'label_count'               => _n_noop( 'Inbox (%s)', 'Inbox (%s)' ),
			'public'                    => false,
			'protected'       			=> true,
			'exclude_from_search'       => false,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			'post_type'                 => array( 'mention' ),
		);
		\register_post_status( 'inbox', $inbox_message_args );

		$moderation_args = array(
			'label'                     => _x( 'Moderation', 'post' ),
			'label_count'               => _n_noop( 'Moderation (%s)', 'Moderation (%s)' ),
			'public'                    => false,
			'protected'       			=> true,
			'exclude_from_search'       => false,//true?
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			'post_type'                 => array( 'mention' ),
		);
		\register_post_status( 'moderation', $moderation_args );


	}

	/**
	 * Rename Title label
	 * https://developer.wordpress.org/reference/hooks/enter_title_here/
	 */
  	public static function mentions_title ( $input ) {
		if( 'mention' === get_post_type() ) {
            return __( 'Add a Summary / Content Warning', 'activitypub' );
        } else {
            return $input;
        }
	}

	public static function post_type_dump () {
		global $wp_post_types;
		echo '<pre>'; print_r( $wp_post_types ); echo '</pre>';
	}

}
