<?php
/**
 * Bulk block confirmation template.
 *
 * @package Activitypub
 */

use Activitypub\Collection\Actors;

/* @var array $args Template arguments. */
$args = wp_parse_args( $args ?? array() );

$followers     = $args['followers'];
$user_id       = $args['user_id'];
$plural_args   = $args['plural_args'];

// Validate followers.
if ( empty( $followers ) ) {
	\wp_die( \esc_html__( 'No accounts selected.', 'activitypub' ), '', array( 'back_link' => true ) );
}

$follower_count = count( $followers );

// Prepare form URL.
$base_url = \add_query_arg(
	array(
		'action'    => 'block',
		'followers' => $followers,
		'confirm'   => 'true',
	)
);

// Prepare follower data for display.
$follower_data = array();
foreach ( $followers as $follower ) {
	$actor = Actors::get_actor( $follower );
	if ( \is_wp_error( $actor ) ) {
		continue;
	}
	$follower_data[] = array(
		'username' => $actor->get_preferred_username(),
	);
}

$can_block_site = \user_can( $user_id, 'manage_options' );
$nonce_action   = 'bulk-' . $plural_args;

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
		<?php foreach ( $follower_data as $follower ) : ?>
			<li><strong><?php echo esc_html( $follower['username'] ); ?></strong></li>
		<?php endforeach; ?>
	</ul>
	<p><?php esc_html_e( 'This will:', 'activitypub' ); ?></p>
	<ul class="ul-disc">
		<li><?php esc_html_e( 'Block incoming requests from these accounts for you.', 'activitypub' ); ?></li>
		<li><?php esc_html_e( 'Remove them from your followers and following lists.', 'activitypub' ); ?></li>
	</ul>

	<form method="post" action="<?php echo esc_url( $base_url ); ?>">
		<?php wp_nonce_field( $nonce_action ); ?>

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
