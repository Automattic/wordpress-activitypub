<?php
/**
 * Blocked Actors Table-Class file.
 *
 * @package Activitypub
 */

namespace Activitypub\WP_Admin\Table;

use Activitypub\Collection\Actors;
use Activitypub\Collection\Blocked_Actors as Blocked_Actors_Collection;
use Activitypub\Collection\Remote_Actors;
use Activitypub\Moderation;
use Activitypub\Sanitize;
use Activitypub\Webfinger;

use function Activitypub\object_to_uri;

if ( ! \class_exists( '\WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Blocked Actors Table-Class.
 */
class Blocked_Actors extends \WP_List_Table {
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
		if ( \get_current_screen()->id === 'settings_page_activitypub' ) {
			$this->user_id = Actors::BLOG_USER_ID;
		} else {
			$this->user_id = \get_current_user_id();

			\add_action( 'admin_notices', array( $this, 'process_admin_notices' ) );
		}

		parent::__construct(
			array(
				'singular' => \__( 'Blocked Actor', 'activitypub' ),
				'plural'   => \__( 'Blocked Actors', 'activitypub' ),
				'ajax'     => false,
			)
		);

		\add_action( 'load-' . \get_current_screen()->id, array( $this, 'process_action' ), 20 );
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
				$redirect_to = \remove_query_arg( array( 'follower', 'blocked' ), $redirect_to );

				// Handle single actor unblock.
				if ( isset( $_GET['follower'], $_GET['_wpnonce'] ) ) {
					$actor_id = \absint( $_GET['follower'] );
					$nonce    = \sanitize_text_field( \wp_unslash( $_GET['_wpnonce'] ) );

					if ( \wp_verify_nonce( $nonce, 'delete-follower_' . $actor_id ) ) {
						Moderation::remove_user_block( $this->user_id, Moderation::TYPE_ACTOR, $actor_id );

						\add_settings_error( 'activitypub', 'actor_unblocked', \__( 'Actor unblocked.', 'activitypub' ), 'success' );
					}
				}

				// Handle bulk actions.
				if ( isset( $_REQUEST['blocked'], $_REQUEST['_wpnonce'] ) ) {
					$nonce = \sanitize_text_field( \wp_unslash( $_REQUEST['_wpnonce'] ) );

					if ( \wp_verify_nonce( $nonce, 'bulk-' . $this->_args['plural'] ) ) {
						$blocked = \array_map( 'absint', \wp_unslash( $_REQUEST['blocked'] ) );

						foreach ( $blocked as $post_id ) {
							Moderation::remove_user_block( $this->user_id, Moderation::TYPE_ACTOR, $post_id );
						}

						$count = \count( $blocked );
						/* translators: %d: Number of actors unblocked. */
						$message = \_n( '%d actor unblocked.', '%d actors unblocked.', $count, 'activitypub' );
						$message = \sprintf( $message, \number_format_i18n( $count ) );

						\add_settings_error( 'activitypub', 'actors_unblocked', $message, 'success' );
					}
				}
				break;
			case 'block':
				$redirect_to = \remove_query_arg( array( 'resource', 's' ), $redirect_to );

				if ( ! isset( $_REQUEST['activitypub-profile'], $_REQUEST['_wpnonce'] ) ) {
					return;
				}

				$nonce = \sanitize_text_field( \wp_unslash( $_REQUEST['_wpnonce'] ) );
				if ( ! \wp_verify_nonce( $nonce, 'activitypub-block-nonce' ) ) {
					return;
				}

				$original = \sanitize_text_field( \wp_unslash( $_REQUEST['activitypub-profile'] ) );
				$profile  = Remote_Actors::normalize_identifier( $original );
				if ( ! $profile ) {
					/* translators: %s: Account profile that could not be blocked */
					\add_settings_error( 'activitypub', 'blocked', \sprintf( \__( 'Unable to block actor &#8220;%s&#8221;. Please verify the account exists and try again.', 'activitypub' ), \esc_html( $original ) ) );
					$redirect_to = \add_query_arg( 'resource', $original, $redirect_to );
					break;
				}

				$result = Moderation::add_user_block( $this->user_id, Moderation::TYPE_ACTOR, $profile );
				if ( \is_wp_error( $result ) ) {
					/* translators: %s: Account profile that could not be blocked */
					\add_settings_error( 'activitypub', 'blocked', \sprintf( \__( 'Unable to block actor &#8220;%s&#8221;. Please verify the account exists and try again.', 'activitypub' ), \esc_html( $original ) ) );
					$redirect_to = \add_query_arg( 'resource', $original, $redirect_to );
				} else {
					\add_settings_error( 'activitypub', 'blocked', \__( 'Actor blocked.', 'activitypub' ), 'success' );
				}

				break;
			default:
				break;
		}

		\set_transient( 'settings_errors', \get_settings_errors(), 30 ); // 30 seconds.

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
			'modified'   => \__( 'Blocked date', 'activitypub' ),
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
		$per_page = $this->get_items_per_page( 'activitypub_blocked_actors_per_page' );
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

		$blocked_with_count = Blocked_Actors_Collection::get_blocked_actors_with_count( $this->user_id, $per_page, $page_num, $args );

		$blocked_actor_posts = $blocked_with_count['blocked_actors'];
		$counter             = $blocked_with_count['total'];

		$this->items = array();
		$this->set_pagination_args(
			array(
				'total_items' => $counter,
				'total_pages' => \ceil( $counter / $per_page ),
				'per_page'    => $per_page,
			)
		);

		foreach ( $blocked_actor_posts as $blocked_actor_post ) {
			$actor = Remote_Actors::get_actor( $blocked_actor_post );
			if ( \is_wp_error( $actor ) ) {
				continue;
			}

			$this->items[] = array(
				'id'         => $blocked_actor_post->ID,
				'icon'       => object_to_uri( $actor->get_icon() ?? ACTIVITYPUB_PLUGIN_URL . 'assets/img/mp.jpg' ),
				'post_title' => $actor->get_name() ?? $actor->get_preferred_username(),
				'username'   => $actor->get_preferred_username(),
				'url'        => object_to_uri( $actor->get_url() ?? $actor->get_id() ),
				'webfinger'  => Remote_Actors::get_acct( $blocked_actor_post->ID ),
				'identifier' => $actor->get_id(),
				'modified'   => $blocked_actor_post->post_modified_gmt,
			);
		}
	}

	/**
	 * Returns bulk actions.
	 *
	 * @return array
	 */
	public function get_bulk_actions() {
		return array(
			'delete' => \__( 'Unblock', 'activitypub' ),
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
		if ( ! \array_key_exists( $column_name, $item ) ) {
			return \esc_html__( 'None', 'activitypub' );
		}
		return \esc_html( $item[ $column_name ] );
	}

	/**
	 * Column checkbox.
	 *
	 * @param array $item Item.
	 * @return string
	 */
	public function column_cb( $item ) {
		return \sprintf( '<input type="checkbox" name="blocked[]" value="%s" />', \esc_attr( $item['id'] ) );
	}

	/**
	 * Column username.
	 *
	 * @param array $item Item.
	 * @return string
	 */
	public function column_username( $item ) {
		return \sprintf(
			'<img src="%1$s" width="32" height="32" alt="%2$s" loading="lazy"/> <strong><a href="%3$s" target="_blank">%4$s</a></strong><br />',
			\esc_url( $item['icon'] ),
			\esc_attr( $item['post_title'] ),
			\esc_url( $item['url'] ),
			\esc_html( $item['username'] )
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
	 * Message to be displayed when there are no blocked actors.
	 */
	public function no_items() {
		\esc_html_e( 'No blocked actors found.', 'activitypub' );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$search = \sanitize_text_field( \wp_unslash( $_GET['s'] ?? '' ) );
		if ( empty( $search ) ) {
			return;
		}

		$search = Sanitize::webfinger( $search );
		if ( \filter_var( $search, FILTER_VALIDATE_EMAIL ) ) {
			return;
		}

		$search = Webfinger::resolve( $search );

		if ( ! \is_wp_error( $search ) && \filter_var( $search, FILTER_VALIDATE_URL ) ) {
			$actor = Remote_Actors::fetch_by_uri( $search );
			if ( ! \is_wp_error( $actor ) ) {
				echo ' ';
				\printf(
					/* translators: %s: Actor name. */
					\esc_html__( 'Would you like to block %s?', 'activitypub' ),
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
			'<tr id="blocked-%1$s">',
			\esc_attr( $item['id'] )
		);
		$this->single_row_columns( $item );
		\printf( "</tr>\n" );
	}

	/**
	 * Handles the row actions for each blocked actor item.
	 *
	 * @param array  $item        The current blocked actor item.
	 * @param string $column_name The current column name.
	 * @param string $primary     The primary column name.
	 * @return string HTML for the row actions.
	 */
	protected function handle_row_actions( $item, $column_name, $primary ) {
		if ( $column_name !== $primary ) {
			return '';
		}

		$actions = array(
			'unblock' => sprintf(
				'<a href="%s" aria-label="%s">%s</a>',
				$this->get_action_url( 'delete', $item['id'] ),
				/* translators: %s: username. */
				\esc_attr( \sprintf( \__( 'Unblock %s', 'activitypub' ), $item['username'] ) ),
				\esc_html__( 'Unblock', 'activitypub' )
			),
		);

		/**
		 * Filters the array of row action links on the Blocked Actors list table.
		 *
		 * This filter is evaluated for each blocked actor item in the list table.
		 *
		 * @since 7.5.0
		 *
		 * @param string[] $actions An array of row action links. Defaults are
		 *                          'Unblock'.
		 * @param array    $item    The current blocked actor item.
		 */
		$actions = apply_filters( 'activitypub_blocked_actors_row_actions', $actions, $item );

		return $this->row_actions( $actions );
	}
}
