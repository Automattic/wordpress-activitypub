<?php
namespace Activitypub\Table;

use WP_List_Table;
use Activitypub\User_Factory;
use Activitypub\Collection\Followers as FollowerCollection;

if ( ! \class_exists( '\WP_List_Table' ) ) {
		require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Followers extends WP_List_Table {
	private $user_id;

	public function __construct() {
		if ( get_current_screen()->id === 'settings_page_activitypub' ) {
			$this->user_id = User_Factory::BLOG_USER_ID;
		} else {
			$this->user_id = \get_current_user_id();
		}

		parent::__construct(
			array(
				'singular' => \__( 'Follower', 'activitypub' ),
				'plural'   => \__( 'Followers', 'activitypub' ),
				'ajax'     => false,
			)
		);
	}

	public function get_columns() {
		return array(
			'cb'           => '<input type="checkbox" />',
			'avatar'       => \__( 'Avatar', 'activitypub' ),
			'name'         => \__( 'Name', 'activitypub' ),
			'username'     => \__( 'Username', 'activitypub' ),
			'url'          => \__( 'URL', 'activitypub' ),
			'updated'      => \__( 'Last updated', 'activitypub' ),
			//'errors'       => \__( 'Errors', 'activitypub' ),
			//'latest-error' => \__( 'Latest Error Message', 'activitypub' ),
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

		$followers = FollowerCollection::get_followers( $this->user_id, $per_page, ( $page_num - 1 ) * $per_page );
		$counter   = FollowerCollection::count_followers( $this->user_id );

		$this->items = array();
		$this->set_pagination_args(
			array(
				'total_items' => $counter,
				'total_pages' => round( $counter / $per_page ),
				'per_page'    => $per_page,
			)
		);

		foreach ( $followers as $follower ) {
			$item = array(
				'icon'         => esc_attr( $follower->get_icon_url() ),
				'name'         => esc_attr( $follower->get_name() ),
				'username'     => esc_attr( $follower->get_preferred_username() ),
				'url'          => esc_attr( $follower->get_url() ),
				'identifier'   => esc_attr( $follower->get_id() ),
				'updated'      => esc_attr( $follower->get_updated() ),
				'errors'       => $follower->count_errors(),
				'latest-error' => $follower->get_latest_error_message(),
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
			$item['icon']
		);
	}

	public function column_url( $item ) {
		return sprintf(
			'<a href="%s" target="_blank">%s</a>',
			$item['url'],
			$item['url']
		);
	}

	public function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="followers[]" value="%s" />', esc_attr( $item['identifier'] ) );
	}

	public function process_action() {
		if ( ! isset( $_REQUEST['followers'] ) || ! isset( $_REQUEST['_apnonce'] ) ) {
			return false;
		}

		if ( ! wp_verify_nonce( $_REQUEST['_apnonce'], 'activitypub-followers-list' ) ) {
			return false;
		}

		if ( ! current_user_can( 'edit_user', $this->user_id ) ) {
			return false;
		}

		$followers = $_REQUEST['followers']; // phpcs:ignore

		switch ( $this->current_action() ) {
			case 'delete':
				if ( ! is_array( $followers ) ) {
					$followers = array( $followers );
				}
				foreach ( $followers as $follower ) {
					FollowerCollection::remove_follower( $this->user_id, $follower );
				}
				break;
		}
	}

	public function get_user_count() {
		return FollowerCollection::count_followers( $this->user_id );
	}
}
