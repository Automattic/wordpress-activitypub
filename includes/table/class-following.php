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
			'post_title' => \__( 'Name', 'activitypub' ),
			'avatar'     => \__( 'Avatar', 'activitypub' ),
			'username'   => \__( 'Username', 'activitypub' ),
			'url'        => \__( 'URL', 'activitypub' ),
			'published'  => \__( 'Followed', 'activitypub' ),
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
			'post_title' => array( 'post_title', true ),
			'modified'   => array( 'modified', false ),
			'published'  => array( 'published', false ),
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
			$args['orderby'] = sanitize_text_field( wp_unslash( $_GET['orderby'] ) );
		}

		if ( isset( $_GET['order'] ) ) {
			$args['order'] = sanitize_text_field( wp_unslash( $_GET['order'] ) );
		}

		if ( isset( $_GET['s'] ) ) {
			$args['s'] = sanitize_text_field( wp_unslash( $_GET['s'] ) );
		}
		$following_with_count = Following_Collection::get_following_with_count( $this->user_id, $per_page, $page_num, $args );
		$following            = $following_with_count['following'];
		$counter              = $following_with_count['total'];

		$this->items = array();
		$this->set_pagination_args(
			array(
				'total_items' => $counter,
				'total_pages' => ceil( $counter / $per_page ),
				'per_page'    => $per_page,
			)
		);

		foreach ( $following as $follow_id ) {
			$actor = Actors::get_actor( $follow_id );
			$item  = array(
				'icon'       => \esc_attr( $actor->get_icon()['url'] ?? '' ),
				'post_title' => \esc_attr( $actor->get_name() ),
				'username'   => \esc_attr( $actor->get_preferred_username() ),
				'url'        => \esc_attr( object_to_uri( $actor->get_url() ) ),
				'identifier' => \esc_attr( $actor->get_id() ),
				'published'  => \esc_attr( $actor->get_published() ),
				'modified'   => \esc_attr( $actor->get_updated() ),
			);

			$this->items[] = $item;
		}
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
			return \__( 'None', 'activitypub' );
		}
		return $item[ $column_name ];
	}

	/**
	 * Column avatar.
	 *
	 * @param array $item Item.
	 * @return string
	 */
	public function column_avatar( $item ) {
		return sprintf(
			'<img src="%s" width="25px;" alt="" />',
			$item['icon']
		);
	}

	/**
	 * Column url.
	 *
	 * @param array $item Item.
	 * @return string
	 */
	public function column_url( $item ) {
		return sprintf(
			'<a href="%s" target="_blank">%s</a>',
			esc_url( $item['url'] ),
			$item['url']
		);
	}

	/**
	 * Column cb.
	 *
	 * @param array $item Item.
	 * @return string
	 */
	public function column_cb( $item ) {
		return \sprintf( '<input type="checkbox" name="following[]" value="%s" />', esc_attr( $item['identifier'] ) );
	}

	/**
	 * Process action.
	 */
	public function process_action() {
		if ( ! isset( $_REQUEST['following'] ) || ! isset( $_REQUEST['_wpnonce'] ) ) {
			return;
		}
		$nonce = \sanitize_text_field( \wp_unslash( $_REQUEST['_wpnonce'] ) );
		if ( ! \wp_verify_nonce( $nonce, 'bulk-' . $this->_args['plural'] ) ) {
			return;
		}

		if ( ! \current_user_can( 'edit_user', $this->user_id ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$following_raw = \wp_unslash( $_REQUEST['following'] );
		$following     = is_array( $following_raw ) ? array_map( 'esc_url_raw', $following_raw ) : array( esc_url_raw( $following_raw ) );

		if ( $this->current_action() === 'delete' ) {
			if ( ! is_array( $following ) ) {
				$following = array( $following );
			}
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
	 * Returns user count.
	 *
	 * @return int
	 */
	public function get_user_count() {
		return Following_Collection::count_following( $this->user_id );
	}
}
