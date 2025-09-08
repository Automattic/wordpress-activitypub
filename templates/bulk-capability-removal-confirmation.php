<?php
/**
 * Bulk ActivityPub capability removal confirmation template.
 *
 * @package Activitypub
 */

use Activitypub\Collection\Actors;

/* @var array $args Template arguments. */
$args = wp_parse_args( $args ?? array() );

$users = $args['users'] ?? array();
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
	
	// Check if user has ActivityPub capability
	if ( ! user_can( $user_id, 'activitypub' ) ) {
		continue;
	}
	
	$user_data[] = array(
		'id' => $user_id,
		'login' => $user->user_login,
		'display_name' => $user->display_name,
		'email' => $user->user_email,
	);
}

// If no users with ActivityPub capability, redirect back
if ( empty( $user_data ) ) {
	wp_safe_redirect( $send_back );
	exit;
}

$user_count = count( $user_data );

require_once ABSPATH . 'wp-admin/admin-header.php';
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Remove ActivityPub Capability', 'activitypub' ); ?></h1>
	<p>
	<?php
	printf(
		/* translators: %d: number of users */
		esc_html( _n( 'You are about to remove ActivityPub capability from %d user.', 'You are about to remove ActivityPub capability from %d users.', $user_count, 'activitypub' ) ),
		esc_html( number_format_i18n( $user_count ) )
	);
	?>
	</p>
	
	<div class="activitypub-user-list">
		<h3><?php esc_html_e( 'Affected Users:', 'activitypub' ); ?></h3>
		<ul>
			<?php foreach ( $user_data as $user ) : ?>
				<li>
					<strong><?php echo esc_html( $user['display_name'] ); ?></strong> 
					(<?php echo esc_html( $user['login'] ); ?>) - 
					<em><?php echo esc_html( $user['email'] ); ?></em>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>

	<div class="notice notice-warning">
		<h3><?php esc_html_e( 'Important: Fediverse Removal Options', 'activitypub' ); ?></h3>
		<p><?php esc_html_e( 'When you remove the ActivityPub capability, you can choose what happens to the user\'s presence in the Fediverse:', 'activitypub' ); ?></p>
	</div>

	<form method="post" action="<?php echo esc_url( admin_url( 'users.php' ) ); ?>">
		<?php wp_nonce_field( 'bulk-users' ); ?>
		
		<?php foreach ( $user_data as $user ) : ?>
			<input type="hidden" name="users[]" value="<?php echo esc_attr( $user['id'] ); ?>" />
		<?php endforeach; ?>
		
		<input type="hidden" name="action" value="remove_activitypub_cap_confirmed" />
		<input type="hidden" name="send_back" value="<?php echo esc_url( $send_back ); ?>" />

		<h3><?php esc_html_e( 'Fediverse Removal Options:', 'activitypub' ); ?></h3>
		
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Remove from Fediverse', 'activitypub' ); ?></th>
				<td>
					<fieldset>
						<legend class="screen-reader-text"><?php esc_html_e( 'Fediverse removal options', 'activitypub' ); ?></legend>
						
						<label>
							<input type="radio" name="fediverse_action" value="keep" checked="checked" />
							<strong><?php esc_html_e( 'Keep in Fediverse (Recommended)', 'activitypub' ); ?></strong>
							<p class="description">
								<?php esc_html_e( 'Only remove the ActivityPub capability. The user\'s profile and posts will remain discoverable in the Fediverse, but they won\'t be able to publish new ActivityPub content.', 'activitypub' ); ?>
							</p>
						</label>
						<br><br>
						
						<label>
							<input type="radio" name="fediverse_action" value="delete" />
							<strong><?php esc_html_e( 'Remove from Fediverse', 'activitypub' ); ?></strong>
							<p class="description">
								<?php esc_html_e( 'Send Delete activities to notify followers that the user is no longer available. This will remove the user from followers\' lists across the Fediverse. This action cannot be undone easily.', 'activitypub' ); ?>
							</p>
						</label>
					</fieldset>
				</td>
			</tr>
		</table>

		<div class="activitypub-consequences">
			<h4><?php esc_html_e( 'This action will:', 'activitypub' ); ?></h4>
			<ul class="ul-disc">
				<li><?php esc_html_e( 'Remove the ActivityPub capability from the selected users', 'activitypub' ); ?></li>
				<li><?php esc_html_e( 'Prevent them from publishing new ActivityPub content', 'activitypub' ); ?></li>
				<li><?php esc_html_e( 'Hide ActivityPub settings from their profile pages', 'activitypub' ); ?></li>
				<li id="delete-consequences" style="display: none;"><?php esc_html_e( 'Send Delete activities to notify all followers (if "Remove from Fediverse" is selected)', 'activitypub' ); ?></li>
			</ul>
		</div>

		<p class="submit">
			<?php submit_button( __( 'Confirm Removal', 'activitypub' ), 'primary', 'submit', false ); ?>
			<a href="<?php echo esc_url( $send_back ); ?>" class="button"><?php esc_html_e( 'Cancel', 'activitypub' ); ?></a>
		</p>
	</form>
</div>

<script type="text/javascript">
document.addEventListener('DOMContentLoaded', function() {
	const radioButtons = document.querySelectorAll('input[name="fediverse_action"]');
	const deleteConsequences = document.getElementById('delete-consequences');
	
	radioButtons.forEach(function(radio) {
		radio.addEventListener('change', function() {
			if (this.value === 'delete') {
				deleteConsequences.style.display = 'list-item';
			} else {
				deleteConsequences.style.display = 'none';
			}
		});
	});
});
</script>

<?php
require_once ABSPATH . 'wp-admin/admin-footer.php';
