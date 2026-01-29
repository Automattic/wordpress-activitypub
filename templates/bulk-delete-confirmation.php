<?php
/**
 * Bulk ActivityPub soft delete confirmation template.
 *
 * Handles both user and post soft deletion from the Fediverse.
 *
 * @package Activitypub
 */

/* @var array $args Template arguments. */
$args = wp_parse_args(
	$args ?? array(),
	array(
		'type'         => 'posts',
		'items'        => array(),
		'send_back'    => '',
		'checked'      => true,
		'cancel_label' => __( 'Cancel', 'activitypub' ),
	)
);

$item_type    = $args['type'];
$item_ids     = $args['items'];
$send_back    = $args['send_back'];
$checked      = $args['checked'];
$cancel_label = $args['cancel_label'];

// Validate items - redirect back with notice if empty.
if ( empty( $item_ids ) ) {
	$notice_param = 'users' === $item_type ? 'activitypub_no_users' : 'activitypub_no_posts';
	wp_safe_redirect( add_query_arg( $notice_param, '1', $send_back ) );
	exit;
}

// Get items based on type.
$items = array();
if ( 'users' === $item_type ) {
	$items = get_users( array( 'include' => $item_ids ) );
} else {
	foreach ( $item_ids as $item_id ) {
		$item = get_post( $item_id );
		if ( $item && current_user_can( 'edit_post', $item_id ) ) {
			$items[] = $item;
		}
	}
}

// If no valid items, redirect back with notice.
if ( empty( $items ) ) {
	$notice_param = 'users' === $item_type ? 'activitypub_no_users' : 'activitypub_no_posts';
	wp_safe_redirect( add_query_arg( $notice_param, '1', $send_back ) );
	exit;
}

// Set up type-specific variables.
if ( 'users' === $item_type ) {
	$page_title   = __( 'Delete Users from Fediverse', 'activitypub' );
	$description  = __( 'You have removed the capability to publish to the Fediverse for the selected users. Do you also want to send a Delete activity to remove them from the Fediverse?', 'activitypub' );
	$note         = __( '<strong>Note:</strong> This sends a Delete activity to notify remote servers that these profiles no longer exist.', 'activitypub' );
	$nonce_action = 'bulk-users';
	$form_action  = 'delete_actor_confirmed';
	$input_name   = 'remove_from_fediverse[]';
	$hidden_name  = 'selected_users[]';
	$columns      = array(
		'name' => __( 'Name', 'activitypub' ),
	);
} else {
	$page_title   = __( 'Delete Posts from Fediverse', 'activitypub' );
	$description  = __( 'You are about to send Delete activities for the following posts. This will remove them from the Fediverse while keeping them on your site.', 'activitypub' );
	$note         = __( '<strong>Note:</strong> This sends a Delete activity to notify remote servers. The posts will remain on your WordPress site.', 'activitypub' );
	$nonce_action = 'activitypub-bulk-post-delete';
	$form_action  = 'activitypub_delete_posts_confirmed';
	$input_name   = 'selected_posts[]';
	$hidden_name  = '';
	$columns      = array(
		'title'  => __( 'Title', 'activitypub' ),
		'author' => __( 'Author', 'activitypub' ),
		'date'   => __( 'Date', 'activitypub' ),
	);
}

$GLOBALS['plugin_page'] = ''; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
require_once ABSPATH . 'wp-admin/admin-header.php';
?>
<div class="wrap">
	<h1><?php echo esc_html( $page_title ); ?></h1>
	<p><?php echo esc_html( $description ); ?></p>
	<p><?php echo wp_kses( $note, array( 'strong' => array() ) ); ?></p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( $nonce_action ); ?>

		<input type="hidden" name="action" value="<?php echo esc_attr( $form_action ); ?>" />
		<input type="hidden" name="send_back" value="<?php echo esc_url( $send_back ); ?>" />

		<table class="widefat fixed striped">
			<thead>
				<tr>
					<td class="manage-column column-cb check-column">
						<input type="checkbox" id="cb-select-all"<?php echo $checked ? ' checked' : ''; ?> />
					</td>
					<?php foreach ( $columns as $column_key => $column_label ) : ?>
						<th scope="col" class="manage-column column-<?php echo esc_attr( $column_key ); ?>">
							<?php echo esc_html( $column_label ); ?>
						</th>
					<?php endforeach; ?>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $items as $item ) : ?>
					<tr>
						<th scope="row" class="check-column">
							<input type="checkbox" name="<?php echo esc_attr( $input_name ); ?>" value="<?php echo esc_attr( $item->ID ); ?>"<?php echo $checked ? ' checked' : ''; ?> />
							<?php if ( $hidden_name ) : ?>
								<input type="hidden" name="<?php echo esc_attr( $hidden_name ); ?>" value="<?php echo esc_attr( $item->ID ); ?>" />
							<?php endif; ?>
						</th>
						<?php if ( 'users' === $item_type ) : ?>
							<td>
								<strong><?php echo esc_html( $item->display_name ); ?></strong>
								<br>
								<span class="description"><?php echo esc_html( $item->user_email ); ?></span>
							</td>
						<?php else : ?>
							<td class="column-title">
								<strong><?php echo esc_html( get_the_title( $item ) ); ?></strong>
							</td>
							<td class="column-author">
								<?php echo esc_html( get_the_author_meta( 'display_name', $item->post_author ) ); ?>
							</td>
							<td class="column-date">
								<?php echo esc_html( get_the_date( '', $item ) ); ?>
							</td>
						<?php endif; ?>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<p class="submit">
			<?php submit_button( __( 'Delete from Fediverse', 'activitypub' ), 'primary', 'submit', false ); ?>
			<a href="<?php echo esc_url( $send_back ); ?>" class="button"><?php echo esc_html( $cancel_label ); ?></a>
		</p>
	</form>
</div>
<script>
document.getElementById('cb-select-all').addEventListener('change', function() {
	var checkboxes = document.querySelectorAll('input[name="<?php echo esc_js( $input_name ); ?>"]');
	for (var i = 0; i < checkboxes.length; i++) {
		checkboxes[i].checked = this.checked;
	}
});
</script>
<?php
require_once ABSPATH . 'wp-admin/admin-footer.php';
