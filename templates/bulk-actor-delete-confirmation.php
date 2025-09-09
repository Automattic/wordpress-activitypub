<?php
/**
 * Bulk ActivityPub actor deletion confirmation template.
 *
 * @package Activitypub
 */

use Activitypub\Collection\Actors;

/* @var array $args Template arguments. */
$args = wp_parse_args( $args ?? array() );

$users     = $args['users'] ?? array();
$send_back = $args['send_back'] ?? '';

// Validate users.
if ( empty( $users ) ) {
	wp_die( esc_html__( 'No users selected.', 'activitypub' ), '', array( 'back_link' => true ) );
}

// Prepare user data for display.
$user_data = array();
foreach ( $users as $user_id ) {
	$user = get_user_by( 'id', $user_id );
	if ( ! $user ) {
		continue;
	}

	$user_data[] = array(
		'id'           => $user_id,
		'login'        => $user->user_login,
		'display_name' => $user->display_name,
		'email'        => $user->user_email,
	);
}

// If no users with ActivityPub capability, redirect back.
if ( empty( $user_data ) ) {
	wp_safe_redirect( $send_back );
	exit;
}

require_once ABSPATH . 'wp-admin/admin-header.php';
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Delete Users from Fediverse', 'activitypub' ); ?></h1>
	<p><?php echo wp_kses( __( 'Fediverse deletion is optional but recommended to properly notify followers, <strong>but be aware that this action might be irreversible.</strong>', 'activitypub' ), array( 'strong' => array() ) ); ?></p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'bulk-users' ); ?>

		<input type="hidden" name="action" value="delete_actor_confirmed" />
		<input type="hidden" name="send_back" value="<?php echo esc_url( $send_back ); ?>" />

		<?php foreach ( $user_data as $user ) : ?>
			<input type="hidden" name="selected_users[]" value="<?php echo esc_attr( $user['id'] ); ?>" />
		<?php endforeach; ?>

		<div class="activitypub-user-list">
			<ul>
				<?php foreach ( $user_data as $user ) : ?>
					<li>
						<label>
							<input type="checkbox" name="remove_from_fediverse[]" value="<?php echo esc_attr( $user['id'] ); ?>" class="fediverse-removal-checkbox" />
							<strong><?php echo esc_html( $user['display_name'] ); ?></strong>
						</label>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>

		<p class="submit">
			<?php submit_button( __( 'Delete from Fediverse', 'activitypub' ), 'primary', 'submit', false ); ?>
			<a href="<?php echo esc_url( $send_back ); ?>" class="button"><?php esc_html_e( 'Skip', 'activitypub' ); ?></a>
		</p>

		<p><?php esc_html_e( 'The users will not be deleted from your WordPress installation.', 'activitypub' ); ?></p>
	</form>
</div>
<?php
require_once ABSPATH . 'wp-admin/admin-footer.php';
