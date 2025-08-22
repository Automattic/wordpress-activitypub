<?php
/**
 * Block confirmation template.
 *
 * @package Activitypub
 */

use Activitypub\Collection\Actors;

/* @var array $args Template arguments. */
$args = wp_parse_args( $args ?? array() );

$actor_id = $args['actor_id'];
$user_id  = $args['user_id'];

// Get actor and validate.
$actor = Actors::get_actor( $actor_id );
if ( \is_wp_error( $actor ) ) {
	\wp_die( \esc_html__( 'Invalid account.', 'activitypub' ), '', array( 'back_link' => true ) );
}

// Prepare form URL.
$base_url = \add_query_arg(
	array(
		'action'   => 'block',
		'follower' => $actor_id,
		'confirm'  => 'true',
	)
);

$can_block_site = \user_can( $user_id, 'manage_options' );
$nonce_action   = 'block-follower_' . $actor_id;

require_once ABSPATH . 'wp-admin/admin-header.php';
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Block Account', 'activitypub' ); ?></h1>

	<p>
	<?php
	printf(
		/* translators: %s: username */
		esc_html__( 'You are about to block &#8220;%s&#8221;.', 'activitypub' ),
		'<strong>' . esc_html( $actor->get_preferred_username() ) . '</strong>'
	);
	?>
	</p>
	<p><?php esc_html_e( 'This will:', 'activitypub' ); ?></p>
	<ul class="ul-disc">
		<li><?php esc_html_e( 'Block incoming requests from this account for you.', 'activitypub' ); ?></li>
		<li><?php esc_html_e( 'Remove them from your followers and following lists.', 'activitypub' ); ?></li>
	</ul>

	<form method="post" action="<?php echo esc_url( $base_url ); ?>">
		<?php wp_nonce_field( $nonce_action ); ?>

		<?php if ( $can_block_site ) : ?>
			<p>
				<label>
					<input type="checkbox" name="site_wide" value="1" />
					<?php esc_html_e( 'Also block this account site-wide (affects all users and the blog actor).', 'activitypub' ); ?>
				</label>
			</p>
		<?php endif; ?>

		<p><?php esc_html_e( 'You can unblock this account later in the ActivityPub moderation settings.', 'activitypub' ); ?></p>

		<p class="submit">
			<?php submit_button( __( 'Confirm Block', 'activitypub' ), 'primary', 'submit', false ); ?>
			<a href="<?php echo esc_url( wp_get_referer() ); ?>" class="button"><?php esc_html_e( 'Cancel', 'activitypub' ); ?></a>
		</p>
	</form>
</div>
<?php
require_once ABSPATH . 'wp-admin/admin-footer.php';
