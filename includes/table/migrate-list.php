<?php
namespace Activitypub\Table;

if ( ! \class_exists( '\WP_List_Table' ) ) {
		require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Migrate_List extends \WP_List_Table {
	public function get_columns() {
		return array(
			'post_author' => \__( 'Post Author (user_id)', 'activitypub' ),
			'title' => \__( 'Post', 'activitypub' ),
			'date' => \__( 'Publication Date', 'activitypub' ),
			'comments' => \__( 'Comments', 'activitypub' ),
			'migrate' => \__( 'Migrate', 'activitypub' ),
		);
	}

	public function get_sortable_columns() {
		return array();
	}

	public function prepare_items() {
		$columns = $this->get_columns();
		$hidden  = array( 'post_author', 'migrate' );
		$sortable = $this->get_sortable_columns();

		$this->process_action();
		$this->_column_headers = array( $columns, $hidden, $sortable );

		foreach ( \Activitypub\Migrate\Posts::get_posts() as $post ) {
			$this->items[] = array(
				'post_author' => $post->post_author,
				'title'      => \sprintf(
					'<a href="%1s">%2s</a>',
					\get_permalink( $post->ID ),
					$post->post_title
				),
				'date'       => $post->post_date,
				'comments'   => $post->comment_count,
				'migrate'     => \get_post_meta( $post->ID, '_activitypub_permalink_compat', true ),
			);
		}

		// pagination
		$per_page = $this->get_items_per_page( 'elements_per_page', 10 );
		$current_page = $this->get_pagenum();
		$total_items = count( $this->items );
		$table_data = array_slice( $this->items, ( ( $current_page - 1 ) * $per_page ), $per_page );
		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => ceil( $total_items / $per_page ),
			)
		);

		// actions
		if ( isset( $_REQUEST['_wpnonce'] ) ) {
			$nonce = \esc_attr( $_REQUEST['_wpnonce'] );
		}
		// delete
		if ( isset( $_REQUEST['action'] ) && 'activitypub_tools' === $_REQUEST['page'] && 'delete' === $_REQUEST['action'] ) {
			if ( wp_verify_nonce( $nonce, 'activitypub_delete_post' ) ) {
				\Activitypub\Migrate\Posts::delete_url( rawurldecode( $_REQUEST['post_url'] ), absint( $_REQUEST['post_author'] ) );
				\delete_post_meta( \url_to_postid( $_REQUEST['post_url'] ), '_activitypub_permalink_compat' );
			}
		}
		// delete and announce
		if ( isset( $_REQUEST['action'] ) && 'activitypub_tools' === $_REQUEST['page'] && 'delete_announce' === $_REQUEST['action'] ) {
			if ( wp_verify_nonce( $nonce, 'activitypub_delete_announce_post' ) ) {
				\Activitypub\Migrate\Posts::migrate_post( rawurldecode( $_REQUEST['post_url'] ), absint( $_REQUEST['post_author'] ) );
				\delete_post_meta( \url_to_postid( $_REQUEST['post_url'] ), '_activitypub_permalink_compat' );
			}
		}
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
			case 'post_author':
			case 'title':
			case 'date':
			case 'comments':
			case 'migrate':
				return $item[ $column_name ];
			default:
				return print_r( $item, true );
		}
	}

	public function column_title( $item ) {
		$delete_announce_nonce = wp_create_nonce( 'activitypub_delete_announce_post' );
		$delete_nonce = wp_create_nonce( 'activitypub_delete_post' );

		$actions = array(
			'delete_announce' => sprintf(
				'<a href="?page=%s&action=%s&post_author=%s&post_url=%s&_wpnonce=%s">%s</a>',
				esc_attr( $_REQUEST['page'] ),
				'delete_announce',
				$item['post_author'],
				\rawurlencode( $item['migrate'] ),
				$delete_announce_nonce,
				__( 'Delete & Announce', 'activitypub' )
			),
			'delete' => sprintf(
				'<a href="?page=%s&action=%s&post_author=%s&post_url=%s&_wpnonce=%s">%s</a>',
				esc_attr( $_REQUEST['page'] ),
				'delete',
				$item['post_author'],
				\rawurlencode( $item['migrate'] ),
				$delete_nonce,
				__( 'Delete', 'activitypub' )
			),
		);
		return sprintf( '%1$s %2$s', $item['title'], $this->row_actions( $actions, true ) );
	}
}
