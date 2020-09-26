<?php
namespace Activitypub\Table;

if ( ! \class_exists( '\WP_List_Table' ) ) {
		require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Mentions_List extends \WP_List_Table {

	/** Class constructor */
	public function __construct() {

		parent::__construct( [
			'singular' => __( 'Mention', 'activitypub' ), //singular name of the listed records
			'plural'   => __( 'Mentions', 'activitypub' ), //plural name of the listed records
			'ajax'     => true //should this table support ajax?
		] );

	}

	public function no_items() {
	  _e( 'You have no mentions.', 'activitypub' );
	}

	public function get_columns() {
		$columns = [
		    'cb'      => '<input type="checkbox" />',
		    'type' => \__( 'Type', 'activitypub' ),
		    'actor'    => \__( 'Actor', 'activitypub' ),
		    'mention' => \__( 'Mention', 'activitypub' ),
		    'date'    => \__( 'Submitted', 'activitypub' )
		  ];

		  return $columns;
	}

	/**
	 * Render a column when no column specific method exists.
	 *
	 * @param array $item
	 * @param string $column_name
	 *
	 * @return mixed
	 */
	public function column_default( $item, $column_name ) {
	  switch ( $column_name ) {
		case 'cb':
		case 'type':	
	    case 'actor':
	    case 'mention':
	    case 'ap_mention_id':
	    case 'date':
	    	return $item[ $column_name ];
	    default:
	    	return print_r( $item, true ); //Show the whole array for troubleshooting purposes
	  }
	}

	/**
	 * Columns to make sortable.
	 * TODO: implement actual sort
	 *
	 * @return array
	 */
	public function get_sortable_columns() {
	  $sortable_columns = array(
	    // 'actor' => array( 'identifier', true ),
	    // 'date' => array( 'date', true ),
	  );
	  return $sortable_columns;
	}


	/**
	 * Render the bulk edit checkbox
	 *
	 * @param array $item
	 *
	 * @return string
	 */
	function column_cb( $item ) {
		return sprintf(
			'<input type="checkbox" name="selected[]" value="%s" />', 
			$item['ap_mention_id']
		);
	}

	function single_row( $item  ) {
		//global $post, $comment;

		$post_id = $item['ap_mention_id'];
		$post_type = $item['type'];
		$post = get_post( $item['ap_mention_id'] );

		//$local_user = get_post_meta( post_id, 'local_user', true );
		$post_status = $post->post_status;
		$posts_class = implode(" ",get_post_class( $post_status, $post_id ));
		//$posts_class = get_post_class( $post_status, $post_id );

		$this->user_can = current_user_can( 'edit_post', $post_id );
		
		echo "<tr id='post-$post_id' class='$posts_class'>";
		echo $this->single_row_columns( $item  );
		echo "</tr>\n";

	}

	/**
	 * Method for name column
	 *
	 * @param array $item an array of DB data
	 *
	 * @return string
	 */
	function column_mention($item) {
		$reply_nonce = wp_create_nonce( 'activitypub_reply_mention' );
		$delete_nonce = wp_create_nonce( 'activitypub_delete_mention' );

		$actions = array(
	            'reply'      => sprintf('<a href="%s?post_type=%s&_ap_mentions=%s&post_parent=%s&_wpnonce=%s">Reply</a>',
								admin_url('post-new.php'),
								'activitypub_mentions',
								$item['_author_url'],
								$item['ap_mention_id'],
								//$item['audience'],//limit audience in UI, but also perform checks later
								$reply_nonce
							),
	            // 'oldreply'      => sprintf('<a href="?page=%s&action=%s&mention=%s&_wpnonce=%s">Reply</a>',
				// 				esc_attr($_REQUEST['page']),
				// 				'reply',
				// 				$item['ap_mention_id'],
				// 				$reply_nonce
				// 			),
				'quickreply'      => sprintf('<span class="reply hide--if-no-js"><button type="button" class="vim-r comment-inline button-link" data-action="replyto" data-parent-id="%s" data-post-id="%s">Reply</button></span>',
					$item['ap_mention_id'],
					$reply_nonce
				),
	            'delete'    => sprintf('<a href="?page=%s&action=%s&mention=%s&_wpnonce=%s">Delete</a>',
								esc_attr($_REQUEST['page']),
								'delete',
								$item['ap_mention_id'],
								$delete_nonce
							),
	        );
	  return sprintf('%1$s %2$s', $item['mention'], $this->row_actions($actions) );
	}

	public function process_bulk_action() {

		$action = $this->current_action();
		if ( isset( $_REQUEST['_wpnonce'] ) ) {
			$nonce = \esc_attr( $_REQUEST['_wpnonce'] );
		}

		switch ($action) {
			case 'bulk-delete':
				if ( wp_verify_nonce( $nonce, 'activitypub_delete_mention' ) ) {
					$ids = esc_sql( $_GET['selected'] );
					foreach ( $ids as $id ) {
						self::delete_mention( $id );
					}
				}
				break;
			// case 'status':
			// 	$ids = esc_sql( $_GET['selected'] );
			// 	foreach ( $ids as $id ) {
			// 				$comment = \wp_set_comment_status( $id, 'unread', false );
			// 				error_log( print_r( $comment, true ) );
			// 				//$comment->comment_approved = 'unread';
			// 	}
			// 	break;
			case 'reply':
				if ( ! wp_verify_nonce( $nonce, 'activitypub_reply_mention' ) ) {
						die( 'Get a life script kiddies' );
				} else {
					$content   = '';
					$editor_id = 'mycustomeditor';
					$settings  = array( 'media_buttons' => false );

					wp_editor( $content, $editor_id, $settings );
				}
				break;
			default:
				break;
		}

  }

	/**
	 * Returns an associative array containing the bulk action
	 *
	 * @return array
	 */
	public function get_bulk_actions() {
	  $actions = [
	   // 'delete' => 'Delete',
	    'bulk-delete' => 'Delete',
	   // 'status' => 'Change status'
	  ];

	  return $actions;
	}

	/**
	 * Delete a mention.
	 *
	 * @param int $id mention ID
	 */
	public static function delete_mention( $id ) {
		wp_delete_post( $id );
	}

	public function prepare_items() {
		$columns = $this->get_columns();
		//$hidden  = array('type');
		$hidden  = array();

		$this->process_action();
		$this->_column_headers = array( $columns, $hidden, $this->get_sortable_columns() );

		/** Process bulk action */
		$this->process_bulk_action();

		$this->items = array();

		$mentions = \Activitypub\Peer\Mentions::get_mentions();

		//echo human_time_diff( '2020-05-28 10:14:50', current_datetime('Y-m-d H:i:s') );
		echo '<details><summary>DEBUG</summary><pre>'; print_r($mentions); echo '</pre></details>';

		foreach ( $mentions as $mention ) :
			$this->items[] = array(
				'cb' => 'callback',
				'ap_mention_id' => $mention->ID,
				'actor' => '<img src="' . get_post_meta( $mention->ID, 'avatar_url', true ) . '" width="32px" heigh="auto" ><a href="' . get_post_meta( $mention->ID, '_author_url', true ) . '">' . get_post_meta( $mention->ID, '_author', true ) . '</a>',
				'mention' => $mention->post_content,
				'type' => $mention->post_status,
				'date' => '<a href="' . get_post_meta( $mention->ID, '_source_url', true ) .  '" rel="norefer noopener" target="_blank">' . $mention->post_date . '</a>', //https://www.lightningrank.com/how-to-display-wordpress-post-date-as-time-ago-posted-x-days-ago/
				'_author_url' => get_post_meta( $mention->ID, '_author_url', true ),
				//'date' => $mention->comment_date,
			);
		endforeach;

	}

	//add_action( 'admin_head', 'admin_header' );
	public function admin_header_parent() {
	  $page = ( isset($_GET['page'] ) ) ? esc_attr( $_GET['page'] ) : false;
	  if( 'activitypub-mentions-list' != $page )
	    return;

		add_action( 'admin_head', array( $this, 'admin_header' ) );

	}

}
/*
https://wpengineer.com/2426/wp_list_table-a-step-by-step-guide/

https://www.sitepoint.com/using-wp_list_table-to-create-wordpress-admin-tables/

*/