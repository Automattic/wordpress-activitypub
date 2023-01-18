<?php
namespace Activitypub\Table;

if ( ! \class_exists( '\WP_List_Table' ) ) {
		require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Followers_List extends \WP_List_Table {
	public function get_columns() {
		return array(
			'avatar' => \__( 'Avatar', 'activitypub' ),
			'identifier' => \__( 'Identifier', 'activitypub' ),
			'service' => \__( 'From Service', 'activitypub' ),
		);
	}

	public function get_sortable_columns() {
		return array(
						'identifier' => array( 'identifier', false ),
						'service' => array( 'service', false ),
					);
	}


	public function no_items() {
  		_e( 'No followers found.' );
	}
	public function prepare_items() {
		$columns = $this->get_columns();
		$hidden  = array();
		$per_page = 20;
  		$current_page = $this->get_pagenum();

		$this->process_action();
		$this->_column_headers = array( $columns, $hidden, $this->get_sortable_columns() );

		$this->items = array();

		foreach ( \Activitypub\Peer\Followers::get_followers( \get_current_user_id() ) as $follower ) {
			$this->items[] = array( 'avatar'     => '<span class="dashicons dashicons-admin-users"></span>',
									'identifier' => \esc_attr( $follower ),
									'service'    => __( 'Unknown', 'activitypub' ),
								);
		}

		$found_data = array_slice($this->items,(($current_page-1)*$per_page),$per_page);

		$this->set_pagination_args(
									array(
										    'total_items' => count( $this->items ),
										    'per_page'    => $per_page,
										)
								);

		$this->items = $found_data;
	}

	public function column_default( $item, $column_name ) {
		return $item[ $column_name ];
	}
}
