<?php
/**
 * FASP Registration Admin interface.
 *
 * @package Activitypub
 */

namespace Activitypub;

/**
 * FASP Registration Admin class.
 *
 * Handles the WordPress admin interface for managing FASP registrations.
 */
class Fasp_Registration_Admin {

	/**
	 * Initialize admin interface.
	 */
	public static function init() {
		add_action( 'admin_menu', array( self::class, 'add_admin_menu' ) );
		add_action( 'admin_post_approve_fasp_registration', array( self::class, 'handle_approve_registration' ) );
		add_action( 'admin_post_reject_fasp_registration', array( self::class, 'handle_reject_registration' ) );
		add_action( 'admin_post_delete_fasp_registration', array( self::class, 'handle_delete_registration' ) );
	}

	/**
	 * Add admin menu item.
	 */
	public static function add_admin_menu() {
		add_submenu_page(
			'activitypub',
			__( 'FASP Registrations', 'activitypub' ),
			__( 'FASP Registrations', 'activitypub' ),
			'manage_options',
			'activitypub-fasp-registrations',
			array( self::class, 'render_admin_page' )
		);
	}

	/**
	 * Render the admin page.
	 */
	public static function render_admin_page() {
		$pending_registrations  = Fasp_Registration::get_pending_registrations();
		$approved_registrations = Fasp_Registration::get_approved_registrations();
		$highlighted_id         = isset( $_GET['highlight'] ) ? sanitize_text_field( wp_unslash( $_GET['highlight'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'FASP Registrations', 'activitypub' ); ?></h1>

			<?php if ( ! empty( $pending_registrations ) ) : ?>
				<h2><?php esc_html_e( 'Pending Registrations', 'activitypub' ); ?></h2>
				<div class="fasp-registrations-pending">
					<?php foreach ( $pending_registrations as $registration ) : ?>
						<?php self::render_registration_card( $registration, 'pending', $highlighted_id === $registration['fasp_id'] ); ?>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $approved_registrations ) ) : ?>
				<h2><?php esc_html_e( 'Approved Registrations', 'activitypub' ); ?></h2>
				<div class="fasp-registrations-approved">
					<?php foreach ( $approved_registrations as $registration ) : ?>
						<?php self::render_registration_card( $registration, 'approved' ); ?>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php if ( empty( $pending_registrations ) && empty( $approved_registrations ) ) : ?>
				<p><?php esc_html_e( 'No FASP registrations found.', 'activitypub' ); ?></p>
			<?php endif; ?>
		</div>

		<style>
		.fasp-registration-card {
			border: 1px solid #c3c4c7;
			background: #fff;
			margin: 10px 0;
			padding: 15px;
			border-radius: 4px;
		}
		.fasp-registration-card.highlighted {
			border-color: #007cba;
			box-shadow: 0 0 0 1px #007cba;
		}
		.fasp-registration-header {
			display: flex;
			justify-content: space-between;
			align-items: center;
			margin-bottom: 10px;
		}
		.fasp-registration-name {
			font-size: 16px;
			font-weight: 600;
			margin: 0;
		}
		.fasp-registration-actions {
			display: flex;
			gap: 10px;
		}
		.fasp-registration-details {
			display: grid;
			grid-template-columns: 1fr 1fr;
			gap: 15px;
			margin-bottom: 15px;
		}
		.fasp-registration-detail {
			background: #f6f7f7;
			padding: 10px;
			border-radius: 3px;
		}
		.fasp-registration-detail strong {
			display: block;
			margin-bottom: 5px;
		}
		.fasp-fingerprint {
			font-family: monospace;
			font-size: 12px;
			word-break: break-all;
			background: #f0f0f1;
			padding: 5px;
			border-radius: 3px;
		}
		</style>
		<?php
	}

	/**
	 * Render a registration card.
	 *
	 * @param array  $registration Registration data.
	 * @param string $status       Registration status.
	 * @param bool   $highlighted  Whether to highlight this card.
	 */
	private static function render_registration_card( $registration, $status, $highlighted = false ) {
		$fingerprint = Fasp_Registration::get_public_key_fingerprint( $registration['fasp_public_key'] );
		$nonce       = wp_create_nonce( 'fasp_registration_' . $registration['fasp_id'] );

		?>
		<div class="fasp-registration-card <?php echo $highlighted ? 'highlighted' : ''; ?>">
			<div class="fasp-registration-header">
				<h3 class="fasp-registration-name"><?php echo esc_html( $registration['name'] ); ?></h3>
				<div class="fasp-registration-actions">
					<?php if ( 'pending' === $status ) : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display: inline;">
							<input type="hidden" name="action" value="approve_fasp_registration">
							<input type="hidden" name="fasp_id" value="<?php echo esc_attr( $registration['fasp_id'] ); ?>">
							<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( $nonce ); ?>">
							<input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Approve', 'activitypub' ); ?>">
						</form>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display: inline;">
							<input type="hidden" name="action" value="reject_fasp_registration">
							<input type="hidden" name="fasp_id" value="<?php echo esc_attr( $registration['fasp_id'] ); ?>">
							<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( $nonce ); ?>">
							<input type="submit" class="button" value="<?php esc_attr_e( 'Reject', 'activitypub' ); ?>" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to reject this registration?', 'activitypub' ); ?>')">
						</form>
					<?php else : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display: inline;">
							<input type="hidden" name="action" value="delete_fasp_registration">
							<input type="hidden" name="fasp_id" value="<?php echo esc_attr( $registration['fasp_id'] ); ?>">
							<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( $nonce ); ?>">
							<input type="submit" class="button button-link-delete" value="<?php esc_attr_e( 'Delete', 'activitypub' ); ?>" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to delete this registration?', 'activitypub' ); ?>')">
						</form>
					<?php endif; ?>
				</div>
			</div>

			<div class="fasp-registration-details">
				<div class="fasp-registration-detail">
					<strong><?php esc_html_e( 'Base URL:', 'activitypub' ); ?></strong>
					<a href="<?php echo esc_url( $registration['base_url'] ); ?>" target="_blank"><?php echo esc_html( $registration['base_url'] ); ?></a>
				</div>
				<div class="fasp-registration-detail">
					<strong><?php esc_html_e( 'Server ID:', 'activitypub' ); ?></strong>
					<?php echo esc_html( $registration['server_id'] ); ?>
				</div>
				<div class="fasp-registration-detail">
					<strong><?php esc_html_e( 'FASP ID:', 'activitypub' ); ?></strong>
					<?php echo esc_html( $registration['fasp_id'] ); ?>
				</div>
				<div class="fasp-registration-detail">
					<strong><?php esc_html_e( 'Requested:', 'activitypub' ); ?></strong>
					<?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $registration['requested_at'] ) ) ); ?>
				</div>
			</div>

			<div class="fasp-registration-detail">
				<strong><?php esc_html_e( 'Public Key Fingerprint:', 'activitypub' ); ?></strong>
				<div class="fasp-fingerprint"><?php echo esc_html( $fingerprint ); ?></div>
			</div>
		</div>
		<?php
	}

	/**
	 * Handle approve registration action.
	 */
	public static function handle_approve_registration() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'activitypub' ) );
		}

		$fasp_id = isset( $_POST['fasp_id'] ) ? sanitize_text_field( wp_unslash( $_POST['fasp_id'] ) ) : '';
		$nonce   = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'fasp_registration_' . $fasp_id ) ) {
			wp_die( esc_html__( 'Invalid nonce.', 'activitypub' ) );
		}

		$result = Fasp_Registration::approve_registration( $fasp_id, get_current_user_id() );

		if ( $result ) {
			wp_safe_redirect( admin_url( 'admin.php?page=activitypub-fasp-registrations&approved=1' ) );
		} else {
			wp_safe_redirect( admin_url( 'admin.php?page=activitypub-fasp-registrations&error=1' ) );
		}
		exit;
	}

	/**
	 * Handle reject registration action.
	 */
	public static function handle_reject_registration() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'activitypub' ) );
		}

		$fasp_id = isset( $_POST['fasp_id'] ) ? sanitize_text_field( wp_unslash( $_POST['fasp_id'] ) ) : '';
		$nonce   = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'fasp_registration_' . $fasp_id ) ) {
			wp_die( esc_html__( 'Invalid nonce.', 'activitypub' ) );
		}

		$result = Fasp_Registration::reject_registration( $fasp_id, get_current_user_id() );

		if ( $result ) {
			wp_safe_redirect( admin_url( 'admin.php?page=activitypub-fasp-registrations&rejected=1' ) );
		} else {
			wp_safe_redirect( admin_url( 'admin.php?page=activitypub-fasp-registrations&error=1' ) );
		}
		exit;
	}

	/**
	 * Handle delete registration action.
	 */
	public static function handle_delete_registration() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'activitypub' ) );
		}

		$fasp_id = isset( $_POST['fasp_id'] ) ? sanitize_text_field( wp_unslash( $_POST['fasp_id'] ) ) : '';
		$nonce   = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'fasp_registration_' . $fasp_id ) ) {
			wp_die( esc_html__( 'Invalid nonce.', 'activitypub' ) );
		}

		$result = Fasp_Registration::delete_registration( $fasp_id );

		if ( $result ) {
			wp_safe_redirect( admin_url( 'admin.php?page=activitypub-fasp-registrations&deleted=1' ) );
		} else {
			wp_safe_redirect( admin_url( 'admin.php?page=activitypub-fasp-registrations&error=1' ) );
		}
		exit;
	}
}
