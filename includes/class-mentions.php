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
			'description' => 'Private and Direct Messages from the fediverse',
			'public' => false,
			'show_ui' => true,//TODO true for dev
			'show_in_admin_bar' => true,//TODO true for dev
			//'show_in_rest' => true,?
			'menu_icon' => 'dashicons-format-chat',//TODO change to ActivityPub logo
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
				//'page-attributes',
				array(
					'post_status' => 'private',
				)
//			'comments',//for public coments? or no that complicates things?
			),
			'hierarchical' => true,//allows thread like comments
			'has_archive' => false,
			'rewrite' => false,
			//'query_var' => false,
			'delete_with_user' => true,
		);
		\register_post_type( 'activitypub_mentions', $post_type_args );

		$private_message_args = array(
				'label'                     => _x( 'Private Message', 'post' ),
				'public'                    => false,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				'label_count'               => _n_noop( 'Private (%s)', 'Private (%s)' ),
				'post_type'                 => array( 'activitypub_mentions' ),
				'show_in_metabox_dropdown'  => true,
				'show_in_inline_dropdown'   => true,
				'dashicon'                  => 'dashicons-businessman',
		);
		\register_post_status( 'private_message', $private_message_args );

		$followers_only_args = array(
				'label'                     => _x( 'Followers only', 'post' ),
				'public'                    => false,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				'label_count'               => _n_noop( 'Followers only (%s)', 'Followers only (%s)' ),
		);
		\register_post_status( 'followers_only', $followers_only_args );

		$unlisted_message_args = array(
				'label'                     => _x( 'Unlisted', 'post' ),
				'public'                    => false,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				'label_count'               => _n_noop( 'Unlisted post (%s)', 'Unlisted post (%s)' ),
		);
		\register_post_status( 'unlisted', $unlisted_message_args );

		$public_message_args = array(
				'label'                     => _x( 'Public', 'post' ),
				'public'                    => false,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				'label_count'               => _n_noop( 'Public post (%s)', 'Public post (%s)' ),
		);
		\register_post_status( 'public', $public_message_args );
  }

	public static function post_type_dump () {
		global $wp_post_types;
		echo '<pre>'; print_r( $wp_post_types ); echo '</pre>';
	}

}
