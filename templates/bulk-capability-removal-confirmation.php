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

	// Check if user has ActivityPub capability.
	if ( ! user_can( $user_id, 'activitypub' ) ) {
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

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'bulk-users' ); ?>

		<input type="hidden" name="action" value="remove_activitypub_cap_confirmed" />
		<input type="hidden" name="send_back" value="<?php echo esc_url( $send_back ); ?>" />

		<?php foreach ( $user_data as $user ) : ?>
			<input type="hidden" name="selected_users[]" value="<?php echo esc_attr( $user['id'] ); ?>" />
		<?php endforeach; ?>

		<div class="activitypub-user-list">
			<h3><?php esc_html_e( 'Users to Process:', 'activitypub' ); ?></h3>

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

		<div class="notice notice-info">
			<h3><?php esc_html_e( 'Fediverse Removal Options', 'activitypub' ); ?></h3>
			<p><?php esc_html_e( 'For each user above, you can choose whether to also remove them from the Fediverse by checking the box next to their name. This will send Delete activities to notify their followers.', 'activitypub' ); ?></p>
		</div>

		<div class="activitypub-consequences">
			<h4><?php esc_html_e( 'This action will:', 'activitypub' ); ?></h4>
			<ul class="ul-disc">
				<li><?php esc_html_e( 'Remove the ActivityPub capability from the selected users', 'activitypub' ); ?></li>
				<li><?php esc_html_e( 'Prevent them from publishing new ActivityPub content', 'activitypub' ); ?></li>
				<li><?php esc_html_e( 'Hide ActivityPub settings from their profile pages', 'activitypub' ); ?></li>
				<li id="delete-consequences" style="display: none;"><?php esc_html_e( 'Send Delete activities to notify all followers', 'activitypub' ); ?></li>
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
	const fediverseCheckboxes = document.querySelectorAll('.fediverse-removal-checkbox');
	const deleteConsequences = document.getElementById('delete-consequences');

	function updateDeleteConsequences() {
		const hasDeleteAction = Array.from(fediverseCheckboxes).some(checkbox => checkbox.checked);
		if (deleteConsequences) {
			deleteConsequences.style.display = hasDeleteAction ? 'list-item' : 'none';
		}
	}

	fediverseCheckboxes.forEach(function(checkbox) {
		checkbox.addEventListener('change', updateDeleteConsequences);
	});

	// Initial check
	updateDeleteConsequences();
});
</script>

<?php
require_once ABSPATH . 'wp-admin/admin-footer.php';
