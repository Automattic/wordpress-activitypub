<?php
namespace Activitypub\Table;

use WP_List_Table;
use Activitypub\Collection\Users;
use Activitypub\Collection\Followers as FollowerCollection;
use Activitypub\Collection\Follow_Requests as FollowerRequestCollection;
use Activitypub\Model\Follow_Request;

use function Activitypub\object_to_uri;

if ( ! \class_exists( '\WP_List_Table' ) ) {
		require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Table that shows all follow requests for a user and allows handling those requests.
 */
class Follow_Requests extends WP_List_Table {
	private $user_id;
	public $follow_requests_count = 0;

	public function __construct( $user_id = null ) {

		if ( $user_id ) {
			$this->user_id = $user_id;
		} else {
			$this->user_id = \get_current_user_id();
		}

		parent::__construct(
			array(
				'singular' => \__( 'Follower', 'activitypub' ),
				'plural'   => \__( 'Followers', 'activitypub' ),
				'ajax'     => true,
			)
		);
	}

	public function get_columns() {
		return array(
			'cb'         => '<input type="button">',
			'action'     => \__( 'Action', 'activitypub' ),
			'name'       => \__( 'Name', 'activitypub' ),
			'url'        => \__( 'URL', 'activitypub' ),
			'status'     => \__( 'Status', 'activitypub' ),
			'published'  => \__( 'Created', 'activitypub' ),
			'modified'   => \__( 'Last updated', 'activitypub' ),
		);
	}

	public function get_sortable_columns() {
		$sortable_columns = array(
			'status' => array( 'status', false ),
			'name' => array( 'name', true ),
			'modified'   => array( 'modified', false ),
			'published'  => array( 'published', false ),
		);

		return $sortable_columns;
	}

	public function prepare_items() {
		$columns = $this->get_columns();
		$hidden  = array();

		$this->process_action();
		$this->_column_headers = array( $columns, $hidden, $this->get_sortable_columns() );

		$page_num = $this->get_pagenum();
		$per_page = 20;

		$args = array();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['orderby'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$args['orderby'] = sanitize_text_field( wp_unslash( $_GET['orderby'] ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['order'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$args['order'] = sanitize_text_field( wp_unslash( $_GET['order'] ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['s'] ) && isset( $_REQUEST['_wpnonce'] ) ) {
			$nonce = sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) );
			if ( wp_verify_nonce( $nonce, 'bulk-' . $this->_args['plural'] ) ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$args['s'] = sanitize_text_field( wp_unslash( $_GET['s'] ) );
			}
		}

		$follow_requests_with_count = FollowerRequestCollection::get_follow_requests_for_user( $this->user_id, $per_page, $page_num, $args );

		$follow_requests = $follow_requests_with_count['follow_requests'];
		$counter         = $follow_requests_with_count['total_items'];
		$this->follow_requests_count = $counter;

		$this->items = array();
		$this->set_pagination_args(
			array(
				'total_items' => $counter,
				'total_pages' => ceil( $counter / $per_page ),
				'per_page'    => $per_page,
			)
		);

		foreach ( $follow_requests as $follow_request ) {
			$item = array(
				'status'     => esc_attr( $follow_request->status ),
				'name'       => esc_attr( $follow_request->post_title ),
				'url'        => esc_attr( $follow_request->follower_guid ),
				'guid'       => esc_attr( $follow_request->guid ),
				'id'         => esc_attr( $follow_request->id ),
				'published'  => esc_attr( $follow_request->published ),
				'modified'   => esc_attr( $follow_request->follower_modified ),
			);

			$this->items[] = $item;
		}
	}

	public function get_bulk_actions() {
		return array(
			'reject'  => __( 'Reject', 'activitypub' ),
			'approve' => __( 'Approve', 'activitypub' ),
		);
	}

	public function column_default( $item, $column_name ) {
		if ( ! array_key_exists( $column_name, $item ) ) {
			return __( 'None', 'activitypub' );
		}
		return $item[ $column_name ];
	}

	public function column_status( $item ) {
		$status = $item['status'];
		switch ( $status ) {
			case 'approved':
				$color = 'success';
				break;
			case 'pending':
				$color = 'warning';
				break;
			case 'rejected':
				$color = 'danger';
				break;
			default:
				$color = 'warning';
		}
		return sprintf(
			'<span class="activitypub-settings-label activitypub-settings-label-%s">%s</span>',
			$color,
			ucfirst( $status )
		);
	}

	public function column_avatar( $item ) {
		return sprintf(
			'<img src="%s" width="25px;" />',
			$item['icon']
		);
	}

	public function column_url( $item ) {
		return sprintf(
			'<a href="%s" target="_blank">%s</a>',
			$item['url'],
			$item['url']
		);
	}

	public function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="follow_requests[]" value="%s" />', esc_attr( $item['id'] ) );
	}

	public function ajax_response() {
		global $_REQUEST;
		$follow_action = isset( $_REQUEST['follow_action'] ) ? sanitize_title( wp_unslash( $_REQUEST['follow_action'] ) ) : null;
		$follow_request_id = isset( $_REQUEST['follow_request'] ) ? (int) $_REQUEST['follow_request'] : null;
		$wp_nonce   = isset( $_REQUEST['_wpnonce'] ) ? sanitize_title( wp_unslash( $_REQUEST['_wpnonce'] ) ) : null;
		if ( ! $follow_action || ! $follow_request_id || ! $wp_nonce ) {
			return;
		}
		wp_verify_nonce( $wp_nonce, "activitypub_{$follow_action}_follow_request" );
		$follow_request = Follow_Request::from_wp_id( $follow_request_id );

		if ( $follow_request->can_handle_follow_request() ) {
			switch ( $follow_action ) {
				case 'approve':
					$follow_request->approve();
					wp_die( 'approved' );
					break;
				case 'reject':
					$follow_request->reject();
					wp_die( 'rejected' );
					break;
				case 'delete':
					$follow_request->delete();
					wp_die( 'deleted' );
					break;
			}
		}
		return;
	}

	private static function display_follow_request_action_button( $id, $follow_action, $display = true ) {
		$url = add_query_arg(
			array(
				'follow_request' => $id,
				'action'         => 'activitypub_handle_follow_request',
				'follow_action'  => $follow_action,
				'_wpnonce'       => wp_create_nonce( "activitypub_{$follow_action}_follow_request" ),
			),
			admin_url( 'admin-ajax.php' )
		);
		if ( $display ) {
			$type = 'button';
		} else {
			$type = 'hidden';
		}
		switch ( $follow_action ) {
			case 'approve':
				$follow_action_text = __( 'Approve', 'activitypub' );
				break;
			case 'delete':
				$follow_action_text = __( 'Delete', 'activitypub' );
				break;
			case 'reject':
				$follow_action_text = __( 'Reject', 'activitypub' );
				break;
			default:
				return;
		}

		printf(
			'<input type="%s" class="button" value="%s" data-action="%s">',
			esc_attr( $type ),
			esc_attr( $follow_action_text ),
			esc_url( $url )
		);
	}

	public function column_action( $item ) {
		$status = $item['status'];

		printf( '<div class="activitypub-settings-action-buttons">' );

		// TODO this can be written smarter, but at least it is readable.
		if ( 'pending' === $status ) {
			self::display_follow_request_action_button( $item['id'], 'approve' );
			self::display_follow_request_action_button( $item['id'], 'reject' );
			self::display_follow_request_action_button( $item['id'], 'delete', false );
		}

		if ( 'approved' === $status ) {
			self::display_follow_request_action_button( $item['id'], 'approve', false );
			self::display_follow_request_action_button( $item['id'], 'reject' );
			self::display_follow_request_action_button( $item['id'], 'delete', false );
		}

		if ( 'rejected' === $status ) {
			self::display_follow_request_action_button( $item['id'], 'approve', false ); // TODO: Clarify with Mobilizon
			self::display_follow_request_action_button( $item['id'], 'reject', false );
			self::display_follow_request_action_button( $item['id'], 'delete' );
		}

		printf( '</div>' );
	}

	public function process_action() {
		if ( ! isset( $_REQUEST['follow_requests'] ) || ! isset( $_REQUEST['_wpnonce'] ) ) {
			return false;
		}
		$nonce = sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) );
		if ( ! wp_verify_nonce( $nonce, 'bulk-' . $this->_args['plural'] ) ) {
			return false;
		}

		if ( ! current_user_can( 'edit_user', $this->user_id ) ) {
			return false;
		}

		$follow_requests = $_REQUEST['follow_requests']; // phpcs:ignore

		switch ( $this->current_action() ) {
			case 'reject':
				if ( ! is_array( $follow_requests ) ) {
					$follow_requests = array( $follow_requests );
				}
				foreach ( $follow_requests as $follow_request ) {
					Follow_Request::from_wp_id( $follow_request )->reject();
				}
				break;
			case 'approve':
				if ( ! is_array( $follow_requests ) ) {
					$follow_requests = array( $follow_requests );
				}
				foreach ( $follow_requests as $follow_request ) {
					Follow_Request::from_wp_id( $follow_request )->approve();
				}
				break;
		}
	}

	public function follow_requests_count() {
		return $this->follow_requests_count;
	}
}
