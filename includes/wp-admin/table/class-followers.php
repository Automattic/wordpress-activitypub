<?php
/**
 * Followers Table-Class file.
 *
 * @package Activitypub
 */

namespace Activitypub\WP_Admin\Table;

use Activitypub\Collection\Actors;
use Activitypub\Collection\Followers as Follower_Collection;
use Activitypub\Collection\Following;
use Activitypub\Collection\Remote_Actors;
use Activitypub\Moderation;
use Activitypub\Sanitize;
use Activitypub\Webfinger;

use function Activitypub\object_to_uri;

if ( ! \class_exists( '\WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Followers Table-Class.
 */
class Followers extends \WP_List_Table {
	use Actor_List_Table;

	/**
	 * User ID.
	 *
	 * @var int
	 */
	private $user_id;

	/**
	 * Follow URL.
	 *
	 * @var string
	 */
	public $follow_url;

	/**
	 * Constructor.
	 */
	public function __construct() {
		if ( get_current_screen()->id === 'settings_page_activitypub' ) {
			$this->user_id    = Actors::BLOG_USER_ID;
			$this->follow_url = \admin_url( 'options-general.php?page=activitypub&tab=following' );
		} else {
			$this->user_id    = \get_current_user_id();
			$this->follow_url = \admin_url( 'users.php?page=activitypub-following-list' );

			\add_action( 'admin_notices', array( $this, 'process_admin_notices' ) );
		}

		parent::__construct(
			array(
				'singular' => \__( 'Follower', 'activitypub' ),
				'plural'   => \__( 'Followers', 'activitypub' ),
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
				$redirect_to = \remove_query_arg( array( 'follower', 'followers' ), $redirect_to );

				// Handle single follower deletion.
				if ( isset( $_GET['follower'], $_GET['_wpnonce'] ) ) {
					$follower = \absint( $_GET['follower'] );
					$nonce    = \sanitize_text_field( \wp_unslash( $_GET['_wpnonce'] ) );

					if ( \wp_verify_nonce( $nonce, 'delete-follower_' . $follower ) ) {
						Follower_Collection::remove( $follower, $this->user_id );

						\add_settings_error( 'activitypub', 'follower_deleted', \__( 'Follower deleted.', 'activitypub' ), 'success' );
					}
				}

				// Handle bulk actions.
				if ( isset( $_REQUEST['followers'], $_REQUEST['_wpnonce'] ) ) {
					$nonce = \sanitize_text_field( \wp_unslash( $_REQUEST['_wpnonce'] ) );

					if ( \wp_verify_nonce( $nonce, 'bulk-' . $this->_args['plural'] ) ) {
						$followers = \array_map( 'absint', \wp_unslash( $_REQUEST['followers'] ) );
						foreach ( $followers as $follower ) {
							Follower_Collection::remove( $follower, $this->user_id );
						}

						$count = \count( $followers );
						/* translators: %d: Number of followers deleted. */
						$message = \_n( '%d follower deleted.', '%d followers deleted.', $count, 'activitypub' );
						$message = \sprintf( $message, \number_format_i18n( $count ) );

						\add_settings_error( 'activitypub', 'followers_deleted', $message, 'success' );
					}
				}
				break;

			case 'follow':
				$redirect_to = \remove_query_arg( array( 'follower', 'followers' ), $redirect_to );

				if ( isset( $_GET['follower'], $_GET['_wpnonce'] ) ) {
					$follower = \absint( $_GET['follower'] );
					$nonce    = \sanitize_text_field( \wp_unslash( $_GET['_wpnonce'] ) );

					if ( \wp_verify_nonce( $nonce, 'follow-follower_' . $follower ) ) {
						Following::follow( $follower, $this->user_id );

						\add_settings_error( 'activitypub', 'followed', \__( 'Account followed.', 'activitypub' ), 'success' );

					}
				}
				break;

			case 'block':
				$redirect_to = \remove_query_arg( array( 'follower', 'followers', 'confirm' ), $redirect_to );

				// Handle single follower block.
				if ( isset( $_GET['follower'], $_GET['_wpnonce'] ) ) {
					$follower = \absint( $_GET['follower'] );
					$nonce    = \sanitize_text_field( \wp_unslash( $_GET['_wpnonce'] ) );

					if ( \wp_verify_nonce( $nonce, 'block-follower_' . $follower ) ) {
						// If confirm is not set, show confirmation screen.
						if ( ! isset( $_GET['confirm'] ) || 'true' !== $_GET['confirm'] ) {
							$args = array(
								'actor_id' => $follower,
								'user_id'  => $this->user_id,
							);
							\load_template( ACTIVITYPUB_PLUGIN_DIR . 'templates/block-confirmation.php', false, $args );
							exit;
						}

						$blocked = $this->block_followers( array( $follower ) );
						if ( $blocked['success'] > 0 ) {
							\add_settings_error( 'activitypub', 'account_blocked', \__( 'Account blocked.', 'activitypub' ), 'success' );
						} else {
							\add_settings_error( 'activitypub', 'block_error', \__( 'Invalid account.', 'activitypub' ) );
						}
					}
				}

				// Handle bulk block actions.
				if ( isset( $_REQUEST['followers'], $_REQUEST['_wpnonce'] ) ) {
					$nonce = \sanitize_text_field( \wp_unslash( $_REQUEST['_wpnonce'] ) );

					if ( \wp_verify_nonce( $nonce, 'bulk-' . $this->_args['plural'] ) ) {
						// If confirm is not set, show confirmation screen.
						if ( ! isset( $_GET['confirm'] ) || 'true' !== $_GET['confirm'] ) {
							$followers = \array_map( 'absint', \wp_unslash( $_REQUEST['followers'] ) );
							$args      = array(
								'followers'   => $followers,
								'user_id'     => $this->user_id,
								'plural_args' => $this->_args['plural'],
							);
							\load_template( ACTIVITYPUB_PLUGIN_DIR . 'templates/bulk-block-confirmation.php', false, $args );
							exit;
						}

						$followers = \array_map( 'absint', \wp_unslash( $_REQUEST['followers'] ) );
						$blocked   = $this->block_followers( $followers );

						if ( $blocked['success'] > 0 ) {
							/* translators: %d: Number of followers blocked. */
							$message = \_n( '%d account blocked.', '%d accounts blocked.', $blocked['success'], 'activitypub' );
							$message = \sprintf( $message, \number_format_i18n( $blocked['success'] ) );
							\add_settings_error( 'activitypub', 'accounts_blocked', $message, 'success' );
						}
					}
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
			'username'   => \esc_html__( 'Username', 'activitypub' ),
			'post_title' => \esc_html__( 'Name', 'activitypub' ),
			'webfinger'  => \esc_html__( 'Profile', 'activitypub' ),
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
			$args['s'] = $this->normalize_search_term( \wp_unslash( $_GET['s'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
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
			$actor = Remote_Actors::get_actor( $follower );
			if ( \is_wp_error( $actor ) ) {
				continue;
			}

			$this->items[] = array(
				'id'         => $follower->ID,
				'icon'       => object_to_uri( $actor->get_icon() ?? ACTIVITYPUB_PLUGIN_URL . 'assets/img/mp.jpg' ),
				'post_title' => $actor->get_name() ?? $actor->get_preferred_username(),
				'username'   => $actor->get_preferred_username(),
				'url'        => object_to_uri( $actor->get_url() ?? $actor->get_id() ),
				'webfinger'  => Remote_Actors::get_acct( $follower->ID ),
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
			'block'  => \__( 'Block', 'activitypub' ),
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
		return \sprintf( '<input type="checkbox" name="followers[]" value="%s" />', \esc_attr( $item['id'] ) );
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
			\esc_attr( $item['username'] ),
			\esc_url( $item['url'] ),
			\esc_html( $item['username'] )
		);
	}

	/**
	 * Column webfinger.
	 *
	 * @param array $item Item.
	 * @return string
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
	 * Message to be displayed when there are no followers.
	 */
	public function no_items() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$search         = \sanitize_text_field( \wp_unslash( $_GET['s'] ?? '' ) );
		$actor_or_false = $this->is_followable( $search );

		if ( $actor_or_false ) {
			\printf(
				/* translators: 1: Actor name, 2: Follow link */
				\esc_html__( '%1$s is not following you, would you like to %2$s instead?', 'activitypub' ),
				\esc_html( $actor_or_false->post_title ),
				\sprintf(
					'<a href="%s">%s</a>',
					\esc_url( \add_query_arg( 'resource', $search, $this->follow_url ) ),
					\esc_html__( 'follow them', 'activitypub' )
				)
			);
		} else {
			\esc_html_e( 'No followers found.', 'activitypub' );
		}
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
				$this->get_action_url( 'delete', $item['id'] ),
				/* translators: %s: username. */
				\esc_attr( \sprintf( \__( 'Delete %s', 'activitypub' ), $item['username'] ) ),
				\esc_html__( 'Delete', 'activitypub' )
			),
			'block'  => sprintf(
				'<a href="%s" aria-label="%s" class="activitypub-block-follower">%s</a>',
				$this->get_action_url( 'block', $item['id'] ),
				/* translators: %s: username. */
				\esc_attr( \sprintf( \__( 'Block %s', 'activitypub' ), $item['username'] ) ),
				\esc_html__( 'Block', 'activitypub' )
			),
		);

		if ( \boolval( \get_option( 'activitypub_following_ui', '0' ) ) ) {
			if ( ! Following::check_status( $this->user_id, $item['id'] ) ) {
				$actions['follow'] = \sprintf(
					'<a href="%s" aria-label="%s">%s</a>',
					$this->get_action_url( 'follow', $item['id'] ),
					/* translators: %s: username. */
					\esc_attr( \sprintf( \__( 'Follow %s', 'activitypub' ), $item['username'] ) ),
					\esc_html__( 'Follow back', 'activitypub' )
				);
			}
		}

		/**
		 * Filters the array of row action links for each follower in the Followers list table.
		 *
		 * This filter allows you to modify the available row actions (such as Delete, Block, or Follow back)
		 * for each follower item displayed in the table.
		 *
		 * @since 7.5.0
		 *
		 * @param string[] $actions An array of row action links. Defaults are
		 *                          'Delete', 'Block', and optionally 'Follow back'.
		 * @param array    $item    The current follower item.
		 */
		$actions = apply_filters( 'activitypub_followers_row_actions', $actions, $item );

		return $this->row_actions( $actions );
	}

	/**
	 * Block one or more followers.
	 *
	 * @param array $follower_ids Array of follower IDs to block.
	 * @return array Array with counts of success and failure.
	 */
	private function block_followers( $follower_ids ) {
		$success_count = 0;
		$fail_count    = 0;

		foreach ( $follower_ids as $follower ) {
			$actor = Remote_Actors::get_actor( $follower );
			if ( \is_wp_error( $actor ) ) {
				++$fail_count;
				continue;
			}

			$actor_id = $actor->get_id();

			// Add user-specific block.
			$user_block_success = Moderation::add_user_block( $this->user_id, 'actor', $actor_id );

			// Add site-wide block only if user is admin and explicitly requested.
			$site_block_success = true;
			if ( \user_can( $this->user_id, 'manage_options' ) && isset( $_REQUEST['site_wide'] ) && '1' === $_REQUEST['site_wide'] ) {
				$site_block_success = Moderation::add_site_block( 'actor', $actor_id );
			}

			// Check if blocking was successful.
			if ( $user_block_success && $site_block_success ) {
				++$success_count;
			} else {
				++$fail_count;
			}
		}

		return array(
			'success' => $success_count,
			'failure' => $fail_count,
		);
	}

	/**
	 * Checks if the searched actor can be followed.
	 *
	 * @param string $search The search string.
	 *
	 * @return \WP_Post|false The actor post or false.
	 */
	private function is_followable( $search ) {
		if ( '1' !== get_option( 'activitypub_following_ui', '0' ) ) {
			return false;
		}

		if ( empty( $search ) ) {
			return false;
		}

		$search = Sanitize::webfinger( $search );
		if ( ! \filter_var( $search, FILTER_VALIDATE_EMAIL ) ) {
			return false;
		}

		$search = Webfinger::resolve( $search );
		if ( \is_wp_error( $search ) || ! \filter_var( $search, FILTER_VALIDATE_URL ) ) {
			return false;
		}

		$actor = Remote_Actors::fetch_by_uri( $search );
		if ( \is_wp_error( $actor ) ) {
			return false;
		}

		$does_follow = Following::check_status( $this->user_id, $actor->ID );
		if ( $does_follow ) {
			return false;
		}

		return $actor;
	}
}
