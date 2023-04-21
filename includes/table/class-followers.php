<?php
namespace Activitypub\Table;

use WP_List_Table;
use Activitypub\Collection\Followers as FollowerCollection;

if ( ! \class_exists( '\WP_List_Table' ) ) {
		require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Followers extends WP_List_Table {
	public function get_columns() {
		return array(
			'cb'         => '<input type="checkbox" />',
			'avatar'     => \__( 'Avatar', 'activitypub' ),
			'name'       => \__( 'Name', 'activitypub' ),
			'username'   => \__( 'Username', 'activitypub' ),
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

		$page_num = $this->get_pagenum();
		$per_page = 20;

		$follower = FollowerCollection::get_followers( \get_current_user_id(), $per_page, ( $page_num - 1 ) * $per_page );
		$counter  = FollowerCollection::count_followers( \get_current_user_id() );

		$this->items = array();
		$this->set_pagination_args(
			array(
				'total_items' => $counter,
				'total_pages' => round( $counter / $per_page ),
				'per_page'    => $per_page,
			)
		);

		foreach ( $follower as $follower ) {
			$item = array(
				'avatar'     => esc_attr( get_term_meta( $follower->term_id, 'avatar', true ) ),
				'name'       => esc_attr( get_term_meta( $follower->term_id, 'name', true ) ),
				'username'   => esc_attr( get_term_meta( $follower->term_id, 'username', true ) ),
				'identifier' => esc_attr( $follower->name ),
			);

			$this->items[] = $item;
		}
	}

	public function get_bulk_actions() {
		return array(
			'delete' => __( 'Delete', 'activitypub' ),
		);
	}

	public function column_default( $item, $column_name ) {
		if ( ! array_key_exists( $column_name, $item ) ) {
			return __( 'None', 'activitypub' );
		}
		return $item[ $column_name ];
	}

	public function column_avatar( $item ) {
		return sprintf(
			'<img src="%s" width="25px;" />',
			$item['avatar']
		);
	}

	public function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="followers[]" value="%s" />', esc_attr( $item['identifier'] ) );
	}

	public function process_action() {
		$followers = isset( $_REQUEST['followers'] ) ? $_REQUEST['followers'] : array(); // phpcs:ignore

		switch ( $this->current_action() ) {
			case 'delete':
				FollowerCollection::remove_follower( \get_current_user_id(), $followers );
				break;
		}
	}
}
