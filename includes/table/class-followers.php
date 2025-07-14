<?php
/**
 * Followers Table-Class file.
 *
 * @package Activitypub
 */

namespace Activitypub\Table;

use Activitypub\Collection\Actors;
use Activitypub\Collection\Followers as Follower_Collection;

use function Activitypub\object_to_uri;

if ( ! \class_exists( '\WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Followers Table-Class.
 */
class Followers extends \WP_List_Table {
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
				'singular' => \__( 'Follower', 'activitypub' ),
				'plural'   => \__( 'Followers', 'activitypub' ),
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
			'username'   => \esc_html__( 'Username', 'activitypub' ),
			'post_title' => \esc_html__( 'Name', 'activitypub' ),
			'modified'   => \esc_html__( 'Last updated', 'activitypub' ),
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
		$columns = $this->get_columns();
		$hidden  = array();

		$this->process_action();
		$this->_column_headers = array( $columns, $hidden, $this->get_sortable_columns() );

		$page_num = $this->get_pagenum();
		$per_page = 20;

		$args = array();

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['orderby'] ) ) {
			$args['orderby'] = \sanitize_text_field( \wp_unslash( $_GET['orderby'] ) );
		}

		if ( isset( $_GET['order'] ) ) {
			$args['order'] = \sanitize_text_field( \wp_unslash( $_GET['order'] ) );
		}

		if ( ! empty( $_GET['s'] ) ) {
			$args['s'] = \sanitize_text_field( \wp_unslash( $_GET['s'] ) );
		}

		$followers_with_count = Follower_Collection::get_followers_with_count( $this->user_id, $per_page, $page_num, $args );
		$followers            = $followers_with_count['followers'];
		$counter              = $followers_with_count['total'];

		$this->items = array();
		$this->set_pagination_args(
			array(
				'total_items' => $counter,
				'total_pages' => ceil( $counter / $per_page ),
				'per_page'    => $per_page,
			)
		);

		foreach ( $followers as $follower ) {
			$actor         = Actors::get_actor( $follower );
			$this->items[] = array(
				'icon'       => $actor->get_icon()['url'] ?? '',
				'post_title' => $actor->get_name(),
				'username'   => $actor->get_preferred_username(),
				'url'        => object_to_uri( $actor->get_url() ),
				'identifier' => $actor->get_id(),
				'modified'   => $follower->post_modified_gmt,
			);
		}
	}

	/**
	 * Returns views.
	 *
	 * @return string[]
	 */
	public function get_views() {
		$count = Follower_Collection::count_followers( $this->user_id );

		$path = 'users.php?page=activitypub-followers-list';
		if ( Actors::BLOG_USER_ID === $this->user_id ) {
			$path = 'options-general.php?page=activitypub&tab=followers';
		}

		$links = array(
			'all' => array(
				'url'     => admin_url( $path ),
				'label'   => sprintf(
					/* translators: %s: Number of users. */
					\_nx(
						'All <span class="count">(%s)</span>',
						'All <span class="count">(%s)</span>',
						$count,
						'users',
						'activitypub'
					),
					number_format_i18n( $count )
				),
				'current' => true,
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
			'delete' => \__( 'Delete', 'activitypub' ),
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
	 * Column cb.
	 *
	 * @param array $item Item.
	 * @return string
	 */
	public function column_cb( $item ) {
		return \sprintf( '<input type="checkbox" name="followers[]" value="%s" />', \esc_attr( $item['identifier'] ) );
	}

	/**
	 * Column username.
	 *
	 * @param array $item Item.
	 * @return string
	 */
	public function column_username( $item ) {
		return \sprintf(
			'<img src="%1$s" width="32" height="32" alt="%2$s" loading="lazy"/> <strong><a href="%3$s">%4$s</a></strong><br />',
			\esc_url( $item['icon'] ),
			\esc_attr( $item['username'] ),
			\esc_url( $item['url'] ),
			\esc_html( $item['username'] )
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
		if ( ! isset( $_REQUEST['followers'], $_REQUEST['_wpnonce'] ) ) {
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
			$followers = \array_map( 'esc_url_raw', \wp_unslash( $_REQUEST['followers'] ) );

			foreach ( $followers as $follower ) {
				Follower_Collection::remove_follower( $this->user_id, $follower );
			}
		}
	}

	/**
	 * Message to be displayed when there are no followers.
	 */
	public function no_items() {
		\esc_html_e( 'No followers found.', 'activitypub' );
	}
}
