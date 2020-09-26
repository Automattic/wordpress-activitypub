<?php
namespace Activitypub\Table;

if ( ! \class_exists( '\WP_List_Table' ) ) {
		require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Followers_List extends \WP_List_Table {
	public function get_columns() {
		return array(
			'cb'      => '<input type="checkbox" />',
			'identifier' => \__( 'Identifier', 'activitypub' ),
		);
	}

	public function get_sortable_columns() {
		return array();
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
	    '<input type="checkbox" name="selected[]" value="%s" />', $item['identifier']
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
		//$reply_nonce = wp_create_nonce( 'activitypub_delete_follower' );
		$delete_nonce = wp_create_nonce( 'activitypub_delete_follower' );

		$actions = array(
	            // 'reply'      => sprintf('<a href="?page=%s&action=%s&message=%s&_wpnonce=%s">Reply</a>',
				// 				esc_attr($_REQUEST['page']),
				// 				'reply',
				// 				$item['ap_message_id'],
				// 				$reply_nonce
				// 			),
	            'delete'    => sprintf('<a href="?page=%s&action=%s&follower=%s&_wpnonce=%s">Delete</a>',
								esc_attr($_REQUEST['page']),
								'delete',
								$item['identifier'],
								$delete_nonce
							),
	        );
	  return sprintf('%1$s %2$s', $item['identifier'], $this->row_actions($actions) );
	}

	/**
	 * Delete a message.
	 *
	 * @param int $id message ID
	 */
	public static function delete_follower( $id ) {
		$author = wp_get_current_user();
		\Activitypub\Peer\Followers::remove_follower( $id, $author->ID );
	}

	/**
	 * Returns an associative array containing the bulk action
	 *
	 * @return array
	 */
	public function get_bulk_actions() {
	  $actions = [
	    'bulk-delete' => 'Bulk delete',
	  ];

	  return $actions;
	}

	public function process_bulk_action() {

		$action = $this->current_action();
		if ( isset( $_REQUEST['_wpnonce'] ) ) {
			$nonce = \esc_attr( $_REQUEST['_wpnonce'] );
		}

		switch ($action) {
			case 'delete':
				if ( wp_verify_nonce( $nonce, 'activitypub_delete_follower' ) ) {
						self::delete_follower( absint( $_GET['identifier'] ) );
				}
				break;
			case 'bulk-delete':
				$ids = esc_sql( $_GET['selected'] );
				foreach ( $ids as $id ) {
					self::delete_follower( $id );
				}
				break;
			case 'status':
				$ids = esc_sql( $_GET['selected'] );
				foreach ( $ids as $id ) {
							$comment = \wp_set_comment_status( $id, 'unread', false );
							error_log( print_r( $comment, true ) );
							//$comment->comment_approved = 'unread';
				}
				break;
			case 'reply':
				if ( ! wp_verify_nonce( $nonce, 'activitypub_reply_message' ) ) {
						die( 'Go get a life script kiddies' );
				} else {
					$content   = '';
					$editor_id = 'mycustomeditor';
					$settings  = array( 'media_buttons' => false );

					wp_editor( $content, $editor_id, $settings );				}
				break;
			default:
				break;
		}

  	}

	public function prepare_items() {
		$columns = $this->get_columns();
		$hidden  = array();

		$this->process_action();
		$this->_column_headers = array( $columns, $hidden, $this->get_sortable_columns() );

		$this->process_bulk_action();

		$this->items = array();

		foreach ( \Activitypub\Peer\Followers::get_followers( \get_current_user_id() ) as $follower ) {
			$this->items[] = array(
			'cb' => 'callback',
			'identifier' => \esc_attr( $follower ),
			);
		}
	}

	public function column_default( $item, $column_name ) {
		return $item[ $column_name ];
	}
}
