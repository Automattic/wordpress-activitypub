<?php
/**
 * Followers Table-Class file.
 *
 * @package Activitypub
 */

namespace Activitypub\WP_Admin\Table;

use Activitypub\Collection\Actors;
use Activitypub\Collection\Following as Following_Collection;
use Activitypub\Collection\Remote_Actors;
use Activitypub\Moderation;
use Activitypub\Sanitize;
use Activitypub\Webfinger;

use function Activitypub\follow;
use function Activitypub\object_to_uri;

if ( ! \class_exists( '\WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Following Table-Class.
 */
class Following extends \WP_List_Table {
	use Actor_List_Table;

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

			\add_action( 'admin_notices', array( $this, 'process_admin_notices' ) );
		}

		parent::__construct(
			array(
				'singular' => \__( 'Following', 'activitypub' ),
				'plural'   => \__( 'Followings', 'activitypub' ),
				'ajax'     => false,
			)
		);

		\add_action( 'load-' . get_current_screen()->id, array( $this, 'process_action' ), 20 );
	}

	/**
	 * Process action.
	 */
	public function process_action() {
		if ( ! \current_user_can( 'edit_user', $this->user_id ) ) {
			return;
		}

		if ( ! $this->current_action() ) {
			return;
		}

		$redirect_to = \add_query_arg(
			array(
				'settings-updated' => true,  // Tell WordPress to load settings errors transient.
				'action'           => false, // Remove action parameter to prevent redirect loop.
			)
		);

		switch ( $this->current_action() ) {
			case 'delete':
				$redirect_to = \remove_query_arg( array( 'follower', 'following' ), $redirect_to );

				// Handle single follower deletion.
				if ( isset( $_GET['follower'], $_GET['_wpnonce'] ) ) {
					$follower = \absint( $_GET['follower'] );
					$nonce    = \sanitize_text_field( \wp_unslash( $_GET['_wpnonce'] ) );

					if ( \wp_verify_nonce( $nonce, 'delete-follower_' . $follower ) ) {
						Following_Collection::unfollow( $follower, $this->user_id );

						\add_settings_error( 'activitypub', 'follower_deleted', \__( 'Account unfollowed.', 'activitypub' ), 'success' );
					}
				}

				// Handle bulk actions.
				if ( isset( $_REQUEST['following'], $_REQUEST['_wpnonce'] ) ) {
					$nonce = \sanitize_text_field( \wp_unslash( $_REQUEST['_wpnonce'] ) );

					if ( \wp_verify_nonce( $nonce, 'bulk-' . $this->_args['plural'] ) ) {
						$following = array_map( 'absint', \wp_unslash( $_REQUEST['following'] ) );

						foreach ( $following as $post_id ) {
							Following_Collection::unfollow( $post_id, $this->user_id );
						}

						$count = \count( $following );
						/* translators: %d: Number of accounts unfollowed. */
						$message = \_n( '%d account unfollowed.', '%d accounts unfollowed.', $count, 'activitypub' );
						$message = \sprintf( $message, \number_format_i18n( $count ) );

						\add_settings_error( 'activitypub', 'followers_deleted', $message, 'success' );
					}
				}
				break;
			case 'follow':
				$redirect_to = \remove_query_arg( array( 'resource', 's' ), $redirect_to );

				if ( ! isset( $_REQUEST['activitypub-profile'], $_REQUEST['_wpnonce'] ) ) {
					return;
				}

				$nonce = \sanitize_text_field( \wp_unslash( $_REQUEST['_wpnonce'] ) );
				if ( ! \wp_verify_nonce( $nonce, 'activitypub-follow-nonce' ) ) {
					return;
				}

				$original = \sanitize_text_field( \wp_unslash( $_REQUEST['activitypub-profile'] ) );
				$profile  = Remote_Actors::normalize_identifier( $original );
				if ( ! $profile ) {
					/* translators: %s: Account profile that could not be followed */
					\add_settings_error( 'activitypub', 'followed', \sprintf( \__( 'Unable to follow account &#8220;%s&#8221;. Please verify the account exists and try again.', 'activitypub' ), \esc_html( $profile ) ) );
					$redirect_to = \add_query_arg( 'resource', $original, $redirect_to );
					break;
				}

				// Check if actor is blocked.
				if ( Moderation::is_actor_blocked( $profile, $this->user_id ) ) {
					/* translators: %s: Account profile that could not be followed */
					\add_settings_error( 'activitypub', 'followed', \sprintf( \__( 'Unable to follow account &#8220;%s&#8221;. The account is blocked.', 'activitypub' ), \esc_html( $profile ) ) );
					$redirect_to = \add_query_arg( 'resource', $original, $redirect_to );
					break;
				}

				$result = follow( $profile, $this->user_id );
				if ( \is_wp_error( $result ) ) {
					/* translators: %s: Account profile that could not be followed */
					\add_settings_error( 'activitypub', 'followed', \sprintf( \__( 'Unable to follow account &#8220;%s&#8221;. Please verify the account exists and try again.', 'activitypub' ), \esc_html( $profile ) ) );
					$redirect_to = \add_query_arg( 'resource', $original, $redirect_to );
				} else {
					\add_settings_error( 'activitypub', 'followed', \__( 'Account followed.', 'activitypub' ), 'success' );
				}

				break;
			default:
				break;
		}

		\set_transient( 'settings_errors', get_settings_errors(), 30 ); // 30 seconds.

		\wp_safe_redirect( $redirect_to );
		exit;
	}

	/**
	 * Process admin notices based on query parameters.
	 */
	public function process_admin_notices() {
		\settings_errors( 'activitypub' );
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
			'webfinger'  => \__( 'Profile', 'activitypub' ),
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
		$status   = Following_Collection::ALL;
		$page_num = $this->get_pagenum();
		$per_page = $this->get_items_per_page( 'activitypub_following_per_page' );
		$args     = array();

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['orderby'] ) ) {
			$args['orderby'] = \sanitize_text_field( \wp_unslash( $_GET['orderby'] ) );
		}

		if ( isset( $_GET['order'] ) ) {
			$args['order'] = \sanitize_text_field( \wp_unslash( $_GET['order'] ) );
		}

		if ( isset( $_GET['s'] ) ) {
			$args['s'] = $this->normalize_search_term( \wp_unslash( $_GET['s'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
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
			$actor = Remote_Actors::get_actor( $following );
			if ( \is_wp_error( $actor ) ) {
				continue;
			}

			$this->items[] = array(
				'id'         => $following->ID,
				'icon'       => object_to_uri( $actor->get_icon() ?? ACTIVITYPUB_PLUGIN_URL . 'assets/img/mp.jpg' ),
				'post_title' => $actor->get_name() ?? $actor->get_preferred_username(),
				'username'   => $actor->get_preferred_username(),
				'url'        => object_to_uri( $actor->get_url() ?? $actor->get_id() ),
				'webfinger'  => Remote_Actors::get_acct( $following->ID ),
				'status'     => Following_Collection::check_status( $this->user_id, $following->ID ),
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

		if ( ! empty( $_GET['status'] ) ) {
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
		return \sprintf( '<input type="checkbox" name="following[]" value="%s" />', \esc_attr( $item['id'] ) );
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
			( Following_Collection::PENDING === $item['status'] )
		) {
			$status = \sprintf( '<strong class="pending"> — %s</strong>', \esc_html__( 'Pending', 'activitypub' ) );
		}

		return sprintf(
			'<img src="%1$s" width="32" height="32" alt="%2$s" loading="lazy"/> <strong><a href="%3$s" target="_blank">%4$s</a></strong>%5$s<br />',
			\esc_url( $item['icon'] ),
			\esc_attr( $item['post_title'] ),
			\esc_url( $item['url'] ),
			\esc_html( $item['username'] ),
			$status
		);
	}

	/**
	 * Column WebFinger.
	 *
	 * @param array $item Item.
	 *
	 * @return string The WebFinger link.
	 */
	public function column_webfinger( $item ) {
		$webfinger = Sanitize::webfinger( $item['webfinger'] );

		return \sprintf(
			'<a href="%1$s" target="_blank" title="%1$s">@%2$s</a>',
			\esc_url( $item['url'] ),
			\esc_html( $webfinger )
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
	 * Message to be displayed when there are no followings.
	 */
	public function no_items() {
		\esc_html_e( 'No profiles found.', 'activitypub' );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$search = \sanitize_text_field( \wp_unslash( $_GET['s'] ?? '' ) );
		if ( empty( $search ) ) {
			return;
		}

		$search = Sanitize::webfinger( $search );
		if ( filter_var( $search, FILTER_VALIDATE_EMAIL ) ) {
			return;
		}

		$search = Webfinger::resolve( $search );

		if ( ! is_wp_error( $search ) && filter_var( $search, FILTER_VALIDATE_URL ) ) {
			$actor = Remote_Actors::fetch_by_uri( $search );
			if ( ! is_wp_error( $actor ) ) {
				echo ' ';
				\printf(
					/* translators: %s: Actor name. */
					\esc_html__( 'Would you like to follow %s?', 'activitypub' ),
					\sprintf(
						'<a href="%s">%s</a>',
						\esc_url( \add_query_arg( 'resource', $search ) ),
						\esc_html( $actor->post_title )
					)
				);
			}
		}
	}

	/**
	 * Single row.
	 *
	 * @param array $item Item.
	 */
	public function single_row( $item ) {
		\printf(
			'<tr id="following-%1$s" class="status-%2$s">',
			\esc_attr( $item['id'] ),
			\esc_attr( $item['status'] )
		);
		$this->single_row_columns( $item );
		\printf( "</tr>\n" );
	}

	/**
	 * Handles the row actions for each following item.
	 *
	 * @param array  $item        The current following item.
	 * @param string $column_name The current column name.
	 * @param string $primary     The primary column name.
	 *
	 * @return string HTML for the row actions.
	 */
	protected function handle_row_actions( $item, $column_name, $primary ) {
		if ( $column_name !== $primary ) {
			return '';
		}

		$actions = array(
			'unfollow' => sprintf(
				'<a href="%s" aria-label="%s">%s</a>',
				$this->get_action_url( 'delete', $item['id'] ),
				/* translators: %s: username. */
				\esc_attr( \sprintf( \__( 'Unfollow %s', 'activitypub' ), $item['username'] ) ),
				\esc_html__( 'Unfollow', 'activitypub' )
			),
		);

		/**
		 * Filters the array of row action links on the Following list table.
		 *
		 * This filter allows you to modify the row actions for each following item in the Following list table.
		 *
		 * @since 7.5.0
		 *
		 * @param string[] $actions An array of row action links. Defaults include 'Unfollow'.
		 * @param array    $item    The current following item.
		 */
		$actions = apply_filters( 'activitypub_following_row_actions', $actions, $item );

		return $this->row_actions( $actions );
	}
}
