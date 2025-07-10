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
			'username'   => array( 'username', true ),
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
		$status  = 'all';

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

		if ( isset( $_GET['status'] ) ) {
			$status = sanitize_text_field( wp_unslash( $_GET['status'] ) );
		}

		$following_with_count = Following_Collection::get_following_with_count( $this->user_id, $per_page, $page_num, $args, $status );
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

		foreach ( $following as $post ) {
			$actor      = Actors::get_actor( $post );
			$class      = 'approved';
			$is_pending = $this->is_pending( $post->ID );

			if ( $is_pending ) {
				$class = 'unapproved';
			}

			$this->items[] = array(
				'id'         => $post->ID,
				'icon'       => \esc_attr( $actor->get_icon()['url'] ?? '' ),
				'post_title' => \esc_attr( $actor->get_name() ),
				'username'   => \esc_attr( $actor->get_preferred_username() ),
				'url'        => \esc_attr( object_to_uri( $actor->get_url() ) ),
				'identifier' => \esc_attr( $actor->get_id() ),
				'published'  => \esc_attr( $actor->get_published() ),
				'modified'   => \esc_attr( $actor->get_updated() ),
				'class'      => $class,
				'status'     => $is_pending ? \__( 'Pending', 'activitypub' ) : \__( 'Accepted', 'activitypub' ),
			);
		}
	}

	/**
	 * Returns views.
	 *
	 * @return string[]
	 */
	public function get_views() {
		$count_all      = Following_Collection::count_following( $this->user_id );
		$count_pending  = Following_Collection::count_following( $this->user_id, 'pending' );
		$count_accepted = Following_Collection::count_following( $this->user_id, 'accepted' );
		$path           = 'users.php?page=activitypub-following-list';
		$status         = 'all';

		if ( Actors::BLOG_USER_ID === $this->user_id ) {
			$path = 'options-general.php?page=activitypub&tab=following';
		}

		if ( isset( $_GET['status'] ) ) {
			$status = sanitize_text_field( wp_unslash( $_GET['status'] ) );
		}

		$links = array(
			'all'      => array(
				'url'     => admin_url( $path ),
				'label'   => sprintf(
					/* translators: %s: Number of users. */
					\_nx(
						'All <span class="count">(%s)</span>',
						'All <span class="count">(%s)</span>',
						$count_all,
						'users',
						'activitypub'
					),
					number_format_i18n( $count_all )
				),
				'current' => 'all' === $status,
			),
			'accepted' => array(
				'url'     => admin_url( $path . '&status=accepted' ),
				'label'   => sprintf(
					/* translators: %s: Number of users. */
					_nx(
						'Accepted <span class="count">(%s)</span>',
						'Accepted <span class="count">(%s)</span>',
						$count_accepted,
						'users',
						'activitypub'
					),
					number_format_i18n( $count_accepted )
				),
				'current' => 'accepted' === $status,
			),
			'pending'  => array(
				'url'     => admin_url( $path . '&status=pending' ),
				'label'   => sprintf(
					/* translators: %s: Number of users. */
					_nx(
						'Pending <span class="count">(%s)</span>',
						'Pending <span class="count">(%s)</span>',
						$count_pending,
						'users',
						'activitypub'
					),
					number_format_i18n( $count_pending )
				),
				'current' => 'pending' === $status,
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
		return sprintf(
			'<img src="%1$s" width="32" height="32" alt="%2$s" loading="lazy"/> <strong><a href="%3$s">%4$s</a></strong><br />',
			\esc_url( $item['icon'] ),
			\esc_attr( $item['username'] ),
			\esc_url( $item['url'] ),
			\esc_html( $item['post_title'] )
		);
	}

	/**
	 * Column published.
	 *
	 * @param array $item Item.
	 * @return string
	 */
	public function column_published( $item ) {
		$published = \strtotime( $item['published'] );

		return \sprintf(
			'<time datetime="%1$s">%2$s</time>',
			\esc_attr( \gmdate( 'c', $published ) ),
			\esc_html( \gmdate( \get_option( 'date_format' ), $published ) )
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
		printf(
			"<tr id='following-%s' class='%s'>",
			esc_attr( $item['id'] ),
			esc_attr( $item['class'] )
		);
		$this->single_row_columns( $item );
		printf( "</tr>\n" );
	}

	/**
	 * Check if the item is pending.
	 *
	 * @param int $item_id Item ID.
	 *
	 * @return bool
	 */
	protected function is_pending( $item_id ) {
		$pending = \get_post_meta( $item_id, Following_Collection::PENDING_META_KEY, false );

		return \is_array( $pending ) && \in_array( (string) $this->user_id, $pending, true );
	}
}
