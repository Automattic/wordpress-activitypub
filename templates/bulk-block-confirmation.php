<?php
/**
 * Bulk block confirmation template.
 *
 * @package Activitypub
 */

/* @var array $args Template arguments. */
$args = wp_parse_args( $args ?? array() );

$follower_count = $args['follower_count'];
$can_block_site = $args['can_block_site'] ?? false;

require_once ABSPATH . 'wp-admin/admin-header.php';
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Block Accounts', 'activitypub' ); ?></h1>
	<p>
	<?php
	printf(
		/* translators: %d: number of followers */
		esc_html( _n( 'You are about to block %d accounts.', 'You are about to block %d accounts.', $follower_count, 'activitypub' ) ),
		esc_html( number_format_i18n( $follower_count ) )
	);
	?>
	</p>
	<ul>
		<?php foreach ( $args['followers'] as $follower_data ) : ?>
			<li><strong><?php echo esc_html( $follower_data['username'] ); ?></strong></li>
		<?php endforeach; ?>
	</ul>
	<p><?php esc_html_e( 'This will:', 'activitypub' ); ?></p>
	<ul class="ul-disc">
		<li><?php esc_html_e( 'Block incoming requests from these accounts for you.', 'activitypub' ); ?></li>
		<li><?php esc_html_e( 'Remove them from your followers and following lists.', 'activitypub' ); ?></li>
	</ul>

	<form method="post" action="<?php echo esc_url( $args['base_url'] ); ?>">
		<?php wp_nonce_field( $args['nonce_action'] ); ?>

		<?php if ( $can_block_site ) : ?>
			<p>
				<label>
					<input type="checkbox" name="site_wide" value="1" />
					<?php esc_html_e( 'Also block these accounts site-wide (affects all users and the blog actor)', 'activitypub' ); ?>
				</label>
			</p>
		<?php endif; ?>

		<p><?php esc_html_e( 'You can unblock these accounts later in the ActivityPub moderation settings.', 'activitypub' ); ?></p>

		<p class="submit">
			<?php submit_button( __( 'Confirm Block', 'activitypub' ), 'primary', 'submit', false ); ?>
			<a href="<?php echo esc_url( wp_get_referer() ); ?>" class="button"><?php esc_html_e( 'Cancel', 'activitypub' ); ?></a>
		</p>
	</form>
</div>
<?php
require_once ABSPATH . 'wp-admin/admin-footer.php';
