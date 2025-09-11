<?php
/**
 * Bulk ActivityPub actor deletion confirmation template.
 *
 * @package Activitypub
 */

/* @var array $args Template arguments. */
$args = wp_parse_args( $args ?? array() );

$users     = $args['users'] ?? array();
$send_back = $args['send_back'] ?? '';

// Validate users.
if ( empty( $users ) ) {
	wp_die( esc_html__( 'No users selected.', 'activitypub' ), '', array( 'back_link' => true ) );
}

// Prepare user data for display.
$users = get_users( array( 'include' => $users ) );

// If no users with ActivityPub capability, redirect back.
if ( ! $users ) {
	wp_safe_redirect( $send_back );
	exit;
}

$GLOBALS['plugin_page'] = ''; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
require_once ABSPATH . 'wp-admin/admin-header.php';
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Delete Users from Fediverse', 'activitypub' ); ?></h1>
	<p><?php esc_html_e( 'You&#8217;ve just removed the capability to publish to the Fediverse for the selected users, do you want to also remove them from the Fediverse?', 'activitypub' ); ?></p>
	<p><?php echo wp_kses( __( 'Fediverse deletion is optional but recommended to properly notify your followers. <strong>This action is irreversible.</strong>', 'activitypub' ), array( 'strong' => array() ) ); ?></p>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'bulk-users' ); ?>

		<input type="hidden" name="action" value="delete_actor_confirmed" />
		<input type="hidden" name="send_back" value="<?php echo esc_url( $send_back ); ?>" />

		<div class="activitypub-user-list">
			<ul>
				<?php foreach ( $users as $user ) : ?>
					<li>
						<label>
							<input type="checkbox" name="remove_from_fediverse[]" value="<?php echo esc_attr( $user->ID ); ?>" class="fediverse-removal-checkbox" />
							<input type="hidden" name="selected_users[]" value="<?php echo esc_attr( $user->ID ); ?>" />
							<strong><?php echo esc_html( $user->display_name ); ?></strong>
						</label>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>

		<p class="submit">
			<?php submit_button( __( 'Delete from Fediverse', 'activitypub' ), 'primary', 'submit', false ); ?>
			<a href="<?php echo esc_url( $send_back ); ?>" class="button"><?php esc_html_e( 'Skip', 'activitypub' ); ?></a>
		</p>
	</form>
</div>
<?php
require_once ABSPATH . 'wp-admin/admin-footer.php';
