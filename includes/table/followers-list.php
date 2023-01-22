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
			'name' => \__( 'Name', 'activitypub' ),
			'desc' => \__( 'Description', 'activitypub' ),
			'service' => \__( 'From Service', 'activitypub' ),
			'since' => \__( 'Since', 'activitypub' ),
			'is_bot' => \__( 'Is Bot?', 'activitypub' ),
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

		$dateformat = \get_option( 'date_format' );
		$timeformat = \get_option( 'time_format' );
		$dtformat = $dateformat . ' @ ' . $timeformat;

		foreach ( \Activitypub\Peer\Followers::get_followers_extended( \get_current_user_id(), $per_page, ( $current_page - 1 ) * $per_page ) as $follower ) {

			$avatar = '<span class="dashicons dashicons-admin-users"></span>';

			if( $follower['avatar'] != '' ) {
				$avatar = '<img class="activitypub-avatar" src="' . \esc_attr( $follower['avatar'] ) . '">';
			}

			$this->items[] = array(
									'avatar'     => $avatar,
									'identifier' => '<a href="' . \esc_attr( $follower['follower'] ) . '" target="_blank">' . \esc_attr( $follower['follower'] ) . '</a>',
									'name'       => \esc_attr( $follower['name'] ),
									'desc'       => \esc_attr( $follower['description'] ),
									'service'    => '<a href="https://' . \esc_attr( $follower['server'] ) . '" target="_blank">' . \esc_attr( $follower['service'] ) . ' (' . \esc_attr( $follower['version'] ) . ')</a>',
									'since'      => \esc_attr( wp_date( $dtformat, strtotime( $follower['since'] ) ) ) ,
									'is_bot'     => $follower['is_bot'] ? __('Yes', 'activitypub') : __('No', 'activitypub'),
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
