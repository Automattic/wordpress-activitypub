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

		\add_action( 'load-' . get_current_screen()->id, array( $this, 'process_action' ), 20 );
		\add_action( 'admin_notices', array( $this, 'process_admin_notices' ) );
	}

	/**
	 * Process action.
	 */
	public function process_action() {
		if ( ! \current_user_can( 'edit_user', $this->user_id ) ) {
			return;
		}

		switch ( $this->current_action() ) {
			case 'delete':
				// Handle single follower deletion.
				if ( isset( $_GET['follower'], $_GET['_wpnonce'] ) ) {
					$follower = \esc_url_raw( \wp_unslash( $_GET['follower'] ) );
					$nonce    = \sanitize_text_field( \wp_unslash( $_GET['_wpnonce'] ) );

					if ( \wp_verify_nonce( $nonce, 'delete-follower_' . $follower ) ) {
						Follower_Collection::remove_follower( $this->user_id, $follower );

						$redirect_args = array(
							'updated' => 'true',
							'action'  => 'deleted',
						);

						\wp_safe_redirect( \add_query_arg( $redirect_args ) );
						exit;
					}
				}

				// Handle bulk actions.
				if ( isset( $_REQUEST['followers'], $_REQUEST['_wpnonce'] ) ) {
					$nonce = \sanitize_text_field( \wp_unslash( $_REQUEST['_wpnonce'] ) );

					if ( \wp_verify_nonce( $nonce, 'bulk-' . $this->_args['plural'] ) ) {
						$followers = \array_map( 'esc_url_raw', \wp_unslash( $_REQUEST['followers'] ) );
						foreach ( $followers as $follower ) {
							Follower_Collection::remove_follower( $this->user_id, $follower );
						}

						$redirect_args = array(
							'updated' => 'true',
							'action'  => 'all_deleted',
							'count'   => \count( $followers ),
						);

						\wp_safe_redirect( \add_query_arg( $redirect_args ) );
						exit;
					}
				}
				break;

			default:
				break;
		}
	}

	/**
	 * Process admin notices based on query parameters.
	 */
	public function process_admin_notices() {
		if ( isset( $_REQUEST['updated'] ) && 'true' === $_REQUEST['updated'] && ! empty( $_REQUEST['action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$message = '';
			switch ( $_REQUEST['action'] ) { // phpcs:ignore WordPress.Security.NonceVerification
				case 'deleted':
					$message = \__( 'Follower deleted.', 'activitypub' );
					break;
				case 'all_deleted':
					$count = \absint( $_REQUEST['count'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
					/* translators: %d: Number of followers deleted. */
					$message = \_n( '%d follower deleted.', '%d followers deleted.', $count, 'activitypub' );
					$message = \sprintf( $message, \number_format_i18n( $count ) );
					break;
			}

			if ( ! empty( $message ) ) {
				\wp_admin_notice(
					$message,
					array(
						'type'        => 'success',
						'dismissible' => true,
						'id'          => 'message',
					)
				);
			}
		}
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
		$page_num = $this->get_pagenum();
		$per_page = $this->get_items_per_page( 'activitypub_followers_per_page' );
		$args     = array();

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['orderby'] ) ) {
			$args['orderby'] = \sanitize_text_field( \wp_unslash( $_GET['orderby'] ) );
		}

		if ( isset( $_GET['order'] ) ) {
			$args['order'] = \sanitize_text_field( \wp_unslash( $_GET['order'] ) );
		}

		if ( ! empty( $_GET['s'] ) ) {
			$search = \sanitize_text_field( \wp_unslash( $_GET['s'] ) );
			$search = \str_replace( 'acct:', '', $search );
			$search = \str_replace( '@', ' ', $search );
			$search = \str_replace( 'http://', '', $search );
			$search = \str_replace( 'https://', '', $search );
			$search = \str_replace( 'www.', '', $search );
			$search = \trim( $search );

			$args['s'] = $search;
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
	 * Message to be displayed when there are no followers.
	 */
	public function no_items() {
		\esc_html_e( 'No followers found.', 'activitypub' );
	}

	/**
	 * Handles the row actions for each follower item.
	 *
	 * @param array  $item        The current follower item.
	 * @param string $column_name The current column name.
	 * @param string $primary     The primary column name.
	 * @return string HTML for the row actions.
	 */
	protected function handle_row_actions( $item, $column_name, $primary ) {
		if ( $column_name !== $primary ) {
			return '';
		}

		$actions = array(
			'delete' => sprintf(
				'<a href="%s" aria-label="%s">%s</a>',
				\wp_nonce_url(
					\add_query_arg(
						array(
							'action'   => 'delete',
							'follower' => $item['identifier'],
						)
					),
					'delete-follower_' . $item['identifier']
				),
				/* translators: %s: username. */
				\esc_attr( \sprintf( \__( 'Delete %s', 'activitypub' ), $item['username'] ) ),
				\esc_html__( 'Delete', 'activitypub' )
			),
		);

		return $this->row_actions( $actions );
	}
}
