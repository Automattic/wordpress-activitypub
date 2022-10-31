<?php
namespace Activitypub\Table;

if ( ! \class_exists( '\WP_List_Table' ) ) {
		require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Migrate_List extends \WP_List_Table {

	public function get_columns() {
		return array(
			'cb'      => '<input type="checkbox" />',
			'post_author' => \__( 'Post Author (user_id)', 'activitypub' ),
			'title' => \__( 'Post', 'activitypub' ),
			'comments' => \__( 'Comments', 'activitypub' ),
			'date' => \__( 'Publication Date', 'activitypub' ),
			'migrate' => \__( 'Migrate', 'activitypub' ),
		);
	}

	public function get_sortable_columns() {
		return array();
	}

	public function get_bulk_actions() {
		$actions = array(
			'delete'    => 'Remove backwards compatibility',
		);
		return $actions;
	}

	public static function get_activitypub_tools_views() {
		$posts_count = \Activitypub\Tools\Posts::count_posts_to_migrate();
		$comments_posts_count = \Activitypub\Tools\Posts::count_posts_with_comments_to_migrate();
		$activitypub_tools_page = 'tools.php?page=activitypub_tools';
		$view_slugs = array(
			array( 'all', null, __( 'All', 'activitypub' ), $posts_count ),
			array( 'comments', 'activitypub', __( 'Posts with Comments', 'activitypub' ), $comments_posts_count ),
		);
		$post_status_var = get_query_var( 'comments' );
		$view_count = count( $view_slugs );
		for ( $x = 0; $x < $view_count; $x++ ) {
			$class = ( $post_status_var == $view_slugs[ $x ][1] ) ? ' class="current"' : '';
			$post_status_temp = $view_slugs[ $x ][1];
			if ( $post_status_temp != '' ) {
				$post_status_temp = '&comments=' . $view_slugs[ $x ][1];
			}
			$views[ $view_slugs[ $x ][0] ] = sprintf(
				__(
					'<a href="' .
						$activitypub_tools_page .
						$post_status_temp . '"' .
						$class .
						' >' .
						$view_slugs[ $x ][2] .
					' <span class="count">(%d)</span></a>'
				),
				$view_slugs[ $x ][3]
			);
		}
		return $views;
	}

	public function prepare_items() {
		$columns = $this->get_columns();
		$hidden  = array( 'post_author', 'migrate' );
		$sortable = $this->get_sortable_columns();
		$this->_column_headers = array( $columns, $hidden, $sortable );

		$this->items = array();
		$this->process_action();
		if ( 'activitypub_tools' === $_REQUEST['page'] ) {
			if ( isset( $_REQUEST['comments'] ) && 'activitypub' === $_REQUEST['comments'] ) {
				foreach ( \Activitypub\Tools\Posts::get_posts_with_activitypub_comments() as $ap_post ) {
					$this->items[] = array(
						'post_author' => $ap_post->post_author,
						'title'      => \sprintf(
							'<a href="%1s">%2s</a>',
							\get_permalink( $ap_post->ID ),
							$ap_post->post_title
						),
						'comments'   => $ap_post->comment_count,
						'date'       => $ap_post->post_date,
						'migrate'     => \get_post_meta( $ap_post->ID, '_activitypub_permalink_compat', true ),
					);
				}
			} else {
				foreach ( \Activitypub\Tools\Posts::get_posts_to_migrate() as $post ) {
					$this->items[] = array(
						'post_author' => $post->post_author,
						'title'      => \sprintf(
							'<a href="%1s">%2s</a>',
							\get_permalink( $post->ID ),
							$post->post_title
						),
						'comments'   => $post->comment_count,
						'date'       => $post->post_date,
						'migrate'     => \get_post_meta( $post->ID, '_activitypub_permalink_compat', true ),
					);
				}
			}
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
		if ( isset( $_REQUEST['action'] ) && 'activitypub_tools' === $_REQUEST['page'] && 'delete_notice' === $_REQUEST['action'] ) {
			if ( wp_verify_nonce( $nonce, 'activitypub_migrate_actions' ) ) {
				\Activitypub\Tools\Posts::delete_url( rawurldecode( $_REQUEST['post_url'] ), absint( $_REQUEST['post_author'] ) );
				\delete_post_meta( \url_to_postid( $_REQUEST['post_url'] ), '_activitypub_permalink_compat' );
			}
		}
		// delete and announce
		if ( isset( $_REQUEST['action'] ) && 'activitypub_tools' === $_REQUEST['page'] && 'delete' === $_REQUEST['action'] ) {
			if ( wp_verify_nonce( $nonce, 'activitypub_migrate_actions' ) ) {
				\Activitypub\Tools\Posts::migrate_post( rawurldecode( $_REQUEST['post_url'] ), absint( $_REQUEST['post_author'] ) );
				\delete_post_meta( \url_to_postid( $_REQUEST['post_url'] ), '_activitypub_permalink_compat' );
			}
		}
	}

	public function single_row( $item ) {
		$inline_styles = ( $item['comments'] > 0 ) ? 'warning' : ''; ?>
		<tr class="<?php echo $inline_styles; ?>"><?php $this->single_row_columns( $item ); ?></tr>
		<?php
	}

	/**
	 * Render the bulk edit checkbox
	 *
	 * @param array $item
	 *
	 * @return string
	 */
	public function column_cb( $item ) {
		return sprintf(
			'<input type="checkbox" name="selected[]" value="%s" />',
			$item['migrate']
		);
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
			case 'post_author':
			case 'title':
			case 'comments':
			case 'date':
			case 'migrate':
				return $item[ $column_name ];
			default:
				return print_r( $item, true );
		}
	}

	public function column_title( $item ) {
		$migrate_action_nonce = wp_create_nonce( 'activitypub_migrate_actions' );

		$actions = array(
			'delete' => sprintf(
				'<a href="?page=%s&action=%s&post_author=%s&post_url=%s&_wpnonce=%s" class="%s" title="%s" data-post_author="%s" data-post_url="%s" data-nonce="%s">%s</a>',
				esc_attr( $_REQUEST['page'] ),
				'delete', // using this id for style reasons
				$item['post_author'],
				\rawurlencode( $item['migrate'] ),
				$migrate_action_nonce,
				'delete_annouce aria-button-ui-if-js',
				__( 'Delete the federated post, and re-share the post with a new id', 'activitypub' ),
				$item['post_author'],
				\rawurlencode( $item['migrate'] ),
				$migrate_action_nonce,
				__( 'Migrate post', 'activitypub' )
			),
			'delete_notice' => sprintf(
				'<a href="?page=%s&action=%s&post_author=%s&post_url=%s&_wpnonce=%s" class="%s" title="%s" data-post_author="%s" data-post_url="%s" data-nonce="%s">%s</a>',
				esc_attr( $_REQUEST['page'] ),
				'delete_notice',
				$item['post_author'],
				\rawurlencode( $item['migrate'] ),
				$migrate_action_nonce,
				'delete aria-button-ui-if-js',
				__( 'Delete this notice and backwards compatibility', 'activitypub' ),
				$item['post_author'],
				\rawurlencode( $item['migrate'] ),
				$migrate_action_nonce,
				__( 'Remove notice', 'activitypub' )
			),
		);
		return sprintf( '%1$s %2$s', $item['title'], $this->row_actions( $actions, true ) );
	}
}
