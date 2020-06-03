<?php
namespace Activitypub\Table;

if ( ! \class_exists( '\WP_List_Table' ) ) {
		require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Messages_List extends \WP_List_Table {

	/** Class constructor */
	public function __construct() {

		parent::__construct( [
			'singular' => __( 'Message', 'activitypub' ), //singular name of the listed records
			'plural'   => __( 'Messages', 'activitypub' ), //plural name of the listed records
			'ajax'     => false //should this table support ajax?
		] );

	}

	public function no_items() {
	  _e( 'You have no messages.', 'activitypub' );
	}

	public function get_columns() {
		$columns = [
		    'cb'      => '<input type="checkbox" />',
		    'actor'    => \__( 'Actor', 'activitypub' ),
		    'message' => \__( 'Message', 'activitypub' ),
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
	     case 'actor':
	     case 'message':
	     case 'ap_message_id':
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
	    '<input type="checkbox" name="bulk-selected[]" value="%s" />', $item['ap_message_id']
	  );
	}

	/**
	 * Method for name column
	 *
	 * @param array $item an array of DB data
	 *
	 * @return string
	 */
	function column_message($item) {
		$reply_nonce = wp_create_nonce( 'activitypub_reply_message' );
		$delete_nonce = wp_create_nonce( 'activitypub_delete_message' );

		$actions = array(
	            'reply'      => sprintf('<a href="?page=%s&action=%s&message=%s&_wpnonce=%s">Reply</a>',
								esc_attr($_REQUEST['page']),
								'reply',
								$item['ap_message_id'],
								$reply_nonce
							),
	            'delete'    => sprintf('<a href="?page=%s&action=%s&message=%s&_wpnonce=%s">Delete</a>',
								esc_attr($_REQUEST['page']),
								'delete',
								$item['ap_message_id'],
								$delete_nonce
							),
	        );
	  return sprintf('%1$s %2$s', $item['message'], $this->row_actions($actions) );
	}

	public function process_bulk_action() {

		$action = $this->current_action();
		if ( isset( $_REQUEST['_wpnonce'] ) ) {
			$nonce = \esc_attr( $_REQUEST['_wpnonce'] );
		}

		switch ($action) {
			case 'delete':
				if ( wp_verify_nonce( $nonce, 'activitypub_delete_message' ) ) {
						self::delete_message( absint( $_GET['message'] ) );
				}
				break;
			case 'bulk-delete':
        $ids = esc_sql( $_GET['bulk-selected'] );
        foreach ( $ids as $id ) {
            self::delete_message( $id );
        }
        break;
			// case 'reply':
			// 	if ( wp_verify_nonce( $nonce, 'activitypub_reply_message' ) ) {
			// 			self::delete_message( absint( $_GET['message'] ) );
			// 	}
			// 	break;
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
	    'bulk-delete' => 'Bulk delete'
	  ];

	  return $actions;
	}

	/**
	 * Delete a message.
	 *
	 * @param int $id message ID
	 */
	public static function delete_message( $id ) {
		wp_delete_comment( $id );
	}

	public function prepare_items() {
		$columns = $this->get_columns();
		$hidden  = array();

		$this->process_action();
		$this->_column_headers = array( $columns, $hidden, $this->get_sortable_columns() );

		/** Process bulk action */
		$this->process_bulk_action();

		$this->items = array();

		$messages = \Activitypub\Peer\Messages::get_messages();

		foreach ( $messages as $message ) :
			$this->items[] = array(
				'cb' => 'callback',
				'ap_message_id' => $message->comment_ID,
				'actor' => '<img src="' . get_comment_meta( $message->comment_ID, 'avatar_url', true ) . '" width="32px" heigh="auto" ><a href="' . $message->comment_author_url . '">' . $message->comment_author . '</a>',
				'message' => $message->comment_content,
				'date' => $message->comment_date,
			);
		endforeach;

	}

}
