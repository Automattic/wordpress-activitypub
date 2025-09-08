<?php
/**
 * Bulk ActivityPub capability removal confirmation template.
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

$user_count = count( $users );

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

$user_count = count( $user_data );

require_once ABSPATH . 'wp-admin/admin-header.php';
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Delete Users from Fediverse', 'activitypub' ); ?></h1>
	<p>
	<?php
	printf(
		/* translators: %d: number of users */
		esc_html( _n( 'ActivityPub capability has been removed from %d user. Do you want to also remove them from the Fediverse?', 'ActivityPub capability has been removed from %d users. Do you want to also remove them from the Fediverse?', $user_count, 'activitypub' ) ),
		esc_html( number_format_i18n( $user_count ) )
	);
	?>
	</p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'bulk-users' ); ?>

		<input type="hidden" name="action" value="remove_activitypub_cap_confirmed" />
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
							<span class="description"><?php esc_html_e( '(Remove from Fediverse)', 'activitypub' ); ?></span>
						</label>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>

		<p><?php esc_html_e( 'Fediverse deletion is optional but recommended to properly notify followers, but be aware that this action might be irreversible.', 'activitypub' ); ?></p>

		<p class="submit">
			<?php submit_button( __( 'Delete from Fediverse', 'activitypub' ), 'primary', 'submit', false ); ?>
			<a href="<?php echo esc_url( $send_back ); ?>" class="button"><?php esc_html_e( 'Skip Fediverse Deletion', 'activitypub' ); ?></a>
		</p>
	</form>
</div>
<?php
require_once ABSPATH . 'wp-admin/admin-footer.php';
