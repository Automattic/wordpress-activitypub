<?php
namespace Activitypub\Table;

if ( ! class_exists( '\WP_List_Table' ) ) {
		require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Followers_List extends \WP_List_Table {
	public function get_columns() {
		return array(
			'identifier' => \__( 'Identifier', 'activitypub' ),
		);
	}

	public function get_sortable_columns() {
		return array();
	}

	public function prepare_items() {
		$columns = $this->get_columns();
		$hidden  = array();

		$this->process_action();
		$this->_column_headers = array( $columns, $hidden, $this->get_sortable_columns() );

		$this->items = array();

		foreach ( \Activitypub\Peer\Followers::get_followers( \get_current_user_id() ) as $follower ) {
			$this->items[]['identifier'] = \esc_attr( $follower );
		}
	}

	public function column_default( $item, $column_name ) {
		return $item[ $column_name ];
	}
}
