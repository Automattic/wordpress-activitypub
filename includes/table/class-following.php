<?php
/**
 * Followers Table-Class file.
 *
 * @package Activitypub
 */

namespace Activitypub\Table;

use Activitypub\Collection\Actors;
use Activitypub\Collection\Following as Following_Collection;

use function Activitypub\object_to_uri;

if ( ! \class_exists( '\WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Following Table-Class.
 */
class Following extends \WP_List_Table {
	/**
	 * User ID.
	 *
	 * @var int
	 */
	private $user_id;

	/**
	 * Constructor.
	 */
	public function __construct() {
		if ( get_current_screen()->id === 'settings_page_activitypub' ) {
			$this->user_id = Actors::BLOG_USER_ID;
		} else {
			$this->user_id = \get_current_user_id();
		}

		parent::__construct(
			array(
				'singular' => \__( 'Following', 'activitypub' ),
				'plural'   => \__( 'Followings', 'activitypub' ),
				'ajax'     => false,
			)
		);
	}

	/**
	 * Get columns.
	 *
	 * @return array
	 */
	public function get_columns() {
		return array(
			'cb'         => '<input type="checkbox" />',
			'username'   => \__( 'Username', 'activitypub' ),
			'post_title' => \__( 'Name', 'activitypub' ),
			'modified'   => \__( 'Last updated', 'activitypub' ),
		);
	}

	/**
	 * Returns sortable columns.
	 *
	 * @return array
	 */
	public function get_sortable_columns() {
		return array(
			'username'   => array( 'username', true ),
			'post_title' => array( 'post_title', true ),
			'modified'   => array( 'modified', false ),
		);
	}

	/**
	 * Prepare items.
	 */
	public function prepare_items() {
		$status = Following_Collection::ALL;

		$this->process_action();

		$page_num = $this->get_pagenum();
		$per_page = $this->get_items_per_page( 'activitypub_following_per_page' );

		$args = array();

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['orderby'] ) ) {
			$args['orderby'] = \sanitize_text_field( \wp_unslash( $_GET['orderby'] ) );
		}

		if ( isset( $_GET['order'] ) ) {
			$args['order'] = \sanitize_text_field( \wp_unslash( $_GET['order'] ) );
		}

		if ( isset( $_GET['s'] ) ) {
			$search = \sanitize_text_field( \wp_unslash( $_GET['s'] ) );
			$search = \str_replace( 'acct:', '', $search );
			$search = \str_replace( '@', ' ', $search );
			$search = \str_replace( 'http://', '', $search );
			$search = \str_replace( 'https://', '', $search );
			$search = \str_replace( 'www.', '', $search );
			$search = \trim( $search );

			$args['s'] = $search;
		}

		if ( isset( $_GET['status'] ) ) {
			$status = \sanitize_text_field( \wp_unslash( $_GET['status'] ) );
		}

		if ( Following_Collection::PENDING === $status ) {
			$following_with_count = Following_Collection::get_pending_with_count( $this->user_id, $per_page, $page_num, $args );
		} elseif ( Following_Collection::ACCEPTED === $status ) {
			$following_with_count = Following_Collection::get_following_with_count( $this->user_id, $per_page, $page_num, $args );
		} else {
			$following_with_count = Following_Collection::get_all_with_count( $this->user_id, $per_page, $page_num, $args );
		}

		$followings = $following_with_count['following'];
		$counter    = $following_with_count['total'];

		$this->items = array();
		$this->set_pagination_args(
			array(
				'total_items' => $counter,
				'total_pages' => ceil( $counter / $per_page ),
				'per_page'    => $per_page,
			)
		);

		foreach ( $followings as $following ) {
			$actor = Actors::get_actor( $following->ID );

			$this->items[] = array(
				'id'         => $following->ID,
				'icon'       => $actor->get_icon()['url'] ?? '',
				'post_title' => $actor->get_name(),
				'username'   => $actor->get_preferred_username(),
				'name'       => $actor->get_name(),
				'url'        => object_to_uri( $actor->get_url() ),
				'identifier' => $actor->get_id(),
				'modified'   => $following->post_modified_gmt,
			);
		}
	}

	/**
	 * Returns views.
	 *
	 * @return string[]
	 */
	public function get_views() {
		$count  = Following_Collection::count( $this->user_id );
		$path   = 'users.php?page=activitypub-following-list';
		$status = Following_Collection::ALL;

		if ( Actors::BLOG_USER_ID === $this->user_id ) {
			$path = 'options-general.php?page=activitypub&tab=following';
		}

		if ( isset( $_GET['status'] ) ) {
			$status = \sanitize_text_field( \wp_unslash( $_GET['status'] ) );
		}

		$links = array(
			'all'      => array(
				'url'     => admin_url( $path ),
				'label'   => sprintf(
					/* translators: %s: Number of users. */
					\_nx(
						'All <span class="count">(%s)</span>',
						'All <span class="count">(%s)</span>',
						$count[ Following_Collection::ALL ],
						'users',
						'activitypub'
					),
					\number_format_i18n( $count[ Following_Collection::ALL ] )
				),
				'current' => Following_Collection::ALL === $status,
			),
			'accepted' => array(
				'url'     => admin_url( $path . '&status=' . Following_Collection::ACCEPTED ),
				'label'   => sprintf(
					/* translators: %s: Number of users. */
					\_nx(
						'Accepted <span class="count">(%s)</span>',
						'Accepted <span class="count">(%s)</span>',
						$count[ Following_Collection::ACCEPTED ],
						'users',
						'activitypub'
					),
					\number_format_i18n( $count[ Following_Collection::ACCEPTED ] )
				),
				'current' => Following_Collection::ACCEPTED === $status,
			),
			'pending'  => array(
				'url'     => admin_url( $path . '&status=' . Following_Collection::PENDING ),
				'label'   => sprintf(
					/* translators: %s: Number of users. */
					\_nx(
						'Pending <span class="count">(%s)</span>',
						'Pending <span class="count">(%s)</span>',
						$count[ Following_Collection::PENDING ],
						'users',
						'activitypub'
					),
					\number_format_i18n( $count[ Following_Collection::PENDING ] )
				),
				'current' => Following_Collection::PENDING === $status,
			),
		);

		return $this->get_views_links( $links );
	}

	/**
	 * Returns bulk actions.
	 *
	 * @return array
	 */
	public function get_bulk_actions() {
		return array(
			'delete' => \__( 'Unfollow', 'activitypub' ),
		);
	}

	/**
	 * Column default.
	 *
	 * @param array  $item        Item.
	 * @param string $column_name Column name.
	 * @return string
	 */
	public function column_default( $item, $column_name ) {
		if ( ! array_key_exists( $column_name, $item ) ) {
			return \esc_html__( 'None', 'activitypub' );
		}
		return \esc_html( $item[ $column_name ] );
	}

	/**
	 * Column avatar.
	 *
	 * @param array $item Item.
	 * @return string
	 */
	public function column_cb( $item ) {
		return \sprintf( '<input type="checkbox" name="following[]" value="%s" />', \esc_attr( $item['identifier'] ) );
	}

	/**
	 * Column url.
	 *
	 * @param array $item Item.
	 * @return string
	 */
	public function column_username( $item ) {
		$status = '';

		if (
			( ! isset( $_GET['status'] ) || Following_Collection::ALL === $_GET['status'] ) &&
			( Following_Collection::PENDING === Following_Collection::check_status( $this->user_id, $item['id'] ) )
		) {
			$status = \sprintf( '<strong> — %s</strong>', \esc_html__( 'Pending', 'activitypub' ) );
		}

		return sprintf(
			'<img src="%1$s" width="32" height="32" alt="%2$s" loading="lazy"/> <strong><a href="%3$s">%4$s</a></strong>%5$s<br />',
			\esc_url( $item['icon'] ),
			\esc_attr( $item['post_title'] ),
			\esc_url( $item['url'] ),
			\esc_html( $item['username'] ),
			$status
		);
	}

	/**
	 * Column modified.
	 *
	 * @param array $item Item.
	 * @return string
	 */
	public function column_modified( $item ) {
		$modified = \strtotime( $item['modified'] );
		return \sprintf(
			'<time datetime="%1$s">%2$s</time>',
			\esc_attr( \gmdate( 'c', $modified ) ),
			\esc_html( \gmdate( \get_option( 'date_format' ), $modified ) )
		);
	}

	/**
	 * Process action.
	 */
	public function process_action() {
		if ( ! isset( $_REQUEST['following'], $_REQUEST['_wpnonce'] ) ) {
			return;
		}

		$nonce = \sanitize_text_field( \wp_unslash( $_REQUEST['_wpnonce'] ) );
		if ( ! \wp_verify_nonce( $nonce, 'bulk-' . $this->_args['plural'] ) ) {
			return;
		}

		if ( ! \current_user_can( 'edit_user', $this->user_id ) ) {
			return;
		}

		if ( $this->current_action() === 'delete' ) {
			$following = array_map( 'esc_url_raw', \wp_unslash( $_REQUEST['following'] ) );

			foreach ( $following as $actor_id ) {
				$actor = Actors::get_remote_by_uri( $actor_id );
				if ( \is_wp_error( $actor ) ) {
					continue;
				}
				Following_Collection::unfollow( $actor, $this->user_id );
			}
		}
	}

	/**
	 * Message to be displayed when there are no followings.
	 */
	public function no_items() {
		\esc_html_e( 'No followings found.', 'activitypub' );
	}

	/**
	 * Single row.
	 *
	 * @param array $item Item.
	 */
	public function single_row( $item ) {
		\printf(
			"<tr id='following-%s'>",
			\esc_attr( $item['id'] )
		);
		$this->single_row_columns( $item );
		\printf( "</tr>\n" );
	}
}
